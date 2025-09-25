<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Services\MagicLinkService;
use Modules\User\Services\UserService;

class MagicLinkController extends Controller
{
    use ApiResponse;

    protected $magicLinkService;
    protected $userService;

    public function __construct(MagicLinkService $magicLinkService, UserService $userService)
    {
        $this->magicLinkService = $magicLinkService;
        $this->userService = $userService;
    }

    public function sendMagicLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = $this->userService->findByEmail($request->email);
        if (!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        $magicLink = $this->magicLinkService->generate($user);
        $this->magicLinkService->send($user, $magicLink);

        return $this->successResponse(null, 'Magic link sent.');
    }

    public function loginWithMagicLink(string $token): JsonResponse
    {
        $user = $this->magicLinkService->verify($token);

        if (! $user) {
            return $this->errorResponse('Invalid or expired magic link.', 401);
        }

        $jwt = auth()->login($user);

        $data = [
            'access_token' => $jwt,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ];

        return $this->successResponse($data, 'Login successful.');
    }
}
