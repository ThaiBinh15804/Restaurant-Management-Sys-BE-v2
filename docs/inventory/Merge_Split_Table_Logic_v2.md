# 🔄 Chi tiết quy trình Gộp, Tách Bàn & Tách Hóa Đơn trong Nhà Hàng

## 🧩 Mục tiêu
Đảm bảo logic nghiệp vụ chính xác, minh bạch và có thể truy xuất trong các trường hợp:
1. **Gộp bàn (Merge Tables)**: Gộp nhiều bàn thành một
2. **Tách bàn (Split Table)**: Di chuyển món ăn giữa các bàn
3. **Tách hóa đơn (Split Invoice)**: Chia tiền thanh toán theo tỷ lệ %

---

# 🧮 Quy trình Gộp Bàn/Hóa Đơn (Merge Tables/Invoices)

## 🎯 Mục đích
Khi nhiều bàn thuộc cùng nhóm khách muốn thanh toán chung, hệ thống hợp nhất toàn bộ **order + invoice** về **một hóa đơn tổng duy nhất**.

## 👣 Các bước thực hiện

### Bước 1. Xác định đối tượng gộp
- Chọn bàn chính (target session)
- Chọn các bàn cần gộp (source sessions)

### Bước 2. Kiểm tra điều kiện
- Source sessions: Active hoặc Pending
- Invoices: Unpaid hoặc Partially Paid
- Không cho gộp invoice đã Paid hoàn toàn

### Bước 3. Thực hiện gộp
- Chuyển orders sang target session
- Tạo/cập nhật merged invoice
- Tính weighted discount và tax
- Chuyển payments sang merged invoice
- Sao chép promotions (không trùng)
- Đánh dấu source sessions là Merged

### Bước 4. Thanh toán
- Hiển thị tổng tiền đã thanh toán
- Thu phần còn lại
- Xuất hóa đơn tổng hợp

**📝 Chi tiết công thức:** Xem document gốc `Merge_Split_Table_Logic.md`

---

# 🍽️ Quy trình Tách Bàn (Split Table) - MỚI

## 🎯 Mục đích
Di chuyển món ăn từ bàn này sang bàn khác, phục vụ use case:
- Khách muốn chuyển bàn
- Tách nhóm khách thành nhiều bàn riêng
- Chia món cho các bàn khác nhau

## ✨ Đặc điểm chính
- ✅ DI CHUYỂN order_items giữa các table sessions
- ✅ Payment & discount giữ nguyên ở bàn gốc
- ✅ Món tách có GIÁ GỐC (không kế thừa discount)
- ✅ Bàn đích có thể là bàn mới hoặc bàn có sẵn

---

## 👣 Bước 1. Xác định đối tượng tách

Hệ thống hiển thị:
- Danh sách món tại bàn nguồn
- Tổng giá trị hóa đơn
- Số tiền đã thanh toán
- **Số tiền còn lại** (remaining_amount)

---

## 👣 Bước 2. Kiểm tra điều kiện hợp lệ

| Điều kiện | Kết quả |
|-----------|---------|
| Bàn nguồn đang Active/Pending | ✅ |
| Invoice chưa Paid hoàn toàn | ✅ |
| Có ít nhất 2 món (giữ lại ≥1 món) | ✅ |
| **remaining_amount > total_price_món_tách** | ✅ |

### 🔹 Công thức kiểm tra:
```typescript
const totalPaid = payments.filter(p => p.status === 'Completed').sum('amount');
const remaining = invoice.final_amount - totalPaid;
const selectedItemsTotal = selectedItems.sum('total_price');

if (selectedItemsTotal >= remaining) {
  throw new Error('Không thể tách: giá trị món tách phải < số tiền còn lại');
}
```

---

## 👣 Bước 3. Chọn món và bàn đích

### 3.1. Chọn món
- Chọn 1+ món
- Chọn số lượng cụ thể (tách 2/5 ly bia)
- Tính tổng theo GIÁ GỐC

### 3.2. Chọn bàn đích

**Option 1: Tạo bàn mới**
```typescript
newSession = {
  type: 'Split',
  status: 'Active',
  parent_session_id: sourceSession.id
}
```

**Option 2: Chuyển sang bàn có sẵn**
- Chọn bàn Active/Pending
- Món được thêm vào order hiện có

---

## 👣 Bước 4. Thực hiện tách

### 4.1. Di chuyển Order Items

**Tách toàn bộ số lượng:**
```sql
UPDATE order_items 
SET order_id = targetOrder.id
WHERE id IN (selectedItemIds)
```

**Tách một phần số lượng:**
```sql
-- Tạo item mới cho bàn đích (GIÁ GỐC)
INSERT INTO order_items (order_id, dish_id, quantity, price, total_price)
VALUES (targetOrder.id, dishId, qtySplit, priceOriginal, qtySplit * priceOriginal)

-- Giảm số lượng ở bàn nguồn
UPDATE order_items 
SET quantity = quantity - qtySplit,
    total_price = (quantity - qtySplit) * price
WHERE id = originalItemId
```

### 4.2. Cập nhật Invoice Bàn Nguồn

```typescript
const itemsTransferredTotal = transferredItems.sum('total_price');

invoice.total_amount -= itemsTransferredTotal;
// Giữ nguyên discount%, tax%
invoice.final_amount = invoice.total_amount 
  * (1 - invoice.discount/100) 
  * (1 + invoice.tax/100);

// Payment & Promotion KHÔNG THAY ĐỔI
```

### 4.3. Cập nhật/Tạo Invoice Bàn Đích

**Bàn mới:**
```typescript
newInvoice = {
  table_session_id: targetSession.id,
  total_amount: transferredItems.sum('total_price'),
  discount: 0,  // Không kế thừa discount
  tax: defaultTax,
  final_amount: total * (1 + tax/100),
  status: 'Unpaid'
}
```

**Bàn có sẵn:**
```typescript
targetInvoice.total_amount += transferredItems.sum('total_price');

// Tính weighted discount & tax
if (targetInvoice.total_amount > 0) {
  targetInvoice.discount = (oldDiscount * oldTotal) / newTotal;
  targetInvoice.tax = (oldTax * oldTotal + defaultTax * transferred) / newTotal;
}

targetInvoice.final_amount = targetInvoice.total_amount 
  * (1 - targetInvoice.discount/100) 
  * (1 + targetInvoice.tax/100);
```

---

## 📊 Ví dụ: Tách 1 phở từ bàn A sang bàn B

**Trước:**
| Bàn | Món | SL | Giá | Tổng | Disc | Tax | Final | Paid | Remaining |
|-----|-----|-----|-----|------|------|-----|-------|------|-----------|
| A | Phở | 3 | 50k | 150k | 10% | 10% | 148.5k | 50k | 98.5k |
| A | Cơm | 2 | 40k | 80k |  |  |  |  |  |
| **A Total** |  |  |  | **230k** | **10%** | **10%** | **228.15k** | **50k** | **178.15k** |

✅ Kiểm tra: `178.15k > 50k` → OK

**Sau:**
| Bàn | Món | SL | Giá | Tổng | Disc | Tax | Final | Paid | Remaining |
|-----|-----|-----|-----|------|------|-----|-------|------|-----------|
| A | Phở | 2 | 50k | 100k | 10% | 10% | 99k | 50k | 49k |
| A | Cơm | 2 | 40k | 80k |  |  |  |  |  |
| **A** |  |  |  | **180k** | **10%** | **10%** | **178.2k** | **50k** | **128.2k** |
| B | Phở | 1 | 50k | 50k | 0% | 10% | 55k | 0k | 55k |

✅ Kết quả:
- Payment 50k vẫn ở bàn A ✅
- Bàn B không có discount (giá gốc) ✅
- Bàn A còn đủ tiền: 128.2k > 0 ✅

---

## 🔒 Lưu ý - Tách Bàn

1. `remaining_amount > total_price_món_tách`
2. Bàn nguồn phải còn ≥1 món
3. Món tách = giá gốc, không kế thừa discount
4. Bàn đích có thể áp dụng promotion mới

**Audit Log:**
```
"TS001 split 1x Phở (50k) to TS002 at 2025-10-16 by EMP001
 Source: 128.2k | Target: 55k"
```

---

# 💳 Quy trình Tách Hóa Đơn (Split Invoice) - CẢI TIẾN

## 🎯 Mục đích
Chia tiền thanh toán theo tỷ lệ % của **số tiền còn lại**, phục vụ use case:
- Khách thanh toán riêng theo %
- Chia đều hóa đơn cho nhiều người

## ✨ Đặc điểm chính
- ✅ Chia theo % của **remaining_amount**
- ✅ KHÔNG di chuyển order_items
- ✅ Payment & discount giữ nguyên ở invoice gốc
- ✅ Phân bổ chính xác discount%, tax%

---

## 👣 Bước 1. Khởi tạo

Hệ thống hiển thị:
- Tổng giá trị hóa đơn
- **Số tiền đã thanh toán** (total_paid)
- **Số tiền còn lại** (remaining_amount)
- Discount%, tax% hiện tại

---

## 👣 Bước 2. Chọn tỷ lệ tách

Nhân viên nhập **% cần tách** (VD: 40%)

### 🔹 Công thức:
```typescript
const totalPaid = payments.filter(p => p.status === 'Completed').sum('amount');
const remaining = invoice.final_amount - totalPaid;
const splitPercentage = 40; // %

// Số tiền tách (sau discount & tax)
const splitFinal = remaining * (splitPercentage / 100);

// Tính ngược total_amount (trước discount & tax)
const splitTotal = splitFinal / (
  (1 - invoice.discount/100) * (1 + invoice.tax/100)
);
```

**Preview:**
```
Invoice gốc: Remaining = X
Invoice mới: Remaining = Y
Tổng: X + Y = remaining ban đầu ✅
```

---

## 👣 Bước 3. Tạo invoice mới

```typescript
childInvoice = {
  parent_invoice_id: parentInvoice.id,
  table_session_id: parentInvoice.table_session_id,
  total_amount: splitTotal,
  discount: parentInvoice.discount,  // Giữ nguyên %
  tax: parentInvoice.tax,            // Giữ nguyên %
  final_amount: splitFinal,
  status: 'Unpaid'
}
```

**🔑 Lưu ý**: Order items KHÔNG di chuyển

---

## 👣 Bước 4. Cập nhật invoice gốc

```typescript
parentInvoice.total_amount -= splitTotal;

// Giữ nguyên discount%, tax%
parentInvoice.final_amount = parentInvoice.total_amount 
  * (1 - parentInvoice.discount/100) 
  * (1 + parentInvoice.tax/100);

// Payment KHÔNG THAY ĐỔI
const remainingNew = parentInvoice.final_amount - totalPaid;

// Cập nhật status
if (remainingNew <= 0) parentInvoice.status = 'Paid';
else if (totalPaid > 0) parentInvoice.status = 'Partially Paid';
else parentInvoice.status = 'Unpaid';
```

### ✅ Đảm bảo:
```typescript
parent.total_amount + child.total_amount === original.total_amount ✅
parent.final_amount + child.final_amount === original.final_amount - totalPaid ✅
```

---

## 📊 Ví dụ: Tách 40% hóa đơn

**Trước:**
| Invoice | Total | Disc | Tax | Final | Paid | Remaining |
|---------|-------|------|-----|-------|------|-----------|
| INV001 | 1,000,000 | 10% | 10% | 990,000 | 300,000 | **690,000** |

**Tính toán:**
```
splitPercentage = 40%
splitFinal = 690,000 * 40% = 276,000

splitTotal = 276,000 / ((1-0.1) * (1+0.1))
           = 276,000 / 0.99
           ≈ 278,788
```

**Sau:**
| Invoice | Total | Disc | Tax | Final | Paid | Remaining |
|---------|-------|------|-----|-------|------|-----------|
| INV001 | 721,212 | 10% | 10% | 714,000 | 300,000 | **414,000** |
| INV001-A | 278,788 | 10% | 10% | 276,000 | 0 | **276,000** |
| **Tổng** | **1,000,000** | **10%** | **10%** | **990,000** | **300,000** | **690,000** ✅ |

✅ Kiểm tra:
- Total không đổi: 721,212 + 278,788 = 1,000,000 ✅
- Remaining không đổi: 414,000 + 276,000 = 690,000 ✅
- Payment vẫn ở INV001 ✅

---

## 🔒 Lưu ý - Tách Hóa Đơn

1. Chỉ tách từ **remaining_amount**
2. Invoice phải chưa Paid hoàn toàn
3. 0 < split% < 100
4. **Order items KHÔNG di chuyển**

**Audit Log:**
```
"INV001 split 40% (276k) into INV001-A at 2025-10-16 by EMP001
 Parent: 414k | Child: 276k"
```

---

# 🔄 So sánh 3 chức năng

| Tiêu chí | Gộp Bàn | Tách Bàn | Tách Hóa Đơn |
|----------|---------|----------|--------------|
| **Mục đích** | Gộp nhiều bàn | Di chuyển món | Chia tiền thanh toán |
| **Order items** | Gộp vào target | DI CHUYỂN | KHÔNG di chuyển |
| **Payment** | Chuyển sang merged | Giữ ở bàn gốc | Giữ ở invoice gốc |
| **Discount** | Weighted average | Không kế thừa | Giữ nguyên % |
| **Điều kiện** | Unpaid/Partially Paid | `remaining > giá món` | `remaining > 0` |
| **Use case** | Nhóm khách gộp bàn | Chuyển bàn, tách nhóm | Thanh toán riêng theo % |

---

# ✅ Tổng kết

## Nguyên tắc chung:
1. **Minh bạch tài chính**: Luôn trace được payment và discount
2. **Không mất dữ liệu**: Lưu lịch sử đầy đủ
3. **Tính toán chính xác**: Sử dụng weighted average cho discount/tax
4. **Audit trail**: Ghi log mọi thao tác

## Công thức quan trọng:
```typescript
// Weighted discount/tax
weighted_value = Σ(value_i * amount_i) / total_amount

// Final amount
final = total * (1 - discount/100) * (1 + tax/100)

// Remaining
remaining = final_amount - sum(completed_payments)
```

**📘 Kết luận**: Ba chức năng này tạo thành hệ thống quản lý bàn linh hoạt, đáp ứng đa dạng nhu cầu thực tế của nhà hàng.
