<?php

namespace Modules\Auth\Repositories;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\User\Models\CentralUser;
use Modules\Auth\Models\JwtRefreshToken;
use Modules\Auth\Models\MagicLink;

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
     * Revoke all refresh tokens for user
     */
    public function revokeAllRefreshTokens(int $userId): int
    {
        return JwtRefreshToken::revokeAllForUser($userId);
    }

    /**
     * Create magic link token using the existing MagicLink model
     */
    public function createMagicLinkToken(string $email, string $type = 'login', array $metadata = []): MagicLink
    {
        $user = $this->findByEmail($email);
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Revoke existing unused tokens for this user
        MagicLink::where('user_id', $user->id)->delete();

        return MagicLink::create([
            'user_id' => $user->id,
            'token' => hash('sha256', Str::random(60) . microtime()),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * Find valid magic link token using the existing MagicLink model
     */
    public function findValidMagicLinkToken(string $token, string $type = 'login'): ?MagicLink
    {
        return MagicLink::where('token', $token)
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();
    }
}
