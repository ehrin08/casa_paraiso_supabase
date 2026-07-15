<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\RevokeMobileTokensOnIdentityChange;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

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
        User::observe(RevokeMobileTokensOnIdentityChange::class);

        if (app()->environment('production') && config('casa.security.force_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('guest-sensitive', function (Request $request): array {
            $key = strtolower(trim((string) $request->input('email', 'anonymous'))).'|'.$request->ip();

            return [
                Limit::perMinute(10)->by('minute:'.$key),
                Limit::perHour(30)->by('hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('user-sensitive', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('mobile-meta', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('mobile-meta:'.$request->ip())
            ->response(fn (): Response => response()->json([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many metadata requests. Please try again shortly.',
                ],
            ], 429)->header('Cache-Control', 'no-store')));

        RateLimiter::for('mobile-login', function (Request $request): array {
            $email = strtolower(trim((string) $request->input('email', 'anonymous')));
            $key = 'mobile-login:'.$email.'|'.$request->ip();
            $response = fn (): Response => response()->json([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many sign-in attempts. Please try again shortly.',
                ],
            ], 429)->header('Cache-Control', 'no-store');

            return [
                Limit::perMinute(5)->by('minute:'.$key)->response($response),
                Limit::perHour(20)->by('hour:'.$request->ip())->response($response),
            ];
        });

        RateLimiter::for('mobile-google', function (Request $request): array {
            $key = 'mobile-google:'.$request->ip();
            $response = fn (): Response => response()->json([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many Google sign-in attempts. Please wait before trying again.',
                ],
            ], 429)->header('Cache-Control', 'no-store');

            return [
                Limit::perMinute(10)->by('minute:'.$key)->response($response),
                Limit::perHour(30)->by('hour:'.$key)->response($response),
            ];
        });

        Paginator::defaultView('pagination.compact');

        Vite::useScriptTagAttributes([
            'data-turbo-track' => 'reload',
        ]);

        Vite::useStyleTagAttributes([
            'data-turbo-track' => 'reload',
        ]);
    }
}
