<?php

namespace App\Http\Middleware;

use App\Services\Storefront\Catalog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

final class StorefrontContext
{
    public function handle(Request $request, Closure $next)
    {
        $catalog = app(Catalog::class);
        $catalog->reset();
        View::share('store', $catalog->layout());
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
