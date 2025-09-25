<?php

namespace Modules\RolesAndPermissions\app\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\RolesAndPermissions\app\Mail\InvitationMail;
use Modules\RolesAndPermissions\app\Models\Invitation;
use Modules\RolesAndPermissions\app\Models\Role;
use Modules\User\Models\User;

class InvitationService
{
    public function createAndSendInvitation(string $email, Role $role): Invitation
    {
        $token = Str::random(64);

        $invitation = Invitation::create([
            'email' => $email,
            'token' => $token,
            'role_id' => $role->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new InvitationMail($invitation));

        return $invitation;
    }

    public function acceptInvitation(string $token, array $userData): ?User
    {
        $invitation = Invitation::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $invitation) {
            return null;
        }

        $user = User::create([
            'name' => $userData['name'],
            'email' => $invitation->email,
            'password' => bcrypt($userData['password']),
            'email_verified_at' => now(), // Consider the user verified upon accepting the invitation
        ]);

        $user->assignRole($invitation->role);

        $invitation->delete();

        return $user;
    }
}
