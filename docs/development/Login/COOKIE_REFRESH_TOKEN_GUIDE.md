# Cookie-based Refresh Token Implementation

## 📋 Tổng Quan

Hệ thống đã được cập nhật để sử dụng **HttpOnly Cookies** cho refresh tokens thay vì trả về trong response body, giải quyết các vấn đề bảo mật và performance.

## 🚨 Vấn Đề Đã Giải Quyết

### 1. **Security Issues**
- **Trước**: Refresh token trong response body → dễ bị XSS attacks
- **Sau**: Refresh token trong HttpOnly cookie → không thể truy cập từ JavaScript

### 2. **Token Duplication**
- **Trước**: Mỗi lần login tạo token mới mà không xóa token cũ
- **Sau**: Revoke tokens cũ của cùng device trước khi tạo token mới

### 3. **Database Bloat**
- **Trước**: Không có strategy cleanup tokens cũ/expired
- **Sau**: Auto cleanup + manual command để xóa tokens cũ

### 4. **Device Management**
- **Trước**: Không phân biệt device/session cụ thể
- **Sau**: Device fingerprint để quản lý tokens theo device

## 🔧 Cấu Trúc Mới

### Database Schema Changes

```sql
-- Thêm trường device_fingerprint
ALTER TABLE refresh_tokens 
ADD COLUMN device_fingerprint VARCHAR(32) NULL AFTER ip_address;

-- Thêm index cho performance
ALTER TABLE refresh_tokens 
ADD INDEX idx_user_device (user_id, device_fingerprint);
```

### Cookie Configuration

```php
const REFRESH_COOKIE_NAME = 'refresh_token';
const REFRESH_TOKEN_TTL = 30; // days

// Cookie settings
- HttpOnly: true (không thể truy cập từ JS)
- Secure: true (chỉ HTTPS in production)
- SameSite: 'Lax' (CSRF protection)
- Path: '/' (accessible across site)
- Max-Age: 30 days
```

## 🔐 Security Features

### 1. Device Fingerprinting
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

### 2. Single Device Token Strategy
- Mỗi device chỉ có 1 active refresh token
- Login mới sẽ revoke token cũ của cùng device
- Refresh token sẽ tạo token mới và revoke token cũ

### 3. Device Verification
```php
// Verify device fingerprint for security
$currentFingerprint = $this->getDeviceFingerprint($request);
if ($refreshToken->device_fingerprint !== $currentFingerprint) {
    // Revoke suspicious token
    $refreshToken->revoke();
    return null;
}
```

## 🌐 API Changes

### Login Response
```json
// Trước
{
    "success": true,
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "RT123456789", // ❌ Security risk
        "token_type": "Bearer",
        "expires_in": 3600
    }
}

// Sau
{
    "success": true,
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer",
        "expires_in": 3600
        // ✅ refresh_token trong HttpOnly cookie
    }
}
```

### Refresh Endpoint
```http
// Trước
POST /api/auth/refresh
Content-Type: application/json

{
    "refresh_token": "RT123456789" // ❌ Phải gửi token trong body
}

// Sau  
POST /api/auth/refresh
Cookie: refresh_token=RT123456789; HttpOnly; Secure; SameSite=Lax
// ✅ Token tự động gửi trong cookie
```

## 📊 Performance Improvements

### 1. Automatic Token Cleanup
```php
// Service method
public function cleanupExpiredTokens(int $daysOld = 7): int

// Artisan command
php artisan tokens:cleanup --days=7
```

### 2. Database Indexes
```sql
-- Performance indexes
INDEX idx_user_device (user_id, device_fingerprint)
INDEX idx_status (status)
INDEX idx_expire_at (expire_at)
INDEX idx_token (token)
```

### 3. Reduced Database Queries
- Single query để revoke tokens cùng device
- Bulk operations cho cleanup
- Optimized relationships

## 🧪 Testing

### Manual Test
```bash
# Chạy test script
bash test_cookie_auth.sh

# Hoặc test từng endpoint
curl -c cookies.txt -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@restaurant.local","password":"password123"}' \
  http://localhost:8000/api/auth/login

curl -b cookies.txt -X POST \
  http://localhost:8000/api/auth/refresh
```

### Expected Behavior

1. **Login**: Sets `refresh_token` HttpOnly cookie
2. **Refresh**: Reads token from cookie, returns new access token
3. **Logout**: Clears refresh token cookie
4. **Multiple Login**: Revokes old device tokens
5. **Device Change**: Detects fingerprint mismatch and revokes

## 🔧 Deployment Notes

### Production Settings
```env
# Cookie security
SESSION_SECURE_COOKIE=true  # HTTPS only
SESSION_HTTP_ONLY=true      # No JS access
SESSION_SAME_SITE=lax      # CSRF protection

# Token cleanup schedule
# Add to cron: 0 2 * * * php artisan tokens:cleanup
```

### Monitoring
```php
// Key metrics to monitor
- Active refresh tokens count
- Token creation/revocation rate  
- Device fingerprint mismatches
- Cleanup operation results
```

## 🚀 Migration Guide

### For Existing Applications

1. **Run Migration**
```bash
php artisan migrate
```

2. **Update Frontend**
```javascript
// Trước: Lưu refresh token trong localStorage
localStorage.setItem('refresh_token', data.refresh_token);

// Sau: Cookie tự động được set, không cần lưu
// Chỉ cần call refresh endpoint khi cần
```

3. **Update API Calls**
```javascript
// Refresh token calls
fetch('/api/auth/refresh', {
    method: 'POST',
    credentials: 'include' // ✅ Quan trọng: gửi cookies
});
```

4. **Schedule Cleanup**
```bash
# Add to crontab
0 2 * * * cd /path/to/project && php artisan tokens:cleanup
```

## 📈 Benefits Summary

### Security ✅
- ❌ XSS attacks on refresh tokens
- ✅ HttpOnly cookie protection
- ✅ Device fingerprint verification
- ✅ Automatic token rotation

### Performance ✅
- ❌ Database bloat from old tokens
- ✅ Automatic cleanup strategy
- ✅ Optimized database queries
- ✅ Single active token per device

### User Experience ✅
- ✅ Seamless token refresh
- ✅ No manual token management
- ✅ Secure cross-tab sessions
- ✅ Proper logout handling

**🎉 Hệ thống hiện tại đã được tối ưu hoàn toàn về bảo mật và performance!**