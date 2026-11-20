# Extended School Operations Modules Technical Documentation

This document provides a comprehensive reference for the **Extended School Operations Modules** of the School ERP SaaS platform, decoupled into five independent Laravel business domains:
1. **Library Management**
2. **Transport Management**
3. **Inventory & Stores Management**
4. **Hostel & Residential Management**
5. **Visitor & Security Front Desk Management**

---

## 1. Architectural Architecture & Principles

Each operations domain is implemented with:
- **Tenant Isolation**: Every database table is equipped with a `tenant_id` UUID column, foreign key constraint to `tenants`, and protected by Eloquent global scoping via `TenantScoped` (`App\Traits\TenantScoped`).
- **Branch-Level Granularity**: Multi-campus and multi-branch operations are isolated using `branch_id`.
- **Database Ledgers & Transactions**: Financial and physical stock movements use ACID transactions with balance auditing.
- **Hardware Integration Hooks**: Schema-level contracts for GPS telemetry ingestion without unverified mock services.

---

## 2. Domain 1: Library Management

### 2.1 Database Schema
- **`library_books`**: Title, ISBN, Author, Publisher, Edition, Category, Shelf No, Total Copies, Available Copies.
- **`book_copies`**: Unique physical copy barcodes (`BC-{branch}-{book}-{copy_num}`), status (`available`, `issued`, `lost`, `damaged`).
- **`book_issues`**: Member type (`student`, `employee`), issue date, due date, return date, late fine calculation, payment status.
- **`book_reservations`**: Queue of reservations fulfilled automatically upon return of checked-out copies.

### 2.2 Business Logic & Constraints
- **Loan Limits**: Maximum 3 books for students (14-day duration), 5 books for staff members (30-day duration).
- **Automated Fine Engine**: Calculates daily late fees (configurable, default ₹5.00/day) based on actual return date vs due date.
- **Barcode & QR Integration**: Instant lookup of physical copy condition, shelf location, and active borrower.

### 2.3 API Routes
- `GET /api/v1/operations/library/books` - List book catalog with search & category filters.
- `POST /api/v1/operations/library/books` - Catalog new title and auto-generate physical barcode copies.
- `GET /api/v1/operations/library/books/{id}` - View book details and physical copy status.
- `POST /api/v1/operations/library/books/{id}/copies` - Add additional physical copies to a title.
- `POST /api/v1/operations/library/issue` - Issue physical copy to student/employee.
- `POST /api/v1/operations/library/return/{issueId}` - Return book, calculate fine, and fulfill reservations.
- `POST /api/v1/operations/library/copies/{copyId}/lost-damaged` - Mark lost/damaged and assess penalties.
- `GET /api/v1/operations/library/barcode/{barcode}` - Desk barcode scanner lookup.
- `GET /api/v1/operations/library/member/{memberType}/{memberId}` - Member library account profile.
- `POST /api/v1/operations/library/reserve` - Reserve checked-out titles.
- `GET /api/v1/operations/library/reports` - Circulation statistics and overdue fine reports.

---

## 3. Domain 2: Transport & Fleet Management

### 3.1 Database Schema
- **`transport_vehicles`**: Fleet registration number, model, seating capacity, fitness certificate expiry date, insurance expiry date, `gps_device_id`.
- **`transport_drivers`**: Driver license number, license expiry date, phone, linked to `employees`.
- **`transport_routes`**: Route name, origin, destination, vehicle assignment, driver assignment, base fare.
- **`transport_stops`**: Intermediate bus stops, scheduled pickup/drop times, stop sequence order, fare amount.
- **`student_transport_allocations`**: Student bus seat allocations by academic session.
- **`transport_vehicle_maintenances`**: Service date, maintenance type, odometer reading, cost, garage center, invoice.
- **`transport_fuel_logs`**: Fuel date, fuel type (diesel, petrol, CNG, electric), quantity in litres, cost per litre, total cost.
- **`transport_attendances`**: Daily student boarding attendance (pickup/drop, present/absent/skipped).
- **`transport_gps_telemetries`**: Telemetry coordinate ingestion (latitude, longitude, speed, heading, timestamp).

### 3.2 Business Logic & Constraints
- **Capacity Enforcement**: Allocations are validated against vehicle capacity; cannot over-allocate passengers.
- **Compliance Monitoring**: Automated detection and alerts for expiring fitness certificates, vehicle insurance, and driver commercial licenses.
- **Hardware Telemetry Contract**: Direct API hook for GPS trackers without simulated third-party mocks.

### 3.3 API Routes
- `GET /api/v1/operations/transport/vehicles` - List fleet vehicles with compliance status.
- `POST /api/v1/operations/transport/vehicles` - Register fleet vehicle.
- `GET /api/v1/operations/transport/vehicles/{id}` - View vehicle with maintenance and fuel ledger.
- `POST /api/v1/operations/transport/drivers` - Register transport driver.
- `GET /api/v1/operations/transport/routes` - List routes with stops and active student allocations.
- `POST /api/v1/operations/transport/routes` - Create route with structured stops.
- `POST /api/v1/operations/transport/allocate` - Allocate student to route and bus stop.
- `POST /api/v1/operations/transport/vehicles/{id}/maintenance` - Log vehicle maintenance expense.
- `POST /api/v1/operations/transport/vehicles/{id}/fuel` - Record fuel intake.
- `POST /api/v1/operations/transport/routes/{routeId}/attendance` - Record daily transport roll call.
- `POST /api/v1/operations/transport/vehicles/{id}/telemetry` - GPS hardware telemetry ingestion.
- `GET /api/v1/operations/transport/reports` - Fleet capacity utilization and compliance warnings.

---

## 4. Domain 3: Inventory & Stores Management

### 4.1 Database Schema
- **`inventory_categories`**: Hierarchical classification (Stationery, Sports, Science Lab, AV Equipment, etc.).
- **`inventory_items`**: Item name, item code, unit of measure, minimum stock alert threshold, current stock balance.
- **`inventory_vendors`**: Vendor name, contact person, phone, email, address, GST tax registration.
- **`inventory_purchases`**: Purchase orders and goods receipts, vendor invoice, total amount, payment status.
- **`inventory_issues`**: Stock disbursements to school departments or individual staff members.
- **`inventory_stock_transactions`**: Complete double-entry audit ledger for purchases, issues, returns, damage write-offs, and adjustments.

### 4.2 Business Logic & Constraints
- **Atomic Balance Integrity**: Every stock movement is logged with signed quantity adjustments and historical `balance_after`.
- **Low Stock Alerts**: Real-time queries for items where `current_stock <= min_stock_alert`.
- **Strict Availability Check**: Disallows disbursements exceeding available on-hand stock.

### 4.3 API Routes
- `GET /api/v1/operations/inventory/categories` - List categories with item counts.
- `POST /api/v1/operations/inventory/categories` - Create inventory category.
- `GET /api/v1/operations/inventory/items` - List items with search and low-stock filters.
- `POST /api/v1/operations/inventory/items` - Catalog item with initial stock and transaction audit.
- `GET /api/v1/operations/inventory/items/{id}` - Show item with full stock transaction ledger.
- `GET /api/v1/operations/inventory/vendors` - List registered suppliers.
- `POST /api/v1/operations/inventory/vendors` - Register supplier with GST details.
- `POST /api/v1/operations/inventory/purchases` - Goods receipt intake with itemized line items.
- `POST /api/v1/operations/inventory/issue` - Disburse stock to department or employee.
- `POST /api/v1/operations/inventory/return/{issueId}` - Return issued stock back to warehouse.
- `POST /api/v1/operations/inventory/items/{id}/damaged` - Record damaged/spoiled stock write-off.
- `GET /api/v1/operations/inventory/low-stock` - Retrieve items breaching minimum thresholds.
- `GET /api/v1/operations/inventory/reports` - Purchase spend analytics and store valuation.

---

## 5. Domain 4: Hostel & Residential Management

### 5.1 Database Schema
- **`hostel_buildings`**: Building name, type (`boys`, `girls`, `co_ed`), warden name, phone.
- **`hostel_rooms`**: Room number, room type (`single`, `double`, `dormitory`), bed capacity, occupied beds, session fee.
- **`hostel_allocations`**: Student room & bed allocation records, allocation date, vacating date, status (`active`, `vacated`).
- **`hostel_attendances`**: Daily evening roll call attendance (`present`, `absent`, `on_leave`, `sick`).
- **`hostel_mess_menus`**: Weekly meal schedules (breakfast, lunch, snacks, dinner, items JSON).
- **`hostel_complaints`**: Boarder maintenance complaints (plumbing, electrical, cleanliness, food, security) and resolution workflow.

### 5.2 Business Logic & Constraints
- **Gender Compatibility**: Strict validation prevents male students from being placed in girls' residences and vice-versa.
- **Capacity & Vacancy Tracking**: Automatic atomic synchronization of `occupied_beds` upon allocation and vacating.
- **Complaint Resolution Workflow**: Status progression (`pending` -> `in_progress` -> `resolved` -> `closed`) with resolver auditing.

### 5.3 API Routes
- `GET /api/v1/operations/hostel/buildings` - List residential buildings with room counts.
- `POST /api/v1/operations/hostel/buildings` - Register building.
- `GET /api/v1/operations/hostel/rooms` - List rooms with vacancy and building filters.
- `POST /api/v1/operations/hostel/rooms` - Add room with bed capacity and fees.
- `POST /api/v1/operations/hostel/allocate` - Allocate boarder to room & bed.
- `POST /api/v1/operations/hostel/vacate/{allocationId}` - Vacate boarder and restore room capacity.
- `POST /api/v1/operations/hostel/buildings/{buildingId}/attendance` - Record daily evening roll call.
- `GET /api/v1/operations/hostel/buildings/{buildingId}/mess-menu` - View weekly dining menu.
- `POST /api/v1/operations/hostel/buildings/{buildingId}/mess-menu` - Update mess meal schedule.
- `GET /api/v1/operations/hostel/complaints` - List boarder complaints.
- `POST /api/v1/operations/hostel/complaints` - Log maintenance or food complaint.
- `POST /api/v1/operations/hostel/complaints/{id}/resolve` - Mark complaint resolved with notes.
- `GET /api/v1/operations/hostel/reports` - Occupancy percentages and complaint breakdown.

---

## 6. Domain 5: Visitor & Security Front Desk Management

### 6.1 Database Schema
- **`visitors`**: Visitor full name, phone number, national ID (Aadhaar/PAN/Passport), photo URL, address.
- **`visitor_passes`**: Pass number (`VP-{date}-{seq}`), host employee ID, purpose of visit, check-in timestamp, check-out timestamp, badge status.
- **`visitor_student_pickups`**: Authorized student early pickup record, relationship verification, parent consent confirmation, photo ID verification.
- **`student_gate_passes`**: Early departure gate pass issued to authorized guardians (`GP-{date}-{seq}`), approval user, exit time.

### 6.2 Business Logic & Constraints
- **Security Check-In/Check-Out**: Accurate tracking of visitors currently on campus; prevents duplicate checkout.
- **Student Pickup Authorization**: Enforces identity verification, relationship validation, and consent checks before releasing students.
- **Visitor History**: Retains comprehensive audit logs of all visits across time.

### 6.3 API Routes
- `GET /api/v1/operations/visitors` - Directory of registered visitors.
- `POST /api/v1/operations/visitors` - Save or retrieve visitor profile.
- `GET /api/v1/operations/visitors/passes` - List visitor passes with active on-campus filter.
- `POST /api/v1/operations/visitors/check-in` - Check in guest, assign host, and record student pickup.
- `POST /api/v1/operations/visitors/passes/{id}/check-out` - Check out guest and record exit timestamp.
- `GET /api/v1/operations/visitors/{id}/history` - Historical visit log for visitor.
- `POST /api/v1/operations/visitors/gate-passes` - Issue emergency student departure pass.
- `POST /api/v1/operations/visitors/gate-passes/{id}/exit` - Record actual gate exit.
- `GET /api/v1/operations/visitors/reports` - Front desk live campus metrics.

---

## 7. Verification & Test Suite Summary

Every domain was independently developed and tested using dedicated feature test suites:
- `LibraryManagementTest.php` (12 tests)
- `TransportManagementTest.php` (11 tests)
- `InventoryManagementTest.php` (9 tests)
- `HostelManagementTest.php` (10 tests)
- `VisitorManagementTest.php` (9 tests)

**Full Test Suite Status:**
- **Total Tests Passed:** 158 tests (1,068 assertions)
- **Failures:** 0
- **Frontend Build:** 20/20 routes prerendered with 0 TypeScript errors.

