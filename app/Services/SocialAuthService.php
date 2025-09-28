<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Services\JWTAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Carbon\Carbon;

class SocialAuthService
{
    private JWTAuthService $jwtAuthService;

    public function __construct(JWTAuthService $jwtAuthService)
    {
        $this->jwtAuthService = $jwtAuthService;
    }
    /**
     * Redirect to Google OAuth provider.
     */
    public function redirectToGoogle(): string
    {
        if (!session()->isStarted()) {
            session()->start();
        }
        
        Log::info('Generating Google OAuth redirect', [
            'session_id' => session()->getId(),
            'session_started' => session()->isStarted(),
            'csrf_token' => csrf_token()
        ]);
        
        return Socialite::driver('google')->redirect()->getTargetUrl();
    }

    /**
     * Handle Google OAuth callback and login/register user.
     */
    public function handleGoogleCallback(): array
    {
        // Log callback session info
        Log::info('Processing Google OAuth callback', [
            'session_id' => session()->getId(),
            'session_started' => session()->isStarted(),
            'request_state' => request('state'),
            'request_code' => request('code') ? 'present' : 'missing',
            'query_params' => request()->query(),
        ]);
        
        DB::beginTransaction();
        
        try {
            // Get user info from Google
            $googleUser = Socialite::driver('google')->user();
            
            if (!$googleUser || !$googleUser->getEmail()) {
                return [
                    'success' => false,
                    'message' => 'Failed to get user information from Google',
                    'errors' => []
                ];
            }

            $existingUser = User::where('email', $googleUser->getEmail())->first();
            
            if ($existingUser) {
                $result = $this->loginExistingUser($existingUser, $googleUser);
            } else {
                $result = $this->registerNewUser($googleUser);
            }

            if ($result['success']) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return $result;

        } catch (InvalidStateException $e) {
            DB::rollBack();
            
            Log::warning('Google OAuth invalid state - regenerating session', [
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
                'has_session' => session()->isStarted(),
                'request_state' => request('state'),
                'session_state' => session('_token')
            ]);

            return [
                'success' => false,
                'message' => 'OAuth session expired. Please try logging in again.',
                'errors' => [],
                'action' => 'retry_login'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'OAuth authentication failed. Please try again.',
                'errors' => []
            ];
        }
    }

    /**
     * Login existing user with Google account.
     */
    private function loginExistingUser(User $user, $googleUser): array
    {
        try {
            if (!$user->isActive()) {
                return [
                    'success' => false,
                    'message' => 'Account is inactive. Please contact administrator.',
                    'errors' => []
                ];
            }

            $this->updateUserGoogleInfo($user, $googleUser);

            $tokenData = $this->jwtAuthService->generateTokenForUser($user);
            
            if (!$tokenData['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate authentication token',
                    'errors' => []
                ];
            }

            Log::info('Google OAuth login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => 'google'
            ]);

            return [
                'success' => true,
                'message' => 'Login successful via Google',
                'data' => [
                    'user' => $user->load('role'),
                    'access_token' => $tokenData['data']['access_token'],
                    'token_type' => 'Bearer',
                    'expires_in' => $tokenData['data']['expires_in'],
                    'provider' => 'google'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Google OAuth user login failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to login user',
                'errors' => []
            ];
        }
    }

    /**
     * Register new user from Google account.
     */
    private function registerNewUser($googleUser): array
    {
        try {
            $defaultRole = Role::where('name', 'Customer')->where('is_active', true)->first();

            // Create new user
            $user = User::create([
                'email' => $googleUser->getEmail(),
                'password' => bcrypt(uniqid()),
                'status' => User::STATUS_ACTIVE,
                'role_id' => $defaultRole?->id,
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => Carbon::now(),
            ]);

            $customer = $user->customerProfile()->create([
                'full_name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
            ]);

            $tokenData = $this->jwtAuthService->generateTokenForUser($user);
            
            if (!$tokenData['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate authentication token',
                    'errors' => []
                ];
            }

            Log::info('Google OAuth registration successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => 'google'
            ]);

            return [
                'success' => true,
                'message' => 'Account created and login successful via Google',
                'data' => [
                    'user' => $user->load('role'),
                    'access_token' => $tokenData['data']['access_token'],
                    'token_type' => 'Bearer',
                    'expires_in' => $tokenData['data']['expires_in'],
                    'provider' => 'google',
                    'is_new_user' => true
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Google OAuth user registration failed', [
                'email' => $googleUser->getEmail(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create user account',
                'errors' => []
            ];
        }
    }

    /**
     * Update existing user with Google information.
     */
    private function updateUserGoogleInfo(User $user, $googleUser): void
    {
        $updates = [];

        if (!$user->avatar && $googleUser->getAvatar()) {
            $updates['avatar'] = $googleUser->getAvatar();
        }

        if (!$user->email_verified_at) {
            $updates['email_verified_at'] = Carbon::now();
        }

        if (empty(trim($user->customerProfile->name)) && $googleUser->getName()) {
            $user->customerProfile->update(['full_name' => $googleUser->getName()]);
        }

        if (!empty($updates)) {
            $user->update($updates);
            
            Log::info('Updated user with Google info', [
                'user_id' => $user->id,
                'updates' => array_keys($updates)
            ]);
        }
    }

    /**
     * Get Google OAuth login URL for frontend.
     */
    public function getGoogleLoginUrl(): array
    {
        try {
            // Debug session information
            Log::info('Starting Google OAuth URL generation', [
                'session_started' => session()->isStarted(),
                'session_id' => session()->getId(),
                'csrf_token' => csrf_token(),
                'request_headers' => request()->headers->all()
            ]);
            
            $url = $this->redirectToGoogle();
            
            return [
                'success' => true,
                'message' => 'Google OAuth URL generated successfully',
                'data' => [
                    'url' => $url,
                    'provider' => 'google'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate Google OAuth URL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate Google login URL',
                'errors' => []
            ];
        }
    }
}