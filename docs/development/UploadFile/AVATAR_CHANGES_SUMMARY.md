# Tóm tắt thay đổi: Avatar Upload System

## 📋 Tổng quan thay đổi

Hệ thống avatar đã được điều chỉnh để **lưu trực tiếp URL đầy đủ vào database** thay vì lưu relative path. Điều này giúp đơn giản hóa việc sử dụng ở frontend.

---

## ✅ Files đã thay đổi

### 1. **User Model** (`app/Models/User.php`)
**Thay đổi:**
- ❌ Xóa `avatar_url` khỏi `$appends`
- ❌ Xóa accessor `getAvatarUrlAttribute()`
- ✅ Giữ nguyên field `avatar` trong `$fillable`

**Kết quả:**
- `avatar` field giờ chứa URL đầy đủ (ví dụ: `http://localhost:8000/storage/assets/employee/EMP-001/file.jpg`)
- Không cần accessor để generate URL

### 2. **HasFileUpload Trait** (`app/Traits/HasFileUpload.php`)
**Thay đổi:**
- ✅ `uploadFile()` giờ **trả về URL đầy đủ** thay vì path
- ✅ Thêm method `deleteFileByUrl()` để xóa file dựa trên URL
- ✅ Cập nhật parameter từ `$oldFilePath` thành `$oldAvatarUrl`

**Methods:**
```php
// Trả về URL đầy đủ
protected function uploadFile(
    UploadedFile $file,
    string $entityType,
    string $entityId,
    ?string $oldAvatarUrl = null
): string

// Xóa file dựa trên full URL
protected function deleteFileByUrl(string $fileUrl): bool

// Xóa file dựa trên relative path (helper)
protected function deleteFile(string $filePath): bool
```

### 3. **Controllers**
**EmployeeController & CustomerController:**
- ✅ Đã được update để sử dụng `uploadFile()` 
- ✅ Giá trị trả về từ `uploadFile()` là URL đầy đủ
- ✅ URL này được lưu trực tiếp vào `users.avatar`

---

## 🔄 So sánh Before/After

### Before (Relative Path)
```json
{
  "id": "U-000001",
  "email": "john@example.com",
  "avatar": "assets/employee/EMP-000001/1733932800_abc123.jpg",
  "avatar_url": "http://localhost:8000/storage/assets/employee/EMP-000001/1733932800_abc123.jpg",
  "name": "John Doe"
}
```

### After (Full URL)
```json
{
  "id": "U-000001",
  "email": "john@example.com",
  "avatar": "http://localhost:8000/storage/assets/employee/EMP-000001/1733932800_abc123.jpg",
  "name": "John Doe"
}
```

---

## 💡 Ưu điểm

### 1. **Đơn giản hóa Frontend**
```jsx
// Before: Cần sử dụng avatar_url
<img src={user.avatar_url || '/default.png'} />

// After: Trực tiếp dùng avatar
<img src={user.avatar || '/default.png'} />
```

### 2. **Giảm Processing**
- ❌ Không cần accessor/getter mỗi lần query
- ❌ Không cần append attribute
- ✅ Database đã chứa giá trị final

### 3. **Flexibility**
- ✅ Dễ migrate sang CDN (chỉ cần đổi base URL khi upload)
- ✅ Có thể mix local storage & external storage
- ✅ Hỗ trợ external URL (từ social login, v.v.)

### 4. **Consistency**
- ✅ URL luôn nhất quán
- ✅ Không phụ thuộc vào APP_URL config tại runtime
- ✅ Một nguồn dữ liệu duy nhất (database)

---

## ⚠️ Lưu ý quan trọng

### 1. **Migration cho dữ liệu cũ**
Nếu có data cũ với relative path, cần migrate:

```bash
php artisan make:migration update_user_avatars_to_full_url
```

```php
public function up()
{
    DB::table('users')->whereNotNull('avatar')->each(function ($user) {
        if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'avatar' => url('storage/' . $user->avatar)
                ]);
        }
    });
}
```

### 2. **Thay đổi APP_URL**
Khi deploy hoặc thay đổi domain, cần update URL trong database:

```sql
UPDATE users 
SET avatar = REPLACE(avatar, 'http://old-domain.com', 'https://new-domain.com')
WHERE avatar IS NOT NULL;
```

### 3. **Storage Link**
Đảm bảo symbolic link tồn tại:
```bash
php artisan storage:link
```

---

## 🧪 Testing

### Test Upload
```bash
curl -X POST "http://localhost:8000/api/employees/EMP-000001" \
  -H "Authorization: Bearer TOKEN" \
  -F "_method=PUT" \
  -F "avatar=@test-image.jpg"
```

### Expected Response
```json
{
  "success": true,
  "data": {
    "user": {
      "avatar": "http://localhost:8000/storage/assets/employee/EMP-000001/1733932800_xyz.jpg"
    }
  }
}
```

---

## 📊 Database Schema

### users table
```sql
avatar VARCHAR(500) NULL  -- Chứa full URL
```

**Ví dụ giá trị:**
```
http://localhost:8000/storage/assets/employee/EMP-000001/1733932800_abc123.jpg
http://localhost:8000/storage/assets/customer/CUS-000001/1733932800_def456.png
https://cdn.example.com/avatars/user-123.jpg  -- External URL
```

---

## 🔧 Code Flow

### Upload Process
```
1. User uploads file qua API
   ↓
2. Controller nhận file
   ↓
3. Call uploadFile() trait
   ↓
4. File lưu vào storage/app/public/assets/{entity}/{id}/
   ↓
5. Generate full URL: url('storage/' . $path)
   ↓
6. Return URL đầy đủ
   ↓
7. Controller lưu URL vào user.avatar
   ↓
8. Response trả về user với avatar URL đầy đủ
```

### Delete Process (khi upload mới)
```
1. uploadFile() nhận oldAvatarUrl
   ↓
2. Call deleteFileByUrl(oldAvatarUrl)
   ↓
3. Parse URL để lấy relative path
   ↓
4. Xóa file từ storage
   ↓
5. Upload file mới
```

---

## 📚 Documentation

Chi tiết đầy đủ: `docs/AVATAR_UPLOAD.md`

---

## ✨ Summary

| Aspect | Before | After |
|--------|--------|-------|
| Database | Relative path | Full URL |
| Response | 2 fields (avatar + avatar_url) | 1 field (avatar) |
| Accessor | Required | Not needed |
| Frontend | Use `avatar_url` | Use `avatar` |
| Processing | Runtime generation | Pre-generated |
| Flexibility | Limited | High (supports CDN, external URLs) |

---

**Ngày cập nhật:** 10/10/2025
**Status:** ✅ Completed and Tested
