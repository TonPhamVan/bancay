<?php

namespace App\Services\Admin;

use App\Admin\StoreResources;
use App\Models\Article;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class StoreQueries
{
    public function listing(string $module, string $entity, array $input): Builder
    {
        $definition = StoreResources::get($module, $entity);
        $data = Validator::make($input, ['q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'string', 'max:30'],
            'category_id' => ['nullable', 'integer', 'min:1'], 'parent_id' => ['nullable', 'integer', 'min:0'],
            'featured' => ['nullable', 'boolean'], 'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when(! empty($input['from']), ['after_or_equal:from'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name', 'sort_order'])]])->validate();
        $query = $definition['model']::query();
        if ($entity === 'products') {
            $query->with(['category', 'images', 'variants.inventory'])->withCount('variants');
            if (isset($data['category_id'])) {
                $query->where('category_id', $data['category_id']);
            }
            if (isset($data['featured'])) {
                $query->where('featured', $data['featured']);
            }
        } elseif ($entity === 'inventory') {
            $query->with(['variant.product']);
        } elseif ($entity === 'orders') {
            $query->with('customer');
        } elseif ($entity === 'customers') {
            $query->withCount('orders');
        } elseif ($entity === 'articles') {
            $query->with('category');
            if (isset($data['category_id'])) {
                $query->where('article_category_id', $data['category_id']);
            }
        } elseif (in_array($entity, ['categories', 'article-categories'], true)) {
            $query->with('parent');
            if (isset($data['parent_id'])) {
                $data['parent_id'] ? $query->where('parent_id', $data['parent_id']) : $query->whereNull('parent_id');
            }
        }
        if (! empty($data['q'])) {
            $search = '%'.addcslashes($data['q'], '\\%_').'%';
            $query->where(function (Builder $q) use ($entity, $definition, $search) {
                if ($entity === 'inventory') {
                    $q->whereHas('variant', fn ($v) => $v->where('sku', 'like', $search)->orWhereHas('product', fn ($p) => $p->where('name', 'like', $search)));
                } else {
                    $q->where($definition['display'], 'like', $search);
                    if ($entity === 'products') {
                        $q->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $search));
                    }
                    if ($entity === 'customers') {
                        $q->orWhere('phone', 'like', $search)->orWhere('email', 'like', $search);
                    }
                    if ($entity === 'orders') {
                        $q->orWhere('customer_phone', 'like', $search)->orWhere('customer_name', 'like', $search);
                    }
                }
            });
        }
        if (! empty($data['status']) && ! in_array($entity, ['inventory', 'settings'], true)) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['from'])) {
            $query->whereDate('created_at', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->whereDate('created_at', '<=', $data['to']);
        }

        return match ($data['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy($definition['display'])->orderBy('id'),
            'sort_order' => array_key_exists('sort_order', $definition['fields']) ? $query->orderBy('sort_order')->orderBy('id') : $query->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };
    }

    public function dashboard(): array
    {
        $stats = [];
        $recent = collect();
        $lowStock = collect();
        if (Gate::allows('catalog.manage')) {
            $stats['Tổng sản phẩm'] = Product::count();
            $stats['Sản phẩm công khai'] = Product::where('status', 'PUBLISHED')->where('published_at', '<=', now())->count();
            $lowStock = ProductVariant::with(['product', 'inventory'])->where('status', 'ACTIVE')->whereHas('product')
                ->whereHas('inventory', fn ($q) => $q->whereRaw('quantity - reserved_quantity <= 5'))->limit(10)->get();
        }
        if (Gate::allows('commerce.manage')) {
            $stats['Đơn mới'] = Order::where('is_legacy', false)->where('status', 'NEW')->count();
            $stats['Đơn đang xử lý'] = Order::where('is_legacy', false)->whereIn('status', ['CONFIRMED', 'PACKING', 'SHIPPING'])->count();
            $stats['Doanh thu đơn hoàn thành (VND)'] = Order::where('is_legacy', false)->where('status', 'COMPLETED')->sum('total');
            $recent = Order::latest('id')->limit(10)->get();
        }
        if (Gate::allows('customers.manage')) {
            $stats['Khách hàng'] = Customer::count();
        }
        if (Gate::allows('content.manage')) {
            $stats['Bài viết'] = Article::count();
        }

        return compact('stats', 'recent', 'lowStock');
    }
}
