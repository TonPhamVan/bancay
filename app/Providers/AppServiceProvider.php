<?php

namespace App\Providers;

use App\Exceptions\Handler;
use App\Http\Middleware\AdminOperationLog;
use App\Http\Middleware\StoreAdminGuard;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $this->app->singleton(ExceptionHandler::class, Handler::class);
            $router = $this->app['router'];
            $router->aliasMiddleware('admin.log', AdminOperationLog::class);
            $group = $router->getMiddlewareGroups()['admin'] ?? [];
            $index = array_search('admin.permission', $group, true);
            if ($index !== false) {
                array_splice($group, $index, 0, [StoreAdminGuard::class]);
                $router->middlewareGroup('admin', $group);
            }
        });
    }
}
