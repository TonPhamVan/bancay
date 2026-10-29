<?php

namespace App\Console\Commands;

use Exceedone\Exment\Database\View\WorkflowStartView;
use Exceedone\Exment\Database\View\WorkflowValueView;
use Exceedone\Exment\Enums\SystemTableName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Rebind only native derived views after a verified base-table restore, never domain data. */
final class RebuildDerivedViews extends Command
{
    protected $signature = 'store:rebuild-derived-views {--database-confirm=}';

    protected $description = 'Rebuild two native Exment workflow views against the confirmed restored database';

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();
        $localTarget = app()->environment(['local', 'testing'])
            && ($database === 'bancay_test' || preg_match('/\Abancay_release_(?:window|verify|candidate)_[0-9_]+\z/', $database));
        $productionRestore = app()->environment('production') && app()->isDownForMaintenance();
        if ($this->option('database-confirm') !== $database || (! $localTarget && ! $productionRestore)) {
            $this->error('VIEW REBUILD BLOCKED: confirmed owned restore DB or production maintenance required.');

            return self::FAILURE;
        }
        foreach (['migrations', 'login_users', 'custom_tables', 'workflows', 'workflow_values', 'orders', 'order_items'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error('VIEW REBUILD BLOCKED: restored native/domain schema incomplete.');

                return self::FAILURE;
            }
        }
        \ExmentDB::createView(SystemTableName::VIEW_WORKFLOW_VALUE_UNION, WorkflowValueView::createWorkflowValueUnionView());
        \ExmentDB::createView(SystemTableName::VIEW_WORKFLOW_START, WorkflowStartView::createWorkflowStartView());
        foreach ([SystemTableName::VIEW_WORKFLOW_VALUE_UNION, SystemTableName::VIEW_WORKFLOW_START] as $view) {
            DB::table($view)->count();
        }
        $this->info('Derived native views rebuilt and queryable; no business-table writes.');

        return self::SUCCESS;
    }
}
