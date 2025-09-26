<?php

namespace Modules\Auth\Services;

use Modules\Auth\Repositories\UserRepository;
use Modules\User\Models\CentralUser;
use Modules\Auth\Models\MagicLinkToken;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Mail\EmailVerificationMail;

class RegisterService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Register new user in central database
     */
    public function register(array $data): array
    {
        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser) {
            return ['status' => 'error', 'message' => 'User already exists'];
        }

        // Create user in central database
        $user = $this->userRepository->create($data);

        // Create email verification token
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
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ]
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

        $user = $this->userRepository->findByEmail($magicLink->email);

        if (!$user) {
            return ['status' => 'error', 'message' => 'User not found'];
        }

        if ($user->hasVerifiedEmail()) {
            return ['status' => 'error', 'message' => 'Email already verified'];
        }

        // Mark email as verified
        $user->markEmailAsVerified();

        // Mark token as used
        $magicLink->markAsUsed();

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
