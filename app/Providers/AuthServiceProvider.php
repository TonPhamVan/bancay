<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Admin\StoreResources;
use App\Services\Admin\StoreAccess;
use Exceedone\Exment\Model\LoginUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        foreach (StoreResources::ABILITIES as $module => $ability) {
            Gate::define($ability, function (Authenticatable $user) use ($module): bool {
                return $user instanceof LoginUser && app(StoreAccess::class)->allows($user, $module);
            });
        }
    }
}
