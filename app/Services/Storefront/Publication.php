<?php

namespace App\Services\Storefront;

use App\Models\ArticleCategory;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class Publication
{
    public static function scope(Builder $query): Builder
    {
        return $query->where('status', 'PUBLISHED')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Hidden/future ancestors hide every descendant; cycles fail closed. */
    public function categories(bool $articles = false): Collection
    {
        $rows = ($articles ? ArticleCategory::query() : Category::query())->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');

        return $rows->filter(function ($category) use ($rows) {
            $cursor = $category;
            $visited = [];
            while ($cursor) {
                if (in_array($cursor->id, $visited, true) || $cursor->status->value !== 'PUBLISHED'
                    || ! $cursor->published_at || $cursor->published_at->isFuture()) {
                    return false;
                }
                $visited[] = $cursor->id;
                if ($cursor->parent_id === null) {
                    return true;
                }
                $cursor = $rows->get($cursor->parent_id);
            }

            return false;
        });
    }

    public function descendants(Collection $categories, int $id): array
    {
        $ids = [$id];
        for ($index = 0; $index < count($ids); $index++) {
            foreach ($categories as $category) {
                if ((int) $category->parent_id === $ids[$index] && ! in_array($category->id, $ids, true)) {
                    $ids[] = $category->id;
                }
            }
        }

        return $ids;
    }
}
