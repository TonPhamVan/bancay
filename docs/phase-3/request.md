Bạn là **Senior Laravel Frontend Developer + Laravel/Exment Architect + Tech Lead**.

Hãy thực hiện **Phase 3 – Frontend Blade Development** cho dự án rebuild `bancay.vn`.

Trước khi thực hiện, bắt buộc đọc và tuân thủ:

1. **Master Plan của dự án bancay.vn**
2. **Báo cáo Phase 0 – Legacy Analysis & Backup**
3. **Báo cáo Phase 1 – Foundation & Database Design**
4. **Báo cáo Phase 2 – Exment Admin Development**
5. ERD/database schema hiện tại
6. Business Rule Inventory
7. Product/Variant/Inventory design
8. Order design
9. SEO/URL Inventory
10. Phase 3 Readiness Report

Nếu Phase 2 có trạng thái:

```text
NOT READY
```

thì không bắt đầu Phase 3.

Nếu:

```text
READY WITH RISKS
```

thì được tiếp tục nhưng phải ghi rõ risk/assumption liên quan.

# 1. Mục tiêu Phase 3

Xây frontend public hoàn chỉnh bằng:

```text
Laravel Blade
+ Alpine.js
+ CSS framework đang được project lựa chọn
```

Frontend phải sử dụng chung:

```text
Service
Action
Domain
Model
```

đã xây từ Phase 1/2.

Không duplicate business logic của Exment Admin.

Mục tiêu cuối Phase 3:

```text
Home
Category
Search
Product Detail
Variant Selection
Cart
Checkout
Order Success
Article
Contact
Basic Customer Order Lookup nếu scope cho phép
```

hoạt động end-to-end trên môi trường test/staging.

---

# 2. Kiến trúc frontend

Tiếp tục giữ:

```text
1 Laravel application
1 Git repository
1 database
```

Không tách frontend thành repository riêng.

Không tạo REST API chỉ để Blade gọi lại chính Laravel nếu không cần.

Luồng chuẩn:

```text
Browser
   ↓
Route
   ↓
Frontend Controller
   ↓
Service / Action
   ↓
Domain / Model
   ↓
Database
   ↓
Blade View
```

Không:

```text
Blade → Database
```

Không query trực tiếp trong Blade.

Không nhét business logic lớn vào Controller.

---

# 3. Cấu trúc frontend

Tổ chức rõ ràng.

Ví dụ:

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Frontend/
│   │       ├── HomeController.php
│   │       ├── ProductController.php
│   │       ├── CategoryController.php
│   │       ├── SearchController.php
│   │       ├── CartController.php
│   │       ├── CheckoutController.php
│   │       └── ArticleController.php
│   │
│   └── Requests/
│
├── Services/
├── Actions/
└── Domain/

resources/
├── views/
│   ├── layouts/
│   ├── components/
│   ├── home/
│   ├── products/
│   ├── categories/
│   ├── cart/
│   ├── checkout/
│   └── articles/
│
├── js/
└── css/
```

Có thể điều chỉnh theo convention project hiện tại nhưng phải giữ separation rõ ràng.

---

# 4. UX định hướng

Frontend lấy cảm hứng từ trải nghiệm mua hàng của Shopee nhưng:

- không clone UI 1:1
- không copy thương hiệu
- không copy asset
- không copy layout một cách máy móc

Mục tiêu là:

```text
dễ xem sản phẩm
dễ tìm kiếm
dễ chọn phân loại
dễ thêm giỏ
dễ checkout
responsive tốt
```

Website vẫn là:

```text
Single Store
```

không phải marketplace.

---

# 5. Responsive Design

Frontend phải responsive tối thiểu cho:

```text
Desktop
Tablet
Mobile
```

Ưu tiên Mobile First nếu phù hợp.

Kiểm tra tối thiểu các breakpoint phổ biến.

Không để:

- horizontal scroll không cần thiết
- button quá nhỏ
- ảnh vỡ layout
- text overflow
- cart unusable trên mobile

---

# 6. Layout chung

Xây layout reusable:

```text
Header
Navigation
Search
Main Content
Footer
Mobile Navigation nếu cần
```

Header có thể gồm:

```text
Logo
Search
Category/Menu
Cart
Customer Area nếu có
```

Không hard-code business data vào Blade.

Ví dụ:

```text
logo
phone
address
social links
```

phải lấy từ Settings nếu Phase 2 đã có.

---

# 7. Homepage

Trang chủ cần tối thiểu:

```text
Hero Banner
Featured Categories
Featured Products
Latest Products
Promotional Section nếu có
Latest Articles
Footer
```

Dữ liệu lấy từ:

```text
ProductService
CategoryService
BannerService
ArticleService
```

hoặc equivalent hiện tại.

Không query trực tiếp database trong View.

---

# 8. Banner

Hiển thị banner dựa trên:

```text
position
status
starts_at
ends_at
sort_order
```

Không hiển thị banner:

- inactive
- chưa đến thời gian
- đã hết hạn

nếu schema Phase 2 hỗ trợ thời gian hiệu lực.

---

# 9. Category Navigation

Hiển thị category hierarchy từ dữ liệu Phase 1.

Hỗ trợ:

```text
Parent Category
Child Category
```

Không hard-code menu category.

Category inactive không được public.

---

# 10. Category Page

Trang category cần:

```text
Category Name
Description nếu có
Subcategories nếu có
Product Grid
Pagination
Sort
Basic Filter nếu scope cho phép
```

Product grid hiển thị tối thiểu:

```text
Image
Name
Price
Sale Price
Discount nếu có
Stock state
Featured/Promotion badge nếu phù hợp
```

Không show product inactive/unpublished.

---

# 11. Product Listing

Tạo reusable component cho product card.

Ví dụ:

```text
ProductCard
├── Image
├── Name
├── Price
├── Sale Price
├── Discount
└── Stock / Status
```

Không duplicate markup ở nhiều page nếu component hóa hợp lý.

---

# 12. Search

Xây search sản phẩm.

Tối thiểu search theo:

```text
Product Name
SKU nếu phù hợp
```

Có thể search Category nếu business requirement cần.

Input phải:

- validated
- escaped
- không ghép raw SQL
- có giới hạn độ dài hợp lý

Không dùng legacy pattern:

```text
LIKE '%$keyword%'
```

bằng raw SQL không bind.

---

# 13. Search Result Page

Hiển thị:

```text
Search Keyword
Total Results
Product Grid
Pagination
```

Nếu không có kết quả:

```text
Không tìm thấy sản phẩm phù hợp
```

Không lỗi 500.

---

# 14. Product Detail

Đây là trang quan trọng nhất.

Phải hiển thị:

```text
Product Name

Image Gallery

Price

Sale Price

Discount

Variant Selection

SKU

Stock

Quantity

Add To Cart

Buy Now

Short Description

Full Description

Product Information

Related Products

SEO Metadata
```

Layout có thể hướng giống trải nghiệm Shopee:

```text
Images      Product Info
            Price
            Variant
            Quantity
            Add Cart
            Buy Now
```

nhưng thiết kế riêng cho bancay.vn.

---

# 15. Product Gallery

Hỗ trợ:

- ảnh chính
- thumbnail
- đổi ảnh khi click
- responsive
- lazy loading nếu phù hợp

Nếu variant có ảnh riêng và Phase 2 hỗ trợ:

```text
chọn variant
→ đổi ảnh tương ứng
```

Không bắt buộc zoom phức tạp ở Phase này.

---

# 16. Variant Selection

Sử dụng Alpine.js cho interaction phía client.

Ví dụ:

```text
Trọng lượng:
[500g] [1kg]

Loại:
[Hoa hồng] [Lan]
```

Khi chọn combination:

```text
500g + Hoa hồng
```

frontend phải xác định đúng:

```text
variant_id
SKU
price
sale_price
stock
availability
```

Không tự tính variant từ text phía browser rồi tin dữ liệu đó khi submit.

Server phải validate lại variant.

---

# 17. Variant Availability

Nếu combination không tồn tại:

```text
disable option
```

hoặc thông báo:

```text
Phân loại này hiện không có
```

Nếu stock = 0:

```text
Hết hàng
```

và không cho Add to Cart nếu business rule không cho oversell.

---

# 18. Price Display

Price cần xử lý đúng:

```text
regular price
sale price
discount
```

Nếu:

```text
sale_price = null
```

thì không hiển thị giá sale.

Nếu có sale:

```text
120.000đ
99.000đ
-18%
```

Tính discount ở server hoặc helper/service phù hợp.

Không tin dữ liệu price gửi từ browser khi checkout.

---

# 19. Cart

Xây Cart cho guest trước.

Có thể sử dụng:

```text
session cart
```

nếu phù hợp với Master Plan.

Cart item tối thiểu:

```text
product_id
variant_id
quantity
```

Không lưu giá do client gửi.

Khi hiển thị/tính cart:

```text
server reload current price
```

theo rule hiện tại.

---

# 20. Cart Operations

Hỗ trợ:

```text
Add Item

Update Quantity

Remove Item

Clear Cart
```

Validation:

- product tồn tại
- product active
- variant tồn tại
- variant active
- quantity > 0
- stock đủ nếu business rule yêu cầu

Không cho user sửa:

```text
price
discount
subtotal
```

qua request.

---

# 21. Cart Page

Hiển thị:

```text
Product Image
Product Name
Variant
SKU
Unit Price
Quantity
Subtotal
Remove
```

Cuối trang:

```text
Subtotal
Proceed To Checkout
```

Voucher chưa implement nếu Phase 7 mới làm.

Không giả lập voucher giả.

---

# 22. Buy Now

`Buy Now`:

```text
Product Detail
→ selected variant
→ quantity
→ checkout
```

Có thể:

- add temporary cart
hoặc
- checkout direct

chọn giải pháp đơn giản nhất mà không duplicate business logic.

---

# 23. Checkout

Checkout Phase 3 ưu tiên guest checkout.

Form tối thiểu:

```text
Customer Name
Phone
Email optional/required theo business rule
Address
Note
```

Hiển thị:

```text
Order Summary
Products
Variants
Quantity
Price
Subtotal
Shipping Fee nếu có rule
Total
```

Không cần customer login ở Phase này nếu Master Plan chưa yêu cầu.

---

# 24. Checkout Validation

Dùng:

```text
CheckoutRequest
```

hoặc Form Request tương đương.

Validate:

```text
name
phone
email
address
cart
variant
quantity
```

Server phải tính lại toàn bộ:

```text
price
subtotal
total
stock
```

Không tin hidden input price/total từ frontend.

---

# 25. Create Order

Checkout phải gọi:

```text
OrderService
```

hoặc Action đã có.

Luồng:

```text
Validate Cart
↓
Validate Inventory
↓
Calculate Prices
↓
Create Order
↓
Create Order Items Snapshot
↓
Create Initial Status
↓
Adjust/Reserve Inventory nếu business rule Phase 2 yêu cầu
↓
Commit
```

Sử dụng database transaction.

Nếu lỗi:

```text
rollback
```

---

# 26. Order Snapshot

Xác nhận `order_items` lưu:

```text
Product Name

SKU

Variant Description

Unit Price

Quantity

Subtotal
```

Sau khi tạo order:

việc thay đổi Product/Variant không được thay đổi dữ liệu lịch sử order.

---

# 27. Prevent Duplicate Order

Xem xét việc user double click / refresh submit.

Không cần distributed architecture.

Nhưng phải tránh trường hợp đơn được tạo 2 lần dễ dàng.

Có thể dùng:

```text
idempotency token
session checkout token
disable submit button
```

hoặc giải pháp phù hợp.

`Idempotency` là **đảm bảo cùng một thao tác gửi lặp lại không vô tình tạo nhiều kết quả giống nhau, ví dụ tạo hai đơn hàng**.

---

# 28. Order Success Page

Sau khi đặt thành công:

```text
Order Number
Customer
Order Summary
Total
Status
```

Không expose dữ liệu order khác.

Không cho user đổi URL ID để xem đơn của người khác.

---

# 29. Basic Order Lookup

Nếu scope cho phép, có thể tạo:

```text
/order-tracking
```

cho guest nhập:

```text
Order Number
Phone
```

để xem:

```text
Order Status
Basic Order Info
```

Không cho lookup chỉ bằng Order ID.

Nếu chưa cần trong Phase 3, đưa backlog.

---

# 30. Article List

Xây:

```text
Article Category

Article List

Article Detail
```

Chỉ hiển thị:

```text
status = active
published_at <= now
```

nếu schema hỗ trợ.

---

# 31. Article Detail

Hiển thị:

```text
Title
Published Date
Featured Image
Content
Related Articles nếu phù hợp
```

SEO metadata lấy từ Article.

Không render raw content nguy hiểm nếu dữ liệu không đáng tin.

Nếu admin editor lưu HTML, kiểm tra strategy sanitization.

---

# 32. Contact Page

Hiển thị:

```text
Address
Phone
Email
Map
```

từ Settings.

Nếu có contact form:

- validate server
- chống spam cơ bản
- không gửi email trực tiếp thiếu timeout/error handling

Nếu Contact chưa nằm trong Phase 1 schema, có thể dùng email only hoặc backlog.

Không tự mở rộng DB vô lý.

---

# 33. SEO

Phase 3 phải implement SEO frontend.

## Product

```text
title
meta description
canonical
Open Graph cơ bản
```

## Category

```text
title
meta description
canonical
```

## Article

```text
title
meta description
canonical
```

Fallback dùng Site Settings nếu entity thiếu SEO data.

---

# 34. Slug Routing

URL mới nên sạch.

Ví dụ:

```text
/san-pham/{slug}

/danh-muc/{slug}

/bai-viet/{slug}
```

hoặc route convention đã thống nhất.

Không phụ thuộc ID nếu không cần.

Nếu slug trùng phải xử lý theo rule Phase 1.

---

# 35. Legacy URL Compatibility

Dựa trên SEO Inventory Phase 0.

Phase 3 chưa cần migrate production toàn bộ, nhưng chuẩn bị route/redirect foundation cho:

```text
*-dm{id}.html
*-cm{id}.html
legacy product URL
legacy article URL
```

Không để redirect logic hard-code hàng nghìn dòng.

Chuẩn bị cho Phase 5.

---

# 36. Breadcrumb

Tạo breadcrumb cho:

```text
Category
Product
Article
```

Ví dụ:

```text
Home
→ Cây cảnh
→ Cây nội thất
→ Product
```

Có thể chuẩn bị structured data nếu phù hợp nhưng không over-engineering.

---

# 37. Structured Data

Nếu dễ triển khai, hỗ trợ schema.org cơ bản cho:

```text
Product
Breadcrumb
Article
Organization
```

Không thêm fake review/rating.

Chỉ xuất dữ liệu có thật.

---

# 38. Alpine.js

Alpine.js chỉ dùng cho interaction nhỏ:

```text
Variant Selection

Quantity +/- 

Mobile Menu

Dropdown

Modal

Image Gallery

Cart Interaction nếu phù hợp
```

Không biến toàn bộ app thành SPA.

---

# 39. JavaScript Principles

Không duplicate business rule quan trọng giữa JS và backend.

Ví dụ JavaScript có thể:

```text
disable invalid variant
```

nhưng server vẫn phải validate lại.

Client-side validation chỉ để UX.

Server-side validation là nguồn quyết định cuối.

---

# 40. Components

Tạo reusable Blade Components khi hợp lý.

Ví dụ:

```text
ProductCard

Price

Breadcrumb

Pagination

Alert

FormInput

Banner

ArticleCard
```

Không component hóa mọi `div`.

Ưu tiên readability.

---

# 41. Error Handling

Frontend phải xử lý:

```text
404

Product not found

Category not found

Product inactive

Variant unavailable

Out of stock

Checkout validation error

Order creation error
```

Không show:

```text
stack trace
SQL error
internal path
credential
```

trên production mode.

---

# 42. Empty States

Thiết kế trạng thái:

```text
Cart empty

Search no result

Category no product

Article category empty
```

Không để trang trắng.

---

# 43. Accessibility

Áp dụng cơ bản:

```text
alt text

label form

button semantic

keyboard usable

focus state

contrast reasonable
```

Không cần audit WCAG đầy đủ trong Phase 3 nhưng tránh lỗi rõ ràng.

---

# 44. Performance

Kiểm tra:

```text
N+1 query

pagination

image size

lazy loading

eager loading

cache nếu phù hợp
```

Không cache dữ liệu động phức tạp quá sớm.

Có thể cache:

```text
categories
settings
homepage data
```

nếu rõ ràng và có invalidation hợp lý.

---

# 45. Image Handling

Frontend dùng image từ Laravel storage mới.

Không phụ thuộc trực tiếp legacy path.

Có:

```text
fallback image
```

nếu file thiếu.

Không output path local server.

---

# 46. Security

Kiểm tra:

- CSRF
- XSS
- SQL Injection
- IDOR
- parameter tampering
- checkout tampering
- session security
- unsafe HTML
- open redirect nếu có
- file path exposure

Đặc biệt test:

```text
price manipulation

variant manipulation

quantity manipulation

order lookup manipulation
```

---

# 47. Testing – Automated

Tạo Feature Test tối thiểu cho:

## Home

- load thành công

## Category

- active category visible
- inactive category not public

## Product

- active product visible
- invalid slug → 404
- variant selection data đúng

## Search

- valid search
- empty result

## Cart

- add
- update
- remove
- invalid variant rejected
- invalid quantity rejected

## Checkout

- validation
- create order
- order item snapshot
- total calculated server-side
- invalid stock rejected

## Order

- status initial đúng
- duplicate order protection nếu implement

## Article

- published article visible
- unpublished article hidden

---

# 48. Manual UI Test

Test các luồng trên browser.

## Desktop

```text
Home
Category
Search
Product
Cart
Checkout
Article
```

## Mobile

test lại toàn bộ core flow.

Luồng bắt buộc:

```text
Home
↓
Category
↓
Product
↓
Select Variant
↓
Add Cart
↓
Cart
↓
Checkout
↓
Order Success
```

Chụp EVD cho các bước quan trọng nếu môi trường cho phép.

---

# 49. Cross-Browser

Test tối thiểu nếu có thể:

```text
Chrome
Edge
Mobile Chrome
Safari/iOS nếu môi trường có
```

Nếu không test được browser nào:

```text
NOT TESTED
```

kèm lý do.

---

# 50. Regression

Chạy toàn bộ:

```text
Phase 1 tests

Phase 2 tests

Phase 3 tests
```

Đảm bảo frontend không phá Admin/Exment.

---

# 51. Legacy Comparison

Đối chiếu chức năng public legacy.

Tạo bảng:

```text
Legacy Feature

New Feature

Status

Behavior Changed?

Reason
```

Ví dụ:

```text
Legacy Product Detail
→ New Product Detail
→ REBUILT

Legacy Variant JSON
→ New Variant Table
→ CHANGED IMPLEMENTATION
```

Business behavior quan trọng phải được preserve trừ khi Master Plan quyết định thay đổi.

---

# 52. UI không clone Shopee

Có thể tham khảo UX:

```text
Product Grid

Product Detail Layout

Variant Selector

Cart

Checkout
```

nhưng:

- không sử dụng logo Shopee
- không copy CSS/HTML
- không copy asset
- không tạo giao diện gây hiểu lầm là Shopee

Thiết kế riêng cho thương hiệu bancay.vn / GreenHome.

---

# 53. Không làm trong Phase 3

Không làm:

```text
Customer Account hoàn chỉnh

Wishlist

Voucher Engine

Review System

Online Payment

Shipping API

Flash Sale

Marketplace

Multi Seller

Mobile App

Next.js

React SPA

Full Legacy Data Migration

Production Cutover
```

Nếu cần tính năng nào để frontend chạy, implement mức tối thiểu hoặc đưa backlog.

Không tự mở rộng sang Phase 7.

---

# 54. Documentation

Cập nhật:

```text
docs/
├── frontend-architecture.md
├── frontend-routes.md
├── cart-checkout.md
├── seo-frontend.md
└── phase-3-report.md
```

Có thể điều chỉnh tên file theo convention.

---

# 55. Completion Criteria

Phase 3 chỉ hoàn thành khi:

```text
Home hoạt động

Category hoạt động

Search hoạt động

Product Detail hoạt động

Variant selection hoạt động

Cart hoạt động

Checkout hoạt động

Order được tạo đúng

Order item snapshot đúng

Article hoạt động

Responsive core flow hoạt động
```

Ngoài ra:

- automated tests pass
- manual UI tests core flow pass
- Phase 1/2 regression pass
- không có Critical/High security issue chưa xử lý hoặc chưa ghi nhận
- không có blocker core checkout flow

---

# 56. Báo cáo cuối Phase 3

Báo cáo theo cấu trúc:

```text
1. Executive Summary

2. Phase 2 Inputs Used

3. Frontend Architecture

4. Routes

5. Layout / Components

6. Homepage

7. Category

8. Search

9. Product Detail

10. Variant Selection

11. Cart

12. Checkout

13. Order Creation

14. Articles

15. SEO

16. Responsive

17. Security

18. Performance

19. Automated Tests

20. Manual UI Tests

21. Cross-Browser Tests

22. Evidence

23. Legacy Comparison

24. Risks

25. Technical Debt

26. Phase 4 Readiness
```

Cuối báo cáo phải ghi:

```text
PHASE 4 STATUS:
READY
```

hoặc:

```text
PHASE 4 STATUS:
READY WITH RISKS
```

hoặc:

```text
PHASE 4 STATUS:
NOT READY
```

Nếu chưa READY, phải liệt kê blocker cụ thể.

---

# 57. Quy tắc cuối cùng

Trong Phase 3:

**ĐƯỢC PHÉP**

```text
Create Blade frontend

Create Frontend Controllers

Create Form Requests

Create Cart

Create Checkout

Use existing Services/Actions

Create Alpine.js interaction

Create Feature Tests

Create UI components

Update documentation
```

**KHÔNG ĐƯỢC PHÉP**

```text
Modify legacy production database

Delete legacy website

Run full production data migration

Switch DNS

Deploy production cutover

Create marketplace

Split frontend repository

Replace Blade bằng React/Next.js
```

Nếu phát hiện kiến trúc hiện tại không đáp ứng yêu cầu, tạo:

```text
ARCHITECTURE CHANGE PROPOSAL
```

ghi rõ:

- vấn đề
- giải pháp đề xuất
- ảnh hưởng
- migration cost
- backward compatibility

Không tự ý thay đổi nền tảng.

**Chỉ thực hiện Phase 3 – Frontend Blade Development.**