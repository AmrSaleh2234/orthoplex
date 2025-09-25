<?php

namespace Modules\Auth\Repositories;

use Illuminate\Support\Facades\Hash;
use Modules\User\Models\User;

class UserRepository
{
    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }
}
