<?php

namespace App\Legacy;

use App\Legacy\Reports\RunReport;
use App\Legacy\Transformers\ProductTransformer;
use FilesystemIterator;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final class Runner
{
    public const TABLES = ['categories', 'products', 'product_images', 'product_options', 'product_option_values', 'product_variants', 'product_variant_values', 'inventories', 'customers', 'customer_addresses', 'orders', 'order_items', 'order_status_histories', 'article_categories', 'articles', 'banners', 'settings'];

    public function __construct(private Source $source, private TargetGuard $guard) {}

    public function execute(array $options, bool $dryRun, bool $force): array
    {
        $proof = $this->guard->check();
        $this->source->assertReadOnly();
        $directory = realpath(config('legacy.report_directory'));
        $lock = fopen($directory.'/import.lock', 'c');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new MigrationFailure('IMPORT_ALREADY_RUNNING');
        }
        $runDirectory = $directory.'/'.($dryRun ? 'dry-' : 'write-').gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
        $review = new ReviewOverrides;
        $migrator = new Migrator($this->source, new ProductTransformer, new Media($review), new RunReport, $review);
        try {
            $sourceHash = $this->source->fingerprint();
            $review->assertSource($sourceHash);
            $targetBefore = $this->guard->fingerprint();
            $code = [];
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() === 'php') {
                    $code[$file->getPathname()] = hash_file('sha256', $file->getPathname());
                }
            }
            ksort($code);
            $binding = ['source' => $sourceHash, 'target' => $targetBefore, 'options' => $options, 'code' => hash('sha256', json_encode($code)),
                'backup' => $proof['target_backup']['sha256'], 'database' => config('legacy.target_database'), 'storage' => Storage::disk('public')->path(''), 'review' => $review->fingerprint()];
            $digest = hash('sha256', json_encode($binding));
            $receiptPath = $directory.'/receipt-'.$digest.'.json';
            if (! $dryRun && (! $force || ! is_file($receiptPath) || json_decode(file_get_contents($receiptPath), true) !== $binding)) {
                throw new MigrationFailure('MATCHING_DRY_RUN_REQUIRED');
            }
            $filesBefore = $dryRun ? $this->storageFingerprint() : null;
            $migrator->run($options, ! $dryRun);
            if ($this->source->fingerprint() !== $sourceHash) {
                throw new MigrationFailure('SOURCE_CHANGED');
            }
            $targetAfter = $this->guard->fingerprint();
            if ($dryRun && ($targetBefore !== $targetAfter || $filesBefore !== $this->storageFingerprint())) {
                throw new MigrationFailure('DRY_RUN_MUTATED_TARGET');
            }
            $metadata = ['mode' => $dryRun ? 'DRY_RUN' : 'WRITE', 'status' => 'COMPLETED_WITH_QUARANTINE', 'source_fingerprint' => $sourceHash, 'target_before' => $targetBefore,
                'target_after' => $targetAfter, 'receipt_digest' => $digest, 'target_database' => config('legacy.target_database'), 'options' => $options,
                'source_read_only' => true, 'source_unchanged' => true, 'dry_run_database_and_storage_unchanged' => $dryRun ? true : null, 'report_directory' => $runDirectory];
            $summary = $migrator->report->save($runDirectory, $metadata);
            file_put_contents($runDirectory.'/media-map.json', json_encode(array_values($migrator->media->mapping), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            chmod($runDirectory.'/media-map.json', 0600);
            if ($dryRun) {
                file_put_contents($receiptPath, json_encode($binding));
                chmod($receiptPath, 0600);
            }

            return $summary;
        } catch (Throwable $e) {
            $failure = $e instanceof MigrationFailure ? $e : new MigrationFailure('MIGRATION_INFRASTRUCTURE_FAILED', ['exception_type' => get_class($e)]);
            $migrator->report->save($runDirectory, ['mode' => $dryRun ? 'DRY_RUN' : 'WRITE', 'status' => 'BLOCKED', 'error_code' => $failure->reason, 'context' => $failure->context]);
            throw $failure;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function storageFingerprint(): string
    {
        $hashes = [];
        foreach (Storage::disk('public')->allFiles() as $file) {
            $hashes[$file] = hash_file('sha256', Storage::disk('public')->path($file));
        }
        ksort($hashes);

        return hash('sha256', json_encode($hashes));
    }
}
