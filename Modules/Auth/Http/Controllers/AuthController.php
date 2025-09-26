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
use Modules\User\Models\CentralUser;
use Modules\User\Models\User;
use Modules\Auth\Models\MagicLinkToken;
use Stancl\Tenancy\Facades\Tenancy;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request, RegisterService $registerService): JsonResponse
    {
        $result = $registerService->register($request->validated());

        if ($result['status'] === 'error') {
            return $this->errorResponse($result['message'], 400);
        }

        return $this->successResponse($result, 'User registered successfully. Please check your email to verify your account.', 201);
    }

    /**
     * Get a JWT via given credentials.
     */
    public function login(LoginRequest $request, LoginService $loginService): JsonResponse
    {
        $result = $loginService->login($request->validated(), $request);

        if ($result['status'] === 'error') {
            $statusCode = match($result['message']) {
                'Email not verified' => 403,
                'Account suspended', 'Account not active' => 403,
                default => 401
            };
            return $this->errorResponse($result['message'], $statusCode);
        }

        if ($result['status'] === '2fa_required') {
            return $this->successResponse([
                'user_id' => $result['user_id'],
                'email' => $result['email'],
                'requires_2fa' => true
            ], '2FA verification required');
        }

        return $this->respondWithToken($result['access_token'], $result['refresh_token']);
    }

    /**
     * Verify two factor authentication.
     */
    public function verifyTwoFactor(VerifyTwoFactorRequest $request, LoginService $loginService): JsonResponse
    {
        $result = $loginService->verifyTwoFactor(
            $request->user_id,
            $request->otp,
            $request
        );

        if ($result['status'] === 'error') {
            return $this->errorResponse($result['message'], 401);
        }

        return $this->respondWithToken($result['access_token'], $result['refresh_token']);
    }

    /**
     * Mark the authenticated user's email address as verified using magic link.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $token = $request->query('token');
        
        if (!$token) {
            return $this->errorResponse('Verification token is required.', 400);
        }

        $magicLink = MagicLinkToken::where('token', $token)
            ->where('type', 'email_verification')
            ->where('expires_at', '>', now())
            ->where('used_at', null)
            ->first();

        if (!$magicLink) {
            return $this->errorResponse('Invalid or expired verification token.', 400);
        }

        $centralUser = CentralUser::where('email', $magicLink->email)->first();
        
        if (!$centralUser) {
            return $this->errorResponse('User not found.', 404);
        }

        // Mark email as verified
        $centralUser->markEmailAsVerified();
        
        // Mark magic link as used
        $magicLink->markAsUsed();

        return $this->successResponse(null, 'Email verified successfully.');
    }

    /**
     * Resend the email verification notification.
     */
    public function resendVerification(Request $request, RegisterService $registerService): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        
        $centralUser = CentralUser::where('email', $request->email)->first();
        
        if (!$centralUser) {
            return $this->errorResponse('User not found.', 404);
        }

        if ($centralUser->hasVerifiedEmail()) {
            return $this->errorResponse('Email already verified.', 400);
        }

        $result = $registerService->resendEmailVerification($request->email);
        
        if ($result['status'] === 'error') {
            return $this->errorResponse($result['message'], 400);
        }

        return $this->successResponse(null, 'Verification link sent.');
    }

    /**
     * Login with magic link (passwordless authentication).
     */
    public function magicLinkLogin(Request $request, LoginService $loginService): JsonResponse
    {
        $request->validate(['token' => 'required|string']);
        
        $result = $loginService->loginWithMagicLink($request->token, $request);
        
        if ($result['status'] === 'error') {
            return $this->errorResponse($result['message'], 401);
        }

        return $this->respondWithToken($result['access_token'], $result['refresh_token']);
    }

    /**
     * Request a magic link for passwordless login.
     */
    public function requestMagicLink(Request $request, LoginService $loginService): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        
        $result = $loginService->sendMagicLink($request->email);
        
        if ($result['status'] === 'error') {
            return $this->errorResponse($result['message'], 400);
        }

        return $this->successResponse(null, 'Magic link sent to your email.');
    }

    /**
     * Refresh access token using refresh token.
     */
    public function refreshToken(Request $request, LoginService $loginService): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);
        
        $result = $loginService->refreshToken($request->refresh_token);
        
        if ($result['status'] === 'error') {
            return $this->errorResponse($result['message'], 401);
        }

        return $this->respondWithToken($result['access_token'], $result['refresh_token']);
    }

    /**
     * Logout the user and invalidate tokens.
     */
    public function logout(Request $request, LoginService $loginService): JsonResponse
    {
        $result = $loginService->logout($request);
        
        if ($result['status'] === 'error') {
            return $this->errorResponse($result['message'], 400);
        }

        return $this->successResponse(null, 'Successfully logged out.');
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        return $this->successResponse([
            'id' => $user->id,
            'global_id' => $user->global_id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'two_factor_enabled' => $user->two_factor_enabled,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ], 'User profile retrieved successfully.');
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(string $accessToken, ?string $refreshToken = null): JsonResponse
    {
        $data = [
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ];
        
        if ($refreshToken) {
            $data['refresh_token'] = $refreshToken;
        }

        return $this->successResponse($data, 'Authentication successful.');
    }
}
