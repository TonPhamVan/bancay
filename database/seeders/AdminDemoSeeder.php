<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Optional local synthetic fixtures, with real generated image files for the Phase 2 UI. */
final class AdminDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Synthetic fixtures are local/testing only.');
        }
        $this->call(DemoDataSeeder::class);
        $paths = Product::where('slug', 'demo-greenhome')->first()?->images()->pluck('path')->all() ?? [];
        // Only paths owned by the explicit synthetic fixture are eligible; never scan/import legacy media.
        $paths[] = Banner::where('name', 'Synthetic banner')->value('image');
        foreach (array_filter($paths) as $path) {
            if (Storage::disk('public')->exists($path)) {
                continue;
            }
            $image = imagecreatetruecolor(640, 360);
            imagefill($image, 0, 0, imagecolorallocate($image, 43, 105, 66));
            imagestring($image, 5, 30, 150, 'SYNTHETIC LOCAL FIXTURE', imagecolorallocate($image, 255, 255, 255));
            ob_start();
            imagejpeg($image, null, 85);
            $bytes = ob_get_clean();
            imagedestroy($image);
            Storage::disk('public')->put($path, $bytes);
        }
    }
}
