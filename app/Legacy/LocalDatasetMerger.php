<?php

namespace App\Legacy;

use Illuminate\Support\Facades\DB;

/** Copy an already validated staging dataset into local demo, remapping every FK. */
final class LocalDatasetMerger
{
    public const TABLES = ['categories', 'article_categories', 'products', 'product_images', 'product_options',
        'product_option_values', 'product_variants', 'product_variant_values', 'inventories', 'customers',
        'customer_addresses', 'orders', 'order_items', 'order_status_histories', 'articles', 'banners', 'settings'];

    private const REFERENCES = ['categories' => ['parent_id' => 'categories'], 'article_categories' => ['parent_id' => 'article_categories'],
        'products' => ['category_id' => 'categories'], 'product_images' => ['product_id' => 'products'],
        'product_options' => ['product_id' => 'products'], 'product_option_values' => ['product_id' => 'products', 'product_option_id' => 'product_options'],
        'product_variants' => ['product_id' => 'products', 'image_id' => 'product_images'],
        'product_variant_values' => ['product_id' => 'products', 'product_variant_id' => 'product_variants', 'product_option_id' => 'product_options', 'product_option_value_id' => 'product_option_values'],
        'inventories' => ['product_variant_id' => 'product_variants'], 'customer_addresses' => ['customer_id' => 'customers'],
        'orders' => ['customer_id' => 'customers'], 'order_items' => ['order_id' => 'orders', 'product_id' => 'products', 'product_variant_id' => 'product_variants'],
        'order_status_histories' => ['order_id' => 'orders'], 'articles' => ['article_category_id' => 'article_categories'], 'banners' => ['category_id' => 'categories']];

    public function preflight(array $source): void
    {
        if (! app()->environment(['local', 'testing']) || config('app.debug') || DB::connection()->getConfig('host') !== 'db'
            || ! in_array(DB::connection()->getDatabaseName(), ['bancay', 'bancay_test'], true)) {
            throw new MigrationFailure('UNSAFE_COMBINED_TARGET');
        }
        if (array_diff(self::TABLES, array_keys($source))) {
            throw new MigrationFailure('INCOMPLETE_COMBINED_SOURCE');
        }
        foreach (['categories' => 'slug', 'article_categories' => 'slug', 'products' => 'slug', 'articles' => 'slug', 'product_variants' => 'sku', 'orders' => 'order_number'] as $table => $column) {
            foreach ($source[$table] as $row) {
                if (DB::table($table)->where($column, $row[$column])->exists()
                    || isset($row['legacy_id']) && DB::table($table)->where('legacy_id', $row['legacy_id'])->exists()) {
                    throw new MigrationFailure('COMBINED_UNIQUE_COLLISION', ['table' => $table, 'source_id' => (int) $row['id']]);
                }
            }
        }
        foreach ($source['orders'] as $row) {
            if (! $row['is_legacy']) {
                throw new MigrationFailure('COMBINED_LIVE_ORDERS_NOT_ALLOWED');
            }
        }
        foreach ($source['order_status_histories'] as $row) {
            if ($row['changed_by'] !== null) {
                throw new MigrationFailure('COMBINED_ACTOR_MAPPING_REQUIRED');
            }
        }
    }

    public function apply(array $source, bool $publishLocal): array
    {
        $this->preflight($source);

        return DB::transaction(function () use ($source, $publishLocal) {
            $maps = $counts = $retainedSettings = $expected = [];
            $publishedAt = now()->toDateTimeString();
            foreach (self::TABLES as $table) {
                $maps[$table] = [];
                $counts[$table] = 0;
                $pending = $source[$table];
                while ($pending) {
                    $progress = false;
                    foreach ($pending as $index => $original) {
                        $oldId = (int) $original['id'];
                        if (in_array($table, ['categories', 'article_categories'], true) && $original['parent_id'] !== null && ! isset($maps[$table][$original['parent_id']])) {
                            continue;
                        }
                        if ($table === 'settings') {
                            $maps[$table][$oldId] = DB::table('settings')->where('key', $original['key'])->value('id');
                            $retainedSettings[] = $original['key'];
                            unset($pending[$index]);
                            $progress = true;

                            continue;
                        }
                        $row = $original;
                        unset($row['id'], $row['primary_product_id'], $row['default_customer_id']);
                        foreach (self::REFERENCES[$table] ?? [] as $column => $parent) {
                            if ($row[$column] !== null) {
                                $row[$column] = $maps[$parent][$row[$column]] ?? throw new MigrationFailure('COMBINED_UNMAPPED_REFERENCE', ['table' => $table, 'column' => $column, 'source_id' => $oldId]);
                            }
                        }
                        if ($table === 'product_variants') {
                            $ids = [];
                            foreach ($source['product_variant_values'] as $pivot) {
                                if ((int) $pivot['product_variant_id'] === $oldId) {
                                    $ids[] = $maps['product_option_values'][$pivot['product_option_value_id']];
                                }
                            }
                            sort($ids, SORT_NUMERIC);
                            $row['combination_key'] = hash('sha256', implode(':', $ids));
                        }
                        if ($publishLocal && in_array($table, ['categories', 'article_categories', 'products', 'articles'], true) && $row['status'] === 'DRAFT') {
                            $row['status'] = 'PUBLISHED';
                            $row['published_at'] = $publishedAt;
                        }
                        $id = DB::table($table)->insertGetId($row);
                        $maps[$table][$oldId] = $id;
                        $expected[$table][$id] = $row;
                        $counts[$table]++;
                        unset($pending[$index]);
                        $progress = true;
                    }
                    if (! $progress) {
                        throw new MigrationFailure('COMBINED_CATEGORY_CYCLE_OR_PARENT');
                    }
                }
            }
            foreach ($expected as $table => $rows) {
                foreach ($rows as $id => $row) {
                    $actual = (array) DB::table($table)->where('id', $id)->first();
                    foreach ($row as $column => $value) {
                        if (($actual[$column] === null) !== ($value === null) || (string) $actual[$column] !== (string) $value) {
                            throw new MigrationFailure('COMBINED_COPY_MISMATCH', ['table' => $table, 'id' => $id, 'column' => $column]);
                        }
                    }
                }
            }

            return ['id_maps' => $maps, 'created' => $counts, 'retained_existing_setting_keys' => $retainedSettings,
                'local_preview_publication' => $publishLocal, 'local_preview_published_at' => $publishedAt];
        });
    }
}
