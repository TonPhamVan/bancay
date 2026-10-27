<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\ChangeOrderStatus;
use App\Actions\Commerce\CreateOrder;
use App\Domain\Commerce\OrderStatus;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Real independent database connections contend on InnoDB rows, not sequential mocks. */
final class StoreConcurrencyTest extends TestCase
{
    public function test_parallel_stock_order_status_and_sku_writes_preserve_invariants(): void
    {
        $this->assertTrue(function_exists('pcntl_fork'), 'Run in the documented PHP Docker image.');
        $this->artisan('migrate:fresh')->assertSuccessful();
        try {
            $category = Category::factory()->create();
            $make = fn (string $slug) => app(CreateProduct::class)->execute(['name' => 'Concurrency synthetic', 'slug' => $slug,
                'category_id' => $category->id, 'status' => 'PUBLISHED', 'published_at' => now()->subDay()->toDateTimeString()]);
            $product = $make('concurrent');
            $variant = app(CreateVariant::class)->execute($product, ['sku' => 'RACE-STOCK', 'pricing_type' => 'FIXED', 'price' => 99000, 'quantity' => 10]);
            $result = $this->parallel(fn () => app(InventoryService::class)->adjust($variant->id, -8));
            $this->assertSame(['ok', 'rejected'], $result);
            $this->assertSame(2, Inventory::firstOrFail()->quantity);
            app(InventoryService::class)->adjust($variant->id, 8);
            $input = ['customer_name' => 'Concurrency synthetic', 'customer_phone' => '0900000000',
                'shipping_address' => ['line1' => 'Synthetic', 'country_code' => 'VN'],
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 7]]];
            $this->assertSame(['ok', 'rejected'], $this->parallel(fn () => app(CreateOrder::class)->execute($input)));
            $this->assertSame(1, Order::count());
            $this->assertSame(7, Inventory::firstOrFail()->reserved_quantity);
            $order = Order::firstOrFail();
            $this->assertSame(['ok', 'rejected'], $this->parallel(fn ($index) => app(ChangeOrderStatus::class)->execute(
                $order, $index === 0 ? OrderStatus::Confirmed : OrderStatus::Cancelled, expected: OrderStatus::New)));
            $this->assertSame(2, $order->statusHistories()->count());
            $a = $make('sku-race-a');
            $b = $make('sku-race-b');
            $this->assertSame(['ok', 'rejected'], $this->parallel(fn ($index) => app(CreateVariant::class)->execute($index === 0 ? $a : $b,
                ['sku' => 'RACE-UNIQUE', 'pricing_type' => 'CONTACT', 'quantity' => 0])));
            $this->assertSame(1, ProductVariant::where('sku', 'RACE-UNIQUE')->count());
        } finally {
            // This suite owns the dedicated bancay_test database, with no enclosing transaction.
            $this->artisan('migrate:fresh');
            RefreshDatabaseState::$migrated = false;
        }
    }

    private function parallel(callable $operation): array
    {
        DB::disconnect();
        $files = [];
        $pids = [];
        for ($index = 0; $index < 2; $index++) {
            $file = tempnam(storage_path('framework/testing'), 'race-');
            $files[] = $file;
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Unable to create contention worker.');
            }
            if ($pid === 0) {
                DB::purge();
                try {
                    $operation($index);
                    file_put_contents($file, 'ok');
                } catch (ValidationException|QueryException $e) {
                    // A uniqueness race is an expected rejection, not an infrastructure failure.
                    if ($e instanceof QueryException && ($e->errorInfo[1] ?? null) !== 1062) {
                        file_put_contents($file, 'database-error');
                    } else {
                        file_put_contents($file, 'rejected');
                    }
                } catch (\Throwable) {
                    file_put_contents($file, 'worker-error');
                }
                DB::disconnect();
                exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();
        $result = array_map('file_get_contents', $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($result);

        return $result;
    }
}
