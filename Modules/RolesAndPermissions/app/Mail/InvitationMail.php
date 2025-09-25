<?php

namespace Modules\RolesAndPermissions\app\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\RolesAndPermissions\app\Models\Invitation;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;

    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function build()
    {
        // Note: We will need to create a frontend URL for the user to accept the invitation.
        // For now, we will use a placeholder route.
        $acceptUrl = url('/accept-invitation?token=' . $this->invitation->token);

        return $this->subject('You have been invited to join ' . config('app.name'))
            ->markdown('rolesandpermissions::emails.invitation', ['acceptUrl' => $acceptUrl]);
    }
}
