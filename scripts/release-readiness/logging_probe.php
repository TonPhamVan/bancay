<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== 'bancay_phase4_ui' || ! app()->environment('production') || config('app.debug')) {
    throw new RuntimeException('Production-mode isolated QA clone only.');
}
$marker = 'readiness-controlled-'.bin2hex(random_bytes(12));
$info = 'readiness-inventory-info-'.bin2hex(random_bytes(12));
Route::get('/__release_log_probe', function () use ($marker) {
    throw new RuntimeException($marker);
});
Log::info($info, ['scope' => 'Synthetic logging test, no customer or credential payload']);
$request = Request::create('https://bancay.vn/__release_log_probe');
$response = app(HttpKernel::class)->handle($request);
$logs = '';
foreach (glob(storage_path('logs/laravel-*.log')) as $file) {
    $logs .= file_get_contents($file);
}
$body = $response->getContent();
$checks = ['http500' => $response->getStatusCode() === 500, 'error_logged' => str_contains($logs, $marker),
    'inventory_info_level_logged' => str_contains($logs, $info), 'internal_details_hidden' => ! str_contains($body, $marker) && ! str_contains($body, '/var/www') && ! str_contains($body, 'SQLSTATE'),
    'no_password_in_response' => ! str_contains($body, (string) config('database.connections.mysql.password'))];
echo json_encode(['status' => in_array(false, $checks, true) ? 'FAIL' : 'PASS', 'checks' => $checks,
    'scope' => 'Ephemeral in-process route in isolated QA; no route added to application', 'response_body_sha256' => hash('sha256', $body)], JSON_PRETTY_PRINT).PHP_EOL;
