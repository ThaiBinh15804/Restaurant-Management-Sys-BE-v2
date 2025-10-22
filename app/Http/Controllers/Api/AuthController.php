<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiResponseTrait;
use App\Models\Role;
use App\Models\User;
use App\Services\JWTAuthService;
use App\Services\UserRegistrationService;
use App\Services\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controller as BaseController;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for Authentication"
 * )
 */
#[Prefix('auth')]
class AuthController extends BaseController
{
    use ApiResponseTrait;

    protected JWTAuthService $authService;
    protected UserRegistrationService $registrationService;
    protected SocialAuthService $socialAuthService;

    public function __construct(
        JWTAuthService $authService,
        UserRegistrationService $registrationService,
        SocialAuthService $socialAuthService
    ) {
        $this->authService = $authService;
        $this->registrationService = $registrationService;
        $this->socialAuthService = $socialAuthService;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Authentication"},
     *     summary="User login",
     *     description="Authenticate user and return JWT tokens",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="superadmin@restaurant.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="refresh_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Invalid credentials or account is not active"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    #[Post('login')]
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:100',
            'password' => 'required|string|min:6|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $credentials = $request->only('email', 'password');
        $authData = $this->authService->authenticate($credentials, $request);

        if (!$authData) {
            return $this->errorResponse(
                'Invalid credentials or account is not active',
                [],
                401
            );
        }

        if (!$authData['success']) {
            return $this->errorResponse(
                $authData['message'],
                $authData['error_code'],
                401
            );
        }

        return $this->successResponse(
            $authData,
            'Login successful'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Authentication"},
     *     summary="Refresh access token",
     *     description="Get new access token using refresh token from cookie",
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired refresh token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid or expired refresh token")
     *         )
     *     )
     * )
     */
    #[Post('/refresh')]
    public function refresh(Request $request): JsonResponse
    {
        $authData = $this->authService->refreshToken(null, $request);

        if (!$authData) {
            return $this->errorResponse(
                'Invalid or expired refresh token',
                [],
                401
            );
        }

        return $this->successResponse(
            $authData,
            'Token refreshed successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentication"},
     *     summary="User logout",
     *     description="Logout user and invalidate tokens",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout successful")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    #[Post('/logout', middleware: ['auth:api'])]
    public function logout(Request $request): JsonResponse
    {
        $success = $this->authService->logout($request);

        if ($success) {
            return $this->successResponse([], 'Đăng xuất thành công');
        }

        return $this->errorResponse('Đăng xuất thất bại', [], 500);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     tags={"Authentication"},
     *     summary="Get authenticated user",
     *     description="Get current authenticated user information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User data retrieved successfully"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    #[Get('/me', middleware: ['auth:api'])]
    public function me(): JsonResponse
    {
        $user = Auth::user();
        // Load the role relationship
        if ($user && $user->role_id) {
            $user->role;
        }

        return $this->successResponse(
            $user,
            'User data retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/auth/sessions",
     *     tags={"Authentication"},
     *     summary="Get user active sessions",
     *     description="Get all active refresh token sessions for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sessions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sessions retrieved successfully"),
     *         )
     *     )
     * )
     */
    #[Get('/sessions', middleware: ['auth:api'])]
    public function sessions(): JsonResponse
    {
        $user = Auth::user();
        $sessions = $this->authService->getUserActiveSessions($user->id);

        return $this->successResponse(
            $sessions,
            'Sessions retrieved successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/revoke-token/{token_id}",
     *     tags={"Authentication"},
     *     summary="Revoke specific refresh token",
     *     description="Revoke a specific refresh token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="token_id",
     *         in="path",
     *         description="Token ID to revoke",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", description="Refresh token to revoke")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token revoked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token revoked successfully")
     *         )
     *     )
     * )
     */
    #[Delete('/revoke-token/{token_id}', middleware: ['auth:api'])]
    public function revokeToken(Request $request, string $token_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $user = Auth::user();
        $success = $this->authService->revokeToken($request->refresh_token, $user->id);

        if ($success) {
            return $this->successResponse([], 'Token revoked successfully');
        }

        return $this->errorResponse(
            'Token not found or already revoked',
            [],
            404
        );
    }

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Authentication"},
     *     summary="User registration",
     *     description="Register a new user account with email verification",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", maxLength=100, example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Registration initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Registration initiated. Please check your email to verify your account."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Registration failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="User with this email already exists"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    #[Post('register')]
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $result = $this->registrationService->initiateRegistration(
            $request->name,
            $request->email,
            $request->password,
            $request->ip(),
            $request->userAgent()
        );

        if ($result['success']) {
            return $this->successResponse(
                $result['data'],
                $result['message']
            );
        }

        return $this->errorResponse(
            $result['message'],
            $result['errors'],
            400
        );
    }

    /**
     * @OA\Get(
     *     path="/api/auth/verify-email",
     *     tags={"Authentication"},
     *     summary="Verify email address",
     *     description="Complete user registration by verifying email with token",
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         required=true,
     *         description="Email verification token",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Account created successfully. You can now login."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user_id", type="string", example="U12345678"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="name", type="string", example="John Doe")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired token",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Invalid or expired verification token"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    #[Get('verify-email')]
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Invalid verification token',
                $validator->errors(),
                422
            );
        }

        $result = $this->registrationService->completeRegistration($request->token);

        if ($result['success']) {
            return redirect('http://localhost:4200/login?verify=success');
        } else {
            return redirect('http://localhost:4200/login?verify=fail');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/resend-verification",
     *     tags={"Authentication"},
     *     summary="Resend verification email",
     *     description="Resend email verification for pending registration",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification email resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Verification email sent successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Resend failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="No pending registration found for this email"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    #[Post('resend-verification')]
    public function resendVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $result = $this->registrationService->resendVerificationEmail($request->email);

        if ($result['success']) {
            return $this->successResponse(
                $result['data'],
                $result['message']
            );
        }

        return $this->errorResponse(
            $result['message'],
            $result['errors'],
            400
        );
    }

    /**
     * @OA\Get(
     *     path="/api/auth/google",
     *     tags={"Authentication"},
     *     summary="Get Google OAuth login URL",
     *     description="Generate Google OAuth redirect URL for authentication",
     *     @OA\Response(
     *         response=200,
     *         description="Google OAuth URL generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Google OAuth URL generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="url", type="string", example="https://accounts.google.com/oauth/authorize..."),
     *                 @OA\Property(property="provider", type="string", example="google")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to generate Google login URL",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to generate Google login URL"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    #[Get('google')]
    public function googleRedirect(): JsonResponse
    {
        $result = $this->socialAuthService->getGoogleLoginUrl();

        if ($result['success']) {
            return $this->successResponse(
                $result['data'],
                $result['message']
            );
        }

        return $this->errorResponse(
            $result['message'],
            $result['errors'],
            500
        );
    }

    /**
     * @OA\Get(
     *     path="/api/auth/google/callback",
     *     tags={"Authentication"},
     *     summary="Handle Google OAuth callback",
     *     description="Process Google OAuth callback and login/register user",
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=true,
     *         description="OAuth authorization code from Google",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="state",
     *         in="query",
     *         required=true,
     *         description="OAuth state parameter",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Google OAuth authentication successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful via Google"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="refresh_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600),
     *                 @OA\Property(property="provider", type="string", example="google"),
     *                 @OA\Property(property="is_new_user", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Google OAuth authentication failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="OAuth authentication failed"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Account is inactive",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Account is inactive"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    #[Get('google/callback')]
    public function googleCallback(Request $request)
    {
        $frontendUrl = 'http://localhost:4200';
        $result = $this->socialAuthService->handleGoogleCallback();

        if ($result['success']) {
            if (!isset($result['data']['access_token'])) {
                return redirect("{$frontendUrl}/login?error=missing_token&provider=google");
            }

            // Encode token để truyền qua URL
            $accessToken = urlencode($result['data']['access_token']);
            $expiresIn = $result['data']['expires_in'] ?? 3600;

            // Redirect về /auth/callback để xử lý token, sau đó Angular sẽ redirect về home
            $redirectUrl = "{$frontendUrl}/auth/callback?access_token={$accessToken}&expires_in={$expiresIn}&provider=google";

            Log::info('Google OAuth callback successful, preparing to redirect.', ['result' => $redirectUrl]);
            return redirect($redirectUrl);
        }

        $errorMessage = urlencode($result['message'] ?? 'Authentication failed');
        $errorCode = $result['error_code'] ?? 'AUTH_FAILED';

        return redirect("{$frontendUrl}/login?" . http_build_query([
            'error' => 'true',
            'message' => $result['message'] ?? 'Authentication failed',
            'error_code' => $errorCode,
            'provider' => 'google'
        ]));
    }

    /**
     * @OA\Post(
     *     path="/api/auth/forgot-password",
     *     tags={"Authentication"},
     *     summary="Request password reset OTP",
     *     description="Send 6-digit OTP code to user's email for password reset",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP code has been sent to your email"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="expires_in", type="integer", example=300)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=404, description="Email not found")
     * )
     */
    #[Post('forgot-password')]
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        // Kiểm tra email tồn tại
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->errorResponse(
                'Email not found in our system',
                [],
                404
            );
        }

        // Generate OTP 6 số
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Lưu OTP vào cache (5 phút)
        $cacheKey = 'password_reset_otp:' . $request->email;
        Cache::put($cacheKey, [
            'otp' => $otp,
            'email' => $request->email,
            'created_at' => now(),
        ], now()->addMinutes(5));

        try {
            $email = $request->email;

            // ✅ Dùng Mail::raw() thay vì Mail::send()
            Mail::raw("Your password reset OTP code is: {$otp}\n\nThis code will expire in 5 minutes.", function ($message) use ($email) {
                $message->to($email);
                $message->subject('Password Reset OTP Code');
            });
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email:', ['error' => $e->getMessage()]);
            return $this->errorResponse(
                'Failed to send OTP email. Please try again later.',
                [],
                500
            );
        }

        return $this->successResponse(
            [
                'email' => $request->email,
                'expires_in' => 300, // 5 phút = 300 giây
            ],
            'OTP code has been sent to your email. Please check your inbox.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/auth/verify-otp",
     *     tags={"Authentication"},
     *     summary="Verify OTP code",
     *     description="Verify 6-digit OTP code and get reset token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","otp"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="otp", type="string", example="123456", minLength=6, maxLength=6)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP verified successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="reset_token", type="string", example="abc123xyz..."),
     *                 @OA\Property(property="expires_in", type="integer", example=600)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="Invalid or expired OTP")
     * )
     */
    #[Post('verify-otp')]
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:100',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        // Lấy OTP từ cache
        $cacheKey = 'password_reset_otp:' . $request->email;
        $otpData = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$otpData) {
            return $this->errorResponse(
                'OTP has expired or does not exist. Please request a new one.',
                [],
                400
            );
        }

        // Kiểm tra OTP
        if ($otpData['otp'] !== $request->otp) {
            return $this->errorResponse(
                'Invalid OTP code. Please try again.',
                [],
                400
            );
        }

        // OTP đúng → Tạo reset token
        $resetToken = \Illuminate\Support\Str::random(64);

        // Lưu reset token vào cache (10 phút)
        $resetCacheKey = 'password_reset_token:' . $resetToken;
        \Illuminate\Support\Facades\Cache::put($resetCacheKey, [
            'email' => $request->email,
            'created_at' => now(),
        ], now()->addMinutes(10));

        // Xóa OTP đã dùng
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        return $this->successResponse(
            [
                'reset_token' => $resetToken,
                'expires_in' => 600, // 10 phút
            ],
            'OTP verified successfully. You can now reset your password.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/auth/reset-password",
     *     tags={"Authentication"},
     *     summary="Reset password",
     *     description="Reset user password with reset token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reset_token","password","password_confirmation"},
     *             @OA\Property(property="reset_token", type="string", example="abc123xyz..."),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password has been reset successfully")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="Invalid or expired reset token")
     * )
     */
    #[Post('reset-password')]
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        // Lấy reset token từ cache
        $resetCacheKey = 'password_reset_token:' . $request->reset_token;
        $tokenData = \Illuminate\Support\Facades\Cache::get($resetCacheKey);

        if (!$tokenData) {
            return $this->errorResponse(
                'Invalid or expired reset token. Please start the password reset process again.',
                [],
                400
            );
        }

        // Tìm user
        $user = \App\Models\User::where('email', $tokenData['email'])->first();

        if (!$user) {
            return $this->errorResponse(
                'User not found',
                [],
                404
            );
        }

        // Cập nhật password
        $user->password = bcrypt($request->password);
        $user->save();

        // Xóa reset token
        \Illuminate\Support\Facades\Cache::forget($resetCacheKey);

        return $this->successResponse(
            [],
            'Password has been reset successfully. You can now login with your new password.'
        );
    }
}
