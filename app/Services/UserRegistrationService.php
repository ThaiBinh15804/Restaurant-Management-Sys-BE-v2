<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\EmailVerificationToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class UserRegistrationService
{
    /**
     * Initiate user registration process.
     * Creates verification token and sends email.
     */
    public function initiateRegistration(
        string $name,
        string $email, 
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        DB::beginTransaction();
        
        try {
            if (User::where('email', $email)->exists()) {
                return [
                    'success' => false,
                    'message' => 'User with this email already exists',
                    'errors' => ['email' => ['Email already registered']]
                ];
            }

            EmailVerificationToken::byEmail($email)->active()->update(['is_used' => true]);

            $verificationToken = EmailVerificationToken::createForRegistration(
                $email,
                $name,
                $password,
                $ipAddress,
                $userAgent
            );

            $this->sendVerificationEmail($verificationToken);

            DB::commit();

            Log::info('User registration initiated', [
                'email' => $email,
                'name' => $name,
                'token_id' => $verificationToken->id
            ]);

            return [
                'success' => true,
                'message' => 'Registration initiated. Please check your email to verify your account.',
                'data' => [
                    'email' => $email,
                    'expires_at' => $verificationToken->expires_at,
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Registration initiation failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'errors' => []
            ];
        }
    }

    /**
     * Complete user registration by verifying email token.
     */
    public function completeRegistration(string $token): array
    {
        DB::beginTransaction();
        
        try {
            $verificationToken = EmailVerificationToken::findValidToken($token);
            
            if (!$verificationToken) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification token',
                    'errors' => []
                ];
            }

            if (User::where('email', $verificationToken->email)->exists()) {
                $verificationToken->markAsUsed();
                return [
                    'success' => false,
                    'message' => 'User with this email already exists',
                    'errors' => []
                ];
            }

            $defaultRole = Role::where('name', 'Customer')->where('is_active', true)->first();

            $user = User::create([
                'email' => $verificationToken->email,
                'password' => $verificationToken->temp_password,
                'status' => User::STATUS_ACTIVE,
                'role_id' => $defaultRole?->id,
                'email_verified_at' => Carbon::now(),
            ]);

            $user->customerProfile()->create([
                'full_name' => $verificationToken->temp_name,
            ]);
            
            $verificationToken->markAsUsed();

            DB::commit();

            Log::info('User registration completed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->customerProfile->full_name,
                'token_id' => $verificationToken->id,
                'role_id' => $user->role_id,
                'expires_at' => $user->email_verified_at,
            ]);

            return [
                'success' => true,
                'message' => 'Account created successfully. You can now login.',
                'data' => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->customerProfile->full_name,
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Registration completion failed', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Registration completion failed. Please try again.',
                'errors' => []
            ];
        }
    }

    /**
     * Resend verification email.
     */
    public function resendVerificationEmail(string $email): array
    {
        try {
            // Find the latest active token for this email
            $verificationToken = EmailVerificationToken::byEmail($email)
                ->active()
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$verificationToken) {
                return [
                    'success' => false,
                    'message' => 'No pending registration found for this email',
                    'errors' => []
                ];
            }

            // Check if we can resend (not too frequent)
            if ($verificationToken->created_at->diffInMinutes(Carbon::now()) < 2) {
                return [
                    'success' => false,
                    'message' => 'Please wait at least 2 minutes before requesting another verification email',
                    'errors' => []
                ];
            }

            // Send verification email again
            $this->sendVerificationEmail($verificationToken);

            Log::info('Verification email resent', [
                'email' => $email,
                'token_id' => $verificationToken->id
            ]);

            return [
                'success' => true,
                'message' => 'Verification email sent successfully',
                'data' => [
                    'email' => $email,
                    'expires_at' => $verificationToken->expires_at,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Resend verification email failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to resend verification email',
                'errors' => []
            ];
        }
    }

    /**
     * Send verification email to user.
     */
    private function sendVerificationEmail(EmailVerificationToken $verificationToken): void
    {
        // Generate verification URL
        $verificationUrl = URL::to('/') . '/api/auth/verify-email?token=' . $verificationToken->token;

        // For small systems, we'll use a simple approach with Mail::raw
        // You can enhance this with proper Mailable classes later
        $emailContent = $this->buildVerificationEmailContent(
            $verificationToken->temp_name,
            $verificationUrl,
            $verificationToken->expires_at
        );

        Mail::raw($emailContent, function ($message) use ($verificationToken) {
            $message->to($verificationToken->email, $verificationToken->temp_name)
                    ->subject('Verify Your Email Address - Restaurant Management System');
        });
    }

    /**
     * Build email content for verification.
     */
    private function buildVerificationEmailContent(
        string $name,
        string $verificationUrl,
        Carbon $expiresAt
    ): string {
        return "Hello {$name},\n\n" .
               "Thank you for registering with our Restaurant Management System!\n\n" .
               "Please click the link below to verify your email address and activate your account:\n\n" .
               "{$verificationUrl}\n\n" .
               "This verification link will expire on {$expiresAt->format('M d, Y \a\t H:i')}.\n\n" .
               "If you didn't create an account with us, please ignore this email.\n\n" .
               "Best regards,\n" .
               "Restaurant Management Team";
    }

    /**
     * Clean up expired verification tokens.
     */
    public function cleanupExpiredTokens(): int
    {
        $deletedCount = EmailVerificationToken::expired()->delete();
        
        Log::info('Cleaned up expired verification tokens', [
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }
}