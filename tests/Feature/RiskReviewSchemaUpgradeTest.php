<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DDL tests own bancay_test without an enclosing transaction; never touch operational data. */
final class RiskReviewSchemaUpgradeTest extends TestCase
{
    public function test_upgrade_preflight_and_rollback_preserve_existing_rows(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $migration = require database_path('migrations/2026_10_28_000002_harden_money_domain_checks.php');
        try {
            $migration->down();
            $product = app(CreateProduct::class)->execute(['name' => 'Synthetic upgrade fixture', 'slug' => 'upgrade-fixture', 'category_id' => Category::factory()->create()->id]);
            $variant = app(CreateVariant::class)->execute($product, ['sku' => 'UPGRADE-1', 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 10]);
            DB::table('product_variants')->where('id', $variant->id)->update(['price' => null]);
            try {
                $migration->up();
                $this->fail('Preflight should reject invalid existing data');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('preflight failed', $e->getMessage());
            }
            $this->assertSame(0, (int) DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('variants_money_domain_check','orders_money_domain_check','items_money_domain_check')")->n);
            DB::table('product_variants')->where('id', $variant->id)->update(['price' => 100000]);
            $migration->up();
            $this->assertSame(100000, $variant->fresh()->price);
            $this->assertSame(10, $variant->inventory->quantity);
            $migration->down();
            $this->assertDatabaseCount('product_variants', 1);
            $migration->up();
            $this->assertSame(100000, $variant->fresh()->price);
        } finally {
            $this->artisan('migrate:fresh');
            RefreshDatabaseState::$migrated = false;
        }
    }
}
