# Phase 1 — Foundation & Database Design

> **Current review 28/10/2026:** [Risk review](risk-review-report.md) và [41-risk register](risk-register.md) là kết luận hiện tại.110 PHP tests/656 assertions +5JS PASS; bổ sung3 money CHECKs và reference-aware image cleanup. TokenGuard advisory FALSE POSITIVE cho exact checked code; Composer audit vẫn FAIL với High email finding chưa remediation. Fresh browser run BLOCKED. **PHASE 4 STATUS: NOT READY**. Các counts/readiness/evidence của phase riêng bên dưới giữ làm lịch sử, không phải approval hiện tại.

Ngày: 27/10/2026, Asia/Saigon. Phạm vi theo [request](phase-1/request.md); Master Plan và evidence Phase 0 giữ nguyên. **Tạm dùng Exment v6 theo chỉ định người dùng; v7/Laravel 12 là công việc cập nhật riêng khi có release.**

## 1. Executive Summary

Đã xây một Laravel application mới với Exment 6.2.15, Laravel 10.50.4, PHP 8.3.36 và MariaDB 10.11 local. Có 17 bảng nghiệp vụ, models/relationships, validation/actions/services, ERD, Exment mapping và legacy mapping. Không xây storefront, cart/checkout UI, payment/shipping integration hoặc chạy import dữ liệu thật.

Migrations đã kiểm tra từ DB trống, full rollback và migrate lại. Exment local login/dashboard hoạt động. **Dependency audit vẫn FAIL**, version metadata gồm critical/high; applicability hiện tại xem risk review; exception chỉ dành cho nền v6 local được yêu cầu. Chưa đủ điều kiện staging/public deployment.

## 2. Phase 0 Inputs Used

Đã dùng architecture,39 functional inventory entries,32 business rules,12-table DB inventory, normalized-variant analysis, order/user/role findings, mapping/scope/security/SEO/readiness. Giữ các evidence115 products/8 variants/748orders, SKU/alias collisions, zero-price contact và legacy status policy. Không tự coi old status1 là completed; không copy MD5 hoặc unsafe handlers. Hosting backup vẫn hoãn theo người dùng.

## 3. Architecture

[Architecture/ADRs](architecture.md): modular monolith; một app/repo/deployment/operational DB; Exment admin; future Blade/API cùng gọi Actions. Native Eloquent tables là nguồn dữ liệu, không sync JSON copies vào CustomTable. Hai containers local không phải microservices; bancay_test chỉ phục vụ test.

## 4. Folder Structure

app/Domain, Actions/Catalog/Commerce/System, Services, Models, Http/Requests, Console/Commands và Exment/bootstrap; migrations/factories/seeders, tests, docker, docs. Không tạo hàng chục interfaces/repositories hoặc controllers API chưa có client. Chi tiết tree trong architecture.md.

## 5. Domain Design

Catalog: category/product/images/options/values/variants/inventory. Commerce: order/item/history. Customer: profile/address chưa login. Content: article/category/banner. System: public Setting; identity/roles dùng built-in Exment. Cart/voucher/review/payment/shipping nâng cao chưa implement.

## 6. ERD

[ERD Mermaid](database-design.md) phản ánh FKs và actual ownership keys; [schema metadata](phase-1/evidence/schema.json) xuất từ DB mới. changed_by tham chiếu LoginUser.id kiểu INT, không tạo duplicate users table.

## 7. Database Tables

17 tables: categories, products, product_images, product_options, product_option_values, product_variants, product_variant_values, inventories, customers, customer_addresses, orders, order_items, order_status_histories, article_categories, articles, banners, settings. Vendor/framework tables được giữ tách vai trò platform trong cùng DB.

## 8. Important Fields

Product không giữ price/stock chính. Variant có unique SKU, pricing type, integer money và combination fingerprint. Order lưu contact/address/totals và item snapshots; legacy references nullable. Entities SEO có slug/meta/status/published_at, canonical derive ở route phase sau. [Field design](database-design.md).

## 9. Foreign Keys

RESTRICT cho references/stock/history cần bảo vệ; CASCADE cho owned image/option/pivot/address khi cleanup hợp lệ; SET NULL cho snapshot catalog/customer/admin actor links. Product archive dùng soft delete. Banner category RESTRICT không làm banner tự thành global. Composite FKs chặn cross-product image/value links.

## 10. Index Strategy

Unique slug theo entity, SKU global, combination theo product, order number, inventory variant. Browse/category/status/published indexes; customer email/phone và order contact phone; history theo order/time. Generated nullable unique keys cho một primary image/default address. Không index mọi field. [Chi tiết](database-design.md).

## 11. Laravel Models

Có17 domain Eloquent models, explicit fillable và casts cho enums/integers/dates/JSON snapshots. Product dùng SoftDeletes; OrderItem có guard immutable snapshot; OrderStatusHistory append-only. Không có App User hoặc login customer mới.

## 12. Relationships

Đã implement parent/children, Product→images/options/variants/category, Variant→values/image/inventory, Customer→orders/addresses, Order→items/history/customer, History→native LoginUser, Article→category và Banner→optional category. Seed/test xác minh các quan hệ chính trên MariaDB.

## 13. Services / Actions

CreateProduct/CreateVariant, CreateOrder/ChangeOrderStatus, SetPublicSetting, InventoryService, CategoryTreeService. Form Requests cho product/order/status. Services không phụ thuộc Blade/HTTP globals. Writes dùng validation, Eloquent/bound queries và transactions; static SQL chỉ cho DDL constraints, không user input.

## 14. Product Variant Design

Normalized option/value/pivot, một value mỗi option/variant và unique combination key. Giá integer VND, CONTACT không có giá để đặt miễn phí; FIXED positive, sale nullable<=price. Inventory luôn được tạo trong CreateVariant transaction. Demo 2 options/4 variants; tests chống SKU trùng, ownership sai, selection không đủ và pricing invalid.

## 15. Inventory Design

Một stock row/SKU; available=physical-reserved. Order mới reserve dưới lock; cancellation release; shipping trừ physical và reserved; returned không auto restock. Adjustment không giảm dưới reserved. Đây là use case foundation, chưa warehouse/ledger/expiry jobs; real two-process concurrency đã được kiểm ở Phase2/3 và review, sustained load vẫn NOT TESTED.

## 16. Order Design

Guest được hỗ trợ; giá/tên/SKU/option text tính từ server và lưu snapshot. Không nhận client unit price, totals, fees, status hoặc arbitrary customer_id. Trusted Customer association là tham số server-side. Histories lưu transition/actor/note; trạng thái terminal không reopen. Legacy history có policy riêng, không gọi live creation/stock effects.

Không có cart/checkout HTTP UI hoặc gateway/shipping API; discount/shipping fee hiện0 trong use case mẫu. Future extensions phải giữ tính đúng của totals và reservations.

## 17. Exment Mapping

[Exment mapping](exment-mapping.md) đủ entity/table/custom-vs-built-in/CRUD/search/filter/workflow/import-export/notes. Chọn authenticated page extensions cho native tables, không fake CustomTable mapping. Order/Inventory writes phải qua actions; built-in platform auth/roles tái dùng. CRUD nghiệp vụ là scope Phase 2, chưa triển khai ở đây.

## 18. Legacy → New Mapping

[Legacy mapping](legacy-mapping.md): new IDs + legacy_id; normalized parallel arrays; deterministic SKU fallback giữ original code; exact old URLs; price/contact policy; orphan/quarantine; historical snapshot và admin reset strategy. Chưa có importer command; không chạy production migration. Phase 4 tooling sau này phải dry-run không writes và có error/count logging an toàn.

## 19. Tests Executed

- Full native/vendor migration từ DB trống, full rollback, remigrate trên dedicated bancay_test.
- SKU uniqueness ở validation và DB; composite ownership FKs; inventory; pricing; default image/address constraints; category trees.
- Order snapshot sau đổi catalog; guest/IDOR/tampering validation; atomic reservation rollback; status/history/cancel/ship lifecycle; immutable snapshots; catalog deletion giữ history.
- Hash/Gates; safe path/HTML/email newline checks; synthetic factories/seeder; platform requirements/Pint/Composer validation.
- HTTP local health, Exment login và authenticated dashboard; không request production.

## 20. Test Results

Kết quả machine-readable: [tests.json](phase-1/evidence/tests.json), [HTTP smoke](phase-1/evidence/http-smoke.json), [platform](phase-1/evidence/platform.json), [Exment requirements](phase-1/evidence/exment-requirements.txt), [final checks](phase-1/evidence/verification.json).

**41 automated tests / 110 assertions PASS**, không skipped; migration lifecycle PASS. Pint PASS trên93 PHP files; Composer strict manifest/lock validation và platform requirements PASS. Exment requirement checker xác nhận cả7 mục memory/POST/upload/timeout/permissions/Composer/backup client đều OK sau cấu hình runtime. HTTP local chứng minh guest bị chuyển về login, thiếu CSRF trả419, valid login vào dashboard và session HttpOnly/SameSite Lax. **Composer audit FAIL** được giữ nguyên trong [audit evidence](phase-1/evidence/composer-audit.json), không trộn với domain test PASS.

## 21. Security Baseline

Không MD5 mới, không raw globals/user-input SQL; fillable/validation/authorization; secrets sinh riêng ngoài Git; CSRF/session standard; HTML sanitizer và image-path foundation; no duplicate auth. API business routes chưa mở; admin bind localhost, debug OFF, SMTP log-only. App email rules reject CR/LF; đây không phải patch loại bỏ các framework advisories.

**Historical Phase1 NOT TESTED:** upload/browser UX sau đó được Phase2/3 kiểm; hiện upload regression PASS, fresh review browser BLOCKED. Penetration test, production security headers/TLS/proxy/SMTP và sustained concurrent load vẫn NOT TESTED. Production và legacy data không bị sửa.

## 22. Risks

Exment6/Laravel10 có6 advisory entries/2 packages; review hiện tại xác minh TokenGuard false positive, High email còn mở; deployment BLOCKED. Người dùng đã chỉ định stack này tạm thời trước v7. Cần xác minh release/upgrade/audit khi cập nhật; không hứa v7 tự động giải quyết mọi dependency issue.

Legacy exceptions và backup hosting còn mở như Phase 0. New schema nghiêm ngặt nên không nhận mọi historical row lỗi; phải quarantine/đối soát ở Phase 4. Các giới hạn Phase1 về role bridge/upload/checkout đã được Phase2/3 xử lý ở core scope; debt chi tiết hiện tại xem risk register.

## 23. Technical Debt

Nợ tạm có chủ đích: v6/Laravel 10 compatibility và abandoned transitive package; constraints/local setup gắn MariaDB; auth/history actor có coupling nhỏ với Exment. Không fork vendor để che advisory. Reservation expiration, returned-goods inspection/restock, shipping pricing, customer accounts và real import tooling thuộc phase sau. Config Exment overrides giữ nhỏ để giảm upgrade conflicts.

Files tạo/sửa: Laravel skeleton/config/composer lock, app domain/actions/models/requests/commands, one native domain migration + vendor base migration, factories/seeders/tests, Docker/setup scripts, architecture/database/mapping/report docs, README/AGENTS/gitignore. Phase 0 backup/evidence và Master Plan không bị sửa; đây là file inventory lịch sử khi kết thúc Phase1; phase commits đã tồn tại, review changes được đóng gói trong commit riêng.

## 24. Phase 2 Readiness

**PHASE 2 STATUS: READY WITH RISKS** cho xây admin nghiệp vụ **local**: schema/domain/API nội bộ/mapping đã có và các test trọng yếu PASS. Không bắt đầu Phase 2 trong task này.

Risk/gates cụ thể: dependency audit chưa sạch; chỉ localhost và synthetic data đến khi stack upgrade được kiểm tra. Trước public staging/production phải xử lý advisories, review permissions/Exment extensions, hoàn thiện backup/runtime plan và run lại toàn bộ checks. Khi v7 chính thức có release và được yêu cầu, ưu tiên update stack rồi mới mở rộng UI.

PHASE 2 STATUS: **READY WITH RISKS** — không phải approval cho deploy production.
