<?php

namespace App\Services\Admin;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProductImages
{
    public function upload(Product $product, mixed $files): void
    {
        Validator::make(['images' => $files], ['images' => ['required', 'array', 'min:1', 'max:10'], 'images.*' => ['required', 'file']])->validate();
        $paths = [];
        try {
            foreach ($files as $file) {
                $paths[] = app(ImageStorage::class)->store($file, 'products');
            }
            DB::transaction(function () use ($product, $paths) {
                $product = Product::lockForUpdate()->findOrFail($product->id);
                $sort = (int) $product->images()->max('sort_order');
                $primary = ! $product->images()->where('is_primary', true)->exists();
                foreach ($paths as $index => $path) {
                    $product->images()->create(['path' => $path, 'sort_order' => ++$sort, 'is_primary' => $primary && $index === 0]);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);
            throw $e;
        }
    }

    public function update(Product $product, int $id, array $input): void
    {
        $data = Validator::make($input, ['alt_text' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'], 'is_primary' => ['required', 'boolean']])->validate();
        DB::transaction(function () use ($product, $id, $data) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $image = $product->images()->findOrFail($id);
            if ($data['is_primary']) {
                $product->images()->where('is_primary', true)->update(['is_primary' => false]);
                $image->refresh();
            }
            $image->update($data);
        });
    }

    public function delete(Product $product, int $id): void
    {
        DB::transaction(function () use ($product, $id) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $image = $product->images()->findOrFail($id);
            if ($product->variants()->where('image_id', $id)->exists()) {
                throw ValidationException::withMessages(['image' => 'Gỡ liên kết ảnh ở SKU trước khi xóa.']);
            }
            $path = $image->path;
            $image->delete();
            if ($image->is_primary) {
                $product->images()->first()?->update(['is_primary' => true]);
            }
            DB::afterCommit(fn () => Storage::disk('public')->delete($path));
        });
    }
}
