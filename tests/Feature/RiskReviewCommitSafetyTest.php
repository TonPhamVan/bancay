<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\Admin\RecordEditor;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class RiskReviewCommitSafetyTest extends TestCase
{
    public function test_cleanup_and_logger_failure_do_not_undo_committed_image_replacement(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $manager = Storage::getFacadeRoot();
        $logger = Log::getFacadeRoot();
        try {
            Storage::fake('public');
            $disk = Storage::disk('public');
            $old = 'categories/review-old.webp';
            $disk->put($old, 'synthetic old image');
            $category = Category::factory()->create(['image' => $old]);
            $proxy = Mockery::mock($disk);
            $proxy->shouldReceive('delete')->andThrow(new \RuntimeException('Synthetic storage outage'));
            Storage::shouldReceive('disk')->with('public')->andReturn($proxy);
            Log::shouldReceive('warning')->andThrow(new \RuntimeException('Synthetic log outage'));
            app(RecordEditor::class)->save('catalog', 'categories', ['name' => $category->name, 'slug' => $category->slug,
                'status' => 'DRAFT', 'sort_order' => 0, 'featured' => 0, 'image_upload' => UploadedFile::fake()->image('new.jpg')], $category->id);
            $new = $category->fresh()->image;
            $this->assertNotSame($old, $new);
            $this->assertTrue($disk->exists($new));
            $this->assertTrue($disk->exists($old)); // Deferred orphan cleanup, not lost committed media.
            $this->assertSame(0, \DB::transactionLevel());
        } finally {
            Storage::swap($manager);
            Log::swap($logger);
            $this->artisan('migrate:fresh');
            RefreshDatabaseState::$migrated = false;
        }
    }
}
