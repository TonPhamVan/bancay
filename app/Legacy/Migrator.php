<?php

namespace App\Legacy;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\System\SetPublicSetting;
use App\Domain\Commerce\Money;
use App\Legacy\Reports\RunReport;
use App\Legacy\Transformers\ProductTransformer;
use App\Legacy\Transformers\Values;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Admin\ImageStorage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class Migrator
{
    public const GROUPS = ['settings', 'categories', 'products', 'article_categories', 'articles', 'customers', 'orders', 'banners', 'users'];

    private array $categories = [];

    private array $articleCategories = [];

    private array $skuCounts = [];

    private array $productIds = [];

    private bool $write;

    public function __construct(private Source $source, private ProductTransformer $products, public Media $media, public RunReport $report, private ReviewOverrides $review = new ReviewOverrides) {}

    public function run(array $options, bool $write): void
    {
        $this->write = $write;
        foreach ($this->source->rows('tblsanpham') as $row) {
            $codes = [(string) ($row['ma'] ?? '')];
            if (trim((string) ($row['phanloai'] ?? ''))) {
                $json = json_decode($row['phanloai'], true);
                $codes = is_array($json['ma_pl'] ?? null) ? $json['ma_pl'] : [];
            }
            foreach ($codes as $code) {
                if (is_string($code) && $code !== '') {
                    $key = strtolower($code);
                    $this->skuCounts[$key] = ($this->skuCounts[$key] ?? 0) + 1;
                }
            }
        }
        $selected = $options['only'];
        foreach (self::GROUPS as $group) {
            if (! in_array($group, $selected, true)) {
                continue;
            }
            $this->report->initialize($group);
            if ($group === 'categories' || $group === 'article_categories') {
                $table = $group === 'categories' ? 'tbldanhmuc' : 'tblchuyenmuc';
                $dependency = $group === 'categories' ? (bool) array_intersect(['products', 'banners'], $selected) : in_array('articles', $selected, true);
                $seen = 0;
                foreach ($this->source->categoryRows($table) as $row) {
                    if (! $dependency && $row['id'] < $options['from_id']) {
                        continue;
                    }
                    if (! $dependency && $options['limit'] > 0 && $seen++ >= $options['limit']) {
                        break;
                    }
                    $this->row($group, $row);
                }

                continue;
            }
            $tables = match ($group) {
                'settings' => ['tblfooter'], 'products' => ['tblsanpham'], 'articles' => ['tblbaiviet'],
                'customers' => ['tblkhachhang'], 'orders' => ['tblorder'], 'banners' => ['tblbanner', 'tblslider'], 'users' => ['tbladmin'],
            };
            foreach ($tables as $table) {
                foreach ($this->source->rows($table, $options['from_id'], $options['limit']) as $row) {
                    $this->row($group, $row, $table);
                }
            }
        }
    }

    private function model(string $group, array $row, ?string $table): mixed
    {
        return match ($group) {
            'categories' => Category::where('legacy_id', $row['id'])->first(),
            'article_categories' => ArticleCategory::where('legacy_id', $row['id'])->first(),
            'products' => Product::withTrashed()->where('legacy_id', $row['id'])->first(),
            'articles' => Article::where('legacy_id', $row['id'])->first(),
            'customers' => Customer::where('legacy_id', $row['id'])->first(),
            'orders' => Order::where('legacy_id', $row['id'])->first(),
            'banners' => Banner::where('legacy_source', $table)->where('legacy_id', $row['id'])->first(),
            default => null,
        };
    }

    private function row(string $group, array $row, ?string $table = null): void
    {
        $id = Values::integer($row['id']);
        $this->report->count($group, 'READ');
        $this->media->created = [];
        try {
            if ($group === 'users') {
                throw new MigrationFailure('ROLE_MAPPING_REQUIRED');
            }
            $existing = $this->model($group, $row, $table);
            if ($existing) {
                $this->report->count($group, 'SKIPPED');
                $this->remember($group, $row, $existing->id, $existing->slug ?? null, 'SKIPPED');

                return;
            }
            $callback = fn () => match ($group) {
                'categories', 'article_categories' => $this->category($group, $row),
                'products' => $this->product($row), 'articles' => $this->article($row),
                'customers' => $this->customer($row), 'orders' => $this->order($row),
                'banners' => $this->banner($row, $table), 'settings' => $this->settings($row),
            };
            $result = $this->write ? DB::transaction($callback, 3) : $callback();
            $status = $result['_migration_status'] ?? 'CREATED';
            $this->report->count($group, $status);
            $this->remember($group, $row, $result['id'] ?? -$id, $result['slug'] ?? null, $status);
        } catch (MigrationFailure $e) {
            $this->cleanup();
            $this->report->issue($group, $id, $e->reason, $e->context, 'QUARANTINE');
        } catch (ValidationException $e) {
            $this->cleanup();
            $this->report->issue($group, $id, 'TARGET_VALIDATION_FAILED', ['fields' => array_keys($e->errors())], 'QUARANTINE');
        } catch (\Throwable $e) {
            $this->cleanup();
            $this->report->count($group, 'FAILED');
            throw new MigrationFailure('ENTITY_WRITE_FAILED', ['entity' => $group, 'legacy_id' => $id, 'exception_type' => get_class($e), 'error_file' => basename($e->getFile()), 'error_line' => $e->getLine()]);
        }
    }

    private function cleanup(): void
    {
        foreach ($this->media->created as $path) {
            app(ImageStorage::class)->deleteIfUnused($path);
        }
    }

    private function remember(string $group, array $row, int $target, ?string $slug, string $status): void
    {
        $id = (int) $row['id'];
        if ($group === 'categories') {
            $this->categories[$id] = $target;
        }
        if ($group === 'products') {
            $this->productIds[$id] = $target;
        }
        if ($group === 'article_categories') {
            $this->articleCategories[$id] = $target;
        }
        $this->report->records[] = ['entity' => $group, 'legacy_id' => $id, 'target_id' => $this->write ? $target : null, 'source_status' => $row['status'] ?? null, 'status' => $status, 'source_hash' => hash('sha256', json_encode($row, JSON_THROW_ON_ERROR))];
        $routes = ['categories' => ['dm', 'danh-muc'], 'products' => ['sp', 'san-pham'], 'articles' => ['bv', 'bai-viet'], 'article_categories' => ['cm', 'chuyen-muc']];
        if (isset($routes[$group]) && $slug) {
            [$suffix, $prefix] = $routes[$group];
            $old = '/'.($row['alias'] ?? '').'-'.$suffix.$id.'.html';
            $this->report->urls[] = ['entity' => $group, 'legacy_id' => $id, 'legacy_url' => $old, 'legacy_url_encoded' => '/'.rawurlencode(substr($old, 1)), 'new_url' => '/'.$prefix.'/'.$slug, 'publication' => 'HIDDEN_PENDING_REVIEW'];
        }
    }

    private function parent(string $group, mixed $legacyId): ?int
    {
        $id = Values::integer($legacyId ?? 0);
        if ($id === 0) {
            return null;
        }
        $map = $group === 'categories' ? $this->categories : $this->articleCategories;
        if (isset($map[$id])) {
            return $map[$id];
        }
        $model = $group === 'categories' ? Category::class : ArticleCategory::class;
        $found = $model::where('legacy_id', $id)->value('id');
        if (! $found) {
            throw new MigrationFailure('INVALID_REFERENCE', ['parent_legacy_id' => $id]);
        }

        return (int) $found;
    }

    private function category(string $group, array $r): array
    {
        $name = Values::text($r['name'], 255, true);
        $id = (int) $r['id'];
        $data = ['legacy_id' => $id, 'parent_id' => $this->parent($group, $r['parent']), 'name' => $name, 'slug' => Values::slug($r, $name),
            'description' => $this->media->html(Values::text($r[$group === 'categories' ? 'mota' : 'discription'] ?? '', 65535), $this->write, $this->report, $group, $id),
            'sort_order' => Values::integer($r['thutu'] ?? 0, 4294967295), 'status' => 'DRAFT', 'published_at' => null];
        if (strlen($data['description']) > 65535) {
            throw new MigrationFailure('CATEGORY_TEXT_TOO_LARGE');
        }
        $data[$group === 'categories' ? 'featured' : 'show_in_menu'] = Values::flag($r[$group === 'categories' ? 'vip' : 'menu']);
        $data['image'] = $this->media->image($r['image'] ? ($group === 'categories' ? 'upload/category/' : 'upload/').$r['image'] : null, 'categories', $this->write, $this->report, $group, $id);
        if ($group !== 'categories') {
            $data += ['meta_title' => Values::text($r['meta_title'] ?? '', 255), 'meta_description' => Values::text($r['meta_des'] ?? '', 1000)];
        }
        $model = $group === 'categories' ? Category::class : ArticleCategory::class;
        if ($this->write) {
            $data['id'] = $model::create($data)->id;
        }

        return $data;
    }

    private function sku(array $row, array $variant): string
    {
        $original = $variant['original_sku'];
        $key = strtolower($original);
        $valid = (bool) preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/', $original);
        $duplicate = ($this->skuCounts[$key] ?? 0) > 1;
        $sku = $original;
        if (! $valid || $duplicate) {
            $this->report->issue('products', (int) $row['id'], $duplicate ? 'DUPLICATE_SKU' : 'INVALID_OR_EMPTY_SKU', ['variant_index' => $variant['index']]);
            $sku = 'BC-L'.$row['id'].($variant['index'] === null ? '-S' : '-V'.($variant['index'] + 1));
            if (isset($this->skuCounts[strtolower($sku)])) {
                throw new MigrationFailure('SKU_FALLBACK_COLLISION');
            }
        }
        if (ProductVariant::where('sku', $sku)->exists()) {
            throw new MigrationFailure('TARGET_SKU_COLLISION');
        }

        return $sku;
    }

    private function product(array $r): array
    {
        $id = (int) $r['id'];
        $category = $this->parent('categories', $this->review->category('products', $r));
        if ($category === null) {
            throw new MigrationFailure('INVALID_REFERENCE');
        }
        $name = Values::text($r['title'], 255, true);
        $recorded = Values::recorded($r['created_at']);
        $plan = $this->products->variants($r);
        $gallery = $this->products->gallery($r);
        if (! $gallery) {
            $this->report->issue('products', $id, 'MISSING_GALLERY');
        }
        foreach ($plan['variants'] as &$variant) {
            $variant['sku'] = $this->sku($r, $variant);
        } unset($variant);
        $data = ['category_id' => $category, 'name' => $name, 'slug' => Values::slug($r, $name), 'status' => 'DRAFT', 'featured' => Values::flag($r['vip']), 'published_at' => null,
            'short_description' => $this->media->html(Values::text($r['sapo'], 10000), $this->write, $this->report, 'products', $id),
            'description' => $this->media->html(Values::text($r['content'], 1000000), $this->write, $this->report, 'products', $id),
            'meta_title' => Values::text($r['meta_title'] ?: $name, 255), 'meta_description' => Values::text($r['meta_des'] ?: $name, 1000), 'options' => $plan['options']];
        $folder = 'upload/sp/'.CarbonImmutable::createFromTimestamp((int) $r['created_at'], 'Asia/Saigon')->format('mY').'/';
        $imagePaths = [];
        foreach (array_merge($gallery, array_column($plan['variants'], 'image')) as $file) {
            if (! $file) {
                continue;
            }
            $path = $this->media->image($folder.$file, 'products', $this->write, $this->report, 'products', $id);
            if ($path) {
                $imagePaths[$file] = $path;
            }
        }
        Validator::make($data, array_replace(CreateProduct::rules(), ['category_id' => ['required', 'integer']]))->validate();
        if (! $this->write) {
            return ['slug' => $data['slug']];
        }
        $product = app(CreateProduct::class)->execute($data);
        $product->fill(['legacy_id' => $id]);
        $product->created_at = $recorded;
        $product->save();
        $images = [];
        $primary = true;
        $orderedFiles = $gallery;
        foreach (array_column($plan['variants'], 'image') as $file) {
            if ($file && ! in_array($file, $orderedFiles, true)) {
                $orderedFiles[] = $file;
            }
        }
        foreach ($orderedFiles as $sort => $file) {
            if (! $file || ! isset($imagePaths[$file])) {
                continue;
            }
            $images[$file] = $product->images()->create(['path' => $imagePaths[$file], 'alt_text' => $name, 'sort_order' => $sort, 'is_primary' => $primary])->id;
            $primary = false;
        }
        $product->load('options.values');
        foreach ($plan['variants'] as $variant) {
            $valueIds = [];
            foreach ($variant['selection'] as $i => $value) {
                $valueIds[] = $product->options[$i]->values->firstWhere('value', $value)->id;
            }
            $model = app(CreateVariant::class)->execute($product, ['sku' => $variant['sku'], 'pricing_type' => $variant['pricing_type'], 'price' => $variant['price'], 'sale_price' => $variant['sale_price'],
                'quantity' => $variant['quantity'], 'status' => 'ACTIVE', 'option_value_ids' => $valueIds, 'image_id' => $images[$variant['image']] ?? null]);
            $model->update(['legacy_variant_index' => $variant['index'], 'legacy_sku' => $variant['original_sku'] !== '' ? $variant['original_sku'] : null]);
        }

        return ['id' => $product->id, 'slug' => $product->slug];
    }

    private function article(array $r): array
    {
        $id = (int) $r['id'];
        $title = Values::text($r['title'], 255, true);
        $category = $this->parent('article_categories', $this->review->category('articles', $r));
        if (! $category) {
            throw new MigrationFailure('INVALID_REFERENCE');
        }
        $data = ['legacy_id' => $id, 'article_category_id' => $category, 'title' => $title, 'slug' => Values::slug($r, $title), 'status' => 'DRAFT', 'published_at' => null,
            'excerpt' => $this->media->html(Values::text($r['sapo'] ?? '', 10000), $this->write, $this->report, 'articles', $id),
            'content' => $this->media->html(Values::text($r['content'] ?? '', 1000000), $this->write, $this->report, 'articles', $id),
            'image' => $this->media->image($r['image'] ? 'upload/news/'.$r['image'] : null, 'articles', $this->write, $this->report, 'articles', $id),
            'featured' => Values::flag($r['vip']), 'show_in_menu' => Values::flag($r['menu']), 'view_count' => Values::integer($r['view'] ?? 0, 4294967295)];
        $recorded = Values::recorded($r['created_at']);
        if ($this->write) {
            $model = new Article($data);
            $model->created_at = $recorded;
            $model->save();
            $data['id'] = $model->id;
        }

        return $data;
    }

    private function customer(array $r): array
    {
        $data = ['legacy_id' => (int) $r['id'], 'name' => Values::text($r['ten'], 255, true), 'phone' => Values::text($r['dienthoai'], 40), 'status' => 'INACTIVE'];
        $address = Values::text($r['diachi'], 255);
        if ($this->write) {
            $model = Customer::create($data);
            $data['id'] = $model->id;
            if ($address !== '') {
                $model->addresses()->create(['recipient_name' => $data['name'], 'phone' => $data['phone'], 'line1' => $address, 'country_code' => 'VN', 'is_default' => true]);
            }
        }

        return $data;
    }

    private function order(array $r): array
    {
        $price = Values::integer($r['giaban']);
        $quantity = Values::integer($r['soluong'], 4294967295);
        if ($price < 1) {
            throw new MigrationFailure('ORDER_PRICE_MISSING');
        }
        if ($quantity < 1) {
            throw new MigrationFailure('INVALID_ORDER_QUANTITY');
        }
        $total = Money::multiply($price, $quantity);
        $status = match ((string) $r['status']) {
            '0' => 'NEW', '1' => 'CONFIRMED', default => throw new MigrationFailure('UNKNOWN_LEGACY_STATUS')
        };
        $data = ['legacy_id' => (int) $r['id'], 'legacy_status' => (int) $r['status'], 'is_legacy' => true, 'order_number' => 'LEG-'.$r['id'], 'customer_id' => null,
            'customer_name' => Values::text($r['hoten'], 255, true), 'customer_phone' => Values::text($r['dienthoai'], 40, true), 'customer_email' => trim((string) $r['email']) === '' ? null : Values::text($r['email'], 255),
            'shipping_address' => ['line1' => Values::text($r['diachi'], 255, true), 'country_code' => 'VN'], 'shipping_note' => Values::text($r['ghichu'], 5000),
            'currency' => 'VND', 'subtotal' => $total, 'discount_amount' => 0, 'shipping_fee' => 0, 'total' => $total, 'status' => $status];
        if ($data['customer_email'] && Validator::make(['email' => $data['customer_email']], ['email' => ['email', 'not_regex:/[\r\n]/']])->fails()) {
            throw new MigrationFailure('INVALID_HISTORICAL_EMAIL');
        }
        $name = Values::text($r['product_name'], 255, true);
        $description = Values::text($r['phanloai'] ?? '', 10000);
        $recorded = Values::recorded($r['ngaytao']);
        $product = $this->productIds[$r['product_id']] ?? Product::withTrashed()->where('legacy_id', $r['product_id'])->value('id');
        if (! $product) {
            $this->report->issue('orders', (int) $r['id'], 'MISSING_PRODUCT_REFERENCE', ['product_legacy_id' => (int) $r['product_id']]);
        }
        if ($this->write) {
            $order = new Order($data);
            $order->created_at = $recorded;
            $order->save();
            $order->items()->create(['product_id' => $product, 'product_variant_id' => null, 'product_name' => $name, 'sku' => null, 'variant_description' => $description ?: null, 'unit_price' => $price, 'quantity' => $quantity, 'subtotal' => $total]);
            $order->statusHistories()->create(['from_status' => null, 'to_status' => $status, 'note' => 'Imported recorded legacy status; mapping policy, not a historical fulfillment event. No inventory/email side effects.', 'changed_by' => null]);
            $data['id'] = $order->id;
        }

        return $data;
    }

    private function banner(array $r, string $table): array
    {
        $id = (int) $r['id'];
        $position = $table === 'tblslider' ? 'home_slider' : (['a0' => 'sidebar', 'a1' => 'sidebar', 'a4' => 'content_bottom'][$r['vitri']] ?? throw new MigrationFailure('UNKNOWN_BANNER_POSITION'));
        $image = $this->media->image($table === 'tblslider' ? 'upload/slider/'.$r['image'] : $r['file'], 'banners', $this->write, $this->report, 'banners', $id);
        if (! $image) {
            throw new MigrationFailure('BANNER_IMAGE_MISSING');
        }
        $link = trim((string) ($r['link'] ?? '')) ?: null;
        if ($link && Validator::make(['link' => $link], ['link' => ['url:http,https', 'max:2048']])->fails()) {
            $this->report->issue('banners', $id, 'UNSAFE_BANNER_LINK');
            $link = null;
        }
        $name = $table === 'tblslider' ? trim((string) $r['title']) : trim((string) $r['name']);
        if ($name === '') {
            $name = 'Legacy '.$table.' #'.$id;
            $this->report->issue('banners', $id, 'TECHNICAL_BANNER_LABEL');
        }
        $data = ['legacy_source' => $table, 'legacy_id' => $id, 'name' => Values::text($name, 255, true), 'image' => $image, 'link' => $link, 'position' => $position,
            'category_id' => $this->parent('categories', $r['cid'] ?? 0), 'priority' => Values::integer($r['vip'] ?? 0, 4294967295), 'sort_order' => $table === 'tblslider' ? $id : ((int) substr($r['vitri'], 1) * 100000 + $id), 'status' => 'INACTIVE'];
        if ($this->write) {
            $data['id'] = Banner::create($data)->id;
        }

        return $data;
    }

    private function settings(array $r): array
    {
        $id = (int) $r['id'];
        $created = false;
        foreach (['name' => 'site_name', 'diachi' => 'address', 'tuvan1' => 'contact_phone', 'email' => 'email', 'meta_title' => 'meta_title', 'meta_des' => 'meta_description', 'fanpage' => 'facebook', 'map' => 'map', 'logo' => 'logo'] as $old => $key) {
            if (Setting::where('key', $key)->exists()) {
                continue;
            }
            $value = strip_tags(Values::text($r[$old] ?? '', 5000));
            if ($key === 'logo') {
                $value = $this->media->image($value ? 'images/'.$value : null, 'settings', $this->write, $this->report, 'settings', $id);
            }
            if ($value && (($key === 'email' && Validator::make(['value' => $value], ['value' => ['email', 'not_regex:/[\r\n]/']])->fails())
                || (in_array($key, ['facebook', 'map'], true) && Validator::make(['value' => $value], ['value' => 'url:http,https'])->fails()))) {
                $this->report->issue('settings', $id, 'SETTING_REVIEW_REQUIRED', ['key' => $key]);

                continue;
            }
            if ($this->write) {
                app(SetPublicSetting::class)->execute($key, $value ?: null);
            }
            $created = true;
        }
        $this->report->issue('settings', $id, 'EXECUTABLE_SETTINGS_NOT_IMPORTED', ['fields' => ['anatic', 'content', 'video']]);

        return ['_migration_status' => $created ? 'CREATED' : 'SKIPPED'];
    }
}
