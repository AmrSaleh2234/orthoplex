<?php

namespace Modules\Analytics\app\Listeners;

use Illuminate\Auth\Events\Login;
use Modules\Analytics\app\Jobs\LogLoginEventJob;

class LoginEventListener
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $user->last_login_at = now();
        $user->login_count++;
        $user->save();

        LogLoginEventJob::dispatch(
            $user->id,
            request()->ip(),
            request()->userAgent()
        );
    }
}
