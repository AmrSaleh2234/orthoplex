<?php

namespace Modules\User\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Modules\User\app\Mail\GdprExportMail;
use Modules\User\Models\User;

class GdprExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        // Gather user data. We can expand this to include more related data in the future.
        $data = [
            'user' => $this->user->toArray(),
            'roles' => $this->user->getRoleNames(),
            // Add more data here, e.g., login history, etc.
        ];

        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);

        Mail::to($this->user->email)->send(new GdprExportMail($jsonContent));
    }
}
