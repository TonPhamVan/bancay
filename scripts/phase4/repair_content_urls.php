<?php

use App\Legacy\ContentMediaUrls;
use App\Legacy\MigrationFailure;
use App\Legacy\Source;
use App\Legacy\TargetGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$guard = app(TargetGuard::class);
$guard->check();
if (DB::connection()->getDatabaseName() !== 'bancay_phase4_staging' || ! in_array($argv[1] ?? '', ['--dry-run', '--apply'], true)) {
    throw new MigrationFailure('UNSAFE_CONTENT_REPAIR');
}
app(Source::class)->assertReadOnly();
$directory = base_path('legacy-private/phase4/content-risk-closure');
if (! is_dir($directory)) {
    mkdir($directory, 0700);
}
$lock = fopen(config('legacy.report_directory').'/import.lock', 'c');
if (! flock($lock, LOCK_EX | LOCK_NB)) {
    throw new MigrationFailure('IMPORT_ALREADY_RUNNING');
}
try {
    $urls = new ContentMediaUrls;
    $binding = ['database' => DB::connection()->getDatabaseName(), 'source' => app(Source::class)->fingerprint(),
        'target' => $guard->fingerprint(), 'code' => hash_file('sha256', app_path('Legacy/ContentMediaUrls.php')), 'changes' => $urls->plan()];
    if ($argv[1] === '--dry-run') {
        file_put_contents($directory.'/plan.json', json_encode($binding, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        chmod($directory.'/plan.json', 0600);
        echo json_encode(['status' => 'DRY RUN PASS', 'changed_fields' => count($binding['changes']), 'target_before' => $binding['target']]).PHP_EOL;
        exit(0);
    }
    $plan = json_decode(file_get_contents($directory.'/plan.json'), true, 512, JSON_THROW_ON_ERROR);
    $proof = json_decode(file_get_contents($directory.'/backup.json'), true, 512, JSON_THROW_ON_ERROR);
    $backup = base_path($proof['private_relative_path'] ?? '');
    if ($plan !== $binding || ($proof['status'] ?? '') !== 'BACKUP VERIFIED'
        || ($proof['target_database'] ?? '') !== $binding['database'] || ($proof['target_fingerprint'] ?? '') !== $binding['target']
        || ! is_file($backup) || hash_file('sha256', $backup) !== ($proof['sha256'] ?? '')) {
        throw new MigrationFailure('STALE_CONTENT_PLAN_OR_BACKUP');
    }
    $changed = $urls->apply($plan['changes']);
    if ($urls->plan() !== [] || app(Source::class)->fingerprint() !== $binding['source']) {
        throw new MigrationFailure('CONTENT_REPAIR_VERIFICATION_FAILED');
    }
    $result = ['status' => 'PASS', 'changed_fields' => $changed, 'target_before' => $binding['target'], 'target_after' => $guard->fingerprint(),
        'source_unchanged' => true, 'remaining_generated_absolute_urls' => 0, 'publication_changed' => false, 'historical_orders_changed' => false];
    file_put_contents($directory.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result).PHP_EOL;
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
