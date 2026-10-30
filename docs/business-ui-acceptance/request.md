Bạn là **Senior Product Owner + Laravel/Exment Architect + UX Reviewer + QA Lead**.

Dự án `bancay.vn` đã hoàn thành các Phase kỹ thuật trước Production Cutover.

Trước khi chạy Phase 6, hãy thực hiện một vòng **Business & UI Acceptance Review** để xác minh hệ thống hiện tại thực sự đáp ứng yêu cầu của khách hàng, không chỉ “code chạy được”.

Đây là **release gate bổ sung trước Phase 6**.

Không triển khai production trong task này.

Không đổi DNS.

Không cutover.

Không tự ý làm lại toàn bộ giao diện.

---

# 1. Mục tiêu

Trả lời chính xác câu hỏi:

```text
Website hiện tại đã đáp ứng yêu cầu thực tế của khách hàng chưa?
```

Đánh giá dựa trên:

```text
Current Source Code
Current Staging UI
Exment Admin
Frontend Blade
Database Design
Master Plan
Previous Phase Reports
Customer Requirements
Reference Screenshots
Reference Shopee Shop
```

Không chỉ đọc report cũ.

Phải kiểm tra implementation thực tế.

---

# 2. Yêu cầu khách hàng cần coi là Acceptance Criteria

Khách hàng muốn website mới có trải nghiệm mua hàng quen thuộc tương tự một shop trên Shopee, nhưng là website riêng của GreenHome/bancay.vn.

Reference shop:

```text
https://shopee.vn/sieu_thi_lam_vuon_greenhome?shopCollection=147796242#product_list
```

Chỉ tham khảo:

```text
information architecture
product browsing UX
category UX
product card UX
variant/product management UX
```

Không clone Shopee 1:1.

Không copy source/CSS/assets/thương hiệu Shopee.

---

# 3. Ngành hàng kinh doanh cần hỗ trợ

Website phải phù hợp với định hướng kinh doanh:

```text
Cây nội thất / cây văn phòng

Phân bón

Đất / giá thể nếu có

Dụng cụ làm vườn

Hệ thống tưới / thiết bị tưới

Đèn và sản phẩm phục vụ cảnh quan

Sản phẩm/cụm sản phẩm phục vụ thiết kế cảnh quan
```

Đặc biệt:

```text
Cây nội thất / cây văn phòng
```

có định hướng bán/phục vụ chủ yếu quanh khu vực Hà Nội.

Không hard-code logic Hà Nội một cách không cần thiết nếu requirement chỉ mang tính phạm vi kinh doanh.

Nhưng frontend phải có khả năng truyền đạt rõ phạm vi cung cấp/dịch vụ nếu khách hàng yêu cầu.

---

# 4. Dịch vụ tư vấn cảnh quan

Website phải có một khu vực riêng cho:

```text
Tư vấn thiết kế cây xanh / cảnh quan
```

Đây không phải Product thông thường.

Cần kiểm tra hệ thống hiện tại đã có hay chưa:

```text
Landing/section giới thiệu dịch vụ

Hình ảnh công trình / ý tưởng

Nội dung mô tả

CTA liên hệ / yêu cầu tư vấn

Form hoặc phương thức liên hệ

SĐT / Zalo / Messenger nếu requirement có
```

Có thể phục vụ các nhu cầu như:

```text
Cây xanh văn phòng

Ban công

Sân vườn

Tiểu cảnh

Hệ thống tưới

Cảnh quan nhỏ
```

Không tự tạo nghiệp vụ báo giá phức tạp nếu khách hàng chưa yêu cầu.

---

# 5. Kiểm tra Homepage

Đánh giá homepage hiện tại có đủ:

```text
Header

Search

Menu ngành hàng

Banner

Danh mục nổi bật

Sản phẩm nổi bật

Sản phẩm mới / bán chạy nếu có

Khu vực cây nội thất

Khu vực phân bón

Khu vực thiết bị tưới

Khu vực cảnh quan

Khu vực tư vấn thiết kế

Bài viết

Footer

Cart
```

Phải đánh giá:

```text
IMPLEMENTED
PARTIAL
MISSING
NOT REQUIRED
```

Không đánh giá chỉ dựa trên source; mở UI thực tế.

---

# 6. Shop-style Product Listing

So sánh trải nghiệm Product Listing với shop Shopee tham chiếu.

Product Card nên có hợp lý:

```text
Product Image

Product Name

Current Price

Old Price nếu có

Discount nếu có

Stock state nếu cần

Rating nếu hệ thống thật sự có

Sold count chỉ nếu có dữ liệu thật

CTA / clickable product
```

Không được fake:

```text
rating
sold count
review
discount
```

chỉ để giống Shopee.

Đánh giá grid:

```text
Desktop
Tablet
Mobile
```

---

# 7. Category UX

Khách hàng cần có trải nghiệm duyệt hàng quen thuộc.

Kiểm tra:

```text
Category hierarchy

Category menu

Subcategory

Product filtering

Search

Sorting

Pagination
```

Ví dụ category có thể gồm:

```text
Cây nội thất

Phân bón

Dụng cụ làm vườn

Thiết bị tưới

Đèn cảnh quan

Đất / giá thể
```

Không bắt buộc tên category đúng 100% ví dụ trên nếu database thực tế khác.

---

# 8. Product Detail UX

Trang Product Detail phải kiểm tra:

```text
Gallery ảnh

Tên sản phẩm

Giá

Giá sale

Variant

SKU

Tồn kho

Số lượng

Add to Cart

Buy Now

Mô tả

Thông tin chi tiết

Hướng dẫn sử dụng

Sản phẩm liên quan
```

Layout phải dễ hiểu với người đã quen sử dụng Shopee.

Không cần clone layout Shopee.

---

# 9. Product Classification / Variant – yêu cầu quan trọng

Khách hàng đặc biệt muốn quản lý sản phẩm tương tự màn hình Shopee Seller:

```text
Phân loại hàng

Nhóm phân loại 1

Nhóm phân loại 2

Các tùy chọn trong từng nhóm

Các combination variant
```

Ví dụ:

```text
Phân loại 1:
Loại

Tùy chọn:
Bộ 5 béc 10m 20cm
Bộ 10 béc 15 mét dây
Bộ 15 béc 20 mét dây
Bộ 20 béc 25 mét dây
Bộ 25 béc 30 mét dây
Đồng hồ tưới hẹn giờ
Đồng hồ hẹn giờ pin
```

Hoặc:

```text
Phân loại 1:
Khối lượng

500g
1kg
2kg

Phân loại 2:
Loại cây

Hoa hồng
Lan
Cây cảnh
```

Kiểm tra Admin hiện tại có cho phép quản lý linh hoạt mô hình này không.

---

# 10. Variant Admin Acceptance

Trong Exment/Admin phải kiểm tra thực tế luồng:

```text
Create Product
↓
Add Classification Group
↓
Add Option Values
↓
Add Classification Group 2
↓
Generate/Create Variant Combinations
↓
Set SKU
↓
Set Price
↓
Set Sale Price
↓
Set Inventory
↓
Set Image nếu variant hỗ trợ
↓
Save
```

Đánh giá UX theo:

```text
PASS
PASS BUT HARD TO USE
PARTIAL
FAIL
```

Không chỉ kiểm tra database hỗ trợ variant.

Khách hàng cần **quản lý được trên giao diện**.

---

# 11. Variant Matrix

Nếu Product có 2 nhóm:

```text
Size:
S / M

Color:
Red / Green
```

Admin nên thể hiện được các combination:

```text
S / Red
S / Green
M / Red
M / Green
```

và quản lý:

```text
SKU
Price
Stock
Status
```

Kiểm tra xem hiện tại đã đáp ứng hay chưa.

Nếu database đúng nhưng UI quản trị rất khó sử dụng:

đánh:

```text
FUNCTIONALLY PASS
UX FAIL
```

---

# 12. Product Admin UI tổng thể

So sánh tư duy tổ chức thông tin với Shopee Seller reference.

Không cần giống pixel.

Nhưng nên có logical sections/tabs tương đương:

```text
Thông tin cơ bản

Thông tin chi tiết

Mô tả

Thông tin bán hàng

Hình ảnh

SEO

Thông tin khác
```

Kiểm tra Product form hiện tại có bị:

```text
quá dài
khó tìm field
variant khó nhập
price/stock khó quản lý
```

hay không.

---

# 13. Category-specific Product Data

Đánh giá có nhu cầu và hệ thống có khả năng quản lý các thông tin đặc thù.

Ví dụ cây:

```text
Kích thước

Chiều cao

Loại chậu

Điều kiện ánh sáng

Mức tưới nước

Không gian phù hợp
```

Ví dụ tưới:

```text
Loại béc

Chiều dài dây

Số lượng béc

Áp lực / thông số
```

Không tự triển khai tất cả nếu khách chưa yêu cầu.

Nhưng xác định kiến trúc hiện tại có đủ linh hoạt để thêm các thuộc tính này sau này hay không.

---

# 14. Cây nội thất / Hà Nội

Kiểm tra frontend có thể thể hiện rõ:

```text
cây nội thất
cây văn phòng
dịch vụ tại Hà Nội
```

nếu đây là định hướng bán hàng.

Đánh giá:

- category/landing page
- SEO landing page
- CTA tư vấn
- delivery/service note

Không yêu cầu geo-block khách ngoài Hà Nội trừ khi có requirement rõ.

---

# 15. Landscape Consultation UX

Kiểm tra website có section/page phù hợp với hình ảnh khách hàng cung cấp về:

```text
ban công
tiểu cảnh
cây xanh
chậu
hệ thống nước/tưới
cảnh quan
```

Yêu cầu tối thiểu:

```text
Ảnh đại diện đẹp

Nội dung giới thiệu

Các loại dịch vụ

CTA "Nhận tư vấn"

Form liên hệ hoặc contact action
```

Ví dụ CTA:

```text
Nhận tư vấn thiết kế

Đăng ký tư vấn

Liên hệ GreenHome
```

Không biến thành ecommerce Product nếu bản chất là Service.

---

# 16. Consultation Form

Nếu đã có form:

test:

```text
Name

Phone

Location

Service Type

Description

Optional Image
```

chỉ nếu implementation hiện tại có hoặc requirement đã xác nhận.

Nếu hiện tại chỉ có:

```text
Name
Phone
Message
```

hãy đánh giá có đủ cho MVP không.

Không tự mở rộng scope quá mức.

---

# 17. Mobile UX

Khách hàng quen dùng Shopee, nên mobile UX rất quan trọng.

Test:

```text
Homepage

Category

Product List

Product Detail

Variant Selector

Cart

Checkout

Consultation CTA
```

Kiểm tra:

```text
button size
navigation
sticky CTA nếu có
variant selection
images
forms
scroll
```

---

# 18. Cart / Checkout

Website mới phải cho khách mua đơn giản.

Test:

```text
Product
→ Variant
→ Quantity
→ Cart
→ Checkout
```

Checkout tối thiểu:

```text
Name
Phone
Address
Note
Order summary
Total
```

Không yêu cầu giống toàn bộ Shopee checkout.

---

# 19. Customer familiarity

Đánh giá UX theo góc nhìn:

```text
Người dùng quen mua hàng trên Shopee có hiểu cách sử dụng website này ngay không?
```

Chấm:

```text
1 = khó hiểu
2
3 = trung bình
4
5 = rất quen thuộc/dễ dùng
```

Đánh giá riêng:

```text
Product browsing
Variant selection
Cart
Checkout
Search
Mobile
```

---

# 20. Search

Kiểm tra:

```text
Search bar dễ thấy

Search product

Vietnamese keyword

No result

Mobile search
```

Search là chức năng quan trọng với catalog nhiều sản phẩm.

---

# 21. Trust Signals

Kiểm tra website có đủ thông tin tạo tin cậy:

```text
Business name

Phone

Address

Contact

Delivery information

Return/warranty information nếu có

About

Social links
```

Không tự bịa chính sách.

Nếu chưa có data:

ghi:

```text
CONTENT REQUIRED FROM CUSTOMER
```

---

# 22. Content Gap

Tách rõ:

```text
SYSTEM GAP
```

và:

```text
CONTENT GAP
```

Ví dụ:

```text
Form tư vấn chưa tồn tại
→ SYSTEM GAP

Trang tư vấn đã có nhưng chưa có ảnh thật
→ CONTENT GAP
```

Không bắt developer sửa code nếu chỉ thiếu nội dung khách hàng.

---

# 23. Shopee Reference Comparison

Lập bảng:

| Feature | Shopee Reference | Current bancay.vn | Required? | Status | Action |
|---|---|---|---|---|---|

Ít nhất kiểm tra:

```text
Shop header

Search

Category navigation

Product grid

Product card

Price/sale

Product detail

Variants

Cart

Checkout

Seller-style Product Management

Variant classification groups

Mobile UX
```

Không yêu cầu clone những chức năng marketplace như:

```text
Seller following
Shopee coin
Shopee Live
Shopee ads
Platform chat infrastructure
Marketplace rating ecosystem
```

trừ khi khách hàng yêu cầu riêng.

---

# 24. Requirement Traceability Matrix

Tạo:

| REQ ID | Customer Requirement | Source/Evidence | Current Implementation | Test | Result |
|---|---|---|---|---|---|

Ví dụ:

```text
REQ-01
Shopee-like product browsing

REQ-02
Product classification groups

REQ-03
Cây nội thất

REQ-04
Phân bón

REQ-05
Thiết bị tưới

REQ-06
Đèn/cảnh quan

REQ-07
Landscape consultation

REQ-08
Mobile-friendly purchase

REQ-09
Hanoi indoor-plant positioning
```

---

# 25. Không tự coi tất cả Shopee feature là requirement

Phân loại:

```text
MUST HAVE

SHOULD HAVE

NICE TO HAVE

NOT REQUIRED
```

Ví dụ:

```text
Variant management
→ MUST HAVE

Product grid
→ MUST HAVE

Shopee Live
→ NOT REQUIRED

Multi Seller
→ NOT REQUIRED
```

---

# 26. UI Review

Kiểm tra screenshot thực tế của website hiện tại.

Đánh giá:

```text
Visual hierarchy

Spacing

Typography

Product card consistency

Image ratio

Mobile layout

Navigation clarity

CTA clarity

Variant UX

Checkout UX
```

Không chỉ đánh giá chức năng.

---

# 27. Không redesign nếu chưa cần

Task này là:

```text
ANALYZE + VERIFY
```

không phải:

```text
REBUILD UI
```

Nếu thấy vấn đề:

ghi recommendation.

Chỉ sửa nếu issue là blocker rõ ràng và user đã cho phép fix.

---

# 28. Acceptance Test – Admin

Thực hiện UI test:

```text
Login Admin

Create Category

Create Product

Upload Images

Create Classification Group 1

Create Option Values

Create Group 2

Generate Variants

Set SKU

Set Price

Set Stock

Publish
```

Sau đó xác minh frontend hiển thị đúng.

---

# 29. Acceptance Test – Customer

Thực hiện:

```text
Open Homepage

Browse Category

Search Product

Open Product

Select Variant

Change Quantity

Add Cart

Checkout

Create Order
```

Sau đó:

```text
Admin
→ Order
```

xem đơn đúng.

---

# 30. Acceptance Test – Consultation

Thực hiện:

```text
Homepage
→ Landscape Consultation
→ Open Service Detail / Section
→ Click Consultation CTA
→ Submit Contact/Tư vấn
```

Nếu flow chưa tồn tại:

```text
MISSING REQUIREMENT
```

nếu khách đã xác định đây là yêu cầu.

---

# 31. Evidence

Mỗi requirement quan trọng phải có EVD:

```text
Screenshot
Route/URL
UI state
Test result
Source location nếu cần
```

Không chỉ ghi:

```text
looks implemented
```

---

# 32. Severity của Gap

Phân loại gap:

```text
BLOCKER
HIGH
MEDIUM
LOW
CONTENT ONLY
```

## BLOCKER

Ví dụ:

```text
Không quản lý được variant

Checkout không hoạt động

Không có sản phẩm category chính

Core mobile UX bị vỡ
```

## HIGH

Ví dụ:

```text
Variant có nhưng Admin UX quá khó sử dụng

Không có landscape consultation dù khách yêu cầu
```

---

# 33. Release Rule

Nếu còn:

```text
BLOCKER
```

hoặc:

```text
HIGH requirement gap
```

ảnh hưởng trực tiếp yêu cầu khách hàng:

```text
PHASE 6 BUSINESS STATUS: NOT READY
```

Dù technical Phase 5 đang PASS.

Không cho phép technical readiness che business requirement gap.

---

# 34. Technical Ready ≠ Business Ready

Đánh giá riêng:

```text
TECHNICAL READINESS

BUSINESS READINESS

UX READINESS

CONTENT READINESS
```

Ví dụ có thể:

```text
Technical: READY

Business: NOT READY
```

Trong trường hợp đó chưa cutover.

---

# 35. Scope Recommendation

Nếu có gap, chia thành:

```text
FIX BEFORE PHASE 6

CAN FIX AFTER RELEASE

CONTENT REQUIRED

OUT OF SCOPE
```

Không đưa tất cả enhancement thành blocker.

---

# 36. Ưu tiên trước release

Ưu tiên:

```text
1. Product / Variant

2. Cart / Checkout

3. Category / Search

4. Mobile

5. Landscape Consultation

6. SEO/business content

7. UI polish
```

trừ khi requirement thực tế quy định khác.

---

# 37. Output bắt buộc

Trả báo cáo:

```text
1. Executive Summary

2. Customer Requirements Extracted

3. Shopee Reference Analysis

4. Current Frontend Assessment

5. Current Exment/Admin Assessment

6. Product Variant Assessment

7. Product Management UX

8. Category / Search Assessment

9. Cart / Checkout Assessment

10. Mobile Assessment

11. Landscape Consultation Assessment

12. Hanoi / Indoor Plant Requirement

13. Requirement Traceability Matrix

14. Shopee Comparison Matrix

15. Missing Requirements

16. System Gaps

17. Content Gaps

18. Acceptance Test Results

19. Evidence

20. Fix Before Release

21. Can Defer

22. Final Business Readiness
```

---

# 38. Final Acceptance Matrix

Tạo bảng:

| Area | Requirement | Current Status | Gap | Release Blocker |
|---|---|---|---|---|
| Homepage | | | | |
| Category | | | | |
| Search | | | | |
| Product | | | | |
| Variant | | | | |
| Product Admin | | | | |
| Cart | | | | |
| Checkout | | | | |
| Mobile | | | | |
| Cây nội thất | | | | |
| Phân bón | | | | |
| Tưới | | | | |
| Cảnh quan | | | | |
| Tư vấn cảnh quan | | | | |
| SEO | | | | |

---

# 39. Final Status

Chỉ kết luận một trong:

```text
PHASE 6 BUSINESS STATUS: READY
```

hoặc:

```text
PHASE 6 BUSINESS STATUS: READY WITH ACCEPTED LOW GAPS
```

hoặc:

```text
PHASE 6 BUSINESS STATUS: NOT READY
```

## READY

Khi:

```text
Core customer requirements PASS

Shopee-like purchasing UX đạt mức hợp lý

Variant management PASS

Admin product management PASS

Cart/Checkout PASS

Mobile PASS

Landscape consultation requirement PASS hoặc đã xác định không phải blocker

Không còn BLOCKER/HIGH business gap
```

## READY WITH ACCEPTED LOW GAPS

Chỉ còn:

```text
minor visual polish

minor content gap

nice-to-have
```

và phải liệt kê rõ.

## NOT READY

Nếu còn:

```text
missing core feature

missing variant management

broken purchase flow

major mobile issue

missing required service flow

major mismatch với yêu cầu khách hàng
```

---

# 40. Nếu NOT READY

Phải tạo ngay bảng hành động:

| Priority | Gap | Required Fix | Affected Area | Acceptance Test |
|---|---|---|---|---|

Không bắt đầu Phase 6.

---

# 41. Quy tắc cuối cùng

Không đánh giá website chỉ bằng tiêu chí:

```text
code compiles
tests pass
```

Phải đánh giá:

```text
Does it actually satisfy the customer's requested website?
```

Không clone Shopee.

Mục tiêu là:

**một website GreenHome/bancay.vn độc lập, có trải nghiệm bán hàng quen thuộc với người dùng Shopee, quản lý Product/Variant thuận tiện, đồng thời hỗ trợ mảng cây nội thất và tư vấn thiết kế cảnh quan.**

**Chỉ thực hiện Business/UI Acceptance Review. Không Production Cutover trong task này.**