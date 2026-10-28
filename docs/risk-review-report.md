# Risk Consolidation & Closure Review — Phases 1–3

Ngày28/10/2026; baseline `b574d1943aa9081f2449b103ab47dda63f6de74e`, branch feature/develop. [Yêu cầu nguyên bản](risk-review/request.md). Scope: kiểm source/docs/tests hiện tại, xác minh risk, fix đúng layer và regression; **không bắt đầu Phase4, không actual legacy import, không đổi dependency, không production/deploy/hosting/DNS**. Kết quả review được đóng gói trong commit riêng theo chỉ định người dùng.

## 1. Executive Summary

Core foundation/admin/storefront đã được kiểm lại trên MariaDB thật. Có3 nhóm lỗi thực tế được sửa: SQL money CHECK nhận FIXED NULL và unsigned amounts vượt PHP integer domain; xóa ảnh còn được entity khác tham chiếu; lỗi cleanup/log sau commit làm ảnh mới đã lưu bị xử lý sai. Mapping banner/publication/orphan/SKU được làm rõ ở tài liệu, không tạo importer.

**110 PHP tests /656 assertions PASS,0 failures/errors/skipped**, cùng5 JavaScript tests PASS. Migrate fresh/upgrade/preflight/down/up, auth/permission, concurrency, checkout/snapshot và một test liên tục admin→frontend→admin PASS. Audit npm0; Composer vẫn FAIL. Critical TokenGuard finding là FALSE POSITIVE cho exact official10.50.4 implementation đã đối chiếu hash/test; không được dùng kết luận này để xóa các findings khác.

Strict readiness hiện **NOT READY**: RR-30/RR-34 chưa có compatible remediation cho High email-validation advisory và supported stack; RR-28 fresh browser core verification bị provider chặn. Không có Critical data-integrity defect còn tái hiện trong suite, nhưng không chứng nhận zero vulnerability hoặc production readiness.

## 2. Phase 1 Risks Reviewed

Đã đọc Master Plan, Phase0/report/source inventory/business rules/security/mapping, Phase1 report/ERD/FKs/indexes/Exment mapping cùng models/actions/migrations/tests. Normalized options/values/variants và global SKU unique hiện có thật; inventory1:1 và composite owner FKs không cần redesign. Immutable item/history và nullable catalog references giữ đơn khi archive/hard-delete; CustomerAddress và snapshot contact tách biệt. SEO fields đủ cho mapping.

Confirmed gaps nằm ở DB defense khi validation bị bypass: MariaDB CHECK chấp nhận UNKNOWN từ NULL và unsigned BIGINT rộng hơn PHP integer casts. Sửa bằng migration additive, không sửa migrations cũ hoặc đổi entity IDs/relations. Stack compatibility v6 hiện chạy, nhưng dependency maintenance/security vẫn chưa đủ để clear gate.

## 3. Phase 2 Risks Reviewed

Native Exment page plugins, auth/LoginUser, direct role bridge, CRUD/forms/options/generation, locked inventory adjustment, order status/history, customer-address ownership, content/settings và exports được đối chiếu source/tests. Domain-specific writes qua services/actions; scalar CRUD ở application service, không generic update stock/reservations/order totals/history. HTTP adapters kiểm Gate và owner scope; native user administration không mở cho manager presets.

Upload MIME/size/pixel decode/re-encode đã được Phase2 xử lý, nên không sửa lại một foundation limitation đã hết. Lỗi mới là delete old shared path sau commit. Cleanup giờ kiểm tất cả declared references; disk/DB/logger failure giữ file để xử lý sau và không undo committed save. Daily logs không được gọi là full stock ledger.

## 4. Phase 3 Risks Reviewed

Home/category/search/product DTO/variant data/cart/checkout/success/articles/contact/SEO/publication cùng JS picker và PHP regression đã kiểm lại. Binding/escape/sanitizer, server price/stock, option ownership, qty limits, quote-change review, transaction/idempotency và exact session success grant đều có tests. Frontend bundle/source không đổi so với baseline; không cần build lại cho thay đổi PHP/schema này.

Fresh responsive/browser interaction **BLOCKED**, không giả PASS bằng screenshots cũ: provider lỗi `constraints` trước navigation; [chi tiết](risk-review/browser-validation.md). Historical Phase3 desktop/mobile evidence giữ nguyên, browser/device matrix vẫn NOT TESTED.

## 5. Consolidated Risk Register

[Register41 risks](risk-register.md) có Risk ID, Origin Phase, severity, nội dung, evidence, initial assessment, current final status và impact. [JSON](risk-review/evidence/risk-register.json) có thêm Reason/Target Phase cho ACCEPTED/DEFERRED. Severity là tác hại gốc, không phải số Critical đang mở. Mọi row kết thúc bằng một trạng thái hợp lệ; không gom tất cả dependency findings thành một exploit.

## 6. Risks Already Resolved

RR-01–05,08–09,13–15,18,21–25 đã được source/tests của các phase trước xử lý: normalize/FKs/unique SKU/stock lock/order snapshots/history/customer/default/category tree/native identity/permissions/upload pipeline/password/SQLi/XSS/CSRF/IDOR/server quote/idempotency/order privacy/SEO. Không refactor các layer này chỉ vì report foundation từng ghi planned.

RR-29 **FALSE POSITIVE** riêng exact TokenGuard: official tag byte match, rejection trước UserProvider, positive string/hash control. Exment publicform có inheritance, nên kết luận không dựa đơn thuần vào auth driver name. [Assessment và primary sources](risk-review/dependency-assessment.md).

## 7. Risks Fixed Now

| Risk | Confirmed reproduction | Fix |
| --- | --- | --- |
| RR-06 | Raw FIXED price=NULL được old SQL CHECK nhận | Explicit price IS NOT NULL trong CHECK mới |
| RR-07 | Variant/order/item nhận9223372036854775808 | Signed64 domain upper bounds cho mọi monetary column |
| RR-16 | Replace category/delete product image làm mất shared file | ImageStorage reference checks cho product/category/article-category/article/banner/logo |
| RR-17 | Old-file delete/logger throw sau real commit | Best-effort post-commit cleanup, giữ committed new image; orphan có thể còn |
| RR-10 | Mapping position sidebar_top/sidebar_bottom/bottom không hợp config | Contract a0/a1→sidebar,a4→content_bottom; DRAFT/INACTIVE, deterministic collision/quarantine/default history OFF |
| RR-27/41 | Thiếu một integration test liên phase và docs stale | Thêm native admin→guest checkout→admin snapshot/cancel test; cập nhật current status, giữ historical evidence |

[Before reproduction](risk-review/evidence/reproduction-before.json):6 failing cases trên MariaDB, không chỉ mock. Post-commit failure test dùng actual commit riêng; các reference tests trong RefreshDatabase dispatch callback có giải thích, không được coi callback đó là actual transaction commit.

## 8. Architecture Changes

Giữ Modular Monolith và Route→Controller→Service/Action→Model/DB. Không thêm repository abstraction, Exment JSON mirror, second auth, media registry hoặc stock ledger. ImageStorage là điểm chung cho4 call sites sau commit; request rollback vẫn dọn new unreferenced generated upload. Concurrent import phải cô lập/maintenance vì reference scan không phải distributed media lock.

Giữ17 domain tables +checkout_attempts technical. PHP64-bit là domain runtime đã kiểm (PHP_INT_SIZE=8). Không thể dùng code integer-domain này để hứa chạy PHP32-bit. [Architecture](architecture.md), [frontend](frontend-architecture.md), [admin](exment-admin.md).

## 9. Database Changes

Migration mới [2026_10_28_000002_harden_money_domain_checks](../database/migrations/2026_10_28_000002_harden_money_domain_checks.php) thêm3 CHECKs, không sửa old migration: variants_money_domain_check,orders_money_domain_check,items_money_domain_check. CONTACT price/saleNULL; FIXED positive/non-NULL/<=PHP_INT_MAX, sale positive<=price; order/item money<=max. Existing unsigned/total/sale/quantity/FK checks vẫn giữ.

Preflight scan cả3 tables trước ANY DDL, reject bằng counts, không export row/PII, không truncate/reprice. MariaDB DDL không fully transactional: unexpected infrastructure failure vẫn có thể tạo partial DDL, cần kiểm status trước retry. Down chỉ bỏ3 checks mới. Tests chứng minh existing row IDs/stock còn nguyên, invalid preflight không thêm một phần checks, correct→up→down→up PASS; full fresh/vendor rollback/remigrate và relation tests PASS.

Local operational bancay đã migrate sau khi tạo private pre-change SQL dump. [Dump metadata/hash](risk-review/evidence/pre-change-dump.json) không chứa dump. **Restore riêng dump mới NOT TESTED**; không gọi đó là verified production backup. [Runtime evidence](risk-review/evidence/runtime-schema.json):17 tables **columns/FKs/indexes unchanged**,3 CHECKs thêm và migration applied. Không nói schema hoàn toàn không đổi.

## 10. Security Fixes

Money DB bounds ngăn invalid persisted pricing; shared-media cleanup bảo vệ asset integrity. Không thay passwords/credential, không bật APIs/SMTP, không audit ignore/vendor patch. Existing app CR/LF email guards PASS, nhưng chưa chứng nhận mọi native Exment mail feature.

[Composer fresh audit](risk-review/evidence/composer-audit.json): FAIL exit1,6 entries/5 unique/2 packages. [Per-advisory assessment](risk-review/dependency-assessment.md) phân biệt TokenGuard false positive, High email unresolved, local temporary URL feature không sử dụng, debug OFF và JWT APIs OFF. Runtime mitigations không phải supported upstream remediation. Production vẫn BLOCKED. SQLi/XSS/CSRF/auth/authorization/password/upload/price/variant/qty/order-access regression PASS không thay penetration test.

## 11. Tests Added

14 PHP methods thêm so với96 baseline:

- [RiskReviewIntegrityTest](../tests/Feature/RiskReviewIntegrityTest.php):9 cases NULL/bigint DB rejection, shared refs/logo/last-ref cleanup và max integer snapshot.
- [RiskReviewIntegrationTest](../tests/Feature/RiskReviewIntegrationTest.php):1 continuous native admin→frontend→admin scenario.
- [RiskReviewSchemaUpgradeTest](../tests/Feature/RiskReviewSchemaUpgradeTest.php):1 upgrade/preflight/rollback/remigrate case trên existing rows, không outer transaction.
- [RiskReviewCommitSafetyTest](../tests/Feature/RiskReviewCommitSafetyTest.php):1 actual commit với disk+logger fault.
- [DependencyGuardTest](../tests/Unit/DependencyGuardTest.php):2 non-string rejection và valid token/hash positive control cases.

Tests chỉ migrate bancay_test, guard từ chối DB khác; contention suite fork independent connections theo thứ tự, không chạy nhiều destructive suites cùng DB. Raw JUnit/logs private/ignored; [safe result](risk-review/evidence/tests.json).

## 12. Regression Results

| Check | Result | Evidence / limit |
| --- | --- | --- |
| Full Phase1+2+3 +new PHP suite | **PASS110 tests/656 assertions**,0 skip/fail/error | Real MariaDB bancay_test;43.83s |
| Fresh/rollback/remigrate +data upgrade/preflight | PASS | Schema lifecycle +RiskReviewSchemaUpgradeTest |
| Stock/order/status/SKU/checkout contention | PASS | Existing real two-process StoreConcurrencyTest, rerun in full suite |
| JS picker | PASS5 tests | Node24 assets service, no new JS |
| Pint / strict Composer validate / platform / Exment requirements | PASS | [Checks](risk-review/evidence/checks.json); final Pint147 files |
| Blade compile / read-only HTTP native/public/CSRF | PASS | [HTTP smoke](risk-review/evidence/http-smoke.json), no response bodies/credentials exported |
| npm audit | PASS0 | [Audit](risk-review/evidence/npm-audit.json) |
| Composer audit | **FAIL exit1 / remediation BLOCKED** | Not treated as PASS by domain tests |
| Fresh browser desktop/mobile core | **BLOCKED — MANUAL TEST REQUIRED** | Provider fails before app navigation |
| Chrome/Edge/Android/Safari, production penetration/load/WCAG/CSP | **NOT TESTED — MANUAL TEST REQUIRED** | No matching device/environment; Phase5 scope |
| New local dump restore / production backup-cutover | **NOT TESTED** | Artifact hash only; hosting backup user deferred |

Full outputs giữ private. [Source manifest](risk-review/evidence/source-manifest.json) gắn tests với source; [final verification](risk-review/evidence/verification.json) kiểm artifacts/links/hash/statuses/history preservation, không thay runtime tests.

## 13. Cross-Phase Integration Test

Một native admin session tạo published category/product2 option groups ×2 values, generate4 variants INACTIVE/CONTACT/stock0. Enable đúng một variant SKU QA-RR-100, set price100000, adjust physical10 bằng endpoint/service. Logout native admin; Home/category/product hiển thị đúng2 option groups/1 active variant; cart quantity2 với forged browser stock99999 vẫn dùng server inventory. Checkout tạo order NEW, item qty2×100000=200000, physical10/reserved2 và owned success.

Quay lại authenticated native admin context, set catalog price150000, mở order detail: price snapshot vẫn100000, SKU/variant/qty/history đúng. Cancel NEW→CANCELLED đưa reserved2→0, physical vẫn10; history có actor. Replay stale cancel422, không release thêm. Assertions dùng app HTML format100,000 ở admin và integer exact ở DB; không thay domain rule để ép test pass.

HTTP feature integration PASS, dùng native LoginUser principal qua test guard (`actingAs`), không giả browser login form; real native login được kiểm riêng ở HTTP smoke. JS picker selection logic5 tests PASS. Fresh browser click-through E2E chưa chạy được; không gộp hai loại bằng chứng thành một browser PASS.

## 14. Remaining Risks

**Blockers:** RR-30/RR-34 compatible supported remediation cho High email CRLF; RR-28 fresh browser core/manual evidence. Latest official stable Exment checked6.2.15; không có release v7 đã xác minh để tự upgrade. Cần thực hiện đúng compatible upgrade/official fix, kiểm audit và native auth/mail trước khi đóng RR-30; không yêu cầu người dùng chấp nhận một blanket waiver.

Other remaining items đều có boundary/Reason/Impact/Target Phase trong register: dependency flags khác, missing media, legacy URL parity, single-instance locks và hosting backup. Legacy source vẫn có bad data; quarantine contract không có nghĩa đã clean/import rows. New pending orders synthetic local không phải fulfillment/revenue thật.

## 15. Deferred Technical Debt

Granular permissions/org inheritance, rich editor/trash/line export/media library, ledger/expiry/retention/returned inspection, shared session/distributed locks trước scale, customer auth/recovery/payment/shipping/voucher/review, CDN/cache/srcset và audit retention giữ đúng scope. Phase4 sau này phải tự chứng minh read-only dry-run, data quarantine/counts/media/URL mapping, idempotency và rollback; Phase5 kiểm staging SEO/security/browser/performance; Phase6 production backup/cutover. Không triển khai importer, transformer hoặc chức năng tương lai để đóng giả risk trong task này.

## 16. Phase 4 Readiness

| Gate | Current evidence |
| --- | --- |
| Stable schema/normalized variants/FKs/SKU/money | PASS after additive CHECK fix |
| Inventory/order/snapshot/history/customer integrity | PASS including real concurrency |
| Native admin +frontend core HTTP integration | PASS |
| Legacy design mapping đủ cụ thể cho tooling | PASS design contract; actual import chưa có theo scope |
| Full regression | PASS110/656 +5JS |
| No unresolved High security finding in candidate runtime dependency stack | **BLOCKED RR-30/RR-34**, mitigations chưa đủ để clear strict gate |
| Fresh required browser core verification | **BLOCKED RR-28**, provider unavailable |

Để đổi readiness: (1) xác minh compatible supported remediation cho RR-30/RR-34 rồi chạy audit/auth/mail/domain regression; (2) có browser hoạt động, chạy và lưu fresh desktop/mobile core +shared-image UI regression. Không cần làm Phase4 import để hoàn thành hai điều kiện này. Không tự bắt đầu Phase4 sau khi có test PASS.

PHASE 4 STATUS: NOT READY
