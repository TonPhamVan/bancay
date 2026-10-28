<?php

namespace App\Services\Admin;

use App\Models\Setting;
use App\Rules\PublicImagePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ImageStorage
{
    /** Post-commit housekeeping must never undo a successfully committed image replacement. */
    public function deleteIfUnused(string $path): void
    {
        try {
            if (Validator::make(['path' => $path], ['path' => [new PublicImagePath]])->fails()) {
                return;
            }
            foreach (['product_images' => 'path', 'categories' => 'image', 'article_categories' => 'image',
                'articles' => 'image', 'banners' => 'image'] as $table => $column) {
                if (DB::table($table)->where($column, $path)->exists()) {
                    return;
                }
            }
            if (Setting::where('key', 'logo')->value('value') === $path) {
                return;
            }
            if (! Storage::disk('public')->delete($path)) {
                $this->cleanupDeferred($path);
            }
        } catch (\Throwable) {
            $this->cleanupDeferred($path);
        }
    }

    private function cleanupDeferred(string $path): void
    {
        try {
            Log::warning('store.image.cleanup_deferred', ['path_hash' => hash('sha256', $path)]);
        } catch (\Throwable) {
            // Logging is also best-effort after commit; it cannot roll back the saved record.
        }
    }

    public function store(UploadedFile $file, string $folder): string
    {
        if (! in_array($folder, ['products', 'categories', 'articles', 'banners', 'settings'], true)) {
            throw new \LogicException('Unknown image folder.');
        }
        Validator::make(['image' => $file], ['image' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120']])->validate();
        $dimensions = @getimagesize($file->getRealPath());
        if (! $dimensions || $dimensions[0] > 8000 || $dimensions[1] > 8000 || $dimensions[0] * $dimensions[1] > 25000000) {
            throw ValidationException::withMessages(['image' => 'Ảnh tối đa 8000px mỗi chiều và 25 triệu pixels.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['image' => 'Không đọc được ảnh.']);
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        try {
            if (! imagewebp($image, null, 85)) {
                throw new \RuntimeException('Image encoding failed.');
            }
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
        $path = $folder.'/'.Str::ulid().'.webp';
        if (! Storage::disk('public')->put($path, $bytes)) {
            throw new \RuntimeException('Image write failed.');
        }

        return $path;
    }
}
