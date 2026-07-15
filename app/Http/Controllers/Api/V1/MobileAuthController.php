<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\MobileTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MobileAuthController
{
    public function login(Request $request, MobileTokenIssuer $tokens): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'device_id' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:80'],
        ]);

        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();

        if (! $user || blank($user->password) || ! Hash::check($data['password'], $user->password)) {
            return $this->error('INVALID_CREDENTIALS', 'The provided credentials are incorrect.', 401);
        }

        if (! $user->is_active) {
            return $this->error('ACCOUNT_INACTIVE', 'This account is inactive. Please contact an administrator.', 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->error('EMAIL_UNVERIFIED', 'Verify your email address before using the mobile app.', 403);
        }

        return response()->json(['data' => $tokens->issue($user, $data['device_id'])])
            ->header('Cache-Control', 'no-store');
    }

    public function me(Request $request, MobileTokenIssuer $tokens): JsonResponse
    {
        return response()->json(['data' => $tokens->user($request->user())])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([], 204)->header('Cache-Control', 'no-store');
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:1024'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            if (blank($user->password) || ! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => __('The current password is incorrect.'),
                ]);
            }

            $user->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ])->save();
        });

        return response()->json([
            'message' => 'Password updated. Sign in again on this phone.',
        ])->header('Cache-Control', 'no-store');
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => compact('code', 'message')], $status)
            ->header('Cache-Control', 'no-store');
    }
}
