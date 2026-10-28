<?php

namespace App\Console\Commands;

use App\Legacy\MigrationFailure;
use App\Legacy\Migrator;
use App\Legacy\Runner;
use Illuminate\Console\Command;

final class MigrateLegacy extends Command
{
    protected $signature = 'legacy:migrate {--dry-run : Validate and report without database or public-storage writes} {--only= : Comma-separated entity groups} {--limit=0} {--from-id=0} {--force : Acknowledge isolated write, never bypass receipt/guards} {--include-orders : Explicitly allow immutable historical-order rehearsal}';

    protected $description = 'Migrate a verified read-only legacy snapshot into an explicitly isolated local staging database';

    public function handle(): int
    {
        try {
            foreach (['limit', 'from-id'] as $key) {
                if (! preg_match('/\A[0-9]{1,9}\z/', (string) $this->option($key))) {
                    throw new MigrationFailure('INVALID_RANGE');
                }
            }
            $only = $this->option('only') ? explode(',', $this->option('only')) : array_values(array_diff(Migrator::GROUPS, ['orders']));
            if ($this->option('include-orders')) {
                $only[] = 'orders';
            }
            if (array_diff($only, Migrator::GROUPS) || (in_array('orders', $only, true) && ! $this->option('include-orders'))) {
                throw new MigrationFailure('INVALID_OR_UNAPPROVED_SCOPE');
            }
            if (array_intersect(['products', 'banners'], $only)) {
                $only[] = 'categories';
            }
            if (in_array('articles', $only, true)) {
                $only[] = 'article_categories';
            }
            $only = array_values(array_intersect(Migrator::GROUPS, array_unique($only)));
            $result = app(Runner::class)->execute(['only' => $only, 'limit' => (int) $this->option('limit'), 'from_id' => (int) $this->option('from-id')], (bool) $this->option('dry-run'), (bool) $this->option('force'));
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (MigrationFailure $e) {
            $this->error('MIGRATION BLOCKED: '.$e->reason.' '.json_encode($e->context));

            return self::FAILURE;
        }
    }
}
