# Tài Liệu Sơ Đồ UML Nghiệp Vụ - Hệ Thống Quản Lý Nhà Hàng

## 📋 Mục Lục

| STT | Module | File | Mô tả |
|-----|--------|------|-------|
| 1 | **Quản lý đặt bàn** | [01-RESERVATION-MANAGEMENT.md](./01-RESERVATION-MANAGEMENT.md) | Quy trình đặt bàn, đặt cọc, xác nhận |
| 2 | **Quản lý đặt món & thanh toán** | [02-ORDER-PAYMENT-MANAGEMENT.md](./02-ORDER-PAYMENT-MANAGEMENT.md) | Quy trình đặt món, xử lý đơn hàng, thanh toán |
| 3 | **Quản lý bàn & phục vụ** | [03-TABLE-SERVICE-MANAGEMENT.md](./03-TABLE-SERVICE-MANAGEMENT.md) | Quản lý bàn, gộp/tách bàn, phục vụ |
| 4 | **Quản lý kho & cung ứng** | [04-INVENTORY-SUPPLY-MANAGEMENT.md](./04-INVENTORY-SUPPLY-MANAGEMENT.md) | Nhập/xuất kho, tồn kho, nhà cung cấp |
| 5 | **Quản lý menu & khuyến mãi** | [05-MENU-PROMOTION-MANAGEMENT.md](./05-MENU-PROMOTION-MANAGEMENT.md) | Quản lý món ăn, menu, chương trình KM |
| 6 | **Quản trị hệ thống & nhân sự** | [06-SYSTEM-HR-MANAGEMENT.md](./06-SYSTEM-HR-MANAGEMENT.md) | Quản lý nhân viên, phân quyền, dashboard |

## 📊 Các Loại Sơ Đồ UML

Mỗi module sẽ bao gồm các loại sơ đồ sau:

### 1. **Use Case Diagram** (Sơ đồ ca sử dụng)
- Mô tả các actor (người dùng) và chức năng họ có thể thực hiện
- Thể hiện mối quan hệ giữa các use case (include, extend)

### 2. **Activity Diagram** (Sơ đồ hoạt động)
- Mô tả luồng xử lý nghiệp vụ từ đầu đến cuối
- Thể hiện các nhánh điều kiện, xử lý song song
- Hiển thị các swimlane (phân chia theo actor)

### 3. **Sequence Diagram** (Sơ đồ tuần tự)
- Mô tả tương tác giữa các đối tượng theo thời gian
- Thể hiện các message gửi/nhận giữa các thành phần
- Hiển thị vòng đời của đối tượng

### 4. **State Diagram** (Sơ đồ trạng thái)
- Mô tả các trạng thái của entity chính
- Thể hiện các chuyển đổi trạng thái (state transition)
- Áp dụng cho: Đơn đặt bàn, Order, Bàn ăn, Kho...

## 🎯 Mục Đích

Tài liệu này cung cấp cái nhìn trực quan về:
- **Quy trình nghiệp vụ**: Hiểu rõ luồng xử lý từ đầu đến cuối
- **Tương tác người dùng**: Nắm bắt các chức năng theo vai trò
- **Logic hệ thống**: Hiểu cách các thành phần tương tác với nhau
- **Trạng thái dữ liệu**: Theo dõi vòng đời của các entity chính

## 👥 Các Actor Chính

| Actor | Vai trò | Mô tả |
|-------|---------|-------|
| **Khách hàng** | Customer | Người đặt bàn, đặt món, thanh toán |
| **Nhân viên phục vụ** | Waiter | Tiếp nhận, xử lý order, phục vụ khách |
| **Đầu bếp** | Kitchen Staff | Nhận đơn, chế biến món ăn |
| **Thu ngân** | Cashier | Xử lý thanh toán, xuất hóa đơn |
| **Nhân viên kho** | Warehouse Staff | Quản lý nhập/xuất kho, kiểm kê |
| **Quản lý** | Manager | Giám sát, phê duyệt, báo cáo |
| **Quản trị viên** | Admin | Quản lý hệ thống, phân quyền |

## 🔄 Quy Ước Ký Hiệu

### Use Case Diagram
- `Khách hàng` - Actor (người thực hiện)
- `(Đặt bàn)` - Use case (chức năng)
- `<<include>>` - Quan hệ bắt buộc
- `<<extend>>` - Quan hệ tùy chọn

### Activity Diagram
- `[Điều kiện]` - Điều kiện rẽ nhánh
- `<<parallel>>` - Xử lý song song
- `:Swimlane:` - Phân chia theo actor

### Sequence Diagram
- `->` - Message đồng bộ
- `-->>` - Message trả về
- `activate/deactivate` - Vòng đời object

### State Diagram
- `[*]` - Trạng thái bắt đầu/kết thúc
- `-->` - Chuyển đổi trạng thái
- `[Event]` - Sự kiện kích hoạt

## 📌 Ghi Chú

- Tất cả sơ đồ được vẽ bằng **Mermaid** để dễ dàng hiển thị trong Markdown
- Mỗi sơ đồ đi kèm với **giải thích chi tiết** bằng tiếng Việt
- Các quy trình được mô tả dựa trên **yêu cầu chức năng** trong Raw.md
- Sơ đồ có thể được cập nhật khi yêu cầu nghiệp vụ thay đổi

## 🚀 Cách Sử Dụng

1. **Cho Developer**: Hiểu logic nghiệp vụ để implement code
2. **Cho Tester**: Xây dựng test case dựa trên flow
3. **Cho BA/PM**: Trình bày và thảo luận với stakeholder
4. **Cho Onboarding**: Giúp thành viên mới nắm bắt hệ thống nhanh

---

**Version**: 1.0  
**Last Updated**: October 21, 2025  
**Maintained by**: Development Team
