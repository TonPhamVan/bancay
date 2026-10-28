> Current stack: **Exment7.0.0 / Laravel12.70.2 / admin-core1.0.1**, PHP8.3.36. [Upgrade report](exment-v7-report.md). Composer audit PASS (0 advisories/0 abandoned), npm audit0; RR-30/RR-34 RESOLVED.132 PHP/814 assertions +5JS and47 browser cases PASS. Both local DBs upgraded;17 business tables unchanged; migrated catalog still hidden. Older v6 pins/audit failures/exceptions below are historical. No production release.

# Kiến trúc — Phases 1–3, reviewed before Phase4

> Current Phase4: [report](phase-4-report.md). Isolated rehearsal completed:113 products/118 SKUs/255 historical orders;126 PHP tests/770 assertions +5JS PASS; native/frontend browser PASS. RR-30/RR-34 temporarily accepted by user; audit still FAIL, production blocked. Phase5 READY WITH RISKS. Earlier NOT READY/import-not-started statements below are historical.

> **Current review 28/10/2026:** [Risk review](risk-review-report.md) và [41-risk register](risk-register.md) là kết luận hiện tại.110 PHP tests/656 assertions +5JS PASS; bổ sung3 money CHECKs và reference-aware image cleanup. TokenGuard advisory FALSE POSITIVE cho exact checked code; Composer audit vẫn FAIL với High email finding chưa remediation. Fresh browser run BLOCKED. **PHASE 4 STATUS: NOT READY**. Các counts/readiness/evidence của phase riêng bên dưới giữ làm lịch sử, không phải approval hiện tại.

Ngày quyết định: 27/10/2026 (Asia/Saigon). Đầu vào: [Master Plan](master-plan.md), [Phase 0](phase-0.md), [yêu cầu Phase 1](phase-1/request.md). Phase 0 cho phép tiếp tục ở trạng thái READY WITH RISKS; không thay đổi hoặc nhập dữ liệu legacy vào ứng dụng mới.

## Quyết định stack và rủi ro đã được ghi nhận

Tạm dùng **Exment 6.2.15 + Laravel 10.50.4 + PHP 8.3.36**, theo chỉ định người dùng dùng v6 rồi cập nhật v7/Laravel 12 khi có release và yêu cầu riêng. Constraint của [Exment v6.2.15](https://github.com/exceedone/exment/blob/v6.2.15/composer.json) yêu cầu PHP ^8.2 và Laravel ^10.34. Composer lock cố định toàn bộ bản cài thực tế; không dùng nhánh dev của v7.

Laravel 10 đã hết security support từ 04/02/2025 theo [Laravel support policy](https://laravel.com/docs/10.x/releases). Composer strict resolution bị chặn; đã dùng ngoại lệ `--no-security-blocking` trong bước resolve/khóa dependency local cho stack v6 được yêu cầu. Không thêm audit ignore hoặc tắt policy vào composer.json. Constraint Exment `~6.2.15` chỉ cho patch của dòng6.2; composer.lock cố định6.2.15. [Audit đã lưu](phase-1/evidence/composer-audit.json) vẫn FAIL với 6 entries trên 2 packages, version metadata gồm Critical/High và một CRLF advisory trùng IDs. Review exact code xác minh TokenGuard false positive; High email chưa remediation. [Per-advisory evidence](risk-review/dependency-assessment.md). Không coi local test PASS là security audit PASS.

**Chỉ cho phát triển local, bind 127.0.0.1. Production deployment BLOCKED** đến khi xử lý các advisories và xác minh stack được hỗ trợ. Debug OFF, session auth, không public API nghiệp vụ, không SMTP thật; những lựa chọn này thu hẹp bề mặt nhưng không xóa advisory upstream. Không patch vendor hoặc tự đổi framework ngoài yêu cầu v6.

MariaDB 10.11 cho local foundation; DB cũ 10.3 chỉ là nguồn export. Đây không phải nâng DB production. PHP image có pdo_mysql, GD, mbstring, intl, zip, bcmath, XML và MariaDB client; php.ini đặt memory 512M, max_input_vars 10000, upload16M/post20M. [Dockerfile](../docker/php/Dockerfile), [local.ini](../docker/php/local.ini).

## Một ứng dụng, một nguồn dữ liệu nghiệp vụ

Một Git repo / Laravel app / deployment unit / operational database. Compose có app và database để phát triển; `bancay_test` là database test phá hủy được, không phải backend hay operational datastore thứ hai. Không có legacy connection trong config; không có frontend repo, SPA hoặc microservice.

```text
HTTP / Exment authenticated extension (Phase 2) / future Blade or API controller
  → Form Request + authorization
  → App\Actions / App\Services
  → domain enums / integer Money / Eloquent models
  → one relational database
```

Actions không đọc HTTP globals, session hoặc Blade. Form Requests chịu authorization; Actions tự validate lại để console/internal callers không bỏ qua invariants. Gate mặc định deny; hiện chỉ Exment system administrators được quyền catalog/commerce/content/customer/settings manage. Phase 2 sẽ gắn các abilities này với role/plugin permissions cụ thể; không tạo bảng role/user duplicate.

Exment quản lý platform identity qua LoginUser và system user/role metadata của nó. Native catalog/commerce tables là nguồn chuẩn. Vì CustomTable lưu dynamic values/JSON và có conventions riêng, **không giả mạo native tables thành CustomTable và không sync bản sao JSON**. [Exment mapping](exment-mapping.md) dùng native Eloquent-backed extension screens trong cùng admin; write actions giữ invariants. Đây là cách triển khai trong kiến trúc Master Plan, không đổi sang backend khác.

## Cấu trúc thực tế

```text
app/
  Domain/Catalog/        PublicationStatus, RecordStatus, PricingType
  Domain/Commerce/       OrderStatus, Money
  Actions/Catalog/       CreateProduct, CreateVariant
  Actions/Commerce/      CreateOrder, ChangeOrderStatus
  Actions/System/        SetPublicSetting
  Services/             InventoryService, CategoryTreeService
  Models/               17 Eloquent models
  Http/Requests/         product/order/status validation
  Console/Commands/     guarded local Exment install/bootstrap
  Exment/bootstrap.php   extension seam; domain screens deferred
database/               migrations; 6 synthetic factories; demo seeder
routes/                 health only; no storefront/commerce endpoints
docker/                 local runtime and isolated DB test initialization
tests/                  real MariaDB migration/domain tests + pure unit tests
```

Models giữ relationships/casts và vài guard nhỏ cho immutable history/snapshots. Không thêm repository/interface layers chưa có nhu cầu. Customer/Content đã có Phase2 application services +Phase3 public reads; không sinh empty interfaces. HTTP writes gọi services/actions có validation/authorization; Blade chỉ nhận DTO.

## Những quyết định nghiệp vụ foundation

- **Money:** integer VND, không float; sale nullable và <= price. CONTACT là giá chưa xác định: price/sale NULL, không checkout miễn phí. Arithmetic kiểm tra overflow; migration2026_10_28_000002 enforce DB money<=9223372036854775807, FIXED price explicit non-NULL. PHP64-bit được kiểm; preflight trước DDL, giữ migrations cũ.
- **Variants:** option/value/pivot tables normalized; lựa chọn một value mỗi option; unique combination fingerprint theo sorted value IDs; DB composite FKs bảo vệ ownership. Backend giới hạn thực thi tối đa 10 options, không hard-code option1/option2; hiện seed/demo kiểm chứng 2 groups/4 variants.
- **Inventory:** một row/variant; quantity là hàng vật lý, reserved_quantity là phần giữ cho đơn. Available=quantity-reserved. Lock rows trong transaction; xử lý IDs tăng dần. NEW reserve; CANCELLED trước shipping release; SHIPPING giảm physical và reserved; RETURNED không tự restock khi chưa kiểm tra hàng trả. Chưa có multi-warehouse/ledger/ERP.
- **Orders:** use case tạo đơn tối thiểu với giá từ server; discount/shipping=0, không voucher/gateway/checkout UI. Guest được hỗ trợ; customer association chỉ từ trusted Customer object, không từ customer_id do client gửi. Snapshot item không thay theo catalog; history append-only. Không có public order HTTP route trong Phase 1.
- **Trees:** adjacency parent_id; CategoryTreeService khóa cây nhỏ theo thứ tự và chặn cycles. Không closure table/nested set. MariaDB không cho CHECK dùng AUTO_INCREMENT id nên chống cycle ở service, không mô tả sai rằng FK tự chặn cycles.
- **Archive/delete:** Product soft delete để ngừng bán nhưng giữ liên kết lịch sử. Category/variant/inventory dependencies RESTRICT khi hard delete; order snapshot links SET NULL; order items/history không cascade mất theo catalog. Không có hard-delete UI.
- **SEO:** unique slug theo entity; canonical derive từ route sau này, không lưu canonical DB để tránh lệch route. Published product trong use case mới cần published_at; không suy diễn ngày publish lịch sử từ created_at cũ.
- **Legacy IDs:** cấp ID mới và legacy_id unique nullable; variant giữ legacy index/SKU; banner namespace tblbanner/tblslider. [Mapping chi tiết](legacy-mapping.md). Không có importer Phase 4 được chạy hoặc tạo giả là đã hoàn tất.

## Runtime defaults và security boundary

Timezone Asia/Ho_Chi_Minh (+07); locale vi, fallback en; Exment UI en tạm thời do package localization. DB utf8mb4, strict mode, session DB +07. Timestamp serialization theo Laravel; cần giữ timezone/provenance khi transform UNIX timestamps legacy.

Cache/session=file; queue=sync và mail=log, chưa chạy worker/email. Log=daily/info; Exment request/SQL debug tắt. New public settings chỉ whitelist text/logo/contact/social/SEO; không chứa password, API keys hoặc analytics script. SMTP credentials ép dùng environment thay Exment settings UI.

`.env` và password local được sinh ngẫu nhiên, giữ ngoài Git; không ghi giá trị vào report. Uploader Phase2 hiện kiểm MIME/size/dimensions, decode/re-encode WebP, server-generated path và owner policy. Review thêm ImageStorage.deleteIfUnused kiểm product/category/article-category/article/banner/logo references trước cleanup; post-commit cleanup/log failure không undo save. Không thêm media registry; concurrent import phải maintenance/isolation, production storage execution rules vẫn cần kiểm Phase5/6.

Laravel CSRF/session middleware được giữ; local HTTP dùng Secure=false và HttpOnly/SameSite Lax. Staging/production HTTPS phải bật Secure và review proxy/session settings trước release. Không dùng Compose này như cấu hình production.

## Nâng v7/Laravel 12 sau

Chỉ cập nhật khi release chính thức đã xác minh và người dùng chỉ định. Đọc composer constraints của release mới; review upgrade guide, config overrides, provider/guard APIs, login_users PK và vendor migrations trước đổi FK. Domain/money/snapshot/option tables không phụ thuộc Blade hoặc Exment CustomValue. Phần coupling tập trung ở auth provider, local initializer và history actor relation; không hứa update không cần test.

Chạy lại migrate-empty/rollback/remigrate, toàn bộ tests, Exment login/CSRF smoke, platform requirements và audit. Không kéo v7 dev, không sửa production hoặc xóa legacy trong task này.
