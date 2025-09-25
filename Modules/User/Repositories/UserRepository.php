<?php

namespace Modules\User\Repositories;

use Modules\User\Models\User;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
