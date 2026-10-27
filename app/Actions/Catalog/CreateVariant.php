<?php

namespace App\Actions\Catalog;

use App\Domain\Catalog\PricingType;
use App\Domain\Catalog\RecordStatus;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateVariant
{
    public function execute(Product $product, array $input): ProductVariant
    {
        $data = Validator::make($input, [
            'sku' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:product_variants,sku'],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'price' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'sale_price' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'status' => ['sometimes', Rule::enum(RecordStatus::class)],
            'quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'option_value_ids' => ['sometimes', 'array', 'max:10'],
            'option_value_ids.*' => ['integer', 'distinct'],
            'image_id' => ['nullable', 'integer'],
        ])->validate();
        $type = $data['pricing_type'] instanceof PricingType ? $data['pricing_type'] : PricingType::from($data['pricing_type']);
        if (($type === PricingType::Fixed && ! isset($data['price']))
            || ($type === PricingType::Contact && (isset($data['price']) || isset($data['sale_price'])))
            || (isset($data['sale_price']) && $data['sale_price'] > $data['price'])) {
            throw ValidationException::withMessages(['price' => 'FIXED needs a positive price and sale <= price; CONTACT has no price.']);
        }

        return DB::transaction(function () use ($product, $data) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $ids = $data['option_value_ids'] ?? [];
            sort($ids, SORT_NUMERIC);
            $values = ProductOptionValue::where('product_id', $product->id)->whereIn('id', $ids)->get();
            $optionIds = $product->options()->pluck('id')->all();
            if (count($ids) !== $values->count()
                || count($optionIds) !== $values->count()
                || $values->pluck('product_option_id')->unique()->count() !== count($optionIds)) {
                throw ValidationException::withMessages(['option_value_ids' => 'Select one value for every option belonging to this product.']);
            }
            if (isset($data['image_id']) && ! $product->images()->whereKey($data['image_id'])->exists()) {
                throw ValidationException::withMessages(['image_id' => 'Variant image must belong to this product.']);
            }
            $combination = hash('sha256', implode(':', $ids));
            if ($product->variants()->where('combination_key', $combination)->exists()) {
                throw ValidationException::withMessages(['option_value_ids' => 'This variant combination already exists.']);
            }
            $variant = $product->variants()->create([
                'sku' => $data['sku'], 'combination_key' => $combination,
                'pricing_type' => $data['pricing_type'], 'price' => $data['price'] ?? null,
                'sale_price' => $data['sale_price'] ?? null, 'status' => $data['status'] ?? RecordStatus::Active,
                'image_id' => $data['image_id'] ?? null,
            ]);
            foreach ($values as $value) {
                $variant->values()->create([
                    'product_id' => $product->id, 'product_option_id' => $value->product_option_id,
                    'product_option_value_id' => $value->id,
                ]);
            }
            $variant->inventory()->create(['quantity' => $data['quantity'], 'reserved_quantity' => 0]);

            return $variant->load('optionValues', 'inventory');
        });
    }
}
