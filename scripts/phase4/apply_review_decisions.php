<?php

use App\Legacy\MigrationFailure;
use App\Legacy\Reconciler;
use App\Legacy\ReviewedMediaRepair;
use App\Legacy\ReviewOverrides;
use App\Legacy\Runner;
use App\Legacy\Source;
use App\Legacy\TargetGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$guard = app(TargetGuard::class);
$guard->check();
if (DB::connection()->getDatabaseName() !== 'bancay_phase4_staging' || ! in_array($argv[1] ?? '', ['--dry-run', '--apply', '--verify-rerun'], true)) {
    throw new MigrationFailure('UNSAFE_REVIEW_TARGET');
}
$source = app(Source::class);
$source->assertReadOnly();
$review = new ReviewOverrides;
$review->assertSource($source->fingerprint());
$media = new ReviewedMediaRepair($source, $review);
$directory = base_path('legacy-private/phase4/review-application');
if (! is_dir($directory)) {
    mkdir($directory, 0700);
}
$options = ['only' => ['categories', 'products', 'article_categories', 'articles'], 'limit' => 0, 'from_id' => 0];
$runner = app(Runner::class);
$binding = ['database' => DB::connection()->getDatabaseName(), 'source' => $source->fingerprint(), 'target' => $guard->fingerprint(),
    'review' => $review->fingerprint(), 'code' => hash_file('sha256', __FILE__), 'media_plan' => $media->plan()];
if ($argv[1] === '--dry-run') {
    $dry = $runner->execute($options, true, false);
    if ($guard->fingerprint() !== $binding['target']) {
        throw new MigrationFailure('REVIEW_DRY_RUN_CHANGED_TARGET');
    }
    file_put_contents($directory.'/plan.json', json_encode($binding, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($directory.'/plan.json', 0600);
    echo json_encode(['status' => 'DRY RUN PASS', 'target_before' => $binding['target'], 'counts' => $dry['counts'], 'media_changes' => count($binding['media_plan'])]).PHP_EOL;
    exit(0);
}
if ($argv[1] === '--verify-rerun') {
    $dry = $runner->execute($options, true, false);
    foreach ($dry['counts'] as $count) {
        if ($count['CREATED'] !== 0 || $count['QUARANTINED'] !== 0 || $count['FAILED'] !== 0) {
            throw new MigrationFailure('REVIEW_RERUN_WOULD_CREATE_OR_FAIL');
        }
    }
    if ($media->plan() !== []) {
        throw new MigrationFailure('REVIEW_MEDIA_NOT_IDEMPOTENT');
    }
    $rerun = $runner->execute($options, false, true);
    if ($guard->fingerprint() !== $binding['target']) {
        throw new MigrationFailure('REVIEW_RERUN_CHANGED_TARGET');
    }
    echo json_encode(['status' => 'PASS', 'created' => 0, 'fingerprint_unchanged' => true, 'media_plan_empty' => true, 'counts' => $rerun['counts']]).PHP_EOL;
    exit(0);
}
$plan = json_decode(file_get_contents($directory.'/plan.json'), true, 512, JSON_THROW_ON_ERROR);
$proof = json_decode(file_get_contents($directory.'/backup.json'), true, 512, JSON_THROW_ON_ERROR);
$backup = base_path($proof['private_relative_path'] ?? '');
if ($plan !== $binding || ($proof['status'] ?? '') !== 'BACKUP VERIFIED' || ($proof['target_database'] ?? '') !== $binding['database']
    || ($proof['target_fingerprint'] ?? '') !== $binding['target'] || ! is_file($backup) || hash_file('sha256', $backup) !== ($proof['sha256'] ?? '')) {
    throw new MigrationFailure('STALE_REVIEW_PLAN_OR_BACKUP');
}
$write = $runner->execute($options, false, true);
foreach ($write['counts'] as $count) {
    if ($count['QUARANTINED'] !== 0 || $count['FAILED'] !== 0) {
        throw new MigrationFailure('REVIEW_IMPORT_NOT_COMPLETE');
    }
}
$changed = $media->apply($plan['media_plan']);
$reconciliation = app(Reconciler::class)->run();
if ($reconciliation['status'] !== 'PASS' || $media->plan() !== [] || $source->fingerprint() !== $binding['source']) {
    throw new MigrationFailure('REVIEW_RECONCILIATION_FAILED');
}
$result = ['status' => 'PASS', 'counts' => $write['counts'], 'media_repairs' => $changed, 'source_unchanged' => true,
    'target_before' => $binding['target'], 'target_after' => $guard->fingerprint(), 'review_sha256' => $review->fingerprint(), 'reconciliation' => $reconciliation];
file_put_contents($directory.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
chmod($directory.'/result.json', 0600);
echo json_encode($result).PHP_EOL;
