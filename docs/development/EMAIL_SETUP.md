# Hướng Dẫn Cấu Hình Gmail SMTP

## Bước 1: Chuẩn Bị Gmail Account

1. **Bật 2-Factor Authentication** cho Gmail account
2. **Tạo App Password** cho Laravel:
   - Vào Google Account Settings: https://myaccount.google.com/
   - Security → 2-Step Verification → App passwords
   - Chọn "Mail" và "Other (custom name)" → nhập "Laravel Restaurant System"
   - Copy App Password (16 ký tự)

## Bước 2: Cập Nhật .env File

Thay thế phần MAIL trong .env file:

```env
# Email Configuration - Gmail SMTP
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-gmail@gmail.com
MAIL_PASSWORD=your-16-char-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-gmail@gmail.com
MAIL_FROM_NAME="Restaurant Management System"
```

## Ví Dụ Cụ Thể:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=binh1582004@gmail.com
MAIL_PASSWORD=abcd efgh ijkl mnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=binh1582004@gmail.com
MAIL_FROM_NAME="Restaurant Management System"
```

## Test Gửi Email

Sau khi cấu hình, test bằng lệnh:

```bash
php artisan tinker
Mail::raw('Test email from Laravel', function($message) {
    $message->to('binh1582004@gmail.com')->subject('Test Email');
});
```

## Lưu Ý

- App Password phải có 2FA enabled
- Không dùng password thường của Gmail
- Port 587 với TLS encryption
- Kiểm tra spam folder nếu không thấy email