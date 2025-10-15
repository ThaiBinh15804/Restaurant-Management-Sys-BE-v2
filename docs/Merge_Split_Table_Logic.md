# 🔄 Chi tiết quy trình Gộp & Tách Hóa Đơn trong Nhà Hàng

## 🧩 Mục tiêu
Đảm bảo logic nghiệp vụ chính xác, minh bạch và có thể truy xuất trong các trường hợp gộp bàn, tách bàn và các hóa đơn liên quan.

---

# 🧮 Quy trình Gộp Hóa Đơn (Merge Invoices)

## 🎯 Mục đích
Khi nhiều bàn hoặc nhiều hóa đơn thuộc cùng một nhóm khách muốn thanh toán chung, hệ thống cần hợp nhất toàn bộ **order + invoice** về **một hóa đơn tổng duy nhất**, đảm bảo:
- Không mất dữ liệu order.
- Không trùng lặp dòng món.
- Bảo toàn lịch sử thanh toán, khuyến mãi, thuế, giảm giá.

---

## 👣 Bước 1. Xác định đối tượng gộp
- Nhân viên chọn bàn chính (bàn đích).
- Chọn các bàn khác cần gộp (có thể đã có session đang hoạt động).
- Hệ thống xác định tất cả **table_session** đang mở của các bàn đó.

Mỗi bàn có thể có:
- Một **order đang mở**.
- Một **invoice** đang mở hoặc đã thanh toán một phần.
- Một số **payment** đã hoàn thành.

---

## 👣 Bước 2. Kiểm tra điều kiện hợp lệ

| Điều kiện | Mô tả | Kết quả |
|------------|--------|----------|
| Hóa đơn đã Paid hoàn toàn | Không được gộp | ❌ Từ chối |
| Hóa đơn đang Paying | Không được gộp | ❌ Từ chối |
| Hóa đơn đang Open hoặc Partially Paid | Cho phép gộp | ✅ OK |
| Bàn đang Active hoặc Pending | Cho phép gộp | ✅ OK |
| Bàn đã Completed | Không cho phép gộp | ❌ |

---

## 👣 Bước 3. Thực hiện gộp

### 3.1. Tạo phiên gộp
- Hệ thống tạo **session mới (type = Merge)**.
- Gắn tất cả bàn con vào session chính.

### 3.2. Chuyển dữ liệu order
- Gộp toàn bộ `order` của bàn con → sang session chính.
- Giữ nguyên chi tiết `order_item`, không xoá dữ liệu.
- Các món trùng chỉ cộng dồn khi hiển thị, không gộp dòng dữ liệu.

### 3.3. Hợp nhất hóa đơn

Giả sử có 3 hóa đơn:
- **Invoice A**: 1.000.000đ, giảm 5%, thuế 10%, thanh toán 400.000đ.
- **Invoice B**: 800.000đ, không giảm, chưa thanh toán.
- **Invoice C**: 1.200.000đ, giảm 10%, thuế 8%, chưa thanh toán.

#### ➤ Công thức gộp chính xác:

**1. Tổng tiền hàng (subtotal)**  
```
subtotal_total = sum(invoice_i.total_amount for all merged invoices)
```

**2. Tỷ lệ giảm giá (weighted_discount)**  
```
weighted_discount = (Σ (invoice_i.discount% * invoice_i.total_amount)) / subtotal_total
```

**3. Tỷ lệ thuế (weighted_tax)**  
```
weighted_tax = (Σ (invoice_i.tax% * invoice_i.total_amount)) / subtotal_total
```

**4. Thành tiền cuối (final_amount)**  
```
final_amount = subtotal_total * (1 - weighted_discount/100) * (1 + weighted_tax/100)
```

**5. Trạng thái thanh toán**  
```
paid_total = sum(all completed payments from merged invoices)
remaining_amount = final_amount - paid_total

if remaining_amount <= 0 → Paid
elif paid_total > 0 → Partially Paid
else → Unpaid
```

**6. Khuyến mãi (Promotion)**  
- Giữ tất cả promotion đã áp dụng ở các hóa đơn con.
- Không áp dụng trùng mã giảm giá.
- Ghi nhận lại toàn bộ `invoice_promotion` cho hóa đơn tổng.


## 👣 Bước 4. Sau khi gộp
- Chỉ hiển thị **1 hóa đơn tổng duy nhất** tại bàn chính.
- Các hóa đơn con:
  - `status = Merged`  -- Bổ sung trạng thái nếu chưa có
  - Không thể sửa, chỉ xem.
- Các payment con:
  - Tồn tại sau khi merged, liên kết với `merged_invoice_id` để đảm bảo lịch sử thanh toán.
  - Tồn tại trước khi merged, giữ nguyên liên kết tới invoice cũ

---

## 👣 Bước 5. Thanh toán
- Khi khách thanh toán tại bàn chính:
  - Hệ thống hiển thị tổng tiền đã thanh toán trước (nếu có).
  - Nhân viên thu phần **còn lại (remaining_amount)**.
  - Xuất **1 hóa đơn tổng hợp** thể hiện:
    - Tổng cộng món.
    - Giảm giá.
    - Thuế.
    - Bàn con đã gộp.
    - Tổng tiền đã thanh toán.
    - Số tiền còn phải trả.

---


# 💳 Quy trình Tách Hóa Đơn (Split Invoice)

---

## 🎯 Mục đích
Khi một nhóm khách trong cùng bàn muốn **thanh toán riêng**, hoặc **chia hóa đơn theo phần ăn**, hệ thống cần hỗ trợ tách hóa đơn để đảm bảo tính minh bạch, chính xác và dễ đối soát.

---

## 👣 Bước 1. Khởi tạo hành động tách

- Nhân viên chọn **bàn đang phục vụ**.
- Chọn chức năng **“Tách hóa đơn”** từ giao diện POS.
- Hệ thống hiển thị:
  - Danh sách **các món hiện có** (order items).
  - Tổng giá trị hóa đơn hiện tại.
  - Tình trạng thanh toán (nếu có partial payment trước đó).

> ⚙️ Mục tiêu: Xác định chính xác hóa đơn đang được tách và phạm vi dữ liệu liên quan.

---

## 👣 Bước 2. Chọn món hoặc số lượng cần tách

Nhân viên có thể chọn **2 cách thực hiện tách**:

### 🔹 Option 1: Tách theo món cụ thể
- Chọn 1 hoặc nhiều món trong danh sách.
- Có thể chọn **số lượng cụ thể** (VD: 3/5 ly bia).
- Hệ thống tự động tính tổng giá trị tạm tính của phần tách.

### 🔹 Option 2: Tách theo tỷ lệ phần trăm
- Nhập tỷ lệ % cần tách (VD: 40% của hóa đơn tổng).
- Hệ thống tự động phân bổ số tiền và món tương ứng.
- Cho phép nhân viên điều chỉnh thủ công sau đó.

> 💡 Hệ thống hiển thị **giá trị tạm tính**, bao gồm:
> - Tổng tiền phần tách
> - Giảm giá (nếu có)
> - Thuế dự kiến
> - Tổng phải trả (ước tính)

---

## 👣 Bước 3. Tạo hóa đơn mới

- Hệ thống tạo **một bản ghi invoice mới**, cùng `table_session_id` với hóa đơn gốc.
- Các dòng `order_item` được chọn:
  - Chuyển sang hóa đơn mới.
  - Nếu chỉ tách một phần → hệ thống **tạo dòng mới** tương ứng với số lượng tách.
  - Giữ nguyên `price` gốc để đảm bảo thống nhất giá.

- Cho phép áp dụng:
  - **Giảm giá riêng** cho hóa đơn tách.
  - **Thuế riêng** (nếu theo chính sách khác nhau).

- Hóa đơn mới có thể được gắn quan hệ:
  ```
  parent_invoice_id = <invoice_gốc>
  ```

> 🧾 Ví dụ:
> - INV001 (gốc): tổng 1.000.000đ  
> - Nhân viên tách 2 món trị giá 300.000đ → tạo INV001-A (300.000đ)
> - INV001 cập nhật còn 700.000đ

---

## 👣 Bước 4. Cập nhật thanh toán

- Mỗi hóa đơn con có thể thanh toán **riêng biệt**.

### 🔹 Công thức tính trạng thái thanh toán:
```plaintext
total_paid = sum(payments.amount where invoice_id = current_invoice.id)
remaining = invoice.final_amount - total_paid

if remaining == 0 → status = Paid
elif 0 < remaining < final_amount → status = Partially Paid
else → status = Unpaid
```

- Khi tách, **hóa đơn gốc giảm tổng tiền** tương ứng với phần tách ra:
  - `total_amount_new = total_amount_old - amount_split`
  - Cập nhật lại `discount`, `tax`, `final_amount` theo tỷ lệ tương ứng.

- Nếu hóa đơn gốc đã có **payment partial trước đó**:
  - Phần đã thanh toán không bị ảnh hưởng.
  - Hệ thống chỉ cho phép tách trên **phần chưa thanh toán**.

> ⚠️ Mọi giao dịch payment vẫn giữ nguyên theo từng invoice gốc để đảm bảo đối soát chính xác.

---

## 👣 Bước 5. Minh bạch & Đối soát

- Ghi log chi tiết mỗi lần tách:
  ```plaintext
  "Invoice INV001 split into INV001-A and INV001-B at 2025-10-15 by Employee E005"
  ```

- Lưu thông tin liên kết:
  - `parent_invoice_id` trên các hóa đơn con.
  - Ghi lại:
    - Danh sách món đã tách.
    - Số lượng gốc / số lượng còn lại.
    - Thời gian thao tác.
    - Nhân viên thực hiện.

- Lịch sử hóa đơn hiển thị:
  - INV001 (gốc): “Đã tách thành INV001-A, INV001-B”.
  - INV001-A / B: “Được tách từ INV001”.

> 🧮 Hệ thống phải đảm bảo có thể truy ngược **mọi thay đổi** của giá trị hóa đơn, món ăn, và payment để phục vụ audit.

---

## 👣 Bước 6. Hoàn tất

- Khi tất cả các hóa đơn con **đã thanh toán xong**:
  - Hóa đơn gốc tự động cập nhật trạng thái → `Completed`.
  - Table session chuyển về `Completed`.
  - Bàn được chuyển về trạng thái `Trống`.

- Nếu một hoặc nhiều hóa đơn con vẫn còn **Pending**:
  - Session vẫn được giữ ở trạng thái `Active`.
  - Cho phép tiếp tục gọi món hoặc thanh toán phần còn lại.

---

## 📊 Ví dụ minh họa quy trình

| Hóa đơn | Trạng thái | Tổng tiền | Đã thanh toán | Còn lại | Ghi chú |
|----------|-------------|------------|----------------|----------|----------|
| INV001 (gốc) | Partially Paid | 1,000,000 | 300,000 | 700,000 | Đã tách 300k sang INV001-A |
| INV001-A | Unpaid | 300,000 | 0 | 300,000 | Được tách từ INV001 |
| INV001-B | Paid | 700,000 | 700,000 | 0 | Phần còn lại của INV001 |

> 🧾 Khi cả INV001-A & INV001-B đều `Paid`, session sẽ `Completed`.

---

## 🔒 Lưu ý quan trọng

- Không được phép tách hóa đơn:
  - Nếu hóa đơn đã `Paid` hoặc `Cancelled`.
  - Nếu tất cả món đã “Served” và thanh toán xong.
- Mỗi lần tách phải được ghi log đầy đủ cho mục đích tra soát.
- Mỗi hóa đơn tách có thể áp dụng **chính sách khuyến mãi riêng**, nhưng cần đảm bảo không vượt tổng khuyến mãi của hóa đơn gốc.

---


# ✅ Tổng kết minh bạch tài chính

| Tình huống | Hành vi hệ thống | Ghi chú |
|-------------|------------------|---------|
| Gộp hóa đơn có thanh toán trước | Giữ nguyên payment, trừ khi xuất hóa đơn tổng | Payment vẫn trace về hóa đơn gốc |
| Tách hóa đơn đang Partially Paid | Không tách phần đã thanh toán | Đảm bảo không double-count |
| Thuế & giảm giá | Luôn tính lại theo trọng số | Giữ chính xác tài chính |
| Lịch sử thao tác | Ghi log chi tiết nhân viên, thời gian, ID hóa đơn | Dễ dàng audit nội bộ |

---

# 📘 Kết luận
Hai quy trình **Gộp hóa đơn** và **Tách hóa đơn** là phần cốt lõi trong hệ thống POS nhà hàng.  
Cần đảm bảo:
- Ghi nhận và tính toán chuẩn xác.
- Lưu vết toàn bộ lịch sử thay đổi.
- Không làm mất dữ liệu gốc (order, payment, promotion).

