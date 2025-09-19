<?php

namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;

class JWTAuthService
{
    /**
     * Access token TTL in minutes.
     */
    const ACCESS_TOKEN_TTL = 60; // 1 hour
    
    /**
     * Refresh token TTL in days.
     */
    const REFRESH_TOKEN_TTL = 30; // 30 days

    /**
     * Authenticate user and return tokens.
     *
     * @param array $credentials
     * @param Request $request
     * @return array|null
     */
    public function authenticate(array $credentials, Request $request): ?array
    {
        $user = User::where('email', $credentials['email'])->first();
        
        if (!$user || !$user->isActive()) {
            return null;
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        $accessToken = JWTAuth::fromUser($user);
        
        if (!$accessToken) {
            return null;
        }

        $refreshToken = $this->createRefreshToken($user, $request);

        return [
            'user' => $user->load('role'),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL * 60, 
        ];
    }

    /**
     * Refresh access token using refresh token.
     *
     * @param string $refreshTokenString
     * @param Request $request
     * @return array|null
     */
    public function refreshToken(string $refreshTokenString, Request $request): ?array
    {
        $refreshToken = RefreshToken::where('token', $refreshTokenString)
            ->active()
            ->first();

        if (!$refreshToken) {
            return null;
        }

        $user = $refreshToken->user;
        
        if (!$user || !$user->isActive()) {
            return null;
        }

        $accessToken = JWTAuth::fromUser($user);
        
        if (!$accessToken) {
            return null;
        }

        $refreshToken->update([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return [
            'user' => $user->load('role'),
            'access_token' => $accessToken,
            'refresh_token' => $refreshTokenString, 
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL * 60,
        ];
    }

    /**
     * Logout user and revoke tokens.
     *
     * @param Request $request
     * @return bool
     */
    public function logout(Request $request): bool
    {
        try {
            $user = Auth::user();
            
            if ($user) {
                $this->revokeAllUserTokens($user->id);
            }

            JWTAuth::invalidate(JWTAuth::getToken());
            
            return true;
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * Revoke specific refresh token.
     *
     * @param string $refreshTokenString
     * @param string|null $revokedBy
     * @return bool
     */
    public function revokeToken(string $refreshTokenString, ?string $revokedBy = null): bool
    {
        $refreshToken = RefreshToken::where('token', $refreshTokenString)->first();
        
        if (!$refreshToken) {
            return false;
        }

        return $refreshToken->revoke($revokedBy);
    }

    /**
     * Revoke all refresh tokens for a user.
     *
     * @param string $userId
     * @param string|null $revokedBy
     * @return int Number of tokens revoked
     */
    public function revokeAllUserTokens(string $userId, ?string $revokedBy = null): int
    {
        $tokens = RefreshToken::where('user_id', $userId)->active()->get();
        $count = 0;

        foreach ($tokens as $token) {
            if ($token->revoke($revokedBy)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Create a new refresh token for user.
     *
     * @param User $user
     * @param Request $request
     * @return RefreshToken
     */
    protected function createRefreshToken(User $user, Request $request): RefreshToken
    {
        return RefreshToken::create([
            'user_id' => $user->id,
            'token' => RefreshToken::generateToken(),
            'expire_at' => Carbon::now()->addDays(self::REFRESH_TOKEN_TTL),
            'status' => RefreshToken::STATUS_ACTIVE,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Get user from JWT token.
     *
     * @return User|null
     */
    public function getAuthenticatedUser(): ?User
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return null;
            }
            
            return $user->isActive() ? $user : null;
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Validate JWT token.
     *
     * @param string|null $token
     * @return bool
     */
    public function validateToken(?string $token = null): bool
    {
        try {
            if ($token) {
                JWTAuth::setToken($token);
            }
            
            return (bool) JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * Clean up expired tokens.
     *
     * @return int Number of tokens cleaned up
     */
    public function cleanupExpiredTokens(): int
    {
        return RefreshToken::expired()
            ->where('status', '!=', RefreshToken::STATUS_REVOKED)
            ->update(['status' => RefreshToken::STATUS_EXPIRED]);
    }

    /**
     * Get all active sessions for a user.
     *
     * @param string $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserActiveSessions(string $userId)
    {
        return RefreshToken::where('user_id', $userId)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }
}