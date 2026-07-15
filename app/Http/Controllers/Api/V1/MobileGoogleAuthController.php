<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\GoogleIdentity;
use App\Services\MobileGoogleOAuth;
use App\Services\MobileTokenIssuer;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class MobileGoogleAuthController
{
    public function redirect(Request $request, MobileGoogleOAuth $oauth): RedirectResponse|JsonResponse
    {
        if (blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))) {
            return $this->error('GOOGLE_NOT_CONFIGURED', 'Google sign-in is not configured on this server.', 503);
        }

        $validator = Validator::make($request->query(), [
            'instance_id' => ['required', 'uuid'],
            'device_id' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:80'],
            'state' => ['required', 'regex:/^[A-Za-z0-9_-]{43}$/'],
            'code_challenge' => ['required', 'regex:/^[A-Za-z0-9_-]{43}$/'],
        ]);

        if ($validator->fails()) {
            return $this->error('GOOGLE_REQUEST_INVALID', 'The Google sign-in request is invalid.', 422);
        }

        try {
            $data = $validator->validated();
            $oauth->begin($data, $request->getHost());
        } catch (RuntimeException $exception) {
            return $this->error('GOOGLE_CONTEXT_INVALID', $exception->getMessage(), 409);
        }

        return Socialite::driver('google')
            ->stateless()
            ->redirectUrl(route('auth.google.mobile.callback'))
            ->with(['state' => $data['state'], 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request, MobileGoogleOAuth $oauth, GoogleIdentity $identities): RedirectResponse
    {
        $state = (string) $request->query('state', '');

        try {
            $authorization = $oauth->consumeAuthorization($state, $request->getHost());

            if ($request->filled('error')) {
                return redirect()->away($oauth->callbackUrl(['state' => $state, 'error' => 'google_cancelled']));
            }

            $googleUser = Socialite::driver('google')
                ->stateless()
                ->redirectUrl(route('auth.google.mobile.callback'))
                ->user();
            $user = $identities->resolve($googleUser);
            $code = $oauth->issueExchange($user, $authorization);

            return redirect()->away($oauth->callbackUrl(['state' => $state, 'code' => $code]));
        } catch (DomainException $exception) {
            report($exception);

            return redirect()->away($oauth->callbackUrl(['state' => $state, 'error' => 'account_ineligible']));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($oauth->callbackUrl(['state' => $state, 'error' => 'oauth_failed']));
        }
    }

    public function exchange(
        Request $request,
        MobileGoogleOAuth $oauth,
        MobileTokenIssuer $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'instance_id' => ['required', 'uuid'],
            'device_id' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'regex:/^[A-Za-z0-9_-]{43}$/'],
            'code_verifier' => ['required', 'regex:/^[A-Za-z0-9._~-]{43,128}$/'],
        ]);

        try {
            $user = $oauth->consumeExchange($data, $request->getHost());
        } catch (RuntimeException $exception) {
            return $this->error('GOOGLE_EXCHANGE_INVALID', $exception->getMessage(), 422);
        }

        return response()->json(['data' => $tokens->issue($user, $data['device_id'])])
            ->header('Cache-Control', 'no-store');
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => compact('code', 'message')], $status)
            ->header('Cache-Control', 'no-store');
    }
}
