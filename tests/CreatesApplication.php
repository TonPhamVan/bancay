<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (! $app->environment('testing')
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'bancay_test'
            || DB::connection()->getDatabaseName() !== 'bancay_test') {
            throw new \LogicException('Tests may only migrate the dedicated bancay_test database.');
        }

        return $app;
    }
}
