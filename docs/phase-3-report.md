# Phase 3 — Frontend Blade Development

> Current Phase4: [report](phase-4-report.md). Isolated rehearsal completed:113 products/118 SKUs/255 historical orders;126 PHP tests/770 assertions +5JS PASS; native/frontend browser PASS. RR-30/RR-34 temporarily accepted by user; audit still FAIL, production blocked. Phase5 READY WITH RISKS. Earlier NOT READY/import-not-started statements below are historical.

> Latest readiness closure: [report](readiness-closure-report.md). Browser Chrome/Edge/mobile PASS; backup restore VERIFIED;114 PHP/670 assertions +5JS PASS. RR-30/RR-34 remain BLOCKED; Phase4 NOT READY. Post-run local metadata differences recorded for review. Prior browser blockers below are historical.

> **Current review 28/10/2026:** [Risk review](risk-review-report.md) và [41-risk register](risk-register.md) là kết luận hiện tại.110 PHP tests/656 assertions +5JS PASS; bổ sung3 money CHECKs và reference-aware image cleanup. TokenGuard advisory FALSE POSITIVE cho exact checked code; Composer audit vẫn FAIL với High email finding chưa remediation. Fresh browser run BLOCKED. **PHASE 4 STATUS: NOT READY**. Các counts/readiness/evidence của phase riêng bên dưới giữ làm lịch sử, không phải approval hiện tại.

Ngày kiểm chứng: **28/10/2026**. Nhánh làm việc: `feature/develop`, baseline Phase2 `e1cdb4b`. Phạm vi **local test với dữ liệu tổng hợp**. [Yêu cầu nguyên bản](phase-3/request.md); [Master Plan nguyên byte](master-plan.md).

## 1. Executive Summary

Đã xây public storefront bằng Blade/Alpine/Tailwind trong cùng Laravel/Exment app: home, category, search, product/gallery/options, guest cart, checkout, session-owned order success, articles/category và contact. Core flow đã chạy trên browser desktop và mobile, tạo hai đơn tổng hợp đúng snapshot/stock reservation. **96 PHP tests /556 assertions PASS**,5 Node tests PASS; admin Phase1/2 regression PASS.

Phase3 hoàn tất trong local test scope; không có functional blocker đã biết cho core guest checkout. **Production BLOCKED:** dependency audit FAIL và stack cũ; High email chưa được remediation; Critical TokenGuard đã được review lại là false positive cho exact code. Không import legacy toàn bộ, không staging deployment, không đổi DNS/hosting/production. Readiness Phase4 được review mới thay thế bằng NOT READY; xem mục26 và risk register.

## 2. Phase 2 Inputs Used

Đã dùng [Phase0 report](phase-0.md), [Phase1 report](phase-1-report.md), [Phase2 report](phase-2-report.md), [ERD/schema](database-design.md), [business rules](phase-0/business-rules.csv), [product design](product-management.md), [order workflow](order-workflow.md), [SEO inventory](phase-0/seo.md) và Phase2 readiness ở mục23 của báo cáo. Đầu vào là **READY WITH RISKS (LOCAL DEVELOPMENT ONLY)**, không phải production-ready.

Tái sử dụng native relational catalog/content/settings, ACTIVE/PUBLISHED enums, effective price, InventoryService, CreateOrder, immutable items, append-only history và Exment identity/permissions. Lock Exment6.2.15/Laravel10.50.4 giữ theo chỉ định dùng v6 tạm thời; không tự nâng v7/dev branch. Master Plan và artifacts Phase0/1/2 không chỉnh sửa.

## 3. Frontend Architecture

Route → Frontend Controller → Storefront services / existing Actions → Eloquent/domain → MariaDB → Blade. Không query DB từ Blade, không gọi API nội bộ để render Blade. [Kiến trúc chi tiết](frontend-architecture.md), [proposal](phase-3/architecture-change-proposal.md).

Thêm `app/Services/Storefront`, `Http/Controllers/Frontend`, requests/middleware, Blade layouts/components/pages, CSS/JS và asset tooling. Điều chỉnh CreateOrder bằng optional storefront flag mặc địnhfalse để bổ sung category ancestry validation mà giữ callers cũ. Handler public giữ status code Laravel; scoped Catalog tránh dữ liệu cũ qua request.17 domain tables so cấu trúc với Phase1 không đổi; thêm **một technical table checkout_attempts**, đã migrate local và test rollback/remigrate. Không mirror Exment JSON.

## 4. Routes

[Route contracts](frontend-routes.md) và [route collection thực tế](phase-3/evidence/routes.json) có20 routes gồm health. Public entity URL theo slug unique. POST/PATCH/DELETE dùng CSRF web middleware; cart/checkout mutations và các GET liên quan dùng session blocking. Cart add/update throttle60/minute, checkout POST10/minute. Missing/hidden404, validation422 JSON hoặc redirect error bag, CSRF419, invalid method405, throttle429. Không thêm bare-ID order lookup.

## 5. Layout / Components

Header/logo/search/navigation/category hierarchy/cart, mobile menu, footer và skip link dùng chung. Logo/tên/phone/email/address/map/social từ settings; thiếu dữ liệu thì hiển thị đang cập nhật. Reusable product/article cards, price, breadcrumbs, alerts, input và icon. Thiết kế riêng tông kem/xanh lá/đất nung, Lora hỗ trợ tiếng Việt self-hosted + system body font; không sử dụng asset/HTML/CSS thương hiệu Shopee. Tailwind bundle không được load vào Exment admin.

## 6. Homepage

Hero từ banner eligible ưu tiên cao nhất; status/time window/position/category scope được lọc. Có featured categories, featured/latest products, phần editorial và latest articles. Secondary banners chỉ xuất hiện khi có dữ liệu hợp lệ. Không có carousel/popup marketing ở phase này. Empty catalog/banner có fallback layout/nội dung rõ ràng. [Desktop](phase-3/evidence/home-desktop.jpg), [mobile](phase-3/evidence/home-mobile.jpg).

## 7. Category

Hiện name, sanitized description, children links, grid24/page, sort, breadcrumbs. PUBLISHED + published_at hợp lệ cho toàn bộ ancestor chain; ẩn một parent sẽ ẩn descendants và sản phẩm dưới nhánh. **BUSINESS RULE CHANGE:** listing gồm mọi visible descendant thay vì chỉ mức con trực tiếp. Đã kiểm parent category hiển thị3 sản phẩm trong2 child categories. [Desktop](phase-3/evidence/category-desktop.jpg), [tablet](phase-3/evidence/category-tablet.jpg).

## 8. Search

Tìm tên product và ACTIVE SKU; q string<=120, LIKE binding, escape wildcard, sort allowlist và pagination. Invalid/oversized input không chạy raw SQL. Empty result có hướng dẫn tìm lại, keyword được escape. UI desktop SKU `SF3-1-0-0` trả1 kết quả; mobile “Monstera” trả2. Test injection, wildcard `%`, script input và inactive SKU đều pass. [Bằng chứng](phase-3/evidence/search-desktop.jpg).

## 9. Product Detail

Gallery chính/thumbnails, name, SKU, regular/sale/discount, stock, options, quantity, Add/Buy Now, short/full description, related products và SEO. Gallery đổi theo thumbnail hoặc variant image; fallback an toàn nếu thiếu file. Only published/past product trong visible category chain có public detail; inactive variants không xuất vào payload. CONTACT hiển thị tư vấn, không phát offer giá0 và không checkout. [Desktop](phase-3/evidence/product-desktop.jpg), [mobile](phase-3/evidence/product-mobile.jpg).

## 10. Variant Selection

Alpine map option IDs → exact variant ID/SKU/price/stock/image, IDs serialize dạng string để không mất BIGINT precision. Value không có variant ACTIVE bị disable; combination thiếu/không tồn tại có thông báo và nút mua disabled. Chuyển giữa các nhóm disconnected không bị mắc kẹt: reset lựa chọn mâu thuẫn và yêu cầu chọn lại thuộc tính còn thiếu. Hết hàng/CONTACT không mua. Server kiểm lại tất cả, không dựa browser để authorise variant/price. Node tests kiểm incomplete/missing/disconnected combinations, stock/contact, quantity bounds và identifiers >2^53.

## 11. Cart

Session chỉ giữ product_id/variant_id/quantity; giá và tồn kho reload mỗi lần. Add merge, update/remove/clear hoạt động; max50 variant lines, qty positive<=1,000,000 và đủ available. Hiện ảnh/name/options/SKU/unit price/qty/subtotal; invalid lines chặn checkout. Buy Now dùng cùng cart, giữ line đã có và chuyển checkout. [Contract](cart-checkout.md), [desktop](phase-3/evidence/cart-desktop.jpg), [mobile](phase-3/evidence/cart-mobile.jpg).

## 12. Checkout

Guest form tên/phone/email optional/address/note; có summary items/qty/server price/subtotal/shipping hiện tại/total. Form Request kế thừa rules của CreateOrder, address key allowlist; cấm client items/totals/customer_id/status. VND integer/overflow guard. Phí vận chuyển baseline0, UI nói rõ chưa có calculator và cửa hàng trao đổi khi xác nhận; không dựng payment success. Price/qty changes sau GET phải review lại quote. [Desktop](phase-3/evidence/checkout-desktop.jpg), [mobile](phase-3/evidence/checkout-mobile.jpg).

## 13. Order Creation

Transaction lock attempt → lock variants tăng dần → validate current quote/cart → **CreateOrder** → reserve → order/item snapshots/NEW/history → link attempt → commit. Token256bit, SHA-256 stored token/owner/quote, TTL30min. Cùng token replay trả cùng order; success grant chỉ sau commit, tối đa20 mã trong session. Replay cũ không clear giỏ mới. Fail sau reservation rollback mọi business write; test2 fork cùng token chứng minh chỉ một order/reservation. Full stock purchase clear cart được kiểm riêng.

Hai đơn thật trên **local synthetic DB**: desktop3 ×259.000=777.000₫ và mobile2 ×220.000=440.000₫, NEW, snapshot đúng, chưa giảm physical. [Đối chiếu DB không xuất contact](phase-3/evidence/synthetic-ui-orders.json). Admin authorized đọc hai đơn qua native pages200; fresh guest và admin session không có public grant đều404 tại success URL. [Success desktop](phase-3/evidence/success-desktop.jpg), [mobile](phase-3/evidence/success-mobile.jpg).

## 14. Articles

List/category/detail, publication schedule + category ancestors, featured image/date/content và related.15/page, category menu từ database. Purifier clean HTML trước raw rendering; script/event attributes không đi vào HTML output. Meta fields ưu tiên entity rồi fallback settings. Contact là settings-only với safe map/social links; chưa có contact form hoặc SMTP gửi thật. [Article desktop](phase-3/evidence/article-desktop.jpg), [mobile](phase-3/evidence/article-mobile.jpg).

## 15. SEO

Title/description/canonical/OG, BreadcrumbList/Product/AggregateOffer/Article/Organization cơ bản, không fake rating/review. Canonical origin tin APP_URL; JSON-LD dùng HEX escaping, tiền decimal string chính xác. Local/staging public noindex,follow mặc định; cart/checkout/success/search noindex,nofollow. Generic old sp/dm/bv/cm routes resolve legacy_id301 tới current published slug, missing/hidden404; legacy listing dịch keyword và page0-based. Không hardcode hàng nghìn redirect hoặc redirect missing về home. [SEO rules và phần còn thiếu](seo-frontend.md).

## 16. Responsive

Đã chạy desktop1440×900, tablet768×1024, mobile390×844, kiểm narrow320×740 và checkout360×800. Full core flow chạy desktop + mobile. Đã sửa header tràn5px ở320px, tăng hit area menu/cart và block title-link để dễ bấm khi title xuống dòng. Sau sửa narrow home và tablet/360 checkout không tràn ngang (scrollWidth=clientWidth). Cards responsive2/3/4 cột, product/checkout chuyển1cột trên mobile. Ảnh/product text/orders không đẩy layout ngang ở các trang đã đo. [320px](phase-3/evidence/home-320.jpg), [tablet checkout](phase-3/evidence/checkout-tablet.jpg).

## 17. Security

CSRF thực tế419, binding/validation, escaped metadata/settings/contact/snapshots, sanitized rich HTML, reject price/ownership tampering, stock recheck và session-scoped success. Public image path validated+file-exists; chỉ xuất storage URL/fallback. Safe HTTP/HTTPS outbound links, no arbitrary next redirect. DebugOFF; custom404/500 không xuất internal path/SQL/credential. HTTP private,no-store tránh lưu cache cart/PII. Không tạo thêm privileged UI accounts; credential local-admin giữ nguyên/private.

**Composer audit vẫn FAIL:**6 advisory entries /2 packages (`firebase/php-jwt`:1, `laravel/framework`:5), version metadata có Critical/High; current exact-code applicability xem [review](risk-review/dependency-assessment.md). laravelcollective/html abandoned. npm audit0 vulnerabilities. Không tắt policy/thêm ignores hoặc coi risk exception là fix. [Audit Composer](phase-3/evidence/composer-audit.json), [npm](phase-3/evidence/npm-audit.json). Production BLOCKED cho tới upgrade/audit lại và các release gates. Không có penetration test/CSP audit/WCAG certification ở Phase3.

## 18. Performance

Eager load catalog images/category/ACTIVE variants/inventory và cart product/images/options/inventory; bounded pagination. Test HTTP kernel + Blade trên MariaDB thật: **1 product9 queries,21 products9 queries;21 cart lines8 queries**. [Measurement](phase-3/evidence/performance.json). Không cache dynamic catalog/order qua request; image URL memoization theo instance. CSS khoảng38.3kB raw/~7.8kB gzip, JS55.4kB/~19.5kB gzip; Lora WOFF2 Latin/Vietnamese normal/italic tổng~96.9kB, có OFL license và không gọi Google khi runtime. WebP hero~341kB, product~98–122kB, lazy card/gallery thumbs, hero priority. Đây là small synthetic/query evidence, không phải Lighthouse/load test hoặc latency SLA production.

## 19. Automated Tests

| Kiểm tra | Kết quả |
| --- | --- |
| PHP full Phase1/2/3 suite | **96 tests /556 assertions PASS**,0 failures/errors/skips,~29 giây |
| Node picker tests | **5 PASS**,0 failure/skip |
| Pint | **140 PHP files PASS** |
| Blade compile / Vite production build / npm ci | PASS |
| Composer strict validate / platform / Exment requirements | PASS |
| npm audit | PASS,0 vulnerabilities |
| Composer audit | **KNOWN FAIL, exit1**, production blocked |
| Domain schema comparison |17 tables unchanged;1 additive technical table |
| Actual HTTP smoke |21 checks PASS including auth/admin/frontend/CSRF/IDOR |

[JUnit summary/cases](phase-3/evidence/tests.json), [Node](phase-3/evidence/js-tests.json), [verification](phase-3/evidence/verification.json). Tests bị guard chỉ bancay_test, có full migrate/rollback/remigrate và real process contention. Phase1 root GET contract đổi404→200 có chủ đích vì storefront đã được xây; assertions nghiệp vụ Phase1/2 giữ và pass. Test client được cấu hình carry session cookie trong checkout tests, không nới owner guard để vượt test.

## 20. Manual UI Tests

Thao tác browser thật: home→category→product→variant/stock/contact/image/quantity→add→cart/update→checkout→success trên desktop; lặp full flow trên mobile. Thêm search tên/SKU, articles/category/detail/contact, Buy Now, Clear Cart/empty state, tablet/narrow layout. Hai đơn chỉ là synthetic, giữ để review trong native admin; không gửi mail/payment/shipping thật, không fulfillment. [Flow records](phase-3/evidence/ui-tests.json). Browser console không ghi nhận error/warning trong run này.

## 21. Cross-Browser Tests

| Môi trường | Trạng thái |
| --- | --- |
| ChatGPT IAB desktop | PASS core flow1440×900 |
| IAB responsive viewport | PASS core flow390×844; tablet/narrow spot checks |
| Chrome desktop riêng | **NOT TESTED** — không có Chrome browser connector kết nối trong session |
| Edge desktop riêng | **NOT TESTED** — không có Edge browser connector kết nối trong session |
| Mobile Chrome/Android device | **NOT TESTED** — viewport emulation không thay cho browser/device thật |
| Safari/iOS | **NOT TESTED** — không có môi trường/device Safari/iOS |

Không suy ra cross-browser pass từ IAB responsive. [Matrix và console evidence](phase-3/evidence/ui-tests.json). Cần bổ sung matrix trước release.

## 22. Evidence

Nguồn kiểm chứng dưới [phase-3/evidence](phase-3/evidence/verification.json): screenshots, UI records, HTTP status checks, DB synthetic orders, schema/runtime/routes, PHP/JS test summary, audits, query counts và source checksums. Raw logs/JUnit/credentials giữ ngoài Git trong legacy-private hoặc ignored storage/logs. Không xuất response bodies chứa dữ liệu khách; order metadata chỉ chọn hai fixture names rõ ràng. [Image provenance](phase-3/image-provenance.md).

`scripts/phase3/collect_evidence.py` chạy checks theo thứ tự, guard test DB và read-only local business data collection. `--reuse-checks` chỉ refresh metadata khi tested source hashes không đổi (collector script được phép sửa); không thay kết quả audit FAIL. [Artifact verifier](phase-3/evidence/artifact-verification.json) kiểm document links/hash/privacy/static view guard; không thay thế runtime tests. Historical evidence không bị collector mới ghi đè.

## 23. Legacy Comparison

[Bảng22 chức năng](phase-3/legacy-comparison.csv) gồm legacy/new/status/behavior changed/reason. Preserve single-store, categories, pricing/contact intent, images, articles, banner/SEO purpose. Các **BUSINESS RULE CHANGE**: all descendants, server-authoritative cart/quote, featured/newest thay popularity chưa có metric, one-based pagination, database variant identity, workflow/reservations và order privacy. Old URL/media migration là foundation, không tuyên bố201 URL inventory đã import/HTTP verified.

## 24. Risks

Current dependency findings xem RR-29–34: TokenGuard false positive có test/hash proof; High email chưa remediation và maintenance stack vẫn là blocker. Không gọi mọi advisory là exploit đã chứng minh. Staging/cross-browser/device/penetration/load tests chưa có. Shipping/payment/customer account/verification chưa implement theo scope. Pending token retention và reservation expiry chưa tự động; admin phải quản lý NEW orders/stock. File sessions/locks chỉ phù hợp single-instance đã test. Synthetic images/content không xác nhận sản phẩm bán thật. Hosting/DirectAdmin/cron/DNS/SSL backup bổ sung vẫn **DEFERRED BY USER — “backup sau”**; không hỏi lại hoặc tự lấy backup hosting.

## 25. Technical Debt

Nâng Exment7/Laravel12 theo chỉ định riêng và kiểm compatibility/audit/auth/migrations/services lại; fine-grained admin abilities; shared lock/session trước scaling; expiry/retention/stock ledger; authenticated order recovery; real shipping policy; richer editor/content accessibility; image srcset/CDN/cache invalidation; full browser/assistive-tech/CSP/performance audit. Import tooling, quarantine/sanitization, slug/media mapping và SEO validation thuộc Phase4/5. Không đưa wishlist/voucher/reviews/payments/marketplace vào Phase3.

## 26. Phase 4 Readiness — superseded by consolidation review

Phase3 từng ghi READY WITH RISKS cho isolated local rehearsal; đó là quyết định lịch sử, không còn current authorization. Review mới theo yêu cầu chặt hơn đã sửa confirmed money/media defects và kiểm110 tests/656 assertions +5JS PASS. [Full review](risk-review-report.md), [register](risk-register.md).

**Blockers hiện tại:** RR-30/RR-34 compatible supported remediation cho High email dependency advisory; RR-28 fresh desktop/mobile core browser verification (provider lỗi trước navigation). TokenGuard riêng exact Laravel10.50.4 đã xác minh false positive; không dùng điều đó để đánh dấu Composer audit PASS. Production vẫn BLOCKED.

Legacy mapping đã chốt safe defaults/quarantine; actual importer, dry-run/count/idempotency/rollback và201 old URL verification là scope Phase4/5 sau khi được chỉ định, chưa chạy trong review. Hosting backup vẫn hoãn theo người dùng.

PHASE 4 STATUS: NOT READY
