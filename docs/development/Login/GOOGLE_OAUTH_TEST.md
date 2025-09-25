# Test Google OAuth Login

## Hướng dẫn test Google OAuth

### 1. Cấu hình Google OAuth Application

**Bước 1: Tạo Google OAuth App**
1. Truy cập [Google Cloud Console](https://console.cloud.google.com/)
2. Tạo hoặc chọn project
3. Vào **APIs & Services > Credentials**
4. Click **Create Credentials > OAuth 2.0 Client IDs**
5. Chọn **Application type: Web application**
6. Thêm **Authorized redirect URIs**: `http://localhost:8000/api/auth/google/callback`

**Bước 2: Cập nhật .env**
```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

### 2. Test các endpoints

#### Endpoint 1: Get Google Login URL
```bash
GET http://localhost:8000/api/auth/google
```

**Response mẫu:**
```json
{
    "success": true,
    "message": "Google OAuth URL generated successfully",
    "data": {
        "url": "https://accounts.google.com/oauth/authorize?client_id=...",
        "provider": "google"
    }
}
```

#### Endpoint 2: Google Callback (automatic)
```bash
GET http://localhost:8000/api/auth/google/callback?code=xxx&state=xxx
```

**Response mẫu (đăng nhập thành công):**
```json
{
    "success": true,
    "message": "Login successful via Google",
    "data": {
        "user": {
            "id": "USR_xxxxxxxxx",
            "name": "John Doe",
            "email": "john@gmail.com",
            "avatar": "https://lh3.googleusercontent.com/...",
            "status": "active",
            "email_verified_at": "2025-01-26T10:00:00.000000Z",
            "role": {
                "id": "ROL_xxxxxxxxx",
                "name": "Staff"
            }
        },
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "abc123def456...",
        "token_type": "Bearer",
        "expires_in": 3600,
        "provider": "google",
        "is_new_user": false
    }
}
```

**Response mẫu (tạo tài khoản mới):**
```json
{
    "success": true,
    "message": "Account created and login successful via Google",
    "data": {
        "user": {
            "id": "USR_xxxxxxxxx",
            "name": "Jane Doe",
            "email": "jane@gmail.com",
            "avatar": "https://lh3.googleusercontent.com/...",
            "status": "active",
            "email_verified_at": "2025-01-26T10:00:00.000000Z",
            "role": {
                "id": "ROL_xxxxxxxxx",
                "name": "Staff"
            }
        },
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "abc123def456...",
        "token_type": "Bearer",
        "expires_in": 3600,
        "provider": "google",
        "is_new_user": true
    }
}
```

### 3. Flow test hoàn chỉnh

#### Test với Postman/Frontend:

1. **Bước 1**: Call GET `/api/auth/google` để lấy OAuth URL
2. **Bước 2**: Redirect user đến URL từ response
3. **Bước 3**: User đăng nhập Google và được redirect về callback
4. **Bước 4**: Backend xử lý callback và trả về token
5. **Bước 5**: Sử dụng access_token để call các API khác

#### Test với cURL:

```bash
# 1. Lấy Google OAuth URL
curl -X GET "http://localhost:8000/api/auth/google"

# 2. Mở URL trong browser, đăng nhập và copy callback URL
# 3. Callback sẽ được xử lý tự động bởi Laravel
```

### 4. Debug và troubleshooting

#### Check logs:
```bash
tail -f storage/logs/laravel.log
```

#### Các lỗi thường gặp:

1. **Invalid redirect URI**
   - Kiểm tra GOOGLE_REDIRECT_URI trong .env
   - Đảm bảo redirect URI được add trong Google Console

2. **Invalid client credentials**
   - Kiểm tra GOOGLE_CLIENT_ID và GOOGLE_CLIENT_SECRET
   - Đảm bảo Google OAuth app được enable

3. **User already exists with different provider**
   - Logic sẽ login user existingvà update Google info

### 5. Frontend Integration

#### JavaScript example:
```javascript
// Lấy Google OAuth URL
const response = await fetch('/api/auth/google');
const data = await response.json();

// Redirect user to Google
if (data.success) {
    window.location.href = data.data.url;
}

// Sau khi callback thành công, sử dụng token
localStorage.setItem('access_token', data.data.access_token);
localStorage.setItem('refresh_token', data.data.refresh_token);
```

### 6. Security notes

- ✅ Refresh tokens được lưu với expiry time
- ✅ Access tokens có TTL 1 giờ  
- ✅ Thông tin user từ Google được validate
- ✅ Email từ Google được tự động verify
- ✅ Avatar và thông tin được update an toàn
- ✅ Supports both login existing users và create new users
- ✅ Inactive accounts không thể đăng nhập