<?php

namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;

class JWTAuthService
{
    const ACCESS_TOKEN_TTL = 60;
    const REFRESH_TOKEN_TTL = 30;
    const REFRESH_COOKIE_NAME = 'refresh_token';

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

        $this->revokeUserDeviceTokens($user->id, $this->getDeviceFingerprint($request));
        
        $refreshToken = $this->createRefreshToken($user, $request);
        
        $this->setRefreshTokenCookie($refreshToken->token);

        return [
            'user' => $user->load('role'),
            'access_token' => $accessToken,
            'token_type' => 'Bearer', 
            'expires_in' => self::ACCESS_TOKEN_TTL * 60,
        ];
    }

    public function generateTokenForUser(User $user): array
    {
        try {
            Log::info("Generating token for user", ['user_id' => $user->id, 'email' => $user->email]);
            
            $accessToken = JWTAuth::fromUser($user);
            
            if (!$accessToken) {
                Log::error("Failed to generate JWT access token for user", ['user_id' => $user->id]);
                return [
                    'success' => false,
                    'message' => 'Failed to generate access token',
                    'errors' => []
                ];
            }

            Log::info("JWT access token generated successfully", ['user_id' => $user->id]);

            $this->revokeUserDeviceTokens($user->id, $this->getDeviceFingerprint(request()));

            $refreshToken = RefreshToken::create([
                'user_id' => $user->id,
                'token' => bin2hex(random_bytes(32)),
                'expire_at' => Carbon::now()->addDays(self::REFRESH_TOKEN_TTL),
                'status' => RefreshToken::STATUS_ACTIVE,
                'device_fingerprint' => $this->getDeviceFingerprint(request()),
                'user_agent' => request()->header('User-Agent'),
                'ip_address' => request()->ip(),
            ]);

            Log::info("Refresh token created successfully", [
                'user_id' => $user->id, 
                'token_id' => $refreshToken->id
            ]);

            $this->setRefreshTokenCookie($refreshToken->token);

            return [
                'success' => true,
                'message' => 'Tokens generated successfully',
                'data' => [
                    'access_token' => $accessToken,
                    'token_type' => 'Bearer',
                    'expires_in' => self::ACCESS_TOKEN_TTL * 60,
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Exception in generateTokenForUser", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to generate tokens',
                'errors' => [$e->getMessage()]
            ];
        }
    }

    private function createRefreshToken(User $user, Request $request, ?Carbon $expireAt = null): RefreshToken
    {
        $deviceFingerprint = $this->getDeviceFingerprint($request);
        $this->revokeUserDeviceTokens($user->id, $deviceFingerprint);
    
        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(32)),
            'expire_at' => Carbon::now()->addDays(self::REFRESH_TOKEN_TTL),
            'status' => RefreshToken::STATUS_ACTIVE,
            'device_fingerprint' => $deviceFingerprint,
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
        ]);
    
        Log::info("Created refresh token", [
            'user_id' => $user->id,
            'token_id' => $refreshToken->id,
            'device_fingerprint' => $deviceFingerprint,
            'expire_at' => $refreshToken->expire_at
        ]);
    
        return $refreshToken;
    }

    public function logout(Request $request): bool
    {
        try {
            $user = Auth::user();
            
            if ($user) {
                $deviceFingerprint = $this->getDeviceFingerprint($request);
                $this->revokeUserDeviceTokens($user->id, $deviceFingerprint);
            }

            $this->clearRefreshTokenCookie();

            JWTAuth::invalidate(JWTAuth::getToken());
            
            Log::info("User logged out successfully", [
                'user_id' => $user ? $user->id : null
            ]);
            
            return true;
        } catch (JWTException $e) {
            Log::error("JWT logout error", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function revokeAllUserTokens(string $userId, ?string $revokedBy = null): int
    {
        $tokens = RefreshToken::where('user_id', $userId)
            ->active()
            ->get();

        $revokedCount = 0;
        foreach ($tokens as $token) {
            if ($token->revoke($revokedBy)) {
                $revokedCount++;
            }
        }

        return $revokedCount;
    }

    public function refreshToken(?string $refreshTokenString = null, ?Request $request = null): ?array
    {
        $request = $request ?? request();
        if (!$refreshTokenString) {
            $refreshTokenString = $this->getRefreshTokenFromCookie($request);
        }
        
        if (!$refreshTokenString) {
            Log::warning("No refresh token provided", ['has_cookie' => $request->hasCookie(self::REFRESH_COOKIE_NAME)]);
            return null;
        }

        $refreshToken = RefreshToken::where('token', $refreshTokenString)
            ->active()
            ->with('user')
            ->first();

        if (!$refreshToken || !$refreshToken->user->isActive()) {
            Log::warning("Invalid or inactive refresh token", [
                'token_found' => $refreshToken !== null,
                'user_active' => $refreshToken ? $refreshToken->user->isActive() : null
            ]);
            return null;
        }

        $currentFingerprint = $this->getDeviceFingerprint($request);
        if ($refreshToken->device_fingerprint !== $currentFingerprint) {
            Log::warning("Device fingerprint mismatch", [
                'stored' => $refreshToken->device_fingerprint,
                'current' => $currentFingerprint,
                'user_id' => $refreshToken->user_id
            ]);
            $refreshToken->revoke();
            return null;
        }

        $newAccessToken = JWTAuth::fromUser($refreshToken->user);
        
        if (!$newAccessToken) {
            return null;
        }

        $newRefreshToken = $this->createRefreshToken(
            $refreshToken->user,
            $request,
            $refreshToken->expire_at // 👈 dùng lại expire_at cũ
        );
        $this->setRefreshTokenCookie($newRefreshToken->token);
        
        $refreshToken->revoke();

        Log::info("Token refreshed successfully", [
            'user_id' => $refreshToken->user_id,
            'old_token_id' => $refreshToken->id,
            'new_token_id' => $newRefreshToken->id
        ]);

        return [
            'user' => $refreshToken->user->load('role'),
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL * 60,
        ];
    }

    public function getUserActiveSessions(string $userId)
    {
        return RefreshToken::where('user_id', $userId)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function revokeToken(string $refreshTokenString, ?string $revokedBy = null): bool
    {
        $refreshToken = RefreshToken::where('token', $refreshTokenString)->first();
        
        if (!$refreshToken) {
            return false;
        }

        return $refreshToken->revoke($revokedBy);
    }

    /**
     * Generate device fingerprint for unique device identification
     */
    private function getDeviceFingerprint(Request $request): string
    {
        $userAgent = $request->header('User-Agent', '');
        $acceptLanguage = $request->header('Accept-Language', '');
        $acceptEncoding = $request->header('Accept-Encoding', '');
        $ipAddress = $request->ip();
        
        $fingerprint = md5($userAgent . $acceptLanguage . $acceptEncoding . $ipAddress);
        
        return $fingerprint;
    }

    /**
     * Revoke all refresh tokens for a specific user and device
     */
    private function revokeUserDeviceTokens(string $userId, string $deviceFingerprint): void
    {
        RefreshToken::where('user_id', $userId)
            ->where('device_fingerprint', $deviceFingerprint)
            ->where('status', RefreshToken::STATUS_ACTIVE)
            ->update([
                'status' => RefreshToken::STATUS_REVOKED,
                'revoked_at' => Carbon::now(),
                'revoked_by' => $userId, 
            ]);
            
        Log::info("Revoked existing tokens for user device", [
            'user_id' => $userId,
            'device_fingerprint' => $deviceFingerprint
        ]);
    }

    /**
     * Set refresh token as HttpOnly cookie
     */
    private function setRefreshTokenCookie(string $token): void
    {
        $minutes = self::REFRESH_TOKEN_TTL * 24 * 60; // Convert days to minutes
        
        cookie()->queue(
            self::REFRESH_COOKIE_NAME,
            $token,
            $minutes,
            '/', // path
            null, // domain
            false, // secure
            true, // httpOnly
            false, // raw
            'Lax' // sameSite
        );
        
        Log::info("Refresh token cookie set", [
            'token_preview' => substr($token, 0, 8) . '...',
            'expires_minutes' => $minutes,
            'secure' => false,
            'domain' => null,
            'path' => '/',
            'httpOnly' => true,
            'sameSite' => 'Lax'
        ]);
    }

    /**
     * Get refresh token from cookie
     */
    private function getRefreshTokenFromCookie(Request $request): ?string
    {
        return $request->cookie(self::REFRESH_COOKIE_NAME);
    }

    /**
     * Clear refresh token cookie
     */
    private function clearRefreshTokenCookie(): void
    {
        cookie()->queue(cookie()->forget(self::REFRESH_COOKIE_NAME));
        Log::info("Refresh token cookie cleared");
    }

    /**
     * Automatic cleanup of expired tokens (called periodically)
     */
    public function cleanupExpiredTokens(int $daysOld = 7): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        $deletedCount = RefreshToken::where(function($query) use ($cutoffDate) {
            $query->where('expire_at', '<', Carbon::now())
                  ->orWhere('status', RefreshToken::STATUS_EXPIRED)
                  ->orWhere(function($q) use ($cutoffDate) {
                      $q->where('status', RefreshToken::STATUS_REVOKED)
                        ->where('revoked_at', '<', $cutoffDate);
                  });
        })->delete();
        
        Log::info("Automatic token cleanup completed", [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateTimeString()
        ]);
        
        return $deletedCount;
    }
}