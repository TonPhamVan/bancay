<?php

namespace App\Services\Admin;

use App\Actions\Catalog\CreateProduct;
use App\Admin\StoreResources;
use App\Domain\Catalog\PublicationStatus;
use App\Domain\Catalog\RecordStatus;
use App\Models\Category;
use App\Rules\DatabaseText;
use App\Rules\DatabaseTimestamp;
use App\Services\CategoryTreeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

final class RecordEditor
{
    public function save(string $module, string $entity, array $input, ?int $id = null): Model
    {
        $definition = StoreResources::get($module, $entity);
        abort_if(in_array($entity, ['orders', 'inventory', 'settings'], true), 405);
        $model = $id !== null ? $definition['model']::findOrFail($id) : new $definition['model'];
        if (array_key_exists('slug', $definition['fields']) && empty($input['slug'])) {
            $input['slug'] = Str::slug($input[$definition['display']] ?? '');
        }
        $rules = $this->rules($entity, $model);
        if ($entity === 'banners' && ! $model->exists) {
            $rules['image_upload'][0] = 'required';
        }
        $data = Validator::make($input, $rules)->validate();
        if ($entity === 'banners' && isset($data['starts_at'], $data['ends_at']) && strtotime($data['ends_at']) < strtotime($data['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'Ngày kết thúc phải sau ngày bắt đầu.']);
        }
        // Only explicitly validated business fields survive; no legacy IDs, stock or actor IDs.
        unset($data['image_upload']);
        $path = null;
        try {
            if (isset($input['image_upload'])) {
                $path = app(ImageStorage::class)->store($input['image_upload'], match ($entity) {
                    'categories', 'article-categories' => 'categories', 'articles' => 'articles', 'banners' => 'banners',
                });
                $data['image'] = $path;
            }

            return DB::transaction(function () use ($entity, $model, $data) {
                $parent = $data['parent_id'] ?? null;
                unset($data['parent_id']);
                foreach (['description', 'short_description', 'content', 'excerpt'] as $field) {
                    if (isset($data[$field])) {
                        $data[$field] = Purifier::clean($data[$field]);
                    }
                }
                if (in_array($entity, ['categories', 'article-categories'], true) && isset($data['description'])) {
                    Validator::make($data, ['description' => [new DatabaseText]])->validate();
                }
                if ($entity === 'products' && ! $model->exists) {
                    return app(CreateProduct::class)->execute($data);
                }
                // Tree mutations take the ordered tree lock before any single-row write.
                if (in_array($entity, ['categories', 'article-categories'], true)) {
                    $model->newQuery()->orderBy('id')->lockForUpdate()->get();
                } elseif ($model->exists) {
                    $model = $model->newQuery()->lockForUpdate()->findOrFail($model->id);
                }
                $oldImage = $model->image;
                $model->fill($data)->save();
                if (in_array($entity, ['categories', 'article-categories'], true)) {
                    $model = app(CategoryTreeService::class)->move($model, $parent === null ? null : (int) $parent);
                }
                if (isset($data['image']) && $oldImage && $oldImage !== $data['image']) {
                    DB::afterCommit(fn () => app(ImageStorage::class)->deleteIfUnused($oldImage));
                }

                return $model;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
    }

    public function delete(string $module, string $entity, int $id): void
    {
        $definition = StoreResources::get($module, $entity);
        abort_if(in_array($entity, ['orders', 'inventory', 'settings'], true), 405);
        DB::transaction(function () use ($definition, $entity, $id) {
            $model = $definition['model']::lockForUpdate()->findOrFail($id);
            if ($entity === 'products') {
                $model->update(['status' => PublicationStatus::Archived]);
                $model->delete(); // SKU, inventories and order snapshots stay intact.
            } elseif (in_array($entity, ['articles', 'customers'], true)) {
                $model->update(['status' => $entity === 'articles' ? PublicationStatus::Archived : RecordStatus::Inactive]);
            } else {
                $path = $model->image;
                $model->delete(); // FK RESTRICT applies to category children/products/articles/banners.
                if ($path) {
                    DB::afterCommit(fn () => app(ImageStorage::class)->deleteIfUnused($path));
                }
            }
        });
    }

    private function rules(string $entity, Model $model): array
    {
        $pub = ['status' => ['required', Rule::enum(PublicationStatus::class)], 'published_at' => ['nullable', 'date', new DatabaseTimestamp, 'required_if:status,PUBLISHED']];
        $seo = ['meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:1000']];
        $slug = ['slug' => ['required', 'string', 'max:191', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique($model->getTable())->ignore($model->id)]];
        $name = ['name' => ['required', 'string', 'max:255']];
        $sort = ['sort_order' => ['required', 'integer', 'min:0', 'max:2147483647']];
        $image = ['image_upload' => ['nullable', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120']];

        return match ($entity) {
            'products' => array_replace(CreateProduct::rules(), $slug, $pub, ['options' => ['prohibited']]),
            'categories', 'article-categories' => $name + $slug + $pub + $seo + $sort + $image + [
                'parent_id' => ['nullable', 'integer', Rule::exists($model->getTable(), 'id')],
                'description' => ['nullable', 'string', 'max:65535', new DatabaseText],
                ($entity === 'categories' ? 'featured' : 'show_in_menu') => ['required', 'boolean']],
            'articles' => ['title' => ['required', 'string', 'max:255'], 'article_category_id' => ['required', 'integer', 'exists:article_categories,id'],
                'excerpt' => ['nullable', 'string', 'max:10000'], 'content' => ['nullable', 'string', 'max:1000000'],
                'featured' => ['required', 'boolean'], 'show_in_menu' => ['required', 'boolean']] + $slug + $pub + $seo + $image,
            'customers' => $name + ['phone' => ['nullable', 'string', 'max:30', 'regex:/\A[0-9+(). -]*\z/'],
                'email' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/', 'email'], 'status' => ['required', Rule::enum(RecordStatus::class)]],
            'banners' => $name + $sort + $image + [
                'link' => ['nullable', 'string', 'max:2000', 'url:http,https'],
                'position' => ['required', Rule::in(array_keys(config('website.banner_positions')))],
                'category_id' => ['nullable', 'integer', 'exists:categories,id', 'required_if:position,category'],
                'priority' => ['required', 'integer', 'min:0', 'max:2147483647'],
                'status' => ['required', Rule::enum(RecordStatus::class)], 'starts_at' => ['nullable', 'date', new DatabaseTimestamp],
                'ends_at' => ['nullable', 'date', new DatabaseTimestamp]],
        };
    }
}
