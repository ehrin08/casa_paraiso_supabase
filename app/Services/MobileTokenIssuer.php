<?php

namespace App\Services;

use App\Models\User;

class MobileTokenIssuer
{
    public function issue(User $user, string $deviceId): array
    {
        $name = 'android:'.$deviceId;
        $user->tokens()->where('name', $name)->delete();
        $token = $user->createToken($name, ['mobile'], now()->addDays((int) config('casa.mobile.token_ttl_days', 30)));

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->timezone(config('app.timezone'))->toIso8601String(),
            'user' => $this->user($user),
        ];
    }

    public function user(User $user): array
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
}
