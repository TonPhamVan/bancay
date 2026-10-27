# Exment data model mapping — Phase 1

Đây là baseline thiết kế Phase 1. Các mục "Phase 2/planned" bên dưới ghi trạng thái tại thời điểm foundation; implementation hiện tại xem [Exment Admin](exment-admin.md) và [báo cáo Phase 2](phase-2-report.md).

Stack tạm: Exment **6.2.15**, theo chỉ định người dùng. [Architecture/risk decision](architecture.md). Exment đã install/initialize và login HTTP local thành công; **chưa xây CRUD nghiệp vụ Phase 2**.

## Integration boundary đã chọn

Domain tables là relational Eloquent tables chuẩn. Không tạo CustomTable cùng dữ liệu và không sync vào CustomValue JSON. Exment CustomTable có lifecycle/system columns và dynamic value model riêng, không tự ánh xạ các composite FKs, pricing/stock transactions và immutable order snapshots của schema này.

Phase 2 dùng **authenticated Exment page plugins** để render native Eloquent grids/forms và gọi Actions. Cơ chế page/plugin có trong [PluginPageBase của v6.2.15](https://github.com/exceedone/exment/blob/v6.2.15/src/Services/Plugin/PluginPageBase.php) và PluginServiceProvider. Không giả định PluginCrudBase/CustomTable generic import đã tương thích các native tables. Không cần thêm app/backend/database hoặc fork Exment.

| Domain Entity | Database Table | Exment Custom Table / Built-in / extension | Admin CRUD? | Search? | Filter? | Workflow? | Import/Export? | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Category | categories | Page extension + native Eloquent | Phase 2; archive/reparent guarded | name/slug | parent/status | Publication; move via CategoryTreeService | Adapter planned | FK RESTRICT; no cycle bypass through generic parent field |
| Product | products | Page extension + native Eloquent | Phase 2; CreateProduct; archive | name/slug + SKU relation | category/status/featured/published_at | Publish/archive | Guarded adapter planned | No price/stock on Product; soft delete; sanitize HTML |
| ProductImage | product_images | Product child editor | Phase 2; validated upload path | filename/owner | product/is_primary | One primary | Asset mapping adapter | MIME/size/storage validation before write; generated unique primary key |
| ProductOption / Value | product_options / product_option_values | Product child editor | Phase 2 | name/value | product | Guard when SKU uses value | With product import | Ownership/combo constraints; no deleting referenced values |
| ProductVariant | product_variants / product_variant_values | Product child grid/form | Phase 2 via CreateVariant; updates need dedicated action | SKU/legacy SKU | product/status/pricing_type | Enable/disable; preserve combinations | Guarded adapter | Global unique SKU; one selection per option; no embedded variant JSON |
| Inventory | inventories | Read grid + adjustment page action | Adjust only; no arbitrary CRUD | SKU/product | available/reserved | InventoryService locks/checks | Reconciliation adapter later | Never set reserved_quantity directly from admin form |
| Order | orders | Native grid/detail/status action page | Create/change status via actions; no delete | order number/contact | status/date/customer | Enum + ChangeOrderStatus | Export; history import via separate Phase 4 path | Never run generic workflow update that bypasses stock/history |
| OrderItem | order_items | Read-only Order detail | No snapshot edit/delete | SKU/product snapshot | order | Immutable | With order history | Item values independent of current catalog; nullable links |
| OrderStatusHistory | order_status_histories | Read-only Order timeline | Append only via action | order/note/actor | status/date | Audit of transitions | Export restricted | changed_by→Exment LoginUser.id, not base_user_id |
| Customer | customers | Native page extension | Phase 2; no account login | name/email/phone | status | Basic active/inactive | Adapter planned with PII controls | No automatic merge by shared email/phone |
| CustomerAddress | customer_addresses | Customer child form | Phase 2/checkout preparation | recipient/phone | customer/default | One default | With approved customer data | Order owns independent shipping snapshot |
| ArticleCategory | article_categories | Native page/tree extension | Phase 2 via tree guard | name/slug | parent/status/menu | Publication | Adapter planned | Legacy article tree exists; cycle guard required |
| Article | articles | Native page extension | Phase 2; sanitizer/validation action required | title/slug | category/status/featured/published_at | Publish/archive | Adapter planned | Current schema/model only; no unsafe raw HTML handler exposed |
| Banner/Slider | banners | One native extension by position | Phase 2 | name/link | position/category/status/schedule | Scheduled active window | Asset-aware adapter | Slider is home_slider position, not another data store |
| Setting | settings | Public-setting page | Phase 2 via SetPublicSetting | key | Whitelisted key | None | Optional safe configuration export | No SMTP/API secrets, arbitrary analytics script or password fields |
| Administrator | Exment LoginUser + system user values | **Built-in** | Platform native | Built-in | Built-in | Native authentication/password reset | Identity strategy only; no MD5 import | No App User or second user table |
| Role/Permission | Exment platform roles/authority metadata | **Built-in**, bridged to Laravel abilities | Phase 2 configuration | Built-in | Built-in | Platform auth + explicit domain actions | Restricted configuration export | No mapping of old integer roles directly |

All CRUD/search/filter/workflow/import entries marked Phase 2/planned are **mapping deliverables**, not screens implemented in Phase 1. Current foundation has no native product/order HTTP CRUD route. A plugin's availability in Exment is not a substitute for per-action authorization.

## Authorization foundation

Abilities: catalog.manage, commerce.manage, customers.manage, content.manage, settings.manage. They default deny; presently only native Exment system administrators pass. Phase 2 may extend this using verified Exment plugin/role permission APIs without inventing duplicate role records. UI hiding is never a guard. Requests derive actor ID from authenticated LoginUser; customer association is server-side, not a submitted foreign key.

Exment's generated Laravel-admin duplicate auth/home routes were removed from app/Admin/routes.php; native Exment routes own login/dashboard. The extension bootstrap is app/Exment/bootstrap.php. Platform configuration remains in Exment tables; public store configuration is the separate whitelisted settings table because its audience/permissions differ.

## Invariants for the next phase

Forms must call application Actions; grids may read native models with explicit scopes. Unique constraints and FKs remain last-line guards. Order totals/history/snapshots/reservations are not editable generic fields. Imported legacy orders (`is_legacy=true`) do not take part in live status/stock mutation. Importers must validate outside Exment generic CSV facilities before persisting domain entities.

Text fields như customer name/note/SKU phải được escape theo HTML context khi render grids/Blade; không dùng raw HTML display cho dữ liệu request/import. Chỉ content được sanitizer cho phép mới render rich text. Domain storage không pre-escape text rồi gây double encoding.

**Known risk:** v6/Laravel 10 dependency audit remains FAIL. Phase 2 is ready for local work with risks, not deployment. Before v7 upgrade, recheck plugin registration, LoginUser schema/guard and installation migrations; do not assume API compatibility from a release announcement alone.
