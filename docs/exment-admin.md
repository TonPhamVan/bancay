> Current stack: **Exment7.0.0 / Laravel12.70.2 / admin-core1.0.1**, PHP8.3.36. [Upgrade report](exment-v7-report.md). Composer audit PASS (0 advisories/0 abandoned), npm audit0; RR-30/RR-34 RESOLVED.132 PHP/814 assertions +5JS and47 browser cases PASS. Both local DBs upgraded;17 business tables unchanged; migrated catalog still hidden. Older v6 pins/audit failures/exceptions below are historical. No production release.

# Exment Admin — Phase 2

> Current Phase4: [report](phase-4-report.md). Isolated rehearsal completed:113 products/118 SKUs/255 historical orders;126 PHP tests/770 assertions +5JS PASS; native/frontend browser PASS. RR-30/RR-34 temporarily accepted by user; audit still FAIL, production blocked. Phase5 READY WITH RISKS. Earlier NOT READY/import-not-started statements below are historical.

Triển khai local ngày 28/10/2026. Đọc [báo cáo Phase 2](phase-2-report.md), [yêu cầu gốc](phase-2/request.md) và [mapping Phase 1](exment-mapping.md). Stack tạm Exment 6.2.15/Laravel 10; current dependency applicability xem [review](risk-review/dependency-assessment.md); production bị chặn. Native CRUD/upload/role bridge đã kiểm lại110-test suite.

## Cài đặt / truy cập

```bash
bash scripts/setup-local.sh
docker compose up -d app
# Với foundation đã cài: build lại GD có WebP, không cài lại Exment/reset identity.
docker compose build app
docker compose up -d --no-deps app
docker compose exec app php artisan store:configure-admin
```

Đăng nhập `http://localhost:8000/admin`, user `local-admin`, password private tại `legacy-private/phase1/admin.env`. Menu **Tổng quan cửa hàng** mở `/admin/plugins/bancay_dashboard`. `store:configure-admin` chỉ local/testing, không reset mật khẩu hoặc gán người dùng vào role. Chạy lại giữ các permission presets đã được admin sửa.

Seed tùy chọn, chỉ dữ liệu tổng hợp local/testing:

```bash
docker compose exec app php artisan db:seed --class=AdminDemoSeeder
```

Seeder giữ fixture Phase 1 và tạo ảnh JPEG tổng hợp nếu thiếu. Ảnh upload thật được lưu `storage/app/public`; public symlink tạo bởi setup. Dữ liệu/ảnh runtime không vào Git. Không chạy importer legacy trong phase này.

## Cơ chế Exment

Sáu **page plugins** dùng `PluginPageBase`, được Exment route/authenticate/render trong shell native. Source được version tại [exment-plugins](../exment-plugins); command copy sang `storage/app/plugins` và đăng ký vào bảng platform `plugins`/`admin_menu`. Không đặt source tại `app/Plugins`: một migration native v6 sẽ di chuyển thư mục đó khi cài từ DB trống.

Đây là Laravel application đang có, không phải admin/backend khác. `StorePage → StoreController → Actions/Services → native Eloquent tables`. Không tạo CustomTable mirror hoặc đưa variants vào CustomValue JSON. Blade admin chỉ render data đã load, không query DB. Native `LoginUser`, User CustomValue và RoleGroup giữ nguyên ownership của platform.

Nguồn upstream đã đối chiếu: [PluginPageBase v6.2.15](https://github.com/exceedone/exment/blob/v6.2.15/src/Services/Plugin/PluginPageBase.php), [PluginServiceProvider v6.2.15](https://github.com/exceedone/exment/blob/v6.2.15/src/Providers/PluginServiceProvider.php). Registration/filesystem/permission bridge được kiểm tra trên package đang lock, không giả định v7 tương thích.

## Entity / Grid / Form contract

Fields chính xác, labels, display field, model adapter có tại [StoreResources](../app/Admin/StoreResources.php). Quan hệ/FK tại [database design](database-design.md). Tất cả native-domain entries dưới đây có **Custom Table Name: không tạo**.

| Entity / plugin | Display / search | List columns / filters | Form / relations / validation | Permission / workflow / export |
| --- | --- | --- | --- | --- |
| Category / BancayCatalog | name; search name | id/name/parent/sort/status/updated; status,parent,sort | name,slug,parent,description,image,sort,status,featured,published,SEO; tree guard; unique slug; RESTRICT | catalog.manage; DRAFT/PUBLISHED/ARCHIVED; chưa import |
| Product / BancayCatalog | name; name và SKU relation | thumbnail/name/category/variant count/price range/available/status/updated; category,status,featured,sort | Basic/SEO form; images/options/SKUs là child editors; category FK; unique slug; HTML purifier | catalog.manage; publication; CSV product metadata |
| ProductImage / child | alt/path, scoped product | thumbnails/primary/order | multi-upload,alt,sort,primary,delete; MIME/size/dimensions/re-encode; owner check; primary unique | catalog.manage; không xóa ảnh đang link SKU |
| Option / Value / child | name/value | groups và values theo thứ tự | name,sort,values từng dòng; duplicate case-insensitive; giữ ID label không đổi; không xóa value đã dùng | catalog.manage; không đổi nhóm khi SKU tồn tại |
| Variant / child | SKU; list product search SKU | SKU/options/status/effective price/available | SKU globally unique; pricing type,price,sale,status,image; create chọn mỗi option một value; combination immutable sau tạo | catalog.manage; ACTIVE/INACTIVE; generate additive ≤256 combinations |
| Inventory / BancayCatalog | variant SKU/product name | SKU/product/physical/reserved/available/updated; search,sort | adjustment: target stock,expected stock,reason; không có generic CRUD/reserved input; InventoryService | catalog.manage; transaction/row lock; CSV stock |
| Order / BancayOrders | order_number; number/customer name/phone | number/customer/phone/total/status/created; status,date,sort | snapshot detail và status action; expected_status; actor từ session; không edit totals/items hoặc delete | commerce.manage; state machine; CSV order summaries |
| OrderItem / History / child | snapshot name/SKU; timeline | unit price/qty/subtotal; from/to/actor/time/note | chỉ đọc; native catalog không ghi đè snapshot | commerce.manage; append-only history; không generic workflow |
| Customer / BancayCustomers | name; name/phone/email | name/contact/status/orders_count; search,status,sort | name,phone,email,status; no password; email/phone không unique | customers.manage; ACTIVE/INACTIVE; CSV có PII, chỉ người có quyền |
| Address / child | recipient/line1 | addresses/default | recipient,phone,address components,country,label,default; scoped owner; một default; sửa profile không sửa order | customers.manage; delete có xác nhận |
| ArticleCategory / BancayContent | name; name | parent/sort/status/updated; status,parent,sort | tree/name/slug/description/image/menu/status/published/SEO; guard như Category | content.manage; RESTRICT với articles/children |
| Article / BancayContent | title; title | title/category/status/updated; category,status,sort | title,slug,category,excerpt,HTML content,image,featured,menu,status,published,SEO; purifier | content.manage; archive thay hard delete; import backlog |
| Banner / BancayContent | name; name | name/status/updated | image bắt buộc lúc tạo; URL HTTP/S; position config; category scope,priority,sort,status,start/end; date validation | content.manage; ACTIVE/INACTIVE + scheduled metadata; một entity Banner/Slider |
| Setting / BancaySettings | key | whitelist form | site_name/logo/contact_phone/email/address/facebook/map/meta_title/meta_description; logo upload; text/URL rules | settings.manage; không DB/SMTP/API secrets; không export |
| User / LoginUser / RoleGroup | native Exment | native grids/forms/filters | profile + login authorization + password/reset + native permission editor | system administrator; [permissions](permissions.md); không MD5 |

Grid tìm có giới hạn 200 ký tự, pagination 25; dùng bound queries và allowlist sort. Categories hiển thị tên trong filter/select. Product list eager-load category/images/variants/inventories; Customer có `withCount(orders)`; Order list eager-load customer. Không query theo từng row trong view.

Ngày publication/schedule tuân theo phạm vi TIMESTAMP của MariaDB Phase 1 (1970–2038), có validation để tránh zero-date/truncation; không tự đổi schema sang DATETIME trong phase này. Address postal_code ≤20, category description ≤10000 ký tự, khớp giới hạn cột nền tảng.

## Error handling / audit

Exment v6 có web error renderer trả HTTP 200 ở một số lỗi và permission responder có thể kết thúc PHP process. Store adapters dùng application exception handler trả đúng 403/404/405/419/422, validation errors và input bag của Laravel. Middleware `StoreAdminGuard` chạy trước native permission responder; controller vẫn Gate-check. Native authentication, CSRF và layout không bị thay thế.

Order status có relational histories. Điều chỉnh tồn có structured daily log `store.inventory.adjusted` với actor/id/before/after/reason sau commit; export có `store.export`. Native operation audit giữ trong `admin_operation_log`, password/token được redact theo cấu trúc JSON, kể cả nested/escaped quotes. Không công bố log bodies/PII. Inventory ledger chưa có trong schema Phase 1; daily logs không thay thế ledger hoặc external immutable audit store.

CSV product/inventory/order/customer có UTF-8 BOM, whitelist columns, neutralize spreadsheet formula prefixes và `no-store, private`. Export đang dùng filter/sort của grid, chunk 200. Order CSV là summary; line-level finance export/import và CSV snapshot consistency khi có concurrent writes nằm trong backlog. Không bật generic Exment CSV import lên domain tables.

## Giới hạn rõ ràng

Scope Phase2 riêng không gồm storefront/cart/checkout; các flow guest này đã có ở Phase3 và test xuyên phase. Customer auth, API riêng, gateway/shipping API, voucher/review, multi-warehouse, full import và deployment vẫn chưa triển khai. Settings không giữ secrets. Chưa kiểm thử production, scale sau import, malware scanning, browser matrix hay cơ chế backup hosting. V7 cần kiểm lại plugin registration/guard/schema/password/permissions/errors/audit/migrations và chạy cả regression suite.

## Risk review hiện tại

Shared-media cleanup sau commit hiện gọi ImageStorage.deleteIfUnused, giữ path còn reference ở product/category/article-category/article/banner/logo (gồm archived product images), chỉ dọn last ref. Cleanup/log lỗi giữ orphan để xử lý sau, không xóa ảnh mới đã commit. Tests có actual commit fault injection. [Register RR-16/17/27](risk-register.md), [cross-phase test](../tests/Feature/RiskReviewIntegrationTest.php).

Admin→category/product/options/variants/price/stock→guest checkout→admin snapshot/status PASS;100000→catalog150000 giữ snapshot100000;stock10/order2 reserve2 và cancel release đúng1lần. Fresh browser review BLOCKED vì provider, không giả screenshots mới. Current Phase4 NOT READY; xem [final report](risk-review-report.md).
