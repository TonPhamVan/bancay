<?php

namespace App\Services\Admin;

use App\Admin\StoreLabels;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProductImages
{
    public function version(Product $product): string
    {
        return $this->digest($product->id, $product->images()->orderBy('id')->get());
    }

    private function digest(int $productId, Collection $images): string
    {
        return hash_hmac('sha256', json_encode([$productId, $images->map(fn ($image) => $image->only(['id', 'path', 'alt_text', 'sort_order', 'is_primary']))->all()], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /** Whole-gallery metadata save only. Files, paths, ownership and SKU links are never request-writable. */
    public function batch(Product $product, array $input): void
    {
        $data = StoreLabels::validate($input, [
            'expected_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'primary_image_id' => ['required', 'integer', 'min:1'],
            'images' => ['required', 'array', 'min:1', 'max:200'],
            'images.*' => ['required', 'array:id,alt_text,sort_order'],
            'images.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ], ['images' => 'Danh sách ảnh', 'images.*.alt_text' => 'Mô tả ảnh', 'images.*.sort_order' => 'Thứ tự ảnh',
            'primary_image_id' => 'Ảnh chính', 'expected_version' => 'Phiên bản ảnh']);

        DB::transaction(function () use ($product, $data) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $images = $product->images()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if (! hash_equals($this->digest($product->id, $images->values()), $data['expected_version'])) {
                throw ValidationException::withMessages(['images' => 'Danh sách ảnh đã thay đổi. Tải lại trang để kiểm tra trước khi lưu lại.']);
            }
            $ids = collect($data['images'])->pluck('id')->map(fn ($id) => (int) $id);
            abort_unless($ids->every(fn ($id) => $images->has($id)) && $images->has((int) $data['primary_image_id']), 404);
            if ($ids->unique()->count() !== $ids->count() || $ids->count() !== $images->count()) {
                throw ValidationException::withMessages(['images' => 'Cần gửi đủ ảnh của sản phẩm, mỗi ảnh một lần.']);
            }
            $product->images()->where('is_primary', true)->update(['is_primary' => false]);
            foreach ($data['images'] as $row) {
                $image = $images[$row['id']];
                $image->is_primary = false;
                $image->syncOriginalAttribute('is_primary', false);
                $image->update(['alt_text' => $row['alt_text'] ?? null, 'sort_order' => $row['sort_order'],
                    'is_primary' => (int) $row['id'] === (int) $data['primary_image_id']]);
            }
        });
    }

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
            DB::afterCommit(fn () => app(ImageStorage::class)->deleteIfUnused($path));
        });
    }
}
