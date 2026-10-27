<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class InstallLocalFoundation extends Command
{
    protected $signature = 'store:install-local';

    protected $description = 'Install a new local Exment foundation; preserve initialized platform data on rerun';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This foundation installer is local/testing only.');

            return self::FAILURE;
        }
        if (Schema::hasTable('custom_tables') && DB::table('custom_tables')->exists()) {
            return $this->call('migrate', ['--no-interaction' => true]);
        }

        return $this->call('exment:install', ['--no-interaction' => true]);
    }
}
