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

/**
 * @OA\Schema(
 *     schema="AuthToken",
 *     type="object",
 *     @OA\Property(property="access_token", type="string", description="JWT access token"),
 *     @OA\Property(property="refresh_token", type="string", description="JWT refresh token"),
 *     @OA\Property(property="token_type", type="string", example="bearer", description="Token type"),
 *     @OA\Property(property="expires_in", type="integer", example=3600, description="Token expiration time in seconds")
 * )
 *
 * @OA\Schema(
 *     schema="UserProfile",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="User ID"),
 *     @OA\Property(property="global_id", type="string", format="uuid", description="Global user identifier"),
 *     @OA\Property(property="name", type="string", description="User full name"),
 *     @OA\Property(property="email", type="string", format="email", description="User email address"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, description="Email verification timestamp"),
 *     @OA\Property(property="two_factor_enabled", type="boolean", description="Whether 2FA is enabled"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}, description="User status"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Account creation timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="TwoFactorRequired",
 *     type="object",
 *     @OA\Property(property="user_id", type="integer", description="User ID requiring 2FA"),
 *     @OA\Property(property="email", type="string", format="email", description="User email"),
 *     @OA\Property(property="requires_2fa", type="boolean", example=true, description="Indicates 2FA verification is required")
 * )
 */

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     operationId="registerUser",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     description="Register a new user account with email verification",
     *     @OA\RequestBody(
     *         required=true,
     *         description="User registration data",
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", description="User full name", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", description="User email address", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", description="User password (min 8 chars)", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", description="Password confirmation", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="user", ref="#/components/schemas/UserProfile"),
     *                         @OA\Property(property="verification_sent", type="boolean", example=true)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Registration error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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
     * @OA\Post(
     *     path="/api/auth/login",
     *     operationId="loginUser",
     *     tags={"Authentication"},
     *     summary="Authenticate user",
     *     description="Authenticate user with email and password, supports 2FA",
     *     @OA\RequestBody(
     *         required=true,
     *         description="User login credentials",
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", description="User email address", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", description="User password", example="password123"),
     *             @OA\Property(property="remember_me", type="boolean", description="Remember user session", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Authentication successful",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/AuthToken")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="2FA verification required",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/TwoFactorRequired")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Account not verified, suspended, or inactive",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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
     * @OA\Post(
     *     path="/api/auth/login/2fa",
     *     operationId="verifyTwoFactor",
     *     tags={"Authentication"},
     *     summary="Verify 2FA code",
     *     description="Complete login process by verifying 2FA code",
     *     @OA\RequestBody(
     *         required=true,
     *         description="2FA verification data",
     *         @OA\JsonContent(
     *             required={"user_id", "otp"},
     *             @OA\Property(property="user_id", type="integer", description="User ID from login response", example=1),
     *             @OA\Property(property="otp", type="string", description="6-digit 2FA code", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="2FA verification successful",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/AuthToken")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid 2FA code",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/auth/email/verify",
     *     operationId="verifyEmail",
     *     tags={"Authentication"},
     *     summary="Verify email address",
     *     description="Verify user's email address using magic link token",
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         required=true,
     *         description="Email verification token from magic link",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="null")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired verification token",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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
     * @OA\Post(
     *     path="/api/auth/email/resend",
     *     operationId="resendVerification",
     *     tags={"Authentication"},
     *     summary="Resend email verification",
     *     description="Resend email verification link to user",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email to resend verification to",
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", description="User email address", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification link sent",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="null")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Email already verified or resend failed",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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
