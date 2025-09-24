# Test Google OAuth - Quick Debug

## Kiểm tra ngay trong browser:

1. **Start server** (nếu chưa chạy):
```bash
php artisan serve
```

2. **Truy cập trực tiếp**:
```
http://localhost:8000/api/auth/google
```

## Expected Results:

### ✅ **Success Response:**
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

### ❌ **If still error - Additional Debug:**

**Check logs:**
```bash
tail -f storage/logs/laravel.log
```

**Test with session driver file:**
Update `.env`:
```env
SESSION_DRIVER=file
```

**Clear cache:**
```bash
php artisan config:clear
php artisan cache:clear
```

## Troubleshooting Commands:

```bash
# 1. Check all config
php artisan config:show session

# 2. Check Google config  
php artisan config:show services.google

# 3. Test route directly
php artisan tinker
>>> app('App\Services\SocialAuthService')->getGoogleLoginUrl()
```

## Quick Fix cho session issue:

Nếu vẫn lỗi, add vào `routes/api.php`:
```php
Route::middleware(['web'])->group(function () {
    Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
});
```