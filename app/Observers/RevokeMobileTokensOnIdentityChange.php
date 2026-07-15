<?php

namespace App\Observers;

use App\Models\User;

class RevokeMobileTokensOnIdentityChange
{
    public function updated(User $user): void
    {
        if ($user->wasChanged(['password', 'email', 'google_id', 'role', 'is_active', 'email_verified_at'])) {
            $user->tokens()->delete();
        }
    }
}
