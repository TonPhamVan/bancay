<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$checks = [];
$check = function (string $id, bool $ok) use (&$checks) {
    $checks[] = ['id' => $id, 'status' => $ok ? 'PASS' : 'FAIL'];
};
$check('production_environment', app()->environment('production'));
$check('debug_disabled', ! config('app.debug'));
$check('https_origin', parse_url(config('app.url'), PHP_URL_SCHEME) === 'https' && (bool) parse_url(config('app.url'), PHP_URL_HOST));
$check('app_key_present', (bool) config('app.key'));
$check('secure_httponly_lax_session', config('session.secure') && config('session.http_only') && config('session.same_site') === 'lax');
$check('database_config_present', (bool) config('database.connections.mysql.database') && (bool) config('database.connections.mysql.username'));
$check('queue_supported_single_instance', config('queue.default') === 'sync');
$check('no_legacy_runtime_import', ! config('legacy.enabled'));
$check('daily_info_inventory_audit', config('logging.default') === 'daily' && config('logging.channels.daily.level') === 'info');
$check('private_storage_writable', is_writable(storage_path('logs')) && is_writable(storage_path('framework/sessions')));
$check('public_storage_link', is_link(public_path('storage')) && realpath(public_path('storage')) === realpath(storage_path('app/public')));
$check('php_supported', PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 80500 && PHP_INT_SIZE === 8);
$check('required_extensions', count(array_filter(['pdo_mysql', 'gd', 'mbstring', 'intl', 'zip', 'bcmath', 'dom'], 'extension_loaded')) === 7 && function_exists('imagewebp'));
$events = app(Schedule::class)->events();
$check('native_scheduler_discovered', count($events) >= 2);
$result = ['status' => in_array('FAIL', array_column($checks, 'status'), true) ? 'FAIL' : 'PASS',
    'scope' => 'Read-only runtime/configuration preflight; does not verify DirectAdmin, certificate, backups, release data or cron installation',
    'checks' => $checks, 'mail_transport' => config('mail.default'), 'smtp_delivery' => 'NOT TESTED',
    'scheduled_commands' => array_map(fn ($e) => ['command' => $e->command, 'expression' => $e->expression], $events)];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($result['status'] === 'PASS' ? 0 : 1);
