<?php

namespace App\Legacy;

use Illuminate\Support\Facades\DB;

final class TargetGuard
{
    public function check(): array
    {
        $database = DB::connection()->getDatabaseName();
        if (DB::connection()->getConfig('host') !== 'db'
            || ! config('legacy.enabled') || ! app()->environment(['local', 'testing']) || config('app.debug')
            || $database !== config('legacy.target_database')
            || (! preg_match('/\Abancay_phase4_[a-z0-9_]+\z/', $database) && ! (app()->environment('testing') && $database === 'bancay_test'))
            || str_starts_with($database, 'bancay_phase4_source') || config('mail.default') !== 'log' && ! app()->environment('testing')) {
            throw new MigrationFailure('UNSAFE_MIGRATION_TARGET');
        }
        $root = realpath(config('legacy.report_directory') ?: '');
        $private = realpath(base_path('legacy-private'));
        if (! $root || ! $private || ! str_starts_with($root.'/', $private.'/')) {
            throw new MigrationFailure('UNSAFE_REPORT_DIRECTORY');
        }
        $proofPath = config('legacy.backup_manifest');
        $proof = $proofPath && is_file($proofPath) ? json_decode(file_get_contents($proofPath), true, 512, JSON_THROW_ON_ERROR) : [];
        if (($proof['status'] ?? '') !== 'BACKUP VERIFIED' || ($proof['target_database'] ?? '') !== $database) {
            throw new MigrationFailure('BACKUP_NOT_VERIFIED');
        }
        foreach (['source_sql', 'source_zip', 'target_backup'] as $key) {
            $file = $proof[$key]['path'] ?? '';
            if (! is_file($file) || hash_file('sha256', $file) !== ($proof[$key]['sha256'] ?? null)) {
                throw new MigrationFailure('BACKUP_CHECKSUM_MISMATCH', ['artifact' => $key]);
            }
        }

        return $proof;
    }

    public function fingerprint(): string
    {
        $hash = hash_init('sha256');
        foreach (['categories', 'products', 'product_images', 'product_options', 'product_option_values', 'product_variants', 'product_variant_values', 'inventories', 'customers', 'customer_addresses', 'orders', 'order_items', 'order_status_histories', 'article_categories', 'articles', 'banners', 'settings'] as $table) {
            hash_update($hash, $table);
            foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
                hash_update($hash, json_encode((array) $row, JSON_THROW_ON_ERROR));
            }
        }

        return hash_final($hash);
    }
}
