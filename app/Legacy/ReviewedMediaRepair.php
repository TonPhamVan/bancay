<?php

namespace App\Legacy;

use App\Legacy\Reports\RunReport;
use App\Legacy\Transformers\ProductTransformer;
use App\Models\Product;
use App\Services\Admin\ImageStorage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReviewedMediaRepair
{
    public function __construct(private Source $source, private ReviewOverrides $review) {}

    public function plan(): array
    {
        $changes = [];
        $media = new Media($this->review);
        $baseline = new Media(new ReviewOverrides(false));
        foreach ($this->review->contentRepairs() as $repair) {
            $field = $repair['field'];
            $sourceField = match ($field) {
                'description' => 'content', 'short_description' => 'sapo',
                default => throw new MigrationFailure('UNSAFE_REVIEW_REPAIR_FIELD'),
            };
            $product = $this->product((int) $repair['legacy_id']);
            $row = $this->source->find('tblsanpham', $product->legacy_id);
            $before = $baseline->html($row[$sourceField], false, new RunReport, 'products', $product->legacy_id);
            $after = $media->html($row[$sourceField], false, new RunReport, 'products', $product->legacy_id);
            if ($after === $product->$field) {
                continue;
            }
            if ($before !== $product->$field) {
                throw new MigrationFailure('MANUAL_CONTENT_EDIT_REQUIRES_REVIEW');
            }
            $changes[] = ['kind' => 'content', 'legacy_id' => $product->legacy_id, 'field' => $field,
                'before_sha256' => hash('sha256', $before), 'after_sha256' => hash('sha256', $after)];
        }
        foreach ($this->review->galleryRepairs() as $id) {
            $product = $this->product((int) $id);
            $row = $this->source->find('tblsanpham', (int) $id);
            $folder = 'upload/sp/'.CarbonImmutable::createFromTimestamp((int) $row['created_at'], 'Asia/Saigon')->format('mY').'/';
            $images = $product->images()->orderBy('id')->get()->map->getAttributes()->all();
            $existing = array_column($images, 'path');
            $paths = [];
            foreach ((new ProductTransformer)->gallery($row) as $file) {
                $relative = $folder.$file;
                if ($this->review->mediaPath($relative) === $relative) {
                    continue;
                }
                $path = $media->image($relative, 'products', false, new RunReport, 'products', (int) $id);
                if (! $path) {
                    throw new MigrationFailure('REVIEW_IMAGE_INVALID');
                }
                if (! in_array($path, $existing, true)) {
                    $paths[$path] = $relative;
                }
            }
            if ($paths) {
                $changes[] = ['kind' => 'gallery', 'legacy_id' => (int) $id, 'before_sha256' => hash('sha256', json_encode($images, JSON_THROW_ON_ERROR)), 'paths' => $paths];
            }
        }

        return $changes;
    }

    public function apply(array $plan): int
    {
        $media = new Media($this->review);
        try {
            return DB::transaction(function () use ($plan, $media) {
                foreach ($plan as $change) {
                    Product::where('legacy_id', $change['legacy_id'])->lockForUpdate()->firstOrFail()->images()->lockForUpdate()->get();
                }
                if ($this->plan() !== $plan) {
                    throw new MigrationFailure('STALE_MEDIA_REPAIR_PLAN');
                }
                foreach ($plan as $change) {
                    $product = $this->product($change['legacy_id']);
                    if ($change['kind'] === 'content') {
                        $row = $this->source->find('tblsanpham', $product->legacy_id);
                        $sourceField = $change['field'] === 'description' ? 'content' : 'sapo';
                        $html = $media->html($row[$sourceField], true, new RunReport, 'products', $product->legacy_id);
                        if (hash('sha256', $html) !== $change['after_sha256']) {
                            throw new MigrationFailure('REVIEW_MEDIA_CHANGED');
                        }
                        DB::table('products')->where('id', $product->id)->update([$change['field'] => $html]);
                    } else {
                        $sort = (int) $product->images()->max('sort_order');
                        foreach ($change['paths'] as $expected => $relative) {
                            $path = $media->image($relative, 'products', true, new RunReport, 'products', $product->legacy_id);
                            if ($path !== $expected) {
                                throw new MigrationFailure('REVIEW_MEDIA_CHANGED');
                            }
                            $product->images()->create(['path' => $path, 'alt_text' => $product->name, 'sort_order' => ++$sort, 'is_primary' => ! $product->images()->exists()]);
                        }
                    }
                }

                return count($plan);
            });
        } catch (\Throwable $exception) {
            foreach ($media->created as $path) {
                app(ImageStorage::class)->deleteIfUnused($path);
            }
            throw $exception;
        }
    }

    private function product(int $legacyId): Product
    {
        $product = Product::where('legacy_id', $legacyId)->firstOrFail();
        if ($product->status->value !== 'DRAFT' || $product->published_at !== null) {
            throw new MigrationFailure('REVIEW_REPAIR_REQUIRES_DRAFT');
        }

        return $product;
    }
}
