<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\Crypt;
use Modules\User\Models\User;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected $google2fa;

    public function __construct(Google2FA $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    public function generateSecret(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->twoFactorAuthentication()->updateOrCreate(
            ['user_id' => $user->id],
            ['google2fa_secret' => Crypt::encrypt($secret)]
        );

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return ['secret' => $secret, 'qr_code_url' => $qrCodeUrl];
    }

    public function enable(User $user, string $otp): bool
    {
        $secret = Crypt::decrypt($user->twoFactorAuthentication->google2fa_secret);

        if ($this->google2fa->verifyKey($secret, $otp)) {
            $user->twoFactorAuthentication->update(['google2fa_enabled' => true]);
            return true;
        }

        return false;
    }

    public function disable(User $user): void
    {
        $user->twoFactorAuthentication->update(['google2fa_enabled' => false]);
    }

    public function verify(User $user, string $otp): bool
    {
        $secret = Crypt::decrypt($user->twoFactorAuthentication->google2fa_secret);

        return $this->google2fa->verifyKey($secret, $otp);
    }
}
