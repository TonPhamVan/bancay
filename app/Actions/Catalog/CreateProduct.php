<?php

namespace App\Actions\Catalog;

use App\Domain\Catalog\PublicationStatus;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Mews\Purifier\Facades\Purifier;

final class CreateProduct
{
    public static function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:products,slug'],
            'short_description' => ['nullable', 'string', 'max:10000'],
            'description' => ['nullable', 'string', 'max:1000000'],
            'status' => ['sometimes', Rule::enum(PublicationStatus::class)],
            'featured' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'published_at' => ['required_if:status,PUBLISHED', 'nullable', 'date'],
            'options' => ['sometimes', 'array', 'max:10'],
            'options.*' => ['array:name,values'],
            'options.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:100'],
            'options.*.values.*' => ['required', 'string', 'max:100'],
        ];
    }

    public function execute(array $input): Product
    {
        $input['slug'] = $input['slug'] ?? Str::slug($input['name'] ?? '');
        $data = Validator::make($input, self::rules())->validate();

        // Per-option uniqueness (not global distinct across all option groups).
        foreach ($data['options'] ?? [] as $index => $option) {
            Validator::make(['values' => $option['values']], [
                'values.*' => ['distinct:ignore_case'],
            ])->validate();
        }

        return DB::transaction(function () use ($data) {
            $options = $data['options'] ?? [];
            unset($data['options']);
            $data['status'] = $data['status'] ?? PublicationStatus::Draft;
            $data['featured'] = $data['featured'] ?? false;
            foreach (['short_description', 'description'] as $field) {
                if (isset($data[$field])) {
                    $data[$field] = Purifier::clean($data[$field]);
                }
            }
            $product = Product::create($data);
            foreach ($options as $position => $option) {
                $row = $product->options()->create(['name' => $option['name'], 'sort_order' => $position]);
                foreach ($option['values'] as $sort => $value) {
                    $row->values()->create(['product_id' => $product->id, 'value' => $value, 'sort_order' => $sort]);
                }
            }

            return $product->load('options.values');
        });
    }
}
