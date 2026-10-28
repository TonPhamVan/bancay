<?php

namespace App\Legacy;

use App\Legacy\Transformers\ProductTransformer;
use App\Models\Article;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class Reconciler
{
    public function __construct(private Source $source, private TargetGuard $guard) {}

    public function run(): array
    {
        $this->guard->check();
        $this->source->assertReadOnly();
        $review = new ReviewOverrides;
        $review->assertSource($this->source->fingerprint());
        $issues = [];
        $samples = ['products' => [], 'variants' => [], 'orders' => [], 'articles' => []];
        $check = function (bool $ok, string $code, int $id = 0) use (&$issues) {
            if (! $ok) {
                $issues[] = compact('code', 'id');
            }
        };
        $transformer = new ProductTransformer;
        foreach ($this->source->rows('tblsanpham') as $row) {
            $product = Product::withTrashed()->with(['options.values', 'variants.inventory', 'images'])->where('legacy_id', $row['id'])->first();
            if (! $product) {
                continue;
            } // Source quarantine is accounted for by per-run root reports.
            $plan = $transformer->variants($row);
            $check($product->name === $row['title'], 'PRODUCT_NAME', (int) $row['id']);
            $check((int) $product->category?->legacy_id === $review->category('products', $row), 'PRODUCT_CATEGORY', (int) $row['id']);
            $check($product->status->value === 'DRAFT' && $product->published_at === null, 'UNAPPROVED_PUBLICATION', (int) $row['id']);
            $check($product->created_at->getTimestamp() === (int) $row['created_at'], 'PRODUCT_RECORDED_DATE', (int) $row['id']);
            $check($product->variants->count() === count($plan['variants']) && $product->options->count() === count($plan['options']), 'NORMALIZATION_COUNT', (int) $row['id']);
            foreach ($plan['variants'] as $v) {
                $actual = $product->variants->first(fn ($a) => $v['index'] === null ? $a->legacy_variant_index === null : (int) $a->legacy_variant_index === $v['index']);
                $check($actual !== null, 'VARIANT_INDEX', (int) $row['id']);
                if (! $actual) {
                    continue;
                }
                $check($actual->price === $v['price'] && $actual->sale_price === $v['sale_price'] && $actual->pricing_type->value === $v['pricing_type'], 'VARIANT_PRICE', (int) $row['id']);
                $check($actual->legacy_sku === ($v['original_sku'] === '' ? null : $v['original_sku']), 'ORIGINAL_SKU', (int) $row['id']);
                $check($actual->inventory && $actual->inventory->quantity === $v['quantity'] && $actual->inventory->reserved_quantity === 0, 'OPENING_STOCK', (int) $row['id']);
                $actual->load('optionValues.option');
                $selected = [];
                foreach ($plan['options'] as $i => $option) {
                    $selected[] = $actual->optionValues->first(fn ($value) => $value->option->name === $option['name'])?->value;
                }
                $check($selected === $v['selection'], 'VARIANT_OPTION_MAPPING', (int) $row['id']);
                $samples['variants'][] = $actual->id;
            }
            $samples['products'][] = (int) $row['id'];
        }
        foreach ($this->source->rows('tblorder') as $row) {
            $order = Order::with(['items', 'statusHistories'])->where('legacy_id', $row['id'])->first();
            if (! $order) {
                continue;
            }
            $item = $order->items->first();
            $expectedStatus = (string) $row['status'] === '0' ? 'NEW' : 'CONFIRMED';
            $check($order->is_legacy && $order->items->count() === 1 && $order->statusHistories->count() === 1 && $order->status->value === $expectedStatus, 'HISTORY_STRUCTURE', (int) $row['id']);
            $check($item && $item->product_name === $row['product_name'] && $item->unit_price === (int) $row['giaban'] && $item->quantity === (int) $row['soluong'] && $item->sku === null && $item->product_variant_id === null, 'HISTORICAL_SNAPSHOT', (int) $row['id']);
            $check($order->total === (int) $row['giaban'] * (int) $row['soluong'] && $order->subtotal === $order->total, 'HISTORICAL_TOTAL', (int) $row['id']);
            $check($order->customer_name === $row['hoten'] && $order->customer_phone === $row['dienthoai'] && $order->shipping_address['line1'] === $row['diachi'], 'HISTORICAL_CONTACT', (int) $row['id']);
            $expectedProduct = Product::withTrashed()->where('legacy_id', $row['product_id'])->value('id');
            $preservedNull = $item?->product_id === null && $review->preservesHistoricalNull((int) $row['id'], (int) $row['product_id']);
            $check($preservedNull || (string) $item?->product_id === (string) $expectedProduct, 'HISTORICAL_PRODUCT_REFERENCE', (int) $row['id']);
            $check($order->created_at->getTimestamp() === (int) $row['ngaytao'], 'HISTORICAL_DATE', (int) $row['id']);
            $samples['orders'][] = (int) $row['id'];
        }
        foreach ($this->source->rows('tblbaiviet') as $row) {
            $article = Article::where('legacy_id', $row['id'])->first();
            if (! $article) {
                continue;
            }
            $check($article->title === $row['title'] && $article->created_at->getTimestamp() === (int) $row['created_at'] && $article->status->value === 'DRAFT', 'ARTICLE_MAPPING', (int) $row['id']);
            $check((int) $article->category?->legacy_id === $review->category('articles', $row), 'ARTICLE_CATEGORY', (int) $row['id']);
            $samples['articles'][] = (int) $row['id'];
        }
        foreach (['categories', 'article_categories'] as $table) {
            $parents = DB::table($table)->pluck('parent_id', 'id')->all();
            foreach ($parents as $id => $parent) {
                $seen = [];
                $cursor = $id;
                while ($cursor && isset($parents[$cursor])) {
                    if (isset($seen[$cursor])) {
                        $check(false, 'CATEGORY_CYCLE', (int) $id);
                        break;
                    }
                    $seen[$cursor] = true;
                    $cursor = $parents[$cursor];
                }
            }
        }
        $paths = [];
        foreach (['product_images' => 'path', 'categories' => 'image', 'article_categories' => 'image', 'articles' => 'image', 'banners' => 'image'] as $table => $column) {
            foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $path) {
                if ($path) {
                    $paths[$path] = true;
                }
            }
        }
        $logo = Setting::where('key', 'logo')->value('value');
        if ($logo) {
            $paths[$logo] = true;
        }
        foreach (['products' => ['description', 'short_description'], 'categories' => ['description'], 'article_categories' => ['description'], 'articles' => ['content', 'excerpt']] as $table => $fields) {
            foreach (DB::table($table)->select($fields)->cursor() as $row) {
                foreach ($fields as $field) {
                    preg_match_all('/(?:src|poster)="([^"]+)"/', (string) $row->$field, $matches);
                    foreach ($matches[1] as $url) {
                        if ($url === '/storefront/fallback.svg') {
                            continue;
                        }
                        $prefix = '/storage/';
                        $check(str_starts_with($url, $prefix), 'UNMAPPED_HTML_MEDIA');
                        if (str_starts_with($url, $prefix)) {
                            $paths[ltrim(substr($url, strlen($prefix)), '/')] = true;
                        }
                    }
                }
            }
        }
        foreach (array_keys($paths) as $path) {
            $check(Storage::disk('public')->exists($path) && @getimagesize(Storage::disk('public')->path($path)) !== false, 'BROKEN_MEDIA');
        }
        $check(DB::table('inventories')->whereColumn('reserved_quantity', '>', 'quantity')->count() === 0, 'RESERVED_EXCEEDS_STOCK');
        $check(DB::table('product_variants as v')->leftJoin('inventories as i', 'i.product_variant_id', '=', 'v.id')->whereNull('i.id')->count() === 0, 'MISSING_INVENTORY');
        foreach ($samples as $name => $ids) {
            usort($ids, fn ($a, $b) => strcmp(hash('sha256', 'phase4-'.$a), hash('sha256', 'phase4-'.$b)));
            $samples[$name] = array_slice($ids, 0, in_array($name, ['products', 'variants']) ? 20 : 10);
        }
        $counts = [];
        foreach (Runner::TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $result = ['status' => $issues ? 'FAIL' : 'PASS', 'counts' => $counts, 'issues' => $issues, 'deterministic_sample_ids' => $samples, 'checked_media_paths' => count($paths), 'source_fingerprint' => $this->source->fingerprint(), 'target_fingerprint' => $this->guard->fingerprint()];
        $path = config('legacy.report_directory').'/validation-'.gmdate('Ymd-His').'.json';
        file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        chmod($path, 0600);

        return $result;
    }
}
