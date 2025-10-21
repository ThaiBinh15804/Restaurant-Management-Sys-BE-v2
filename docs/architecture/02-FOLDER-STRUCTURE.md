# 02 - Cấu Trúc Thư Mục Dự Án

> **Version:** 1.0.0 | **Last Updated:** October 21, 2025

## 📁 Project Structure

```
Restaurant-Management-Sys-BE-v2/
│
├── 📂 app/                          # Application code
│   ├── Console/                     # Artisan commands
│   │   └── Commands/
│   │       ├── CleanupExpiredTokens.php
│   │       └── RBACCommand.php
│   │
│   ├── Http/                        # HTTP layer
│   │   ├── Controllers/
│   │   │   ├── Controller.php       # Base controller
│   │   │   └── Api/                 # API controllers
│   │   │       ├── AuthController.php
│   │   │       ├── UserController.php
│   │   │       ├── DiningTableController.php
│   │   │       ├── TableSessionController.php
│   │   │       ├── OrderController.php
│   │   │       ├── DishController.php
│   │   │       ├── IngredientController.php
│   │   │       ├── InvoiceController.php
│   │   │       ├── EmployeeController.php
│   │   │       └── ... (20+ controllers)
│   │   │
│   │   ├── Middleware/              # Custom middleware
│   │   │   ├── CheckPermission.php
│   │   │   └── EnableCookieQueue.php
│   │   │
│   │   └── Requests/                # Form requests (validation)
│   │       ├── BaseQueryRequest.php
│   │       ├── Customer/
│   │       ├── Dish/
│   │       ├── Employee/
│   │       └── ... (per module)
│   │
│   ├── Models/                      # Eloquent models
│   │   ├── BaseModel.php           # Base for all models
│   │   ├── BaseAuthenticatable.php # Base for auth models
│   │   ├── User.php
│   │   ├── Customer.php
│   │   ├── Employee.php
│   │   ├── DiningTable.php
│   │   ├── TableSession.php
│   │   ├── Order.php
│   │   ├── Dish.php
│   │   ├── Ingredient.php
│   │   ├── Invoice.php
│   │   └── ... (30+ models)
│   │   └── Traits/
│   │       ├── HasCustomId.php     # Custom ID generation
│   │       └── HasAuditFields.php  # Audit trail
│   │
│   ├── Services/                    # Business logic services
│   │   ├── JWTAuthService.php      # JWT authentication
│   │   ├── SocialAuthService.php   # OAuth (Google)
│   │   ├── TableSessionService.php # Table management
│   │   └── UserRegistrationService.php
│   │
│   ├── Providers/                   # Service providers
│   │   └── AppServiceProvider.php
│   │
│   └── Traits/                      # Reusable traits
│       └── HasFileUpload.php       # File upload helper
│
├── 📂 bootstrap/                    # Bootstrap files
│   ├── app.php
│   ├── providers.php
│   └── cache/
│
├── 📂 config/                       # Configuration files
│   ├── app.php                     # Application config
│   ├── auth.php                    # Authentication config
│   ├── database.php                # Database connections
│   ├── jwt.php                     # JWT settings
│   ├── permissions.php             # RBAC definitions ⭐
│   ├── cors.php                    # CORS settings
│   ├── l5-swagger.php              # Swagger config
│   ├── mail.php                    # Email settings
│   └── services.php                # Third-party services
│
├── 📂 database/                     # Database files
│   ├── factories/                   # Model factories
│   │   └── UserFactory.php
│   │
│   ├── migrations/                  # Database migrations
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2025_09_18_140959_create_roles_table.php
│   │   ├── 2025_09_18_141111_create_permissions_table.php
│   │   ├── 2025_09_28_153546_create_table_reservation_menu_dish_schema.php
│   │   └── ... (60+ migrations)
│   │
│   └── seeders/                     # Database seeders
│       ├── DatabaseSeeder.php
│       ├── RoleSeeder.php
│       ├── PermissionSeeder.php
│       └── UserSeeder.php
│
├── 📂 docs/                         # Documentation
│   ├── architecture/                # This architecture docs 📚
│   │   ├── 00-INDEX.md
│   │   ├── 01-SYSTEM-OVERVIEW.md
│   │   ├── 02-FOLDER-STRUCTURE.md
│   │   ├── 03-DATA-MODEL.md
│   │   ├── 05-API-ARCHITECTURE.md
│   │   ├── 07-AUTHENTICATION.md
│   │   ├── 08-AUTHORIZATION.md
│   │   ├── 10-TABLE-ORDER-MANAGEMENT.md
│   │   └── ... (more docs)
│   │
│   ├── development/                 # Development guides
│   │   ├── DEVELOPMENT_GUIDE.md
│   │   ├── RBAC_GUIDE.md
│   │   ├── EMAIL_SETUP.md
│   │   └── GOOGLE_OAUTH_SETUP.md
│   │
│   └── implementation/              # Implementation docs
│       └── IMPLEMENTATION_SUMMARY.md
│
├── 📂 public/                       # Public assets
│   ├── index.php                   # Entry point
│   ├── robots.txt
│   └── storage/                    # Symlink to storage/app/public
│
├── 📂 resources/                    # Resources
│   └── views/                      # Blade views (email templates)
│       └── emails/
│
├── 📂 routes/                       # Route definitions
│   ├── api.php                     # API routes (mostly auto-loaded via attributes)
│   ├── web.php                     # Web routes
│   └── console.php                 # Console routes
│
├── 📂 storage/                      # Storage files
│   ├── api-docs/                   # Swagger JSON
│   ├── app/                        # Application storage
│   │   ├── private/                # Private files
│   │   └── public/                 # Public files
│   │       └── assets/
│   │           ├── employee/       # Employee photos
│   │           ├── ingredient/     # Ingredient images
│   │           └── user/           # User avatars
│   ├── framework/                  # Framework files
│   ├── logs/                       # Application logs
│   └── certs/                      # SSL certificates
│
├── 📂 tests/                        # Test files
│   ├── Feature/                    # Feature tests
│   ├── Unit/                       # Unit tests
│   └── TestCase.php
│
├── 📂 vendor/                       # Composer dependencies
│
├── 📄 .env                         # Environment variables
├── 📄 .env.example                 # Example environment file
├── 📄 .gitignore                   # Git ignore rules
├── 📄 artisan                      # Artisan CLI
├── 📄 composer.json                # PHP dependencies
├── 📄 composer.lock                # Locked dependencies
├── 📄 phpunit.xml                  # PHPUnit config
└── 📄 README.md                    # Project README
```

## 📦 Key Directories Explained

### 1. **`app/`** - Application Core

#### **`app/Http/Controllers/Api/`**
- RESTful API controllers
- Each controller handles one resource
- Uses Route Attributes for routing
- Example: `UserController`, `OrderController`

#### **`app/Models/`**
- Eloquent ORM models
- Represents database tables
- Includes relationships
- Custom traits for ID generation and audit

#### **`app/Services/`**
- Business logic layer
- Separated from controllers
- Reusable across controllers
- Example: `JWTAuthService`, `TableSessionService`

#### **`app/Http/Middleware/`**
- Request/Response filters
- Authentication checks
- Permission validation
- Custom middleware for business logic

### 2. **`config/`** - Configuration

#### Important Config Files:
- **`permissions.php`** ⭐ - RBAC system definition
- **`jwt.php`** - JWT authentication settings
- **`auth.php`** - Authentication guards
- **`database.php`** - Database connections
- **`cors.php`** - CORS policy

### 3. **`database/`** - Database Layer

#### **`migrations/`**
- Version control for database schema
- Chronological order (by date prefix)
- Each migration is reversible

#### **`seeders/`**
- Initial data population
- Roles, permissions, sample users
- Development test data

### 4. **`storage/`** - File Storage

#### Structure:
```
storage/
├── app/
│   ├── private/        # Not web-accessible
│   └── public/         # Web-accessible (via symlink)
│       └── assets/
│           ├── employee/
│           ├── ingredient/
│           └── user/
├── logs/               # Application logs
└── framework/          # Framework cache/sessions
```

### 5. **`docs/`** - Documentation

#### **`architecture/`** - System architecture docs
- Complete system documentation
- Diagrams and flowcharts
- API specifications
- Business logic explanation

#### **`development/`** - Developer guides
- Setup instructions
- Development guidelines
- Troubleshooting guides

## 🔍 File Naming Conventions

### Controllers
```
{Resource}Controller.php
Example: UserController.php, OrderController.php
```

### Models
```
{SingularResource}.php
Example: User.php, Order.php, DiningTable.php
```

### Migrations
```
YYYY_MM_DD_HHMMSS_description.php
Example: 2025_09_18_140959_create_roles_table.php
```

### Requests (Validation)
```
{Action}{Resource}Request.php
Example: CreateUserRequest.php, UpdateOrderRequest.php
```

### Services
```
{Domain}Service.php
Example: JWTAuthService.php, TableSessionService.php
```

## 📋 Important Files

### `.env`
Environment configuration. **Never commit to git!**
```ini
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=restaurant_db
JWT_SECRET=your-secret-key
GOOGLE_CLIENT_ID=your-client-id
```

### `composer.json`
PHP dependencies management
```json
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "tymon/jwt-auth": "^2.2",
    "spatie/laravel-route-attributes": "^1.25"
  }
}
```

### `artisan`
Laravel command-line interface
```bash
php artisan serve              # Start dev server
php artisan migrate           # Run migrations
php artisan rbac:sync         # Sync permissions
php artisan l5-swagger:generate  # Generate API docs
```

## 🚀 File Generation Commands

### Create Controller
```bash
php artisan make:controller Api/ProductController --api
```

### Create Model
```bash
php artisan make:model Product -m  # with migration
```

### Create Migration
```bash
php artisan make:migration create_products_table
```

### Create Seeder
```bash
php artisan make:seeder ProductSeeder
```

### Create Request
```bash
php artisan make:request CreateProductRequest
```

### Create Service
```bash
# Manual creation in app/Services/
```

## 📝 Code Organization Best Practices

### ✅ DO:
- Keep controllers thin (delegate to services)
- Use Form Requests for validation
- Use Services for business logic
- Follow PSR-12 coding standards
- Use Route Attributes in controllers
- Create separate Request classes

### ❌ DON'T:
- Put business logic in controllers
- Validate in controllers (use Requests)
- Create models without migrations
- Mix concerns (separation of concerns)
- Hardcode values (use config)

## 🔄 Asset Management

### Public Assets
```
public/
├── index.php           # Entry point
├── robots.txt          # SEO
└── storage/            # Symlink → storage/app/public
```

### Create Storage Link
```bash
php artisan storage:link
```

### File Upload Path
```
storage/app/public/assets/{type}/{custom_id}/filename.ext

Example:
storage/app/public/assets/employee/EMP123ABC/photo.jpg
→ Accessible via: /storage/assets/employee/EMP123ABC/photo.jpg
```

## 🛠 Development Workflow

### 1. Create Feature
```bash
# 1. Create migration
php artisan make:migration create_products_table

# 2. Create model
php artisan make:model Product

# 3. Create controller
php artisan make:controller Api/ProductController

# 4. Create requests
php artisan make:request Product/CreateProductRequest
php artisan make:request Product/UpdateProductRequest

# 5. Add routes (via attributes in controller)

# 6. Run migration
php artisan migrate
```

### 2. Testing
```bash
# Run tests
php artisan test

# With coverage
php artisan test --coverage
```

### 3. Code Style
```bash
# Fix code style
./vendor/bin/pint
```

## 📚 Additional Resources

### Logs Location
```
storage/logs/laravel.log
```

### Cache Files
```
bootstrap/cache/
storage/framework/cache/
```

### Generated API Docs
```
storage/api-docs/api-docs.json
→ View at: /swagger
```

---

## 🔗 Related Documents

- **Previous**: [01-SYSTEM-OVERVIEW.md](./01-SYSTEM-OVERVIEW.md)
- **Next**: [03-DATA-MODEL.md](./03-DATA-MODEL.md)
- **See also**: [README.md](../../README.md) - Setup instructions

---

**📅 Last Updated:** October 21, 2025  
**👤 Author:** Development Team
