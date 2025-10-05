# System Improvements - Customer Management & Architecture Alignment

## Ngày thực hiện: October 4, 2025

## Tổng quan
Tài liệu này mô tả các cải tiến được thực hiện để hoàn thiện hệ thống Restaurant Management, bao gồm:
1. Bổ sung phương thức kiểm tra loại user
2. Xây dựng đầy đủ Customer Management module
3. Cải thiện RoleController và PermissionController để tuân thủ kiến trúc chuẩn

---

## 1. BỔ SUNG PHƯƠNG THỨC KIỂM TRA LOẠI USER

### File: `app/Models/User.php`

Đã thêm các phương thức sau để kiểm tra và xác định loại user:

#### 1.1. `isCustomer(): bool`
Kiểm tra xem user có phải là customer không.
```php
public function isCustomer(): bool
{
    return $this->customerProfile()->exists();
}
```

#### 1.2. `isEmployee(): bool`
Kiểm tra xem user có phải là employee không.
```php
public function isEmployee(): bool
{
    return $this->employeeProfile()->exists();
}
```

#### 1.3. `getUserType(): ?string`
Trả về loại user ('customer', 'employee', hoặc null).
```php
public function getUserType(): ?string
{
    // Kiểm tra relation đã load trước
    if ($this->relationLoaded('customerProfile') && $this->customerProfile) {
        return 'customer';
    }
    
    if ($this->relationLoaded('employeeProfile') && $this->employeeProfile) {
        return 'employee';
    }

    // Nếu chưa load, query database
    if ($this->customerProfile()->exists()) {
        return 'customer';
    }
    
    if ($this->employeeProfile()->exists()) {
        return 'employee';
    }
    
    return null;
}
```

#### 1.4. `getProfile()`
Trả về profile instance (Customer hoặc Employee).
```php
public function getProfile()
{
    if ($this->isCustomer()) {
        return $this->customerProfile;
    }
    
    if ($this->isEmployee()) {
        return $this->employeeProfile;
    }
    
    return null;
}
```

### Mục đích
- Dễ dàng phân biệt loại user trong business logic
- Tối ưu performance bằng cách kiểm tra relation đã load trước khi query
- Hỗ trợ việc authorization và routing dựa trên loại user
- Code dễ đọc và maintain hơn

---

## 2. CUSTOMER MANAGEMENT MODULE

### 2.1. Form Request Classes

Tạo đầy đủ các Form Request classes trong thư mục `app/Http/Requests/Customer/`:

#### a) `CustomerQueryRequest.php`
- Extends `BaseQueryRequest` cho pagination support
- Filters: `full_name`, `phone`, `gender`, `membership_level`, `user_id`
- Validation rules cho query parameters

#### b) `CustomerStoreRequest.php`
- Validation cho việc tạo customer mới
- Required fields: `full_name`, `phone`, `gender`, `membership_level`
- Optional fields: `address`, `user_id`
- Unique validation cho `phone` và `user_id`
- Custom error messages tiếng Anh

#### c) `CustomerUpdateRequest.php`
- Validation cho việc update customer
- Sử dụng `Rule::unique()->ignore($customerId)` để ignore record hiện tại
- Tất cả fields là optional (sometimes)
- Unique validation cho `phone` và `user_id`

#### d) `CustomerStatusRequest.php`
- Validation cho việc update membership level
- Required field: `membership_level` (1-4)

### 2.2. CustomerController

File: `app/Http/Controllers/Api/CustomerController.php`

Implement đầy đủ CRUD operations theo chuẩn kiến trúc hệ thống:

#### Endpoints

##### a) `GET /api/customers` - List customers
- **Middleware**: `auth:api`, `permission:customers.view`
- **Features**:
  - Pagination với `BaseQueryRequest`
  - Filters: full_name, phone, gender, membership_level, user_id
  - Eager load `user` relationship
  - Order by full_name
- **Response**: Standardized với items + meta pagination

##### b) `GET /api/customers/{id}` - Get customer detail
- **Middleware**: `auth:api`, `permission:customers.view`
- **Features**: Eager load user relationship
- **Error handling**: 404 if not found

##### c) `POST /api/customers` - Create customer
- **Middleware**: `auth:api`, `permission:customers.create`
- **Features**:
  - Validation qua `CustomerStoreRequest`
  - Auto set `created_by` = auth()->id()
  - Logging creation event
  - Try-catch error handling
- **Response**: 201 Created với customer data

##### d) `PUT /api/customers/{id}` - Update customer
- **Middleware**: `auth:api`, `permission:customers.edit`
- **Features**:
  - Validation qua `CustomerUpdateRequest`
  - Auto set `updated_by` = auth()->id()
  - Logging update event
  - Try-catch error handling
- **Response**: 200 OK với updated data

##### e) `PATCH /api/customers/{id}/membership` - Update membership
- **Middleware**: `auth:api`, `permission:customers.edit`
- **Features**: Quick update cho membership level only
- **Validation**: `CustomerStatusRequest`

##### f) `DELETE /api/customers/{id}` - Delete customer
- **Middleware**: `auth:api`, `permission:customers.delete`
- **Features**: 
  - Soft delete support (if enabled)
  - Logging delete event
  - Try-catch error handling

#### OpenAPI Documentation
- Đầy đủ `@OA\*` annotations cho Swagger
- Mô tả rõ ràng parameters, request body, responses
- Security scheme: bearerAuth

#### Code Quality
- ✅ Pagination support
- ✅ Filter support
- ✅ Validation với Form Requests
- ✅ Audit trail (created_by, updated_by)
- ✅ Logging
- ✅ Error handling
- ✅ Consistent API responses
- ✅ OpenAPI documentation

---

## 3. CẢI TIẾN ROLECONTROLLER

File: `app/Http/Controllers/Api/RoleController.php`

### 3.1. Thay đổi chính

#### a) Thêm RoleQueryRequest
- Tạo file `app/Http/Requests/Role/RoleQueryRequest.php`
- Extends `BaseQueryRequest`
- Filters: `name`, `is_active`

#### b) Refactor `index()` method
**TRƯỚC:**
```php
public function index(): JsonResponse
{
    $roles = Role::with('permissions')
        ->where('is_active', true)  // Hard-coded filter
        ->orderBy('name')
        ->get();  // No pagination!
    
    return $this->successResponse($roles, 'Roles retrieved successfully');
}
```

**SAU:**
```php
public function index(RoleQueryRequest $request): JsonResponse
{
    $query = Role::query()
        ->with('permissions')
        ->orderBy('name');

    $filters = $request->filters();

    // Filter by name
    if (!empty($filters['name'])) {
        $query->where('name', 'like', '%' . $filters['name'] . '%');
    }

    // Filter by is_active
    if (array_key_exists('is_active', $filters)) {
        $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }
    }

    // Pagination
    $paginator = $query->paginate($request->perPage(), ['*'], 'page', $request->page());
    $paginator->withQueryString();

    return $this->successResponse([
        'items' => $paginator->items(),
        'meta' => [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ],
    ], 'Roles retrieved successfully');
}
```

#### c) Cải thiện `store()` method
**Thêm:**
- `created_by` audit field
- Try-catch error handling
- Logging
- Proper error responses

#### d) Cải thiện `update()` method
**Thêm:**
- `updated_by` audit field
- Try-catch error handling
- Logging
- Better error handling

#### e) Cải thiện `destroy()` method
**Thêm:**
- Try-catch error handling
- Logging với `deleted_by`
- Proper error responses

### 3.2. Benefits
- ✅ Pagination cho performance tốt hơn
- ✅ Flexible filters
- ✅ Consistent với các controllers khác
- ✅ Better error handling
- ✅ Audit trail đầy đủ
- ✅ Logging cho debugging và monitoring

---

## 4. CẢI TIẾN PERMISSIONCONTROLLER

File: `app/Http/Controllers/Api/PermissionController.php`

### 4.1. Thay đổi chính

#### a) Thêm PermissionQueryRequest
- Tạo file `app/Http/Requests/Permission/PermissionQueryRequest.php`
- Extends `BaseQueryRequest`
- Filters: `name`, `code`, `is_active`, `search`

#### b) Refactor `index()` method

**TRƯỚC:**
```php
public function index(Request $request): JsonResponse
{
    $perPage = min($request->get('per_page', 15), 100);  // Manual handling
    $search = $request->get('search');
    
    $query = Permission::query();
    
    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('code', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%');
        });
    }
    
    $permissions = $query->orderBy('name')->paginate($perPage);
    
    return $this->successResponse($permissions, 'Permissions retrieved successfully');
}
```

**SAU:**
```php
public function index(PermissionQueryRequest $request): JsonResponse
{
    $query = Permission::query()->orderBy('code');

    $filters = $request->filters();

    // Search filter - searches across name, code, and description
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('code', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%');
        });
    }

    // Specific name filter
    if (!empty($filters['name'])) {
        $query->where('name', 'like', '%' . $filters['name'] . '%');
    }

    // Specific code filter
    if (!empty($filters['code'])) {
        $query->where('code', 'like', '%' . $filters['code'] . '%');
    }

    // Active status filter
    if (array_key_exists('is_active', $filters)) {
        $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }
    }

    $paginator = $query->paginate($request->perPage(), ['*'], 'page', $request->page());
    $paginator->withQueryString();

    return $this->successResponse([
        'items' => $paginator->items(),
        'meta' => [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ],
    ], 'Permissions retrieved successfully');
}
```

#### c) Cập nhật OpenAPI docs
- Làm gọn annotations
- Thêm filter parameters documentation
- Consistent với các controllers khác

### 4.2. Benefits
- ✅ Standardized pagination handling qua `BaseQueryRequest`
- ✅ Multiple filter options (search, name, code, is_active)
- ✅ Consistent response format
- ✅ Better query string handling
- ✅ Proper middleware declaration

---

## 5. KIẾN TRÚC VÀ PATTERNS

### 5.1. Consistent Architecture

Tất cả controllers hiện tại đều tuân thủ cùng một pattern:

```
Request → Middleware (auth + permission) → Controller → Form Request → 
Service (optional) → Model → Database → Response
```

### 5.2. Common Features

Mọi controller đều có:

1. **Pagination Support**
   - Sử dụng `BaseQueryRequest`
   - Configurable per_page với max limit
   - Standardized meta response

2. **Filter Support**
   - Dedicated `filters()` method trong Request classes
   - Flexible filter combinations
   - Proper handling của boolean filters

3. **Validation**
   - Dedicated Form Request classes
   - Custom error messages
   - Proper validation rules

4. **Error Handling**
   - Try-catch blocks
   - Consistent error responses
   - Proper HTTP status codes

5. **Logging**
   - Info logs cho successful operations
   - Error logs với stack traces
   - Context data cho debugging

6. **Audit Trail**
   - `created_by`, `updated_by` fields
   - Automatic tracking
   - Link back to User model

7. **OpenAPI Documentation**
   - Complete `@OA\*` annotations
   - Request/Response examples
   - Security requirements

8. **Middleware Protection**
   - `auth:api` cho authentication
   - `permission:xxx` cho authorization
   - Route-level protection

### 5.3. Response Format

Tất cả responses đều consistent:

```json
{
    "status": "success|error",
    "message": "Human readable message",
    "data": {
        "items": [...],
        "meta": {
            "current_page": 1,
            "per_page": 15,
            "total": 100,
            "last_page": 7
        }
    }
}
```

---

## 6. DATABASE RELATIONSHIPS

### 6.1. User Model Relationships

```
User (1) ──── (0..1) Customer
User (1) ──── (0..1) Employee
User (1) ──── (1) Role
```

**Key Points:**
- Một User có thể là Customer HOẶC Employee (không đồng thời cả hai theo design hiện tại)
- Mỗi User phải có một Role
- User có thể không có profile nào (pending user)

### 6.2. Validation Rules

#### Customer Creation
```php
'user_id' => 'nullable|string|exists:users,id|unique:customers,user_id'
```
- Cho phép tạo customer không liên kết User
- Nếu có user_id, phải tồn tại trong users table
- Một user chỉ có thể có 1 customer profile

#### Employee Creation (tương tự)
```php
'user_id' => 'nullable|string|exists:users,id|unique:employees,user_id'
```

---

## 7. PERMISSIONS REQUIRED

### 7.1. Customer Module
Cần thêm vào `config/permissions.php`:

```php
'customers' => [
    'name' => 'Customer Management',
    'description' => 'Permissions for customer management',
    'permissions' => [
        'customers.view' => [
            'name' => 'View Customers',
            'description' => 'Permission to view customer listings and details',
        ],
        'customers.create' => [
            'name' => 'Create Customers',
            'description' => 'Permission to create new customers',
        ],
        'customers.edit' => [
            'name' => 'Edit Customers',
            'description' => 'Permission to update customer information',
        ],
        'customers.delete' => [
            'name' => 'Delete Customers',
            'description' => 'Permission to delete customers',
        ],
    ],
],
```

### 7.2. Sync Permissions

Sau khi thêm permissions vào config, chạy:

```bash
php artisan rbac sync
```

---

## 8. TESTING

### 8.1. Manual Testing Checklist

#### Customer API
- [ ] GET /api/customers - List với pagination
- [ ] GET /api/customers?full_name=John - Filter by name
- [ ] GET /api/customers?membership_level=3 - Filter by level
- [ ] GET /api/customers/{id} - Get detail
- [ ] POST /api/customers - Create new
- [ ] PUT /api/customers/{id} - Update
- [ ] PATCH /api/customers/{id}/membership - Update membership
- [ ] DELETE /api/customers/{id} - Delete

#### Role API  
- [ ] GET /api/roles - List với pagination
- [ ] GET /api/roles?name=Admin - Filter by name
- [ ] GET /api/roles?is_active=true - Filter by status
- [ ] Verify audit trail (created_by, updated_by)

#### Permission API
- [ ] GET /api/permissions - List với pagination
- [ ] GET /api/permissions?search=user - Search filter
- [ ] GET /api/permissions?code=users.view - Code filter
- [ ] GET /api/permissions?is_active=true - Active filter

### 8.2. User Type Methods
```php
// Test trong tinker
$user = User::find('U-000001');
$user->isCustomer();      // Should return true/false
$user->isEmployee();      // Should return true/false
$user->getUserType();     // Should return 'customer'|'employee'|null
$user->getProfile();      // Should return Customer|Employee|null
```

---

## 9. SWAGGER DOCUMENTATION

### 9.1. Generate Documentation

```bash
php artisan l5-swagger:generate
```

### 9.2. Access Documentation

```
http://localhost:8000/swagger
```

### 9.3. New Endpoints Documented

- **Customers** tag: 6 endpoints
- **Roles** tag: Updated với pagination params
- **Permissions** tag: Updated với filter params

---

## 10. NEXT STEPS

### 10.1. Immediate Tasks

1. **Thêm permissions vào config**
   ```bash
   # Edit config/permissions.php
   # Add customers module permissions
   php artisan rbac sync
   ```

2. **Assign permissions to roles**
   - Qua Swagger UI
   - Hoặc qua database seeder

3. **Testing**
   - Manual testing qua Postman/Swagger
   - Verify all filters work correctly
   - Check audit trail

### 10.2. Future Enhancements

1. **Customer Features**
   - Customer order history
   - Loyalty points system
   - Customer analytics
   - Export customer data

2. **Performance**
   - Redis caching cho permissions
   - Query optimization
   - Eager loading optimization

3. **Advanced Filters**
   - Date range filters
   - Complex search queries
   - Saved filter presets

---

## 11. FILES CREATED/MODIFIED

### Created Files
```
app/Http/Requests/Customer/
├── CustomerQueryRequest.php
├── CustomerStoreRequest.php
├── CustomerUpdateRequest.php
└── CustomerStatusRequest.php

app/Http/Requests/Role/
└── RoleQueryRequest.php

app/Http/Requests/Permission/
└── PermissionQueryRequest.php

app/Http/Controllers/Api/
└── CustomerController.php

docs/development/
└── CUSTOMER_IMPLEMENTATION.md (this file)
```

### Modified Files
```
app/Models/User.php
├── + isCustomer()
├── + isEmployee()
├── + getUserType()
└── + getProfile()

app/Http/Controllers/Api/RoleController.php
├── Modified: index() - Added pagination & filters
├── Modified: store() - Added audit & logging
├── Modified: update() - Added audit & logging
└── Modified: destroy() - Added logging

app/Http/Controllers/Api/PermissionController.php
├── Modified: index() - Added pagination & filters
└── Modified: show() - Updated middleware
```

---

## 12. SUMMARY

### ✅ Completed
- Bổ sung 4 methods kiểm tra loại user trong User model
- Tạo đầy đủ Customer Management module với 6 endpoints
- Refactor RoleController với pagination và filters
- Refactor PermissionController với pagination và filters
- Tạo đầy đủ Form Request classes
- OpenAPI documentation đầy đủ
- Audit trail và logging
- Error handling chuẩn

### 🎯 Goals Achieved
- ✅ Consistent architecture across all controllers
- ✅ Proper pagination với BaseQueryRequest
- ✅ Flexible filtering system
- ✅ Standardized API responses
- ✅ Complete validation
- ✅ Audit trail tracking
- ✅ Error handling và logging
- ✅ OpenAPI documentation

### 💡 Key Improvements
1. **User Model**: Có thể dễ dàng kiểm tra và xác định loại user
2. **Customer Module**: Đầy đủ CRUD operations theo chuẩn enterprise
3. **Role & Permission**: Pagination và filters như các module khác
4. **Consistency**: Tất cả controllers đều follow cùng pattern
5. **Documentation**: Đầy đủ cho Swagger UI

---

**Tài liệu này sẽ được cập nhật khi có thêm thay đổi.**
