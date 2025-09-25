# Hướng Dẫn Cấu Hình Google OAuth

## Bước 1: Tạo Google Cloud Project

1. Truy cập [Google Cloud Console](https://console.cloud.google.com/)
2. Tạo project mới hoặc chọn project hiện có
3. Tên project: "Restaurant Management System"

## Bước 2: Bật Google+ API

1. Vào "APIs & Services" → "Library"
2. Tìm kiếm "Google+ API" hoặc "Google People API"
3. Click "Enable"

## Bước 3: Tạo OAuth 2.0 Credentials

1. Vào "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "OAuth 2.0 Client ID"
3. Chọn "Web application"
4. Điền thông tin:
   - **Name**: Restaurant Management System
   - **Authorized JavaScript origins**: 
     - `http://localhost:8000`
     - `http://127.0.0.1:8000`
   - **Authorized redirect URIs**:
     - `http://localhost:8000/api/auth/google/callback`
     - `http://127.0.0.1:8000/api/auth/google/callback`

5. Click "Create"
6. Copy **Client ID** và **Client Secret**

## Bước 4: Cập Nhật .env File

Thêm vào cuối file .env:

```env
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

## Bước 5: Publish Socialite Config

```bash
php artisan vendor:publish --provider="Laravel\Socialite\SocialiteServiceProvider"
```

## Bước 6: Cấu Hình services.php

File `config/services.php` sẽ được tự động cập nhật.

## Test URL

Sau khi hoàn thành, test Google login tại:
`http://localhost:8000/api/auth/google/redirect`