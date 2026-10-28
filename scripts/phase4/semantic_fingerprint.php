<?php

use App\Legacy\Runner;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = DB::connection()->getDatabaseName();
if (! app()->environment('local') || ! in_array($database, ['bancay_phase4_staging', 'bancay_phase4_repeat'], true)) {
    throw new RuntimeException('Owned Phase4 target only');
}
$result = [];
foreach (Runner::TABLES as $table) {
    $hash = hash_init('sha256');
    $count = 0;
    foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
        $data = (array) $row;
        unset($data['updated_at']);
        if (! in_array($table, ['products', 'articles', 'orders'], true)) {
            unset($data['created_at']);
        }
        hash_update($hash, json_encode($data, JSON_THROW_ON_ERROR));
        $count++;
    }
    $result[$table] = ['count' => $count, 'semantic_sha256' => hash_final($hash)];
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
