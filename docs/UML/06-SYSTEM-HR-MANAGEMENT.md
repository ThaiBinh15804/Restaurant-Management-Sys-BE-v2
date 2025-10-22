# Sơ Đồ UML - Quản Trị Hệ Thống và Nhân Sự (System & HR Management)

## 📋 Tổng Quan Module

Module quản trị hệ thống và nhân sự cung cấp công cụ quản lý toàn diện cho quản trị viên và cấp quản lý, hỗ trợ giám sát, phân tích và điều hành hoạt động của nhà hàng một cách hiệu quả, minh bạch và có tính hệ thống.

### Yêu Cầu Chính
- ✅ Dashboard tổng quan với các chỉ số hoạt động
- ✅ Quản lý nhân viên (CRUD, phân quyền, vai trò)
- ✅ Phân ca làm việc và chấm công
- ✅ Tính lương tự động (lương, thưởng, phạt, phụ cấp)
- ✅ Báo cáo nhân sự định kỳ
- ✅ Phân quyền truy cập chi tiết (RBAC)
- ✅ Audit log và theo dõi hoạt động

---

## 1️⃣ Use Case Diagram - Sơ Đồ Ca Sử Dụng

```mermaid
graph TB
    subgraph "Hệ Thống Quản Trị & Nhân Sự"
        UC1((Xem Dashboard))
        UC2((Quản Lý Nhân Viên))
        UC3((Phân Quyền))
        UC4((Phân Ca Làm Việc))
        UC5((Chấm Công))
        UC6((Tính Lương))
        UC7((Quản Lý Thưởng Phạt))
        UC8((Báo Cáo Nhân Sự))
        UC9((Quản Lý Vai Trò))
        UC10((Xem Audit Log))
        UC11((Sao Lưu Dữ Liệu))
    end
    
    Admin[👨‍💼 Quản Trị Viên]
    Manager[👔 Quản Lý]
    HR[👥 Nhân Sự]
    Employee[👤 Nhân Viên]
    System[🤖 Hệ Thống]
    
    Admin --> UC1
    Admin --> UC2
    Admin --> UC3
    Admin --> UC9
    Admin --> UC10
    Admin --> UC11
    
    Manager --> UC1
    Manager --> UC2
    Manager --> UC4
    Manager --> UC7
    Manager --> UC8
    
    HR --> UC2
    HR --> UC4
    HR --> UC5
    HR --> UC6
    HR --> UC8
    
    Employee --> UC5
    
    UC2 -.->|include| UC3
    UC6 -.->|include| UC5
    UC6 -.->|include| UC7
    
    System --> UC6
```

### Giải Thích Use Case

| Use Case | Actor | Mô Tả |
|----------|-------|-------|
| **Xem Dashboard** | Admin, Quản lý | Xem tổng quan hoạt động, KPIs, biểu đồ |
| **Quản Lý Nhân Viên** | Admin, Quản lý, HR | CRUD thông tin nhân viên |
| **Phân Quyền** | Admin | Gán quyền truy cập cho từng nhân viên |
| **Phân Ca Làm Việc** | Quản lý, HR | Sắp xếp lịch làm việc cho nhân viên |
| **Chấm Công** | HR, Nhân viên | Ghi nhận giờ vào/ra, nghỉ phép |
| **Tính Lương** | HR, Hệ thống | Tự động tính lương theo công, thưởng, phạt |
| **Quản Lý Thưởng Phạt** | Quản lý | Ghi nhận khen thưởng/kỷ luật |
| **Báo Cáo Nhân Sự** | Quản lý, HR | Báo cáo chấm công, lương, hiệu suất |
| **Quản Lý Vai Trò** | Admin | Tạo/sửa role với permissions |
| **Xem Audit Log** | Admin | Theo dõi mọi thao tác trong hệ thống |
| **Sao Lưu Dữ Liệu** | Admin | Backup/restore database |

---

## 2️⃣ Activity Diagram - Quy Trình Quản Lý Nhân Viên

```mermaid
flowchart TD
    Start([Bắt Đầu]) --> A1{Hành động?}
    
    A1 -->|Tạo nhân viên mới| A2[HR/Admin nhập thông tin<br/>nhân viên mới]
    A1 -->|Cập nhật thông tin| A3[Chọn nhân viên cần cập nhật]
    A1 -->|Xóa/Vô hiệu hóa| A4[Chọn nhân viên cần xóa]
    
    A2 --> A5[Nhập thông tin cơ bản:<br/>- Họ tên<br/>- CMND/CCCD<br/>- Ngày sinh<br/>- Địa chỉ<br/>- Điện thoại<br/>- Email]
    A3 --> A5
    
    A5 --> A6[Nhập thông tin công việc:<br/>- Vị trí<br/>- Phòng ban<br/>- Ngày bắt đầu<br/>- Loại hợp đồng<br/>- Lương cơ bản]
    
    A6 --> A7[Tạo tài khoản đăng nhập:<br/>- Username<br/>- Email<br/>- Password tạm thời]
    
    A7 --> A8[Phân quyền và vai trò]
    A8 --> A9{Vai trò?}
    
    A9 -->|Super Admin| A10[Gán role: super_admin<br/>Full permissions]
    A9 -->|Manager| A11[Gán role: manager<br/>Management permissions]
    A9 -->|Staff| A12[Gán role: staff<br/>Basic permissions]
    A9 -->|Waiter/Kitchen/Cashier| A13[Gán role theo bộ phận]
    
    A10 --> A14[Kiểm tra và xác nhận<br/>thông tin]
    A11 --> A14
    A12 --> A14
    A13 --> A14
    
    A14 --> A15{Thông tin hợp lệ?}
    A15 -->|Không| A16[Hiển thị lỗi validation]
    A16 --> A5
    
    A15 -->|Có| A17[Lưu vào database<br/>Status = Active]
    A17 --> A18[Tạo mã nhân viên<br/>EMP-xxxxx]
    
    A18 --> A19[Gửi email chào mừng:<br/>- Username<br/>- Password tạm<br/>- Link đổi MK<br/>- Thông tin onboarding]
    
    A19 --> A20[Tạo hồ sơ nhân viên<br/>trong HRM system]
    A20 --> A21[Thông báo cho<br/>quản lý trực tiếp]
    
    A21 --> A22{Nhân viên mới?}
    A22 -->|Có| A23[Tạo lịch đào tạo<br/>onboarding]
    A23 --> A24[Gán mentor]
    A24 --> A25[Chuẩn bị trang thiết bị:<br/>- Đồng phục<br/>- Thiết bị làm việc<br/>- Tài khoản hệ thống]
    
    A22 -->|Không| A26[Lưu lịch sử thay đổi]
    A25 --> A26
    
    A4 --> A27{Xóa hay vô hiệu hóa?}
    A27 -->|Xóa vĩnh viễn| A28[Kiểm tra ràng buộc<br/>dữ liệu]
    A28 --> A29{Có dữ liệu liên quan?}
    A29 -->|Có| A30[⚠️ Không thể xóa<br/>Đề xuất vô hiệu hóa]
    A30 --> A27
    
    A29 -->|Không| A31[Xác nhận xóa<br/>từ Giám đốc]
    A31 --> A32{Xác nhận?}
    A32 -->|Không| End1([Kết Thúc])
    A32 -->|Có| A33[Xóa nhân viên<br/>và tài khoản]
    
    A27 -->|Vô hiệu hóa| A34[Cập nhật Status = Inactive]
    A34 --> A35[Vô hiệu hóa tài khoản<br/>đăng nhập]
    A35 --> A36[Ghi lý do nghỉ việc]
    A36 --> A37[Tính toán lương tháng cuối<br/>và thanh toán]
    
    A33 --> End2([Kết Thúc])
    A26 --> End2
    A37 --> End2

    style A15 fill:#ffcc99
    style A29 fill:#ff9999
    style A32 fill:#ff9999
    style A17 fill:#99ff99
```

```
@startuml
start

:Xác định hành động nhân sự;

if (Tạo nhân viên mới?) then (Có)
    :Nhập thông tin cơ bản & công việc;
    :Tạo tài khoản và phân quyền;
    if (Thông tin hợp lệ?) then (Có)
        :Lưu vào hệ thống;
        :Onboarding nhân viên mới;
    else (Không)
        :Hiển thị lỗi và nhập lại;
    endif

elseif (Cập nhật thông tin?) then (Có)
    :Chọn nhân viên và cập nhật;
    :Lưu lịch sử thay đổi;

elseif (Xóa/Vô hiệu hóa?) then (Có)
    if (Xóa vĩnh viễn?) then (Có)
        :Kiểm tra ràng buộc dữ liệu;
        if (Có dữ liệu liên quan?) then (Có)
            :Đề xuất vô hiệu hóa;
        else (Không)
            :Xác nhận và xóa nhân viên;
        endif
    else (Vô hiệu hóa)
        :Cập nhật trạng thái Inactive;
        :Ghi lý do nghỉ việc và thanh toán cuối;
    endif
endif

stop
@enduml

```

---

## 3️⃣ Activity Diagram - Quy Trình Chấm Công và Tính Lương

```mermaid
flowchart TD
    Start([Bắt Đầu Tháng]) --> A1[Nhân viên check-in<br/>hàng ngày]
    A1 --> A2[Hệ thống ghi nhận:<br/>- Thời gian vào<br/>- Thời gian ra<br/>- GPS location]
    
    A2 --> A3{Ca làm việc?}
    A3 -->|Đúng ca| A4[Đánh dấu: Đúng giờ ✅]
    A3 -->|Trễ < 15 phút| A5[Đánh dấu: Trễ ⚠️<br/>Không phạt]
    A3 -->|Trễ > 15 phút| A6[Đánh dấu: Trễ muộn ❌<br/>Phạt theo quy định]
    
    A4 --> A7[Tính giờ công trong ngày]
    A5 --> A7
    A6 --> A7
    
    A7 --> A8{Làm thêm giờ?}
    A8 -->|Có| A9[Tính overtime:<br/>- Giờ thường: 150%<br/>- Cuối tuần: 200%<br/>- Lễ: 300%]
    A8 -->|Không| A10[Ghi nhận công chuẩn]
    
    A9 --> A11[Lưu vào bảng timesheet]
    A10 --> A11
    
    A11 --> A12{Hết tháng?}
    A12 -->|Chưa| A1
    
    A12 -->|Rồi| A13[HR khóa bảng chấm công<br/>của tháng]
    A13 --> A14[Tổng hợp:<br/>- Tổng ngày công<br/>- Tổng giờ OT<br/>- Số ngày nghỉ<br/>- Số lần đi muộn]
    
    A14 --> A15[Hệ thống tự động tính lương]
    A15 --> A16[Lương cơ bản<br/>= base_salary × (working_days / standard_days)]
    
    A16 --> A17[Phụ cấp<br/>= allowances]
    A17 --> A18[Lương OT<br/>= hourly_rate × OT_hours × rate]
    
    A18 --> A19[Thưởng<br/>= bonuses trong tháng]
    A19 --> A20[Phạt<br/>= penalties trong tháng]
    
    A20 --> A21[Tổng lương<br/>= base + allowances + OT + bonuses - penalties]
    A21 --> A22[Khấu trừ:<br/>- BHXH (8%)<br/>- BHYT (1.5%)<br/>- BHTN (1%)<br/>- Thuế TNCN]
    
    A22 --> A23[Lương thực nhận<br/>= total - deductions]
    
    A23 --> A24[Tạo payslip<br/>cho từng nhân viên]
    A24 --> A25[Gửi email payslip]
    A25 --> A26[Quản lý duyệt<br/>bảng lương]
    
    A26 --> A27{Duyệt?}
    A27 -->|Không| A28[Ghi chú lý do<br/>Yêu cầu chỉnh sửa]
    A28 --> A15
    
    A27 -->|Có| A29[Chuyển khoản lương<br/>vào ngày 5 tháng sau]
    A29 --> A30[Cập nhật trạng thái<br/>Payroll = Paid]
    
    A30 --> A31[Lưu báo cáo lương]
    A31 --> A32[Gửi cho kế toán]
    A32 --> End([Kết Thúc])

    style A27 fill:#ff9999
    style A23 fill:#99ff99
    style A29 fill:#99ccff
```

---

## 4️⃣ Sequence Diagram - Quy Trình Chấm Công Hàng Ngày

```mermaid
sequenceDiagram
    actor E as 👤 Nhân Viên
    participant App as 📱 Mobile App
    participant API as 🔧 API Gateway
    participant AttSvc as ⏰ Attendance Service
    participant ShiftSvc as 📅 Shift Service
    participant Cache as 💾 Redis
    participant DB as 💾 Database
    participant Notif as 🔔 Notification Service

    Note over E,DB: ━━━ CHECK-IN BUỔI SÁNG ━━━
    
    E->>App: 1. Mở app và nhấn Check-in
    App->>App: Lấy GPS location
    
    App->>API: POST /api/attendance/check-in<br/>{employee_id, location, timestamp}
    API->>AttSvc: processCheckIn(data)
    
    AttSvc->>ShiftSvc: getEmployeeShift(employee_id, date)
    ShiftSvc->>DB: SELECT shift_assignment<br/>WHERE employee_id AND date
    DB-->>ShiftSvc: Shift data (ca sáng 06:00-14:00)
    ShiftSvc-->>AttSvc: Shift info
    
    AttSvc->>AttSvc: Validate location<br/>(trong bán kính 100m?)
    
    alt Location hợp lệ
        AttSvc->>AttSvc: Tính thời gian đến:<br/>- Đúng giờ: 06:00-06:05 ✅<br/>- Trễ nhẹ: 06:05-06:15 ⚠️<br/>- Trễ muộn: > 06:15 ❌
        
        AttSvc->>DB: BEGIN TRANSACTION
        activate DB
        
        AttSvc->>DB: INSERT INTO attendance<br/>(employee_id, check_in, status)
        
        alt Trễ > 15 phút
            AttSvc->>DB: INSERT INTO penalties<br/>(employee_id, type, amount)
            Note over AttSvc: Phạt 50k-100k tùy mức độ
        end
        
        AttSvc->>DB: COMMIT
        deactivate DB
        
        AttSvc->>Cache: SET attendance:{emp_id}:{date}<br/>status='checked_in'
        
        AttSvc-->>API: Check-in successful
        API-->>App: Success response
        App-->>E: ✅ Đã chấm công vào ca<br/>Giờ: 06:03 (Đúng giờ)
        
        AttSvc->>Notif: sendNotification(manager)<br/>"NV [Name] đã vào ca"
        Notif-->>E: 📱 Thông báo cho quản lý
        
    else Location không hợp lệ
        AttSvc-->>API: Error: Location invalid
        API-->>App: Error response
        App-->>E: ❌ Bạn không ở trong khu vực<br/>nhà hàng (>100m)
    end

    Note over E,DB: ━━━ CHECK-OUT BUỔI CHIỀU ━━━
    
    E->>App: 2. Nhấn Check-out
    App->>API: POST /api/attendance/check-out<br/>{employee_id, timestamp}
    API->>AttSvc: processCheckOut(data)
    
    AttSvc->>Cache: GET attendance:{emp_id}:{date}
    Cache-->>AttSvc: Check-in data
    
    AttSvc->>AttSvc: Tính tổng giờ làm:<br/>check_out - check_in - break_time
    
    AttSvc->>AttSvc: Kiểm tra overtime:<br/>Nếu > 8h → tính OT
    
    AttSvc->>DB: UPDATE attendance<br/>SET check_out, total_hours,<br/>overtime_hours, status='completed'
    
    AttSvc->>Cache: UPDATE cache
    
    AttSvc-->>API: Check-out successful
    API-->>App: Success with summary
    App-->>E: ✅ Đã checkout<br/>━━━━━━━━━━━━━<br/>⏰ Tổng: 8.5 giờ<br/>⏱️ OT: 0.5 giờ<br/>💰 Lương OT: 75,000đ
```

```
@startuml
actor Employee as E
participant App as "Website"
participant API as "API Gateway"
participant AttendanceSvc as "Attendance Service"
participant ShiftSvc as "Shift Service"
participant DB as "Database"

== Check-in ==
E -> App: Mở app và nhấn Check-in
App -> App: Lấy location
App -> API: Gửi check-in (employee_id, location, timestamp)
API -> AttendanceSvc: Xử lý check-in
AttendanceSvc -> ShiftSvc: Lấy ca làm việc của nhân viên
ShiftSvc -> DB: Truy vấn ca
DB --> ShiftSvc: Ca làm việc
ShiftSvc --> AttendanceSvc: Trả ca
AttendanceSvc -> AttendanceSvc: Validate location & check-in time
alt Location hợp lệ
    AttendanceSvc -> DB: Ghi nhận check-in
    AttendanceSvc --> API: Check-in thành công
    API --> App: Phản hồi thành công
    App --> E: Hiển thị thông báo 
else Location không hợp lệ
    AttendanceSvc --> API: Lỗi location
    API --> App: Phản hồi lỗi
    App --> E: Hiển thị cảnh báo
end

== Check-out ==
E -> App: Nhấn Check-out
App -> API: Gửi check-out
API -> AttendanceSvc: Xử lý check-out
AttendanceSvc -> AttendanceSvc: Tính tổng giờ làm & OT
AttendanceSvc -> DB: Cập nhật check-out & giờ làm
AttendanceSvc --> API: Check-out thành công
API --> App: Phản hồi thành công
App --> E: Hiển thị tổng giờ & OT
@enduml

```

---

## 5️⃣ Sequence Diagram - Quy Trình Tính Lương Cuối Tháng

```mermaid
sequenceDiagram
    actor HR as 👥 HR Manager
    participant App as 📱 HR Portal
    participant API as 🔧 API Gateway
    participant PaySvc as 💰 Payroll Service
    participant AttSvc as ⏰ Attendance Service
    participant BonusSvc as 🎁 Bonus Service
    participant DB as 💾 Database
    participant Mail as 📧 Email Service
    participant Bank as 🏦 Banking API

    Note over HR,Bank: ━━━ NGÀY 1: KHÓA CHẤM CÔNG ━━━
    
    HR->>App: 1. Khóa bảng chấm công tháng 10
    App->>API: POST /api/attendance/lock<br/>{month: 10, year: 2025}
    API->>AttSvc: lockAttendance(month, year)
    
    AttSvc->>DB: UPDATE attendance<br/>SET locked=true<br/>WHERE month=10 AND year=2025
    
    AttSvc->>DB: INSERT INTO audit_log<br/>(action='ATTENDANCE_LOCKED')
    
    AttSvc-->>API: Locked successfully
    API-->>App: Success
    App-->>HR: ✅ Đã khóa chấm công tháng 10<br/>Không thể chỉnh sửa

    Note over HR,Bank: ━━━ NGÀY 2-3: TÍNH LƯƠNG TỰ ĐỘNG ━━━
    
    HR->>App: 2. Chạy tính lương tự động
    App->>API: POST /api/payroll/calculate<br/>{month: 10, year: 2025}
    API->>PaySvc: calculatePayrollForMonth(10, 2025)
    
    PaySvc->>DB: SELECT employees<br/>WHERE status IN ('Active','OnLeave')
    DB-->>PaySvc: List of employees (45 người)
    
    loop Từng nhân viên
        PaySvc->>AttSvc: getMonthlyAttendance(emp_id, 10, 2025)
        AttSvc->>DB: SELECT attendance summary
        DB-->>AttSvc: Attendance data
        AttSvc-->>PaySvc: {<br/>  working_days: 24,<br/>  total_hours: 192,<br/>  overtime_hours: 12,<br/>  late_count: 2<br/>}
        
        PaySvc->>BonusSvc: getBonusAndPenalties(emp_id, 10, 2025)
        BonusSvc->>DB: SELECT bonuses, penalties
        DB-->>BonusSvc: Bonus/Penalty data
        BonusSvc-->>PaySvc: {<br/>  bonuses: 1,000,000đ,<br/>  penalties: -100,000đ<br/>}
        
        Note over PaySvc: ━━━ TÍNH TOÁN LƯƠNG ━━━
        
        PaySvc->>PaySvc: 1. Base Salary<br/>= 8,000,000 × (24/26)<br/>= 7,384,615đ
        
        PaySvc->>PaySvc: 2. Allowances<br/>= Meal: 30k × 24 = 720k<br/>+ Gas: 500k<br/>= 1,220,000đ
        
        PaySvc->>PaySvc: 3. Overtime Pay<br/>= (8M/160) × 12h × 1.5<br/>= 900,000đ
        
        PaySvc->>PaySvc: 4. Gross Salary<br/>= 7,384,615 + 1,220,000<br/>+ 900,000 + 1,000,000<br/>- 100,000<br/>= 10,404,615đ
        
        PaySvc->>PaySvc: 5. Deductions<br/>• BHXH (8%): 832,369đ<br/>• BHYT (1.5%): 156,069đ<br/>• BHTN (1%): 104,046đ<br/>• TNCN: 500,000đ<br/>Total: 1,592,484đ
        
        PaySvc->>PaySvc: 6. Net Salary<br/>= 10,404,615 - 1,592,484<br/>= 8,812,131đ
        
        PaySvc->>DB: BEGIN TRANSACTION
        activate DB
        
        PaySvc->>DB: INSERT INTO payroll<br/>(employee_id, month, year,<br/>base, allowances, overtime,<br/>bonuses, penalties,<br/>gross, deductions, net,<br/>status='pending_approval')
        
        PaySvc->>DB: INSERT INTO payroll_items<br/>(type, description, amount)
        Note over PaySvc,DB: Chi tiết từng khoản:<br/>Base, Meal, Gas, OT,<br/>Bonus, Penalty, BHXH...
        
        PaySvc->>DB: COMMIT
        deactivate DB
        
        PaySvc->>Mail: generatePayslip(emp_id)
        Mail->>Mail: Create PDF payslip
        Mail-->>PaySvc: Payslip PDF
    end
    
    PaySvc-->>API: Payroll calculated for 45 employees
    API-->>App: Calculation complete
    App-->>HR: ✅ Đã tính lương 45 nhân viên<br/>Tổng chi: 396,546,000đ

    Note over HR,Bank: ━━━ NGÀY 4: DUYỆT LƯƠNG ━━━
    
    HR->>App: 3. Xem và kiểm tra bảng lương
    App->>API: GET /api/payroll?month=10&year=2025
    API->>PaySvc: getPayrollList(10, 2025)
    PaySvc->>DB: SELECT payroll with details
    DB-->>PaySvc: Payroll data
    PaySvc-->>API: Payroll list
    API-->>App: Display payroll
    App-->>HR: Hiển thị bảng lương chi tiết
    
    HR->>App: 4. Duyệt bảng lương
    App->>API: POST /api/payroll/batch-approve<br/>{payroll_ids: [...]}
    API->>PaySvc: approvePayroll(payroll_ids)
    
    PaySvc->>DB: UPDATE payroll<br/>SET status='approved',<br/>approved_by='HR-001',<br/>approved_at=NOW()
    
    PaySvc-->>API: Approved
    API-->>App: Success
    App-->>HR: ✅ Đã duyệt lương
    
    par Gửi payslip cho nhân viên
        PaySvc->>Mail: sendPayslipToAllEmployees()
        loop 45 nhân viên
            Mail->>Mail: Attach PDF payslip
            Mail-->>E: 📧 [Payslip Tháng 10/2025]<br/>Kính gửi anh/chị...<br/>Lương tháng 10: 8,812,131đ
        end
    end

    Note over HR,Bank: ━━━ NGÀY 5: CHUYỂN LƯƠNG ━━━
    
    HR->>App: 5. Chuyển lương qua ngân hàng
    App->>API: POST /api/payroll/transfer<br/>{month: 10, year: 2025}
    API->>PaySvc: initiatePayment(10, 2025)
    
    PaySvc->>DB: SELECT payroll<br/>WHERE status='approved'
    DB-->>PaySvc: Payroll data with bank info
    
    PaySvc->>Bank: POST /banking/batch-transfer
    Note over PaySvc,Bank: Gửi file batch transfer<br/>với 45 giao dịch
    
    Bank-->>PaySvc: Transfer initiated<br/>Transaction ID: TXN-12345
    
    PaySvc->>DB: UPDATE payroll<br/>SET status='paid',<br/>payment_date=NOW(),<br/>transaction_id='TXN-12345'
    
    PaySvc-->>API: Payment successful
    API-->>App: Success
    App-->>HR: ✅ Đã chuyển lương thành công<br/>━━━━━━━━━━━━━<br/>Số giao dịch: 45<br/>Tổng tiền: 396,546,000đ<br/>Mã GD: TXN-12345
    
    Bank->>Mail: sendBankNotification()
    Mail-->>E: 📧 [Ngân hàng] Bạn nhận được<br/>chuyển khoản 8,812,131đ<br/>từ [Nhà Hàng ABC]
```

---

## 6️⃣ Sequence Diagram - Phân Quyền RBAC

```mermaid
sequenceDiagram
    actor A as 👨‍💼 Admin
    participant App as 📱 Admin Panel
    participant API as 🔧 API Gateway
    participant AuthSvc as 🔐 Auth Service
    participant RoleSvc as 👥 Role Service
    participant DB as 💾 Database

    A->>App: 1. Vào màn hình phân quyền
    App->>API: GET /api/roles
    API->>RoleSvc: getAllRoles()
    RoleSvc->>DB: SELECT roles with permissions
    DB-->>RoleSvc: Roles data
    RoleSvc-->>API: Roles list
    API-->>App: Roles data
    App-->>A: Hiển thị danh sách roles

    A->>App: 2. Chọn nhân viên cần phân quyền
    App->>API: GET /api/employees/{employeeId}
    API->>AuthSvc: getEmployeeWithRoles(employeeId)
    AuthSvc->>DB: SELECT employee, roles, permissions
    DB-->>AuthSvc: Employee data
    AuthSvc-->>API: Employee with current roles
    API-->>App: Employee data
    App-->>A: Hiển thị thông tin NV và roles hiện tại

    A->>App: 3. Chọn roles mới
    Note over A,App: Chọn: Manager, Cashier

    A->>App: 4. Xác nhận thay đổi
    App->>API: PUT /api/employees/{employeeId}/roles
    API->>AuthSvc: updateEmployeeRoles(employeeId, roles)
    
    AuthSvc->>DB: BEGIN TRANSACTION
    activate DB
    
    AuthSvc->>DB: DELETE FROM employee_roles<br/>WHERE employee_id = ?
    AuthSvc->>DB: INSERT INTO employee_roles<br/>(employee_id, role_id)
    
    AuthSvc->>DB: INSERT INTO audit_log<br/>(action='ROLE_CHANGED', ...)
    
    AuthSvc->>DB: COMMIT TRANSACTION
    deactivate DB
    
    AuthSvc->>AuthSvc: invalidateUserCache(employeeId)
    
    Note over AuthSvc: Xóa cache permissions<br/>để buộc reload
    
    AuthSvc-->>API: Roles updated
    API-->>App: Success
    App-->>A: ✅ Đã cập nhật quyền

    App->>API: 5. Gửi thông báo đến NV
    API->>AuthSvc: notifyRoleChanged(employeeId)
    AuthSvc-->>A: 📧 Email: Quyền của bạn đã thay đổi
```

---

## 5️⃣ Sequence Diagram - Xem Dashboard Real-time

```mermaid
sequenceDiagram
    actor M as 👔 Quản Lý
    participant App as 📱 Dashboard App
    participant API as 🔧 API Gateway
    participant DashSvc as 📊 Dashboard Service
    participant Cache as 💾 Redis Cache
    participant DB as 💾 Database
    participant Analytics as 📈 Analytics Engine

    M->>App: Mở dashboard
    App->>API: GET /api/dashboard/summary
    API->>DashSvc: getSummary(date)
    
    DashSvc->>Cache: GET dashboard:summary:{date}
    
    alt Cache hit
        Cache-->>DashSvc: Cached data
        DashSvc-->>API: Summary data (from cache)
    else Cache miss
        DashSvc->>DB: Query multiple tables
        
        par Parallel queries
            DashSvc->>DB: SELECT revenue FROM orders<br/>WHERE date = TODAY
            DashSvc->>DB: SELECT COUNT(*) FROM orders<br/>WHERE date = TODAY
            DashSvc->>DB: SELECT AVG(total) FROM orders<br/>WHERE date = TODAY
            DashSvc->>DB: SELECT stock_value<br/>FROM inventory_summary
            DashSvc->>DB: SELECT COUNT(*) FROM employees<br/>WHERE status = 'Working'
        end
        
        DB-->>DashSvc: Query results
        
        DashSvc->>Analytics: calculateTrends(data)
        Analytics-->>DashSvc: Trends data
        
        DashSvc->>Cache: SET dashboard:summary:{date}<br/>EX 300 (5 minutes)
        DashSvc-->>API: Summary data
    end
    
    API-->>App: Dashboard data
    App-->>M: Hiển thị dashboard
    
    Note over App: WebSocket connection<br/>for real-time updates
    
    loop Every 30 seconds
        App->>API: WS: Subscribe to updates
        API->>DashSvc: getRealtimeUpdates()
        DashSvc->>DB: SELECT new orders, payments
        DB-->>DashSvc: Latest data
        DashSvc-->>API: Updates
        API-->>App: WS: Push updates
        App->>App: Update UI without refresh
    end
```

---

## 6️⃣ State Diagram - Vòng Đời Nhân Viên

```mermaid
stateDiagram-v2
    [*] --> Recruited: Tuyển dụng
    
    Recruited --> Onboarding: Ký hợp đồng
    
    Onboarding --> Probation: Bắt đầu thử việc
    
    Probation --> Active: Đạt yêu cầu thử việc
    Probation --> Terminated: Không đạt
    
    Active --> OnLeave: Xin nghỉ phép
    Active --> Suspended: Bị đình chỉ
    Active --> Resigned: Xin nghỉ việc
    Active --> Retired: Nghỉ hưu
    
    OnLeave --> Active: Quay lại làm việc
    
    Suspended --> Active: Hết thời gian đình chỉ
    Suspended --> Terminated: Bị sa thải
    
    Resigned --> [*]
    Retired --> [*]
    Terminated --> [*]
    
    note right of Recruited
        Vừa tuyển dụng
        Chưa bắt đầu làm
    end note
    
    note right of Onboarding
        Đào tạo ban đầu
        Làm quen môi trường
    end note
    
    note right of Probation
        Thử việc 2 tháng
        Đánh giá năng lực
    end note
    
    note right of Active
        Nhân viên chính thức
        Đang làm việc
    end note
```

---

## 7️⃣ ER Diagram - Mô Hình Dữ Liệu

```mermaid
erDiagram
    EMPLOYEE ||--o{ EMPLOYEE_ROLE : has
    EMPLOYEE ||--o{ SHIFT_ASSIGNMENT : assigned_to
    EMPLOYEE ||--o{ ATTENDANCE : records
    EMPLOYEE ||--o{ PAYROLL : receives
    EMPLOYEE ||--o| USER : has_account
    
    ROLE ||--o{ EMPLOYEE_ROLE : assigned_to
    ROLE ||--o{ ROLE_PERMISSION : has
    
    PERMISSION ||--o{ ROLE_PERMISSION : granted_to
    
    SHIFT ||--o{ SHIFT_ASSIGNMENT : contains
    
    ATTENDANCE ||--o{ PAYROLL_ITEM : affects
    
    PAYROLL ||--o{ PAYROLL_ITEM : contains
    
    EMPLOYEE {
        string id PK
        string full_name
        string id_number
        date date_of_birth
        string phone
        string email
        string address
        string position
        string department
        date hire_date
        enum contract_type
        decimal base_salary
        enum status
        string created_by FK
        datetime created_at
    }
    
    USER {
        string id PK
        string employee_id FK
        string username UK
        string email UK
        string password_hash
        bool is_active
        datetime last_login
    }
    
    ROLE {
        string id PK
        string name UK
        string description
        int priority
        bool is_system
    }
    
    PERMISSION {
        string id PK
        string module
        string action
        string description
    }
    
    EMPLOYEE_ROLE {
        string id PK
        string employee_id FK
        string role_id FK
        date assigned_at
        string assigned_by FK
    }
    
    ROLE_PERMISSION {
        string id PK
        string role_id FK
        string permission_id FK
    }
    
    SHIFT {
        string id PK
        string name
        time start_time
        time end_time
        int duration_hours
    }
    
    SHIFT_ASSIGNMENT {
        string id PK
        string employee_id FK
        string shift_id FK
        date work_date
        enum status
    }
    
    ATTENDANCE {
        string id PK
        string employee_id FK
        date work_date
        datetime check_in
        datetime check_out
        decimal total_hours
        decimal overtime_hours
        enum status
    }
    
    PAYROLL {
        string id PK
        string employee_id FK
        int month
        int year
        decimal base_salary
        decimal total_allowances
        decimal total_overtime
        decimal total_bonuses
        decimal total_penalties
        decimal gross_salary
        decimal total_deductions
        decimal net_salary
        enum status
        date payment_date
    }
    
    PAYROLL_ITEM {
        string id PK
        string payroll_id FK
        enum item_type
        string description
        decimal amount
    }
```

---

## 8️⃣ Business Rules - Quy Tắc Nghiệp Vụ

### 👥 Quy Tắc Nhân Viên

#### **Mã Nhân Viên**
- Format: `EMP-xxxxx` (5 chữ số)
- Tự động tạo khi thêm NV mới
- Không thay đổi, duy nhất

#### **Trạng Thái**
| Status | Mô Tả | Có thể đăng nhập? |
|--------|-------|-------------------|
| **Recruited** | Vừa tuyển | ❌ Không |
| **Onboarding** | Đào tạo | ✅ Có (hạn chế) |
| **Probation** | Thử việc | ✅ Có |
| **Active** | Chính thức | ✅ Có |
| **OnLeave** | Nghỉ phép | ⚠️ Hạn chế |
| **Suspended** | Đình chỉ | ❌ Không |
| **Resigned** | Đã nghỉ | ❌ Không |
| **Terminated** | Bị sa thải | ❌ Không |

### 🔐 Quy Tắc Phân Quyền (RBAC)

#### **Cấu Trúc**
```
User → Roles → Permissions → Resources
```

#### **7 Roles Chính**
| Role | Priority | Mô Tả |
|------|----------|-------|
| **super_admin** | 1 | Toàn quyền hệ thống |
| **admin** | 2 | Quản trị hệ thống |
| **manager** | 3 | Quản lý nhà hàng |
| **staff** | 4 | Nhân viên văn phòng |
| **cashier** | 5 | Thu ngân |
| **kitchen** | 6 | Bếp |
| **waiter** | 7 | Phục vụ |

#### **Permission Format**
```
{module}:{action}
```
Ví dụ:
- `users:view` - Xem danh sách user
- `orders:create` - Tạo order
- `invoices:delete` - Xóa hóa đơn

#### **Kiểm Tra Quyền**
```javascript
function hasPermission(user, permission) {
  // 1. Lấy tất cả roles của user
  const userRoles = getUserRoles(user.id);
  
  // 2. Lấy tất cả permissions của các roles
  const permissions = [];
  for (const role of userRoles) {
    permissions.push(...getRolePermissions(role.id));
  }
  
  // 3. Kiểm tra permission có trong danh sách không
  return permissions.includes(permission);
}
```

### ⏰ Quy Tắc Chấm Công

#### **Ca Làm Việc**
| Ca | Giờ | Thời gian nghỉ |
|----|-----|----------------|
| **Sáng** | 06:00 - 14:00 | 11:00-11:30 |
| **Chiều** | 14:00 - 22:00 | 17:00-17:30 |
| **Tối** | 22:00 - 06:00 | 01:00-01:30 |

#### **Quy Định Đi Muộn**
- **< 5 phút**: Không phạt
- **5-15 phút**: Cảnh cáo, không phạt tiền
- **15-30 phút**: Phạt 50,000đ
- **> 30 phút**: Phạt 100,000đ + cảnh cáo
- **> 3 lần/tháng**: Đình chỉ 1 ngày

#### **Tính Overtime**
```
overtime_rate = {
  weekday: 1.5,      // 150% lương
  weekend: 2.0,      // 200% lương
  holiday: 3.0       // 300% lương
}

overtime_pay = (base_salary / 160) × overtime_hours × rate
```
- 160 = số giờ chuẩn/tháng (8h × 20 ngày)

### 💰 Quy Tắc Tính Lương

#### **Công Thức**
```
gross_salary = base_salary + allowances + overtime_pay + bonuses - penalties

deductions = {
  BHXH: gross_salary × 0.08,    // 8%
  BHYT: gross_salary × 0.015,   // 1.5%
  BHTN: gross_salary × 0.01,    // 1%
  TNCN: calculateTax(gross_salary)
}

net_salary = gross_salary - SUM(deductions)
```

#### **Phụ Cấp**
| Loại | Số Tiền | Điều Kiện |
|------|---------|-----------|
| **Ăn ca** | 30,000đ/ngày | Làm full ca |
| **Xăng xe** | 500,000đ/tháng | Có xe đi làm |
| **Điện thoại** | 200,000đ/tháng | Quản lý trở lên |
| **Trách nhiệm** | 1,000,000đ/tháng | Manager |

#### **Thưởng**
- **Tháng 13**: 1 tháng lương (cuối năm)
- **KPI**: 10-30% lương (theo performance)
- **Lễ Tết**: 500,000đ - 2,000,000đ

#### **Phạt**
- Đi muộn: 50,000đ - 100,000đ
- Nghỉ không phép: 200,000đ/ngày
- Vi phạm quy định: 500,000đ - 2,000,000đ

---

## 9️⃣ API Endpoints - Danh Sách API

### Employee Management

#### CRUD Nhân Viên
```http
# Danh sách nhân viên
GET /api/employees?status=Active&department=Kitchen

# Chi tiết nhân viên
GET /api/employees/{employeeId}

# Tạo nhân viên mới
POST /api/employees
Body: {
  "full_name": "Nguyễn Văn A",
  "id_number": "001234567890",
  "date_of_birth": "1990-01-01",
  "phone": "0901234567",
  "email": "nva@restaurant.com",
  "position": "Waiter",
  "department": "Service",
  "base_salary": 8000000,
  "hire_date": "2025-10-22"
}

# Cập nhật nhân viên
PUT /api/employees/{employeeId}

# Vô hiệu hóa nhân viên
POST /api/employees/{employeeId}/deactivate
Body: {
  "reason": "Resigned",
  "last_working_date": "2025-10-31"
}
```

### Role & Permission Management

#### Phân Quyền
```http
# Danh sách roles
GET /api/roles

# Chi tiết role với permissions
GET /api/roles/{roleId}

# Gán roles cho nhân viên
PUT /api/employees/{employeeId}/roles
Body: {
  "role_ids": ["ROLE-001", "ROLE-002"]
}

# Kiểm tra quyền
POST /api/auth/check-permission
Body: {
  "user_id": "USR-001",
  "permission": "orders:create"
}
Response: {
  "has_permission": true
}
```

### Attendance Management

#### Chấm Công
```http
# Check-in
POST /api/attendance/check-in
Body: {
  "employee_id": "EMP-001",
  "location": {
    "lat": 10.762622,
    "lng": 106.660172
  }
}

# Check-out
POST /api/attendance/check-out
Body: {
  "employee_id": "EMP-001"
}

# Bảng chấm công tháng
GET /api/attendance?employee_id=EMP-001&month=10&year=2025
Response: {
  "employee_id": "EMP-001",
  "month": 10,
  "year": 2025,
  "total_working_days": 24,
  "total_hours": 192,
  "overtime_hours": 12,
  "late_count": 2,
  "absent_count": 0,
  "records": [...]
}
```

### Payroll Management

#### Tính Lương
```http
# Tạo bảng lương tháng
POST /api/payroll/calculate
Body: {
  "month": 10,
  "year": 2025,
  "employee_ids": ["EMP-001", "EMP-002"]
}

# Xem payslip
GET /api/payroll/{payrollId}
Response: {
  "payroll_id": "PAY-001",
  "employee_name": "Nguyễn Văn A",
  "month": "10/2025",
  "base_salary": 8000000,
  "allowances": 600000,
  "overtime": 450000,
  "bonuses": 1000000,
  "penalties": -100000,
  "gross_salary": 9950000,
  "deductions": {
    "BHXH": 796000,
    "BHYT": 149250,
    "BHTN": 99500,
    "TNCN": 500000
  },
  "net_salary": 8405250
}

# Duyệt bảng lương
POST /api/payroll/{payrollId}/approve
```

### Dashboard

#### Dashboard Tổng Quan
```http
GET /api/dashboard/summary?date=2025-10-21
Response: {
  "revenue": {
    "today": 15000000,
    "yesterday": 12000000,
    "change_percent": 25
  },
  "orders": {
    "today": 125,
    "yesterday": 98,
    "change_percent": 27.55
  },
  "customers": {
    "today": 280,
    "yesterday": 245
  },
  "inventory_value": 25000000,
  "staff_working": 18,
  "tables_occupied": 12
}
```

---

## 🔟 Screen Mockups - Giao Diện Tham Khảo

### Dashboard Tổng Quan
```
┌─────────────────────────────────────────────────────────┐
│          📊 DASHBOARD - Tổng Quan Hệ Thống             │
├─────────────────────────────────────────────────────────┤
│ 📅 Ngày: 21/10/2025                    👤 Admin: Hùng  │
├─────────────────────────────────────────────────────────┤
│                                                           │
│ 💰 DOANH THU HÔM NAY         📦 ĐƠN HÀNG               │
│ ┌──────────────────┐         ┌──────────────────┐      │
│ │   15,000,000đ    │         │       125        │      │
│ │   ▲ +25% so hôm qua│         │   ▲ +27.55%      │      │
│ └──────────────────┘         └──────────────────┘      │
│                                                           │
│ 👥 KHÁCH HÀNG                📊 TỒN KHO                │
│ ┌──────────────────┐         ┌──────────────────┐      │
│ │       280        │         │  25,000,000đ     │      │
│ │   ▲ +14.29%      │         │   🟢 Ổn định     │      │
│ └──────────────────┘         └──────────────────┘      │
│                                                           │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━      │
│                                                           │
│ 📈 BIỂU ĐỒ DOANH THU 7 NGÀY QUA                        │
│ ┌─────────────────────────────────────────────────┐    │
│ │ 15M │         ╱╲                            ▲   │    │
│ │ 12M │      ╱╲╱  ╲      ╱╲                  ╱│   │    │
│ │  9M │    ╱╲      ╲╱╲╱╲╱  ╲              ╱╲╱ │   │    │
│ │  6M │  ╱╲                  ╲╱╲        ╱╲    │   │    │
│ │  3M │╱╲                        ╲╱╲╱╲╱      │   │    │
│ │     └────────────────────────────────────────│   │    │
│ │      15  16  17  18  19  20  21            │   │    │
│ └─────────────────────────────────────────────────┘    │
│                                                           │
│ 🔥 TOP 5 MÓN BÁN CHẠY       ⚠️ CẢNH BÁO                │
│ 1. Phở Bò (45 đơn)          • 8 NVL sắp hết             │
│ 2. Bún Bò (32 đơn)          • 2 thiết bị cần bảo trì   │
│ 3. Cơm Tấm (28 đơn)         • 3 NV cần đào tạo         │
│                                                           │
└─────────────────────────────────────────────────────────┘
```

### Màn Hình Quản Lý Nhân Viên
```
┌─────────────────────────────────────────────────────────┐
│          👥 QUẢN LÝ NHÂN VIÊN                           │
├─────────────────────────────────────────────────────────┤
│ 🔍 [_________]  📁 [Tất cả ▼]  🏢 [Bộ phận ▼]         │
│ [ ➕ Thêm Nhân Viên ]  [ 📊 Báo Cáo ]                  │
├─────────────────────────────────────────────────────────┤
│                                                           │
│ Mã      │ Họ Tên       │ Vị trí  │ Ca    │ Trạng thái  │
│─────────┼──────────────┼─────────┼───────┼─────────────│
│ EMP-001 │ Nguyễn Văn A │ Waiter  │ Sáng  │ 🟢 Active   │
│ EMP-002 │ Trần Thị B   │ Cashier │ Chiều │ 🟢 Active   │
│ EMP-003 │ Lê Văn C     │ Chef    │ Chiều │ 🟡 OnLeave  │
│ EMP-004 │ Phạm Thị D   │ Manager │ Full  │ 🟢 Active   │
│                                                           │
│ [ 👁️ Xem ] [ ✏️ Sửa ] [ 🔐 Phân Quyền ] [ ⏰ Chấm Công ]│
└─────────────────────────────────────────────────────────┘
```

---

## 1️⃣1️⃣ Security Best Practices - Thực Hành Bảo Mật

### 🔐 Authentication & Authorization
1. **JWT Token**: Access token 60 phút, Refresh token 30 ngày
2. **Password Policy**: 
   - Tối thiểu 8 ký tự
   - Bao gồm chữ hoa, chữ thường, số, ký tự đặc biệt
   - Hash bằng bcrypt (cost factor 12)
3. **MFA**: Bắt buộc cho Admin và Manager
4. **Session Management**: Logout tự động sau 30 phút không hoạt động

### 📝 Audit Log
Ghi lại mọi thao tác quan trọng:
- User login/logout
- Thay đổi quyền
- Tạo/sửa/xóa dữ liệu quan trọng
- Thanh toán, nhập/xuất kho

Format:
```json
{
  "timestamp": "2025-10-21T12:00:00Z",
  "user_id": "USR-001",
  "action": "UPDATE",
  "resource": "employees",
  "resource_id": "EMP-001",
  "changes": {
    "base_salary": {"old": 8000000, "new": 9000000}
  },
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0..."
}
```

---

**[⬅️ Quay lại: Menu & Promotion](./05-MENU-PROMOTION-MANAGEMENT.md)** | **[🏠 Về Index](./00-INDEX.md)**
