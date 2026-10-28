<?php

use App\Legacy\LocalDatasetMerger;
use App\Legacy\MigrationFailure;
use App\Legacy\TargetGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || config('app.debug') || DB::connection()->getConfig('host') !== 'db'
    || DB::connection()->getDatabaseName() !== 'bancay' || config('mail.default') !== 'log'
    || ! in_array($argv[1] ?? '', ['--dry-run', '--apply', '--verify-rerun'], true)) {
    throw new MigrationFailure('UNSAFE_COMBINED_ENVIRONMENT');
}
$directory = base_path('legacy-private/ui-mapping/combined');
$reader = json_decode(file_get_contents($directory.'/reader.json'), true, 512, JSON_THROW_ON_ERROR);
config(['database.connections.combined_source' => array_replace(config('database.connections.mysql'), [
    'url' => null, 'database' => 'bancay_phase4_staging', 'username' => $reader['username'], 'password' => $reader['password'],
])]);
$sourceConnection = DB::connection('combined_source');
$grants = implode(' ', array_map(fn ($row) => implode(' ', (array) $row), $sourceConnection->select('SHOW GRANTS')));
if (! str_contains($grants, 'GRANT SELECT ON `bancay_phase4_staging`.*') || preg_match('/GRANT (?:ALL|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER)/i', $grants)) {
    throw new MigrationFailure('COMBINED_SOURCE_NOT_READ_ONLY');
}
$sourceConnection->statement('SET SESSION TRANSACTION READ ONLY');
$read = function () use ($sourceConnection) {
    $rows = [];
    foreach (LocalDatasetMerger::TABLES as $table) {
        $rows[$table] = $sourceConnection->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }

    return $rows;
};
$source = $read();
$sourceHash = hash('sha256', json_encode($source, JSON_THROW_ON_ERROR));
$targetHash = app(TargetGuard::class)->fingerprint();
$sourceRoot = realpath(base_path('legacy-private/phase4/storage/app/public'));
$targetRoot = realpath(Storage::disk('public')->path(''));
if (! $sourceRoot || ! $targetRoot || $sourceRoot === $targetRoot || $targetRoot !== realpath(base_path('storage/app/public'))) {
    throw new MigrationFailure('UNSAFE_COMBINED_MEDIA_ROOT');
}
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isLink()) {
        throw new MigrationFailure('COMBINED_MEDIA_SYMLINK');
    }
    if ($file->isFile() && $file->getFilename() !== '.gitignore') {
        $path = substr($file->getPathname(), strlen($sourceRoot) + 1);
        if (! preg_match('#\A(?:products|articles|categories|banners|settings)/legacy/[a-f0-9]{64}\.(?:webp|gif)\z#', $path)) {
            throw new MigrationFailure('COMBINED_UNREVIEWED_MEDIA');
        }
        $hash = hash_file('sha256', $file->getPathname());
        if (is_file($targetRoot.'/'.$path) && hash_file('sha256', $targetRoot.'/'.$path) !== $hash) {
            throw new MigrationFailure('COMBINED_MEDIA_COLLISION');
        }
        $files[$path] = $hash;
    }
}
ksort($files);
$binding = ['source_database' => 'bancay_phase4_staging', 'target_database' => 'bancay', 'source_fingerprint' => $sourceHash,
    'target_before' => $targetHash, 'files' => $files, 'code' => hash_file('sha256', app_path('Legacy/LocalDatasetMerger.php')),
    'local_preview_publication' => true, 'settings_policy' => 'Keep all existing website settings; preserve source settings in original staging, backup and private settings archive'];
$lock = fopen($directory.'/merge.lock', 'c');
if (! flock($lock, LOCK_EX | LOCK_NB)) {
    throw new MigrationFailure('COMBINED_MERGE_ALREADY_RUNNING');
}
try {
    if ($argv[1] === '--verify-rerun') {
        $receipt = json_decode(file_get_contents($directory.'/receipt.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($sourceHash !== $receipt['binding']['source_fingerprint'] || $targetHash !== $receipt['target_after']) {
            throw new MigrationFailure('COMBINED_CHECKPOINT_CHANGED');
        }
        foreach ($files as $path => $hash) {
            if (! is_file($targetRoot.'/'.$path) || hash_file('sha256', $targetRoot.'/'.$path) !== $hash) {
                throw new MigrationFailure('COMBINED_MEDIA_CHECK_FAILED');
            }
        }
        echo json_encode(['status' => 'PASS', 'mode' => 'NOOP', 'created' => 0, 'domain_fingerprint_unchanged' => true, 'source_unchanged' => true]).PHP_EOL;
        exit(0);
    }
    $merger = new LocalDatasetMerger;
    $merger->preflight($source);
    if ($argv[1] === '--dry-run') {
        $before = [];
        foreach (LocalDatasetMerger::TABLES as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        file_put_contents($directory.'/original-demo.json', json_encode($before, JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/plan.json', json_encode($binding, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        chmod($directory.'/original-demo.json', 0600);
        chmod($directory.'/plan.json', 0600);
        echo json_encode(['status' => 'DRY RUN PASS', 'source_counts' => array_map('count', $source), 'target_before' => $targetHash,
            'source_fingerprint' => $sourceHash, 'media_files' => count($files), 'settings_policy' => $binding['settings_policy']]).PHP_EOL;
        exit(0);
    }
    $plan = json_decode(file_get_contents($directory.'/plan.json'), true, 512, JSON_THROW_ON_ERROR);
    $proof = json_decode(file_get_contents($directory.'/backup.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($plan !== $binding || $proof['status'] !== 'BACKUP VERIFIED' || $proof['target_before'] !== $targetHash) {
        throw new MigrationFailure('COMBINED_MATCHING_DRY_RUN_AND_BACKUP_REQUIRED');
    }
    foreach ($proof['databases'] as $backup) {
        $file = base_path($backup['private_path']);
        if (! is_file($file) || hash_file('sha256', $file) !== $backup['sha256']) {
            throw new MigrationFailure('COMBINED_BACKUP_CHECKSUM_MISMATCH');
        }
    }
    $createdFiles = [];
    $committed = false;
    try {
        foreach ($files as $path => $hash) {
            if (! is_file($targetRoot.'/'.$path)) {
                if (! is_dir(dirname($targetRoot.'/'.$path))) {
                    mkdir(dirname($targetRoot.'/'.$path), 0755, true);
                }
                if (! copy($sourceRoot.'/'.$path, $targetRoot.'/'.$path)) {
                    throw new MigrationFailure('COMBINED_MEDIA_COPY_FAILED');
                }
                $createdFiles[] = $path;
            }
            if (hash_file('sha256', $targetRoot.'/'.$path) !== $hash) {
                throw new MigrationFailure('COMBINED_MEDIA_CHECK_FAILED');
            }
        }
        $result = $merger->apply($source, true);
        $committed = true;
        $original = json_decode(file_get_contents($directory.'/original-demo.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($original as $table => $rows) {
            foreach ($rows as $row) {
                $current = (array) DB::table($table)->where('id', $row['id'])->first();
                if ($current !== $row) {
                    throw new MigrationFailure('COMBINED_ORIGINAL_DEMO_CHANGED', ['table' => $table, 'id' => $row['id']]);
                }
            }
        }
        if (hash('sha256', json_encode($read(), JSON_THROW_ON_ERROR)) !== $sourceHash) {
            throw new MigrationFailure('COMBINED_SOURCE_CHANGED');
        }
        file_put_contents($directory.'/source-settings.json', json_encode($source['settings'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        chmod($directory.'/source-settings.json', 0600);
        $receipt = ['binding' => $binding, 'target_after' => app(TargetGuard::class)->fingerprint(), 'result' => $result,
            'source_unchanged' => true, 'original_demo_rows_unchanged' => true, 'copied_media_files' => count($createdFiles)];
        file_put_contents($directory.'/receipt.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        chmod($directory.'/receipt.json', 0600);
        echo json_encode(['status' => 'PASS', 'created' => $result['created'], 'copied_media_files' => count($createdFiles),
            'source_unchanged' => true, 'original_demo_rows_unchanged' => true, 'local_preview_publication' => true,
            'target_after' => $receipt['target_after'], 'settings_policy' => $binding['settings_policy']]).PHP_EOL;
    } catch (Throwable $error) {
        if (! $committed) {
            foreach ($createdFiles as $path) {
                unlink($targetRoot.'/'.$path);
            }
        }
        throw $error;
    }
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
