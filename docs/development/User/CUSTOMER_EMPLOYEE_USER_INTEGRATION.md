# Customer & Employee User Account Integration

## 📋 Tổng Quan

Document này mô tả các thay đổi được thực hiện để tích hợp **User Account** vào việc tạo và quản lý **Customer** và **Employee**. 

### Mục Đích
- Cho phép tạo Customer/Employee kèm theo tài khoản User (email, password) trong một transaction
- Đảm bảo tính nhất quán dữ liệu giữa User, Customer, Employee
- Hỗ trợ update thông tin User khi update Customer/Employee

---

## 🔄 Workflow Tạo Mới

### 1. Create Employee with User Account

**Endpoint**: `POST /api/employees`

**Request Body**:
```json
{
  "full_name": "John Smith",
  "phone": "0123456789",
  "gender": "male",
  "address": "123 Main St",
  "bank_account": "1234567890",
  "contract_type": 0,
  "position": "Chef",
  "base_salary": 2000.00,
  "hire_date": "2025-01-01",
  "is_active": true,
  
  // User Account (REQUIRED)
  "email": "john.smith@restaurant.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role_id": "R-123456"
}
```

**Process Flow**:
1. Validate request data (EmployeeStoreRequest)
2. Start DB transaction
3. Create User record:
   - Email: `john.smith@restaurant.com`
   - Password: hashed với `Hash::make()`
   - Role: được chỉ định qua `role_id`
   - Status: `User::STATUS_ACTIVE`
   - Created_by: Current authenticated user
4. Create Employee record với `user_id` từ User vừa tạo
5. Commit transaction
6. Return Employee với relationship `user` loaded

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Employee created successfully with user account",
  "data": {
    "id": "EMP-000001",
    "full_name": "John Smith",
    "user_id": "U-000001",
    "user": {
      "id": "U-000001",
      "email": "john.smith@restaurant.com",
      "role_id": "R-123456"
    }
  }
}
```

---

### 2. Create Customer with User Account

**Endpoint**: `POST /api/customers`

**Request Body**:
```json
{
  "full_name": "Jane Doe",
  "phone": "0987654321",
  "gender": "female",
  "address": "456 Customer Ave",
  "membership_level": 1,
  
  // User Account (REQUIRED)
  "email": "jane.doe@email.com",
  "password": "password123",
  "password_confirmation": "password123"
  
  // Note: role_id NOT needed - Customer role auto-assigned
}
```

**Process Flow**:
1. Validate request data (CustomerStoreRequest)
2. Start DB transaction
3. Get "Customer" role from database
4. Create User record:
   - Email: `jane.doe@email.com`
   - Password: hashed
   - Role: **Auto-assigned "Customer" role**
   - Status: `User::STATUS_ACTIVE`
   - Created_by: Current authenticated user
5. Create Customer record với `user_id` từ User vừa tạo
6. Commit transaction
7. Return Customer với relationship `user` loaded

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Customer created successfully with user account",
  "data": {
    "id": "CU-000001",
    "full_name": "Jane Doe",
    "membership_level": 1,
    "user_id": "U-000002",
    "user": {
      "id": "U-000002",
      "email": "jane.doe@email.com",
      "role": {
        "name": "Customer"
      }
    }
  }
}
```

---

## 🔄 Workflow Update

### 3. Update Employee with User Account

**Endpoint**: `PUT /api/employees/{id}`

**Request Body** (all fields optional):
```json
{
  // Employee fields
  "full_name": "John Smith Updated",
  "phone": "0123456789",
  "base_salary": 2500.00,
  
  // User Account updates (optional)
  "email": "john.updated@restaurant.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123",
  "role_id": "R-789012"
}
```

**Process Flow**:
1. Find Employee with User relationship
2. Start DB transaction
3. Update Employee fields (exclude email, password, role_id)
4. If User exists and email/password/role_id provided:
   - Update User email
   - Update User password (if provided)
   - Update User role_id (if provided)
   - Set updated_by to current user
5. Commit transaction
6. Return updated Employee với User loaded

---

### 4. Update Customer with User Account

**Endpoint**: `PUT /api/customers/{id}`

**Request Body** (all fields optional):
```json
{
  // Customer fields
  "full_name": "Jane Doe Updated",
  "membership_level": 2,
  
  // User Account updates (optional)
  "email": "jane.updated@email.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Process Flow**:
1. Find Customer with User relationship
2. Start DB transaction
3. Update Customer fields (exclude email, password)
4. If User exists and email/password provided:
   - Update User email
   - Update User password (if provided)
   - Set updated_by to current user
5. Commit transaction
6. Return updated Customer với User loaded

---

## 📁 Files Modified

### Form Requests

#### EmployeeStoreRequest.php
```php
// Added validation rules
'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
'password' => ['required', 'string', 'min:8', 'confirmed'],
'role_id' => ['required', 'string', 'exists:roles,id'],
'user_id' => ['nullable', 'string', 'exists:users,id', 'unique:employees,user_id'],
```

#### CustomerStoreRequest.php
```php
// Added validation rules
'email' => 'required|string|email|max:255|unique:users,email',
'password' => 'required|string|min:8|confirmed',
'user_id' => 'nullable|string|exists:users,id|unique:customers,user_id',
```

#### EmployeeUpdateRequest.php
```php
// Added validation rules for user account updates
'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email'],
'password' => ['nullable', 'string', 'min:8', 'confirmed'],
'role_id' => ['sometimes', 'string', 'exists:roles,id'],
```

#### CustomerUpdateRequest.php
```php
// Added validation rules for user account updates
'email' => 'sometimes|string|email|max:255|unique:users,email',
'password' => 'nullable|string|min:8|confirmed',
```

### Controllers

#### EmployeeController.php
- **Added imports**: `User`, `DB`, `Hash`
- **Modified `store()`**: 
  - Wrap trong DB transaction
  - Create User first (hoặc use existing user_id)
  - Create Employee với user_id
  - Enhanced error handling và logging
- **Modified `update()`**:
  - Wrap trong DB transaction
  - Separate employee data vs user data
  - Update User email/password/role_id if provided
  - Enhanced error handling

#### CustomerController.php
- **Added imports**: `Role`, `User`, `DB`, `Hash`
- **Modified `store()`**:
  - Wrap trong DB transaction
  - Auto-assign Customer role
  - Create User first (hoặc use existing user_id)
  - Create Customer với user_id
  - Enhanced error handling và logging
- **Modified `update()`**:
  - Wrap trong DB transaction
  - Separate customer data vs user data
  - Update User email/password if provided
  - Enhanced error handling

---

## 🔐 Security Features

### Password Handling
- ✅ Password được hash bằng `Hash::make()` trước khi lưu
- ✅ Validation yêu cầu `password_confirmation` để tránh typo
- ✅ Minimum 8 characters cho password
- ✅ Password chỉ update khi có giá trị mới (không override với empty string)

### Data Integrity
- ✅ DB Transaction đảm bảo User và Employee/Customer được tạo cùng nhau
- ✅ Rollback tự động nếu có lỗi ở bất kỳ bước nào
- ✅ Email unique validation trên users table
- ✅ user_id unique validation trên employees/customers table

### Audit Trail
- ✅ `created_by` được set khi tạo User mới
- ✅ `updated_by` được set khi update User
- ✅ Log đầy đủ cho mọi operation

---

## 🎯 Use Cases

### Use Case 1: HR tạo nhân viên mới với tài khoản đăng nhập
```json
POST /api/employees
{
  "full_name": "Alice Johnson",
  "email": "alice@restaurant.com",
  "password": "alice2025!",
  "password_confirmation": "alice2025!",
  "role_id": "R-STAFF",
  "contract_type": 0,
  "base_salary": 1800.00,
  "position": "Waiter"
}
```
→ Tạo cả User account và Employee profile trong 1 request

### Use Case 2: Customer tự đăng ký (hoặc staff tạo cho customer)
```json
POST /api/customers
{
  "full_name": "Bob Wilson",
  "phone": "0901234567",
  "email": "bob@email.com",
  "password": "bobsecure123",
  "password_confirmation": "bobsecure123",
  "gender": "male",
  "membership_level": 1
}
```
→ Tự động assign Customer role, tạo cả User và Customer profile

### Use Case 3: Update employee information và đổi role
```json
PUT /api/employees/EMP-000001
{
  "base_salary": 2200.00,
  "role_id": "R-MANAGER"
}
```
→ Cập nhật salary và promote lên Manager role

### Use Case 4: Customer đổi email và password
```json
PUT /api/customers/CU-000001
{
  "email": "newemail@email.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```
→ Update login credentials của customer

---

## ⚠️ Important Notes

### For Developers

1. **Required Fields for Creation**:
   - Employee: `email`, `password`, `password_confirmation`, `role_id`
   - Customer: `email`, `password`, `password_confirmation` (role_id auto-assigned)

2. **Optional user_id**:
   - Nếu cung cấp `user_id`, hệ thống sẽ link với User có sẵn
   - Nếu không cung cấp, hệ thống tự động tạo User mới
   - Validation đảm bảo `user_id` chưa được dùng cho Employee/Customer khác

3. **Transaction Safety**:
   - Mọi operation create/update đều wrap trong DB transaction
   - Nếu có lỗi, toàn bộ thay đổi sẽ rollback
   - Log errors với full stack trace để debug

4. **Email Uniqueness**:
   - Email phải unique trong `users` table
   - Validation sẽ reject nếu email đã tồn tại
   - Update email cũng check uniqueness (ignore current user)

### For API Consumers

1. **Password Confirmation**:
   - Luôn gửi cả `password` và `password_confirmation`
   - Hai field phải match exactly

2. **Role Selection**:
   - Với Employee: Phải chọn role (Staff, Manager, Kitchen, etc.)
   - Với Customer: Không cần chọn role (tự động assign "Customer")

3. **Update Behavior**:
   - Các field không gửi trong request sẽ giữ nguyên giá trị cũ
   - Password chỉ update khi có giá trị mới (có thể bỏ qua field này)
   - Email có thể update độc lập với password

---

## 🧪 Testing Examples

### Test 1: Create Employee với User Account
```bash
curl -X POST http://localhost:8000/api/employees \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "Test Employee",
    "email": "test.employee@restaurant.com",
    "password": "test12345",
    "password_confirmation": "test12345",
    "role_id": "R-STAFF",
    "contract_type": 0,
    "base_salary": 1500.00
  }'
```

### Test 2: Create Customer với User Account
```bash
curl -X POST http://localhost:8000/api/customers \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "Test Customer",
    "phone": "0123456789",
    "email": "test.customer@email.com",
    "password": "test12345",
    "password_confirmation": "test12345",
    "gender": "male",
    "membership_level": 1
  }'
```

### Test 3: Update Employee Email
```bash
curl -X PUT http://localhost:8000/api/employees/EMP-000001 \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newemail@restaurant.com"
  }'
```

### Test 4: Update Customer Password
```bash
curl -X PUT http://localhost:8000/api/customers/CU-000001 \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }'
```

---

## 📊 Database Relationships

```
┌─────────────┐
│   users     │
│─────────────│
│ id (PK)     │───┐
│ email       │   │
│ password    │   │
│ role_id (FK)│   │
│ status      │   │
│ created_by  │   │
│ updated_by  │   │
└─────────────┘   │
                  │
        ┌─────────┴─────────┐
        │                   │
        │                   │
┌───────▼───────┐  ┌────────▼────────┐
│  employees    │  │   customers     │
│───────────────│  │─────────────────│
│ id (PK)       │  │ id (PK)         │
│ user_id (FK)  │  │ user_id (FK)    │
│ full_name     │  │ full_name       │
│ position      │  │ membership_level│
│ base_salary   │  │ phone           │
│ contract_type │  │ gender          │
│ ...           │  │ ...             │
└───────────────┘  └─────────────────┘
```

**Constraints**:
- `employees.user_id` UNIQUE (One User → One Employee max)
- `customers.user_id` UNIQUE (One User → One Customer max)
- `users.email` UNIQUE (One email per system)

---

## ✅ Checklist for Implementation

- [x] Update EmployeeStoreRequest validation
- [x] Update CustomerStoreRequest validation
- [x] Update EmployeeUpdateRequest validation
- [x] Update CustomerUpdateRequest validation
- [x] Modify EmployeeController::store() with transaction
- [x] Modify CustomerController::store() with transaction
- [x] Modify EmployeeController::update() with user data handling
- [x] Modify CustomerController::update() with user data handling
- [x] Add proper error handling and logging
- [x] Update Swagger documentation
- [ ] Test all endpoints với valid data
- [ ] Test validation errors (missing password_confirmation, duplicate email, etc.)
- [ ] Test transaction rollback khi có lỗi
- [ ] Update Swagger documentation với `php artisan l5-swagger:generate`
- [ ] Test với production-like data

---

## 🚀 Next Steps

1. **Generate Swagger Docs**:
   ```bash
   php artisan l5-swagger:generate
   ```

2. **Test Endpoints**:
   - Test create employee với user account
   - Test create customer với user account
   - Test update operations với email/password changes
   - Test validation errors

3. **Optional Enhancements**:
   - Add email verification cho user mới tạo
   - Add password reset functionality
   - Add audit log cho user account changes
   - Add permission check cho việc update email/role

---

**Document Version**: 1.0  
**Last Updated**: 2025-10-04  
**Author**: GitHub Copilot  
