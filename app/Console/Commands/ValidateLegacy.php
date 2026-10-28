<?php

namespace App\Console\Commands;

use App\Legacy\MigrationFailure;
use App\Legacy\Reconciler;
use Illuminate\Console\Command;

final class ValidateLegacy extends Command
{
    protected $signature = 'legacy:validate';

    protected $description = 'Reconcile a guarded isolated migration target against the read-only source snapshot';

    public function handle(): int
    {
        try {
            $result = app(Reconciler::class)->run();
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return $result['status'] === 'PASS' ? self::SUCCESS : self::FAILURE;
        } catch (MigrationFailure $e) {
            $this->error('VALIDATION BLOCKED: '.$e->reason);

            return self::FAILURE;
        }
    }
}
