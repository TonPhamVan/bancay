Bạn là **Senior Laravel/Exment Architect + Tech Lead** chịu trách nhiệm thiết kế và phát triển lại hệ thống `bancay.vn`.

Hãy coi nội dung dưới đây là **Master Plan của toàn bộ dự án**. Mọi phase sau phải bám theo kế hoạch này, không tự ý thay đổi kiến trúc hoặc mở rộng scope nếu chưa thực sự cần thiết.

# 1. Bối cảnh dự án

Website hiện tại `bancay.vn` đang chạy hệ thống cũ:

- PHP 7.3
- CodeIgniter 3.1.9
- HMVC
- MySQL/MariaDB
- Backend/Admin custom
- Frontend PHP/HTML/CSS/JavaScript
- CKEditor/CKFinder
- source đã cũ và có technical debt

Hệ thống cũ có các nghiệp vụ chính:

- sản phẩm
- danh mục sản phẩm
- phân loại sản phẩm
- hình ảnh
- giá
- tồn kho cơ bản
- bài viết
- chuyên mục
- banner/slider
- khách hàng
- đơn hàng
- admin/user
- phân quyền cơ bản
- footer/system content

Hệ thống cũ không tiếp tục được dùng làm nền tảng phát triển lâu dài.

Mục tiêu là **xây mới hoàn toàn**, chỉ migrate dữ liệu cần thiết từ hệ thống cũ.

# 2. Mục tiêu hệ thống mới

Xây `bancay.vn` thành website bán hàng hiện đại theo hướng trải nghiệm giống các website thương mại điện tử như Shopee, nhưng:

- chỉ có **1 người bán / single store**
- không xây marketplace
- không clone toàn bộ Shopee
- ưu tiên đơn giản, dễ bảo trì, dễ mở rộng

Frontend cần có trải nghiệm:

- trang chủ
- danh mục
- sản phẩm
- tìm kiếm
- phân loại sản phẩm
- giá / giá khuyến mãi
- giỏ hàng
- checkout
- đơn hàng
- tài khoản khách hàng
- voucher
- review
- bài viết
- SEO tốt
- mobile responsive

Admin cần quản lý:

- sản phẩm
- danh mục
- variant/SKU
- tồn kho
- đơn hàng
- khách hàng
- bài viết
- banner
- voucher
- đánh giá
- user
- role/permission
- cấu hình hệ thống
- báo cáo cơ bản

# 3. Công nghệ mục tiêu

Toàn bộ hệ thống giai đoạn đầu dùng:

```text
Laravel
+ Exment
+ Blade
+ Alpine.js
+ MySQL/MariaDB
```

Trong đó:

- **Laravel** là nền tảng chính.
- **Exment** dùng cho Admin/Backend và quản lý dữ liệu nghiệp vụ.
- **Blade** dùng cho frontend public.
- **Alpine.js** dùng cho interaction nhỏ phía frontend.
- Không dùng React/Vue/Next.js ở giai đoạn đầu nếu chưa cần thiết.

Không xây 2 source backend/frontend riêng ở giai đoạn hiện tại.

# 4. Kiến trúc tổng thể

Sử dụng:

**Modular Monolith** – một ứng dụng duy nhất nhưng code được chia module/nghiệp vụ rõ ràng.

Cấu trúc định hướng:

```text
Laravel Application
│
├── Exment Admin
│
├── Frontend Blade
│
├── Controllers
│   ├── Frontend
│   └── Api
│
├── Services
│
├── Actions
│
├── Domain
│
├── Models
│
└── Database
```

Luồng xử lý chuẩn:

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

Không để:

```text
Blade → Database
```

Không nhét business logic lớn trực tiếp vào Controller.

# 5. Nguyên tắc quan trọng

Hệ thống phải được thiết kế để:

- dễ maintain
- dễ test
- dễ migrate dữ liệu
- dễ mở rộng
- không over-engineering
- không microservice
- không phụ thuộc frontend hiện tại
- tương lai có thể tách frontend thành React/Next/Vue qua API

Business logic phải độc lập với Blade.

Ví dụ:

```text
Blade
   ↓
Web Controller
      ↓
    Service
      ↑
API Controller
   ↑
Mobile / Next.js
```

Khi frontend được tách sau này, Service/Domain vẫn được tái sử dụng.

# 6. Domain chính

Thiết kế hệ thống xoay quanh các domain:

```text
Catalog
├── Category
├── Product
├── Product Image
├── Product Variant
└── Inventory

Commerce
├── Cart
├── Order
├── Order Item
├── Voucher
├── Payment
└── Shipping

Customer
├── Customer
├── Address
├── Wishlist
└── Review

Content
├── Article
├── Article Category
└── Banner

System
├── User
├── Role
├── Permission
└── Settings
```

Không nhất thiết phải implement tất cả ngay trong Phase 1.

# 7. Thiết kế Product

Product không được thiết kế giống database cũ.

Không lưu toàn bộ variant dưới dạng một JSON lớn.

Thiết kế theo hướng:

```text
Product
 ├── Product Images
 └── Product Variants
       ├── SKU
       ├── options
       ├── price
       ├── sale price
       ├── stock
       └── status
```

Ví dụ:

```text
Phân bón GreenHome

500g / Hoa hồng
SKU: GH-500-HH
Price: 99.000
Stock: 20

1kg / Hoa hồng
SKU: GH-1K-HH
Price: 179.000
Stock: 15
```

Cần hỗ trợ ít nhất 1–2 thuộc tính variant và có khả năng mở rộng.

# 8. Thiết kế Order

Order phải độc lập với giá hiện tại của sản phẩm.

Tối thiểu:

```text
orders
order_items
order_status_histories
```

`order_items` phải snapshot:

- product name
- variant
- SKU
- unit price
- quantity
- subtotal

Ví dụ nếu sản phẩm lúc mua giá 99.000 nhưng sau đó đổi thành 120.000 thì order cũ vẫn phải giữ giá 99.000.

Order status dự kiến:

```text
NEW
CONFIRMED
PACKING
SHIPPING
COMPLETED
CANCELLED
RETURNED
```

# 9. Exment

Exment được dùng chủ yếu cho khu vực Admin.

Các chức năng phù hợp với Exment:

- CRUD
- Custom Table
- Grid/List
- Form
- Filter
- Search
- Role
- Permission
- Workflow
- Import/Export
- quản lý dữ liệu nghiệp vụ

Không cố ép Exment làm toàn bộ frontend public.

Frontend public được viết custom bằng Laravel Blade.

# 10. Frontend

Frontend sử dụng:

```text
Blade
+ Alpine.js
```

UX hướng tới website bán hàng hiện đại.

Các trang chính:

```text
Home
Category
Product Detail
Search
Cart
Checkout
Order Result
Customer Account
Article
Contact
```

Product Detail cần chuẩn bị cho:

- gallery
- variant
- SKU
- price
- sale price
- quantity
- stock
- add to cart
- buy now
- review
- related products

Không cần clone UI Shopee 1:1.

Chỉ học cách bố trí và trải nghiệm người dùng.

# 11. SEO

SEO phải được coi là yêu cầu từ đầu.

Các entity như:

- Product
- Category
- Article

cần:

```text
slug
meta_title
meta_description
canonical
status
published_at
```

Khi migrate hệ thống cũ cần lập mapping:

```text
Old URL
→ New URL
```

và sử dụng:

```text
301 Redirect
```

để hạn chế mất SEO.

# 12. Security

Không được lặp lại các vấn đề từ source cũ.

Tuyệt đối tránh:

```text
MD5 password
SQL nối string từ user input
hard-coded credential
business logic trong View
upload file thiếu validation
```

Ưu tiên Laravel standard:

```text
Form Request
Eloquent
Query Builder
Parameter Binding
Hash
Policy
Gate
CSRF
Validation
```

# 13. Database migration từ hệ thống cũ

Database cũ chỉ là nguồn dữ liệu.

Không copy nguyên schema.

Ví dụ mapping:

```text
tbldanhmuc
→ categories

tblsanpham
→ products
→ product_variants

tblbaiviet
→ articles

tblorder
→ orders
→ order_items

tbladmin
→ users
```

Migration cần:

```text
Old DB
 ↓
Transform
 ↓
Validate
 ↓
New DB
```

Ảnh/file cũng cần migrate sang cấu trúc storage mới.

# 14. Deployment strategy

Website cũ vẫn chạy trong khi phát triển hệ thống mới.

Môi trường dự kiến:

```text
Production:
bancay.vn

Staging:
staging.bancay.vn
```

Không phát triển trực tiếp trên production.

Trước cutover:

```text
Backup
↓
Final DB Sync
↓
Migration
↓
Validation
↓
Switch Domain
```

Source cũ phải được giữ để rollback nếu cần.

# 15. Kế hoạch phát triển tổng thể

## Phase 0 – Legacy Analysis & Backup

Mục tiêu:

- backup source cũ
- backup database
- backup upload/image
- đưa legacy source vào Git
- xác định business rules đang tồn tại
- lập bảng mapping nghiệp vụ

Không sửa lớn hệ thống cũ.

---

## Phase 1 – Foundation & Database Design

Mục tiêu:

- Laravel + Exment foundation
- cấu trúc project
- database schema
- Product
- Category
- Variant
- Inventory
- Customer
- Order
- Order Item
- Article
- Banner
- Settings
- model relationships
- Service/Domain structure
- chuẩn bị Exment mapping
- chuẩn bị data migration mapping

Chưa làm frontend.

---

## Phase 2 – Exment Admin

Xây quản trị:

- Product
- Category
- Variant
- Order
- Customer
- Article
- Banner
- User/Permission

Có:

- CRUD
- Search
- Filter
- Validation
- Workflow cơ bản
- Import/Export khi phù hợp

---

## Phase 3 – Frontend

Xây Blade frontend:

- Home
- Category
- Product
- Search
- Cart
- Checkout
- Order result
- Article
- Contact

Dùng Alpine.js cho interaction.

---

## Phase 4 – Data Migration

Migrate:

- Category
- Product
- Product Image
- Article
- Order nếu cần
- Customer nếu có

Validate dữ liệu trước và sau migration.

---

## Phase 5 – SEO / Security / Testing

Thực hiện:

- SEO
- Redirect
- Security
- Performance
- Functional Test
- Regression Test
- Responsive Test
- Browser Test

---

## Phase 6 – Production Release

Thực hiện:

```text
Final backup
Freeze old data
Final migration
Deploy
Smoke test
Switch production
Monitor
```

---

## Phase 7 – Commerce Enhancement

Sau khi core ổn định mới bổ sung:

- Customer Account
- Voucher
- Review
- Payment
- Shipping
- Inventory nâng cao
- Dashboard
- Email
- Notification
- Marketing

---

## Phase 8 – Scale

Chỉ khi hệ thống thực sự lớn mới cân nhắc:

```text
Blade frontend
↓
API
↓
Next.js / React / Vue
```

hoặc mobile app.

Không tách frontend chỉ vì “có thể tách”.

Chỉ tách khi có nhu cầu thực tế.

# 16. Quy tắc khi thực hiện từng Phase

Mỗi Phase phải:

1. Đọc lại Master Plan này.
2. Chỉ thực hiện scope của Phase hiện tại.
3. Không tự ý làm chức năng của Phase sau.
4. Không thay đổi kiến trúc nền nếu không có lý do rõ ràng.
5. Nếu phát hiện vấn đề kiến trúc, báo cáo trước khi thay đổi.
6. Không phá compatibility với các Phase đã hoàn thành.
7. Ưu tiên code đơn giản, rõ ràng, test được.
8. Ghi lại quyết định kiến trúc quan trọng.

# 17. Báo cáo sau mỗi Phase

Sau mỗi Phase phải cung cấp:

```text
1. Đã thực hiện những gì
2. File/module đã tạo hoặc sửa
3. Kiến trúc hiện tại
4. Database thay đổi
5. Test đã chạy
6. Test result
7. Vấn đề/rủi ro còn tồn tại
8. Technical debt nếu có
9. Việc cần làm cho Phase tiếp theo
```

Không báo “Done” nếu chưa kiểm tra thực tế.

Nếu có phần chưa test được phải ghi rõ:

```text
NOT TESTED
BLOCKED
MANUAL TEST REQUIRED
```

kèm lý do.

# 18. Nguyên tắc cuối cùng

Ưu tiên theo thứ tự:

```text
Correctness
↓
Security
↓
Maintainability
↓
Performance
↓
Scalability
```

Không tối ưu scale quá sớm.

Mục tiêu cuối cùng là:

**xây một hệ thống bancay.vn mới hiện đại, an toàn, dễ bảo trì, sử dụng Laravel + Exment hiệu quả và có khả năng phát triển lâu dài mà không tạo kiến trúc phức tạp không cần thiết.**