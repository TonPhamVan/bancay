<?php

use App\Services\Release\OrderDelta;

// Offline: no Laravel bootstrap, environment credentials or database connection required.
require __DIR__.'/../../vendor/autoload.php';
try {
    if ($argc !== 6) {
        throw new RuntimeException('Arguments: baseline.json baseline_sha256 window.json window_sha256 output.json');
    }
    $private = realpath(__DIR__.'/../../legacy-private/release-readiness');
    $read = function (string $file, string $sha) use ($private) {
        $path = realpath($file);
        if (! $private || ! $path || ! str_starts_with($path, $private.'/') || ! is_file($path)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $sha) || ! hash_equals($sha, hash_file('sha256', $path))) {
            throw new RuntimeException('Private input containment or checksum failed.');
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    };
    $before = $read($argv[1], $argv[2]);
    $after = $read($argv[3], $argv[4]);
    $parent = realpath(dirname($argv[5]));
    if (! $parent || ! str_starts_with($parent.'/', $private.'/') || ! preg_match('/\A[a-zA-Z0-9_.-]+\.json\z/', basename($argv[5]))) {
        throw new RuntimeException('Private output directory required.');
    }
    $delta = (new OrderDelta)->compare($before, $after);
    $bytes = json_encode($delta, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    umask(0077);
    $file = @fopen($argv[5], 'xb');
    if (! $file) {
        throw new RuntimeException('Output exists or is not writable; never overwrite a recovery ledger.');
    }
    try {
        if (fwrite($file, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Incomplete recovery ledger write.');
        }
    } finally {
        fclose($file);
    }
    echo json_encode(['status' => 'PASS', 'orders' => count($delta['orders']), 'sha256' => hash('sha256', $bytes), 'automatic_writes' => false]).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'DELTA EXPORT BLOCKED: '.$error->getMessage().PHP_EOL);
    exit(1);
}
