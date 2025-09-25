<?php

namespace Modules\User\app\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GdprExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $jsonContent;

    public function __construct(string $jsonContent)
    {
        $this->jsonContent = $jsonContent;
    }

    public function build()
    {
        return $this->subject('Your Data Export')
            ->markdown('user::emails.gdpr-export')
            ->attachData($this->jsonContent, 'export.json', [
                'mime' => 'application/json',
            ]);
    }
}
