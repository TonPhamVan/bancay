<?php

namespace App\Services\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ImageStorage
{
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
