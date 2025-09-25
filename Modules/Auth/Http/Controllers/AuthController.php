<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Requests\VerifyTwoFactorRequest;
use Modules\Auth\Services\LoginService;
use Modules\Auth\Services\RegisterService;
use Modules\Auth\Services\TwoFactorService;
use Modules\User\Models\User;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request, RegisterService $registerService): JsonResponse
    {
        $user = $registerService->register($request->validated());

        return $this->successResponse($user, 'User registered successfully.', 201);
    }

    /**
     * Get a JWT via given credentials.
     */
    public function login(LoginRequest $request, LoginService $loginService): JsonResponse
    {
        $result = $loginService->login($request->validated());

        if ($result['status'] === 'error') {
            $statusCode = $result['message'] === 'Email not verified.' ? 403 : 401;
            return $this->errorResponse($result['message'], $statusCode);
        }

        if ($result['status'] === '2fa_required') {
            return $this->successResponse(['user_id' => $result['user_id']], '2FA required');
        }

        return $this->respondWithToken($result['token']);
    }

    /**
     * Verify two factor authentication.
     */
    public function verifyTwoFactor(VerifyTwoFactorRequest $request, TwoFactorService $twoFactorService): JsonResponse
    {
        $user = User::findOrFail($request->user_id);

        if ($twoFactorService->verify($user, $request->otp)) {
            $token = auth()->login($user);
            return $this->respondWithToken($token);
        }

        return $this->errorResponse('Invalid 2FA code.', 401);
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        $request->fulfill();

        return $this->successResponse(null, 'Email verified successfully.');
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->errorResponse('Email already verified.', 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->successResponse(null, 'Verification link sent.');
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        $data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ];

        return $this->successResponse($data, 'Login successful.');
    }
}
