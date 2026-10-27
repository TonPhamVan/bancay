<?php

namespace App\Services;

use App\Models\ArticleCategory;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CategoryTreeService
{
    public function move(Category|ArticleCategory $category, ?int $parentId): Category|ArticleCategory
    {
        return DB::transaction(function () use ($category, $parentId) {
            // Small single-store tree: one ordered lock set avoids concurrent cycles.
            $tree = $category->newQuery()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if (! $tree->has($category->id) || ($parentId !== null && ! $tree->has($parentId))) {
                throw ValidationException::withMessages(['parent_id' => 'Category or parent does not exist.']);
            }
            $cursor = $parentId;
            $visited = [];
            while ($cursor !== null) {
                if ($cursor === $category->id || in_array($cursor, $visited, true)) {
                    throw ValidationException::withMessages(['parent_id' => 'Category trees cannot contain cycles.']);
                }
                $visited[] = $cursor;
                $parent = $tree->get($cursor)?->parent_id;
                $cursor = $parent === null ? null : (int) $parent;
            }
            $category = $tree->get($category->id);
            $category->update(['parent_id' => $parentId]);

            return $category;
        });
    }
}
