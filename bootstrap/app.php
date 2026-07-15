<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureMobileUserIsEligible;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);

        $middleware->trustHosts(
            at: fn (): array => config('casa.security.trusted_hosts', []),
            subdomains: false,
        );

        $middleware->trustProxies(at: [
            '127.0.0.1',
            '172.16.0.0/12',
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'active_mobile' => EnsureMobileUserIsEligible::class,
            'role' => EnsureUserHasRole::class,
            'super_admin' => EnsureUserIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json(['error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'The submitted information is invalid.',
                'fields' => $exception->errors(),
            ]], 422)->header('Cache-Control', 'no-store');
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json(['error' => [
                'code' => 'UNAUTHENTICATED',
                'message' => 'Sign in to continue.',
            ]], 401)->header('Cache-Control', 'no-store');
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            [$code, $message] = match ($status) {
                403 => ['FORBIDDEN', 'You do not have permission to perform this action.'],
                404 => ['NOT_FOUND', 'The requested record was not found.'],
                429 => ['TOO_MANY_REQUESTS', 'Too many requests. Please wait and try again.'],
                default => ['REQUEST_FAILED', 'The request could not be completed.'],
            };

            return response()->json(['error' => compact('code', 'message')], $status)
                ->header('Cache-Control', 'no-store');
        });
    })->create();
