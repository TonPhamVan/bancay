<?php

namespace App\Legacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Repair only generated legacy image attributes; preserve all other content bytes. */
final class ContentMediaUrls
{
    public const FIELDS = ['products' => ['description', 'short_description'], 'categories' => ['description'],
        'article_categories' => ['description'], 'articles' => ['content', 'excerpt']];

    public function normalize(string $html): string
    {
        return preg_replace_callback('~(\\s(?:src|poster)=["\'])http://localhost:8002/storage/((?:articles|products|categories|article-categories)/legacy/[a-f0-9]{64}\\.(?:webp|gif))(["\'])~', function ($match) {
            if ($match[1][strlen($match[1]) - 1] !== $match[3]
                || ! Storage::disk('public')->exists($match[2])) {
                throw new MigrationFailure('CONTENT_ASSET_NOT_VERIFIED');
            }

            return $match[1].'/storage/'.$match[2].$match[3];
        }, $html);
    }

    public function plan(): array
    {
        $changes = [];
        foreach (self::FIELDS as $table => $fields) {
            foreach (DB::table($table)->whereNotNull('legacy_id')->orderBy('id')->get(['id', ...$fields]) as $row) {
                foreach ($fields as $field) {
                    $before = (string) $row->$field;
                    $after = $this->normalize($before);
                    if ($before !== $after) {
                        $changes[] = ['table' => $table, 'id' => $row->id, 'field' => $field,
                            'before_sha256' => hash('sha256', $before), 'after_sha256' => hash('sha256', $after)];
                    }
                }
            }
        }

        return $changes;
    }

    public function apply(array $plan): int
    {
        return DB::transaction(function () use ($plan) {
            foreach (self::FIELDS as $table => $fields) {
                DB::table($table)->whereNotNull('legacy_id')->orderBy('id')->lockForUpdate()->get(['id']);
            }
            if ($this->plan() !== $plan) {
                throw new MigrationFailure('STALE_CONTENT_PLAN');
            }
            foreach ($plan as $change) {
                $query = DB::table($change['table'])->where('id', $change['id'])->whereNotNull('legacy_id');
                $before = $query->value($change['field']);
                // Query builder intentionally preserves publication, recorded dates and updated_at.
                $query->update([$change['field'] => $this->normalize($before)]);
            }

            return count($plan);
        });
    }
}
