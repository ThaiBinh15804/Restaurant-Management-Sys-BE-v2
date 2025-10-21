# 03 - Mô Hình Dữ Liệu (Data Model)

> **Version:** 1.0.0 | **Last Updated:** October 21, 2025

## 📖 Tổng Quan

Hệ thống sử dụng **MySQL 8.0+** làm database chính với **Eloquent ORM** của Laravel để quản lý data. Tất cả models đều extend từ `BaseModel` và sử dụng custom string IDs thay vì auto-increment.

## 🎨 Entity Relationship Diagram (ERD)

### Core System Entities

```mermaid
erDiagram
    users ||--o{ refresh_tokens : has
    users ||--|| roles : belongs_to
    users ||--o| customers : has_profile
    users ||--o| employees : has_profile
    
    roles ||--o{ users : has
    roles ||--o{ role_permissions : has
    permissions ||--o{ role_permissions : has
    
    customers ||--o{ reservations : makes
    customers ||--o{ table_sessions : participates
    
    employees ||--o{ table_sessions : serves
    employees ||--o{ employee_shifts : works
    employees ||--o{ payrolls : receives
    employees ||--o{ stock_losses : reports
    
    shifts ||--o{ employee_shifts : has
    
    dining_tables ||--o{ table_session_dining_table : used_in
    table_sessions ||--o{ table_session_dining_table : uses
    table_sessions ||--o{ table_session_reservations : links
    table_sessions ||--o{ orders : has
    table_sessions ||--o{ invoices : generates
    table_sessions ||--o| table_sessions : parent_of
    
    reservations ||--o{ table_session_reservations : linked_to
    
    orders ||--o{ order_items : contains
    order_items }o--|| dishes : orders
    order_items }o--|| employees : prepared_by
    
    dishes }o--|| dish_categories : categorized
    dishes ||--o{ menu_items : included_in
    dishes ||--o{ dish_ingredients : uses
    
    menus ||--o{ menu_items : contains
    
    ingredients }o--|| ingredient_categories : categorized
    ingredients ||--o{ dish_ingredients : used_in
    ingredients ||--o{ stock_import_details : received_in
    ingredients ||--o{ stock_export_details : used_in
    ingredients ||--o{ stock_losses : lost
    
    suppliers ||--o{ stock_imports : supplies
    stock_imports ||--o{ stock_import_details : has
    
    stock_exports ||--o{ stock_export_details : has
    
    invoices ||--o{ invoice_promotions : applies
    invoices ||--|| payments : paid_by
    
    promotions ||--o{ invoice_promotions : used_in
    
    payrolls ||--o{ payroll_items : contains
```

## 📊 Domain Models

### 1. **User Management Domain**

```mermaid
classDiagram
    class User {
        +string id
        +string name
        +string email
        +string password
        +string phone
        +string avatar
        +string role_id
        +bool is_active
        +bool email_verified
        +string auth_provider
        +role() Role
        +refreshTokens() RefreshToken[]
        +customerProfile() Customer
        +employeeProfile() Employee
    }
    
    class Role {
        +string id
        +string name
        +string code
        +string description
        +bool is_active
        +users() User[]
        +permissions() Permission[]
    }
    
    class Permission {
        +string id
        +string name
        +string code
        +string module
        +string description
        +bool is_active
        +roles() Role[]
    }
    
    class RefreshToken {
        +string id
        +string user_id
        +string token
        +datetime expire_at
        +string status
        +string device_fingerprint
        +user() User
    }
    
    User "1" --> "1" Role
    User "1" --> "*" RefreshToken
    Role "1" --> "*" Permission
```

### 2. **Customer & Employee Domain**

```mermaid
classDiagram
    class Customer {
        +string id
        +string user_id
        +string name
        +string phone
        +string email
        +int loyalty_points
        +user() User
        +reservations() Reservation[]
        +tableSessions() TableSession[]
    }
    
    class Employee {
        +string id
        +string user_id
        +string code
        +string position
        +decimal base_salary
        +date hire_date
        +string status
        +string image
        +user() User
        +shifts() EmployeeShift[]
        +tableSessions() TableSession[]
        +payrolls() Payroll[]
    }
    
    class Shift {
        +string id
        +string name
        +time start_time
        +time end_time
        +bool is_active
        +employeeShifts() EmployeeShift[]
    }
    
    class EmployeeShift {
        +string id
        +string employee_id
        +string shift_id
        +date work_date
        +datetime check_in
        +datetime check_out
        +string status
        +employee() Employee
        +shift() Shift
    }
    
    Customer "1" --> "1" User
    Employee "1" --> "1" User
    Employee "1" --> "*" EmployeeShift
    Shift "1" --> "*" EmployeeShift
```

### 3. **Table & Order Domain**

```mermaid
classDiagram
    class DiningTable {
        +string id
        +int table_number
        +int capacity
        +bool is_active
        +sessions() TableSession[]
    }
    
    class Reservation {
        +string id
        +string customer_id
        +datetime reserved_at
        +int number_of_people
        +int status
        +string notes
        +customer() Customer
        +tableSessions() TableSession[]
    }
    
    class TableSession {
        +string id
        +int type
        +int status
        +string parent_session_id
        +string merged_into_session_id
        +datetime started_at
        +datetime ended_at
        +string customer_id
        +string employee_id
        +diningTables() DiningTable[]
        +reservations() Reservation[]
        +orders() Order[]
        +invoices() Invoice[]
        +parentSession() TableSession
        +mergedIntoSession() TableSession
    }
    
    class Order {
        +string id
        +string table_session_id
        +int status
        +decimal total_amount
        +tableSession() TableSession
        +orderItems() OrderItem[]
    }
    
    class OrderItem {
        +string id
        +string order_id
        +string dish_id
        +int quantity
        +decimal price
        +decimal total_price
        +int status
        +string notes
        +string prepared_by
        +datetime served_at
        +order() Order
        +dish() Dish
        +preparedBy() Employee
    }
    
    DiningTable "1" --> "*" TableSession
    Reservation "1" --> "*" TableSession
    TableSession "1" --> "*" Order
    Order "1" --> "*" OrderItem
    OrderItem "*" --> "1" Dish
```

### 4. **Menu & Dish Domain**

```mermaid
classDiagram
    class DishCategory {
        +string id
        +string name
        +string desc
        +dishes() Dish[]
    }
    
    class Dish {
        +string id
        +string name
        +decimal price
        +string desc
        +string category_id
        +int cooking_time
        +string image
        +bool is_active
        +category() DishCategory
        +ingredients() Ingredient[]
        +menuItems() MenuItem[]
        +orderItems() OrderItem[]
    }
    
    class Menu {
        +string id
        +string name
        +string description
        +int version
        +bool is_active
        +menuItems() MenuItem[]
    }
    
    class MenuItem {
        +string id
        +string menu_id
        +string dish_id
        +decimal price
        +string notes
        +menu() Menu
        +dish() Dish
    }
    
    class DishIngredient {
        +string id
        +string dish_id
        +string ingredient_id
        +decimal quantity
        +string unit
        +dish() Dish
        +ingredient() Ingredient
    }
    
    DishCategory "1" --> "*" Dish
    Dish "1" --> "*" MenuItem
    Menu "1" --> "*" MenuItem
    Dish "1" --> "*" DishIngredient
```

### 5. **Inventory Domain**

```mermaid
classDiagram
    class IngredientCategory {
        +string id
        +string name
        +string desc
        +ingredients() Ingredient[]
    }
    
    class Ingredient {
        +string id
        +string name
        +string unit
        +decimal quantity
        +decimal reorder_level
        +string category_id
        +string image
        +category() IngredientCategory
        +dishes() Dish[]
        +stockImports() StockImportDetail[]
        +stockExports() StockExportDetail[]
        +stockLosses() StockLoss[]
    }
    
    class Supplier {
        +string id
        +string name
        +string contact_name
        +string phone
        +string email
        +string address
        +stockImports() StockImport[]
    }
    
    class StockImport {
        +string id
        +string supplier_id
        +date import_date
        +decimal total_amount
        +string notes
        +supplier() Supplier
        +details() StockImportDetail[]
    }
    
    class StockImportDetail {
        +string id
        +string stock_import_id
        +string ingredient_id
        +decimal quantity
        +decimal unit_price
        +decimal total_price
        +stockImport() StockImport
        +ingredient() Ingredient
    }
    
    class StockExport {
        +string id
        +date export_date
        +string purpose
        +string notes
        +details() StockExportDetail[]
    }
    
    class StockLoss {
        +string id
        +string ingredient_id
        +string employee_id
        +date loss_date
        +decimal quantity
        +string reason
        +ingredient() Ingredient
        +employee() Employee
    }
    
    IngredientCategory "1" --> "*" Ingredient
    Supplier "1" --> "*" StockImport
    StockImport "1" --> "*" StockImportDetail
    Ingredient "1" --> "*" StockImportDetail
    Ingredient "1" --> "*" StockLoss
```

### 6. **Billing & Payment Domain**

```mermaid
classDiagram
    class Invoice {
        +string id
        +string table_session_id
        +decimal subtotal
        +decimal tax_amount
        +decimal discount_amount
        +decimal total_amount
        +string status
        +string merge_from_invoices
        +string split_from_invoice_id
        +tableSession() TableSession
        +payment() Payment
        +promotions() Promotion[]
    }
    
    class Payment {
        +string id
        +string invoice_id
        +decimal amount
        +string payment_method
        +string status
        +datetime paid_at
        +invoice() Invoice
    }
    
    class Promotion {
        +string id
        +string name
        +string code
        +string type
        +decimal value
        +date start_date
        +date end_date
        +int usage_limit
        +int used_count
        +bool is_active
        +invoices() Invoice[]
    }
    
    class InvoicePromotion {
        +string id
        +string invoice_id
        +string promotion_id
        +decimal discount_amount
        +invoice() Invoice
        +promotion() Promotion
    }
    
    Invoice "1" --> "1" Payment
    Invoice "1" --> "*" InvoicePromotion
    Promotion "1" --> "*" InvoicePromotion
```

### 7. **Payroll Domain**

```mermaid
classDiagram
    class Payroll {
        +string id
        +string employee_id
        +date pay_period_start
        +date pay_period_end
        +decimal total_amount
        +string status
        +datetime paid_at
        +employee() Employee
        +items() PayrollItem[]
    }
    
    class PayrollItem {
        +string id
        +string payroll_id
        +string item_type
        +string description
        +decimal amount
        +payroll() Payroll
    }
    
    Payroll "1" --> "*" PayrollItem
    Payroll "*" --> "1" Employee
```

## 🔑 Key Design Decisions

### 1. **Custom String IDs**
- **Why**: Tránh expose sequential IDs, tăng security
- **Format**: Prefix + Random (e.g., `USR123ABC`, `ORD456DEF`)
- **Implementation**: Trait `HasCustomId` trong `BaseModel`

### 2. **Soft Deletes** (Optional)
- Sử dụng `deleted_at` column cho một số models
- Cho phép recovery data
- Không áp dụng cho tất cả tables

### 3. **Audit Fields**
- `created_at`, `updated_at` - Timestamps
- `created_by`, `updated_by` - User tracking
- **Implementation**: Trait `HasAuditFields`

### 4. **Status Enums**
- Sử dụng integers cho status (0, 1, 2, ...)
- Constants định nghĩa trong Model
- Dễ query và index

### 5. **Decimal for Money**
- Type: `decimal(18, 2)`
- Chính xác cho financial calculations
- Tránh floating-point errors

## 📝 Naming Conventions

### Database Tables
- **Singular Model**: `User` → **Plural Table**: `users`
- **Camel Case Model**: `DiningTable` → **Snake Case**: `dining_tables`
- **Pivot Tables**: `table_session_dining_table`, `invoice_promotions`

### Foreign Keys
- Pattern: `{table_singular}_id`
- Examples: `user_id`, `order_id`, `table_session_id`

### Indexes
- **Primary Key**: `id` (string, unique)
- **Foreign Keys**: Automatically indexed
- **Search Fields**: Add composite indexes
- **Unique Constraints**: email, code, etc.

## 🔗 Relationship Types

### One-to-One (1:1)
```php
// User has one Customer profile
User::hasOne(Customer::class)
Customer::belongsTo(User::class)
```

### One-to-Many (1:N)
```php
// Order has many OrderItems
Order::hasMany(OrderItem::class)
OrderItem::belongsTo(Order::class)
```

### Many-to-Many (N:N)
```php
// TableSession - DiningTable (pivot: table_session_dining_table)
TableSession::belongsToMany(DiningTable::class, 'table_session_dining_table')
DiningTable::belongsToMany(TableSession::class, 'table_session_dining_table')
```

### Polymorphic (Optional)
- Không sử dụng nhiều trong hệ thống hiện tại
- Có thể áp dụng cho: Comments, Attachments, Logs

## 📊 Data Integrity

### Cascade Rules
- **ON DELETE CASCADE**: Xóa parent → xóa children
  - Example: Order deleted → OrderItems deleted
- **ON DELETE SET NULL**: Xóa parent → set NULL cho foreign key
  - Example: Employee deleted → prepared_by = NULL
- **ON DELETE RESTRICT**: Không cho xóa nếu có references

### Constraints
- **UNIQUE**: email, code, token
- **NOT NULL**: Required fields
- **CHECK**: Status values, positive amounts
- **DEFAULT**: is_active, timestamps

## 🔄 Migration Strategy

### Version Control
- Migrations trong thư mục `database/migrations/`
- Naming: `YYYY_MM_DD_HHMMSS_description.php`
- Có thể rollback: `php artisan migrate:rollback`

### Seed Data
- Default roles & permissions
- Sample users
- Test data cho development

---

## 🔗 Related Documents

- **Previous**: [01-SYSTEM-OVERVIEW.md](./01-SYSTEM-OVERVIEW.md)
- **Next**: [04-DATABASE-SCHEMA.md](./04-DATABASE-SCHEMA.md)
- **See also**: [08-AUTHORIZATION.md](./08-AUTHORIZATION.md) - RBAC relationships

---

**📅 Last Updated:** October 21, 2025  
**👤 Author:** Development Team
