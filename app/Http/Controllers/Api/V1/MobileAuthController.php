<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MobileAuthController
{
    public function login(Request $request): JsonResponse
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

        $name = 'android:'.$data['device_id'];
        $user->tokens()->where('name', $name)->delete();
        $token = $user->createToken($name, ['mobile'], now()->addDays((int) config('casa.mobile.token_ttl_days', 30)));

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->timezone(config('app.timezone'))->toIso8601String(),
                'user' => $this->user($user),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->user($request->user())])->header('Cache-Control', 'no-store');
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

    private function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'workspace' => match ($user->role) {
                User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN => 'admin',
                User::ROLE_RECEPTIONIST => 'reception',
                User::ROLE_STAFF => 'staff',
                default => 'customer',
            },
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => compact('code', 'message')], $status)
            ->header('Cache-Control', 'no-store');
    }
}
