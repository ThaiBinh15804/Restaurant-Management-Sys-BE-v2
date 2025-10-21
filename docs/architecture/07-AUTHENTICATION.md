# 07 - Authentication System

> **Version:** 1.0.0 | **Last Updated:** October 21, 2025

## 📖 Tổng Quan

Hệ thống sử dụng **JWT (JSON Web Token)** làm cơ chế xác thực chính, kết hợp với **Refresh Token** mechanism và hỗ trợ **OAuth 2.0** (Google Login).

## 🔐 Authentication Architecture

```mermaid
graph TB
    subgraph "Authentication Methods"
        A1[Email/Password Login]
        A2[Google OAuth Login]
        A3[Email Registration]
    end
    
    subgraph "JWT Service"
        B1[Generate Access Token]
        B2[Generate Refresh Token]
        B3[Validate Token]
        B4[Revoke Token]
    end
    
    subgraph "Token Storage"
        C1[Access Token<br/>Client Storage]
        C2[Refresh Token<br/>HttpOnly Cookie]
        C3[Token Database<br/>refresh_tokens table]
    end
    
    subgraph "Security Features"
        D1[Device Fingerprinting]
        D2[Token Expiration]
        D3[Token Rotation]
        D4[Session Management]
    end
    
    A1 & A2 & A3 --> B1 & B2
    B1 --> C1
    B2 --> C2 & C3
    B3 & B4 --> C3
    C1 & C2 & C3 --> D1 & D2 & D3 & D4
    
    style B1 fill:#e1f5fe
    style B2 fill:#e1f5fe
    style D1 fill:#fff3e0
    style D4 fill:#f3e5f5
```

## 🔑 Token System

### 1. **Access Token (JWT)**
- **Type**: Bearer Token
- **Lifetime**: 60 minutes
- **Storage**: Client-side (localStorage/memory)
- **Usage**: Sent in Authorization header
- **Contains**: User ID, Role, Permissions

```json
{
  "header": {
    "alg": "HS256",
    "typ": "JWT"
  },
  "payload": {
    "sub": "USR123ABC",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "manager",
    "iat": 1634567890,
    "exp": 1634571490
  },
  "signature": "..."
}
```

### 2. **Refresh Token**
- **Type**: Random string (64 chars)
- **Lifetime**: 30 days
- **Storage**: HttpOnly Cookie + Database
- **Usage**: Refresh access token when expired
- **Security**: Device fingerprint validation

```php
// Refresh Token Model
class RefreshToken {
    string $id;
    string $user_id;
    string $token;              // Random 64-char string
    datetime $expire_at;        // 30 days from creation
    string $status;             // active, revoked, expired
    string $device_fingerprint; // MD5 hash of device info
    string $user_agent;
    string $ip_address;
    datetime $revoked_at;
    string $revoked_by;
}
```

## 🚀 Authentication Flows

### Flow 1: Standard Login

```mermaid
sequenceDiagram
    participant Client
    participant API
    participant JWTService
    participant Database
    
    Client->>API: POST /auth/login<br/>{email, password}
    API->>Database: Find user by email
    
    alt User not found
        Database-->>API: null
        API-->>Client: 404 - Email not found
    else User is Google account
        Database-->>API: User (auth_provider=google)
        API-->>Client: 400 - Use Google login
    else User inactive
        Database-->>API: User (is_active=false)
        API-->>Client: 403 - Account inactive
    else Password incorrect
        API->>API: Hash::check(password)
        API-->>Client: 401 - Invalid password
    else Success
        Database-->>API: User data
        API->>JWTService: Generate tokens
        JWTService->>JWTService: Create access token (JWT)
        JWTService->>JWTService: Create refresh token
        JWTService->>Database: Store refresh token
        JWTService->>JWTService: Revoke old device tokens
        JWTService-->>API: Tokens
        API->>Client: Set refresh_token cookie
        API-->>Client: 200 - Success<br/>{user, access_token, expires_in}
    end
```

### Flow 2: Google OAuth Login

```mermaid
sequenceDiagram
    participant Client
    participant API
    participant Google
    participant JWTService
    participant Database
    
    Client->>API: GET /auth/google
    API->>API: Generate state token
    API->>API: Store state in session
    API-->>Client: Redirect to Google
    
    Client->>Google: Authorize app
    Google-->>Client: Redirect to callback
    
    Client->>API: GET /auth/google/callback<br/>?code=xxx&state=yyy
    API->>API: Validate state
    
    alt State mismatch
        API-->>Client: 400 - Invalid state
    else Success
        API->>Google: Exchange code for token
        Google-->>API: Access token
        API->>Google: Get user info
        Google-->>API: User profile
        
        API->>Database: Find user by email
        
        alt User exists (email login)
            Database-->>API: User (auth_provider=email)
            API-->>Client: 400 - Email already registered
        else User exists (google login)
            Database-->>API: User (auth_provider=google)
            API->>JWTService: Generate tokens
        else User not found
            API->>Database: Create new user
            Database-->>API: New user
            API->>JWTService: Generate tokens
        end
        
        JWTService-->>API: Tokens
        API-->>Client: 200 - Success + tokens
    end
```

### Flow 3: Email Registration

```mermaid
sequenceDiagram
    participant Client
    participant API
    participant Database
    participant EmailService
    participant User
    
    Client->>API: POST /auth/register<br/>{name, email, password}
    API->>Database: Check email exists
    
    alt Email exists
        Database-->>API: User found
        API-->>Client: 409 - Email already registered
    else Success
        API->>API: Hash password
        API->>Database: Create user (email_verified=false)
        Database-->>API: New user
        API->>Database: Create verification token
        API->>EmailService: Send verification email
        EmailService->>User: Email with token link
        API-->>Client: 201 - Registration successful<br/>Check email for verification
    end
    
    Note over User: User clicks email link
    
    User->>API: POST /auth/verify-email<br/>{token}
    API->>Database: Find token & check expiry
    
    alt Token invalid/expired
        Database-->>API: null or expired
        API-->>Client: 400 - Invalid/expired token
    else Success
        Database-->>API: Valid token
        API->>Database: Update user (email_verified=true)
        API->>Database: Delete token
        API-->>Client: 200 - Email verified
    end
```

### Flow 4: Token Refresh

```mermaid
sequenceDiagram
    participant Client
    participant API
    participant JWTService
    participant Database
    
    Note over Client: Access token expired
    
    Client->>API: POST /auth/refresh<br/>Cookie: refresh_token
    API->>JWTService: Get refresh token from cookie
    
    alt No cookie
        JWTService-->>API: null
        API-->>Client: 401 - No refresh token
    else Success
        JWTService->>Database: Find active token
        
        alt Token not found/expired
            Database-->>JWTService: null
            JWTService-->>API: null
            API-->>Client: 401 - Invalid token
        else Device fingerprint mismatch
            JWTService->>JWTService: Validate device fingerprint
            JWTService->>Database: Revoke token (security)
            JWTService-->>API: null
            API-->>Client: 401 - Security violation
        else Success
            Database-->>JWTService: Token data
            JWTService->>JWTService: Generate new access token
            JWTService->>Database: Create new refresh token
            JWTService->>Database: Revoke old refresh token
            JWTService->>API: Clear old cookie
            JWTService->>API: Set new cookie
            JWTService-->>API: New tokens
            API-->>Client: 200 - Success<br/>{access_token, expires_in}
        end
    end
```

### Flow 5: Logout

```mermaid
sequenceDiagram
    participant Client
    participant API
    participant JWTService
    participant Database
    
    Client->>API: POST /auth/logout<br/>Authorization: Bearer {token}
    API->>JWTService: Get current user
    JWTService->>Database: Revoke device refresh tokens
    Database-->>JWTService: Updated
    JWTService->>JWTService: Invalidate access token (blacklist)
    JWTService->>API: Clear refresh cookie
    JWTService-->>API: Success
    API-->>Client: 200 - Logged out
```

## 🛡️ Security Features

### 1. **Device Fingerprinting**

```php
private function getDeviceFingerprint(Request $request): string
{
    $userAgent = $request->header('User-Agent', '');
    $acceptLanguage = $request->header('Accept-Language', '');
    $acceptEncoding = $request->header('Accept-Encoding', '');
    $ipAddress = $request->ip();
    
    return md5($userAgent . $acceptLanguage . $acceptEncoding . $ipAddress);
}
```

**Benefits:**
- Detect token theft
- Prevent token reuse from different devices
- Automatic revocation on mismatch

### 2. **Token Rotation**

- **Old refresh token** is revoked when new one is issued
- Prevents refresh token reuse
- Limits attack window

### 3. **Automatic Cleanup**

```php
// Scheduled command (daily)
php artisan tokens:cleanup

// Deletes:
// - Expired tokens (expire_at < now)
// - Revoked tokens older than 7 days
```

### 4. **Session Management**

```php
// User can view all active sessions
GET /auth/sessions

// Response
{
  "sessions": [
    {
      "id": "RTK123",
      "device": "Chrome on Windows",
      "ip_address": "192.168.1.1",
      "last_active": "2025-10-21T10:00:00Z",
      "is_current": true
    }
  ]
}

// Revoke specific session
POST /auth/revoke-token
{
  "token_id": "RTK123"
}

// Revoke all sessions (except current)
POST /auth/revoke-all-tokens
```

## 🔒 Password Security

### Hashing
- **Algorithm**: bcrypt (Laravel default)
- **Cost**: 12 (configurable)
- **Salt**: Automatic

```php
// Hash on registration/password change
$hashedPassword = Hash::make($password);

// Verify on login
if (Hash::check($password, $user->password)) {
    // Valid
}
```

### Password Requirements
- **Minimum length**: 8 characters
- **Recommended**: Mix of uppercase, lowercase, numbers, symbols
- **Validation**: Laravel validation rules

```php
'password' => 'required|string|min:8|confirmed'
```

## 📧 Email Verification

### Token Generation
```php
$token = Str::random(64);
EmailVerificationToken::create([
    'email' => $user->email,
    'token' => hash('sha256', $token),
    'expire_at' => now()->addHours(24)
]);
```

### Verification Email
```
Subject: Verify Your Email Address

Click the link below to verify your email:
https://app.restaurant.com/verify-email?token={token}

This link expires in 24 hours.
```

### Resend Verification
```php
POST /auth/resend-verification
{
  "email": "user@example.com"
}
```

## 🔐 OAuth Configuration

### Google OAuth Setup

1. **Create OAuth 2.0 Credentials**
   - Go to Google Cloud Console
   - Create OAuth client ID
   - Add authorized redirect URI

2. **Environment Variables**
```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

3. **Scopes Requested**
   - `openid` - User authentication
   - `profile` - Basic profile info
   - `email` - Email address

## 🚨 Error Handling

### Authentication Errors

| Code | Message | HTTP Status |
|------|---------|-------------|
| `EMAIL_NOT_FOUND` | Email not registered | 404 |
| `GOOGLE_ACCOUNT_ONLY` | Use Google login | 400 |
| `ACCOUNT_INACTIVE` | Account disabled | 403 |
| `INVALID_PASSWORD` | Wrong password | 401 |
| `TOKEN_EXPIRED` | Token expired | 401 |
| `TOKEN_INVALID` | Invalid token | 401 |
| `TOKEN_REVOKED` | Token revoked | 401 |
| `EMAIL_NOT_VERIFIED` | Verify email first | 403 |
| `EMAIL_ALREADY_EXISTS` | Email in use | 409 |

### Example Error Response
```json
{
  "success": false,
  "message": "Invalid credentials",
  "error_code": "INVALID_PASSWORD",
  "timestamp": "2025-10-21T10:00:00Z"
}
```

## 📊 Token Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Active: Token Created
    Active --> Expired: TTL Elapsed
    Active --> Revoked: User Logout
    Active --> Revoked: Device Mismatch
    Active --> Revoked: Manual Revocation
    Active --> Refreshed: Token Refresh
    Refreshed --> Active: New Token
    Expired --> [*]: Auto Cleanup
    Revoked --> [*]: Auto Cleanup
    
    note right of Active
        Access Token: 60 min
        Refresh Token: 30 days
    end note
    
    note right of Expired
        Cleaned after 7 days
    end note
```

## 🔧 Configuration

### JWT Settings (config/jwt.php)
```php
return [
    'secret' => env('JWT_SECRET'),
    'ttl' => 60,                    // Access token lifetime (minutes)
    'refresh_ttl' => 43200,        // Refresh token lifetime (minutes = 30 days)
    'algo' => 'HS256',             // Algorithm
    'required_claims' => ['iss', 'iat', 'exp', 'nbf', 'sub', 'jti'],
    'blacklist_enabled' => true,   // Enable token blacklist
    'blacklist_grace_period' => 0, // Grace period (seconds)
];
```

### Auth Settings (config/auth.php)
```php
'defaults' => [
    'guard' => 'api',
    'passwords' => 'users',
],

'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

## 📝 Best Practices

### Client-Side
✅ **DO:**
- Store access token in memory/localStorage
- Never log tokens
- Clear tokens on logout
- Handle 401 responses (refresh flow)
- Use HTTPS only

❌ **DON'T:**
- Store refresh token in localStorage
- Share tokens between users
- Hardcode tokens
- Send tokens in URL parameters

### Server-Side
✅ **DO:**
- Use HttpOnly cookies for refresh tokens
- Implement device fingerprinting
- Rotate refresh tokens
- Log authentication events
- Rate limit authentication endpoints
- Clean expired tokens regularly

❌ **DON'T:**
- Log sensitive data (passwords, tokens)
- Use weak JWT secrets
- Disable token expiration
- Skip validation

---

## 🔗 Related Documents

- **Previous**: [05-API-ARCHITECTURE.md](./05-API-ARCHITECTURE.md)
- **Next**: [08-AUTHORIZATION.md](./08-AUTHORIZATION.md) - RBAC system
- **See also**: [09-USER-MANAGEMENT.md](./09-USER-MANAGEMENT.md)

---

**📅 Last Updated:** October 21, 2025  
**👤 Author:** Development Team
