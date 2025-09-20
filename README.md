# Restaurant Management System - Backend API

Hệ thống quản lý nhà hàng với kiến trúc Backend-only, cung cấp RESTful API để các ứng dụng client tích hợp.

## Yêu cầu hệ thống

- **PHP**: 8.2+
- **Laravel**: 12.0
- **MySQL**: 8.0+
- **Composer**: 2.0+
- **Laragon**: Môi trường phát triển PHP/MySQL

## Cài đặt và cấu hình

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

### 4. Thiết lập database

1. Tạo database `restaurant_management` trong MySQL
2. Chạy migrations:
```bash
php artisan migrate
```

### 5. Generate Swagger Documentation

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
- **Laragon**: `http://restaurant-management-sys-be-v2.test/api/documentation`

## API Endpoints

### Health Check
```http
GET /health
```

Kiểm tra trạng thái API và kết nối database.

### API Structure

Tất cả API endpoints được tổ chức theo cấu trúc:
```
/{resource}
```

### Authentication

API sử dụng Laravel Sanctum cho authentication:
- Bearer token trong Authorization header
- Format: `Authorization: Bearer {token}`

## Development

### Tạo Controller mới

```bash
php artisan make:controller Api/ResourceController --api
```

### Tạo Model với Migration

```bash
php artisan make:model ResourceName -m
```

### Chạy tests

```bash
php artisan test
```

### Code Style

```bash
vendor/bin/pint
```

## Kiến trúc hệ thống

Hệ thống sử dụng kiến trúc API-only với các layer sau:

1. **API Controllers** - Xử lý HTTP requests/responses
2. **Services** - Business logic
3. **Repositories** - Data access layer
4. **Models** - Eloquent ORM models
5. **Resources** - API response transformation
6. **Requests** - Input validation

## Các lệnh hữu ích

```bash
# Chạy development server
composer run dev

# Generate Swagger docs
composer run swagger

# Chạy tests
composer run test

# Xem logs
php artisan pail

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

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

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
