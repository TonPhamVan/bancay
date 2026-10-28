Bạn là **Senior Laravel/Exment Architect + Tech Lead + QA Reviewer**.

Dự án `bancay.vn` đã thực hiện xong:

```text
Phase 0 – Legacy Analysis
Phase 1 – Foundation & Database Design
Phase 2 – Exment Admin
Phase 3 – Frontend Blade
```

Tuy nhiên trong quá trình thực hiện, một hoặc nhiều Phase trước có trạng thái:

```text
READY WITH RISKS
```

và Phase tiếp theo đã được thực hiện trước khi xử lý hết các risk đó.

Nhiệm vụ hiện tại là:

**Rà soát, hợp nhất và xử lý toàn bộ risk còn tồn tại của Phase 1, 2 và 3 trước khi bắt đầu Phase 4 – Data Migration.**

Không chạy lại toàn bộ Phase 1/2/3 từ đầu.

Không bắt đầu Phase 4 trong task này.

# 1. Đọc toàn bộ context hiện tại

Bắt buộc đọc:

- Master Plan
- Phase 0 Report
- Phase 1 Report
- Phase 2 Report
- Phase 3 Report
- Risk sections của từng Phase
- Technical Debt
- Test Results
- Architecture docs
- Database docs
- ERD
- Exment mapping
- source code hiện tại
- test hiện tại

Không chỉ dựa vào summary.

Phải đối chiếu report với source thực tế.

# 2. Tạo Consolidated Risk Register

Gộp toàn bộ risk của:

```text
Phase 1
Phase 2
Phase 3
```

thành một bảng duy nhất:

| Risk ID | Origin Phase | Severity | Nội dung | Evidence | Current Status | Impact |
|---|---|---|---|---|---|---|

Severity:

```text
Critical
High
Medium
Low
Info
```

# 3. Không giả định risk cũ vẫn còn

Vì Phase 2 hoặc Phase 3 có thể đã vô tình xử lý risk từ Phase trước.

Với từng risk, kiểm tra source hiện tại và kết luận:

```text
STILL EXISTS
ALREADY RESOLVED
PARTIALLY RESOLVED
FALSE POSITIVE
NO LONGER RELEVANT
NEEDS FIX
```

Chỉ fix những risk còn tồn tại thực tế.

# 4. Ưu tiên theo dependency

Kiểm tra theo thứ tự:

```text
Phase 1 architecture/database
        ↓
Phase 2 Admin/Exment
        ↓
Phase 3 Frontend
```

Nếu Phase 1 có lỗi kiến trúc:

không chỉ sửa symptom ở Phase 3.

Phải sửa tại layer đúng.

Ví dụ:

```text
Product Variant schema sai
```

thì phải xử lý schema/domain trước, sau đó sửa:

```text
Exment Admin
Frontend
Tests
```

tương ứng.

# 5. Kiểm tra Phase 1 Risks

Ưu tiên:

```text
Database schema

Foreign keys

Indexes

Product / Variant design

Inventory

Order snapshot

Order status history

Customer relationship

SEO fields

Legacy mapping

Exment compatibility
```

Đặc biệt xác minh:

- Variant có normalize đúng không
- SKU unique đúng không
- inventory có gắn đúng variant không
- Order Item có snapshot giá không
- xóa Product có ảnh hưởng Order history không
- legacy mapping có đủ để Phase 4 migrate không

# 6. Kiểm tra Phase 2 Risks

Ưu tiên:

```text
Exment CRUD

Product management

Variant management

Inventory management

Order workflow

Permission

Validation

File upload

Admin security
```

Xác minh:

- Admin có thể tạo Product hoàn chỉnh
- Variant/SKU hoạt động
- Inventory cập nhật đúng
- Order status transition hợp lệ
- Status history được ghi
- Permission hoạt động
- Không bypass Service/Domain bằng raw DB update

# 7. Kiểm tra Phase 3 Risks

Ưu tiên:

```text
Home

Category

Search

Product Detail

Variant Selection

Cart

Checkout

Order Creation

Inventory Validation

SEO

Responsive

Security
```

Core flow bắt buộc:

```text
Home
→ Category
→ Product
→ Select Variant
→ Add Cart
→ Checkout
→ Create Order
→ Order Success
```

phải chạy end-to-end.

# 8. Kiểm tra Cross-Phase Integration

Đây là phần quan trọng nhất.

Test luồng:

```text
Exment Admin
→ tạo Category
→ tạo Product
→ tạo Options
→ tạo Variants
→ set Price
→ set Stock
```

sau đó:

```text
Frontend
→ hiển thị Product
→ chọn đúng Variant
→ hiển thị đúng Price
→ Add Cart
→ Checkout
→ Create Order
```

sau đó quay lại:

```text
Exment Admin
→ xem Order
→ Order Item đúng
→ Variant đúng
→ Price snapshot đúng
→ Quantity đúng
→ Status đúng
```

# 9. Kiểm tra Inventory Consistency

Đặc biệt test:

```text
Stock = 10
Order quantity = 2
```

xác nhận behavior theo business rule hiện tại.

Kiểm tra:

- stock/reserved stock
- out-of-stock
- invalid quantity
- concurrent order nếu đã có handling
- cancel order có restore stock hay không

Không sửa rule tùy ý.

Đối chiếu Master Plan/Business Rule.

# 10. Kiểm tra Order Integrity

Xác nhận:

```text
Order
OrderItem
OrderStatusHistory
```

hoạt động đúng.

Test:

```text
Product price = 100.000
Create Order
Change Product price = 150.000
```

Order cũ vẫn phải:

```text
100.000
```

Nếu không:

```text
Critical Risk
```

# 11. Kiểm tra Security tổng thể

Review lại:

```text
SQL Injection
XSS
CSRF
IDOR
Authentication
Authorization
Password
File Upload
Price Tampering
Variant Tampering
Quantity Tampering
Order Access
```

Đặc biệt frontend:

không được tin:

```text
price
subtotal
total
stock
```

gửi từ browser.

Server phải tính lại.

# 12. Kiểm tra Test Gaps

Tổng hợp:

```text
PASS
FAIL
NOT TESTED
BLOCKED
```

cho toàn bộ Phase 1–3.

Mọi core case `NOT TESTED` phải test bây giờ nếu môi trường cho phép.

Nếu không thể test:

ghi rõ:

```text
MANUAL TEST REQUIRED
```

kèm lý do.

# 13. Fix Strategy

Chỉ fix:

```text
Confirmed Risk
```

Không refactor toàn bộ project.

Ưu tiên:

```text
Correctness
Security
Data Integrity
Architecture
Regression
```

sau đó mới đến:

```text
Code Style
Minor Refactor
Optimization
```

# 14. Không phá chức năng đã chạy

Sau mỗi fix:

chạy regression tương ứng.

Sau khi xử lý xong tất cả:

chạy toàn bộ:

```text
Phase 1 tests
+
Phase 2 tests
+
Phase 3 tests
```

# 15. Database Changes

Nếu cần sửa schema đã có:

không sửa migration cũ tùy tiện nếu project đã có dữ liệu dev/staging phụ thuộc.

Ưu tiên tạo migration mới.

Sau đó test:

```text
migrate
rollback nếu phù hợp
fresh migrate trên test DB
foreign key
relationships
```

# 16. Documentation

Cập nhật lại:

```text
architecture.md

database-design.md

exment-admin.md

frontend-architecture.md

risk-register.md

phase-3-report.md
```

hoặc các tài liệu tương ứng đang tồn tại.

Không để report cũ nói risk vẫn còn nếu đã fix.

# 17. Phân loại risk cuối cùng

Mỗi risk phải kết thúc bằng một trạng thái:

```text
RESOLVED

FALSE POSITIVE

ALREADY RESOLVED

ACCEPTED

DEFERRED

BLOCKED
```

`ACCEPTED` hoặc `DEFERRED` phải có:

```text
Reason
Impact
Target Phase
```

# 18. Điều kiện để sang Phase 4

Phase 4 là Data Migration nên yêu cầu cao về data integrity.

Chỉ cho phép:

```text
PHASE 4 STATUS: READY
```

khi:

- schema ổn định
- Product/Variant design ổn định
- Inventory ổn định
- Order structure ổn định
- Exment Admin hoạt động
- Frontend core flow hoạt động
- legacy mapping đủ rõ
- không còn Critical risk
- không còn High risk ảnh hưởng migration
- regression pass

Nếu còn risk liên quan dữ liệu/migration:

```text
PHASE 4 STATUS: NOT READY
```

# 19. Không cần đạt zero-risk tuyệt đối

Không cần fix mọi:

```text
Low Risk
Minor UI issue
Optional refactor
Minor technical debt
```

nếu không ảnh hưởng Phase 4.

Có thể defer nhưng phải ghi rõ.

# 20. Output cuối cùng

Trả báo cáo:

```text
1. Executive Summary

2. Phase 1 Risks Reviewed

3. Phase 2 Risks Reviewed

4. Phase 3 Risks Reviewed

5. Consolidated Risk Register

6. Risks Already Resolved

7. Risks Fixed Now

8. Architecture Changes

9. Database Changes

10. Security Fixes

11. Tests Added

12. Regression Results

13. Cross-Phase Integration Test

14. Remaining Risks

15. Deferred Technical Debt

16. Phase 4 Readiness
```

Cuối cùng chỉ trả một trong:

```text
PHASE 4 STATUS: READY
```

hoặc:

```text
PHASE 4 STATUS: READY WITH ACCEPTED LOW RISKS
```

hoặc:

```text
PHASE 4 STATUS: NOT READY
```

Nếu `NOT READY`, liệt kê chính xác blocker cần xử lý.

**Không bắt đầu Phase 4 – Data Migration trong task này.**