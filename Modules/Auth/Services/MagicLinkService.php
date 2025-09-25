<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Auth\Mail\MagicLinkMail;
use Modules\Auth\Models\MagicLink;
use Modules\User\Models\User;

class MagicLinkService
{
    public function generate(User $user): MagicLink
    {
        $token = Str::random(64);

        return MagicLink::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function send(User $user, MagicLink $magicLink): void
    {
        Mail::to($user->email)->send(new MagicLinkMail($magicLink));
    }

    public function verify(string $token): ?User
    {
        $magicLink = MagicLink::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $magicLink) {
            return null;
        }

        $user = $magicLink->user;
        $magicLink->delete();

        return $user;
    }
}
