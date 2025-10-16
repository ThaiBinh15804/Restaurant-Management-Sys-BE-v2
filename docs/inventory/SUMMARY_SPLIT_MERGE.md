# ✅ Tóm tắt Triển khai - Gộp/Tách Bàn & Hóa Đơn

## 🎯 Mục tiêu hoàn thành
Triển khai đầy đủ 3 chức năng với audit trail hoàn chỉnh:
1. **Merge Tables** (Gộp bàn) - ✅ Đã cải tiến
2. **Split Invoice** (Tách hóa đơn theo %) - ✅ Đã sửa logic
3. **Split Table** (Tách bàn - di chuyển món) - ✅ Mới 100%

---

## 📦 Files đã thay đổi

### 1. Database
- ✅ **Migration**: `2025_10_16_105349_add_audit_fields_to_invoices_table.php`
  - Thêm: `operation_type`, `source_invoice_ids`, `split_percentage`, `transferred_item_ids`
  - Thêm: `operation_notes`, `operation_at`, `operation_by`
  - Thêm 4 indexes để query nhanh

### 2. Model
- ✅ **Invoice.php**: 
  - Constants: `OPERATION_MERGE`, `OPERATION_SPLIT_INVOICE`, `OPERATION_SPLIT_TABLE`
  - Scopes: `mergedInvoices()`, `splitInvoices()`, `byOperationType()`, `operationBetween()`
  - Accessors: `audit_trail`, `source_invoices`, `operation_type_label`

### 3. Service Layer
- ✅ **TableSessionService.php**:
  - `mergeTables()`: Thêm audit trail tracking
  - `splitInvoice()`: ⚠️ **THAY ĐỔI HOÀN TOÀN** - từ split by items → split by %
  - `splitTable()`: 🆕 **MỚI** - di chuyển món giữa bàn

### 4. Validation
- ✅ **SplitInvoiceRequest.php**: Validation mới cho % thay vì order_items
- ✅ **SplitTableRequest.php**: 🆕 **MỚI** - validation cho di chuyển món

### 5. Controller
- ✅ **TableSessionController.php**: 
  - Endpoint mới: `POST /split-table`
  - OpenAPI documentation đầy đủ

---

## 🔄 Logic của 3 chức năng

### 1️⃣ Merge Tables (Gộp bàn)
```
Input: [TS001, TS002] → TS003
Process:
  - Di chuyển orders → target
  - Tính weighted discount & tax
  - Chuyển payments → merged invoice
  - Lưu source_invoice_ids = [IN001, IN002]
Output: 1 merged invoice với operation_type = 'merge'
```

### 2️⃣ Split Invoice (Tách hóa đơn theo %)
```
Input: IN001 (remaining: 150k), splits: [40%, 30%]
Process:
  - Child 1: 60k (40% × 150k)
  - Child 2: 45k (30% × 150k)
  - Parent: 45k (còn lại 30%)
  - Giữ nguyên discount%, tax%
  - KHÔNG di chuyển order_items
Output: 2 child invoices với operation_type = 'split_invoice'
```

### 3️⃣ Split Table (Tách bàn - di chuyển món)
```
Input: TS001, items: [1×Phở@50k], target: DT005
Validate: remaining (178k) > transferred (50k) ✅
Process:
  - DI CHUYỂN order_items → target
  - Source: total -= 50k (giữ discount%)
  - Target: total = 50k (discount = 0%)
  - Payment giữ ở source
  - Lưu transferred_item_ids = [OI001]
Output: Target invoice với operation_type = 'split_table'
```

---

## 📊 So sánh nhanh

|  | Merge | Split Invoice | Split Table |
|--|-------|---------------|-------------|
| **Move items?** | ✅ Có | ❌ Không | ✅ Có |
| **Move payment?** | ✅ Có | ❌ Không | ❌ Không |
| **Discount** | Weighted | Giữ % | Bàn mới = 0% |
| **Validation** | Active/Pending | % < 100% | remaining > transferred |

---

## 🚀 API Endpoints

### Merge Tables
```bash
POST /api/table-sessions/merge
{
  "source_session_ids": ["TS001", "TS002"],
  "target_session_id": "TS003",
  "employee_id": "EMP001"
}
```

### Split Invoice (by %)
```bash
POST /api/table-sessions/split-invoice
{
  "invoice_id": "IN001",
  "splits": [
    { "percentage": 40, "note": "Khách A" },
    { "percentage": 30, "note": "Khách B" }
  ],
  "employee_id": "EMP001"
}
```

### Split Table (move dishes) - 🆕 MỚI
```bash
POST /api/table-sessions/split-table
{
  "source_session_id": "TS001",
  "order_items": [
    { "order_item_id": "OI001", "quantity_to_transfer": 2 }
  ],
  "target_session_id": "TS002",  // HOẶC target_dining_table_id
  "note": "Khách tách bàn",
  "employee_id": "EMP001"
}
```

---

## 🔍 Audit Trail

### Truy vết nhanh:
```php
// Invoice gộp từ những invoice nào?
$merged->source_invoice_ids; // [IN001, IN002, IN003]

// Invoice tách từ invoice nào?
$child->parent_invoice_id; // IN001

// Tách bao nhiêu %?
$child->split_percentage; // 40.00

// Món nào được chuyển?
$invoice->transferred_item_ids; // [OI001, OI002]

// Ai làm? Khi nào?
$invoice->operation_by; // EMP001
$invoice->operation_at; // 2025-10-16 10:53:49

// Xem đầy đủ
$invoice->audit_trail; // Array với tất cả thông tin
```

### Query hữu ích:
```php
// Tất cả invoice merge trong tháng
Invoice::mergedInvoices()->operationBetween($start, $end)->get();

// Tất cả invoice split
Invoice::splitInvoices()->get();

// Theo loại
Invoice::byOperationType(Invoice::OPERATION_SPLIT_TABLE)->get();
```

---

## ⚠️ Lưu ý quan trọng

1. **Split Invoice ≠ Split Table**
   - Split Invoice: Chia tiền (%), KHÔNG di chuyển món
   - Split Table: Di chuyển món, GIÁ GỐC (không kế thừa discount)

2. **Validation khác nhau:**
   - Split Invoice: Tổng % < 100%
   - Split Table: remaining_amount > transferred_total

3. **Payment luôn ở source/parent** (trừ Merge)

4. **Discount:**
   - Merge: Weighted average
   - Split Invoice: Giữ nguyên %
   - Split Table: Bàn mới = 0%

---

## ✅ Checklist

- [x] Migration đã chạy
- [x] Invoice Model cập nhật
- [x] Service Layer hoàn chỉnh (3 methods)
- [x] Request Validators (2 mới, 1 sửa)
- [x] Controller endpoint mới
- [x] OpenAPI documentation
- [x] Audit trail đầy đủ

---

## 📚 Tài liệu

- **Chi tiết**: `docs/CHANGELOG_SPLIT_MERGE.md`
- **Business Logic**: `docs/Merge_Split_Table_Logic_v2.md`
- **API Docs**: Swagger UI

---

**Status**: ✅ **HOÀN THÀNH** - Sẵn sàng testing!
