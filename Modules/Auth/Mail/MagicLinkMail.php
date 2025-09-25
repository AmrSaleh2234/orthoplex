<?php

namespace Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\Models\MagicLink;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public $magicLink;

    public function __construct(MagicLink $magicLink)
    {
        $this->magicLink = $magicLink;
    }

    public function build()
    {
        return $this->subject('Your Magic Login Link')
            ->markdown('auth::emails.magic-link');
    }
}
