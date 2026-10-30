# Business & UI Acceptance Review — bancay.vn

Review ngày 29–30/10/2026. Baseline `ba7a3c7`, nhánh `feature/develop`, worktree sạch trước khi tạo tài liệu/công cụ review. Yêu cầu gốc: [request](business-ui-acceptance/request.md). Đây là đánh giá độc lập bổ sung cho release gate kỹ thuật.

## 1. Executive Summary

**Website hiện tại chưa đáp ứng đầy đủ yêu cầu thực tế của khách hàng.** Core mua hàng hoạt động, dữ liệu phân loại được chuẩn hóa và admin có thể tạo sản phẩm/SKU. Tuy nhiên, luồng tư vấn cảnh quan chưa tồn tại, quản trị SKU thiếu bảng chỉnh sửa thuận tiện, catalog đang chủ yếu là cây cảnh và còn nội dung không phù hợp để công khai.

| Readiness | Kết quả | Lý do |
|---|---|---|
| Technical release | NOT READY | P5-B01/B02/B03 từ review kỹ thuật vẫn mở; task này không tái chứng nhận production |
| Business | NOT READY | BA-G01 dịch vụ tư vấn và BA-G04 catalog/nội dung kinh doanh |
| UX | NOT READY | BA-G02: chức năng variant PASS, trải nghiệm quản lý nhiều SKU FAIL |
| Content | NOT READY | Thiếu catalog ngành hàng chính, ảnh TEST, sản phẩm chưa có gallery và nội dung legacy chưa được duyệt lại |

Stack đọc từ runtime: PHP 8.3.36, Laravel 12.70.2, Exment 7.0.0, MariaDB 10.11.19. Main có **121 products, 119 visible products, 115 visible migrated products; 14 categories, 12 visible**. Con số 120 visible trong checkpoint cũ không được dùng thay phép đo mới. Hai category demo/QA hiện DRAFT; đây là trạng thái lúc bắt đầu review, không phải thay đổi do review.

Frontend hiện tại được mở trực tiếp ở 8000 bằng browser; writes chỉ trên bản clone 8004. Clone ban đầu khớp 98 base tables, có private storage và log mail; không importer/reseed. [Môi trường](business-ui-acceptance/evidence/qa-environment.json), [inventory](business-ui-acceptance/evidence/inventory.json).

## 2. Customer Requirements Extracted

Nguồn: Master Plan, request hiện tại, REF-01/02 màn hình Seller, REF-03 mô tả nhu cầu mua hàng/ngành hàng/Hà Nội, REF-04/05/06 ý tưởng ban công–tiểu cảnh, REF-07 yêu cầu tư vấn và bán set cây. [Hashes của 7 ảnh](business-ui-acceptance/evidence/customer-reference-hashes.json); bản chat gốc không đưa vào Git.

| Priority | Yêu cầu |
|---|---|
| MUST HAVE | Website một cửa hàng; duyệt/tìm hàng, category, giá, phân loại 1–2 nhóm, SKU/stock, cart/checkout dễ dùng trên mobile |
| MUST HAVE | Người bán quản lý nhóm phân loại và tổ hợp trên UI; thao tác giá/tồn đủ thuận tiện cho catalog thực tế |
| MUST HAVE | Hỗ trợ cây nội thất/văn phòng, phân bón, dụng cụ, tưới, đèn/cảnh quan và set cây; có luồng tư vấn thiết kế riêng |
| SHOULD HAVE | Định vị cây nội thất/văn phòng quanh Hà Nội; thông tin giao hàng/dịch vụ, nội dung đáng tin, merchandising theo ngành |
| SHOULD HAVE | Hướng dẫn/thông số rõ, bộ lọc hữu ích khi catalog nhiều ngành; SEO landing theo nhu cầu |
| NICE TO HAVE | Sticky buy CTA, rich editor, tiện ích bulk nâng cao ngoài bảng SKU cơ bản |
| NOT REQUIRED ở gate này | Marketplace, multi-seller, Shopee Live/coin/ads/follow/chat platform; account/voucher/review/payment nâng cao thuộc Phase 7 |

Tên Onegarden/logo hiện tại được người dùng yêu cầu khôi phục trước review. Không tự đổi sang GreenHome. Khách cần chốt cách dùng Onegarden/GreenHome/bancay.vn trong nội dung cuối cùng.

## 3. Shopee Reference Analysis

Đã thử mở [shop tham chiếu](https://shopee.vn/sieu_thi_lam_vuon_greenhome?shopCollection=147796242#product_list). Browser ban đầu hiện shop header/search/tab sản phẩm, sau đó chuyển sang trang **Login Required / traffic verification**. Không đăng nhập hoặc vượt chặn. Product grid/detail live của shop: **NOT VERIFIED** trong lần này; không giả nhận đã so sánh pixel hay thử mua tại Shopee.

REF-01/02 cho thấy nhóm phân loại với các giá trị và khu vực danh sách phân loại. Đây là bằng chứng trực tiếp cho kỳ vọng quản trị. Những mục mua hàng khác được đánh giá theo acceptance criteria của người dùng; bảng đối chiếu ghi rõ giới hạn tham chiếu. Không sao chép source/CSS/assets/thương hiệu Shopee.

## 4. Current Frontend Assessment

UI có phong cách cửa hàng cây theo hướng editorial, không phải storefront có mật độ thông tin như sàn. Đây không tự động là lỗi; khoảng cách cần xử lý là khả năng nhận diện ngành hàng và thao tác, không phải màu sắc/pixel.

| Homepage item | Status | Evidence / nhận xét |
|---|---|---|
| Header / logo | IMPLEMENTED | Logo Onegarden, cart, navigation; screenshot homepage |
| Search | IMPLEMENTED | Desktop hiện trực tiếp; mobile nằm trong menu |
| Menu ngành hàng | PARTIAL | Có hierarchy; dữ liệu chủ yếu cây, thiếu các ngành hàng đã yêu cầu |
| Banner | IMPLEMENTED | Hero và banner Onegarden; chưa phải carousel, không bắt buộc carousel |
| Danh mục nổi bật | IMPLEMENTED | Bốn ô đầu hiện cây ngoại thất/hoa; chưa ưu tiên nội thất |
| Sản phẩm nổi bật | IMPLEMENTED | Trường featured; không phải bằng chứng bán chạy |
| Sản phẩm mới | IMPLEMENTED | Sắp theo ID; không có sold-count thật để gọi bán chạy |
| Khu vực cây nội thất | PARTIAL | Có menu và category 44 products; chưa có block/CTA riêng trên home |
| Phân bón | MISSING | Chưa có category/catalog được nhận diện |
| Thiết bị tưới | MISSING | QA có thể tạo/bán; main chưa có catalog ngành này |
| Cảnh quan / set cây | PARTIAL | Có các sản phẩm mix cây; chưa có khu vực giải pháp/công trình |
| Tư vấn thiết kế | MISSING | Không có section/landing/CTA dịch vụ |
| Bài viết | IMPLEMENTED | Danh sách và detail |
| Footer | PARTIAL | Có tên, phone, email, Facebook, Hà Nội; thiếu nội dung chính sách được duyệt |
| Cart | IMPLEMENTED | Hiển thị số lượng và vào giỏ |
| Rating / sold count / marketplace features | NOT REQUIRED | Không tạo số liệu giả |

Source: `resources/views/home/index.blade.php`, `layouts/store.blade.php`, `Services/Storefront/Catalog.php`. [Ảnh desktop listing](business-ui-acceptance/evidence/current-desktop-listing.png), [ảnh mobile menu](business-ui-acceptance/evidence/current-mobile-menu.png).

## 5. Current Exment/Admin Assessment

Đăng nhập native Exment trên QA thành công. Category/product/image/options/SKU/inventory/order đều thao tác được bằng UI. Form không đòi sửa DB. Source relational và Actions phù hợp kiến trúc; không cần thay Laravel/Exment hay thêm marketplace.

Luồng thực thi: category **15** → product **122** → upload ảnh → nhóm Loại → nhóm Chiều dài dây → generate **4** SKU → sửa SKU/price/sale/status/image từng dòng → mở **4** trang inventory để đặt stock 10. Sau đó frontend đặt order **260**, admin đọc đúng snapshot và đổi NEW → CONFIRMED → CANCELLED.

## 6. Product Variant Assessment

**FUNCTIONALLY PASS — UX FAIL cho yêu cầu quản trị thuận tiện.** Nhóm/value linh hoạt, không hard-code ngành hàng. Hai nhóm × hai giá trị sinh đủ bốn tổ hợp, chọn đúng ảnh/giá và stock. UI frontend đổi tổ hợp sang QA-BIZ-4, giá thực 90.000, giá gốc 160.000, lượng 2 → 180.000.

Admin hiển thị các SKU trong accordion, **không có variant matrix dạng bảng chỉnh SKU/price/stock/status**. Mỗi SKU cần lưu riêng; stock sửa tại route khác. Bốn SKU cần ít nhất tám lần submit riêng sau generate để hoàn tất giá và kho. Đây là khác biệt có tác động trực tiếp đến yêu cầu Seller-style, không phải lỗi relational schema.

Generator mặc định INACTIVE/CONTACT/stock 0 là guard đúng; không đề xuất bỏ guard. Không thêm/xóa nhóm sau khi đã có SKU là giới hạn bảo vệ snapshot/combination; cần hướng dẫn và kiểm soát thay đổi, không tự bỏ constraint.

[UI admin](business-ui-acceptance/evidence/admin-configured-skus.png), [mobile selector](business-ui-acceptance/evidence/qa-mobile-variants.png), [DB xác minh](business-ui-acceptance/evidence/data-verification.json). Source: `admin/store/product.blade.php`, `variant-form.blade.php`, `docs/product-management.md`.

## 7. Product Management UX

| Khu vực | Kết quả | Nhận xét |
|---|---|---|
| Basic / category / publication / SEO | PASS | Chia fieldset Basic/SEO; lưu xong chuyển detail |
| Hình ảnh | PASS | Upload, alt, primary, sort có UI; variant chọn từ ảnh đã upload |
| Mô tả / thông số / hướng dẫn | PARTIAL | Textarea HTML; có thể nhập, khó dùng với người bán không biết HTML |
| Nhóm phân loại | PASS BUT HARD TO USE | Values nhập mỗi dòng; cần tạo đủ nhóm trước SKU; không có preview matrix |
| Tổ hợp / giá / trạng thái | PASS BUT HARD TO USE | Accordion từng SKU, không bulk/inline table |
| Tồn kho | PASS BUT HARD TO USE | Điều chỉnh an toàn, nhưng rời product để sửa từng SKU |
| Logical sections / tabs | PARTIAL | Có các section theo trang, chưa có navigation/tab/sticky anchor cho form dài |

Các đặc tính cây (chiều cao, chậu, ánh sáng, tưới) hoặc tưới (béc, dây, áp lực) hiện có thể ghi trong HTML; option chỉ dùng khi tạo biến thể mua hàng. **Chưa có mô hình thuộc tính mô tả theo ngành/typed specification/filter**. Kiến trúc cho phép thêm bằng migration additive/service sau khi chốt field; không phải lý do phải làm ngay toàn bộ hoặc dồn variants vào JSON.

## 8. Category / Search Assessment

Hierarchy/children/ancestor publication có source và UI. Category nội thất chứa 44 products; tên ngành khác có thể tạo bằng admin mà không thay schema, đã chứng minh với category QA tưới. Menu mobile dạng danh sách có thụt cấp; desktop chỉ hiện một số category rồi “Danh mục khác”.

Tìm “hạnh phúc” trả 6 kết quả; từ khóa không khớp trả 0 và trạng thái hướng dẫn. Mobile search hoạt động trong menu, chưa dễ thấy ngay khi vào website. Next trên mobile sang `?page=2`, có 24 cards; sort “Giá tăng dần” gửi `?sort=price_asc`, bỏ page cũ. Chưa có filter khoảng giá/còn hàng/đặc tính; đây là SHOULD HAVE, không biến mọi bộ lọc Shopee thành bắt buộc.

## 9. Cart / Checkout Assessment

**PASS trong phạm vi core hiện có.** QA chọn hai nhóm, số lượng 2, giỏ và checkout đều 180.000. Form có name/phone/address/note, email optional; order admin giữ SKU QA-BIZ-4, variant description, unit price 90.000, quantity 2, subtotal/total 180.000. Cancel trả reserved về 0, physical 10 trên cả bốn SKU; history đủ ba trạng thái.

Checkout nói rõ chưa thanh toán online, cửa hàng liên hệ xác nhận và phí giao hàng chưa tự tính. Không gọi đây là miễn phí vận chuyển. Không giao dịch thật, gửi email khách thật hay tạo đơn trên main.

[Cart](business-ui-acceptance/evidence/cart.png), [checkout mobile](business-ui-acceptance/evidence/checkout-mobile.png), [success](business-ui-acceptance/evidence/order-success-mobile.png), [admin snapshot](business-ui-acceptance/evidence/admin-order.png), [history](business-ui-acceptance/evidence/admin-order-history.png).

## 10. Mobile Assessment

Đã mở main home/list/indoor/product ở desktop 1440×900, tablet 768×1024, mobile viewport 390×844: **12 observations**, grid tương ứng 4/3/2 cột, không horizontal overflow, không broken image trong viewport đã kiểm. [Metrics](business-ui-acceptance/evidence/responsive.json). Mobile hai nhóm giá trị dài, chọn tổ hợp, cart và checkout tiếp tục hoạt động.

CTA mua có kích thước thao tác hợp lý, nhưng không sticky; mô tả legacy dài đẩy nội dung xuống, search giấu trong menu. Đây là cải thiện UX, không phải core mobile bị vỡ. Ảnh fallback không được tính là ảnh sản phẩm thật. Khoảng trống trong full-page screenshot có thể là lazy images chưa vào viewport; chỉ kết luận thiếu gallery từ DB, không suy diễn mọi vùng trống là 404.

Lần review này dùng IAB Chromium với viewport override; **không phải** physical Android/iOS test hoặc fresh Chrome/Edge certification. Evidence Chrome/Edge/Safari của các phase cũ giữ nguyên, không đổi tên thành test mới.

Điểm sau là expert review, **không phải khảo sát khách hàng**:

| UX | Điểm / 5 | Cơ sở |
|---|---:|---|
| Product browsing | 3 | Grid rõ nhưng định hướng cây editorial, thiếu các ngành hàng cần bán |
| Variant selection | 4 | Hai nhóm dễ chọn, giá/SKU thay đổi đúng |
| Cart | 4 | Dòng hàng, phân loại, quantity và summary dễ hiểu |
| Checkout | 4 | Guest checkout ngắn, trạng thái xác nhận rõ |
| Search | 3 | Tiếng Việt/no-result tốt; mobile chưa hiện ngay |
| Mobile tổng thể | 3 | Không vỡ, nhưng dài và chưa tối ưu khám phá catalog nhiều ngành |

## 11. Landscape Consultation Assessment

**MISSING REQUIREMENT — BA-G01 HIGH.** Không có landing/service section, portfolio/ý tưởng, loại dịch vụ, CTA “Nhận tư vấn” hoặc tuyến tiếp nhận chuyên biệt. `/lien-he` hiện có phone/email/Facebook; không có form và không giới thiệu dịch vụ cảnh quan. [Contact UI](business-ui-acceptance/evidence/current-contact.png).

Luồng Home → Landscape Consultation dừng ngay bước tìm entry point; Submit tư vấn **BLOCKED vì feature thiếu**, không ghi PASS nhờ contact chung tồn tại. Source `routes/web.php`, `ContactController`, `home/contact.blade.php`; schema không có consultation entity.

MVP cần service page/section + nội dung cây văn phòng/ban công/tiểu cảnh/tưới + hình hợp lệ + contact CTA rõ người nhận. Có thể dùng phone/Facebook/Zalo được xác nhận thay form. Form lưu lead, optional image, workflow báo giá và CRM **chưa phải bắt buộc**; nếu chọn form mới chốt trường, lưu/notification/owner và validation. Không biến dịch vụ thành product thường chỉ để có nút mua.

## 12. Hanoi / Indoor Plant Requirement

**PARTIAL.** Category “Cây cảnh Nội thất” có 44 products và mô tả nói về cây văn phòng/Hà Nội; footer/contact có Hà Nội. Nhưng homepage không ưu tiên ngành nội thất, không có service/delivery note theo phạm vi phục vụ và chưa có CTA tư vấn văn phòng/Hà Nội riêng. Mô tả category còn tên/link thương hiệu cũ cần khách duyệt, không được tự coi mọi cam kết cũ vẫn đúng.

Đề xuất nội dung ngắn về vùng giao/lắp đặt, CTA tư vấn và category/SEO landing rõ. Không geo-block tỉnh khác; khách cần chốt phạm vi cụ thể trước khi ghi cam kết giao/thi công.

## 13. Requirement Traceability Matrix

| REQ ID | Requirement / priority | Source | Implementation / Test | Result |
|---|---|---|---|---|
| REQ-01 | Shop-style browsing / MUST | Master Plan, request 2/6 | Cards, 4/3/2 grid, category/list screenshots | PARTIAL ngành hàng; core PASS |
| REQ-02 | 1–2 classification groups / MUST | REF-01/02; request 9 | UI tạo Loại + Chiều dài dây, bốn combinations | PASS chức năng |
| REQ-03 | Cây nội thất/văn phòng / MUST | REF-03; request 3 | Category 7, 44 products; UI/DB | PASS catalog, PARTIAL positioning |
| REQ-04 | Phân bón / MUST | REF-03 | Không có category/name match; taxonomy hiện tại là cây | MISSING CONTENT |
| REQ-05 | Thiết bị tưới / MUST | REF-01/03 | Main thiếu; QA tạo/bán được không đổi schema | MISSING CONTENT |
| REQ-06 | Đèn/cảnh quan / MUST | REF-03 | Không có category; “Cây hoa Lồng Đèn” không phải đèn thiết bị | MISSING CONTENT |
| REQ-07 | Tư vấn cảnh quan / MUST | REF-07; request 4/15 | Route/nav/contact review, flow dừng bước đầu | FAIL / BA-G01 |
| REQ-08 | Mobile purchase / MUST | request 17/18 | Two-group mobile cart/checkout; tạo order QA | PASS core |
| REQ-09 | Hanoi indoor positioning / SHOULD | REF-03 | Category/body/footer có Hà Nội, thiếu CTA/vùng phục vụ | PARTIAL / BA-G05 |
| REQ-10 | Dụng cụ làm vườn / MUST | request 3 | Không có category/catalog nhận diện | MISSING CONTENT |
| REQ-11 | Đất/giá thể / SHOULD nếu bán | request 3 “nếu có” | Không có dữ liệu; chưa chốt bán ngay | CONTENT DECISION REQUIRED |
| REQ-12 | Set cây / MUST | REF-07 | Có sản phẩm mix cây thông thường | PARTIAL hình/nội dung/merchandising |
| REQ-13 | Seller-friendly matrix / MUST | REF-01/02; request 10/11 | Từng accordion + stock route riêng | FUNCTIONALLY PASS / UX FAIL |
| REQ-14 | Product details/gallery/related / MUST | request 8 | Gallery đổi ảnh, SKU/price/stock, related UI | PASS chức năng; content FAIL |
| REQ-15 | Guest cart/checkout/order admin / MUST | request 18/29 | Order 260, snapshot 180.000, history/cancel | PASS |
| REQ-16 | Category/search/sort/pagination / MUST | request 7/20 | Vietnamese 6 results; empty; next page24; price sort | PASS core; advanced filters absent |
| REQ-17 | Thông số/hướng dẫn ngành / SHOULD | request 13 | Sanitized HTML, options cho SKU; chưa typed attributes | PARTIAL, không bắt thêm mọi field |
| REQ-18 | Trust/contact/policies / SHOULD | request 21 | Phone/email/Facebook/city; chưa policies được duyệt | PARTIAL / BA-G06 |
| REQ-19 | Real price/discount; no fake rating / MUST | request 6 | QA sale từ price thật, không rating/sold giả | PASS; badge editorial cần rõ nghĩa |
| REQ-20 | Independent single store / MUST | Master Plan | Laravel/Exment/Blade native relational catalog | PASS |

## 14. Shopee Comparison Matrix

“Reference” dưới đây nêu phần quan sát được hoặc expectation do request xác lập; phần live product listing bị chặn không được gán VERIFIED.

| Feature | Shopee Reference | Current bancay.vn | Required? | Status | Action |
|---|---|---|---|---|---|
| Shop header | Live header quan sát được | Logo/tên/contact độc lập | MUST | PASS | Giữ brand riêng |
| Search | Live shop search quan sát được | Global product search; menu trên mobile | MUST | PASS/PARTIAL UX | Làm mobile search dễ thấy hơn |
| Category navigation | Expectation request; live list NOT VERIFIED | Hierarchy, dropdown, mobile menu | MUST | PARTIAL | Bổ sung taxonomy/dữ liệu ngành |
| Product grid | Expectation request; live list NOT VERIFIED | 4/3/2 columns | MUST | PASS core | Không clone density/pixel |
| Product card | Expectation request | Image/name/price/sale/stock/link | MUST | PASS code, content gap | Duyệt ảnh/title |
| Price/sale | Expectation request | Giá variant, giảm giá tính thật | MUST | PASS | Không thêm discount giả |
| Product detail | Expectation request; live detail NOT VERIFIED | Gallery/options/stock/qty/actions/description/related | MUST | PASS core | Nhãn trung tính nhiều ngành |
| Variants buyer | Expectation request | Hai nhóm, chọn tổ hợp, đổi SKU/giá/ảnh | MUST | PASS | Giữ authority server |
| Cart | Expectation request | IDs/qty, summary, cập nhật | MUST | PASS | Giữ core |
| Checkout | Expectation request | Guest name/phone/address/note/summary | MUST | PASS | Chốt nội dung giao hàng |
| Seller management | REF-01/02 sections | Basic/SEO rồi detail/images/options/SKU | MUST | PARTIAL | Điều hướng section/editor dễ dùng |
| Classification groups | REF-01/02 minh họa trực tiếp | Multiline values, nhiều nhóm | MUST | PASS chức năng | Hướng dẫn/quy trình rõ |
| Variant matrix | REF-01/02 + request 11 | Không bảng; sửa từng SKU/kho riêng | MUST | UX FAIL | BA-G02 |
| Mobile UX | Expectation request | Responsive, core purchase chạy | MUST | PASS core / 3 trên 5 UX | Tối ưu search/scroll sau critical gaps |
| Coins/live/multi-seller | Không thuộc yêu cầu | Không có | NOT REQUIRED | N/A | Không triển khai |

## 15. Missing Requirements

Thiếu dịch vụ tư vấn cảnh quan riêng (BA-G01). Chưa đạt quản trị variant thuận tiện theo yêu cầu khách dù chức năng chạy (BA-G02). Chưa có catalog thực cho phân bón/dụng cụ/tưới/đèn và nội dung đủ sạch cho release nhiều ngành (BA-G04). Không gọi các feature Phase 7 hoặc toàn bộ feature Shopee là thiếu bắt buộc.

## 16. System Gaps

BA-G01 cần thêm điểm vào dịch vụ/landing/CTA. BA-G02 cần variant matrix có thể chỉnh SKU/giá/sale/trạng thái và thao tác stock hợp lệ qua service hiện tại; không generic-update reserved/total. BA-G03 cần nhãn frontend phù hợp nhiều ngành và merchandising do category/nội dung dẫn dắt. Chi tiết, severity và owner ở [gap register](business-ui-acceptance/gap-register.csv). Không sửa app hoặc schema trong review.

## 17. Content Gaps

**CONTENT REQUIRED FROM CUSTOMER**: danh mục/SKU/giá/thông số/ảnh cho phân bón, dụng cụ, tưới, đèn; xác nhận có bán đất/giá thể ngay không; nội dung set cây; dịch vụ/vùng cung cấp/portfolio; chính sách giao/đổi trả/bảo hành thực tế; tên thương hiệu thống nhất và địa chỉ công khai nếu có.

DB/UI mới xác nhận **15 visible products không có gallery**; source thiếu ảnh không đồng nghĩa importer làm mất file. Có title `12111`, `ffff` và placeholder. Product **119 / legacy 127**, default variant **139**, dùng ảnh chữ TEST; hash file main khớp bản stage đã migrate, không do review upload vào main. [Data proof](business-ui-acceptance/evidence/data-verification.json), [ảnh đang hiển thị](business-ui-acceptance/evidence/current-product-test-image.png).

Cùng product này giá variant hiển thị 80.000/100.000 nhưng mô tả ngắn còn giá 735.000/975.000 và tên/brand legacy. Đây là mâu thuẫn nội dung mua hàng dù checkout tính đúng server price. Cần duyệt lại title/description/option labels/prices/photos hoặc giữ draft trong release artifact theo quyết định khách, **giữ nguyên dữ liệu gốc/demo**, không tự xóa hoặc reprice trong review.

Ảnh cảnh quan REF-04/05/06 dùng làm định hướng; chưa xác minh là công trình thực đã thi công hay ảnh ý tưởng. Chỉ gọi là “công trình thực tế” sau khi khách xác nhận nguồn/quyền và nội dung. Logo/contact đã được khôi phục trước đó không tự giải quyết các nội dung catalog legacy này.

## 18. Acceptance Test Results

Danh sách đầy đủ: [30 cases](business-ui-acceptance/acceptance-tests.csv). **26 PASS, 1 UX FAIL, 1 PARTIAL, 1 MISSING, 1 BLOCKED**. Các con số là bản ghi review của các bước browser đã thực thi, không phải output PHPUnit hay điểm phần trăm dùng để bỏ qua gap.

| Nhóm | Kết quả | Evidence |
|---|---|---|
| Admin login/create category/product/publish/upload | PASS | Category15/product122; UI screenshots và DB |
| Group1 → Group2 → bốn combinations | PASS | admin-variants / data-verification |
| SKU/price/sale/image/status + stock | PASS BUT HARD TO USE | Bốn SKU, bốn lần lưu giá + bốn trang kho |
| Product admin UX / matrix | PARTIAL / UX FAIL | Không bảng inline/bulk, editor HTML |
| Customer category/search/product/variant/cart/order | PASS | QA order260, 90.000×2, admin snapshot |
| Gallery thật | PASS chức năng | Thumbnail đổi từ variant TEST sang cover; gallery-verification |
| Responsive observations | PASS baseline | 12 page/viewport metrics; 4/3/2 grid |
| Mobile two-group → cart → checkout | PASS | qa-mobile-* screenshots, tổng 180.000 |
| Consultation entry | MISSING | Không route/nav/section |
| Consultation submit | BLOCKED | Không feature để submit; không gửi lead thật ra ngoài |

Regression gần nhất tại baseline ba7a3c7: StorefrontTest 30/244 và StoreAdminTest 24/187 đã PASS trước push; bộ full technical 164/1062 là checkpoint lịch sử. Không đổi chúng thành fresh full-suite result của task này. Task mới không đổi runtime application, có PHP syntax check của collector và data verifier mới PASS.

## 19. Evidence

[Input hashes](business-ui-acceptance/evidence/inputs.json), [runtime inventory](business-ui-acceptance/evidence/inventory.json), [clone receipt](business-ui-acceptance/evidence/qa-environment.json), [snapshot/stock verification](business-ui-acceptance/evidence/data-verification.json), [responsive](business-ui-acceptance/evidence/responsive.json), [gallery](business-ui-acceptance/evidence/gallery-verification.json), và screenshots trong cùng thư mục. QA screenshots dùng data/contact tổng hợp; không có credential/SQL/customer dump trong Git.

Main/stage DB được snapshot trước review. Cleanup receipt ghi rõ domain data và stage nguyên vẹn; native main `admin_operation_log` có thay đổi trong cửa sổ review nên **không tuyên bố toàn bộ 98 main tables có checksum giống nhau**. Không suy diễn người gây thay đổi log, không xóa/khôi phục log để làm đẹp evidence. DB/media/credentials clone ở private; clone/container/user được dọn sau khi xác minh.

## 20. Fix Before Release

| Priority | Gap | Required Fix | Affected Area | Acceptance Test |
|---|---|---|---|---|
| P1 | BA-G02 HIGH | Matrix chỉnh từng combination ngay trong một bảng; batch save có validation và dùng Actions; stock không sửa reserved | Exment Product/Variant | Hai nhóm × hai values → nhìn đủ bốn dòng; chỉnh giá/sale/SKU/status/stock; save/reload/front/order khớp, stale stock từ chối |
| P1 | BA-G04 HIGH | Khách chốt launch assortment, cung cấp dữ liệu ngành thiếu; duyệt TEST/placeholders/tên/giá/brand; cho phép draft ở artifact phát hành khi chưa đủ | Catalog / Content | Mỗi ngành bán lúc launch có category + SKU thật mua được; sample thông số/ảnh/giá/label khớp, checkout/admin snapshot; không mất originals |
| P1 | BA-G01 HIGH | Landing/section tư vấn riêng, dịch vụ/ảnh hợp lệ và CTA có người nhận | Frontend / Content | Home → dịch vụ → CTA → phương thức tiếp nhận đã chọn hoạt động trên mobile/desktop; nếu form thì test validation/lưu/gửi riêng |
| P2 | BA-G03 MEDIUM | Chỉnh copy đa ngành và ưu tiên category nội thất; merchandising theo catalog đã chốt | Homepage/Product | Người mua tìm được từng nhóm hàng và phân biệt sản phẩm/dịch vụ, không nhãn “chiếc cây” cho thiết bị tưới |
| P2 | BA-G05/06 MEDIUM | Chốt vùng cung cấp Hà Nội, chính sách, brand/các thông tin liên hệ công khai | Content / SEO | Home/category/contact/checkout thông điệp nhất quán; không bịa chính sách/geo-block |

Các fix ở bảng là recommendation cho task tiếp theo; review không tự redesign hoặc triển khai chúng. Khách có thể giảm scope launch bằng quyết định rõ ràng, nhưng chưa có quyết định đó nên không tự accept ba High gaps.

## 21. Can Defer

BA-G07: advanced filters, mobile search placement, sticky CTA, cải thiện image ratio/description scroll sau khi giải quyết gaps chính. BA-G08: bản dịch pagination và nhãn featured rõ nghĩa. Typed specifications toàn ngành, CRM/quotation, optional image consultation, advanced stock, voucher/review/payment không bắt triển khai tại gate này. Không dùng từ ACCEPTED cho risk khách chưa chấp nhận.

## 22. Final Business Readiness

| Area | Requirement | Current Status | Gap | Release Blocker |
|---|---|---|---|---|
| Homepage | Khám phá đủ ngành/dịch vụ | PARTIAL | BA-G01/03/04 | Có do dịch vụ/catalog |
| Category | Hierarchy + ngành kinh doanh | PARTIAL | BA-G04 | Có do nội dung ngành thiếu |
| Search | Tìm tên/SKU tiếng Việt/no result | PASS | BA-G07 mobile visibility | Không |
| Product | Gallery/price/stock/detail | PASS code, FAIL content | BA-G04 TEST/giá mô tả | Có |
| Variant | 1–2 groups/combinations | FUNCTIONALLY PASS | BA-G02 quản trị | Có ở UX quản trị |
| Product Admin | Seller-friendly operation | PASS BUT HARD TO USE | BA-G02 | Có |
| Cart | Variant/quantity/summary | PASS | Không phát hiện gap core | Không |
| Checkout | Name/phone/address/note/total/order | PASS | Chính sách giao BA-G06 | Không ở core đã thử |
| Mobile | Core purchase không vỡ | PASS baseline | BA-G07 | Không |
| Cây nội thất | Có sản phẩm và định vị | PARTIAL | BA-G04/05 | Có ở publication quality |
| Phân bón | Catalog thực | MISSING CONTENT | BA-G04 | Có với scope hiện tại |
| Tưới | Catalog thực + phân loại | MISSING CONTENT; QA capability PASS | BA-G04 | Có với scope hiện tại |
| Cảnh quan | Set/giải pháp + nội dung | PARTIAL | BA-G01/04 | Có |
| Tư vấn cảnh quan | Service landing → consultation | MISSING | BA-G01 | Có |
| SEO | Technical foundation + business copy | PARTIAL | Brand/landing/content BA-G03/04/05/06 | Có do content core; kỹ thuật production vẫn gate riêng |

Kết luận dựa trên current source, UI, database và phép thử QA, không chỉ compilation/test percentage. Chỉ thực hiện review; không cutover, final import, DNS/docroot hoặc sửa CI3 production.

**PHASE 6 BUSINESS STATUS: NOT READY**
