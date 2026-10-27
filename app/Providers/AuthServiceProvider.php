<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
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
        // Phase 1 fails closed: only Exment system administrators may mutate domains.
        // Granular Exment role/plugin permissions will be wired to these abilities in Phase 2.
        foreach (['catalog.manage', 'commerce.manage', 'customers.manage', 'content.manage', 'settings.manage'] as $ability) {
            Gate::define($ability, function (Authenticatable $user): bool {
                return $user instanceof LoginUser && $user->isAdministrator();
            });
        }
    }
}
