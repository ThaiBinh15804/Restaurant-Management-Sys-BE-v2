# Avatar Upload Documentation

## Tổng quan
Hệ thống đã được cấu hình để hỗ trợ upload và quản lý avatar cho User (thông qua Employee và Customer).

## Cấu trúc lưu trữ

### Đường dẫn file
File avatar sẽ được lưu tại:
```
storage/app/public/assets/{entity_type}/{entity_id}/{timestamp}_{random}.{ext}
```

**Ví dụ:**
- Employee: `storage/app/public/assets/employee/EMP-000001/1733932800_a7b3c9d2e1.jpg`
- Customer: `storage/app/public/assets/customer/CUS-000001/1733932800_x5y6z7w8q9.png`

### Public URL
File có thể được truy cập qua URL:
```
http://your-domain.com/storage/assets/{entity_type}/{entity_id}/{filename}
```

## Response Format

### User Model Response
Khi query User model, response sẽ tự động bao gồm:

```json
{
  "id": "U-000001",
  "email": "john.doe@example.com",
  "avatar": "assets/employee/EMP-000001/1733932800_a7b3c9d2e1.jpg",
  "avatar_url": "http://localhost:8000/storage/assets/employee/EMP-000001/1733932800_a7b3c9d2e1.jpg",
  "name": "John Doe",
  "status_label": "Active",
  "role": { ... }
}
```

**Lưu ý:**
- `avatar`: Đường dẫn relative trong storage
- `avatar_url`: URL đầy đủ để hiển thị ảnh (tự động generate)

## API Endpoints

### 1. Upload Avatar cho Employee

**Endpoint:** `POST /api/employees/{id}` (với `_method=PUT`)

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Body (Form Data):**
```
_method: PUT
full_name: John Smith
avatar: [file] (image file)
```

**Validation Rules:**
- `avatar`: nullable, image, mimes:jpeg,jpg,png,gif,webp, max:2048KB

**Example với cURL:**
```bash
curl -X POST "http://localhost:8000/api/employees/EMP-000001" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "_method=PUT" \
  -F "full_name=John Smith" \
  -F "avatar=@/path/to/image.jpg"
```

**Response:**
```json
{
  "success": true,
  "message": "Employee updated successfully",
  "data": {
    "id": "EMP-000001",
    "full_name": "John Smith",
    "user": {
      "id": "U-000001",
      "email": "john@example.com",
      "avatar": "assets/employee/EMP-000001/1733932800_a7b3c9d2e1.jpg",
      "avatar_url": "http://localhost:8000/storage/assets/employee/EMP-000001/1733932800_a7b3c9d2e1.jpg"
    }
  }
}
```

### 2. Upload Avatar cho Customer

**Endpoint:** `POST /api/customers/{id}` (với `_method=PUT`)

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Body (Form Data):**
```
_method: PUT
full_name: Jane Doe
avatar: [file] (image file)
```

**Example với cURL:**
```bash
curl -X POST "http://localhost:8000/api/customers/CUS-000001" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "_method=PUT" \
  -F "full_name=Jane Doe" \
  -F "avatar=@/path/to/image.jpg"
```

## Frontend Integration

### React/Vue Example
```javascript
const updateEmployee = async (employeeId, formData) => {
  const data = new FormData();
  data.append('_method', 'PUT');
  data.append('full_name', 'John Smith');
  data.append('avatar', avatarFile); // File object from input

  const response = await fetch(`/api/employees/${employeeId}`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    },
    body: data
  });

  return response.json();
};
```

### Hiển thị Avatar
```jsx
// React
<img 
  src={user.avatar_url || '/default-avatar.png'} 
  alt={user.name}
  onError={(e) => e.target.src = '/default-avatar.png'}
/>

// Vue
<img 
  :src="user.avatar_url || '/default-avatar.png'" 
  :alt="user.name"
  @error="$event.target.src = '/default-avatar.png'"
/>
```

## Tính năng

### 1. Tự động xóa file cũ
Khi upload avatar mới, file cũ sẽ tự động bị xóa để tiết kiệm không gian lưu trữ.

### 2. Tự động generate URL
User model tự động thêm attribute `avatar_url` với URL đầy đủ.

### 3. Validation
- Chỉ chấp nhận file ảnh: jpeg, jpg, png, gif, webp
- Kích thước tối đa: 2MB
- Optional: Có thể update mà không cần upload avatar mới

### 4. Security
- File được lưu trong storage/app/public (accessible via symbolic link)
- Validation file type và size
- Authentication required

## Troubleshooting

### Lỗi: File không truy cập được
**Giải pháp:** Đảm bảo symbolic link đã được tạo
```bash
php artisan storage:link
```

### Lỗi: avatar_url trả về null
**Nguyên nhân:** File không tồn tại trong storage
**Giải pháp:** Kiểm tra đường dẫn file trong database và file system

### Lỗi: 413 Request Entity Too Large
**Nguyên nhân:** File size vượt quá giới hạn server
**Giải pháp:** 
- Kiểm tra `php.ini`: `upload_max_filesize` và `post_max_size`
- Kiểm tra nginx/apache config

## Testing

### Postman
1. Method: POST
2. URL: `http://localhost:8000/api/employees/{id}`
3. Headers: `Authorization: Bearer {token}`
4. Body: 
   - Type: form-data
   - Key `_method`: Value `PUT`
   - Key `avatar`: Type `File`, Select file
   - Key `full_name`: Value `John Smith`

### Swagger UI
Truy cập: `http://localhost:8000/api/documentation`
- Chọn endpoint `POST /api/employees/{id}`
- Click "Try it out"
- Upload file trong field `avatar`
- Thêm `_method=PUT` trong form

## Maintenance

### Dọn dẹp file không sử dụng
```bash
# Script để xóa file avatar không còn reference trong database
php artisan storage:cleanup-avatars
```
(Cần implement command này nếu cần)

### Backup
Đảm bảo backup thư mục:
```
storage/app/public/assets/
```

## Notes
- Avatar được lưu permanent, không tự động xóa khi xóa user
- Nếu cần xóa avatar khi xóa user, implement trong Model Observer
- Path pattern có thể customize trong trait `HasFileUpload`
