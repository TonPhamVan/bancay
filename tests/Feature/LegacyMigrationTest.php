<?php

namespace Tests\Feature;

use App\Actions\Commerce\ChangeOrderStatus;
use App\Domain\Commerce\OrderStatus;
use App\Legacy\ContentMediaUrls;
use App\Legacy\Media;
use App\Legacy\MigrationFailure;
use App\Legacy\Reconciler;
use App\Legacy\Reports\RunReport;
use App\Legacy\ReviewedMediaRepair;
use App\Legacy\ReviewOverrides;
use App\Legacy\Source;
use App\Legacy\TargetGuard;
use App\Legacy\Transformers\ProductTransformer;
use App\Legacy\Transformers\Values;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class LegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $private;

    private FixtureLegacySource $source;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->private = base_path('legacy-private/phase4/tests/'.Str::ulid());
        File::ensureDirectoryExists($this->private.'/media/upload/sp/112023');
        $image = imagecreatetruecolor(4, 4);
        imagepng($image, $this->private.'/media/upload/sp/112023/cover.png');
        imagedestroy($image);
        foreach (['source.sql', 'source.zip', 'target.sql'] as $file) {
            file_put_contents($this->private.'/'.$file, 'synthetic backup guard fixture');
        }
        $proof = ['status' => 'BACKUP VERIFIED', 'target_database' => 'bancay_test'];
        foreach (['source_sql' => 'source.sql', 'source_zip' => 'source.zip', 'target_backup' => 'target.sql'] as $key => $file) {
            $proof[$key] = ['path' => $this->private.'/'.$file, 'sha256' => hash_file('sha256', $this->private.'/'.$file)];
        }
        file_put_contents($this->private.'/proof.json', json_encode($proof));
        config(['app.debug' => false, 'legacy.enabled' => true, 'legacy.review_manifest' => null, 'legacy.target_database' => 'bancay_test', 'legacy.report_directory' => $this->private,
            'legacy.backup_manifest' => $this->private.'/proof.json', 'legacy.media_root' => $this->private.'/media']);
        $this->source = new FixtureLegacySource;
        $this->source->data = $this->fixtures();
        $this->app->instance(Source::class, $this->source);
    }

    protected function tearDown(): void
    {
        if (isset($this->private) && str_starts_with($this->private, base_path('legacy-private/phase4/tests/'))) {
            File::deleteDirectory($this->private);
        }
        parent::tearDown();
    }

    private function fixtures(): array
    {
        $category = ['id' => 1, 'parent' => 0, 'name' => 'Synthetic category', 'alias' => 'synthetic', 'mota' => str_repeat('x', 25000), 'image' => '', 'thutu' => 0, 'vip' => 1, 'status' => 1];
        $simple = ['id' => 10, 'title' => 'Synthetic current catalog', 'alias' => 'synthetic', 'cid' => 1, 'phanloai' => '', 'image' => '["cover.png"]', 'ma' => 'SYN-10', 'giaban' => 120000, 'giale' => 0, 'sl' => 10,
            'created_at' => 1700000000, 'sapo' => '<p>Safe</p>', 'content' => '<p>Safe</p>', 'vip' => 1, 'status' => 1, 'meta_title' => '', 'meta_des' => ''];
        $variant = array_replace($simple, ['id' => 11, 'ma' => '', 'phanloai' => json_encode(['pl' => ['Size', 'Pot'], 'pl1' => ['S', 'M'], 'pl2' => ['A', 'B'], 'img' => ['cover.png', 'cover.png'],
            'price_pl' => ['100,000', '200,000'], 'sale_pl' => ['80,000', ''], 'ma_pl' => ['DUP', 'DUP'], 'sl_pl' => ['3', '4']])]);
        $order = ['id' => 20, 'product_id' => 10, 'product_name' => 'Recorded historical product', 'giaban' => 100000, 'soluong' => 2, 'phanloai' => 'Recorded variant text', 'status' => 1,
            'hoten' => 'Historical synthetic', 'dienthoai' => '0900000000', 'email' => 'history@example.test', 'diachi' => 'Synthetic address', 'ghichu' => '', 'ngaytao' => 1700000000];

        return ['tbldanhmuc' => [$category], 'tblsanpham' => [$simple, $variant], 'tblorder' => [$order]];
    }

    private function migrate(): void
    {
        $this->artisan('legacy:migrate', ['--dry-run' => true, '--include-orders' => true])->assertSuccessful();
        $this->artisan('legacy:migrate', ['--force' => true, '--include-orders' => true])->assertSuccessful();
    }

    public function test_dry_run_never_writes_domain_or_public_files_and_write_needs_receipt(): void
    {
        $before = app(TargetGuard::class)->fingerprint();
        $this->artisan('legacy:migrate', ['--force' => true, '--include-orders' => true])->assertFailed();
        $this->artisan('legacy:migrate', ['--dry-run' => true, '--include-orders' => true])->assertSuccessful();
        $this->assertSame($before, app(TargetGuard::class)->fingerprint());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_normalized_catalog_history_and_idempotent_rerun_preserve_snapshots(): void
    {
        $this->migrate();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_variants', 3);
        $this->assertDatabaseCount('inventories', 3);
        $this->assertDatabaseCount('product_options', 2);
        $this->assertDatabaseCount('product_variant_values', 4);
        $this->assertDatabaseHas('product_variants', ['sku' => 'BC-L11-V1', 'legacy_sku' => 'DUP', 'price' => 100000, 'sale_price' => 80000]);
        $this->assertDatabaseHas('product_variants', ['sku' => 'BC-L11-V2', 'legacy_variant_index' => 1]);
        $this->assertSame(str_repeat('x', 25000), strip_tags(Category::first()->description));
        $order = Order::firstOrFail();
        $this->assertTrue($order->is_legacy);
        $this->assertSame('LEG-20', $order->order_number);
        $this->assertSame('Recorded historical product', $order->items[0]->product_name);
        $this->assertSame(100000, $order->items[0]->unit_price);
        $this->assertSame(200000, $order->total);
        $this->assertNull($order->items[0]->sku);
        $this->assertNull($order->items[0]->product_variant_id);
        $this->assertSame(0, Inventory::sum('reserved_quantity'));
        $this->assertCount(1, $order->statusHistories);
        $this->assertSame(1700000000, $order->created_at->getTimestamp());
        $this->assertSame('PASS', app(Reconciler::class)->run()['status']);
        $ids = ProductVariant::orderBy('id')->pluck('id')->all();
        $stock = Inventory::orderBy('id')->pluck('quantity')->all();
        $files = Storage::disk('public')->allFiles();
        $this->migrate();
        $this->assertSame($ids, ProductVariant::orderBy('id')->pluck('id')->all());
        $this->assertSame($stock, Inventory::orderBy('id')->pluck('quantity')->all());
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_stale_target_or_changed_source_invalidates_dry_run_receipt(): void
    {
        $this->artisan('legacy:migrate', ['--dry-run' => true])->assertSuccessful();
        Category::factory()->create();
        $this->artisan('legacy:migrate', ['--force' => true])->assertFailed();
        $this->artisan('legacy:migrate', ['--dry-run' => true])->assertSuccessful();
        $this->source->data['tblsanpham'][0]['title'] = 'Changed synthetic input';
        $this->artisan('legacy:migrate', ['--force' => true])->assertFailed();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_json_missing_reference_and_invalid_history_are_quarantined(): void
    {
        $this->source->data['tblsanpham'][0]['cid'] = 999;
        $this->source->data['tblsanpham'][1]['phanloai'] = '{bad';
        $this->source->data['tblorder'][0]['soluong'] = 0;
        $this->migrate();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventories', 0);
        $summaries = glob($this->private.'/write-*/migration-summary.json');
        $summary = json_decode(file_get_contents($summaries[0]), true);
        $this->assertSame(2, $summary['counts']['products']['QUARANTINED']);
        $this->assertSame(1, $summary['counts']['orders']['QUARANTINED']);
    }

    public function test_product_variant_failure_rolls_back_whole_entity_and_files(): void
    {
        $this->source->data['tblsanpham'] = [$this->source->data['tblsanpham'][1]];
        ProductVariant::creating(function ($variant) {
            if ($variant->sku === 'BC-L11-V2') {
                throw ValidationException::withMessages(['sku' => 'Synthetic fault']);
            }
        });
        try {
            $this->migrate();
        } finally {
            ProductVariant::flushEventListeners();
        }
        foreach (['products', 'product_variants', 'inventories', 'product_options', 'product_option_values', 'product_images'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_rerun_never_overwrites_manual_catalog_price_or_historical_snapshot(): void
    {
        $this->migrate();
        ProductVariant::where('sku', 'SYN-10')->update(['price' => 150000]);
        $this->migrate();
        $this->assertSame(150000, ProductVariant::where('sku', 'SYN-10')->first()->price);
        $this->assertSame(100000, Order::first()->items[0]->unit_price);
        $this->expectException(ValidationException::class);
        app(ChangeOrderStatus::class)->execute(Order::first(), OrderStatus::Packing);
    }

    public function test_only_range_includes_required_parents_without_duplicate_children(): void
    {
        foreach ([true, false] as $dry) {
            $this->artisan('legacy:migrate', ['--only' => 'products', '--from-id' => 11, '--limit' => 1, '--dry-run' => $dry, '--force' => ! $dry])->assertSuccessful();
        }
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['legacy_id' => 11]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_media_paths_are_contained_and_html_is_sanitized_without_external_requests(): void
    {
        $report = new RunReport;
        $media = new Media;
        $this->assertNull($media->image('../target.sql', 'products', false, $report, 'products', 1));
        $html = $media->html('<p>Keep</p><img src="https://external.invalid/image.jpg"><script>bad()</script><iframe src="https://external.invalid"></iframe>', false, $report, 'products', 1);
        $this->assertStringContainsString('Keep', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('external.invalid', $html);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_exact_integer_and_grouping_validation_reject_overflow_and_bad_formats(): void
    {
        $this->assertSame(1234567, Values::integer('1,234,567', grouped: true));
        $this->assertSame(PHP_INT_MAX, Values::integer((string) PHP_INT_MAX));
        $this->assertSame([], (new ProductTransformer)->gallery(['image' => '']));
        foreach (['1,23', '-1', '1.2', '1e3', '9223372036854775808'] as $value) {
            try {
                Values::integer($value, grouped: true);
                $this->fail('Invalid integer accepted');
            } catch (MigrationFailure) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_content_urls_are_portable_and_repair_preserves_edits_and_timestamps(): void
    {
        config(['filesystems.disks.public.url' => 'https://different.example.test/storage']);
        $media = new Media;
        $html = $media->html('<img src="/upload/sp/112023/cover.png">', true, new RunReport, 'products', 10);
        $this->assertStringContainsString('src="/storage/articles/legacy/', $html);
        $this->assertStringNotContainsString('different.example.test', $html);
        $old = str_replace('/storage/', 'http://localhost:8002/storage/', $html);
        $category = Category::factory()->create(['legacy_id' => 1, 'description' => '<p>Manual edit stays</p>'.$old]);
        Category::factory()->create(['description' => $old]); // Never touch native content.
        $urls = new ContentMediaUrls;
        $before = $category->getRawOriginal('updated_at');
        $plan = $urls->plan();
        $this->assertCount(1, $plan);
        $this->assertSame(1, $urls->apply($plan));
        $this->assertSame('<p>Manual edit stays</p>'.$html, $category->fresh()->description);
        $this->assertSame($before, $category->fresh()->getRawOriginal('updated_at'));
        $this->assertSame([], $urls->plan());
        $this->assertSame(0, $urls->apply([]));
        $this->assertSame($old, Category::whereNull('legacy_id')->first()->description);
        $this->assertSame('<a href="http://localhost:8002/storage/manual.jpg">Manual</a>', $urls->normalize('<a href="http://localhost:8002/storage/manual.jpg">Manual</a>'));
    }

    public function test_content_repair_rejects_stale_plan_without_overwriting_admin_edits(): void
    {
        $path = 'articles/legacy/'.str_repeat('a', 64).'.webp';
        Storage::disk('public')->put($path, 'synthetic');
        $category = Category::factory()->create(['legacy_id' => 1, 'description' => '<img src="http://localhost:8002/storage/'.$path.'">']);
        $urls = new ContentMediaUrls;
        $plan = $urls->plan();
        $category->update(['description' => '<p>New edit</p>'.$category->description]);
        try {
            $urls->apply($plan);
            $this->fail('Stale plan accepted');
        } catch (MigrationFailure $error) {
            $this->assertSame('STALE_CONTENT_PLAN', $error->reason);
            $this->assertStringStartsWith('<p>New edit</p>', $category->fresh()->description);
        }
        Storage::disk('public')->delete($path);
        $this->expectException(MigrationFailure::class);
        $urls->plan();
    }

    public function test_explicit_category_range_is_bounded_and_ancestry_is_never_guessed(): void
    {
        $this->source->data['tbldanhmuc'][] = array_replace($this->source->data['tbldanhmuc'][0], ['id' => 2, 'parent' => 1]);
        foreach ([true, false] as $dry) {
            $this->artisan('legacy:migrate', ['--only' => 'categories', '--limit' => 1, '--dry-run' => $dry, '--force' => ! $dry])->assertSuccessful();
        }
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('categories', ['legacy_id' => 1]);
    }

    public function test_target_source_and_backup_guards_cannot_be_forced(): void
    {
        config(['legacy.target_database' => 'production']);
        $this->artisan('legacy:migrate', ['--force' => true])->assertFailed();
        config(['legacy.target_database' => 'bancay_test']);
        file_put_contents($this->private.'/target.sql', 'tampered');
        $this->artisan('legacy:migrate', ['--dry-run' => true])->assertFailed();
        config(['database.connections.legacy.database' => 'production']);
        try {
            (new Source)->assertReadOnly();
            $this->fail('Production source accepted');
        } catch (MigrationFailure $e) {
            $this->assertSame('UNSAFE_SOURCE_DATABASE', $e->reason);
        }
    }

    private function approveReview(array $extra): string
    {
        $file = $this->private.'/approved-review.json';
        file_put_contents($file, json_encode(array_replace(['approval' => 'USER APPROVED', 'target_database' => 'bancay_test', 'source_fingerprint' => $this->source->fingerprint()], $extra)));
        config(['legacy.review_manifest' => $file]);

        return $file;
    }

    public function test_reviewed_category_mapping_is_explicit_source_immutable_and_receipt_bound(): void
    {
        $this->source->data['tblsanpham'][0]['cid'] = 999;
        $hash = $this->source->fingerprint();
        $file = $this->approveReview(['categories' => ['products' => [10 => ['from' => 999, 'to' => 1]]]]);
        $this->artisan('legacy:migrate', ['--dry-run' => true])->assertSuccessful();
        $data = json_decode(file_get_contents($file), true);
        $data['categories']['products'][10]['to'] = 2;
        file_put_contents($file, json_encode($data));
        $this->artisan('legacy:migrate', ['--force' => true])->assertFailed();
        $data['categories']['products'][10]['to'] = 1;
        file_put_contents($file, json_encode($data));
        $this->migrate();
        $this->assertSame(1, (int) Product::where('legacy_id', 10)->first()->category->legacy_id);
        $this->assertSame($hash, $this->source->fingerprint());
        $this->assertSame(999, $this->source->data['tblsanpham'][0]['cid']);
        $this->assertSame('PASS', app(Reconciler::class)->run()['status']);
    }

    public function test_reviewed_media_repairs_are_safe_idempotent_and_preserve_manual_edits(): void
    {
        $relative = 'upload/sp/112023/MISSING.png';
        $this->source->data['tblsanpham'][0]['image'] = '["MISSING.png"]';
        $this->source->data['tblsanpham'][0]['content'] = '<p>Body</p><img src="/'.$relative.'">';
        $this->migrate();
        $product = Product::where('legacy_id', 10)->firstOrFail();
        $before = $product->description;
        $time = $product->getRawOriginal('updated_at');
        $this->approveReview(['media' => [$relative => ['path' => 'upload/sp/112023/cover.png', 'sha256' => hash_file('sha256', $this->private.'/media/upload/sp/112023/cover.png')]],
            'content_repairs' => [['legacy_id' => 10, 'field' => 'description']], 'gallery_repairs' => [10]]);
        $repair = new ReviewedMediaRepair($this->source, new ReviewOverrides);
        $files = Storage::disk('public')->allFiles();
        $plan = $repair->plan();
        $this->assertCount(2, $plan);
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $product->update(['description' => '<p>Manual edit</p>']);
        try {
            $repair->apply($plan);
            $this->fail('Manual edit overwritten');
        } catch (MigrationFailure $error) {
            $this->assertSame('MANUAL_CONTENT_EDIT_REQUIRES_REVIEW', $error->reason);
            $this->assertSame('<p>Manual edit</p>', $product->fresh()->description);
        }
        DB::table('products')->where('id', $product->id)->update(['description' => $before, 'updated_at' => $time]);
        $this->assertSame(2, $repair->apply($plan));
        $this->assertStringContainsString('/storage/articles/legacy/', $product->fresh()->description);
        $this->assertSame(1, $product->images()->count());
        $this->assertSame($time, $product->fresh()->getRawOriginal('updated_at'));
        $this->assertSame([], $repair->plan());
        $this->assertSame(0, $repair->apply([]));
        $this->assertSame('PASS', app(Reconciler::class)->run()['status']);
    }

    public function test_reviewed_media_hash_and_source_fingerprint_cannot_drift(): void
    {
        $this->approveReview(['media' => ['upload/missing.png' => ['path' => 'upload/sp/112023/cover.png', 'sha256' => str_repeat('0', 64)]]]);
        $review = new ReviewOverrides;
        try {
            $review->mediaPath('upload/missing.png');
            $this->fail('Changed media accepted');
        } catch (MigrationFailure $error) {
            $this->assertSame('REVIEW_MEDIA_CHANGED', $error->reason);
        }
        $this->source->data['tblsanpham'][0]['title'] = 'Source changed';
        $this->artisan('legacy:migrate', ['--dry-run' => true])->assertFailed();
        $this->assertTrue($review->preservesHistoricalNull(80, 7) === false);
    }

    public function test_restoring_catalog_preserves_only_explicitly_reviewed_historical_null_links(): void
    {
        $this->source->data['tblsanpham'][0]['cid'] = 999;
        $this->migrate();
        $order = Order::with('items')->firstOrFail();
        $this->assertNull($order->items[0]->product_id);
        $snapshot = $order->items[0]->getAttributes();
        $file = $this->approveReview(['categories' => ['products' => [10 => ['from' => 999, 'to' => 1]]], 'preserve_historical_null' => [20 => 10]]);
        $this->migrate();
        $this->assertSame($snapshot, $order->items[0]->fresh()->getAttributes());
        $this->assertSame('PASS', app(Reconciler::class)->run()['status']);
        $data = json_decode(file_get_contents($file), true);
        $data['preserve_historical_null'][20] = 11;
        file_put_contents($file, json_encode($data));
        $this->assertSame('FAIL', app(Reconciler::class)->run()['status']);
    }
}

final class FixtureLegacySource extends Source
{
    public array $data = [];

    public function assertReadOnly(): void {}

    public function find(string $table, int $id): array
    {
        foreach ($this->data[$table] ?? [] as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        throw new MigrationFailure('SOURCE_CHANGED');
    }

    public function rows(string $table, int $from = 0, int $limit = 0): iterable
    {
        $seen = 0;
        foreach ($this->data[$table] ?? [] as $row) {
            if (($row['id'] ?? 0) < $from) {
                continue;
            }
            if ($limit > 0 && $seen++ >= $limit) {
                break;
            }
            yield $row;
        }
    }
}
