<?php

namespace App\Exceptions;

use Exceedone\Exment\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Exceptions\Handler as LaravelHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        // Exment v6 renders some web errors with HTTP 200. Native store adapters need
        // Laravel's real 403/404/405/419/422 responses and validation error bags.
        if ($request->is(trim(config('admin.route.prefix'), '/').'/plugins/bancay_*',
            trim(config('admin.route.prefix'), '/').'/plugins/bancay_*/*')
            || $exception instanceof AuthorizationException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403)) {
            return (new LaravelHandler(app()))->render($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
