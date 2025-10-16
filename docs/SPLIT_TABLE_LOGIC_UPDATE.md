# Cập nhật Logic Tách Bàn (Split Table)

**Ngày cập nhật:** 16 tháng 10, 2025

## 📋 Tổng quan thay đổi

Cập nhật logic phương thức `splitTable()` trong `TableSessionService` để xử lý linh hoạt hơn khi tách bàn, cho phép thực hiện tách món **dù bàn nguồn có hoặc không có hóa đơn**.

## 🎯 Mục tiêu

1. **Cho phép tách bàn dù có hóa đơn:** Bàn nguồn có thể có hóa đơn đã khóa (Paid/Partially Paid) vẫn có thể tách món
2. **Không bắt buộc tạo invoice:** Khi chuyển món sang bàn mới, chỉ tạo invoice khi cần thiết
3. **Tách biệt logic:** Phân biệt rõ giữa xử lý order items và xử lý invoices

## 🔄 Các thay đổi chính

### 1. **Loại bỏ kiểm tra canBeSplit() cho source invoice**

**Trước đây:**
```php
if ($sourceInvoice && $sourceInvoice->canBeSplit()) {
    return [
        'success' => false,
        'message' => 'Source invoice cannot be split',
        'errors' => ['invoice' => ['Invoice must be Unpaid or Partially Paid']]
    ];
}
```

**Hiện tại:**
```php
// Chỉ validate remaining_amount nếu có invoice
if ($sourceInvoice) {
    $remainingAmount = $sourceInvoice->remaining_amount;
    if ($transferredTotal >= $remainingAmount) {
        return ['success' => false, ...];
    }
}
```

**Lý do:** Cho phép tách món bất kể trạng thái hóa đơn. Chỉ cần đảm bảo số tiền chuyển < số tiền còn lại.

### 2. **Không tự động tạo invoice cho bàn đích**

**Trước đây:**
```php
if ($targetInvoice) {
    // Cập nhật invoice có sẵn
} else {
    // TẠO invoice mới
    $targetInvoice = Invoice::create([...]);
}
```

**Hiện tại:**
```php
if ($targetInvoice) {
    // Cập nhật invoice có sẵn
}
// KHÔNG TẠO invoice mới nếu chưa có
// Chỉ chuyển order items
```

**Lý do:** Invoice chỉ được tạo khi khách hàng yêu cầu thanh toán, không phải tự động khi tách bàn.

### 3. **Xử lý audit trail linh hoạt**

**Hiện tại:**
```php
if ($sourceInvoice) {
    $sourceInvoice->update([
        'operation_type' => Invoice::OPERATION_SPLIT_TABLE,
        'transferred_item_ids' => $transferredItemIds,
        'operation_notes' => $note ?? "Split to session {$targetSession->id}",
        'operation_at' => now(),
        'operation_by' => $employeeId,
        // ... cập nhật số tiền
    ]);
}
```

**Lý do:** Chỉ ghi audit trail khi có invoice, tránh lỗi null reference.

### 4. **Response trả về có thể null**

**Hiện tại:**
```php
return [
    'success' => true,
    'data' => [
        'source_invoice' => $sourceInvoice ? $sourceInvoice->fresh() : null,
        'target_invoice' => $targetInvoice, // có thể null
        'summary' => [
            'source_remaining' => $sourceInvoice ? $sourceInvoice->fresh()->remaining_amount : null
        ]
    ]
];
```

**Lý do:** Phản ánh đúng thực tế - không phải lúc nào cũng có invoice.

## 📊 Flow mới

```
┌──────────────────────────────────────────────────────────────┐
│ 1. VALIDATE SOURCE SESSION                                   │
│    - Kiểm tra session tồn tại                               │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│ 2. TÍNH TOÁN GIÁ TRỊ MÓN TÁCH                               │
│    - Duyệt qua order_items                                  │
│    - Tính transferredTotal                                  │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│ 3. VALIDATE INVOICE (NẾU CÓ)                               │
│    ✓ Nếu có invoice:                                        │
│      - Kiểm tra transferredTotal < remaining_amount         │
│    ✓ Nếu không có invoice:                                  │
│      - Skip bước này                                        │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│ 4. CHUẨN BỊ TARGET SESSION                                  │
│    - Tìm hoặc tạo target session                           │
│    - Tìm hoặc tạo target order                             │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│ 5. DI CHUYỂN ORDER ITEMS                                    │
│    - Chuyển hoặc tách order items                          │
│    - Cập nhật quantity ở source                            │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│ 6. CẬP NHẬT INVOICES (NẾU CÓ)                              │
│    ✓ Source invoice (nếu có):                              │
│      - Giảm total_amount                                    │
│      - Cập nhật audit trail                                │
│    ✓ Target invoice (nếu có):                              │
│      - Tăng total_amount với weighted discount/tax         │
│    ✓ Nếu không có invoice:                                 │
│      - Skip bước này                                        │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│ 7. COMMIT & RETURN RESULT                                   │
│    - Log thông tin                                          │
│    - Trả về kết quả với invoices nullable                  │
└──────────────────────────────────────────────────────────────┘
```

## 🎮 Các trường hợp sử dụng

### Trường hợp 1: Tách bàn chưa có hóa đơn
```json
{
  "source_session_id": "TS001",
  "order_items": [
    {"order_item_id": "OI001", "quantity_to_transfer": 2}
  ],
  "target_dining_table_id": "DT005",
  "employee_id": "EMP001"
}
```

**Kết quả:**
- ✅ Chuyển 2 món từ TS001 sang bàn mới
- ✅ Không tạo invoice
- ✅ `source_invoice` = null, `target_invoice` = null

### Trường hợp 2: Tách bàn có hóa đơn chưa thanh toán
```json
{
  "source_session_id": "TS002",
  "order_items": [
    {"order_item_id": "OI005", "quantity_to_transfer": 1}
  ],
  "target_session_id": "TS003",
  "employee_id": "EMP001"
}
```

**Kết quả:**
- ✅ Chuyển 1 món từ TS002 sang TS003
- ✅ Giảm total_amount của source invoice
- ✅ Nếu TS003 có invoice thì cập nhật, không thì không tạo mới
- ✅ Lưu audit trail vào source invoice

### Trường hợp 3: Tách bàn có hóa đơn đã thanh toán một phần
```json
{
  "source_session_id": "TS004",
  "order_items": [
    {"order_item_id": "OI010", "quantity_to_transfer": 3}
  ],
  "target_dining_table_id": "DT008",
  "employee_id": "EMP001"
}
```

**Kết quả:**
- ✅ Validate: transferred_total < remaining_amount
- ✅ Chuyển 3 món sang bàn mới
- ✅ Cập nhật source invoice với audit trail
- ✅ Không tạo invoice cho bàn mới

## ⚠️ Lưu ý quan trọng

### 1. **Validation đơn giản hơn**
- ❌ Không còn kiểm tra `canBeSplit()` (trạng thái invoice)
- ✅ Chỉ kiểm tra `transferredTotal < remaining_amount` (nếu có invoice)

### 2. **Invoice không bắt buộc**
- ❌ Không tự động tạo invoice cho bàn đích
- ✅ Chỉ cập nhật nếu đã có sẵn
- ✅ Invoice được tạo riêng khi khách yêu cầu thanh toán

### 3. **Audit trail có điều kiện**
- ✅ Chỉ ghi audit trail khi có invoice
- ✅ Không gây lỗi khi invoice = null

### 4. **Response linh hoạt**
- ✅ `source_invoice` có thể null
- ✅ `target_invoice` có thể null
- ✅ `source_remaining` có thể null

## 📝 Cập nhật API Documentation

Đã cập nhật OpenAPI annotation trong `TableSessionController`:

```php
* @OA\Property(property="source_invoice", type="object", nullable=true, 
*              description="Null nếu chưa có invoice")
* @OA\Property(property="target_invoice", type="object", nullable=true, 
*              description="Null nếu chưa có invoice")
* @OA\Property(property="source_remaining", type="number", format="float", 
*              nullable=true)
```

## 🧪 Testing

### Test cases cần kiểm tra:

1. ✅ **Tách bàn không có invoice**
   - Source: Chưa tạo invoice
   - Action: Tách món
   - Expected: Chuyển món thành công, không tạo invoice

2. ✅ **Tách bàn có invoice chưa thanh toán**
   - Source: Invoice status = Unpaid
   - Action: Tách món
   - Expected: Cập nhật invoice, audit trail đầy đủ

3. ✅ **Tách bàn có invoice đã thanh toán một phần**
   - Source: Invoice status = Partially Paid
   - Action: Tách món (transferred < remaining)
   - Expected: Tách thành công

4. ✅ **Tách sang bàn đã có invoice**
   - Target: Đã có invoice
   - Action: Tách món
   - Expected: Cập nhật target invoice với weighted discount/tax

5. ✅ **Tách sang bàn chưa có invoice**
   - Target: Chưa có invoice
   - Action: Tách món
   - Expected: KHÔNG tạo invoice mới, chỉ chuyển món

## 🔗 Files thay đổi

1. **app/Services/TableSessionService.php**
   - Phương thức: `splitTable()`
   - Dòng: ~318-585

2. **app/Http/Controllers/Api/TableSessionController.php**
   - OpenAPI annotation cho endpoint `/split-table`
   - Dòng: ~1226-1325

## 📚 Tài liệu liên quan

- [CHANGELOG_SPLIT_MERGE.md](./CHANGELOG_SPLIT_MERGE.md) - Changelog chi tiết toàn bộ tính năng
- [SUMMARY_SPLIT_MERGE.md](./SUMMARY_SPLIT_MERGE.md) - Tổng quan 3 operations
- [Merge_Split_Table_Logic.md](./Merge_Split_Table_Logic.md) - Phân tích logic ban đầu

---

**Kết luận:** Logic mới linh hoạt hơn, tách biệt rõ ràng giữa xử lý order items và invoices, phù hợp với flow thực tế của nhà hàng.
