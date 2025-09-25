<?php

namespace Modules\Auth\Services;

use Modules\Auth\Repositories\UserRepository;
use Modules\User\Models\User;

class RegisterService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(array $data): User
    {
        $user = $this->userRepository->create($data);
        $user->sendEmailVerificationNotification();

        return $user;
    }
}
