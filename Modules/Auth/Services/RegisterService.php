<?php

namespace Modules\Auth\Services;

use Modules\Auth\Repositories\UserRepository;
use Modules\User\Models\CentralUser;
use Modules\Auth\Models\MagicLink;
use Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Mail\EmailVerificationMail;

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

        // Wrap entire registration process in a database transaction
        return DB::transaction(function () use ($data, $tenant) {
            Log::info('Starting registration transaction', [
                'email' => $data['email'],
                'tenant_id' => $tenant ? $tenant->id : null
            ]);

            // Create user in central database
            $user = $this->userRepository->create($data);

            // Handle tenant-aware registration if tenant is provided (simple pivot table approach)
            $tenantAssociated = false;
            if ($tenant) {
                // Simply associate the central user with the tenant via pivot table
                $user->tenants()->syncWithoutDetaching([$tenant->id]);
                $tenantAssociated = true;
                
                Log::info('User associated with tenant via pivot table', [
                    'user_id' => $user->id,
                    'global_id' => $user->global_id,
                    'tenant_id' => $tenant->id
                ]);
            }

            // Create email verification token using MagicLink model
            $magicLink = $this->userRepository->createMagicLinkToken(
                $user->email,
                'email_verification',
                ['user_id' => $user->id]
            );

            Log::info('Registration transaction completed successfully', [
                'user_id' => $user->id,
                'global_id' => $user->global_id,
                'tenant_associated' => $tenantAssociated
            ]);

            // Send verification email (outside of transaction critical path)
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
                'tenant_associated' => $tenantAssociated
            ];
        });
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

}
