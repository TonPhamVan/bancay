# Frontend Blade — Phase 3

Ngày kiểm chứng: 28/10/2026. Phạm vi local với dữ liệu tổng hợp. Đầu vào là Phase 2 **READY WITH RISKS**; các dependency findings của Exment v6/Laravel 10 vẫn chặn production. [Yêu cầu gốc](phase-3/request.md), [báo cáo](phase-3-report.md).

## Kiến trúc

Giữ một Laravel application/repository/database. Exment phục vụ admin; public frontend dùng Blade + Alpine 3.17.5 + Tailwind 4.3.3, build bằng Vite 8.3.5 / Laravel Vite plugin 3.2.0. Dependencies JavaScript khóa chính xác trong package-lock; Docker Node 24 chạy build, host không cần Node. Không có API gọi vòng lại Laravel, SPA hoặc CDN runtime.

| Lớp | Trách nhiệm |
| --- | --- |
| `routes/web.php` | Routes public, CSRF web middleware, throttle và session blocking |
| `Http/Controllers/Frontend` | Nhận request, gọi services, chuẩn bị view |
| `Http/Requests/Storefront` | Validate guest cart/checkout; cấm giá/totals/items từ client |
| `Services/Storefront/Publication` | Quyết định publication và cây category |
| `Catalog` | Query/eager load/pagination, settings whitelist, banners và navigation |
| `Presentation` / `Seo` | DTO card/picker, VND integer, ảnh an toàn và metadata |
| `Cart` / `Checkout` | Giỏ session, quote fingerprint, token và quyền xem đơn |
| `Actions/Commerce/CreateOrder` | Action dùng chung với admin: lock, giá server, reserve, snapshots và history |
| Blade / Alpine | Render và tương tác nhỏ; không truy vấn database trong view |

`StorefrontContext` chia sẻ layout DTO theo request. `Catalog` scoped và reset khi bắt đầu request để tránh dùng danh mục/settings cũ; chưa cache catalog qua nhiều request. Middleware public đặt `private, no-store`, `nosniff` và referrer policy. Handler public dùng HTTP semantics của Laravel để tránh Exment v6 biến lỗi thành HTTP 200. Admin adapters và native auth/permissions vẫn giữ nguyên.

## Publication và catalog

Product/Category/Article/ArticleCategory phải PUBLISHED và `published_at <= now()`; date NULL không public. Một ancestor bị ẩn/hẹn xuất bản sẽ ẩn cả nhánh; vòng lặp/missing ancestor fail closed. Chỉ ACTIVE variants xuất hiện trong picker và SKU search. CONTACT vẫn hiển thị nhưng không mua được. Stock hiển thị là physical trừ reserved, không phải physical stock đơn thuần.

Category listing gồm chính category và **mọi descendant đang visible**. Đây là **BUSINESS RULE CHANGE** so với legacy chỉ nối mức con trực tiếp. Product grid paginate24, article15; sort allowlist, fixed price ascending/descending dùng MIN giá hiện tại của ACTIVE FIXED variants, CONTACT xếp cuối. Search tên/SKU dùng binding và escape `%`, `_`, `\`.

Header có hierarchy parent/child, tìm kiếm, cart count và menu mobile. Logo/tên/điện thoại/email/địa chỉ/social/map lấy từ settings whitelist. Thiếu contact có trạng thái đang cập nhật, không dựng số điện thoại hoặc địa chỉ bán hàng. Hero lấy banner eligible đầu tiên theo priority/sort/id; chưa có carousel. Secondary banners hiển thị nếu có bản ghi hợp lệ.

## Frontend assets và nội dung

Layout `resources/views/layouts/store.blade.php`; component `resources/views/components/store/` gồm icon, breadcrumb, price, product/article card, alerts, input. Tailwind chỉ được load trong public layout/error pages, không thay CSS của Exment. Body font hệ thống; heading dùng Lora Latin/Vietnamese normal/italic self-hosted qua Vite, font-display:swap; không gọi font bên ngoài. Nguồn/giấy phép OFL-1.1 ở `resources/fonts/SOURCE.md`, `resources/fonts/OFL.txt` và public `/storefront/Lora-OFL.txt`. Alpine dùng cho menu, gallery, options, quantity và trạng thái gửi checkout. Đặt hàng cần JavaScript để chọn variant; noscript giải thích điều kiện này.

Rich HTML product/category/article được Purifier clean trước khi render. Metadata, settings, form input, SKU và snapshots dùng escaped Blade. Không render iframe map hoặc script từ settings. Ảnh chỉ nhận public storage path qua PublicImagePath và kiểm file tồn tại; fallback SVG nội bộ nếu thiếu, không xuất đường dẫn filesystem. Hero/product chính ưu tiên tải, thumbnails/cards lazy loading, có dimensions và object-fit.

Ảnh demo AI minh họa có [provenance](phase-3/image-provenance.md); không phải ảnh catalog legacy hay ảnh xác nhận hàng thật. `StorefrontDemoSeeder` chỉ local/testing; fixture repeatable, không có accounts, không import khách hàng. Bốn sản phẩm demo có 16 variants, trạng thái còn/hết hàng và CONTACT; ba bài viết, bốn category, một hero. Chỉ cập nhật tên/meta title cũ khi khớp đúng giá trị synthetic Phase 2; các settings thực tế khác giữ nguyên.

## Database và vận hành local

17 domain tables giữ cấu trúc. Thêm một technical table `checkout_attempts` theo [architecture change proposal](phase-3/architecture-change-proposal.md), không mirror dữ liệu vào Exment CustomTable. Migration additive và rollback/remigrate được đưa vào regression. [Cart/checkout contract](cart-checkout.md).

Với foundation đã cài:

```bash
docker compose exec -T app php artisan migrate
docker compose run --rm --no-deps assets
docker compose exec -T app php artisan storage:link
# Optional, local synthetic fixtures:
docker compose exec -T app php artisan db:seed --class=StorefrontDemoSeeder
```

Setup mới qua `scripts/setup-local.sh` đã gồm asset build; không tự seed demo. Public URL `http://localhost:8000/`, admin `/admin`. Không chạy raw exment:install lên instance đã initialize. Assets build output/node_modules không commit; source WebP và lockfile có version control.

## Giới hạn

Session/cache file phù hợp một local instance. Nếu scale nhiều instance cần shared session và distributed lock store được kiểm chứng, không chỉ đổi SESSION_DRIVER. Chưa có customer account, lookup qua OTP, payment, shipping API, voucher, review, full import, production publish. Pending checkout token hết hiệu lực sau30 phút; chưa có job dọn token/retention hoặc reservation expiry. Các mục này nằm backlog, không được coi là đã triển khai.

## Cross-phase risk review hiện tại

Core services/routes/views giữ nguyên; new cross-phase HTTP test tạo catalog bằng native admin rồi order từ guest và đọc/cancel bằng admin. Giá snapshot100000 không đổi sau catalog150000;physical10/reserved2 sau qty2, cancel→10/0. Shared image replacement không làm mất file của public product khác. DB thêm3 money CHECKs additive, không đổi17 domain table columns/FKs/indexes hoặc checkout_attempts.

Full110 PHP/656 assertions +5JS PASS; native/public real HTTP/CSRF smoke PASS. Fresh browser desktop/mobile **MANUAL TEST REQUIRED/BLOCKED** do provider lỗi trước navigation; Phase3 screenshots chỉ là historical. Composer audit FAIL, TokenGuard riêng false positive có proof; High email còn blocker. [Final review](risk-review-report.md), [register](risk-register.md). **PHASE 4 STATUS: NOT READY**.
