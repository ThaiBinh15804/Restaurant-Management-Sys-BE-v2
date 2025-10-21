# 01 - Tổng Quan Hệ Thống

> **Version:** 1.0.0 | **Last Updated:** October 21, 2025

## 📖 Giới Thiệu

Hệ thống **Restaurant Management System** là một ứng dụng Backend RESTful API được xây dựng bằng Laravel 12, cung cấp đầy đủ chức năng quản lý nhà hàng từ đặt bàn, order, menu, kho, thanh toán đến quản lý nhân sự.

## 🎯 Mục Tiêu Hệ Thống

- ✅ **Quản lý toàn diện**: Tất cả nghiệp vụ nhà hàng trong một hệ thống
- ✅ **Bảo mật cao**: JWT Authentication + RBAC authorization
- ✅ **Scalable**: Kiến trúc modular, dễ mở rộng
- ✅ **RESTful API**: Chuẩn REST, hỗ trợ đa nền tảng (Web, Mobile, Desktop)
- ✅ **Real-time**: Hỗ trợ cập nhật trạng thái real-time

## 🏗 Kiến Trúc Tổng Thể

```mermaid
graph TB
    subgraph "Client Layer"
        A1[Web App]
        A2[Mobile App]
        A3[Desktop App]
    end
    
    subgraph "API Gateway Layer"
        B1[Laravel Routes]
        B2[Middleware Stack]
        B3[Authentication<br/>JWT + OAuth]
    end
    
    subgraph "Application Layer"
        C1[Controllers]
        C2[Services]
        C3[Business Logic]
    end
    
    subgraph "Domain Layer"
        D1[Models<br/>Eloquent ORM]
        D2[Relationships]
        D3[Validation Rules]
    end
    
    subgraph "Data Layer"
        E1[(MySQL Database)]
        E2[File Storage]
        E3[Cache Redis]
    end
    
    A1 & A2 & A3 -->|HTTP/HTTPS| B1
    B1 --> B2
    B2 --> B3
    B3 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> D1
    D1 --> D2
    D2 --> E1
    C2 --> E2
    C2 --> E3
    
    style A1 fill:#e1f5ff
    style A2 fill:#e1f5ff
    style A3 fill:#e1f5ff
    style B3 fill:#fff3e0
    style C2 fill:#f3e5f5
    style D1 fill:#e8f5e9
    style E1 fill:#fce4ec
```

## 💻 Tech Stack

### Backend Framework
- **Laravel 12** - PHP Framework
- **PHP 8.2+** - Programming Language

### Database & Storage
- **MySQL 8.0+** - Primary Database
- **Redis** - Cache & Session (Optional)
- **File System** - Image & Document Storage

### Authentication & Security
- **JWT (tymon/jwt-auth)** - Stateless Authentication
- **Laravel Socialite** - OAuth (Google Login)
- **RBAC** - Role-Based Access Control

### API Documentation
- **Swagger/OpenAPI** - Auto-generated API docs
- **L5-Swagger** - Swagger integration

### Development Tools
- **Composer** - Dependency Management
- **Laravel Pint** - Code Style Fixer
- **PHPUnit** - Testing Framework

## 📦 Core Modules

```mermaid
graph LR
    A[Restaurant Management System] --> B[User Management]
    A --> C[Table & Order]
    A --> D[Menu & Dishes]
    A --> E[Inventory]
    A --> F[Billing & Payment]
    A --> G[Employee & Payroll]
    A --> H[Reports & Statistics]
    
    B --> B1[Users]
    B --> B2[Roles]
    B --> B3[Permissions]
    
    C --> C1[Tables]
    C --> C2[Sessions]
    C --> C3[Reservations]
    C --> C4[Orders]
    
    D --> D1[Dishes]
    D --> D2[Categories]
    D --> D3[Menus]
    
    E --> E1[Ingredients]
    E --> E2[Suppliers]
    E --> E3[Stock Import/Export]
    
    F --> F1[Invoices]
    F --> F2[Payments]
    F --> F3[Promotions]
    
    G --> G1[Employees]
    G --> G2[Shifts]
    G --> G3[Payrolls]
    
    style A fill:#ff6b6b
    style B fill:#4ecdc4
    style C fill:#45b7d1
    style D fill:#96ceb4
    style E fill:#ffeaa7
    style F fill:#dfe6e9
    style G fill:#fab1a0
    style H fill:#a29bfe
```

## 🔐 Security Architecture

```mermaid
sequenceDiagram
    participant Client
    participant API Gateway
    participant Auth Service
    participant RBAC System
    participant Controller
    participant Database
    
    Client->>API Gateway: Request + Credentials
    API Gateway->>Auth Service: Validate Token
    Auth Service->>Database: Check User & Token
    Database-->>Auth Service: User Data
    Auth Service->>RBAC System: Check Permissions
    RBAC System-->>Auth Service: Permission Result
    Auth Service-->>API Gateway: Authenticated User
    API Gateway->>Controller: Process Request
    Controller->>Database: Query Data
    Database-->>Controller: Response Data
    Controller-->>Client: JSON Response
```

### Security Layers

1. **Network Layer**
   - HTTPS/TLS encryption
   - CORS configuration
   - Rate limiting

2. **Authentication Layer**
   - JWT Access Tokens (60 min)
   - Refresh Tokens (30 days)
   - Device fingerprinting
   - Session management

3. **Authorization Layer**
   - Role-Based Access Control (RBAC)
   - 16 permission modules
   - 7 predefined roles
   - Dynamic permission checking

4. **Application Layer**
   - Input validation
   - XSS protection
   - SQL injection prevention
   - CSRF protection

## 🎨 Design Patterns

### 1. **MVC Pattern** (Model-View-Controller)
- **Models**: Eloquent ORM entities
- **Views**: JSON API responses
- **Controllers**: HTTP request handlers

### 2. **Service Layer Pattern**
- Tách business logic ra khỏi controllers
- Reusable services
- Easy testing

### 3. **Repository Pattern** (Implicit via Eloquent)
- Data access abstraction
- Query builder
- Relationship management

### 4. **Middleware Pattern**
- Request/Response filtering
- Authentication checks
- Permission validation

### 5. **Observer Pattern** (Laravel Events)
- Model events (creating, updating, etc.)
- Audit trail logging
- Custom ID generation

## 📊 System Flow

### Request Lifecycle

```mermaid
graph TB
    A[HTTP Request] --> B{Route Match?}
    B -->|No| Z[404 Not Found]
    B -->|Yes| C[Route Middleware]
    C --> D{Auth Required?}
    D -->|Yes| E[JWT Auth Middleware]
    D -->|No| F[Controller]
    E --> G{Valid Token?}
    G -->|No| Y[401 Unauthorized]
    G -->|Yes| H[Permission Middleware]
    H --> I{Has Permission?}
    I -->|No| X[403 Forbidden]
    I -->|Yes| F
    F --> J[Service Layer]
    J --> K[Model/Database]
    K --> L[Response Formatter]
    L --> M[JSON Response]
    
    style A fill:#e3f2fd
    style M fill:#c8e6c9
    style X fill:#ffcdd2
    style Y fill:#ffcdd2
    style Z fill:#ffcdd2
```

## 🔄 Data Flow Patterns

### 1. **Write Operations**
```
Client → Controller → Validation → Service → Model → Database
```

### 2. **Read Operations**
```
Client → Controller → Service → Model → Query Builder → Database → JSON
```

### 3. **File Upload**
```
Client → Controller → File Validation → Storage → Database (path)
```

## 📈 Scalability Considerations

### Horizontal Scaling
- **Stateless API**: No server-side sessions
- **Load Balancer Ready**: Multiple app instances
- **Database Replication**: Master-Slave setup

### Performance Optimization
- **Query Optimization**: Eager loading relationships
- **Caching**: Redis for frequently accessed data
- **API Response Caching**: HTTP cache headers
- **Database Indexing**: Primary keys, foreign keys, search fields

### Future Enhancements
- 🔮 **Queue System**: Laravel Queue for async tasks
- 🔮 **Microservices**: Split modules into services
- 🔮 **GraphQL**: Alternative API approach
- 🔮 **WebSocket**: Real-time updates
- 🔮 **Docker**: Containerization

## 🌐 API Principles

### RESTful Standards
- **Resource-Based URLs**: `/api/users`, `/api/dishes`
- **HTTP Methods**: GET, POST, PUT, DELETE
- **Status Codes**: 200, 201, 400, 401, 403, 404, 500
- **JSON Format**: Consistent response structure

### Response Format
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data here
  },
  "meta": {
    "pagination": {},
    "timestamp": "2025-10-21T10:00:00Z"
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

## 📝 Naming Conventions

### Database
- **Tables**: `snake_case` plural (e.g., `dining_tables`, `order_items`)
- **Columns**: `snake_case` (e.g., `created_at`, `total_amount`)
- **Primary Keys**: `id` (custom string, not auto-increment)
- **Foreign Keys**: `{table}_id` (e.g., `user_id`, `order_id`)

### Code
- **Classes**: `PascalCase` (e.g., `UserController`, `OrderService`)
- **Methods**: `camelCase` (e.g., `createOrder`, `getUserById`)
- **Variables**: `camelCase` (e.g., `userId`, `totalAmount`)
- **Constants**: `UPPER_SNAKE_CASE` (e.g., `ACCESS_TOKEN_TTL`)

## 🔧 Configuration Management

### Environment Variables (.env)
```ini
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=localhost
JWT_SECRET=your-secret-key
GOOGLE_CLIENT_ID=your-client-id
```

### Config Files (config/)
- `auth.php` - Authentication settings
- `database.php` - Database connections
- `jwt.php` - JWT configuration
- `permissions.php` - RBAC definitions
- `cors.php` - CORS settings

## 📚 Documentation

- **API Docs**: `/swagger` - Interactive API documentation
- **Code Comments**: PHPDoc standards
- **README**: Setup and quick start guide
- **Architecture Docs**: This document series

---

## 🔗 Related Documents

- **Next**: [02-FOLDER-STRUCTURE.md](./02-FOLDER-STRUCTURE.md) - Cấu trúc thư mục
- **See also**: [03-DATA-MODEL.md](./03-DATA-MODEL.md) - Mô hình dữ liệu
- **See also**: [05-API-ARCHITECTURE.md](./05-API-ARCHITECTURE.md) - Kiến trúc API

---

**📅 Last Updated:** October 21, 2025  
**👤 Author:** Development Team
