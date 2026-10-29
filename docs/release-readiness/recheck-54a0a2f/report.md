# Phase 6 release readiness — recheck of 54a0a2f

Ngày 29/10/2026. **PHASE 6 STATUS: NOT READY**. Phạm vi: sửa và xác minh release gate; không cutover, đổi DNS/docroot, thay CI3, final production migration hoặc truy cập hosting.

## 1. Executive Summary

Baseline Git `54a0a2f95c447943fcc57d8d66c69512f3e92bce`, branch `feature/develop`, worktree sạch lúc bắt đầu. Các thay đổi của lần recheck này chưa commit. Stack giữ nguyên Exment 7.0.0 / Laravel 12.70.2 / admin-core 1.0.1 / PHP 8.3.36 / MariaDB 10.11.19; local Docker, main 8000 và migrated staging 8002. Không nâng package hoặc thay schema.

Phát hiện và sửa **P6-B07**, lỗi kiểm chứng ledger phục hồi offline: có thể chấp nhận sửa snapshot tài chính/contact của đơn cũ, history live không nhất quán, hoặc chỉ dẫn xử lý đơn live cho imported history. Đây là lỗi công cụ hỗ trợ rollback, không phải lỗi tính tiền trong live checkout. Năm tình huống âm được reproduce trước fix; bổ sung sáu test gồm cả trường hợp hợp lệ customer FK trở thành NULL.

Fresh regression **164 PHP tests / 1.062 assertions**, 0 failed/error/skipped; **5 JS**, **47 browser**, **24 responsive**, **13 bước E2E**, **5 security HTTP** PASS. Composer/npm audit 0. Local backup/restore và offline recovery ledger PASS. Ba gate production gốc vẫn thiếu bằng chứng thực tế; không chuyển chúng thành accepted low risk.

## 2. Original Phase 6 Blockers

[Register đầy đủ](blockers.csv), [bản máy đọc](blockers.json). Các cột bao gồm ID, area, severity, nội dung, evidence, impact, verification, final status, required fix và verification to close.

| Blocker ID | Area | Severity | Verification / current status | Release impact |
|---|---|---|---|---|
| P5-B01 / RR-39 | Backup / Rollback | High | PARTIALLY RESOLVED → BLOCKER | Supplied/local backup verified; chưa có backup production hiện hành và CI3/routing restore proof |
| P5-B02 | Deployment / Configuration / Security | High | PARTIALLY RESOLVED → BLOCKER | Chỉ biết DirectAdmin; chưa xác minh PHP/web stack/docroot/TLS/storage/cron/log thực tế |
| P5-B03 | Migration / Rollback | High | PARTIALLY RESOLVED → BLOCKER | Chưa có final-source/freeze/changed-row/candidate provenance receipt |

## 3. Blocker Verification

[Input hashes](inputs.json) ghi nhận Master Plan, Phase 0–5, consolidated review, readiness matrix, risk/security/SEO/test/browser/responsive reports, reconciliation, production runbook, rollback và backup. Đây là hash đầu vào trước các cập nhật báo cáo lần này. [Baseline](baseline.json), [target profile](target-profile.json), source review và runtime evidence mới được dùng thay vì chỉ đọc kết luận cũ.

Người dùng đã xác nhận “DirectAdmin, chưa có hồ sơ”; không có export/profile/final source mới trong đầu vào task. Không hỏi lại hoặc tự lấy hosting backup đã được hoãn. Ba blocker còn đúng ở phạm vi production. Local phần nào đã giải quyết được ghi riêng, không coi thiếu bằng chứng là false positive.

RR-30/RR-34 ALREADY RESOLVED: dependency audit mới vẫn sạch. P6-B04 scheduler, P6-B05 logging và P6-B06 derived-view portability ALREADY RESOLVED; fresh execution/restore xác nhận lại. P6-B07 NEEDS FIX tại lúc reproduce, final RESOLVED sau sửa/test. [57-risk register](risk-register.json) chỉ dùng RESOLVED, FALSE POSITIVE, ACCEPTED LOW RISK, DEFERRED NON-BLOCKING hoặc BLOCKER.

## 4. Fixes Implemented

`app/Services/Release/OrderDelta.php` kiểm immutable financial/contact/identity snapshots của existing order; đối chiếu trạng thái với history cuối; kiểm chain live bằng `OrderStatus::canTransitionTo`; imported history luôn có chỉ dẫn `KEEP_IMPORTED_HISTORY_DO_NOT_FULFILL_OR_RESTOCK`. FK customer được phép SET NULL vì contact snapshot vẫn độc lập.

`tests/Feature/ReleaseOrderDeltaTest.php` bổ sung sáu test. [Reproduction](delta-reproduction.json): năm negative cases từng lọt trước fix nay reject; positive nullable-customer case PASS. Class này có 12 tests / 22 assertions; full suite có 164 / 1.062. [Filesystem/checksum guards](delta-guards.json) tiếp tục reject overwrite, sai SHA và output public.

Collectors mới chuẩn bị private copies để ghi evidence vào thư mục recheck riêng, giữ nguyên evidence lịch sử. [Collector provenance](collector-provenance.json) ghi hash nguồn/bản thực thi. Không sửa vendor, migration cũ, domain writes, importer hoặc public checkout. Code/assets sau fix được seal riêng trong [checkpoint](code-checkpoint.json); private archives, raw logs, screenshots và credentials không vào Git.

## 5. Database / Migration Fixes

Không có schema fix hoặc hand-edit dữ liệu. [Main audit](main.json) và [stage audit](stage.json): 37 checks mỗi DB PASS; không orphan/FK mismatch, SKU/slug duplicate, âm stock, invalid price/status, thiếu item/snapshot/history hoặc sai total. Hai historical NULL catalog links và 255 unknown legacy SKUs giữ đúng dữ liệu được duyệt.

[Reconciliation](reconciliation.json) PASS cho supplied snapshot. Stage: 8 categories, 115 products, 120 variants/inventories, 347 gallery rows, 11 article categories, 67 articles, 255 historical orders/items/histories, 21 banners, 8 settings. Main gộp: 14 categories, 121 products, 142 variants/inventories, 356 gallery rows, 13 article categories, 72 articles, 258 orders, 24 banners, 8 target settings. Cả demo và migrated data được giữ.

493 invalid historical orders, 2 ambiguous banners và 2 legacy identities là loại trừ có quyết định, bản gốc giữ private. Một missing article image được user chấp nhận fallback; năm media aliases đã xử lý. Không có unexplained loss hoặc queue vô chủ mới. Main 785 / stage 775 media references đều tồn tại; không ảnh trên 1 MB, lớn nhất 626.578 bytes. [Source preservation](source-preservation.json) chứng minh toàn bộ 98 main base tables không thay đổi checksum/count/schema; stage vẫn 255 historical orders. Không chạy `legacy:migrate` trong task.

## 6. Security Fixes

P6-B07 được đóng bằng fail-closed validator tại recovery layer, không thay authentication/checkout. [Security HTTP](security-http.json), full tests và [source scan](source-security.json) bao phủ CSRF 419, session rotation/logout invalidation, upload PHP giả ảnh, public/admin order ownership. Role/direct URL/unauthorized, SQL bindings, sanitized raw HTML, upload re-encode, price/quantity tampering và production error redaction được regression.

Composer và npm audit không có advisory. Secret scan là kiểm pattern của source hiện tại và Git history, không phải chứng nhận pen-test đầy đủ. Không công bố matched values. Production-mode preflight/log probe chạy trên QA local xác nhận APP_DEBUG false, secure-cookie configuration, info/error logging; cookie Secure/TLS và private file/executable-upload protection trên DirectAdmin thật vẫn thuộc P5-B02.

## 7. SEO Fixes

Không cần sửa SEO source mới. [SEO HTTP scan](seo.json): **201 actual legacy URLs → một HTTP 301 → đúng 200 target**, 221 sitemap targets, không chain/loop hoặc broken internal asset/link phát hiện trong scan. [Legacy source comparison](migration-seo.json) kiểm title/slug/meta cho 201 records. Canonical/meta/robots/sitemap/structured data được kiểm lại; staging noindex, search noindex, sitemap không chứa draft/admin/checkout.

Chưa xác nhận redirect/HTTPS/APP_URL tại ingress production; đó là target verification P5-B02, không phải PASS production nhờ localhost scan.

## 8. Frontend / Browser Fixes

Không có lỗi frontend cần source fix trong recheck. [Browser](browser-acceptance.json): installed Chrome Desktop 18, Edge Desktop 18, Chrome mobile viewport 11 cases PASS; không console/page/server error. [Responsive](responsive.json): 24 cases trên desktop/tablet/mobile, sáu core pages, menu/form/gallery/variant layout và baseline alt/labels/overflow PASS.

Mobile là emulation trên installed desktop Chrome, không physical Android certification. Safari/iOS NOT TESTED vì không có thiết bị/runtime. Browser baseline yêu cầu đã chạy thực tế; không gắn PASS cho Safari/iOS.

## 9. Order / Inventory Verification

[Rollback rehearsal](rollback-rehearsal.json): stock physical 10, đặt 2 → physical 10 / reserved 2 / available 8. Giá catalog 100.000 → 150.000; snapshot đơn cũ vẫn 100.000. Cancel → 10 / 0, lặp cancel bị reject. Đơn thứ hai 150.000 × 2 đi SHIPPING → physical 8 / reserved 0. History append-only và transition rule PASS.

Full tests bao phủ server-authoritative totals, invalid/inactive variant/product, oversell, zero/negative quantity, duplicate checkout/request concurrency, immutable order item snapshots và backend permissions. Không dùng browser price/total/stock làm authority. Không phát hiện live order/inventory release blocker.

## 10. Backup Verification

[Backup matrix](backup-matrix.json), [fresh DB proof](backup-verification.json): **BACKUP VERIFIED cho supplied/local artifacts**.

| Artifact | Timestamp / bytes | Verification |
|---|---|---|
| bancay.zip | mtime 2026-10-27T13:38:14Z / 587.368.474 | SHA-256, all-entry CRC; 3.321 source files, 1.843 upload files |
| caybonmua_db.sql | mtime 2026-10-27T13:52:34Z / 1.762.723 | SHA-256, restore 12 tables / 980 rows, source checksums unchanged |
| bancay-20261029T025249Z.sql | 2026-10-29T02:52:49Z / 2.159.445 | SHA-256, all 98 tables restore/count/checksum/schema/CHECK TABLE PASS |
| public-media.tar.gz | 2026-10-29T02:53:01Z / 115.464.002 | SHA-256, restore all 787 files and hashes PASS |
| current code/assets archive | 2026-10-29T03:07:13Z / 11.065.551 | [Latest seal](code-checkpoint.json), 1.236 files/member hashes PASS |

SHA-256 mới của DB: `568cd618bd0595f856f5a399428960eb3bccbf63811458b216c8f701db099157`. File private tồn tại, readable, mode 0600; không commit SQL/media/archive. Timestamp legacy là artifact mtime, không suy diễn thời điểm production export. Latest code seal thay initial code archive trong backup matrix sau fix.

P5-B01 còn BLOCKER vì các proof này không chứng minh production freshness/coherent DirectAdmin configuration/CI3 runtime restore hiện tại.

## 11. Rollback Verification

[Window restore](window-restore.json): fresh portable post-window backup phục hồi **98 tables + 2 derived native views** sang owned scratch DB; schema/data/count chính xác, restored snapshot và offline ledger trùng live QA export. [Offline export](offline-delta-export.json) lặp byte-identical, không bootstrap Laravel hoặc cần DB, không tự ghi CI3/stock. Temporary DB/user đã dọn.

Nếu lỗi sau 10 phút: freeze Laravel tại ingress/app và worker/cron; giữ CI3 writes frozen; chụp SQL/media/logs/window receipt; xuất ledger private từ cutoff/window đã xác minh; Data Owner đối soát stable reference, cancelled/shipped/real orders và physical/reserved stock; Hosting Operator khôi phục routing/SSL đúng bản lưu; smoke CI3; chỉ mở legacy writes khi không còn order/stock delta chưa có owner/correspondence. Không overwrite Laravel DB chứa real orders bằng backup cũ. Imported historical orders chỉ archive, không fulfil/restock.

[Rollback plan](../../rollback-plan.md) và [runbook](../../production-runbook.md) có sequence/data delta. Actual CI3 routing/restore và final-source receipt chưa kiểm được; rollback production chưa READY.

## 12. Regression Results

| Check | Result | Evidence |
|---|---|---|
| Phase 1–5 + release tests | 164 passed / 1.062 assertions / 0 failed/error/skipped | [tests](tests.json) |
| Recovery delta class | 12 passed / 22 assertions | tests.json, delta-reproduction.json |
| JavaScript | 5 passed | [asset checks](checks-assets.json) |
| Pint / Composer validate strict / platform / Exment requirements / Blade | PASS | [PHP checks](checks-php.json) |
| Production frontend build | PASS | checks-assets.json |
| Composer / npm audit | 0 advisories/abandoned; 0 vulnerabilities | [Composer](composer-audit.json), [npm](npm-audit.json) |
| Main/stage integrity / source reconciliation | PASS | main.json, stage.json, reconciliation.json |
| Performance baseline | 8 local pages PASS, no sampled query >100 ms | [performance](performance.json) |

Performance là warm in-process kernel/Blade/DB samples, không production capacity/TLS/network/Web Vitals benchmark. Native admin Product/Order được đo. Không tối ưu micro-level hoặc thêm cache vào stock/checkout. [Final verification](verification.json) kiểm evidence/source/cleanup/historical preservation; PASS ở đây không là production approval.

## 13. E2E Results

[13 bước continuous E2E](admin-frontend-e2e.json) PASS: Exment login → category/product/image/options/variants/price/stock → frontend chọn variant/cart/checkout → admin mở order/snapshot → đổi status/history → đổi catalog giữ snapshot → cancel release reservation. Chỉ tạo dữ liệu trên disposable QA clone; main và approved stage không nhận synthetic order mới.

[Manual matrix](manual-matrix.csv) có Admin login, Product/Variant CRUD, Inventory, Order workflow, Product frontend, Cart add/update/remove, Checkout, SEO 301 và mobile checkout với evidence. HTTP/browser automation là thực thi UI thật; không suy diễn remote hosting/manual device PASS.

## 14. Migrated Data E2E

Chrome/Edge/mobile core flow sử dụng migrated product legacy ID 4, SKU `BC-L4-V1`, category/image/variant/80.000 price/stock/SEO, tạo order mới trên QA clone và đối chiếu server snapshots. [Browser data verification](browser-data-verification.json) PASS. Source historical orders không gọi live-order actions, không reserve/mail lại. 8002 vẫn hidden, 8000 vẫn giữ demo + migrated catalog theo yêu cầu.

## 15. Remaining Risks

Ba release blockers High: P5-B01/RR-39, P5-B02, P5-B03. Không có Critical/High application security hoặc order/data integrity blocker còn mở theo source/test hiện tại. Không tuyên bố không còn High release blockers vì ba gate trên chưa đóng.

Các mục deferred có owner/impact/mitigation/target trong [register](risk-register.json): RR-19 granular permissions, RR-35 optional stock ledger/expiry/return workflow, RR-36 editor/cache/CDN backlog, RR-38 extended device/load/CSP/pen-test scope. RR-43 là Medium có quyết định loại trừ dữ liệu và archive, không phải chấp nhận unexplained data loss; final-source reconciliation vẫn bắt buộc theo P5-B03. Native reset SMTP NOT TESTED; order/admin email notification không phải feature core hiện tại, sync queue không cần worker. Actual native scheduler cron vẫn cần xác minh tại target.

## 16. Accepted Risks

Không nhận acceptance mới trong task. Giữ các acceptance đã có:

| ID | Reason / impact | Mitigation | Target |
|---|---|---|---|
| RR-11 | Một legacy image thiếu, user đã cho bỏ qua; visual fallback | Giữ originals, fallback rõ; không che hàng loạt ảnh thất lạc | Duy trì quyết định khi chuẩn bị release |
| RR-20 | Xóa native identity có thể để history actor NULL | Giữ order/status snapshots và history append-only; audit account lifecycle | Review khi thay identity hoặc nâng Exment |
| RR-40 | File session/cache và reference cleanup ở một instance | Local/single-instance contract; không dùng chung write storage legacy; re-evaluate trước scale/import đồng thời | Trước thay topology |

Acceptance này không bỏ qua các production gates hoặc tự cho phép READY WITH ACCEPTED LOW RISKS.

## 17. Release Readiness Matrix

PASS bên dưới chỉ áp dụng build/dataset/local rehearsal đã nêu. [CSV](readiness-matrix.csv).

| Area | Status | Evidence | Release Blocking |
|---|---|---|---|
| Architecture | PASS | Relational domains, scoped offline CLI | No |
| Database | PASS | main/stage audits, 98-table restore | No |
| Migration | PASS | Supplied-snapshot reconciliation | P5-B03 final source |
| Exment Admin | PASS | Full tests, 13-step E2E | No |
| Product/Variant | PASS | Browser, ownership/option/SKU tests | No |
| Inventory | PASS | Reserve/cancel/ship/race tests | No |
| Order | PASS | Snapshots/history/totals + recovery guards | No |
| Cart/Checkout | PASS | Core E2E, tampering/idempotency | No |
| Security | PASS | Application audit 0, source/HTTP tests | P5-B02 actual host |
| SEO | PASS | 201 redirects / 221 targets | P5-B02 ingress |
| Browser | PASS | Chrome/Edge/mobile viewport 47 | No baseline failure; Safari/iOS unavailable |
| Responsive | PASS | 24 cases | No |
| Performance | PASS | 8-page local baseline | No measured local blocker |
| Email | N/A | No core order/admin mail; native SMTP NOT TESTED | Not automatic core blocker |
| Queue/Cron | PASS | Sync queue; both native commands executed | P5-B02 installation |
| Backup | FAIL | Local/supplied VERIFIED; current production absent | P5-B01 |
| Rollback | FAIL | Local data delta verified; CI3/routing unverified | P5-B01/P5-B03 |
| Production Runbook | FAIL | Procedure prepared; target/source receipts absent | P5-B02/P5-B03 |

## 18. Final Phase 6 Readiness

**PHASE 6 STATUS: NOT READY**

| Blocker ID | Lý do | Cần bổ sung / sửa | Cần test để đóng |
|---|---|---|---|
| P5-B01 / RR-39 | Chưa có coherent backup production/DirectAdmin và CI3 runtime/routing restore proof | Hosting Operator cung cấp SQL/files/uploads/config/cron/DNS/SSL backup hiện hành, timestamps/size/SHA-256 và exact old routing recovery procedure | Restore CI3 cô lập; schema/media/runtime smoke; thử routing rollback theo profile đã cung cấp |
| P5-B02 | Chưa có actual target profile | Hoàn thiện secret-free DirectAdmin profile, xác nhận PHP CLI/web, DB/web server, public docroot, TLS/proxy/cookies, storage non-execution, cron/log paths/core config | Restricted target preflight/HTTPS/private-file/upload/login/checkout/cron/logging smoke; không cần đổi DNS production |
| P5-B03 | Chưa chứng minh final-source freshness, freeze và changed-existing-row reconciliation | Fresh consistent source receipt, cutoff/freeze plan, combined candidate provenance; giữ demo và exclusions đã duyệt, không xem SKIP-only importer là delta sync | Changed-row/count/hash reconciliation trên source mới; isolated candidate E2E/restore/window ledger trước final release approval |

Chưa chạy final production migration. Không làm mới production source/freeze hay đổi routing trong task. Kết quả đã đóng mọi lỗi tái hiện được trong công cụ/source local ở lần review này; phần còn lại là gate bằng chứng môi trường/dữ liệu production, không thể chứng nhận từ Docker local.
