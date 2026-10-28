Bạn là **Senior Laravel/Exment Architect + Data Migration Engineer + QA Lead**.

Hãy thực hiện **Phase 4 – Legacy Data Migration** cho dự án rebuild `bancay.vn`.

Trước khi thực hiện, bắt buộc đọc và tuân thủ:

1. Master Plan của dự án
2. Phase 0 – Legacy Analysis & Backup
3. Phase 1 – Foundation & Database Design
4. Phase 2 – Exment Admin Development
5. Phase 3 – Frontend Blade Development
6. Consolidated Risk Review Phase 1–3
7. Legacy → New Mapping
8. Business Rule Inventory
9. Database Inventory
10. File/Image Inventory
11. SEO/URL Inventory
12. ERD/schema hệ thống mới
13. Phase 4 Readiness Report

Chỉ bắt đầu nếu trạng thái hiện tại là:

```text
PHASE 4 STATUS: READY
```

Nếu đang là:

```text
NOT READY
```

thì không migrate dữ liệu.

---

# 1. Mục tiêu Phase 4

Di chuyển dữ liệu cần thiết từ hệ thống legacy:

```text
PHP 7.3
CodeIgniter 3.1.9
MySQL/MariaDB
```

sang hệ thống mới:

```text
Laravel + Exment
MySQL/MariaDB
```

Migration phải:

- có thể chạy thử
- có thể chạy lặp lại an toàn
- có log
- có validation
- không phá dữ liệu legacy
- không migrate mù
- có reconciliation
- có báo cáo record thành công/thất bại
- chuẩn bị cho final migration ở Phase 6

**Không cutover production trong Phase 4.**

---

# 2. Nguyên tắc quan trọng nhất

Legacy database là:

```text
READ ONLY
```

Không được:

```text
UPDATE legacy DB
DELETE legacy DB
DROP legacy table
ALTER legacy schema
```

Mọi transformation phải thực hiện ở:

```text
Migration Layer
↓
New Database
```

Không sửa dữ liệu nguồn để “cho dễ migrate”.

---

# 3. Môi trường Migration

Không chạy migration thử nghiệm trực tiếp lên production database mới nếu chưa xác minh.

Ưu tiên:

```text
Legacy DB Copy
        ↓
Migration Tool
        ↓
Staging New DB
```

Phải phân biệt rõ:

```text
Legacy Production
Legacy Backup/Clone
New Staging
New Production
```

Phase 4 chỉ sử dụng staging/test target.

---

# 4. Backup trước Migration

Trước khi chạy:

xác minh backup:

```text
Legacy Database
Legacy Source
Legacy Upload
New Database
```

Nếu backup chưa được verify:

```text
MIGRATION STATUS: BLOCKED
```

Không tiếp tục.

---

# 5. Migration Architecture

Tạo migration layer riêng.

Ví dụ:

```text
app/
└── Legacy/
    ├── Connections/
    ├── DTO/
    ├── Transformers/
    ├── Migrators/
    ├── Validators/
    └── Reports/
```

hoặc cấu trúc equivalent phù hợp project.

Không nhét migration logic vào:

```text
Controller
Blade
Exment Form
```

---

# 6. Legacy Database Connection

Tạo connection riêng:

```text
legacy
```

Ví dụ:

```text
DB_CONNECTION=mysql

LEGACY_DB_HOST=
LEGACY_DB_DATABASE=
LEGACY_DB_USERNAME=
LEGACY_DB_PASSWORD=
```

Credential thật chỉ nằm ở environment.

Không commit credential.

Legacy connection chỉ được sử dụng read-only trong migration code.

---

# 7. Migration Command

Tạo command rõ ràng.

Ví dụ:

```text
php artisan legacy:migrate
```

Hỗ trợ tối thiểu:

```text
--dry-run
--only=
--limit=
--from-id=
--force
```

Ví dụ:

```text
php artisan legacy:migrate --only=products --dry-run
```

`Dry-run` là **chạy mô phỏng để xem kết quả dự kiến mà chưa ghi dữ liệu thật**.

Dry-run là bắt buộc trước migration thật.

---

# 8. Migration Order

Xác định dependency và migrate đúng thứ tự.

Ví dụ:

```text
Settings
↓
Categories
↓
Products
↓
Product Images
↓
Product Options
↓
Product Variants
↓
Inventory

Article Categories
↓
Articles

Customers
↓
Orders
↓
Order Items
↓
Order Status History

Banners
```

Không migrate child trước parent nếu FK chưa tồn tại.

---

# 9. Category Migration

Legacy:

```text
tbldanhmuc
```

New:

```text
categories
```

Mapping dựa trên Phase 0/1.

Kiểm tra:

- parent/child
- name
- alias → slug
- image
- sort
- status
- meta
- legacy ID

Phải giữ được hierarchy.

Nếu parent legacy không tồn tại:

```text
INVALID_REFERENCE
```

Không tự đoán parent.

---

# 10. Product Migration

Legacy:

```text
tblsanpham
```

New:

```text
products
product_images
product_options
product_option_values
product_variants
product_variant_values
inventories
```

Phải transform dữ liệu, không copy trực tiếp.

Kiểm tra:

```text
title
alias
category
images
price
sale price
stock
variant JSON
SEO
status
featured/VIP
created_at
```

---

# 11. Legacy Variant JSON

Legacy field:

```text
phanloai
```

có cấu trúc dự kiến như:

```text
pl
pl1
pl2
img
price_pl
sale_pl
ma_pl
sl_pl
```

Phải parse an toàn.

Nếu JSON lỗi:

```text
INVALID_VARIANT_JSON
```

ghi log.

Không silently bỏ dữ liệu.

Transform sang:

```text
ProductOption
ProductOptionValue
ProductVariant
ProductVariantValue
Inventory
```

---

# 12. Variant Mapping Rules

Ví dụ legacy:

```text
pl1:
500g
1kg

pl2:
Hoa hồng
Lan
```

phải tạo đúng combination tương ứng.

Mỗi variant cần:

```text
SKU
Price
Sale Price
Stock
Option Values
Status
```

Không tạo duplicate SKU.

Nếu SKU legacy duplicate:

```text
DUPLICATE_SKU
```

phải đưa vào migration report.

Không tự sửa SKU âm thầm.

---

# 13. Product Without Variant

Nếu Product legacy không có `phanloai`:

xác định strategy đã thống nhất.

Có thể:

```text
Product
→ Default Variant
```

nếu architecture mới yêu cầu mọi Product có Variant.

Ví dụ:

```text
SKU
price
stock
```

được chuyển vào default variant.

Phải thống nhất một rule duy nhất.

---

# 14. Image Migration

Legacy có thể sử dụng:

```text
upload/sp/
upload/news/
upload/banner/
images/
```

New storage sử dụng Laravel filesystem.

Ví dụ:

```text
storage/app/public/products/
storage/app/public/articles/
storage/app/public/banners/
```

Migration phải:

- copy file
- kiểm tra file tồn tại
- validate type
- tạo path mới
- giữ relationship
- ghi log file thiếu

Nếu DB reference có nhưng file không tồn tại:

```text
MISSING_FILE
```

Không fail toàn bộ migration nếu policy cho phép skip.

---

# 15. Image Filename Collision

Kiểm tra duplicate filename.

Không overwrite file khác.

Nếu trùng:

sử dụng naming strategy an toàn:

```text
UUID
hash
legacy-id prefix
```

nhưng phải giữ mapping.

---

# 16. Article Migration

Legacy:

```text
tblbaiviet
tblchuyenmuc
```

New:

```text
articles
article_categories
```

Migrate:

```text
title
slug
category
excerpt/sapo
content
image
status
published_at
meta_title
meta_description
```

Phải xử lý legacy content HTML cẩn thận.

Không tự strip content nếu không cần.

---

# 17. HTML Content Audit

Kiểm tra article/product description legacy có:

```text
absolute image URL
old domain URL
inline style
old upload path
iframe
script
```

Không auto-rewrite tất cả nếu chưa có rule rõ.

Tạo report:

```text
CONTENT_REWRITE_REQUIRED
```

cho nội dung cần xử lý ở Phase 5.

---

# 18. Customer Migration

Nếu legacy có customer data:

mapping sang:

```text
customers
customer_addresses
```

Kiểm tra:

```text
name
phone
email
address
status
```

Deduplication phải thận trọng.

Không merge customer chỉ vì:

```text
same name
```

Có thể dùng:

```text
phone
email
```

nếu business rule xác nhận.

Nếu không chắc:

không merge.

---

# 19. Admin/User Migration

Legacy:

```text
tbladmin
```

New:

Laravel/Exment User.

Migrate:

```text
username
fullname
email nếu có
status
role mapping
```

Không migrate MD5 thành password hợp lệ mới bằng cách copy hash.

Password strategy:

```text
FORCE PASSWORD RESET
```

hoặc strategy đã thống nhất Phase 1/2.

Không lưu legacy plaintext.

---

# 20. Role Mapping

Legacy role phải dựa vào Phase 0:

```text
legacy role
→ new role
```

Không đoán.

Nếu role không mapping được:

```text
ROLE_MAPPING_REQUIRED
```

User đó không được tự động cấp Super Admin.

---

# 21. Order Migration

Legacy:

```text
tblorder
```

Phải xác định từ Phase 0:

- mỗi row là một order hay một order item
- customer info
- product
- quantity
- price
- variant
- status

Transform sang:

```text
orders
order_items
order_status_histories
```

---

# 22. Historical Order Integrity

Order legacy phải giữ giá lịch sử nếu dữ liệu nguồn có.

Không lookup price hiện tại từ Product để thay thế historical price.

Nếu legacy thiếu price:

ghi:

```text
ORDER_PRICE_MISSING
```

và xử lý theo migration rule đã thống nhất.

Không tự lấy giá hiện tại làm lịch sử nếu không được chấp thuận.

---

# 23. Order Number

Nếu legacy không có order number chuẩn:

thiết kế strategy.

Ví dụ:

```text
LEG-{legacy_id}
```

hoặc format khác.

Phải:

- unique
- trace được về record cũ
- không collision với order mới

---

# 24. Order Status Mapping

Ví dụ:

```text
legacy status
→ NEW
→ CONFIRMED
→ ...
```

Mapping phải có tài liệu rõ.

Không map unknown status thành `COMPLETED`.

Nếu không xác định:

```text
UNKNOWN_LEGACY_STATUS
```

---

# 25. Banner / Slider Migration

Nếu hệ thống mới đã merge:

```text
Banner
Slider
```

thành:

```text
banners
```

thì migrate cả:

```text
tblbanner
tblslider
```

vào cùng entity với `position/type` phù hợp.

Không duplicate nếu cùng record logic.

---

# 26. Settings Migration

Legacy:

```text
tblfooter
config
```

có thể chứa:

```text
site name
address
phone
email
logo
facebook
map
SEO
```

Transform sang:

```text
settings
```

Không migrate:

```text
password
secret
SMTP credential
API key
```

vào settings database nếu architecture không cho phép.

---

# 27. Legacy ID Strategy

Áp dụng strategy Phase 1 đã quyết định.

Nếu dùng:

```text
legacy_id
```

thì migrate đầy đủ.

Ví dụ:

```text
products.legacy_id
categories.legacy_id
orders.legacy_id
```

Legacy ID giúp:

- debug
- redirect
- reconciliation
- rerun migration

---

# 28. Idempotent Migration

Migration phải càng gần **idempotent (chạy lại không tạo duplicate)** càng tốt.

Ví dụ lookup bằng:

```text
legacy_id
```

Nếu record đã migrate:

có thể:

```text
SKIP
UPDATE
REPLACE
```

theo strategy rõ ràng.

Không để chạy command lần 2 tạo duplicate toàn bộ dữ liệu.

---

# 29. Migration Modes

Nên hỗ trợ:

```text
DRY RUN
INSERT
RE-RUN
VALIDATE ONLY
```

Nếu feasible.

Ví dụ:

```text
php artisan legacy:migrate --dry-run
php artisan legacy:migrate
php artisan legacy:validate
```

---

# 30. Transaction Strategy

Không dùng một transaction khổng lồ cho hàng chục nghìn record nếu không cần.

Ưu tiên:

```text
transaction per entity
transaction per batch
```

để có khả năng recover.

Nếu product migration fail:

không để Product được tạo nhưng Variant/Inventory nửa chừng.

---

# 31. Chunking

Không load toàn bộ table vào memory.

Dùng:

```text
chunk
chunkById
cursor
```

phù hợp.

Đặc biệt:

```text
products
articles
orders
```

nếu dữ liệu lớn.

---

# 32. Migration Logging

Mỗi entity cần thống kê:

```text
READ
CREATED
UPDATED
SKIPPED
FAILED
WARNING
```

Ví dụ:

```text
Products:
Read: 1200
Created: 1180
Skipped: 10
Failed: 10
```

Không chỉ log “Migration completed”.

---

# 33. Error Logging

Mỗi lỗi phải có:

```text
Entity
Legacy ID
Error Code
Message
Context
```

Không log:

```text
password
secret
full sensitive customer data
```

---

# 34. Migration Error Policy

Phân loại:

## Blocking

Ví dụ:

```text
Database connection failed
Schema mismatch
Core FK missing
```

→ stop migration.

## Record-level

Ví dụ:

```text
missing image
invalid optional SEO
```

→ có thể log + continue.

Policy phải rõ ràng.

---

# 35. Data Validation trước Migration

Trước migration thật, scan legacy:

```text
duplicate SKU
duplicate slug
invalid JSON
missing parent
missing referenced product
missing image
invalid email
invalid price
negative stock
unknown status
```

Tạo:

```text
Pre-Migration Validation Report
```

Không migrate rồi mới phát hiện toàn bộ.

---

# 36. Post-Migration Validation

Sau migration, kiểm tra:

```text
row counts
FK
relationships
null critical fields
duplicate SKU
duplicate slug
broken images
invalid variant mapping
order totals
```

---

# 37. Reconciliation

Tạo bảng đối chiếu:

| Entity | Legacy Count | Migrated | Skipped | Failed | Match % |
|---|---:|---:|---:|---:|---:|

Ví dụ:

```text
Category
Product
Variant
Article
Customer
Order
Banner
```

Không dùng 100% count để khẳng định đúng dữ liệu.

Cần validation nội dung.

---

# 38. Sample Data Verification

Random sample một số record.

Ví dụ:

```text
20 Products
20 Variants
10 Orders
10 Articles
```

So sánh:

```text
Legacy
vs
New
```

theo:

- name
- price
- category
- variant
- stock
- image
- SEO
- order info

---

# 39. Product Functional Verification

Sau migration:

vào Exment Admin kiểm tra:

```text
Category
Product
Images
Options
Variants
Stock
```

Sau đó frontend:

```text
Product List
Product Detail
Variant Selector
Price
Stock
```

phải hiển thị đúng.

---

# 40. Order Functional Verification

Kiểm tra:

```text
Legacy Order
→ New Order
```

Admin phải xem được.

Nếu order item có product reference:

link đúng Product nếu product đã migrate.

Nếu product legacy bị thiếu:

historical order vẫn phải hiển thị snapshot.

---

# 41. File Verification

Kiểm tra:

```text
DB path exists
file exists
file readable
public URL works
```

Sample kiểm tra trực tiếp trên staging.

---

# 42. SEO Migration Preparation

Dựa trên:

```text
legacy_id
slug
old URLs
```

tạo dataset cho Phase 5:

```text
legacy_url
new_url
entity
legacy_id
```

Chưa bắt buộc bật redirect production.

---

# 43. Migration Report Files

Xuất report machine-readable nếu phù hợp:

```text
migration-summary.json
migration-errors.csv
migration-warnings.csv
legacy-url-map.csv
```

Không chứa secret.

---

# 44. Dry Run bắt buộc

Trước migration ghi thật:

```text
php artisan legacy:migrate --dry-run
```

Review:

- counts
- errors
- mapping
- warnings

Nếu có Critical/High migration issue:

không chạy write migration.

---

# 45. Test Migration

Sau dry-run:

chạy migration trên database staging sạch.

Không migrate chồng lên database đã bị sửa bằng tay nếu tránh được.

Ưu tiên:

```text
fresh schema
↓
seed system data
↓
legacy migration
```

---

# 46. Re-run Test

Sau lần migrate đầu:

reset staging target nếu phù hợp.

Chạy lại migration từ đầu.

Kết quả phải deterministic ở mức hợp lý.

`Deterministic` nghĩa là **cùng dữ liệu đầu vào sẽ cho kết quả đầu ra nhất quán**.

---

# 47. Automated Tests

Tạo tests cho migration logic quan trọng.

## Product

- legacy product → product
- JSON variants → normalized variants
- price mapping
- stock mapping
- image mapping

## Category

- parent mapping

## Order

- order snapshot
- status mapping
- product reference

## Invalid Data

- invalid JSON
- missing FK
- duplicate SKU

---

# 48. Regression

Sau migration staging, chạy:

```text
Phase 1 tests
Phase 2 tests
Phase 3 tests
Migration tests
```

Không để data migration làm hỏng application assumptions.

---

# 49. Exment UI Verification

Sau migration test trực tiếp:

```text
Product Grid
Product Detail/Edit
Variant
Inventory
Orders
Customers
Articles
Banners
```

Không chỉ kiểm tra database.

---

# 50. Frontend Verification

Test core flow bằng migrated data:

```text
Home
→ Category
→ Migrated Product
→ Variant
→ Add Cart
→ Checkout
→ New Order
```

Đây là test quan trọng.

Dữ liệu cũ phải hoạt động trong hệ thống mới.

---

# 51. Không migrate Production trong Phase 4

Phase 4 chỉ chứng minh:

```text
migration process works
```

Final production data migration sẽ thực hiện trong:

```text
Phase 6 – Production Release / Cutover
```

Không:

```text
freeze production
switch DNS
replace bancay.vn
```

ở Phase này.

---

# 52. Không sửa legacy business data

Nếu legacy có data quality problem:

ghi vào:

```text
Migration Exception Report
```

Có thể transform nếu rule đã được duyệt.

Không tự “sửa đẹp dữ liệu” nếu có thể thay đổi ý nghĩa business.

---

# 53. Manual Review Queue

Các record không thể tự động migrate an toàn đưa vào:

```text
MANUAL REVIEW
```

Ví dụ:

```text
duplicate SKU
unknown role
broken JSON
unknown order status
orphan category
```

Không bỏ record âm thầm.

---

# 54. Migration Success Criteria

Phase 4 chỉ hoàn thành khi:

- dry-run hoạt động
- staging migration chạy hoàn chỉnh
- migration rerun được
- không duplicate nghiêm trọng
- relationships đúng
- Product/Variant mapping đúng
- Inventory đúng
- Order mapping đúng
- Images migrate đúng ở mức chấp nhận
- Exment đọc được migrated data
- Frontend đọc được migrated data
- core checkout flow vẫn hoạt động
- reconciliation report hoàn thành
- unresolved migration exceptions đã liệt kê

---

# 55. Phase 5 Readiness

Phase 5 là:

```text
SEO
Security
Testing
Performance
Final hardening
```

Chỉ `READY` khi migration structure ổn định.

Các record cần manual review có thể tồn tại nếu:

- không ảnh hưởng schema
- đã xác định rõ
- có plan xử lý trước cutover

---

# 56. Báo cáo cuối Phase 4

Trả báo cáo:

```text
1. Executive Summary

2. Migration Architecture

3. Source / Target Environment

4. Legacy Tables Processed

5. Migration Order

6. Category Migration

7. Product Migration

8. Variant Transformation

9. Inventory Migration

10. Article Migration

11. Customer Migration

12. User Migration

13. Order Migration

14. Banner / Setting Migration

15. File / Image Migration

16. Pre-Migration Validation

17. Dry-Run Result

18. Migration Result

19. Reconciliation

20. Sample Verification

21. Exment Verification

22. Frontend Verification

23. Automated Tests

24. Regression Results

25. Migration Errors

26. Manual Review Items

27. Risks

28. SEO URL Mapping Prepared

29. Phase 5 Readiness
```

Cuối báo cáo phải ghi:

```text
PHASE 5 STATUS: READY
```

hoặc:

```text
PHASE 5 STATUS: READY WITH RISKS
```

hoặc:

```text
PHASE 5 STATUS: NOT READY
```

Nếu chưa READY:

liệt kê chính xác blocker.

---

# 57. Quy tắc cuối cùng

Trong Phase 4:

**ĐƯỢC PHÉP**

```text
Read legacy DB

Create migration tooling

Transform legacy data

Write staging new DB

Copy legacy files to staging storage

Create reports

Create migration tests

Validate migrated data
```

**KHÔNG ĐƯỢC PHÉP**

```text
Modify legacy production DB

Delete legacy data

Switch production domain

Switch DNS

Replace current website

Run final production migration

Remove CodeIgniter system
```

Nếu phát hiện schema hệ thống mới không đủ để migrate dữ liệu:

không workaround bằng JSON/hard-code.

Tạo:

```text
SCHEMA CHANGE PROPOSAL
```

và ghi:

- legacy data không map được ở đâu
- schema hiện tại có vấn đề gì
- thay đổi tối thiểu cần thiết
- ảnh hưởng Phase 1/2/3
- migration mới cần tạo
- regression cần chạy

Sau khi thay đổi phải chạy lại regression trước khi tiếp tục.

**Chỉ thực hiện Phase 4 – Legacy Data Migration. Không thực hiện production cutover trong task này.**