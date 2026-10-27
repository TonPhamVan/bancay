Bạn là **Senior PHP/Laravel Architect + Legacy System Analyst**.

Hãy thực hiện **Phase 0 – Legacy Analysis & Backup** cho dự án rebuild `bancay.vn`.

Trước khi thực hiện, hãy đọc và tuân thủ **Master Plan của dự án bancay.vn** đã được cung cấp.

## 1. Mục tiêu Phase 0

Mục tiêu của Phase này là hiểu đầy đủ hệ thống CodeIgniter cũ trước khi xây hệ thống Laravel + Exment mới.

Phase 0 phải:

- backup đầy đủ hệ thống hiện tại
- phân tích source code cũ
- phân tích database cũ
- xác định chức năng đang hoạt động
- xác định business rules hiện tại
- xác định dữ liệu cần migrate
- xác định file/image cần migrate
- xác định URL cũ cần giữ SEO
- xác định dependency và tích hợp bên ngoài
- xác định các rủi ro kỹ thuật/bảo mật
- chuẩn bị tài liệu đầu vào cho Phase 1

**Không rewrite code.**

**Không migrate sang Laravel.**

**Không sửa cấu trúc database production.**

**Không nâng PHP/CodeIgniter ở Phase này.**

---

# 2. Thông tin hệ thống hiện tại đã biết

Legacy system hiện tại:

```text
PHP 7.3
CodeIgniter 3.1.9
HMVC
MySQL/MariaDB
DirectAdmin
```

Cấu trúc chính:

```text
application/modules/
├── admin/
│   ├── controllers/Admin.php
│   ├── models/Admin_model.php
│   └── views/
│
└── site/
    ├── controllers/Site.php
    ├── models/Site_model.php
    └── views/
```

Các chức năng đã thấy:

- sản phẩm
- danh mục sản phẩm
- phân loại sản phẩm
- ảnh sản phẩm
- giá bán
- số lượng/tồn kho cơ bản
- bài viết
- chuyên mục
- banner
- slider
- khách hàng
- đơn hàng
- admin/user
- role cơ bản
- footer/system information

Source cũ có một số dấu hiệu technical debt:

- Controller lớn
- raw SQL
- query nối string từ user input
- MD5 password
- variant sản phẩm lưu JSON
- một số config/integration hard-code
- có khả năng tồn tại dead code

Không được coi danh sách trên là đầy đủ. Hãy xác minh từ source/database thực tế.

---

# 3. Backup hệ thống

Trước khi phân tích sâu, lập checklist backup.

Cần backup tối thiểu:

```text
Source code

Database

Upload directory

Product images

Article images

Banner/slider images

Config files

.htaccess

Cron configuration

Domain/DNS information

SSL information

Email/SMTP configuration

DirectAdmin configuration liên quan

Environment-specific configuration
```

Không được đưa password, API key hoặc secret vào báo cáo công khai.

Nếu phát hiện credential trong source:

```text
chỉ ghi:
FOUND SECRET / CREDENTIAL

không copy giá trị secret vào báo cáo
```

## Kiểm tra backup

Xác nhận:

- file backup tồn tại
- dung lượng hợp lý
- archive mở được
- database dump đọc được
- upload/image có trong backup
- config quan trọng có trong backup

Nếu có thể, kiểm tra khả năng restore trên môi trường test.

---

# 4. Đưa legacy source vào Git

Nếu source hiện tại chưa được quản lý Git:

Khởi tạo repository:

```text
bancay-legacy
```

Commit đầu tiên:

```text
Legacy production baseline
```

Không refactor code trước commit baseline.

Mục tiêu là giữ nguyên snapshot hệ thống hiện tại để:

- đối chiếu
- rollback
- audit
- xem lại business rule sau này

Các file chứa secret phải được xử lý an toàn và không push vào remote public.

---

# 5. Phân tích kiến trúc source

Lập inventory toàn bộ source.

Phân tích:

```text
Controllers

Models

Views

Helpers

Libraries

Hooks

Modules

Config

Third-party libraries

Cron jobs

Upload handlers

Email handlers

Authentication

Authorization

Routing
```

Đối với mỗi module, ghi:

```text
Tên module

Mục đích

Controller

Model

Tables sử dụng

View liên quan

Business rules

External dependency

Mức độ quan trọng
```

---

# 6. Phân tích chức năng hệ thống

Lập **Functional Inventory**.

Tối thiểu xác minh:

## Public website

- Home
- Product List
- Product Detail
- Category
- Search
- Article
- Article Category
- Contact
- Order/Buy
- Product Variant
- Popup
- SEO URL

## Admin

- Login
- Dashboard
- Product CRUD
- Category CRUD
- Article CRUD
- Article Category CRUD
- Order Management
- Customer/Contact
- Banner
- Slider
- User/Admin
- Role/Permission
- Footer/System setting

Với mỗi chức năng ghi:

```text
Function ID

Tên chức năng

URL

Controller::method

Model method

Tables

Input

Output

Validation

Business rule

Permission

Status

Có cần migrate hay không
```

---

# 7. Phân tích business rules

Đây là phần quan trọng nhất.

Không chỉ đọc table/schema.

Phải tìm các business rule đang nằm trong:

```text
Controller
Model
View
Helper
JavaScript
Database
```

Ví dụ cần xác định:

- cách tính giá sale
- cách xác định sản phẩm VIP
- logic tồn kho
- cấu trúc variant sản phẩm
- cách tạo slug
- quy tắc hiển thị category
- category cha/con
- cách tạo order
- trạng thái order
- quyền role admin
- cách sort sản phẩm
- cách search
- cách chọn sản phẩm nổi bật
- cách xử lý ảnh
- logic SEO
- cách tính lượt xem bài viết
- popup logic
- business rule ẩn trong JavaScript

Lập bảng:

```text
Rule ID

Business Rule

Source location

Input

Condition

Output

Used by

Must preserve in new system?

Notes
```

---

# 8. Phân tích database

Xuất schema database hiện tại.

Lập danh sách toàn bộ table.

Tối thiểu đã biết có khả năng có:

```text
tblsanpham
tbldanhmuc
tblbaiviet
tblchuyenmuc
tblorder
tbladmin
tblbanner
tblslider
tblcontact
tblfooter
```

Nhưng phải scan toàn database để tìm đầy đủ.

Với mỗi table ghi:

```text
Table

Purpose

Row count

Primary key

Foreign/reference fields

Important columns

Used by controller/model nào

Active / Legacy / Unknown

Need migration?

Target entity dự kiến
```

---

# 9. Phân tích dữ liệu sản phẩm

Đây là phần quan trọng cho Phase 1.

Đặc biệt phân tích:

```text
tblsanpham
```

Xác định:

- ID
- product code/SKU
- title
- slug/alias
- images
- regular price
- sale price
- variant JSON
- stock
- category
- SEO
- status
- VIP
- created time

Đối với `phanloai` JSON:

Phân tích cấu trúc thực tế:

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

Lấy một số sample representative nhưng không sửa dữ liệu.

Xác định cách chuyển sang:

```text
products

product_variants

product_images

inventory
```

Chưa implement migration ở Phase 0.

Chỉ phân tích mapping.

---

# 10. Phân tích Order

Xác định chính xác:

```text
tblorder
```

và toàn bộ code liên quan.

Kiểm tra:

- một order có một hay nhiều sản phẩm?
- có order_items riêng không?
- có snapshot giá không?
- có variant không?
- có trạng thái không?
- có payment status không?
- có shipping information không?
- customer data lưu thế nào?
- có history không?
- order có cần migrate lịch sử không?

Đây là đầu vào quan trọng cho thiết kế `orders` và `order_items` ở Phase 1.

---

# 11. Phân tích User/Admin

Kiểm tra:

```text
tbladmin
```

Xác định:

- username
- fullname
- password format
- role
- status
- warehouse/kho
- avatar
- permission logic

Lập bảng mapping role hiện tại:

```text
Role value
→ quyền hiện tại
→ chức năng được phép
```

Không migrate password MD5 trực tiếp sang hệ thống mới nếu không có chiến lược an toàn.

Đưa ra đề xuất migration authentication cho Phase sau.

---

# 12. Kiểm tra security legacy

Chỉ audit và báo cáo, không sửa ở Phase 0 trừ khi có lỗ hổng production cực kỳ nghiêm trọng và được yêu cầu riêng.

Kiểm tra:

- SQL Injection
- XSS
- CSRF
- password hashing
- session management
- authorization
- unrestricted file upload
- path traversal
- secret hard-code
- insecure SMTP
- missing validation
- direct object reference
- debug/error exposure

Mỗi finding ghi:

```text
Severity

Issue

File:line

Evidence

Attack condition

Impact

Recommendation

Must fix before migration?
```

Severity:

```text
Critical
High
Medium
Low
Info
```

Không khai thác phá hoại production.

---

# 13. Phân tích file/image

Scan toàn bộ:

```text
upload/
images/
ckeditor/
ckfinder/
```

Xác định:

- nơi lưu product image
- article image
- banner
- slider
- avatar
- CKFinder upload
- file không còn reference trong database
- file duplicate nếu có

Lập mapping dự kiến:

```text
Legacy path
→ New storage path
```

Ví dụ:

```text
upload/sp/
→ storage/app/public/products/

upload/news/
→ storage/app/public/articles/

upload/banner/
→ storage/app/public/banners/
```

---

# 14. Phân tích dependency

Tìm:

```text
composer.json
package.json
vendor
third_party
libraries
```

Liệt kê:

- CodeIgniter version
- HMVC library
- PHPMailer
- CKEditor
- CKFinder
- image library
- JS library
- CSS framework
- payment library nếu có
- external SDK nếu có

Phân loại:

```text
Still used

Legacy

Unknown

Must replace
```

---

# 15. External Integration Inventory

Tìm toàn source/config để xác định:

- SMTP
- Google Analytics
- Google Maps
- Facebook
- Zalo
- payment
- shipping
- API
- webhook
- cron
- external database
- CDN
- storage service

Không ghi secret.

Lập bảng:

```text
Integration

Purpose

Source location

Environment

Credential required?

Still active?

Need migrate?

Replacement strategy
```

---

# 16. URL & SEO Inventory

Đây là phần bắt buộc.

Tìm toàn bộ route và URL pattern cũ.

Ví dụ:

```text
*-dm{id}.html
*-cm{id}.html
```

Xác định URL cho:

- product
- category
- article
- article category
- search
- pagination

Lập mapping template:

```text
Old URL pattern
→ Entity
→ New URL proposal
→ 301 required?
```

Nếu có thể, export danh sách URL thực tế từ database.

Đây sẽ là đầu vào cho Redirect Migration ở Phase 5.

---

# 17. Cron / Scheduled Jobs

Kiểm tra:

- DirectAdmin cron
- application cron directory
- PHP cron script
- DB maintenance job
- email job
- sync job

Với mỗi cron:

```text
Command

Schedule

Purpose

Dependency

Still required?

Migration plan
```

---

# 18. Runtime / Server Inventory

Chỉ thu thập thông tin, không upgrade.

Ghi:

```text
OS

Web Server

PHP version

PHP SAPI

PHP extensions

Database version

Database charset

Document root

Disk usage

SSL

Cron

Email service
```

Xác định những extension nào legacy system đang phụ thuộc.

---

# 19. Migration classification

Mọi thành phần legacy phải được phân loại:

```text
KEEP
MIGRATE
REBUILD
DROP
REVIEW
```

Ví dụ:

```text
Product data
→ MIGRATE

Old CI controller
→ DROP

SEO URL
→ MIGRATE/REDIRECT

Old MD5 password
→ REVIEW

Product images
→ MIGRATE

Dead code
→ DROP
```

---

# 20. Mapping sang hệ thống mới

Tạo draft mapping cho Phase 1:

```text
Legacy
→ New Domain
```

Ví dụ:

```text
tblsanpham
→ Product

tbldanhmuc
→ Category

phanloai JSON
→ ProductVariant

tblorder
→ Order / OrderItem

tblbaiviet
→ Article

tblchuyenmuc
→ ArticleCategory

tblbanner
→ Banner

tbladmin
→ User/Role
```

Chưa tạo migration code.

Chỉ phân tích mapping.

---

# 21. Xác định dữ liệu cần giữ

Phân loại dữ liệu:

## MUST MIGRATE

Dữ liệu bắt buộc phải giữ.

## SHOULD MIGRATE

Nên giữ nếu hợp lý.

## OPTIONAL

Có thể bỏ.

## DROP

Không cần migrate.

Đặc biệt phải xác định cho:

```text
Product

Category

Product images

Article

Order history

Customer

Admin

SEO URL

Contact

Banner

Slider
```

---

# 22. Gap Analysis

So sánh:

```text
Legacy System
vs
Master Plan New System
```

Lập bảng:

```text
Feature

Legacy Support

New System Requirement

Gap

Action

Target Phase
```

Ví dụ:

```text
Product Variant

Legacy:
JSON

Target:
normalized tables

Action:
redesign

Phase:
1
```

---

# 23. Deliverables bắt buộc

Sau Phase 0 phải tạo đầy đủ:

## A. Legacy Architecture Report

Kiến trúc source hiện tại.

## B. Functional Inventory

Danh sách chức năng.

## C. Business Rule Inventory

Business rules cần giữ.

## D. Database Inventory

Tables + relationships + row count.

## E. Legacy → New Mapping

Mapping domain/database.

## F. Data Migration Scope

Dữ liệu KEEP/MIGRATE/DROP.

## G. File Migration Inventory

Images/files cần migrate.

## H. SEO URL Inventory

URL cần preserve/redirect.

## I. Integration Inventory

SMTP/API/Cron/etc.

## J. Security Assessment

Các rủi ro chính.

## K. Backup Verification Report

Backup đã đủ hay chưa.

## L. Phase 1 Readiness Report

Đánh giá:

```text
READY

READY WITH RISKS

NOT READY
```

Nếu chưa READY phải ghi rõ blocker.

---

# 24. Phase 0 Completion Criteria

Chỉ được coi Phase 0 hoàn thành khi:

- source đã backup
- database đã backup
- uploads/images đã backup
- legacy baseline đã lưu
- chức năng chính đã inventory
- database đã inventory
- business rules chính đã xác định
- product variant structure đã hiểu
- order structure đã hiểu
- user/role structure đã hiểu
- SEO URL pattern đã xác định
- integrations đã inventory
- security risk chính đã ghi nhận
- migration scope đã xác định
- Phase 1 có đủ thông tin để thiết kế schema mới

Không báo `DONE` nếu thiếu các mục trên.

---

# 25. Quy tắc làm việc

Trong Phase 0:

**ĐƯỢC PHÉP**

```text
Read source

Read DB schema

Read records phục vụ phân tích

Export backup

Analyze

Create documentation

Create Git baseline

Run safe read-only commands
```

**KHÔNG ĐƯỢC PHÉP**

```text
DROP TABLE

DELETE production data

UPDATE production data

Upgrade PHP

Upgrade CodeIgniter

Rewrite source

Change production business logic

Change domain/DNS

Change database schema
```

Ưu tiên thao tác **read-only (chỉ đọc, không thay đổi dữ liệu)**.

Nếu một bước có khả năng ảnh hưởng production, phải dừng bước đó và ghi:

```text
MANUAL APPROVAL REQUIRED
```

---

# 26. Báo cáo cuối Phase 0

Trả kết quả theo cấu trúc:

```text
1. Executive Summary

2. Current Architecture

3. Functional Inventory

4. Business Rules

5. Database Inventory

6. Product/Variant Analysis

7. Order Analysis

8. User/Role Analysis

9. File/Image Inventory

10. External Integrations

11. SEO/URL Inventory

12. Security Findings

13. Legacy → New Mapping

14. Migration Scope

15. Risks

16. Blockers

17. Phase 1 Readiness

18. Recommended next steps
```

Cuối báo cáo phải đưa ra kết luận rõ ràng:

```text
PHASE 1 STATUS:
READY
```

hoặc:

```text
PHASE 1 STATUS:
READY WITH RISKS
```

hoặc:

```text
PHASE 1 STATUS:
NOT READY
```

và giải thích ngắn gọn lý do.

**Chỉ thực hiện Phase 0. Không bắt đầu code Laravel/Exment Phase 1 trong cùng task này.**