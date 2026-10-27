# Phase 2 — Exment Admin Development

Báo cáo ngày **28/10/2026, Asia/Saigon**. Scope theo [yêu cầu Phase 2 nguyên bản](phase-2/request.md). Các kết quả local ở [evidence](phase-2/evidence); không phải chứng nhận production.

## 1. Executive Summary

Đã xây khu vực quản trị cốt lõi trong Exment: dashboard, categories/products/images/options/variants/inventory, orders/workflow, customers/addresses, article categories/articles/banner-slider, public settings và native user/role/permission. Admin không cần sửa trực tiếp domain database cho các thao tác được hỗ trợ.

Không storefront, public cart/checkout, customer auth, payment/shipping integration, legacy import hoặc deployment. Tiếp tục stack v6 theo chỉ định, không tự nâng release khác. **PHASE 3: READY WITH RISKS cho phát triển local; production vẫn BLOCKED do dependency risks đã ghi nhận.**

## 2. Phase 1 Inputs Used

Đã đọc Master Plan, Phase 0 report/business rules/security, Phase 1 report/readiness, ERD/schema, Exment mapping và legacy mapping. Trước code chạy lại 41 tests/110 assertions PASS. Phase 1 cho phép tiếp tục **READY WITH RISKS**. 17 domain tables, relationships, normalized variants, inventory reservations, immutable snapshots và history tiếp tục được tái sử dụng.

## 3. Exment Configuration

Sáu registered page plugins, native Exment routing/middleware/shell, native identity và RoleGroup. Source [exment-plugins](../exment-plugins); installer `store:configure-admin` copy runtime và tạo typed menu idempotently, không reset login hoặc role assignments. [Integration/fields contract](exment-admin.md).

Exment v6 cũ có migration di chuyển `app/Plugins`, Menu fillable thiếu Exment type/target và error renderer có thể trả HTTP 200. Đã xử lý ở application adapter/source layout và tests, không sửa vendor. Native store errors có mã HTTP đúng; native role Access và Laravel Gates đều kiểm tra server-side.

## 4. Admin Modules Implemented

| Module | Đã có |
| --- | --- |
| Dashboard | Product total/published, new/processing orders, customers/articles, completed revenue, recent orders, low-stock SKU; dữ liệu theo quyền |
| Catalog | Category CRUD/tree, Product Basic/SEO, owned image editor, options/values, create/update/generate SKU, search/filter/sort/archive |
| Inventory | Physical/reserved/available grid, locked adjustment with reason/expected quantity, audit log, export |
| Commerce | Order list/detail/snapshots, 7 statuses, locked transitions/history/stock side effects, filter/date/export; không generic delete |
| Customer | CRUD/profile, inactive, addresses/default, authorized order history/count, search/export; không customer password |
| Content | ArticleCategory tree, Article HTML/SEO/publication/image, unified Banner/Slider positions/schedule/links |
| System | Nine public setting keys, safe logo upload, native User/LoginUser/RoleGroup UI và three role presets |

Source thay đổi: `app/Admin`, `app/Services/Admin`, middleware/provider/exception adapter, guarded admin configure command, 6 plugins, 13 admin Blade templates, Docker GD WebP, tests, local setup, docs/evidence. **Không có schema migration mới, không sửa migration Phase 1.** Không cần SCHEMA CHANGE PROPOSAL vì schema đã đủ. Presets/menu là platform configuration data, không mirror domain data.

## 5. Category Management

Create/edit/view/search/filter/sort; name/slug/status/date/SEO/image/featured hoặc menu. Ordered tree locks chống self/circular parent. Unique slug. FK RESTRICT khi category đang được products, banners hoặc children dùng; article category tương tự. Lỗi constraint được chuyển thành validation message không lộ SQL.

## 6. Product Management

Basic và SEO sections riêng; detail tách ảnh/options/SKUs. Multi-image MIME/size/dimension validation, GD decode/re-encode WebP, tên ULID/public disk, primary/sort/alt/owner checks và cleanup sau commit. Không nhận path từ request hoặc dùng file manager legacy. Product soft-delete/archive giữ SKU/inventory và order snapshots.

## 7. Product Variant Management

Normalized rows/FKs giữ nguyên. Duplicate SKU và combinations bị chặn; server kiểm một value cho mỗi option và đúng product owner. Giá integer FIXED positive/sale≤price; CONTACT null và không checkout. Generator chỉ tạo missing combinations ≤256, INACTIVE/CONTACT/stock0, có xác nhận; không overwrite SKU/stock/price/IDs. Used values/group structure bị bảo vệ. [Hướng dẫn](product-management.md).

## 8. Inventory Management

Một kho như Phase 1; adjustment qua InventoryService, transaction và row lock. Không dưới reserved hoặc overflow unsigned INT; expected quantity chống lost update. Structured audit sau commit. Không thêm warehouse/ledger tables; inventory transaction ledger chưa có trong Phase 1 nên để backlog, không gọi daily log là full inventory ledger.

## 9. Order Management

Grid number/customer/phone/total/status/created; search/status/date/sort. Detail snapshot contact/address/items/totals, history actor/time/note. Không generic update totals/status/items, không delete order/history. CSV summary được authorize và chống spreadsheet formula injection.

## 10. Order Workflow

Giữ state machine Phase 1; thêm optional expected status cho admin action. Mỗi transition lock order, validate lại, update inventory/history cùng transaction. Cancel release một lần; Shipping deduct physical+reserved; Completed không cancel; Returned không auto-restock. `is_legacy` chỉ đọc. [Workflow đầy đủ](order-workflow.md).

## 11. Customer Management

Name/phone/email/status/create time, orders count; search và edit profile; address owner/default guarded. Xem history cần commerce permission bổ sung. Đơn giữ contact/address snapshot không phụ thuộc profile. Inactive thay xóa khách hàng; không tự merge shared phone/email hoặc tạo customer auth.

## 12. Content Management

ArticleCategory hierarchy có thật từ legacy, dùng tree service. Article editor đơn giản nhập HTML trong textarea, server Purifier, featured image upload/SEO/publication. Banner/Slider một entity, configured positions, HTTP/S link, schedule/start≤end và category scope. Không WYSIWYG/media library hoặc arbitrary script settings.

## 13. User / Role / Permission

Native User/LoginUser forms tạo/sửa profile, gán role, login authorization, password reset/history. Presets Product/Content/Order Manager không có quyền quản trị users/roles/settings mặc định. System administrator giữ toàn quyền. Bridge dùng direct native User membership; organization inheritance chưa hỗ trợ. Module capability gồm view/write/export, không hàng trăm permissions. Legacy role mapping có **REVIEW REQUIRED**, không đoán hoặc import. [Matrix và native disable semantics](permissions.md).

## 14. Validation

Tất cả forms validate phía server trong reusable services/actions; browser constraints chỉ hỗ trợ UX. Whitelist resource/class/fields/sort/setting keys; no mass-assigned legacy ID/totals/actor/reserved quantity. Unique indexes/FKs/CHECK vẫn là lớp bảo vệ cuối. PUBLISHED cần published_at; URL protocols/MIME/size/date/quantity/email newline được kiểm.

## 15. Security

Native session auth+CSRF; per-module Gates và scoped child ownership; removed LoginUser/profile revokes existing store session. Store error handler trả đúng HTTP status. Blade text escape; rich content purifier. Native operation audit redact JSON password/token structurally. CSV no-store/private, formula neutralization; export PII không public. Files runtime/credentials/backups ngoài Git.

Critical/High upstream dependency findings **vẫn được ghi nhận, chưa sửa**: xem audit hiện tại tại [composer audit](phase-2/evidence/composer-audit.json) và baseline [ADR](architecture.md). Chỉ localhost, không đánh dấu audit PASS hoặc tắt policy/ignore để che lỗi. Không kiểm thử khai thác production.

## 16. Automated Tests

Toàn bộ Phase 1 + Phase 2 chạy trên MariaDB thật với guard `bancay_test`: [test results](phase-2/evidence/tests.json). Bao gồm migrations từ trống/rollback/remigrate, typed plugin install idempotency, authorization/revocation/IDOR, slug/tree, sanitize/upload/rollback/primary, options/generation/price, snapshot/inventory/status/history, safe native reset/redaction, date filters, CSV và bounded query count.

| Kiểm tra cuối | Kết quả |
| --- | --- |
| Automated suite | **67 tests / 318 assertions PASS**, 0 failure/error/skipped, khoảng24 giây |
| Pint | **PASS,120 PHP files** |
| Blade compile | PASS |
| Composer strict validate / platform requirements | PASS |
| Exment requirements | 7/7 OK; GD WebP encoder hoạt động |
| Artifact/privacy/source/schema/document verification | 580 checks PASS;135 local document links; Master Plan nguyên byte |
| HTTP smoke | Native local-admin login, grids/detail pages200; thiếu CSRF419, invalid form422 |
| Composer audit | **FAIL,6 advisory entries trên2 packages**; không deployment |

Concurrency test fork hai process/kết nối DB độc lập: simultaneous stock deduction không âm, competing order reservations không oversell, expected-status transitions không replay, global SKU unique. Được chạy thật, không sequential mock. Formatter VND kiểm BIGINT/SUM vượt floating-point precision. Pint, composer manifest/platform, Exment requirements, GD WebP và Git whitespace kiểm tra trong [verification](phase-2/evidence/verification.json).

## 17. Manual UI Tests

Đã thao tác real browser/real Exment login bằng synthetic accounts và fixtures: create category/product, upload/select primary/alt, option values, generate SKU, set price/status/stock, search/filter, customer create/view, order snapshot/status/history, article/banner upload/create, settings save, native profile create/edit và native permission editor. Content Manager thấy đúng content, direct URL order bị 403. [20 flow records](phase-2/evidence/ui-tests.json).

Không đổi password cá nhân qua UI; reset/native credential revocation kiểm bằng automated tests. Initial stale-CSRF login sau rebuild bị 419 đúng cơ chế; fresh login thành công. UI phát hiện lỗi re-save primary/default stale model; đã sửa refresh và giữ regression checks.

## 18. Evidence

[Dashboard](phase-2/evidence/dashboard-ui.jpg), [Product](phase-2/evidence/product-ui.jpg), [Order/history](phase-2/evidence/order-ui.jpg), [Content role](phase-2/evidence/content-role-ui.jpg), [403](phase-2/evidence/permission-denied-ui.jpg). Tất cả dữ liệu hiển thị tổng hợp. JSON checks/tests/platform/audit và schema comparison tại evidence folder; raw logs/credentials private không đưa vào docs.

## 19. Performance Findings

Grids paginate25; exports chunk200. Product category/images/variants/inventories eager load, order customer eager load, customer orders aggregate. Test đo số query khi tăng 1→21 products vẫn bounded, không N+1; baseline cụ thể tại [performance](phase-2/evidence/performance.json). Product form/detail cần options/gallery; chưa benchmark data lớn hoặc query plans sau import. CSV không phải snapshot transaction export khi database đang ghi đồng thời.

## 20. Business Rule Comparison

[32 rules với legacy/new/reason/impact](phase-2/business-rule-comparison.csv), phân loại PRESERVED/CHANGED/DROPPED/NOT APPLICABLE. Mọi rule đổi có nhãn BUSINESS RULE CHANGE. Giữ contact pricing, snapshot, gallery order, SEO fields, banner intent; thay shared SKU/JSON overwrite/client pricing/boolean order toggle/MD5/login-only privilege bằng invariant mới. Public paging/popularity/cookies nằm đúng phase frontend, không ngầm triển khai.

## 21. Risks

Exment v6/Laravel10 unsupported dependencies là risk lớn hiện hữu, production BLOCKED. Single desktop/browser UI run, chưa browser matrix/mobile, penetration test, large dataset, real SMTP hoặc production hardening. Native v6 disable login xóa LoginUser, actor FK có thể NULL; profile vẫn giữ và timeline thông báo rõ. V7 upgrade cần kiểm lại toàn bộ adapters trước deployment. Hosting backup bổ sung vẫn DEFERRED BY USER.

## 22. Technical Debt

Fine-grained read/write/export abilities và organization role inheritance; rich editor/media library; safe import/legacy transformation/quarantine; stock ledger/return restock approval/reservation expiry; order line exports; trash restore UI; production image storage/server execution rules/AV; audit retention/external immutable logging; production-scale performance và UI browser matrix. Không coi các mục ngoài scope là đã implement.

## 23. Phase 3 Readiness

Admin chuẩn bị dữ liệu, services/domain có thể tái sử dụng cho Blade frontend. Không có functional blocker core admin còn chưa ghi nhận. Chỉ bắt đầu Phase 3 khi được chỉ định; không publish production với audit hiện tại. Trước frontend cần chốt canonical routes/SEO fallback/publication category scopes và tái sử dụng authoritative price/stock/actions; trước release phải upgrade/audit sạch và hoàn tất backup/cutover prerequisites.

```text
PHASE 3 STATUS:
READY WITH RISKS (LOCAL DEVELOPMENT ONLY)

PRODUCTION DEPLOYMENT:
BLOCKED — temporary Exment v6/Laravel10 dependency security findings
```
