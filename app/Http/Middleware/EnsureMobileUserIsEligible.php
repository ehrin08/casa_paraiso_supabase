<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileUserIsEligible
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            $request->user()?->currentAccessToken()?->delete();

            return $this->error('ACCOUNT_INACTIVE', 'This account is inactive. Please contact an administrator.');
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->error('EMAIL_UNVERIFIED', 'Verify your email address before using the mobile app.');
        }

        return $next($request);
    }

    private function error(string $code, string $message): JsonResponse
    {
        return response()->json(['error' => compact('code', 'message')], 403)
            ->header('Cache-Control', 'no-store');
    }
}
