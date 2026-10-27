Bạn là **Senior Laravel/Exment Developer + Tech Lead**.

Hãy thực hiện **Phase 2 – Exment Admin Development** cho dự án rebuild `bancay.vn`.

Trước khi thực hiện, bắt buộc đọc và tuân thủ:

1. **Master Plan của dự án bancay.vn**
2. **Báo cáo Phase 0 – Legacy Analysis & Backup**
3. **Báo cáo Phase 1 – Foundation & Database Design**
4. ERD/database schema Phase 1
5. Exment Mapping Phase 1
6. Legacy → New Mapping
7. Business Rule Inventory
8. Security Assessment
9. Phase 2 Readiness Report

Nếu Phase 1 có trạng thái:

```text
NOT READY
```

thì **không bắt đầu Phase 2**.

Nếu:

```text
READY WITH RISKS
```

thì được tiếp tục nhưng phải ghi rõ risk/assumption liên quan.

# 1. Mục tiêu Phase 2

Xây hoàn chỉnh khu vực **Admin/Backend bằng Exment** cho các nghiệp vụ cốt lõi.

Phase này tập trung vào:

```text
Product
Category
Product Option
Product Variant / SKU
Inventory
Order
Customer
Article
Article Category
Banner
System Settings
User / Role / Permission
```

Mục tiêu cuối cùng:

Admin có thể quản lý đầy đủ dữ liệu cần thiết để Phase 3 xây frontend Blade mà **không phải chỉnh trực tiếp database**.

Không xây frontend public trong Phase này.

---

# 2. Nguyên tắc kiến trúc

Tiếp tục sử dụng:

```text
1 Laravel application
+ Exment
+ MySQL/MariaDB
```

Không tạo backend riêng.

Không tạo API riêng chỉ để Exment sử dụng nếu không cần.

Business logic quan trọng phải tiếp tục nằm ở:

```text
Service
Action
Domain
```

không nhét trực tiếp toàn bộ vào Exment event/controller.

Luồng mong muốn:

```text
Exment Admin
     ↓
Service / Action
     ↓
Domain / Model
     ↓
Database
```

Frontend Phase 3 sau này cũng phải có thể tái sử dụng cùng Service/Domain.

---

# 3. Kiểm tra đầu vào Phase 1

Trước khi code, xác nhận:

- migrations chạy thành công
- database schema đúng với ERD
- models tồn tại
- relationships hoạt động
- Product Variant design hoạt động
- Inventory design hoạt động
- Order snapshot design hoạt động
- Order status history hoạt động
- Exment Mapping Phase 1 còn phù hợp

Nếu phát hiện schema Phase 1 chưa đủ, không tự ý sửa lớn.

Phải tạo:

```text
SCHEMA CHANGE PROPOSAL
```

ghi:

- vấn đề
- schema hiện tại
- thay đổi đề xuất
- lý do
- ảnh hưởng
- migration cần thiết

Chỉ thực hiện thay đổi nếu thực sự cần cho Phase 2.

---

# 4. Dashboard Admin

Xây dashboard quản trị cơ bản.

Hiển thị tối thiểu:

```text
Tổng sản phẩm
Sản phẩm đang hoạt động
Đơn mới
Đơn đang xử lý
Khách hàng
Bài viết
```

Nếu dữ liệu hỗ trợ, thêm:

```text
Doanh thu đơn hoàn thành
Đơn hàng gần đây
Sản phẩm sắp hết hàng
```

Không xây BI/dashboard phức tạp ở Phase này.

---

# 5. Category Management

Admin phải quản lý:

```text
Category
├── Name
├── Parent Category
├── Slug
├── Description
├── Image
├── Sort Order
├── Status
├── Meta Title
└── Meta Description
```

Hỗ trợ:

- create
- edit
- view
- search
- filter status
- filter parent
- sort
- enable/disable

Phải validation:

- name required
- slug đúng format
- slug unique theo rule Phase 1
- không được chọn chính nó làm parent
- tránh circular parent relationship nếu có hierarchy

`Circular relationship` là **quan hệ vòng, ví dụ A là cha của B nhưng B lại trở thành cha của A**.

Không cho xóa Category đang được Product sử dụng nếu điều đó phá dữ liệu.

Phải đưa ra behavior rõ:

```text
RESTRICT
```

hoặc phương án phù hợp đã xác định ở Phase 1.

---

# 6. Product Management

Đây là module quan trọng nhất.

Admin Product cần quản lý:

```text
Basic Information
SEO
Images
Options
Variants
Pricing
Inventory
Status
```

Thông tin cơ bản:

```text
Name
Slug
Category
Short Description
Description
Featured
Status
Published At
Meta Title
Meta Description
```

Có thể thêm field từ Phase 0 nếu business rule legacy yêu cầu.

Không mang toàn bộ field legacy sang nếu không còn nghiệp vụ.

---

# 7. Product Images

Admin phải:

- upload nhiều ảnh
- chọn ảnh chính
- sắp xếp ảnh
- xóa ảnh
- nhập alt text nếu cần

File phải lưu bằng Laravel filesystem/storage convention.

Không sử dụng trực tiếp kiểu legacy:

```text
upload/sp/...
```

nếu Phase 1 đã có storage strategy mới.

Validation:

- image MIME/type
- maximum size
- filename/path an toàn

Không tin extension do user cung cấp.

---

# 8. Product Options

Phải hỗ trợ tạo option cho Product.

Ví dụ:

```text
Option:
Trọng lượng

Values:
500g
1kg
2kg
```

và:

```text
Option:
Loại cây

Values:
Hoa hồng
Lan
Cây cảnh
```

Admin phải:

- thêm option
- sửa tên option
- thêm/xóa value
- thay đổi thứ tự
- validation duplicate value

Không hard-code option cụ thể.

---

# 9. Product Variants

Từ Product Options, Admin phải quản lý Variant.

Ví dụ:

```text
500g / Hoa hồng
SKU: GH-500-HH
Price: 99.000
Sale Price: 89.000
Stock: 20
```

Variant phải hỗ trợ:

```text
SKU
Option Values
Price
Sale Price
Status
Inventory
```

Validation:

- SKU unique
- price >= 0
- sale_price hợp lệ
- stock không âm nếu business rule không cho phép
- combination option không duplicate

Không lưu variant trở lại JSON như legacy.

---

# 10. Variant Generation

Nếu phù hợp, hỗ trợ generate combinations tự động.

Ví dụ:

```text
Weight:
500g
1kg

Type:
Hoa hồng
Lan
```

Sinh:

```text
500g / Hoa hồng
500g / Lan
1kg / Hoa hồng
1kg / Lan
```

Nhưng không generate lại làm mất Variant đang có dữ liệu.

Nếu update options làm ảnh hưởng variants, phải xử lý an toàn.

Ví dụ:

```text
MANUAL CONFIRMATION REQUIRED
```

cho thao tác destructive.

`Destructive` nghĩa là **thao tác có khả năng làm mất hoặc thay đổi dữ liệu quan trọng**.

---

# 11. Inventory Management

Admin phải xem và chỉnh tồn kho.

Tối thiểu:

```text
Variant
SKU
Current Stock
Reserved Stock nếu có
Available Stock
Status
```

Nếu Phase 1 chỉ hỗ trợ một kho thì không tự xây multi-warehouse.

Nếu legacy có khái niệm `kho`, kiểm tra Business Rule Inventory trước khi quyết định.

Không để stock logic nằm rải rác ở View.

Sử dụng:

```text
InventoryService
```

hoặc Action tương đương.

---

# 12. Inventory History

Nếu thiết kế Phase 1 có chuẩn bị inventory transaction/history thì implement.

Ví dụ:

```text
Stock Adjustment

+10 nhập kho
-2 điều chỉnh
-1 order
```

Nếu Phase 1 chưa thiết kế phần này và không bắt buộc, có thể đưa sang backlog.

Không tự mở rộng scope quá mức.

---

# 13. Order Management

Admin phải có danh sách order:

```text
Order Number
Customer
Phone
Total
Status
Created At
```

Filter:

```text
All
NEW
CONFIRMED
PACKING
SHIPPING
COMPLETED
CANCELLED
RETURNED
```

Thêm:

- search order number
- search customer phone
- date range nếu dễ thực hiện
- sort newest first

---

# 14. Order Detail

Order Detail phải hiển thị:

```text
Order Number

Customer
Name
Phone
Email
Address

Products
Product Name
SKU
Variant
Unit Price
Quantity
Subtotal

Subtotal
Discount
Shipping
Total

Status History
```

Phải dùng dữ liệu snapshot từ `order_items`.

Không lấy lại tên/giá hiện tại của Product để thay thế lịch sử order.

Product hiện tại chỉ dùng làm link/reference nếu còn tồn tại.

---

# 15. Order Status Workflow

Sử dụng Workflow của Exment nếu phù hợp.

Luồng cơ bản:

```text
NEW
 ↓
CONFIRMED
 ↓
PACKING
 ↓
SHIPPING
 ↓
COMPLETED
```

Ngoài ra:

```text
CANCELLED
RETURNED
```

Phải xác định transition hợp lệ.

Ví dụ không được:

```text
COMPLETED
→ NEW
```

nếu business rule không cho phép.

Mọi thay đổi status phải tạo:

```text
order_status_histories
```

ghi:

- from status
- to status
- user
- time
- note nếu có

Không update status trực tiếp mà bỏ qua history.

---

# 16. Cancellation Rules

Thiết kế rule cancel theo Business Rule Inventory.

Tối thiểu:

- đơn đã COMPLETED không cancel trực tiếp
- trạng thái transition phải hợp lệ
- cancellation ghi history

Nếu việc cancel cần hoàn tồn kho, sử dụng:

```text
OrderService
InventoryService
```

và transaction.

`Database Transaction` là **nhóm thao tác database phải thành công cùng nhau hoặc rollback cùng nhau**.

---

# 17. Customer Management

Admin phải xem:

```text
Name
Phone
Email
Status
Created At
Orders Count
```

Hỗ trợ:

- search name
- search phone
- search email
- view customer detail
- xem order history

Không bắt buộc Customer login ở Phase 2.

Không lưu password Customer nếu chưa triển khai account.

---

# 18. Article Category

Admin quản lý:

```text
Name
Slug
Parent nếu có
Status
Meta Title
Meta Description
```

Phải dựa vào Business Rule Phase 0 để quyết định có hierarchy hay không.

---

# 19. Article Management

Admin phải quản lý:

```text
Title
Slug
Category
Excerpt
Content
Featured Image
Status
Published At
Meta Title
Meta Description
```

Content editor có thể dùng editor phù hợp với Exment/project.

Không bắt buộc tiếp tục CKEditor cũ nếu không cần.

Upload ảnh phải đi qua storage mới.

---

# 20. Banner Management

Admin quản lý:

```text
Name
Image
Link
Position
Sort Order
Status
Starts At
Ends At
```

Nếu Phase 1 đã thống nhất Banner + Slider thành một entity thì dùng đúng thiết kế đó.

Không tạo lại `slider` riêng nếu không cần.

Position ví dụ:

```text
HOME_MAIN
HOME_SECONDARY
CATEGORY
```

Có thể dùng enum/config phù hợp.

Không hard-code logic rải rác.

---

# 21. System Settings

Admin quản lý các setting business thông thường:

```text
Site Name
Logo
Phone
Email
Address
Facebook
Map
Default SEO Title
Default SEO Description
```

Không cho admin quản lý trực tiếp:

```text
DB_PASSWORD
APP_KEY
SMTP_PASSWORD
API SECRET
```

Secret phải ở `.env` hoặc secret management phù hợp.

---

# 22. User Management

Sử dụng user management của Laravel/Exment phù hợp.

Admin cần:

- create user
- edit user
- activate/deactivate
- assign role
- reset password theo cơ chế an toàn

Không sử dụng MD5.

Không migrate password legacy dưới dạng hash mới giả.

Nếu cần migrate admin legacy:

```text
username/profile
→ migrate

password
→ force reset
```

hoặc strategy Phase 1 đã chọn.

---

# 23. Role / Permission

Thiết kế role theo nhu cầu thực tế.

Ví dụ:

```text
Super Admin

Content Manager

Product Manager

Order Manager
```

Permission có thể gồm:

```text
product.view
product.create
product.update
product.delete

order.view
order.update_status

article.manage

user.manage
```

Không tạo hàng trăm permission nếu chưa cần.

Áp dụng **Least Privilege (nguyên tắc cấp quyền tối thiểu cần thiết)**.

---

# 24. Legacy Role Mapping

Dựa vào Phase 0:

```text
legacy role value
→ new role
```

Ví dụ nếu legacy:

```text
1
3
5
```

được check bằng code cũ, phải xác định ý nghĩa thật trước khi mapping.

Không đoán.

Nếu không xác định được:

```text
REVIEW REQUIRED
```

---

# 25. Search & Filter

Các grid quan trọng cần search/filter hợp lý.

## Product

```text
Name
SKU
Category
Status
Featured
```

## Order

```text
Order Number
Customer
Phone
Status
Date
```

## Customer

```text
Name
Phone
Email
```

## Article

```text
Title
Category
Status
```

Không tạo search toàn bộ field nếu không có giá trị.

---

# 26. Import / Export

Ưu tiên Exment Import/Export khi phù hợp.

Tối thiểu xem xét:

```text
Product Export
Inventory Export
Order Export
Customer Export
```

Import Product chỉ implement nếu có thể validation an toàn.

Nếu import Product Variant quá phức tạp ở Phase này, có thể ghi backlog.

Không import làm duplicate SKU.

---

# 27. Validation

Mọi form Admin phải validation phía server.

Không chỉ validation JavaScript.

Ví dụ Product:

```text
name required

slug valid/unique

category valid

price numeric

sale_price valid

SKU unique
```

Order status:

```text
new status valid

transition valid
```

Image:

```text
type
size
path
```

---

# 28. Database Transaction

Dùng transaction cho nghiệp vụ nhiều bước.

Ví dụ:

```text
Create Product
+ Options
+ Variants
+ Inventory
```

hoặc:

```text
Change Order Status
+ History
+ Inventory Adjustment
```

nếu một bước lỗi:

```text
rollback toàn bộ
```

---

# 29. Concurrency

Kiểm tra các thao tác dễ xảy ra race condition.

`Race condition` là **lỗi xảy ra khi nhiều request đồng thời thay đổi cùng dữ liệu và tạo kết quả không nhất quán**.

Đặc biệt:

```text
Inventory
Order status
SKU creation
```

Không cần xây distributed locking phức tạp.

Nhưng phải sử dụng transaction/locking hợp lý nếu cần.

---

# 30. Audit / Logging

Các thao tác quan trọng nên trace được.

Tối thiểu:

```text
Order status changes

Critical inventory changes

Admin user changes
```

Nếu Exment có audit/log phù hợp thì tận dụng.

Không tự xây audit framework lớn nếu không cần.

---

# 31. Soft Delete

Không áp dụng soft delete một cách máy móc.

Đánh giá:

## Có thể phù hợp

```text
Product
Article
Customer
```

## Không được làm mất lịch sử

```text
Order
OrderItem
OrderStatusHistory
```

Nếu Product bị delete, Order Item vẫn phải tồn tại.

---

# 32. Security

Phase 2 phải đảm bảo:

- CSRF protection
- server-side validation
- authorization
- safe file upload
- không raw SQL từ input
- không hard-coded credential
- không MD5
- tránh mass assignment không kiểm soát
- không expose sensitive data
- admin route được bảo vệ authentication

Kiểm tra **IDOR – Insecure Direct Object Reference (truy cập trái phép dữ liệu chỉ bằng cách đổi ID trên URL/request)** ở các màn hình Admin.

---

# 33. Exment Custom Tables

Với mỗi entity Exment, ghi rõ:

```text
Entity

Custom Table Name

Fields

Display Field

Search Fields

List Columns

Form Fields

Relations

Validation

Permissions

Workflow

Import/Export
```

Không tạo Custom Table trùng với table Laravel nếu Exment có thể sử dụng/chia sẻ đúng kiến trúc đã thống nhất.

Nếu Exment có constraint riêng ảnh hưởng schema Phase 1, ghi rõ.

---

# 34. Exment Views

Cấu hình View/List phù hợp.

Ví dụ Product:

```text
Image
Name
SKU/Variant Count
Category
Price Range
Stock
Status
Updated At
```

Order:

```text
Order Number
Customer
Phone
Total
Status
Created At
```

Không đưa quá nhiều cột làm grid khó sử dụng.

---

# 35. UX Admin

Admin cần ưu tiên:

```text
dễ tìm

dễ filter

dễ edit

ít click không cần thiết
```

Product form nên chia section/tab nếu Exment hỗ trợ:

```text
Basic

Images

Variants

SEO
```

Không nhét một form dài hỗn loạn nếu có thể tổ chức tốt hơn.

---

# 36. Legacy Business Rule Preservation

Đối chiếu Business Rule Inventory Phase 0.

Với mỗi business rule:

```text
PRESERVED
CHANGED
DROPPED
NOT APPLICABLE
```

Nếu thay đổi rule:

```text
BUSINESS RULE CHANGE
```

phải ghi:

- legacy behavior
- new behavior
- lý do
- ảnh hưởng migration
- ảnh hưởng user

Không âm thầm thay đổi.

---

# 37. Automated Tests

Tạo test cho các use case quan trọng.

## Category

- create category
- parent validation
- slug uniqueness

## Product

- create product
- create options
- create variants
- duplicate SKU rejected
- invalid price rejected

## Inventory

- adjustment đúng
- stock không âm nếu rule yêu cầu

## Order

- list/view
- valid transition
- invalid transition rejected
- history created
- order snapshot không thay đổi

## Permission

- user không có quyền bị deny
- role đúng có thể thao tác

---

# 38. Manual UI Test

Test trên Exment UI các luồng:

```text
Login

Create Category

Create Product

Upload Images

Create Options

Generate/Create Variants

Set Price

Set Stock

Search Product

Filter Product

Create/View Customer

View Order

Change Order Status

Check Status History

Create Article

Create Banner

Permission Test
```

Chụp EVD nếu môi trường cho phép.

`EVD – Evidence` là **bằng chứng kiểm thử như ảnh chụp màn hình, log hoặc kết quả thực thi**.

---

# 39. Regression Check

Đảm bảo Phase 2 không phá:

```text
Phase 1 migrations

Models

Relationships

Services

Tests
```

Chạy toàn bộ test Phase 1 + Phase 2.

---

# 40. Performance Baseline

Không cần tối ưu cực đoan.

Nhưng phải tránh:

```text
N+1 query
```

`N+1 Query` là **lỗi thực hiện một query chính rồi thêm rất nhiều query con lặp lại cho từng record**.

Đặc biệt ở:

- Product list
- Order list
- Customer order count

Sử dụng eager loading/count aggregate phù hợp.

---

# 41. Seed/Test Data

Tạo đủ dữ liệu để test admin:

```text
Categories

Products

Product Options

Variants

Inventory

Customers

Orders

Articles

Banners
```

Không dùng dữ liệu production nhạy cảm.

---

# 42. Documentation

Cập nhật:

```text
docs/
├── exment-admin.md
├── product-management.md
├── order-workflow.md
├── permissions.md
└── phase-2-report.md
```

Có thể điều chỉnh filename theo convention project.

---

# 43. Không làm trong Phase 2

Không làm:

```text
Frontend Blade public

Homepage

Product public page

Cart

Checkout public

Customer login

Voucher

Review

Payment gateway

Shipping API

Full legacy data migration

Production cutover

DNS switch
```

Không mở rộng thành marketplace.

Không xây seller multi-tenant.

---

# 44. Completion Criteria

Phase 2 chỉ hoàn thành khi Admin có thể:

```text
Login

Manage Category

Manage Product

Manage Product Images

Manage Product Options

Manage Variants/SKU

Manage Inventory

View/Manage Orders

Change Order Status safely

View Customers

Manage Articles

Manage Banners

Manage Settings

Manage Users/Roles/Permissions
```

Và:

- validation hoạt động
- authorization hoạt động
- order history hoạt động
- inventory logic hoạt động
- test pass
- UI manual test pass
- không có blocker Critical/High chưa xử lý hoặc chưa ghi nhận

---

# 45. Báo cáo cuối Phase 2

Báo cáo theo cấu trúc:

```text
1. Executive Summary

2. Phase 1 Inputs Used

3. Exment Configuration

4. Admin Modules Implemented

5. Category Management

6. Product Management

7. Product Variant Management

8. Inventory Management

9. Order Management

10. Order Workflow

11. Customer Management

12. Content Management

13. User / Role / Permission

14. Validation

15. Security

16. Automated Tests

17. Manual UI Tests

18. Evidence

19. Performance Findings

20. Business Rule Comparison

21. Risks

22. Technical Debt

23. Phase 3 Readiness
```

Cuối báo cáo phải ghi:

```text
PHASE 3 STATUS:
READY
```

hoặc:

```text
PHASE 3 STATUS:
READY WITH RISKS
```

hoặc:

```text
PHASE 3 STATUS:
NOT READY
```

Nếu chưa READY, phải ghi blocker cụ thể.

---

# 46. Quy tắc cuối cùng

Trong Phase 2:

**ĐƯỢC PHÉP**

```text
Configure Exment

Create Admin Forms

Create Admin Views

Create Validation

Create Workflow

Create Service/Action logic

Create Tests

Create Seed/Test Data

Update Documentation
```

**KHÔNG ĐƯỢC PHÉP**

```text
Modify legacy production data

Delete legacy website

Build frontend Phase 3

Switch production

Change DNS

Run production migration

Add marketplace functionality
```

Nếu gặp vấn đề kiến trúc ngoài scope, tạo:

```text
ARCHITECTURE CHANGE PROPOSAL
```

Không tự ý thay đổi nền tảng.

**Chỉ thực hiện Phase 2 – Exment Admin Development.**