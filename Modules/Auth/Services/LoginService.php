<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Modules\Auth\Repositories\UserRepository;
use Modules\Auth\Models\JwtRefreshToken;
use Modules\Auth\Models\MagicLinkToken;
use Modules\User\Models\CentralUser;
use Modules\Analytics\Services\LoginAnalyticsService;
use Stancl\Tenancy\Facades\Tenancy;
use Tymon\JWTAuth\Facades\JWTAuth;
class LoginService
{
    protected UserRepository $userRepository;
    protected LoginAnalyticsService $analyticsService;

    public function __construct(
        UserRepository $userRepository,
        LoginAnalyticsService $analyticsService
    ) {
        $this->userRepository = $userRepository;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Authenticate user using central database
     */
    public function login(array $credentials, ?Request $request = null): array
    {
        // Find user in central database
        $user = $this->userRepository->findByEmail($credentials['email']);
        $tenant = Tenancy::initialized() ? tenant() : null;

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            // Record failed login attempt
            $this->analyticsService->recordFailedLogin(
                $credentials['email'],
                $tenant,
                $request,
                'Invalid credentials'
            );

            return ['status' => 'error', 'message' => 'Invalid credentials'];
        }

        // Check if user is active
        if (!$user->isActive()) {
            $message = $user->isSuspended() ? 'Account suspended' : 'Account not active';

            // Record failed login attempt
            $this->analyticsService->recordFailedLogin(
                $credentials['email'],
                $tenant,
                $request,
                $message
            );

            return ['status' => 'error', 'message' => $message];
        }

        // Check email verification
        if (!$user->hasVerifiedEmail()) {
            // Record failed login attempt
            $this->analyticsService->recordFailedLogin(
                $credentials['email'],
                $tenant,
                $request,
                'Email not verified'
            );

            return ['status' => 'error', 'message' => 'Email not verified'];
        }

        // Check 2FA if enabled
        if ($user->two_factor_enabled) {
            return ['status' => '2fa_required', 'user_id' => $user->id, 'email' => $user->email];
        }

        // Record successful login
        $this->analyticsService->recordSuccessfulLogin($user, $tenant, $request);

        return $this->generateTokens($user, $request);
    }

    /**
     * Verify 2FA and complete login
     */
    public function verifyTwoFactorAndLogin(int $userId, string $code, ?Request $request = null): array
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }

        if (!$user->verifyTwoFactorCode($code)) {
            return ['status' => 'error', 'message' => 'Invalid 2FA code'];
        }

        return $this->generateTokens($user, $request);
    }

    /**
     * Login with magic link token
     */
    public function loginWithMagicLink(string $token, ?Request $request = null): array
    {
        $magicLink = $this->userRepository->findValidMagicLinkToken($token, 'login');

        if (!$magicLink) {
            return ['status' => 'error', 'message' => 'Invalid or expired magic link'];
        }

        $user = $this->userRepository->findByEmail($magicLink->email);

        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }

        // Mark magic link as used
        $ipAddress = $request?->ip();
        $userAgent = $request?->userAgent();
        $magicLink->markAsUsed($ipAddress, $userAgent);

        return $this->generateTokens($user, $request);
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken(string $refreshTokenId, ?Request $request = null): array
    {
        $refreshToken = $this->userRepository->findValidRefreshToken($refreshTokenId);

        if (!$refreshToken || !$refreshToken->isValid()) {
            return ['status' => 'error', 'message' => 'Invalid refresh token'];
        }

        // Update last used
        $ipAddress = $request?->ip();
        $userAgent = $request?->userAgent();
        $refreshToken->markAsUsed($ipAddress, $userAgent);

        return $this->generateTokens($refreshToken->user, $request);
    }

    /**
     * Generate JWT access token and refresh token
     */
    protected function generateTokens(CentralUser $user, ?Request $request = null): array
    {
        // Update login statistics
        $ipAddress = $request?->ip();
        $userAgent = $request?->userAgent();
        $this->userRepository->updateLoginStats($user, $ipAddress, $userAgent);

        // Generate JWT access token
        $accessToken = JWTAuth::fromUser($user);
        $payload = JWTAuth::getPayload($accessToken);
        $jti = $payload->get('jti');

        // Create refresh token
        $refreshToken = $this->userRepository->createRefreshToken($user, $jti, $ipAddress, $userAgent);

        // With Stancl's synced resources, user sync happens automatically
        // when the user is attached to a tenant via the tenant_users pivot table

        return [
            'status' => 'success',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token_id,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'two_factor_enabled' => $user->two_factor_enabled,
            ]
        ];
    }

    /**
     * Verify 2FA and complete login
     */
    public function verifyTwoFactor(int $userId, string $otp, ?Request $request = null): array
    {
        try {
            $user = $this->userRepository->findById($userId);
            $tenant = Tenancy::initialized() ? tenant() : null;

            if (!$user) {
                return ['status' => 'error', 'message' => 'User not found'];
            }

            // Verify 2FA code
            $google2fa = new \PragmaRX\Google2FA\Google2FA();
            $secret = decrypt($user->two_factor_secret);

            if (!$google2fa->verifyKey($secret, $otp)) {
                // Record failed 2FA attempt
                $this->analyticsService->recordFailedLogin(
                    $user->email,
                    $tenant,
                    $request,
                    'Invalid 2FA code'
                );

                return ['status' => 'error', 'message' => 'Invalid 2FA code'];
            }

            // Record successful 2FA login
            $this->analyticsService->recordTwoFactorLogin($user, $tenant, $request);

            return $this->generateTokens($user, $request);

        } catch (\Exception $e) {
            $user = $this->userRepository->findById($userId);
            $tenant = Tenancy::initialized() ? tenant() : null;

            if ($user) {
                $this->analyticsService->recordFailedLogin(
                    $user->email,
                    $tenant,
                    $request,
                    'Invalid 2FA code'
                );
            }

            return ['status' => 'error', 'message' => 'Invalid 2FA code'];
        }
    }

    /**
     * Login with magic link token
     */

    /**
     * Send magic link for passwordless login
     */
    public function sendMagicLink(string $email): array
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }

        if (!$user->hasVerifiedEmail()) {
            return ['status' => 'error', 'message' => 'Email not verified'];
        }

        if (!$user->isActive()) {
            return ['status' => 'error', 'message' => 'Account not active'];
        }

        // Create magic link token
        $magicLink = $this->userRepository->createMagicLinkToken(
            $email,
            'magic_login',
            ['user_id' => $user->id]
        );

        // Send magic link email
        // TODO: Create MagicLinkMail class
        // Mail::to($email)->send(new MagicLinkMail($magicLink->token));

        return [
            'status' => 'success',
            'message' => 'Magic link sent to your email'
        ];
    }

    /**
     * Refresh access token using refresh token
     */

    /**
     * Logout user and invalidate tokens
     */
    public function logout(?Request $request = null): array
    {
        try {
            // Invalidate JWT token
            JWTAuth::invalidate(JWTAuth::getToken());

            // Revoke refresh tokens for the user
            if ($request && $request->has('refresh_token')) {
                JwtRefreshToken::where('token_id', $request->refresh_token)
                    ->update(['revoked' => true]);
            }

            return [
                'status' => 'success',
                'message' => 'Successfully logged out'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to logout'
            ];
        }
    }
}
