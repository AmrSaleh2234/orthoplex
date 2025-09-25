<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\Auth;
use Modules\User\Models\User;

class LoginService
{
    public function login(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            return ['status' => 'error', 'message' => 'Unauthorized'];
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasVerifiedEmail()) {
            Auth::logout(); // Log the user out
            return ['status' => 'error', 'message' => 'Email not verified.'];
        }

        if ($user->twoFactorAuthentication && $user->twoFactorAuthentication->google2fa_enabled) {
            Auth::logout(); // Log out to prevent session fixation
            return ['status' => '2fa_required', 'user_id' => $user->id];
        }

        $token = Auth::login($user);
        return ['status' => 'success', 'token' => $token];
    }
}
