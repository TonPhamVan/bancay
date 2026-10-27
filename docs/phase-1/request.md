Bạn là **Senior Laravel/Exment Architect + Tech Lead**.

Hãy thực hiện **Phase 1 – Foundation & Database Design** cho dự án rebuild `bancay.vn`.

Trước khi thực hiện, bắt buộc đọc và tuân thủ:

1. **Master Plan của dự án bancay.vn**
2. **Báo cáo Phase 0 – Legacy Analysis & Backup**
3. Các tài liệu:
   - Legacy Architecture
   - Functional Inventory
   - Business Rule Inventory
   - Database Inventory
   - Legacy → New Mapping
   - Migration Scope
   - Security Assessment
   - SEO/URL Inventory
   - Phase 1 Readiness Report

Không được tự giả định business rule nếu Phase 0 đã có dữ liệu xác minh.

Nếu Phase 0 đang ở trạng thái:

```text
NOT READY
```

thì **không bắt đầu Phase 1**. Hãy báo blocker.

Nếu:

```text
READY WITH RISKS
```

thì được tiếp tục nhưng phải ghi rõ các assumption/risk liên quan.

# 1. Mục tiêu Phase 1

Xây nền móng kỹ thuật cho hệ thống mới:

```text
Laravel + Exment
+ MySQL/MariaDB
+ Modular Monolith
```

Phase này tập trung vào:

- project foundation
- kiến trúc source
- domain model
- database design
- migrations
- models
- relationships
- service/domain structure
- Exment data model mapping
- chuẩn bị migration dữ liệu legacy
- chuẩn bị khả năng API/frontend tách sau này

**Không xây frontend Blade.**

**Không làm Cart/Checkout hoàn chỉnh.**

**Không làm UI Shopee.**

**Không migrate dữ liệu production thật.**

---

# 2. Kiến trúc bắt buộc

Giai đoạn hiện tại vẫn là:

```text
1 Git repository
1 Laravel application
1 deployment unit
1 database
```

Sử dụng:

**Modular Monolith** – một ứng dụng duy nhất nhưng phân tách nghiệp vụ rõ ràng.

Không tạo:

- microservice
- repository frontend riêng
- backend API riêng chỉ để phục vụ Blade
- kiến trúc phức tạp không cần thiết

Luồng xử lý định hướng:

```text
Route
 ↓
Controller
 ↓
Service / Action
 ↓
Domain / Model
 ↓
Database
```

Business logic không được phụ thuộc Blade.

Thiết kế phải cho phép tương lai:

```text
Blade Controller
       ↓
    Service
       ↑
API Controller
       ↑
Next.js / Mobile
```

mà không phải viết lại business logic.

---

# 3. Khởi tạo Laravel + Exment Foundation

Kiểm tra project hiện tại trước khi thay đổi.

Thiết lập:

- Laravel version phù hợp với Exment hiện tại
- Exment installation/configuration
- PHP compatibility
- database connection
- timezone
- locale
- filesystem/storage
- logging
- queue foundation nếu cần
- cache foundation
- session
- `.env.example`
- `.gitignore`
- coding convention

Không đưa credential thật vào repository.

Kiểm tra:

```text
php artisan about
php artisan migrate
```

và các command Exment cần thiết.

Không tự ý nâng major version dependency nếu không cần.

---

# 4. Cấu trúc source

Đề xuất cấu trúc rõ ràng, nhưng tránh over-engineering.

Có thể sử dụng:

```text
app/
├── Domain/
│   ├── Catalog/
│   ├── Commerce/
│   ├── Customer/
│   └── Content/
│
├── Services/
│
├── Actions/
│
├── Models/
│
├── Http/
│   ├── Controllers/
│   │   ├── Frontend/
│   │   └── Api/
│   └── Requests/
│
└── Support/
```

Hoặc cấu trúc khác nếu phù hợp hơn với Exment/Laravel hiện tại.

Nhưng phải giải thích lý do.

Không tạo hàng chục interface/repository nếu chưa cần.

---

# 5. Domain Model

Thiết kế tối thiểu các domain/entity sau.

## Catalog

```text
Category
Product
ProductImage
ProductOption
ProductOptionValue
ProductVariant
Inventory
```

## Commerce

```text
Order
OrderItem
OrderStatusHistory
```

Chuẩn bị khả năng mở rộng sau:

```text
Cart
Voucher
Payment
Shipping
```

nhưng không cần implement đầy đủ Phase 1.

## Customer

```text
Customer
CustomerAddress
```

## Content

```text
Article
ArticleCategory
Banner
```

## System

```text
Setting
```

User/Role/Permission ưu tiên sử dụng cơ chế phù hợp của Laravel/Exment thay vì tạo hệ thống duplicate không cần thiết.

---

# 6. Category Design

Category phải hỗ trợ cây cha/con.

Tối thiểu:

```text
id
parent_id
name
slug
description
image
sort_order
status
meta_title
meta_description
created_at
updated_at
```

Nếu cần soft delete phải giải thích lý do.

Cần index phù hợp cho:

- parent_id
- slug
- status
- sort_order nếu cần

Không dùng recursive architecture quá phức tạp nếu dữ liệu hiện tại chỉ có vài cấp.

---

# 7. Product Design

Product là entity trung tâm.

Không copy nguyên `tblsanpham`.

Đề xuất:

```text
products
```

tối thiểu có:

```text
id
category_id
name
slug
short_description
description
status
featured
meta_title
meta_description
published_at
created_at
updated_at
```

Nếu business rule Phase 0 yêu cầu thêm field thì bổ sung dựa trên evidence.

Không lưu price/stock chính trực tiếp trong Product nếu hệ thống cần variant thực sự.

---

# 8. Product Image

Thiết kế:

```text
product_images
```

Ví dụ:

```text
id
product_id
path
alt_text
sort_order
is_primary
created_at
updated_at
```

Phải hỗ trợ:

- nhiều ảnh
- ảnh chính
- thứ tự ảnh
- alt text cho SEO/accessibility

Không lưu danh sách ảnh JSON như legacy nếu không có lý do đặc biệt.

---

# 9. Product Options & Variants

Legacy system hiện có variant JSON.

Hệ thống mới phải normalize.

Mục tiêu hỗ trợ kiểu:

```text
Product:
Phân bón GreenHome

Option 1:
Trọng lượng

Values:
500g
1kg
2kg

Option 2:
Loại cây

Values:
Hoa hồng
Lan
Cây cảnh
```

Variant:

```text
500g / Hoa hồng
SKU: GH-500-HH
Price: 99.000
Stock: 20
```

Thiết kế tối thiểu:

```text
product_options

product_option_values

product_variants

product_variant_values
```

hoặc thiết kế equivalent tốt hơn.

Product Variant cần có:

```text
id
product_id
sku
price
sale_price
status
created_at
updated_at
```

Không hard-code:

```text
option1
option2
```

nếu một thiết kế normalized đơn giản có thể hỗ trợ mở rộng tốt hơn.

Tuy nhiên không over-engineering cho unlimited complex variants.

Mục tiêu thực tế:

- hiện tại hỗ trợ tốt 1–2 option
- kiến trúc không khóa đường mở rộng sau này

---

# 10. Inventory

Tách tồn kho khỏi Product nếu phù hợp.

Có thể thiết kế:

```text
inventories
```

với:

```text
id
product_variant_id
quantity
reserved_quantity
created_at
updated_at
```

Nếu Phase 0 cho thấy legacy có khái niệm kho/location thì phải xem xét.

Nếu hệ thống hiện tại chỉ cần một kho:

- không cần xây multi-warehouse phức tạp ngay
- nhưng tránh thiết kế khiến tương lai không thể mở rộng

Giải thích quyết định.

---

# 11. Pricing Rules

Cần thống nhất:

```text
price
sale_price
```

Quy định:

```text
sale_price nullable
sale_price <= price
```

nếu business rule phù hợp.

Không dùng floating point cho tiền.

Sử dụng:

```text
DECIMAL
```

hoặc integer theo đơn vị tiền nhỏ nhất nếu kiến trúc thống nhất như vậy.

Giải thích lựa chọn.

---

# 12. Customer

Thiết kế:

```text
customers
```

tối thiểu:

```text
id
name
email
phone
status
created_at
updated_at
```

Customer Phase 1 chưa bắt buộc phải có login.

Tách:

```text
customer_addresses
```

để chuẩn bị cho account/checkout sau này.

Không buộc Order phụ thuộc customer account vì phải hỗ trợ guest checkout.

---

# 13. Order Design

Đây là phần bắt buộc phải thiết kế cẩn thận.

Tạo:

```text
orders
order_items
order_status_histories
```

## orders

Tối thiểu:

```text
id
order_number
customer_id nullable

customer_name
customer_email
customer_phone

shipping_address
shipping_note

subtotal
discount_amount
shipping_fee
total

status

created_at
updated_at
```

Có thể normalize address thêm nếu hợp lý nhưng không làm phức tạp quá mức.

## order_items

Phải snapshot dữ liệu khi mua:

```text
product_id nullable
product_variant_id nullable

product_name
sku
variant_description

unit_price
quantity
subtotal
```

Không được tính lại giá order lịch sử từ Product hiện tại.

Ví dụ:

```text
Ngày mua:
99.000

Giá sản phẩm hiện tại:
120.000

Order lịch sử:
vẫn là 99.000
```

---

# 14. Order Status

Chuẩn bị status:

```text
NEW
CONFIRMED
PACKING
SHIPPING
COMPLETED
CANCELLED
RETURNED
```

Không dùng magic number khó hiểu nếu có giải pháp enum/value object phù hợp với Laravel version.

Thiết kế:

```text
order_status_histories
```

ghi tối thiểu:

```text
order_id
from_status
to_status
note
changed_by
created_at
```

Nếu `changed_by` cần liên kết user Exment thì thiết kế phù hợp.

---

# 15. Article / Content

Tạo:

```text
article_categories
articles
```

Article cần:

```text
title
slug
excerpt
content
image
status
published_at
meta_title
meta_description
```

Category bài viết hỗ trợ cha/con nếu business rule legacy yêu cầu; nếu không thì giữ đơn giản.

---

# 16. Banner

Tạo:

```text
banners
```

Tối thiểu:

```text
name
image
link
position
sort_order
status
starts_at
ends_at
```

Nếu legacy không cần thời gian hiệu lực thì có thể nullable.

Không tách Slider/Banner thành hai hệ thống riêng nếu bản chất nghiệp vụ giống nhau và có thể thống nhất sạch sẽ.

Phải giải thích quyết định merge hoặc giữ riêng.

---

# 17. Settings

Tạo cơ chế lưu cấu hình website:

```text
settings
```

Ví dụ:

```text
site_name
logo
contact_phone
email
address
facebook
map
seo defaults
```

Không hard-code config business trong source.

Không dùng bảng settings để chứa credential quan trọng.

Secret phải nằm ở environment/config phù hợp.

---

# 18. SEO Design

Product, Category, Article cần hỗ trợ:

```text
slug
meta_title
meta_description
```

Canonical URL có thể generate theo route nếu không cần lưu DB.

Không thêm field DB nếu có thể derive an toàn.

Slug uniqueness phải xác định rõ:

- global unique
hoặc
- unique theo entity

Phải giải thích.

Chuẩn bị cho Phase 5 thực hiện:

```text
Legacy URL
→ New URL
→ 301
```

Nhưng chưa implement redirect đầy đủ ở Phase 1.

---

# 19. Database Conventions

Thống nhất:

- naming
- primary key
- timestamps
- nullable rules
- foreign key
- delete behavior
- indexes

Không dùng cascade delete bừa bãi với dữ liệu nghiệp vụ quan trọng như Order.

Ví dụ:

```text
Xóa Product
```

không được làm mất lịch sử `order_items`.

Đề xuất rõ:

```text
RESTRICT
SET NULL
CASCADE
```

cho từng relationship quan trọng.

---

# 20. Index Strategy

Xác định index cho các luồng phổ biến:

```text
product slug
product status
category
SKU
order number
order status
customer phone
customer email
article slug
published_at
```

Không tạo index mọi field.

Phải giải thích index quan trọng.

---

# 21. Model Relationships

Implement Laravel Models và relationship.

Ví dụ:

```text
Category
hasMany Products

Product
belongsTo Category
hasMany Images
hasMany Options
hasMany Variants

Variant
belongsTo Product
hasMany OptionValues
hasOne Inventory

Order
hasMany OrderItems
hasMany StatusHistories

Customer
hasMany Orders
hasMany Addresses
```

Sử dụng casts phù hợp.

Không nhét business logic phức tạp vào model nếu Service/Action phù hợp hơn.

---

# 22. Service / Domain Structure

Tạo foundation tối thiểu.

Ví dụ:

```text
ProductService
OrderService
InventoryService
```

hoặc Action classes:

```text
CreateProduct
CreateOrder
ChangeOrderStatus
AdjustInventory
```

Không cần implement toàn bộ chức năng Phase sau.

Mục tiêu là chứng minh kiến trúc hoạt động.

Ví dụ có thể implement skeleton/use case cơ bản cho:

```text
Create Product

Create Variant

Create Order

Change Order Status
```

nhưng không làm UI.

---

# 23. Validation

Tạo Form Request hoặc validation layer cho các use case mẫu.

Ví dụ:

```text
StoreProductRequest
StoreOrderRequest
```

Không đọc raw:

```text
$_POST
$_GET
```

Không nối user input trực tiếp vào SQL.

---

# 24. Authentication / Password

Không migrate logic MD5 vào hệ thống mới.

Sử dụng cơ chế Laravel/Exment chuẩn:

```text
Hash
```

Nếu cần giữ legacy admin accounts, chỉ thiết kế migration strategy.

Ví dụ:

```text
Force password reset
```

hoặc phương án tương đương.

Không lưu password legacy plaintext.

---

# 25. Exment Mapping

Dựa trên schema mới, xác định entity nào sẽ quản lý trực tiếp bằng Exment.

Tạo bảng:

```text
Domain Entity

Database Table

Exment Custom Table / Built-in

Admin CRUD?

Search?

Filter?

Workflow?

Import/Export?

Notes
```

Tối thiểu:

```text
Product
Category
Product Variant
Inventory
Order
Customer
Article
Banner
```

Nếu entity nào không phù hợp quản lý trực tiếp bằng Custom Table thì giải thích.

Không duplicate dữ liệu chỉ để Exment nhìn thấy.

---

# 26. Legacy → New Data Mapping

Sử dụng kết quả Phase 0.

Tạo mapping chi tiết.

Ví dụ:

```text
tblsanpham.id
→ products.legacy_id hoặc migration reference

tblsanpham.title
→ products.name

tblsanpham.alias
→ products.slug
```

Đặc biệt:

```text
tblsanpham.phanloai JSON
```

phải mapping sang:

```text
product_options
product_option_values
product_variants
product_variant_values
inventory
```

Phải ghi rõ transformation rule.

Chưa migrate production data.

---

# 27. Legacy ID Strategy

Quyết định có giữ ID cũ hay không.

Các lựa chọn:

```text
Preserve IDs

hoặc

Generate new IDs + legacy_id
```

Đánh giá ưu/nhược điểm dựa trên:

- SEO URL cũ
- relationships
- migration complexity
- rollback/debug

Đưa ra quyết định rõ ràng.

Không quyết định tùy tiện.

---

# 28. Migration Scripts Foundation

Có thể chuẩn bị command/skeleton cho Phase 4 như:

```text
php artisan legacy:analyze
php artisan legacy:migrate-products --dry-run
```

nhưng Phase 1 **không chạy migrate dữ liệu production thật**.

Nếu tạo migration tooling, bắt buộc hỗ trợ:

```text
--dry-run
```

và logging.

`Dry-run` nghĩa là **chạy thử để xem kết quả dự kiến mà chưa ghi thay đổi thật**.

---

# 29. Seeders / Factories

Tạo dữ liệu test tối thiểu.

Ví dụ:

```text
Category

Product

2 Product Options

4 Variants

Inventory

Customer

Order

Order Items
```

Mục tiêu để kiểm tra relationships và schema.

Không copy dữ liệu production thật vào test.

---

# 30. Automated Tests

Phase 1 cần ít nhất các test quan trọng.

## Database

- migrations chạy từ database rỗng
- rollback được
- migrate lại được
- FK hợp lệ

## Product

- tạo product
- tạo variant
- SKU unique
- relationship đúng

## Inventory

- variant có inventory

## Order

- tạo order
- order item snapshot giá
- đổi giá product không ảnh hưởng order cũ
- status history hoạt động

## Validation

- dữ liệu không hợp lệ bị reject

Không cần test frontend.

---

# 31. Static / Code Quality Check

Chạy các check hiện có trong project.

Ví dụ tùy project:

```text
PHPUnit / Pest

Laravel Pint

PHPStan / Larastan
```

Không bắt buộc thêm tool mới nếu project chưa dùng và không cần thiết.

Nếu thêm dependency development, giải thích lý do.

---

# 32. Security Baseline

Xác nhận:

- không có MD5 mới
- không raw SQL từ user input
- không hard-code credential
- mass assignment được kiểm soát
- validation tồn tại
- secret không commit
- upload design chuẩn bị kiểm soát file type/path
- authorization foundation phù hợp

Không cần penetration test đầy đủ ở Phase này.

---

# 33. ERD

Tạo ERD rõ ràng.

Tối thiểu thể hiện:

```text
Category
  ↓
Product
  ├── ProductImage
  ├── ProductOption
  │       ↓
  │ ProductOptionValue
  │
  └── ProductVariant
          ├── ProductVariantValue
          └── Inventory


Customer
   ↓
Order
   ├── OrderItem
   └── OrderStatusHistory


ArticleCategory
   ↓
Article
```

Nếu cấu trúc implementation khác, ERD phải phản ánh source thực tế.

---

# 34. Documentation

Tạo tài liệu Phase 1.

Ít nhất:

```text
docs/
├── architecture.md
├── database-design.md
├── legacy-mapping.md
├── exment-mapping.md
└── phase-1-report.md
```

Tên file có thể điều chỉnh theo convention project.

---

# 35. Không làm trong Phase 1

Không làm:

```text
Frontend Blade hoàn chỉnh

Homepage UI

Shopee-style UI

Cart UI

Checkout UI

Voucher

Review

Payment Gateway

Shipping API

Customer Dashboard

Full Data Migration

Production Deployment

DNS switch

Delete legacy system
```

Nếu phát hiện nhu cầu Phase sau, chỉ ghi vào backlog.

---

# 36. Completion Criteria

Phase 1 chỉ được đánh dấu hoàn thành khi:

- Laravel + Exment chạy được
- cấu trúc source đã thiết lập
- database schema mới hoàn chỉnh cho phạm vi Phase 1
- migrations chạy sạch
- rollback/migrate lại thành công
- Models + relationships hoạt động
- Product Variant design hoạt động
- Inventory design hoạt động
- Order snapshot design hoạt động
- Order status history hoạt động
- SEO field foundation có
- Exment mapping hoàn thành
- Legacy mapping hoàn thành
- test Phase 1 pass
- không có credential hard-code
- không có blocker nghiêm trọng chưa ghi nhận

---

# 37. Báo cáo cuối Phase 1

Báo cáo theo cấu trúc:

```text
1. Executive Summary

2. Phase 0 Inputs Used

3. Architecture

4. Folder Structure

5. Domain Design

6. ERD

7. Database Tables

8. Important Fields

9. Foreign Keys

10. Index Strategy

11. Laravel Models

12. Relationships

13. Services / Actions

14. Product Variant Design

15. Inventory Design

16. Order Design

17. Exment Mapping

18. Legacy → New Mapping

19. Tests Executed

20. Test Results

21. Security Baseline

22. Risks

23. Technical Debt

24. Phase 2 Readiness
```

Cuối báo cáo phải có:

```text
PHASE 2 STATUS:
READY
```

hoặc:

```text
PHASE 2 STATUS:
READY WITH RISKS
```

hoặc:

```text
PHASE 2 STATUS:
NOT READY
```

Nếu không phải `READY`, phải liệt kê blocker/risk cụ thể.

---

# 38. Quy tắc thực hiện

Trong Phase 1:

**ĐƯỢC PHÉP**

```text
Create Laravel/Exment foundation

Create migrations

Create models

Create service/action foundation

Create tests

Create documentation

Create test/seed data

Refine database design
```

**KHÔNG ĐƯỢC PHÉP**

```text
Modify legacy production database

Delete legacy data

Switch DNS

Replace production website

Migrate production data

Build Phase 3 frontend

Add unrelated features
```

Nếu cần thay đổi quyết định trong Master Plan phải ghi:

```text
ARCHITECTURE CHANGE PROPOSAL
```

và giải thích:

- vấn đề hiện tại
- thay đổi đề xuất
- lợi ích
- ảnh hưởng
- migration cost

Không tự ý thay đổi rồi mới báo cáo.

**Chỉ thực hiện Phase 1 – Foundation & Database Design.**