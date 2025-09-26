<?php

namespace Modules\Auth\Services;

use Modules\Auth\Repositories\UserRepository;
use Modules\User\Models\CentralUser;
use Modules\Auth\Models\MagicLink;
use Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Mail\EmailVerificationMail;
use Stancl\Tenancy\Facades\Tenancy;

class RegisterService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Register new user in central database and optionally sync to tenant
     */
    public function register(array $data, ?string $tenantId = null): array
    {
        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser) {
            return ['status' => 'error', 'message' => 'User already exists'];
        }

        // Validate tenant if provided
        $tenant = null;
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return ['status' => 'error', 'message' => 'Invalid tenant ID provided'];
            }
        }

        // Create user in central database
        $user = $this->userRepository->create($data);

        // Handle tenant-aware registration if tenant is provided
        if ($tenant) {
            $this->syncUserToTenant($user, $tenant);
        }

        // Create email verification token using MagicLink model
        $magicLink = $this->userRepository->createMagicLinkToken(
            $user->email,
            'email_verification',
            ['user_id' => $user->id]
        );

        // Send verification email
        $this->sendEmailVerification($user->email, $magicLink->token);

        return [
            'status' => 'success',
            'message' => 'User registered successfully. Please check your email to verify your account.',
            'user' => [
                'id' => $user->id,
                'global_id' => $user->global_id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ],
            'tenant_synced' => !is_null($tenant)
        ];
    }

    /**
     * Verify email using token
     */
    public function verifyEmail(string $token): array
    {
        $magicLink = $this->userRepository->findValidMagicLinkToken($token, 'email_verification');

        if (!$magicLink) {
            return ['status' => 'error', 'message' => 'Invalid or expired verification token'];
        }

        $user = $magicLink->user;

        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }

        if ($user->hasVerifiedEmail()) {
            return ['status' => 'error', 'message' => 'Email already verified'];
        }

        // Mark email as verified
        $user->markEmailAsVerified();

        // Delete the used magic link token
        $magicLink->delete();

        return [
            'status' => 'success',
            'message' => 'Email verified successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ]
        ];
    }

    /**
     * Resend email verification
     */
    public function resendEmailVerification(string $email): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }

        if ($user->hasVerifiedEmail()) {
            return ['status' => 'error', 'message' => 'Email already verified'];
        }

        // Create new verification token
        $magicLink = $this->userRepository->createMagicLinkToken(
            $user->email,
            'email_verification',
            ['user_id' => $user->id]
        );

        // Send verification email
        $this->sendEmailVerification($user->email, $magicLink->token);

        return [
            'status' => 'success',
            'message' => 'Verification email sent successfully'
        ];
    }

    /**
     * Send email verification
     */
    /**
     * Resend email verification for existing user
     */

    protected function sendEmailVerification(string $email, string $token): void
    {
        $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;

        Mail::to($email)->send(new EmailVerificationMail($verificationUrl));
    }

    /**
     * Sync central user to tenant database using Stancl resource syncing
     */
    protected function syncUserToTenant(CentralUser $centralUser, Tenant $tenant): void
    {
        try {
            // Associate the central user with the tenant in the pivot table
            $centralUser->tenants()->attach($tenant->id, [
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Initialize tenant context and sync the user to tenant database
            Tenancy::initialize($tenant);
            
            // The ResourceSyncing trait will automatically sync the user
            // when the pivot relationship is created
            $centralUser->sync();
            
            Log::info('User successfully synced to tenant', [
                'user_id' => $centralUser->id,
                'global_id' => $centralUser->global_id,
                'tenant_id' => $tenant->id,
                'tenant_key' => $tenant->getTenantKey()
            ]);
            
        } catch (\Exception $e) {
            // Log the error but don't fail the registration
            Log::warning('Failed to sync user to tenant', [
                'user_id' => $centralUser->id,
                'global_id' => $centralUser->global_id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            // Always end tenancy context
            Tenancy::end();
        }
    }
}
