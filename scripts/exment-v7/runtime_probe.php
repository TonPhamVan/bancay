<?php

use App\Legacy\Source;
use App\Legacy\TargetGuard;
use Composer\InstalledVersions;
use ExmentAdminCore\Admin\Form;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
app(TargetGuard::class)->check();
if (DB::connection()->getDatabaseName() !== 'bancay_phase4_staging') {
    throw new RuntimeException('Owned migrated local staging only');
}
app(Source::class)->assertReadOnly();
echo json_encode(['php' => PHP_VERSION, 'laravel' => app()->version(),
    'exment' => InstalledVersions::getPrettyVersion('exceedone/exment'),
    'admin_core' => InstalledVersions::getPrettyVersion('exceedone/exment-admin-core'),
    'new_admin_class_available' => class_exists(Form::class),
    'old_admin_class_available' => class_exists('Encore\\Admin\\Form'),
    'database' => DB::connection()->getDatabaseName(), 'debug' => config('app.debug'), 'mail' => config('mail.default'),
    'source_fingerprint' => app(Source::class)->fingerprint(), 'domain_fingerprint' => app(TargetGuard::class)->fingerprint()], JSON_THROW_ON_ERROR).PHP_EOL;
