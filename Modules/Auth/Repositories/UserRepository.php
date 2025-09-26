<?php

namespace Modules\Auth\Repositories;

use Illuminate\Support\Facades\Hash;
use Modules\User\Models\CentralUser;
use Modules\Auth\Models\JwtRefreshToken;
use Modules\Auth\Models\MagicLinkToken;

class UserRepository
{
    /**
     * Create user in central database
     */
    public function create(array $data): CentralUser
    {
        return CentralUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'pending_verification',
        ]);
    }

    /**
     * Find user by email in central database
     */
    public function findByEmail(string $email): ?CentralUser
    {
        return CentralUser::where('email', $email)->first();
    }

    /**
     * Find user by ID in central database
     */
    public function findById(int $id): ?CentralUser
    {
        return CentralUser::find($id);
    }

    /**
     * Update user login statistics
     */
    public function updateLoginStats(CentralUser $user, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $user->updateLoginStats($ipAddress, $userAgent);
    }

    /**
     * Create refresh token for user
     */
    public function createRefreshToken(CentralUser $user, string $jti, ?string $ipAddress = null, ?string $userAgent = null): JwtRefreshToken
    {
        return JwtRefreshToken::create([
            'user_id' => $user->id,
            'token_id' => JwtRefreshToken::generateTokenId(),
            'jti' => $jti,
            'expires_at' => now()->addDays(30), // 30 days refresh token expiry
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Find valid refresh token
     */
    public function findValidRefreshToken(string $tokenId): ?JwtRefreshToken
    {
        return JwtRefreshToken::where('token_id', $tokenId)
            ->valid()
            ->with('user')
            ->first();
    }

    /**
     * Create magic link token
     */
    public function createMagicLinkToken(string $email, string $type = 'login', array $metadata = []): MagicLinkToken
    {
        return MagicLinkToken::createToken($email, $type, 15, $metadata);
    }

    /**
     * Find valid magic link token
     */
    public function findValidMagicLinkToken(string $token, string $type = 'login'): ?MagicLinkToken
    {
        return MagicLinkToken::findValidToken($token, $type);
    }

    /**
     * Revoke all refresh tokens for user
     */
    public function revokeAllRefreshTokens(int $userId): int
    {
        return JwtRefreshToken::revokeAllForUser($userId);
    }
}
