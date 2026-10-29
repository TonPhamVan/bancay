<?php

use App\Legacy\Transformers\Values;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bancay') {
    throw new RuntimeException('Read-only combined local comparison only');
}
$source = json_decode(file_get_contents(base_path('legacy-private/phase0/records.private.json')), true, 512, JSON_THROW_ON_ERROR);
$result = ['records' => [], 'mismatches' => [], 'policy' => 'Full title/slug/meta comparison; categories/articles without original meta use documented fallbacks. Image conversion/aliases checked by full staging reconciliation and unchanged copied media hashes.'];
foreach (['tblsanpham' => ['products', 'title', 'name'], 'tblbaiviet' => ['articles', 'title', 'title'], 'tbldanhmuc' => ['categories', 'name', 'name'], 'tblchuyenmuc' => ['article_categories', 'name', 'name']] as $table => [$target, $field, $targetField]) {
    foreach ($source[$table] as $row) {
        $actual = DB::table($target)->where('legacy_id', $row['id'])->first();
        $checks = ['title' => $actual && $row[$field] === $actual->$targetField, 'slug' => $actual && $actual->slug === Values::slug($row, $row[$field])];
        if ($table === 'tblsanpham') {
            $checks['meta_title'] = $actual && $actual->meta_title === ($row['meta_title'] ?: $row[$field]);
            $checks['meta_description'] = $actual && $actual->meta_description === ($row['meta_des'] ?: $row[$field]);
        } elseif ($table === 'tblchuyenmuc') {
            $checks['meta_title'] = $actual && $actual->meta_title === $row['meta_title'];
            $checks['meta_description'] = $actual && $actual->meta_description === $row['meta_des'];
        }
        $result['records'][] = ['entity' => $target, 'legacy_id' => (int) $row['id'], 'title_sha256' => hash('sha256', $row[$field]), 'checks' => $checks, 'status' => ! in_array(false, $checks, true) ? 'PASS' : 'FAIL'];
        foreach ($checks as $name => $ok) {
            if (! $ok) {
                $result['mismatches'][] = ['entity' => $target, 'legacy_id' => (int) $row['id'], 'field' => $name];
            }
        }
    }
}
$result['status'] = $result['mismatches'] ? 'FAIL' : 'PASS';
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
