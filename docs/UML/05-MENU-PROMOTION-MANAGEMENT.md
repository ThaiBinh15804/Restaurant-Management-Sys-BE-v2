# Sơ Đồ UML - Quản Lý Menu và Khuyến Mãi (Menu & Promotion Management)

## 📋 Tổng Quan Module

Module quản lý menu và khuyến mãi đảm bảo tính linh hoạt cao trong việc quản lý và cập nhật menu, điều chỉnh giá bán và triển khai các chương trình khuyến mãi phù hợp với từng giai đoạn hoạt động của nhà hàng.

### Yêu Cầu Chính
- ✅ Tạo mới, chỉnh sửa, ẩn/hiện món ăn
- ✅ Phân loại món theo danh mục
- ✅ Cập nhật giá bán và định mức nguyên liệu
- ✅ Quản lý phiên bản menu (theo mùa/sự kiện)
- ✅ Xây dựng và triển khai chương trình khuyến mãi/voucher
- ✅ Theo dõi và đánh giá hiệu quả khuyến mãi
- ✅ Báo cáo doanh thu theo món/chương trình KM

---

## 1️⃣ Use Case Diagram - Sơ Đồ Ca Sử Dụng

```mermaid
graph TB
    subgraph "Hệ Thống Quản Lý Menu & Khuyến Mãi"
        UC1((Quản Lý Món Ăn))
        UC2((Quản Lý Danh Mục))
        UC3((Cập Nhật Giá))
        UC4((Quản Lý Recipe))
        UC5((Quản Lý Phiên Bản Menu))
        UC6((Tạo Chương Trình KM))
        UC7((Tạo Voucher))
        UC8((Áp Dụng Khuyến Mãi))
        UC9((Theo Dõi Hiệu Quả KM))
        UC10((Báo Cáo Doanh Thu))
    end
    
    Manager[👨‍💼 Quản Lý]
    Chef[👨‍🍳 Đầu Bếp]
    Cashier[💰 Thu Ngân]
    Customer[👤 Khách Hàng]
    System[🤖 Hệ Thống]
    
    Manager --> UC1
    Manager --> UC2
    Manager --> UC3
    Manager --> UC5
    Manager --> UC6
    Manager --> UC7
    Manager --> UC9
    Manager --> UC10
    
    Chef --> UC1
    Chef --> UC4
    
    Cashier --> UC8
    
    Customer --> UC8
    
    UC1 -.->|include| UC2
    UC1 -.->|include| UC4
    UC6 -.->|extend| UC7
    UC8 --> System
```

```
@startuml
left to right direction

actor "Quản lý" as Manager
actor "Đầu bếp" as Chef
actor "Thu ngân" as Cashier
actor "Khách hàng" as Customer
actor "Hệ thống" as System

rectangle "Hệ Thống Quản Lý Menu & Khuyến Mãi" {
    usecase "Quản Lý Món Ăn" as UC1
    usecase "Quản Lý Danh Mục" as UC2
    usecase "Cập Nhật Giá" as UC3
    usecase "Quản Lý Recipe" as UC4
    usecase "Quản Lý Phiên Bản Menu" as UC5
    usecase "Tạo Chương Trình KM" as UC6
    usecase "Tạo Voucher" as UC7
    usecase "Áp Dụng Khuyến Mãi" as UC8
    usecase "Theo Dõi Hiệu Quả KM" as UC9
    usecase "Báo Cáo Doanh Thu" as UC10
}

' --- Quan hệ giữa actor và use case ---
Manager --> UC1
Manager --> UC2
Manager --> UC3
Manager --> UC5
Manager --> UC6
Manager --> UC7
Manager --> UC9
Manager --> UC10

Chef --> UC1
Chef --> UC4

Cashier --> UC8
Customer --> UC8

' --- Quan hệ giữa các use case ---
UC1 .> UC2 : <<include>>
UC1 .> UC4 : <<include>>
UC6 .> UC7 : <<extend>>
UC8 --> System

@enduml

```

### Giải Thích Use Case

| Use Case | Actor | Mô Tả |
|----------|-------|-------|
| **Quản Lý Món Ăn** | Quản lý, Đầu bếp | CRUD món ăn, cập nhật thông tin, ảnh |
| **Quản Lý Danh Mục** | Quản lý | Tạo/sửa danh mục món (Khai vị, Món chính...) |
| **Cập Nhật Giá** | Quản lý | Thay đổi giá bán, lưu lịch sử thay đổi |
| **Quản Lý Recipe** | Đầu bếp | Định nghĩa nguyên liệu và định mức cho món |
| **Quản Lý Phiên Bản Menu** | Quản lý | Tạo menu theo mùa, sự kiện, dịp lễ |
| **Tạo Chương Trình KM** | Quản lý | Thiết lập promotion, điều kiện áp dụng |
| **Tạo Voucher** | Quản lý | Tạo mã giảm giá, giới hạn sử dụng |
| **Áp Dụng Khuyến Mãi** | Thu ngân, Khách hàng | Tự động/thủ công áp dụng promotion/voucher |
| **Theo Dõi Hiệu Quả KM** | Quản lý | Xem số lượt dùng, tỷ lệ chuyển đổi |
| **Báo Cáo Doanh Thu** | Quản lý | Báo cáo theo món, danh mục, KM |

---

## 2️⃣ Activity Diagram - Quy Trình Quản Lý Món Ăn

```mermaid
flowchart TD
    Start([Bắt Đầu]) --> A1{Hành động?}
    
    A1 -->|Tạo món mới| A2[Đầu bếp/Quản lý<br/>khởi tạo món mới]
    A1 -->|Chỉnh sửa món| A3[Chọn món cần sửa]
    A1 -->|Ẩn/hiện món| A4[Chọn món và<br/>toggle visibility]
    
    A2 --> A5[Nhập thông tin cơ bản:<br/>- Tên món<br/>- Mô tả<br/>- Giá bán<br/>- Danh mục]
    A3 --> A5
    
    A5 --> A6[Upload ảnh món ăn]
    A6 --> A7[Định nghĩa Recipe:<br/>Danh sách nguyên liệu<br/>+ Định mức]
    
    A7 --> A8[Nhập từng nguyên liệu]
    
    loop For each ingredient
        A8 --> A9[Chọn nguyên liệu<br/>từ danh sách]
        A9 --> A10[Nhập định mức<br/>quantity + unit]
        A10 --> A11{Thêm NVL khác?}
        A11 -->|Có| A8
    end
    
    A11 -->|Không| A12[Tính giá vốn món:<br/>cost = SUM ingredient_cost]
    A12 --> A13[Kiểm tra tỷ lệ lợi nhuận:<br/>profit_margin = gross_profit / price]
    
    A13 --> A14{Lợi nhuận hợp lý?}
    A14 -->|< 30%| A15[⚠️ Cảnh báo lợi nhuận thấp<br/>Đề xuất tăng giá]
    A15 --> A16{Điều chỉnh giá?}
    A16 -->|Có| A5
    A16 -->|Không| A17[Ghi chú lý do<br/>chấp nhận lợi nhuận thấp]
    
    A14 -->|≥ 30%| A18[Lưu món vào database]
    A17 --> A18
    
    A18 --> A19{Món mới?}
    A19 -->|Có| A20[Status = Draft<br/>Chưa hiển thị trên menu]
    A19 -->|Không| A21[Lưu lịch sử thay đổi]
    
    A20 --> A22[Đầu bếp test món]
    A22 --> A23{Test thành công?}
    A23 -->|Không| A24[Điều chỉnh recipe<br/>hoặc cách chế biến]
    A24 --> A7
    
    A23 -->|Có| A25[Chụp ảnh món thật]
    A25 --> A26[Quản lý duyệt món]
    A26 --> A27{Duyệt?}
    
    A27 -->|Từ chối| A28[Ghi lý do từ chối]
    A28 --> End1([Kết Thúc])
    
    A27 -->|Duyệt| A29[Cập nhật Status = Active]
    A21 --> A29
    
    A29 --> A30[Đồng bộ lên menu<br/>hiển thị cho khách]
    A30 --> A31[Thông báo món mới<br/>đến nhân viên]
    A31 --> A32[Tự động tạo marketing<br/>trên app/website]
    
    A4 --> A33[Toggle available flag]
    A33 --> A34[Cập nhật menu real-time]
    
    A32 --> End2([Kết Thúc])
    A34 --> End2

    style A13 fill:#ffcc99
    style A14 fill:#ff9999
    style A27 fill:#ff9999
    style A29 fill:#99ff99
```

```
@startuml
start

:Chọn hành động: Tạo / Chỉnh sửa / Ẩn/hiện món;

if (Tạo hoặc chỉnh sửa món?) then (Có)
    :Nhập thông tin món và Recipe;
    :Kiểm tra lợi nhuận và lưu vào hệ thống;

    if (Món mới?) then (Có)
        :Đầu bếp test món;
        :Chụp ảnh món thật;
    endif

    :Quản lý duyệt món;
    if (Từ chối?) then (Có)
        :Ghi lý do và kết thúc;
        stop
    endif

    :Cập nhật Status = Active;
    :Đồng bộ lên menu và marketing;
endif

if (Ẩn/hiện món?) then (Có)
    :Cập nhật trạng thái hiển thị;
endif

stop
@enduml

```

---

## 3️⃣ Activity Diagram - Quy Trình Tạo Chương Trình Khuyến Mãi

```mermaid
flowchart TD
    Start([Bắt Đầu]) --> A1[Quản lý xác định<br/>mục tiêu khuyến mãi]
    A1 --> A2{Loại khuyến mãi?}
    
    A2 -->|Giảm theo %| A3[Promotion: Discount Percent]
    A2 -->|Giảm cố định| A4[Promotion: Fixed Amount]
    A2 -->|Tặng món| A5[Promotion: Free Item]
    A2 -->|Voucher code| A6[Voucher: Discount Code]
    
    A3 --> A7[Nhập thông tin KM:<br/>- Tên chương trình<br/>- Mô tả<br/>- % giảm giá]
    A4 --> A7
    A5 --> A7
    A6 --> A8[Tạo mã voucher:<br/>- Code duy nhất<br/>- Số lượng phát hành]
    
    A8 --> A7
    A7 --> A9[Thiết lập điều kiện:<br/>- Giá trị đơn tối thiểu<br/>- Áp dụng cho món/danh mục<br/>- Thời gian áp dụng]
    
    A9 --> A10[Chọn phạm vi áp dụng]
    A10 --> A11{Áp dụng cho?}
    
    A11 -->|Tất cả món| A12[All dishes]
    A11 -->|Danh mục cụ thể| A13[Chọn categories]
    A11 -->|Món cụ thể| A14[Chọn dishes]
    
    A12 --> A15[Thiết lập giới hạn:<br/>- Số lần sử dụng tối đa<br/>- Số lần/khách hàng<br/>- Ngân sách tối đa]
    A13 --> A15
    A14 --> A15
    
    A15 --> A16[Xem trước thông tin KM]
    A16 --> A17{Xác nhận?}
    
    A17 -->|Không| A18[Chỉnh sửa]
    A18 --> A7
    
    A17 -->|Có| A19[Lưu KM<br/>Status = Draft]
    A19 --> A20[Tính toán dự báo:<br/>- Chi phí dự kiến<br/>- Số khách hưởng lợi<br/>- ROI ước tính]
    
    A20 --> A21[Trình Giám đốc duyệt]
    A21 --> A22{Duyệt?}
    
    A22 -->|Từ chối| A23[Ghi lý do<br/>Status = Rejected]
    A23 --> End1([Kết Thúc])
    
    A22 -->|Duyệt| A24[Status = Scheduled]
    A24 --> A25[Chờ đến thời gian<br/>bắt đầu]
    
    A25 --> A26[Tự động kích hoạt<br/>Status = Active]
    A26 --> A27[Hiển thị trên app/web<br/>Gửi notification]
    
    A27 --> A28[Áp dụng tự động<br/>khi khách thanh toán]
    A28 --> A29[Thu thập dữ liệu:<br/>- Số lượt sử dụng<br/>- Doanh thu<br/>- Khách hàng mới]
    
    A29 --> A30{Hết thời gian?}
    A30 -->|Chưa| A28
    A30 -->|Rồi| A31[Tự động kết thúc<br/>Status = Ended]
    
    A31 --> A32[Tạo báo cáo hiệu quả:<br/>- Tổng chi phí<br/>- Tổng doanh thu<br/>- ROI thực tế<br/>- Tỷ lệ chuyển đổi]
    A32 --> End2([Kết Thúc])

    style A20 fill:#99ccff
    style A22 fill:#ff9999
    style A26 fill:#99ff99
    style A32 fill:#ffcc99
```

---

## 4️⃣ Sequence Diagram - Áp Dụng Khuyến Mãi Tự Động

```mermaid
sequenceDiagram
    actor C as 👤 Khách Hàng
    actor CS as 💰 Thu Ngân
    participant App as 📱 POS App
    participant API as 🔧 API Gateway
    participant OrderSvc as 📦 Order Service
    participant PromoSvc as 🎁 Promotion Service
    participant DB as 💾 Database

    C->>CS: Yêu cầu thanh toán
    CS->>App: Mở màn hình thanh toán
    App->>API: GET /api/orders/{orderId}/calculate-bill
    API->>OrderSvc: calculateBill(orderId)
    
    OrderSvc->>DB: SELECT order with items
    DB-->>OrderSvc: Order data
    
    OrderSvc->>PromoSvc: 1. findApplicablePromotions(orderData)
    PromoSvc->>DB: SELECT promotions<br/>WHERE status='Active'<br/>AND NOW() BETWEEN valid_from AND valid_to
    DB-->>PromoSvc: Active promotions
    
    loop For each promotion
        PromoSvc->>PromoSvc: 2. checkConditions(promotion, order)
        
        Note over PromoSvc: Kiểm tra điều kiện:<br/>- Min order value<br/>- Applicable items<br/>- Usage limit<br/>- Customer eligibility
        
        alt Điều kiện thỏa mãn
            PromoSvc->>PromoSvc: calculateDiscount(promotion, order)
            PromoSvc->>PromoSvc: Add to applicable list
        end
    end
    
    PromoSvc->>PromoSvc: 3. selectBestPromotion(applicableList)
    
    Note over PromoSvc: Chọn promotion<br/>có lợi nhất cho khách
    
    PromoSvc-->>OrderSvc: Best promotion + discount amount
    
    OrderSvc->>OrderSvc: 4. calculateFinalTotal()<br/>total = subtotal - discount + tax
    
    OrderSvc-->>API: Bill with promotion applied
    API-->>App: Bill data
    App-->>CS: Hiển thị hóa đơn với KM
    
    CS-->>C: Thông báo được giảm {amount}
    
    C->>CS: Xác nhận thanh toán
    CS->>App: Process payment
    App->>API: POST /api/payments
    API->>OrderSvc: createPayment()
    
    OrderSvc->>PromoSvc: 5. recordPromotionUsage(promotionId, orderId)
    PromoSvc->>DB: BEGIN TRANSACTION
    activate DB
    
    PromoSvc->>DB: UPDATE promotions<br/>SET used_count = used_count + 1
    PromoSvc->>DB: INSERT promotion_usages
    PromoSvc->>DB: INSERT customer_promotions<br/>(tracking per customer)
    
    PromoSvc->>DB: COMMIT TRANSACTION
    deactivate DB
    
    PromoSvc-->>OrderSvc: Usage recorded
    OrderSvc-->>API: Payment success
    API-->>App: Success
    App-->>CS: ✅ Thanh toán thành công
```



---

## 5️⃣ State Diagram - Vòng Đời Món Ăn (Dish)

```mermaid
stateDiagram-v2
    [*] --> Draft: Tạo món mới
    
    Draft --> Testing: Đầu bếp test
    Draft --> Cancelled: Hủy món
    
    Testing --> Revision: Cần điều chỉnh
    Testing --> PendingApproval: Test thành công
    
    Revision --> Testing: Test lại
    
    PendingApproval --> Active: Quản lý duyệt
    PendingApproval --> Rejected: Quản lý từ chối
    
    Active --> Inactive: Tạm ẩn (hết NVL)
    Active --> Discontinued: Ngưng bán vĩnh viễn
    
    Inactive --> Active: Cập nhật lại menu
    
    Rejected --> [*]
    Cancelled --> [*]
    Discontinued --> [*]
    
    note right of Draft
        Món đang soạn
        Chưa hiển thị
    end note
    
    note right of Testing
        Đầu bếp thử nghiệm
        Điều chỉnh recipe
    end note
    
    note right of Active
        Món đang bán
        Hiển thị trên menu
    end note
    
    note right of Inactive
        Tạm ẩn do hết NVL
        hoặc không phù hợp mùa
    end note
```

---

## 6️⃣ State Diagram - Vòng Đời Chương Trình Khuyến Mãi

```mermaid
stateDiagram-v2
    [*] --> Draft: Tạo chương trình
    
    Draft --> PendingApproval: Gửi duyệt
    Draft --> Cancelled: Hủy
    
    PendingApproval --> Approved: Giám đốc duyệt
    PendingApproval --> Rejected: Từ chối
    
    Approved --> Scheduled: Chờ đến ngày bắt đầu
    
    Scheduled --> Active: Đến thời gian<br/>(auto trigger)
    Scheduled --> Cancelled: Hủy trước khi bắt đầu
    
    Active --> Paused: Tạm dừng
    Active --> Ended: Hết thời gian<br/>hoặc hết budget
    
    Paused --> Active: Tiếp tục
    Paused --> Ended: Kết thúc sớm
    
    Ended --> [*]
    Cancelled --> [*]
    Rejected --> [*]
    
    note right of Draft
        Đang soạn
        Chưa gửi duyệt
    end note
    
    note right of Active
        Đang áp dụng
        Khách có thể sử dụng
    end note
    
    note right of Paused
        Tạm dừng do vấn đề
        hoặc cần điều chỉnh
    end note
    
    note right of Ended
        Đã kết thúc
        Tạo báo cáo hiệu quả
    end note
```

---

## 7️⃣ ER Diagram - Mô Hình Dữ Liệu

```mermaid
erDiagram
    DISH ||--o{ DISH_INGREDIENT : contains
    DISH }o--|| DISH_CATEGORY : belongs_to
    DISH ||--o{ MENU_ITEM : appears_in
    DISH ||--o{ ORDER_ITEM : ordered_as
    
    MENU ||--o{ MENU_ITEM : contains
    
    INGREDIENT ||--o{ DISH_INGREDIENT : used_in
    
    PROMOTION ||--o{ PROMOTION_USAGE : has
    PROMOTION ||--o{ PROMOTION_DISH : applies_to
    
    VOUCHER }o--|| PROMOTION : type_of
    
    ORDER ||--o| PROMOTION_USAGE : uses
    
    DISH {
        string id PK
        string name
        string description
        string category_id FK
        decimal price
        decimal cost
        decimal profit_margin
        string image_url
        enum status
        bool available
        int popularity_score
        datetime created_at
        datetime updated_at
    }
    
    DISH_CATEGORY {
        string id PK
        string name
        string description
        int display_order
        string icon
    }
    
    DISH_INGREDIENT {
        string id PK
        string dish_id FK
        string ingredient_id FK
        decimal quantity
        string unit
        text notes
    }
    
    MENU {
        string id PK
        string name
        enum menu_type
        date valid_from
        date valid_to
        bool is_active
        text description
    }
    
    MENU_ITEM {
        string id PK
        string menu_id FK
        string dish_id FK
        int display_order
        decimal price_override
    }
    
    PROMOTION {
        string id PK
        string name
        string description
        enum promotion_type
        decimal discount_value
        decimal min_order_value
        decimal max_discount_amount
        date valid_from
        date valid_to
        int usage_limit
        int used_count
        enum status
        decimal budget
        decimal spent_amount
    }
    
    PROMOTION_DISH {
        string id PK
        string promotion_id FK
        string dish_id FK
        string category_id FK
    }
    
    PROMOTION_USAGE {
        string id PK
        string promotion_id FK
        string order_id FK
        string customer_id FK
        decimal discount_amount
        datetime used_at
    }
    
    VOUCHER {
        string id PK
        string promotion_id FK
        string code UK
        int total_quantity
        int used_quantity
        int max_uses_per_customer
        enum status
    }
```

---

## 8️⃣ Business Rules - Quy Tắc Nghiệp Vụ

### 🍽️ Quy Tắc Món Ăn

#### **Giá Cả**
1. **Giá vốn (Cost)**: Tổng giá trị nguyên liệu
   ```
   cost = SUM(ingredient.unit_price × dish_ingredient.quantity)
   ```

2. **Giá bán (Price)**: Phải > giá vốn
   ```
   price > cost
   ```

3. **Tỷ lệ lợi nhuận (Profit Margin)**:
   ```
   profit_margin = (price - cost) / price × 100%
   Khuyến nghị: ≥ 30%
   ```

4. **Lịch sử giá**: Lưu mọi thay đổi giá với timestamp

#### **Trạng Thái**
| Trạng thái | Hiển thị Menu? | Có thể đặt? |
|------------|----------------|-------------|
| **Draft** | ❌ Không | ❌ Không |
| **Testing** | ❌ Không | ❌ Không |
| **PendingApproval** | ❌ Không | ❌ Không |
| **Active** | ✅ Có | ✅ Có |
| **Inactive** | ⚠️ Xám | ❌ Không |
| **Discontinued** | ❌ Không | ❌ Không |

#### **Recipe (Công Thức)**
- Mỗi món phải có **ít nhất 1 nguyên liệu**
- Định mức phải **chính xác** để trừ kho đúng
- Có thể có **recipe thay thế** cho nguyên liệu hết

### 📋 Quy Tắc Menu

#### **Loại Menu**
| Menu Type | Mô Tả | Ví Dụ |
|-----------|-------|-------|
| **Standard** | Menu thường xuyên | Menu hàng ngày |
| **Seasonal** | Menu theo mùa | Menu hè, đông |
| **Special** | Menu sự kiện | Menu Tết, Giáng Sinh |
| **Lunch** | Menu buổi trưa | Set lunch |
| **Dinner** | Menu buổi tối | Set dinner |

#### **Quản Lý Phiên Bản**
- Có thể có **nhiều menu active** cùng lúc
- Menu có **thời gian hiệu lực** (valid_from, valid_to)
- Món có thể xuất hiện trong **nhiều menu** với giá khác nhau
- Tự động **switch menu** theo thời gian

### 🎁 Quy Tắc Khuyến Mãi

#### **Loại Khuyến Mãi**

##### **1. Discount Percent**
- Giảm theo % giá trị đơn hàng
- Có thể giới hạn **max_discount_amount**
- Ví dụ: Giảm 20%, tối đa 100,000đ

##### **2. Fixed Amount**
- Giảm số tiền cố định
- Ví dụ: Giảm 50,000đ cho đơn từ 200,000đ

##### **3. Free Item**
- Tặng món khi đạt điều kiện
- Ví dụ: Mua 2 tặng 1

##### **4. Voucher Code**
- Nhập mã để được giảm
- Giới hạn số lượng sử dụng
- Có thể giới hạn số lần/khách

#### **Điều Kiện Áp Dụng**
```javascript
function isPromotionApplicable(promotion, order) {
  // 1. Kiểm tra thời gian
  if (NOW() < promotion.valid_from || NOW() > promotion.valid_to) {
    return false;
  }
  
  // 2. Kiểm tra giá trị đơn tối thiểu
  if (order.subtotal < promotion.min_order_value) {
    return false;
  }
  
  // 3. Kiểm tra giới hạn sử dụng
  if (promotion.used_count >= promotion.usage_limit) {
    return false;
  }
  
  // 4. Kiểm tra ngân sách
  if (promotion.spent_amount >= promotion.budget) {
    return false;
  }
  
  // 5. Kiểm tra món áp dụng
  if (promotion.applicable_dishes.length > 0) {
    const hasApplicableDish = order.items.some(item =>
      promotion.applicable_dishes.includes(item.dish_id)
    );
    if (!hasApplicableDish) {
      return false;
    }
  }
  
  return true;
}
```

#### **Ưu Tiên Khuyến Mãi**
Khi có nhiều promotion áp dụng được, chọn theo thứ tự:
1. **Voucher code** (nếu khách nhập)
2. Promotion có **discount_amount cao nhất**
3. Promotion có **priority** cao hơn

#### **Không Cộng Dồn**
- Mỗi order chỉ được áp dụng **1 promotion**
- Trừ trường hợp đặc biệt được cấu hình

---

## 9️⃣ API Endpoints - Danh Sách API

### Dish Management

#### CRUD Món Ăn
```http
# Danh sách món
GET /api/dishes?category=Appetizer&status=Active

# Chi tiết món
GET /api/dishes/{dishId}

# Tạo món mới
POST /api/dishes
Body: {
  "name": "Phở Bò Đặc Biệt",
  "description": "Phở bò với đầy đủ topping",
  "category_id": "CAT-001",
  "price": 85000,
  "image": "base64_string",
  "ingredients": [
    {"ingredient_id": "ING-001", "quantity": 0.3, "unit": "kg"},
    {"ingredient_id": "ING-015", "quantity": 0.5, "unit": "kg"}
  ]
}

# Cập nhật món
PUT /api/dishes/{dishId}

# Ẩn/hiện món
PATCH /api/dishes/{dishId}/toggle-availability
```

#### Cập Nhật Giá
```http
POST /api/dishes/{dishId}/update-price
Body: {
  "new_price": 90000,
  "reason": "Tăng giá nguyên liệu"
}
Response: {
  "old_price": 85000,
  "new_price": 90000,
  "effective_from": "2025-10-22T00:00:00Z"
}
```

### Menu Management

#### Quản Lý Menu
```http
# Danh sách menu
GET /api/menus?type=Standard&active=true

# Tạo menu mới
POST /api/menus
Body: {
  "name": "Menu Hè 2025",
  "menu_type": "Seasonal",
  "valid_from": "2025-06-01",
  "valid_to": "2025-08-31",
  "dish_ids": ["DSH-001", "DSH-002", "DSH-003"]
}

# Kích hoạt menu
POST /api/menus/{menuId}/activate
```

### Promotion Management

#### CRUD Khuyến Mãi
```http
# Tạo promotion
POST /api/promotions
Body: {
  "name": "Giảm 20% Món Khai Vị",
  "promotion_type": "Percent",
  "discount_value": 20,
  "min_order_value": 200000,
  "max_discount_amount": 100000,
  "valid_from": "2025-10-22",
  "valid_to": "2025-10-31",
  "applicable_categories": ["CAT-001"],
  "usage_limit": 1000,
  "budget": 50000000
}

# Danh sách promotion
GET /api/promotions?status=Active

# Duyệt promotion
POST /api/promotions/{id}/approve

# Tạm dừng promotion
POST /api/promotions/{id}/pause
```

#### Voucher
```http
# Tạo voucher
POST /api/vouchers
Body: {
  "promotion_id": "PRM-001",
  "code": "SUMMER2025",
  "total_quantity": 100,
  "max_uses_per_customer": 1
}

# Validate voucher
GET /api/vouchers/validate?code=SUMMER2025&customer_id=CUS-001
Response: {
  "valid": true,
  "discount_amount": 50000,
  "conditions": {
    "min_order_value": 200000
  }
}
```

### Reports

#### Báo Cáo Doanh Thu Theo Món
```http
GET /api/reports/dishes/revenue?from=2025-10-01&to=2025-10-31
Response: {
  "summary": {
    "total_revenue": 150000000,
    "total_orders": 1250,
    "avg_order_value": 120000
  },
  "top_dishes": [
    {
      "dish_id": "DSH-001",
      "dish_name": "Phở Bò",
      "orders_count": 450,
      "revenue": 33750000,
      "profit": 10125000
    }
  ]
}
```

#### Báo Cáo Hiệu Quả Khuyến Mãi
```http
GET /api/reports/promotions/{promotionId}/performance
Response: {
  "promotion_name": "Giảm 20% Món Khai Vị",
  "period": "2025-10-22 to 2025-10-31",
  "usage_count": 350,
  "total_discount": 8500000,
  "revenue_with_promotion": 42500000,
  "new_customers": 75,
  "roi": 150,
  "conversion_rate": 35
}
```

---

## 🔟 Screen Mockups - Giao Diện Tham Khảo

### Màn Hình Quản Lý Món
```
┌─────────────────────────────────────────────────────────┐
│          🍽️ QUẢN LÝ MÓN ĂN                             │
├─────────────────────────────────────────────────────────┤
│ 🔍 [_________]  📁 [Tất cả ▼]  🏷️ [Active ▼]          │
│ [ ➕ Thêm Món Mới ]                                     │
├─────────────────────────────────────────────────────────┤
│                                                           │
│ ┌──────────┬────────────────────────────────────────┐  │
│ │ 🍜       │ PHỞ BÒ                                 │  │
│ │          │ Món chính • 75,000đ                    │  │
│ │  [Ảnh]   │ Giá vốn: 45,000đ • Lãi: 40%           │  │
│ │          │ 🟢 Active • 156 đơn/tháng             │  │
│ │          │ [✏️ Sửa] [👁️ Ẩn] [📊 Thống kê]       │  │
│ └──────────┴────────────────────────────────────────┘  │
│                                                           │
│ ┌──────────┬────────────────────────────────────────┐  │
│ │ 🍲       │ BÚN BÒ HUẾ                             │  │
│ │          │ Món chính • 65,000đ                    │  │
│ │  [Ảnh]   │ Giá vốn: 38,000đ • Lãi: 42%           │  │
│ │          │ 🟡 Inactive • Hết hành                │  │
│ │          │ [✏️ Sửa] [👁️ Hiện] [📊 Thống kê]      │  │
│ └──────────┴────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### Màn Hình Tạo Khuyến Mãi
```
┌─────────────────────────────────────────────────────────┐
│          🎁 TẠO CHƯƠNG TRÌNH KHUYẾN MÃI                │
├─────────────────────────────────────────────────────────┤
│                                                           │
│ Tên CT: [_______________________________________]        │
│ Loại:   [Giảm theo % ▼]                                 │
│                                                           │
│ 💰 CHIẾT KHẤU                                            │
│ Giảm:        [__20__] %                                  │
│ Giảm tối đa: [_100,000_] đ                              │
│                                                           │
│ 📅 THỜI GIAN                                             │
│ Từ:  [22/10/2025]  [00:00]                              │
│ Đến: [31/10/2025]  [23:59]                              │
│                                                           │
│ 🎯 ĐIỀU KIỆN                                             │
│ Đơn tối thiểu:  [_200,000_] đ                           │
│ Áp dụng cho:    [☑ Món khai vị]                         │
│                 [☐ Món chính]                            │
│                 [☐ Đồ uống]                              │
│                                                           │
│ 🔢 GIỚI HẠN                                              │
│ Số lượt dùng:   [_1,000_]                               │
│ Lượt/khách:     [_1_]                                    │
│ Ngân sách:      [_50,000,000_] đ                        │
│                                                           │
│ [ 💾 LƯU NHÁP ]  [ 📤 GỬI DUYỆT ]  [ ❌ Hủy ]          │
└─────────────────────────────────────────────────────────┘
```

---

## 1️⃣1️⃣ Performance Metrics - Chỉ Số Đánh Giá

### KPIs Món Ăn
- **Popularity Score**: Số lượt đặt/tháng
- **Revenue Contribution**: % đóng góp vào tổng doanh thu
- **Profit Margin**: Tỷ lệ lợi nhuận
- **Customer Rating**: Đánh giá trung bình từ khách
- **Waste Rate**: Tỷ lệ hao hụt nguyên liệu

### KPIs Khuyến Mãi
- **Usage Rate**: Tỷ lệ sử dụng so với phát hành
- **ROI**: Return on Investment
- **Customer Acquisition**: Số khách mới thu hút
- **Average Order Value**: Giá trị đơn trung bình khi dùng KM
- **Conversion Rate**: Tỷ lệ chuyển đổi

---

**[⬅️ Quay lại: Inventory & Supply](./04-INVENTORY-SUPPLY-MANAGEMENT.md)** | **[➡️ Tiếp: System & HR](./06-SYSTEM-HR-MANAGEMENT.md)**
