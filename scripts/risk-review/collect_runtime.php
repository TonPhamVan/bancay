<?php

use Composer\InstalledVersions;
use Exceedone\Exment\Auth\PublicFormGuard;
use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bancay') {
    throw new RuntimeException('Read-only collector requires the documented local instance.');
}
$baseline = json_decode(file_get_contents(base_path('docs/phase-1/evidence/schema.json')), true);
$changes = [];
$canonical = function ($rows) {
    $values = array_map(fn ($r) => (array) $r, $rows);
    usort($values, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));

    return $values;
};
foreach ($baseline['tables'] as $table => $expected) {
    $columns = DB::select('SELECT COLUMN_NAME as column_name,COLUMN_TYPE as column_type,IS_NULLABLE as is_nullable,COLUMN_DEFAULT as column_default,EXTRA as extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$table]);
    if ($canonical($columns) != $canonical($expected['columns'])) {
        $changes[] = $table.':columns';
    }
    $fks = DB::select('SELECT k.COLUMN_NAME as column_name,k.REFERENCED_TABLE_NAME as referenced_table_name,k.REFERENCED_COLUMN_NAME as referenced_column_name,r.DELETE_RULE as delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$table]);
    if ($canonical($fks) != $canonical($expected['foreign_keys'])) {
        $changes[] = $table.':foreign_keys';
    }
    $indexes = DB::select('SELECT INDEX_NAME as key_name,NON_UNIQUE as non_unique,SEQ_IN_INDEX as seq_in_index,COLUMN_NAME as column_name,INDEX_TYPE as index_type FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
    $old = array_map(function ($row) {
        $result = [];
        foreach (['key_name', 'non_unique', 'seq_in_index', 'column_name', 'index_type'] as $key) {
            $result[$key] = $row[$key];
        }

        return $result;
    }, $expected['indexes']);
    if ($canonical($indexes) != $canonical($old)) {
        $changes[] = $table.':indexes';
    }
}
$checks = DB::select("SELECT CONSTRAINT_NAME AS name,CHECK_CLAUSE AS expression FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('variants_money_domain_check','orders_money_domain_check','items_money_domain_check') ORDER BY CONSTRAINT_NAME");
$guardDrivers = collect(config('auth.guards'))->map(fn ($g) => $g['driver'])->all();
$guardClasses = [];
$unavailableGuards = [];
foreach (array_keys($guardDrivers) as $name) {
    try {
        $guardClasses[$name] = get_class(Auth::guard($name));
    } catch (Throwable) {
        $unavailableGuards[$name] = 'Cannot instantiate with current local configuration; no keys generated.';
    }
}
echo json_encode(['environment' => app()->environment(), 'php' => PHP_VERSION, 'php_int_size' => PHP_INT_SIZE,
    'laravel' => app()->version(), 'exment' => InstalledVersions::getPrettyVersion('exceedone/exment'),
    'domain_tables_checked' => count($baseline['tables']), 'column_fk_index_changes' => $changes, 'added_checks' => $checks,
    'debug' => config('app.debug'), 'mail_driver' => config('mail.default'), 'guard_drivers' => $guardDrivers,
    'guard_classes' => $guardClasses, 'unavailable_guards' => $unavailableGuards, 'instantiated_top_level_token_guard' => collect($guardClasses)->contains(fn ($class) => is_a($class, TokenGuard::class, true)), 'public_form_guard_extends_token_guard' => is_subclass_of(PublicFormGuard::class, TokenGuard::class), 'exment_api' => config('exment.api'), 'outside_api' => config('exment.outside_api'),
    'session_driver' => config('session.driver'), 'cache_driver' => config('cache.default'), 'indexable' => config('storefront.indexable'),
    'migration_applied' => DB::table('migrations')->where('migration', '2026_10_28_000002_harden_money_domain_checks')->exists()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
