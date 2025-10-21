# 10 - Table & Order Management

> **Version:** 1.0.0 | **Last Updated:** October 21, 2025

## 📖 Tổng Quan

Module quản lý bàn và đơn hàng là core business của hệ thống nhà hàng, bao gồm: quản lý bàn ăn, phiên bàn (table sessions), đặt bàn (reservations), và đơn hàng (orders).

## 🏗 Architecture Overview

```mermaid
graph TB
    subgraph "Customer Journey"
        A[Walk-in/Reservation]
        B[Seated at Table]
        C[Order Food]
        D[Eat & Pay]
        E[Leave]
    end
    
    subgraph "System Entities"
        F[DiningTable]
        G[Reservation]
        H[TableSession]
        I[Order]
        J[OrderItem]
        K[Invoice]
    end
    
    A -->|Creates| G
    G -->|Links to| H
    B -->|Activates| H
    H -->|Uses| F
    C -->|Creates| I
    I -->|Contains| J
    D -->|Generates| K
    H -->|Has many| I
    H -->|Generates| K
    
    style A fill:#e1f5fe
    style H fill:#fff3e0
    style I fill:#f3e5f5
    style K fill:#c8e6c9
```

## 🪑 Dining Tables

### Entity Structure

```mermaid
classDiagram
    class DiningTable {
        +string id
        +int table_number
        +int capacity
        +bool is_active
        +sessions() TableSession[]
        +currentSession() TableSession
        +isAvailable() bool
    }
    
    class TableStatus {
        <<enumeration>>
        AVAILABLE
        OCCUPIED
        RESERVED
        MAINTENANCE
    }
    
    DiningTable --> TableStatus
```

### Table States

```mermaid
stateDiagram-v2
    [*] --> Available: Created
    Available --> Occupied: Customer Seated
    Available --> Reserved: Reservation Made
    Reserved --> Occupied: Customer Arrives
    Occupied --> Available: Session Ended
    Available --> Maintenance: Mark Inactive
    Reserved --> Available: Cancel Reservation
    Maintenance --> Available: Reactivate
    Occupied --> [*]: Archived
```

### API Endpoints

```http
# List tables (with status)
GET /api/dining-tables
Query: ?status=available&capacity_min=4

# Create table
POST /api/dining-tables
{
  "table_number": 5,
  "capacity": 4,
  "is_active": true
}

# Update table
PUT /api/dining-tables/{id}
{
  "capacity": 6,
  "is_active": true
}

# Get table details with current session
GET /api/dining-tables/{id}?include=currentSession

# Delete table
DELETE /api/dining-tables/{id}
```

## 📅 Reservations

### Entity Structure

```mermaid
classDiagram
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
    
    class ReservationStatus {
        <<enumeration>>
        PENDING = 0
        CONFIRMED = 1
        ARRIVED = 2
        COMPLETED = 3
        CANCELLED = 4
        NO_SHOW = 5
    }
    
    Reservation --> ReservationStatus
    Reservation --> Customer
```

### Reservation Flow

```mermaid
sequenceDiagram
    participant Customer
    participant Waiter/System
    participant Database
    participant Notification
    
    Customer->>Waiter/System: Request reservation
    Waiter/System->>Database: Check availability
    
    alt Tables available
        Database-->>Waiter/System: Available tables
        Waiter/System->>Database: Create reservation (PENDING)
        Waiter/System->>Notification: Send confirmation
        Notification->>Customer: Email/SMS confirmation
        Waiter/System-->>Customer: Reservation confirmed
        
        Note over Customer,Database: On reservation time
        
        Customer->>Waiter/System: Arrive at restaurant
        Waiter/System->>Database: Update status (ARRIVED)
        Waiter/System->>Database: Create table session
        Database-->>Waiter/System: Session created
        Waiter/System-->>Customer: Seat customer
    else No availability
        Database-->>Waiter/System: No tables
        Waiter/System-->>Customer: Suggest alternative time
    end
```

### API Endpoints

```http
# List reservations
GET /api/reservations
Query: ?date=2025-10-21&status=confirmed&customer_id=CUS123

# Create reservation
POST /api/reservations
{
  "customer_id": "CUS123ABC",
  "reserved_at": "2025-10-21T19:00:00Z",
  "number_of_people": 4,
  "notes": "Birthday celebration"
}

# Update reservation
PUT /api/reservations/{id}
{
  "status": 1,  // CONFIRMED
  "reserved_at": "2025-10-21T19:30:00Z"
}

# Cancel reservation
DELETE /api/reservations/{id}

# Mark as no-show
POST /api/reservations/{id}/no-show
```

## 🔄 Table Sessions

### Entity Structure

```mermaid
classDiagram
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
    }
    
    class SessionType {
        <<enumeration>>
        OFFLINE = 0
        ONLINE = 1
        MERGED = 2
        SPLIT = 3
    }
    
    class SessionStatus {
        <<enumeration>>
        PENDING = 0
        ACTIVE = 1
        COMPLETED = 2
        CANCELLED = 3
    }
    
    TableSession --> SessionType
    TableSession --> SessionStatus
```

### Session Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Pending: Created
    Pending --> Active: Customer Seated
    Active --> Completed: Payment Done
    Active --> Cancelled: Customer Left
    Completed --> [*]
    Cancelled --> [*]
    
    Active --> Merged: Merge with others
    Merged --> Active: Unmerge
    Active --> Split: Split into multiple
```

### Merge/Split Tables Feature

#### Merge Tables

```mermaid
sequenceDiagram
    participant Waiter
    participant API
    participant Database
    
    Note over Waiter: Customer wants to join tables
    
    Waiter->>API: POST /table-sessions/{id}/merge
    Note right of API: Request body:<br/>{session_ids: [SES2, SES3]}
    
    API->>Database: Validate sessions (must be ACTIVE)
    Database-->>API: Sessions valid
    
    API->>Database: Create new merged session
    API->>Database: Link all tables to new session
    API->>Database: Link all orders to new session
    API->>Database: Update old sessions (merged_into_session_id)
    API->>Database: Set old sessions status = COMPLETED
    
    Database-->>API: Merge successful
    API-->>Waiter: Return new merged session
```

#### Split Tables

```mermaid
sequenceDiagram
    participant Waiter
    participant API
    participant Database
    
    Note over Waiter: Customers want separate bills
    
    Waiter->>API: POST /table-sessions/{id}/split
    Note right of API: Request body:<br/>{splits: [{tables: [T1], order_items: [I1,I2]}]}
    
    API->>Database: Validate session (must be ACTIVE)
    Database-->>API: Session valid
    
    loop For each split
        API->>Database: Create new session
        API->>Database: Link tables to new session
        API->>Database: Move specified order items
        API->>Database: Recalculate order totals
    end
    
    API->>Database: Update original session
    API->>Database: Set parent references
    
    Database-->>API: Split successful
    API-->>Waiter: Return new sessions
```

### API Endpoints

```http
# List sessions
GET /api/table-sessions
Query: ?status=active&date=2025-10-21&employee_id=EMP123

# Create session (seat customer)
POST /api/table-sessions
{
  "type": 0,  // OFFLINE
  "dining_table_ids": ["TBL001", "TBL002"],
  "customer_id": "CUS123ABC",
  "employee_id": "EMP456DEF",
  "reservation_id": "RES789GHI"  // Optional
}

# Get session details
GET /api/table-sessions/{id}
Query: ?include=diningTables,orders,customer,employee

# Update session
PUT /api/table-sessions/{id}
{
  "status": 1,  // ACTIVE
  "customer_id": "CUS123ABC"
}

# Merge sessions
POST /api/table-sessions/{id}/merge
{
  "session_ids": ["SES002", "SES003"]
}

# Split session
POST /api/table-sessions/{id}/split
{
  "splits": [
    {
      "dining_table_ids": ["TBL001"],
      "order_item_ids": ["ITM001", "ITM002"]
    },
    {
      "dining_table_ids": ["TBL002"],
      "order_item_ids": ["ITM003"]
    }
  ]
}

# Unmerge session (restore original sessions)
POST /api/table-sessions/{id}/unmerge

# End session
POST /api/table-sessions/{id}/end
```

## 🍽 Orders

### Entity Structure

```mermaid
classDiagram
    class Order {
        +string id
        +string table_session_id
        +int status
        +decimal total_amount
        +tableSession() TableSession
        +orderItems() OrderItem[]
        +calculateTotal() decimal
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
        +string cancelled_reason
        +order() Order
        +dish() Dish
        +preparedBy() Employee
    }
    
    class OrderStatus {
        <<enumeration>>
        OPEN = 0
        CONFIRMED = 1
        IN_PROGRESS = 2
        COMPLETED = 3
        CANCELLED = 4
    }
    
    class OrderItemStatus {
        <<enumeration>>
        ORDERED = 0
        CONFIRMED = 1
        COOKING = 2
        READY = 3
        SERVED = 4
        CANCELLED = 5
    }
    
    Order --> OrderStatus
    Order "1" --> "*" OrderItem
    OrderItem --> OrderItemStatus
    OrderItem --> Dish
```

### Order Flow

```mermaid
sequenceDiagram
    participant Customer
    participant Waiter
    participant Kitchen
    participant System
    
    Customer->>Waiter: Request menu
    Waiter->>Customer: Show menu
    Customer->>Waiter: Order dishes
    
    Waiter->>System: Create order
    System->>System: Calculate prices
    System-->>Waiter: Order created (OPEN)
    
    Waiter->>System: Confirm order
    System->>Kitchen: Notify kitchen (CONFIRMED)
    
    Kitchen->>System: Start cooking (COOKING)
    System-->>Waiter: Status update
    
    Kitchen->>System: Food ready (READY)
    System-->>Waiter: Notify waiter
    
    Waiter->>Customer: Serve food
    Waiter->>System: Mark as served (SERVED)
    System-->>Waiter: Updated
    
    Note over Customer: Continue eating...
```

### Order Item State Machine

```mermaid
stateDiagram-v2
    [*] --> Ordered: Created
    Ordered --> Confirmed: Waiter confirms
    Confirmed --> Cooking: Kitchen starts
    Cooking --> Ready: Food ready
    Ready --> Served: Delivered to table
    Served --> [*]
    
    Ordered --> Cancelled: Customer cancels
    Confirmed --> Cancelled: Kitchen out of stock
    Cancelled --> [*]
```

### API Endpoints

```http
# List orders
GET /api/orders
Query: ?table_session_id=SES123&status=confirmed&date=2025-10-21

# Create order
POST /api/orders
{
  "table_session_id": "SES123ABC",
  "items": [
    {
      "dish_id": "DSH001",
      "quantity": 2,
      "notes": "No onions"
    },
    {
      "dish_id": "DSH002",
      "quantity": 1
    }
  ]
}

# Get order details
GET /api/orders/{id}
Query: ?include=orderItems.dish,tableSession

# Update order
PUT /api/orders/{id}
{
  "status": 1  // CONFIRMED
}

# Add items to order
POST /api/orders/{id}/items
{
  "items": [
    {
      "dish_id": "DSH003",
      "quantity": 1
    }
  ]
}

# Update order item status
PUT /api/order-items/{id}/status
{
  "status": 2,  // COOKING
  "prepared_by": "EMP123ABC"
}

# Cancel order item
POST /api/order-items/{id}/cancel
{
  "reason": "Out of stock"
}

# Delete order (before confirmation)
DELETE /api/orders/{id}
```

## 📊 Business Logic

### Total Calculation

```php
// Order total calculation
class Order extends BaseModel
{
    public function calculateTotal(): float
    {
        return $this->orderItems()
            ->whereNotIn('status', [OrderItemStatus::CANCELLED])
            ->sum('total_price');
    }
    
    // Auto-update on item changes
    protected static function booted()
    {
        static::updating(function ($order) {
            $order->total_amount = $order->calculateTotal();
        });
    }
}

// OrderItem total calculation
class OrderItem extends BaseModel
{
    protected static function booted()
    {
        static::saving(function ($item) {
            $item->total_price = $item->price * $item->quantity;
        });
    }
}
```

### Table Availability Check

```php
class DiningTable extends BaseModel
{
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        // Check if table has active session
        return !$this->currentSession()->exists();
    }
    
    public function currentSession()
    {
        return $this->belongsToMany(TableSession::class)
            ->wherePivotIn('status', [
                SessionStatus::PENDING,
                SessionStatus::ACTIVE
            ]);
    }
}
```

### Reservation Conflict Detection

```php
class Reservation extends BaseModel
{
    public static function hasConflict(
        Carbon $reservedAt,
        int $durationMinutes = 120,
        int $numberOfPeople = 1
    ): bool {
        $startTime = $reservedAt;
        $endTime = $reservedAt->copy()->addMinutes($durationMinutes);
        
        // Check overlapping reservations
        $conflicts = self::where('status', '!=', ReservationStatus::CANCELLED)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('reserved_at', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime) {
                        $q->where('reserved_at', '<=', $startTime)
                          ->whereRaw('DATE_ADD(reserved_at, INTERVAL 120 MINUTE) >= ?', [$startTime]);
                    });
            })
            ->count();
            
        // Check if we have enough capacity
        $availableTables = DiningTable::where('is_active', true)
            ->where('capacity', '>=', $numberOfPeople)
            ->count();
            
        return $conflicts >= $availableTables;
    }
}
```

## 📈 Statistics & Reports

```http
# Table utilization
GET /api/statistics/tables
Query: ?from=2025-10-01&to=2025-10-31

Response:
{
  "data": {
    "total_tables": 20,
    "average_utilization": 75.5,  // %
    "most_popular_tables": [1, 5, 10],
    "least_popular_tables": [15, 18],
    "peak_hours": ["12:00-14:00", "19:00-21:00"]
  }
}

# Order statistics
GET /api/statistics/orders
Query: ?from=2025-10-01&to=2025-10-31

Response:
{
  "data": {
    "total_orders": 1250,
    "total_revenue": 125000000,
    "average_order_value": 100000,
    "most_ordered_dishes": [
      {"dish_id": "DSH001", "name": "Phở", "count": 320},
      {"dish_id": "DSH005", "name": "Bún chả", "count": 280}
    ]
  }
}
```

## 🔔 Notifications (Future Enhancement)

```mermaid
graph LR
    A[Event] --> B{Notification Type}
    B -->|Kitchen| C[Kitchen Display System]
    B -->|Waiter| D[Mobile App/Tablet]
    B -->|Customer| E[SMS/Email]
    
    C --> F[Order CONFIRMED]
    C --> G[Order READY]
    D --> H[Table Seated]
    D --> I[Order Item READY]
    E --> J[Reservation Confirmed]
    E --> K[Table Ready]
```

---

## 🔗 Related Documents

- **Previous**: [08-AUTHORIZATION.md](./08-AUTHORIZATION.md)
- **Next**: [11-MENU-DISH-MANAGEMENT.md](./11-MENU-DISH-MANAGEMENT.md)
- **See also**: [03-DATA-MODEL.md](./03-DATA-MODEL.md) - Table/Order models
- **See also**: [13-BILLING-PAYMENT.md](./13-BILLING-PAYMENT.md) - Invoice generation

---

**📅 Last Updated:** October 21, 2025  
**👤 Author:** Development Team
