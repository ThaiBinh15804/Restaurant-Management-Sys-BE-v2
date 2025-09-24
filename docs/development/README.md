# Restaurant Management System - Backend API

Hệ thống quản lý nhà hàng với kiến trúc Backend-only, cung cấp RESTful API để các ứng dụng client tích hợp.

## 🚀 Cài đặt và cấu hình

### 1. Clone dự án

```bash
git clone <repository-url>
cd Restaurant-Management-Sys-BE-v2
```

### 2. Cài đặt dependencies

```bash
composer install
```

### 3. Cấu hình môi trường

1. Sao chép file cấu hình:
```bash
cp .env.example .env
```

2. Cập nhật thông tin database trong `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=restaurant_db
DB_USERNAME=root
DB_PASSWORD=
```

3. Generate application key:
```bash
php artisan key:generate
```

### 4. Thiết lập database và dữ liệu mẫu

1. Tạo database `restaurant_db` trong MySQL
2. Chạy migrations và seed dữ liệu mẫu:
```bash
php artisan migrate:fresh --seed
```

**Lưu ý**: Lệnh này sẽ:
- Tạo tất cả bảng trong database
- Tạo 7 roles mặc định (Super Admin, Admin, Manager, Staff, Cashier, Kitchen, Waiter)
- Tạo permissions base cho các modules
- Tạo sample users data với roles tương ứng

### 5. Cấu hình JWT Authentication

```bash
php artisan jwt:secret
```

### 6. Generate Swagger Documentation

```bash
php artisan l5-swagger:generate
```

## Chạy ứng dụng

### Sử dụng PHP built-in server
```bash
php artisan serve
```

### Sử dụng Laragon
1. Khởi động Laragon
2. Đặt project trong thư mục `www` của Laragon
3. Truy cập: `http://restaurant-management-sys-be-v2.test`

## API Documentation

Sau khi chạy ứng dụng, truy cập Swagger UI tại:
- **Local**: `http://localhost:8000/swagger`
- **Laragon**: `http://restaurant-management-sys-be-v2.test/swagger`

## Tài khoản mặc định

Sau khi chạy seeder, hệ thống sẽ tạo các tài khoản mặc định:

### Users mẫu
- **Super Admin**: `superadmin@restaurant.local` (password: `password123`)
- **Admin**: `admin@restaurant.local` (password: `password123`)
- **Manager**: `manager@restaurant.local` (password: `password123`)
- **Staff**: `staff@restaurant.local` (password: `password123`)

### Roles và Permissions
- **Base roles**: Super Administrator, Administrator, Manager, Staff, Cashier, Kitchen Staff, Waiter/Server
- **Base permissions**: Bao gồm tất cả modules (users, roles, permissions, categories, products, orders, tables, reservations, inventory, reports, system)

## Authentication & Authorization

### JWT Authentication
Hệ thống sử dụng JWT (JSON Web Token) cho authentication:

```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "admin@restaurant.com",
    "password": "password123"
}
```

### RBAC (Role-Based Access Control)
- Hệ thống phân quyền dựa trên Role và Permission
- Mỗi user có 1 role
- Mỗi role có nhiều permissions
- Chi tiết xem: [RBAC_GUIDE.md](RBAC_GUIDE.md)

## API Endpoints

### Authentication Endpoints
```http
POST /api/auth/login     # Đăng nhập
POST /api/auth/logout    # Đăng xuất  
POST /api/auth/refresh   # Refresh token
GET  /api/auth/me        # Thông tin user hiện tại
```

### Core Resources
```http
# Users Management
GET    /api/users        # Danh sách users
POST   /api/users        # Tạo user mới
GET    /api/users/{id}   # Chi tiết user
PUT    /api/users/{id}   # Cập nhật user
DELETE /api/users/{id}   # Xóa user

# Roles & Permissions
GET    /api/roles        # Danh sách roles
POST   /api/roles        # Tạo role mới
GET    /api/permissions  # Danh sách permissions

# Restaurant Management
GET    /api/categories   # Danh mục sản phẩm
GET    /api/products     # Sản phẩm
GET    /api/orders       # Đơn hàng
GET    /api/tables       # Bàn ăn
GET    /api/reservations # Đặt bàn
GET    /api/inventory    # Kho hàng
```

### Health Check
```http
GET /health             # Kiểm tra trạng thái API
```

### API Structure
- **Base URL**: `/api`
- **Authentication**: Required cho tất cả endpoints (trừ login, health)
- **Response Format**: JSON
- **Error Handling**: Standardized error responses
- **Pagination**: Laravel standard pagination

### Authentication

API sử dụng JWT Authentication:
- Bearer token trong Authorization header
- Format: `Authorization: Bearer {access_token}`

### Refresh Token
- Hệ thống hỗ trợ refresh token để gia hạn access token
- Endpoint: `POST /api/auth/refresh`

## Development

### RBAC Management

Quản lý hệ thống phân quyền:

```bash
# Xem help cho tất cả commands
php artisan rbac help

# Đồng bộ permissions từ config
php artisan rbac sync --dry-run  # Preview
php artisan rbac sync            # Apply

# Quản lý roles và users
php artisan rbac list-roles
php artisan rbac list-permissions
php artisan rbac assign-role --user=admin@restaurant.local --role="Manager"
php artisan rbac check-permission --user=admin@restaurant.local --permission=users.create
```

## Kiến trúc hệ thống

Hệ thống sử dụng kiến trúc API-only với các layer sau:

1. **API Controllers** - Xử lý HTTP requests/responses
2. **Services** - Business logic (JWT Authentication Service)
2.1. **RBAC System** - Role-Based Access Control
3. **Middleware** - Authentication, authorization, CORS
4. **Models** - Eloquent ORM models với relationships
5. **Requests** - Input validation và form requests
6. **Resources** - API response transformation


### Key Dependencies
- **tymon/jwt-auth**: JWT Authentication
- **darkaonline/l5-swagger**: API Documentation
- **spatie/laravel-route-attributes**: Route attributes support

## Contributing

1. Fork dự án
2. Tạo feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Tạo Pull Request

## License

Dự án này được cấp phép dưới [MIT License](LICENSE).

## Support

Nếu gặp vấn đề, vui lòng tạo issue trong repository hoặc liên hệ team phát triển.
