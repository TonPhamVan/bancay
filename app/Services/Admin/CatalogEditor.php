<?php

namespace App\Services\Admin;

use App\Actions\Catalog\CreateVariant;
use App\Domain\Catalog\PricingType;
use App\Domain\Catalog\RecordStatus;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CatalogEditor
{
    public function option(Product $product, array $input, ?int $id = null): ProductOption
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100', Rule::unique('product_options')->where('product_id', $product->id)->ignore($id)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'values' => ['required', 'array', 'min:1', 'max:100'], 'values.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
        ])->validate();

        return DB::transaction(function () use ($product, $data, $id) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $option = $id ? $product->options()->findOrFail($id) : null;
            if (! $option && $product->variants()->exists()) {
                throw ValidationException::withMessages(['options' => 'Không thêm nhóm thuộc tính khi SKU đã tồn tại. Tạo sản phẩm mới nếu cần đổi cấu trúc.']);
            }
            if (! $option && $product->options()->count() >= 10) {
                throw ValidationException::withMessages(['options' => 'Tối đa 10 nhóm thuộc tính.']);
            }
            $option ??= $product->options()->make();
            $option->fill(['name' => $data['name'], 'sort_order' => $data['sort_order']])->save();
            // Retain value IDs for exact existing labels; referenced values cannot disappear.
            $old = $option->values()->get();
            foreach ($old as $value) {
                if (! in_array($value->value, $data['values'], true)) {
                    if (DB::table('product_variant_values')->where('product_option_value_id', $value->id)->exists()) {
                        throw ValidationException::withMessages(['values' => 'Giá trị đang dùng bởi SKU không được xóa hoặc đổi tên.']);
                    }
                    $value->delete();
                }
            }
            foreach ($data['values'] as $sort => $label) {
                $option->values()->updateOrCreate(['value' => $label], ['product_id' => $product->id, 'sort_order' => $sort]);
            }

            return $option->load('values');
        });
    }

    public function deleteOption(Product $product, int $id): void
    {
        DB::transaction(function () use ($product, $id) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $option = $product->options()->findOrFail($id);
            if ($product->variants()->exists()) {
                throw ValidationException::withMessages(['options' => 'Không xóa nhóm thuộc tính khi sản phẩm có SKU.']);
            }
            $option->delete();
        });
    }

    public function updateVariant(Product $product, int $id, array $input): ProductVariant
    {
        $data = Validator::make($input, [
            'sku' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', Rule::unique('product_variants')->ignore($id)],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'price' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'sale_price' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'status' => ['required', Rule::enum(RecordStatus::class)], 'image_id' => ['nullable', 'integer'],
            'option_value_ids' => ['prohibited'], 'quantity' => ['prohibited'], 'reserved_quantity' => ['prohibited'],
        ])->validate();
        if (($data['pricing_type'] === 'FIXED' && ! isset($data['price']))
            || ($data['pricing_type'] === 'CONTACT' && (isset($data['price']) || isset($data['sale_price'])))
            || (isset($data['sale_price']) && $data['sale_price'] > ($data['price'] ?? 0))) {
            throw ValidationException::withMessages(['price' => 'Giá cố định > 0, giá sale <= giá gốc; Liên hệ không có giá.']);
        }

        return DB::transaction(function () use ($product, $id, $data) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $variant = $product->variants()->lockForUpdate()->findOrFail($id);
            if (isset($data['image_id']) && ! $product->images()->whereKey($data['image_id'])->exists()) {
                throw ValidationException::withMessages(['image_id' => 'Ảnh không thuộc sản phẩm.']);
            }
            $variant->update($data);

            return $variant;
        });
    }

    /** Create missing combinations only; existing SKU/price/stock/IDs are preserved. */
    public function generate(Product $product, array $input): int
    {
        $data = Validator::make($input, ['sku_prefix' => ['required', 'string', 'max:50', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/'],
            'confirm' => ['accepted']])->validate();

        return DB::transaction(function () use ($product, $data) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $options = $product->options()->with('values')->get();
            $combinations = [[]];
            foreach ($options as $option) {
                if ($option->values->isEmpty() || count($combinations) * $option->values->count() > 256) {
                    throw ValidationException::withMessages(['options' => 'Mỗi nhóm cần giá trị; tối đa 256 tổ hợp mỗi lần.']);
                }
                $next = [];
                foreach ($combinations as $combo) {
                    foreach ($option->values as $value) {
                        $next[] = [...$combo, $value->id];
                    }
                }
                $combinations = $next;
            }
            $created = 0;
            foreach ($combinations as $ids) {
                sort($ids, SORT_NUMERIC);
                $key = hash('sha256', implode(':', $ids));
                if ($product->variants()->where('combination_key', $key)->exists()) {
                    continue;
                }
                app(CreateVariant::class)->execute($product, ['sku' => $data['sku_prefix'].'-'.$product->id.'-'.substr($key, 0, 12),
                    'pricing_type' => 'CONTACT', 'status' => 'INACTIVE', 'quantity' => 0, 'option_value_ids' => $ids]);
                $created++;
            }

            return $created;
        });
    }
}
