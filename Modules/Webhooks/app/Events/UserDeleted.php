<?php

namespace Modules\Webhooks\app\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDeleted
{
    use Dispatchable, SerializesModels;

    public $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }
}
