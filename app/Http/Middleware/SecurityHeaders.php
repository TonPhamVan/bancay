<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public static function apply(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // Tested subset compatible with Alpine expressions and native Exment's inline scripts.
        // Do not claim script-src protection until nonce/CSP-compatible adapters are tested.
        $response->headers->set('Content-Security-Policy', "object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
        if (! config('storefront.indexable')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request));
    }
}
