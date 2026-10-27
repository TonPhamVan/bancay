<?php

namespace App\Http\Middleware;

use App\Admin\StoreResources;
use Closure;
use Exceedone\Exment\Model\LoginUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Run after native authentication and before Exment's legacy permission responder. */
final class StoreAdminGuard
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('admin')->user();
        if (! $user instanceof LoginUser) {
            return $next($request);
        }
        // A removed login identity or soft-deleted native profile revokes an existing session too.
        abort_unless(LoginUser::find($user->id)?->base_user, 403);
        $prefix = trim(config('admin.route.prefix'), '/');
        foreach (StoreResources::ABILITIES as $module => $ability) {
            if ($request->is($prefix.'/plugins/bancay_'.$module, $prefix.'/plugins/bancay_'.$module.'/*')) {
                Gate::forUser($user)->authorize($ability);
            }
        }
        if ($request->is($prefix.'/data/user', $prefix.'/data/user/*', $prefix.'/loginuser', $prefix.'/loginuser/*',
            $prefix.'/role_group', $prefix.'/role_group/*')) {
            abort_unless($user->isAdministrator(), 403);
        }

        return $next($request);
    }
}
