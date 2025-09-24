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

            $refreshToken = RefreshToken::create([
                'user_id' => $user->id,
                'token' => bin2hex(random_bytes(32)),
                'expire_at' => Carbon::now()->addDays(self::REFRESH_TOKEN_TTL),
                'status' => RefreshToken::STATUS_ACTIVE,
                'user_agent' => request()->header('User-Agent'),
                'ip_address' => request()->ip(),
            ]);

            Log::info("Refresh token created successfully", [
                'user_id' => $user->id, 
                'token_id' => $refreshToken->id
            ]);

            return [
                'success' => true,
                'message' => 'Tokens generated successfully',
                'data' => [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken->token,
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

    private function createRefreshToken(User $user, Request $request): RefreshToken
    {
        return RefreshToken::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => Carbon::now()->addDays(self::REFRESH_TOKEN_TTL),
            'last_used_at' => Carbon::now(),
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
        ]);
    }

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

    public function refreshToken(string $refreshTokenString, Request $request): ?array
    {
        $refreshToken = RefreshToken::where('token', $refreshTokenString)
            ->active()
            ->with('user')
            ->first();

        if (!$refreshToken || !$refreshToken->user->isActive()) {
            return null;
        }

        $refreshToken->touch();

        $newAccessToken = JWTAuth::fromUser($refreshToken->user);
        
        if (!$newAccessToken) {
            return null;
        }

        return [
            'user' => $refreshToken->user->load('role'),
            'access_token' => $newAccessToken,
            'refresh_token' => $refreshToken->token,
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
}