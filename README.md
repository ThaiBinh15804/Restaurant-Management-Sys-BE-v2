# Restaurant Management System - Backend API

Hệ thống quản lý nhà hàng với kiến trúc Backend-only, cung cấp RESTful API với các tính năng:
- **JWT Authentication** với refresh tokens
- **Email Registration** với verification
- **Google OAuth** integration
- **RBAC System** (Role-Based Access Control)
- **Swagger API Documentation**

## 📋 Yêu Cầu Hệ Thống

- **PHP**: 8.2+
- **Laravel**: 12.x
- **MySQL**: 8.0+
- **Composer**: 2.x+

## 🚀 Quick Start Guide

### 1. Clone và Cài Đặt

```bash
git clone <repository-url>
cd Restaurant-Management-Sys-BE-v2
composer install
```

### 2. Cấu Hình Môi Trường

```bash
# Copy file cấu hình
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret
```

### 3. Cấu Hình Database

Cập nhật thông tin database trong `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=restaurant_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Thiết Lập Database

```bash
# Tạo database (MySQL)
mysql -u root -p -e "CREATE DATABASE restaurant_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Chạy migrations và seeders
php artisan migrate:fresh --seed
```

**✅ Thành công!** Hệ thống sẽ tạo:
- 7 roles mặc định (Super Admin, Admin, Manager, Staff, Cashier, Kitchen, Waiter)
- Base permissions cho tất cả modules
- Sample users với roles tương ứng

### 5. Khởi Động Server

```bash
php artisan serve
```

🎉 **Truy cập**: http://localhost:8000/swagger để xem API documentation

## 🔧 Cấu Hình Nâng Cao

### Email Configuration (Gmail SMTP)
Để sử dụng email registration và verification:

📖 **Chi tiết**: [docs/development/EMAIL_SETUP.md](docs/development/EMAIL_SETUP.md)

**Quick setup:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-16-char-app-password  # Gmail App Password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Restaurant Management System"
```

### Google OAuth Integration
Để sử dụng Google login:

📖 **Chi tiết**: [docs/development/GOOGLE_OAUTH_SETUP.md](docs/development/GOOGLE_OAUTH_SETUP.md)

**Quick setup:**
```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

**Test OAuth**: [docs/development/GOOGLE_OAUTH_TEST.md](docs/development/GOOGLE_OAUTH_TEST.md)

## 📚 Documentation

### Development Guides
- 📘 [Development Guide](docs/development/DEVELOPMENT_GUIDE.md) - Kiến trúc và chuẩn phát triển
- 🔐 [RBAC Guide](docs/development/RBAC_GUIDE.md) - Hệ thống phân quyền
- 📧 [Registration Guide](docs/development/REGISTRATION_GUIDE.md) - Email registration flow
- 🔍 [System Analysis](docs/development/SYSTEM_ANALYSIS.md) - Phân tích hệ thống chi tiết

### Setup Guides  
- ✉️ [Email Setup](docs/development/EMAIL_SETUP.md) - Cấu hình Gmail SMTP
- 🔑 [Google OAuth Setup](docs/development/GOOGLE_OAUTH_SETUP.md) - Cấu hình Google login
- ⚡ [Quick Google Test](docs/development/QUICK_GOOGLE_TEST.md) - Test Google OAuth nhanh

### Implementation References
- 📝 [Implementation Summary](docs/development/IMPLEMENTATION_SUMMARY.md) - Tóm tắt các tính năng đã implement
- 🐛 [OAuth State Debug](docs/development/OAUTH_STATE_DEBUG.md) - Debug OAuth issues

## 👥 Tài Khoản Mặc Định

Sau khi chạy seeder, hệ thống sẽ tạo các tài khoản test:

| Role | Email | Password |
|------|--------|----------|
| Super Admin | `superadmin@restaurant.local` | `password123` |
| Admin | `admin@restaurant.local` | `password123` |
| Manager | `manager@restaurant.local` | `password123` |
| Staff | `staff@restaurant.local` | `password123` |

### Quick Login Test
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@restaurant.local",
    "password": "password123"
  }'
```

## 📡 API Documentation

### Swagger UI
Truy cập API documentation tại:
- **Local**: http://localhost:8000/swagger
- **Laragon**: http://restaurant-management-sys-be-v2.test/swagger

### Core Endpoints

#### Authentication
```http
POST /api/auth/login           # JWT Login
POST /api/auth/register        # Email Registration  
POST /api/auth/verify-email    # Email Verification
GET  /api/auth/google          # Google OAuth Login
POST /api/auth/refresh         # Refresh Token
POST /api/auth/logout          # Logout
GET  /api/auth/me              # Current User Info
```

#### User Management
```http
GET    /api/users        # List users (với pagination)
POST   /api/users        # Create user
GET    /api/users/{id}   # User details
PUT    /api/users/{id}   # Update user
DELETE /api/users/{id}   # Delete user
```

#### RBAC Management
```http
GET    /api/roles               # List roles
POST   /api/roles               # Create role
GET    /api/roles/{id}          # Role details
PUT    /api/roles/{id}          # Update role
DELETE /api/roles/{id}          # Delete role

GET    /api/permissions         # List permissions
POST   /api/permissions         # Create permission
```

### Authentication Flow

#### 1. Standard Login
```json
POST /api/auth/login
{
    "email": "admin@restaurant.local",
    "password": "password123"
}

Response:
{
    "success": true,
    "data": {
        "user": {...},
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refresh_token": "RT123456789",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}
```

#### 2. Email Registration Flow
```json
# Step 1: Register
POST /api/auth/register
{
    "name": "John Doe",
    "email": "john@example.com", 
    "password": "password123"
}

# Step 2: Verify email (token sent to email)
POST /api/auth/verify-email
{
    "token": "token-from-email"
}
```

#### 3. Google OAuth Flow
```http
# Step 1: Get OAuth URL
GET /api/auth/google
→ Redirects to Google

# Step 2: Google callback (automatic)
GET /api/auth/google/callback?code=...&state=...
→ Returns JWT tokens
```

### Authorization
Tất cả protected endpoints yêu cầu Bearer token:

```http
Authorization: Bearer {access_token}
```

## 🛠 Development Tools

### RBAC Commands
Quản lý roles và permissions:

```bash
# Sync permissions từ config
php artisan rbac sync

# List roles và permissions
php artisan rbac list-roles
php artisan rbac list-permissions

# Assign role cho user
php artisan rbac assign-role --user=admin@restaurant.local --role="Manager"

# Check user permissions
php artisan rbac check-permission --user=admin@restaurant.local --permission=users.create
```

📖 **Chi tiết RBAC**: [docs/development/RBAC_GUIDE.md](docs/development/RBAC_GUIDE.md)

### Generate API Documentation
```bash
php artisan l5-swagger:generate
```

### Database Operations
```bash
# Fresh migration với sample data
php artisan migrate:fresh --seed

# Chỉ chạy migrations
php artisan migrate

# Rollback migrations  
php artisan migrate:rollback
```

## ⚡ Troubleshooting

### Các Lỗi Thường Gặp

#### 1. JWT Token Issues
```bash
# Nếu gặp lỗi JWT token không hợp lệ
php artisan jwt:secret --force
php artisan config:clear
```

#### 2. Database Connection
```bash
# Kiểm tra connection
php artisan migrate:status

# Reset database nếu cần
php artisan migrate:fresh --seed
```

#### 3. Permission Issues (Linux/Mac)
```bash
sudo chown -R $USER:www-data storage
sudo chown -R $USER:www-data bootstrap/cache
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

#### 4. Google OAuth Issues
- ✅ Check GOOGLE_CLIENT_ID và GOOGLE_CLIENT_SECRET trong .env
- ✅ Verify redirect URI trong Google Console
- ✅ Đảm bảo session được khởi tạo đúng

📖 **Debug OAuth**: [docs/development/OAUTH_STATE_DEBUG.md](docs/development/OAUTH_STATE_DEBUG.md)

#### 5. Email Issues  
- ✅ Verify Gmail App Password (16 characters)
- ✅ Check MAIL_* configs trong .env
- ✅ Test email: `php artisan tinker` → `Mail::raw('test', fn($m) => $m->to('test@example.com'));`

### Health Check
```bash
# Kiểm tra API status
curl http://localhost:8000/health

# Response should be:
{
    "status": "OK",
    "timestamp": "2025-09-24T10:30:00Z",
    "version": "1.0.0"
}
```

## 🏗 Kiến Trúc Hệ Thống

### Tech Stack
- **Backend**: Laravel 12 + PHP 8.2+
- **Database**: MySQL 8.0+
- **Authentication**: JWT với refresh tokens
- **Authorization**: RBAC (Role-Based Access Control)
- **Documentation**: OpenAPI/Swagger
- **Email**: SMTP (Gmail)
- **OAuth**: Google Login

### Architecture Layers
```
┌─────────────────────────────────────┐
│             Frontend Apps            │ (Mobile, Web, Desktop)
├─────────────────────────────────────┤
│             API Gateway             │ (Laravel Routes + Middleware)
├─────────────────────────────────────┤
│            Controllers              │ (HTTP Request/Response)
├─────────────────────────────────────┤
│             Services                │ (Business Logic)
├─────────────────────────────────────┤
│              Models                 │ (Eloquent ORM)
├─────────────────────────────────────┤
│             Database                │ (MySQL)
└─────────────────────────────────────┘
```

### Key Features
- ✅ **JWT Authentication** - Stateless authentication
- ✅ **Email Registration** - With verification flow  
- ✅ **Google OAuth** - Social login integration
- ✅ **RBAC System** - Role-based access control
- ✅ **API Documentation** - Auto-generated Swagger
- ✅ **Custom ID Generation** - Prefix-based IDs instead of auto-increment
- ✅ **Route Attributes** - Modern routing approach
- ✅ **Refresh Tokens** - Secure token management

📖 **Architecture Details**: [docs/development/SYSTEM_ANALYSIS.md](docs/development/SYSTEM_ANALYSIS.md)

## 🚀 Deployment

### Production Checklist
- [ ] Set `APP_ENV=production` in .env
- [ ] Set `APP_DEBUG=false` in .env  
- [ ] Configure proper database credentials
- [ ] Set up SSL certificates
- [ ] Configure reverse proxy (Nginx/Apache)
- [ ] Set up monitoring and logging
- [ ] Configure backup strategy
- [ ] Update CORS settings for production domains

### Performance Optimization
```bash
# Production optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

## 🤝 Contributing

### Development Workflow
1. Fork repository
2. Create feature branch: `git checkout -b feature/feature-name`
3. Follow coding standards trong [DEVELOPMENT_GUIDE.md](docs/development/DEVELOPMENT_GUIDE.md)
4. Write tests cho new features
5. Update documentation nếu cần
6. Submit pull request

### Code Standards
- PSR-12 coding standards
- Laravel naming conventions  
- Route attributes instead of route files
- Service layer cho business logic
- Comprehensive API documentation

📖 **Development Standards**: [docs/development/DEVELOPMENT_GUIDE.md](docs/development/DEVELOPMENT_GUIDE.md)

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

## 📞 Support & Contact

- **Issues**: Create GitHub issue cho bugs hoặc feature requests
- **Documentation**: Check `docs/development/` cho detailed guides
- **Architecture**: Xem [SYSTEM_ANALYSIS.md](docs/development/SYSTEM_ANALYSIS.md) cho system overview

**🎯 Happy Coding!** 🚀
