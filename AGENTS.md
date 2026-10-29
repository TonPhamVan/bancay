> Latest release readiness review: [report](docs/release-readiness-report.md), [6-item blocker register](docs/release-readiness/blockers.csv), [56-risk register](docs/release-readiness/evidence/risk-register.json).158 PHP/1054 assertions +5JS,47 Chrome/Edge/mobile-viewport,24 responsive,13-step E2E PASS;audits0. Fixed native cron omission,production audit log level andcross-DB workflow-view restore. Portable98-table restore+2 native views+offline new-order ledger verified. Target DirectAdmin confirmed,profile absent. **PHASE 6 STATUS: NOT READY** — P5-B01 current production backup/CI3 routing,P5-B02 actual target config,P5-B03 final-source/candidate receipt. No DNS/docroot/CI3/final migration. Older statements below are historical.

> Release tooling: source writes only on owned QA/test clones. store:rebuild-derived-views requires exact DB confirmation andowned local restore/test DB orproduction maintenance;uses official native APIs only. Portable dumps contain all BASE TABLES withoriginal view definitions separately preserved. Offline export_delta.php reads checksum-bound private snapshots,no DB/CI3 writes. Keep DirectAdmin credentials/SQL/ledger private;do not claim actual target verified from local preflight.

> Latest Phase5: [report](docs/phase-5-report.md), [readiness](docs/phase-5/readiness.md), [current53-risk register](docs/phase-5/evidence/risk-register.json).149 PHP/1033 assertions +5JS,47 Chrome/Edge/mobile cases,24 responsive cases,13-step admin/frontend/admin E2E and5 security HTTP cases PASS;201 redirects/221 sitemap targets PASS. Composer/npm audit0 after isolated symfony/mime7.4.22 patch. Added publication sitemap/environment robots,headers/Host/log masking,scoped banner placements and320px gallery fix. Main98 base tables and all17 domain tables unchanged;both datasets/source/backups preserved. **PHASE 6 STATUS: NOT READY**: P5-B01 production backup/rollback,P5-B02 target TLS/runtime/storage/SMTP,P5-B03 final frozen-source/combined release artifact rehearsal. No production/DNS/final migration. Older statements below are historical.

> Phase5 operations: use scripts/phase5/checks.py and dedicated Phase5 evidence; QA clone only for writes. No replay of merge/import or historical collectors. Production legacy:migrate remains intentionally forbidden; read production-runbook.md/rollback-plan.md before future Phase6 work. Keep accepted category/exclusion/image-fallback decisions; hosting backup remains user-deferred.

> Current local website: **demo + migrated data combined at http://localhost:8000**. [UI mapping/merge report](docs/ui-mapping-report.md).121 products/142 SKUs/72 articles/258 orders;120 products/71 articles visible locally, noindex. Original demo rows, source8002 and backups preserved.137 PHP/860 assertions,44 combined browser cases,0 UI mapping mismatches,201 local redirects PASS.13 inactive banner placements remain frontend work. Previous pure-demo/hidden8000 and pre-merge counts below are historical.

> Current stack: **Exment7.0.0 / Laravel12.70.2 / admin-core1.0.1**, PHP8.3.36. [Upgrade report](docs/exment-v7-report.md). Composer audit PASS (0 advisories/0 abandoned), npm audit0; RR-30/RR-34 RESOLVED.132 PHP/814 assertions +5JS and47 browser cases PASS. Both local DBs upgraded;17 business tables unchanged; migrated catalog still hidden. Older v6 pins/audit failures/exceptions below are historical. No production release.

> Latest approved-review application: [report](docs/phase-4/review-application.md). All category decisions applied:115 products/120 SKUs/67 articles, still hidden.5 missing media references resolved,1 fallback accepted;493 historical orders/2 banners/2 legacy users intentionally excluded.201/201 URL targets mapped.132 PHP/815 assertions +5JS and44 browser cases PASS. Dependency audit remains FAIL under local-only exception; Phase5 READY WITH RISKS. Earlier counts/queues below are historical.

> Latest risk closure: [report](docs/phase-4/risk-closure.md). Content URLs repaired in124 fields after verified87-table backup/restore;128 PHP/783 assertions +5JS and37 browser cases PASS. RR-46 RESOLVED. Remaining dependency/source-data/content/identity review risks are recorded; Phase5 READY WITH RISKS, public release blocked.

# Quy tắc làm việc trong repository bancay

> Current Phase4: [report](docs/phase-4-report.md). Isolated rehearsal completed:113 products/118 SKUs/255 historical orders;126 PHP tests/770 assertions +5JS PASS; native/frontend browser PASS. RR-30/RR-34 temporarily accepted by user; audit still FAIL, production blocked. Phase5 READY WITH RISKS. Earlier NOT READY/import-not-started statements below are historical.

> Current authorization: Phase4 isolated legacy migration is explicitly requested; RR-30/RR-34 temporarily accepted by user, not resolved. See docs/phase-4/authorization.md. Preserve prior readiness changes; no production/cutover or audit ignore.

> Latest readiness closure: [report](docs/readiness-closure-report.md). Browser Chrome/Edge/mobile PASS; backup restore VERIFIED;114 PHP/670 assertions +5JS PASS. RR-30/RR-34 remain BLOCKED; Phase4 NOT READY. Post-run local metadata differences recorded for review. Prior browser blockers below are historical.

## Nguồn yêu cầu

- Đọc `docs/master-plan.md` trước khi thực hiện mỗi phase. Đây là Master Plan do người dùng cung cấp; không tự ý sửa nội dung gốc.
- Xem `docs/phase-0.md` và báo cáo phase mới nhất để xác định tiến độ bằng bằng chứng thực tế.
- Chỉ định mới nhất: **Risk consolidation Phases1–3 trước Phase4**, theo `docs/risk-review/request.md`. Đọc `docs/risk-review-report.md` và `docs/risk-register.md`; kết quả review được đóng gói trong commit riêng theo chỉ định người dùng. Không bắt đầu Phase4 trong review. Latest user instruction explicitly upgrades to stable Exment7.0.0/Laravel12.70.2; see docs/exment-v7-report.md. Do not downgrade to v6, use dev branches or deploy production. Earlier risk-review phase instructions are historical.
- Foundation local Laravel12.70.2/Exment7.0.0 (upgraded from10.50.4/6.2.15);17 domain tables columns/FKs/indexes không đổi, review thêm3 money CHECKs qua migration additive2026_10_28_000002. Phase 2 admin đã triển khai; đọc `docs/phase-2-report.md`, `docs/exment-admin.md`, `docs/product-management.md`, `docs/order-workflow.md`, `docs/permissions.md` cùng tài liệu Phase 1 trước thay đổi tiếp. Phase3 storefront đã kiểm96 PHP tests/556 assertions +5 Node tests; desktop/mobile core checkout pass. Review mới:110 PHP tests/656 assertions +5JS PASS, nhưng Phase4 NOT READY do High email dependency remediation +fresh browser core verification BLOCKED; không tự bắt đầu Phase4. Đọc thêm `docs/frontend-architecture.md`, `docs/cart-checkout.md`, `docs/seo-frontend.md`.
- Dependency audit FAIL6 entries/5 unique/2 packages. TokenGuard Critical là FALSE POSITIVE cho official exact10.50.4 code đã hash/test; High email advisory chưa compatible remediation, runtime mail log-only/app CRLF rules không phải package fix. Xem docs/risk-review/dependency-assessment.md. Người dùng chấp nhận v6 tạm thời; chỉ localhost/synthetic data, không deployment. Không thêm audit ignore hoặc tắt policy toàn project để che lỗi. Lock/risk exception được ghi trong architecture report; v7 upgrade phải chạy lại tests/migrations/auth/audit.
- Legacy tại `E:\Workspace\GitHub\bancay_legacy`: ZIP + `caybonmua_db.sql`. Đã kiểm chứng bản sao local, restore SQL cô lập, lập inventory và Git baseline đã redaction; xem `docs/phase-0.md`. Không coi đó là chứng nhận full production backup hoặc runtime tests.
- Người dùng cho phép chủ động thiết kế mapping trạng thái: policy `0→NEW`, `1→CONFIRMED`, giữ legacy_status, không suy diễn completed. Hosting/DirectAdmin/cron/DNS/SSL backup bổ sung được người dùng hoãn: “backup sau”; không hỏi lại hay tự truy cập hosting.
- Phase 0 cho phép tiếp tục Phase 1 ở trạng thái READY WITH RISKS; giữ nguyên bằng chứng và giới hạn của Phase 0.
- Chỉ làm scope phase đang được thực hiện. Nếu người dùng chỉ định phase khác, ghi rõ điều kiện còn thiếu và ảnh hưởng; không ngầm xác nhận các phase trước đã hoàn tất.

## Kiến trúc bắt buộc

- Modular Monolith: Laravel + Exment + Blade + Alpine.js + MySQL/MariaDB, một ứng dụng cho một cửa hàng.
- Exment phục vụ admin; frontend public dùng Blade. Không thêm React/Vue/Next.js, marketplace hoặc microservice khi chưa có yêu cầu thực tế.
- Route → Controller → Service/Action → Domain/Model → Database. Controller mỏng; business logic tái sử dụng được từ web và API; Blade không truy cập database.
- Các domain: Catalog, Commerce, Customer, Content, System. Không triển khai trước chức năng của phase sau.
- Product variants là bản ghi riêng với SKU, options, giá, giá sale, tồn kho và trạng thái; không gộp toàn bộ variants vào JSON.
- Order items snapshot tên sản phẩm, variant, SKU, đơn giá, số lượng, thành tiền. Giữ lịch sử trạng thái đơn.
- Product/Category/Article có kế hoạch SEO từ đầu; migration phải có mapping URL cũ → mới và redirect 301.
- Trước khi cài foundation, kiểm tra tương thích phiên bản Laravel, Exment, PHP và database từ nguồn chính thức; ghi lại quyết định, không mặc định mọi phiên bản mới nhất tương thích nhau.
- Báo cáo vấn đề kiến trúc trước khi thay đổi nền tảng; ghi quyết định quan trọng trong tài liệu dự án.

## Dữ liệu và bảo mật

- Legacy chỉ là nguồn dữ liệu. Không copy nguyên schema hoặc sửa lớn hệ thống cũ.
- Không sửa production; giữ source cũ và backup phục vụ rollback. Không chạy import/migration lên production trong quá trình phát triển.
- Không commit credential, `.env` thực, dump database, dữ liệu khách hàng hoặc bản backup vào repository.
- Source legacy đưa vào Git phải được kiểm tra secret và dữ liệu nhạy cảm; dùng repository riêng có quyền truy cập phù hợp. Bản backup gốc giữ ngoài Git.
- Dùng Form Request, Eloquent/Query Builder với parameter binding, Hash, Policy/Gate, CSRF và validation upload. Không dùng MD5 cho mật khẩu mới hoặc nối SQL từ input.
- Không ghi nhận backup thành công nếu chưa có artifact và kiểm chứng khả năng khôi phục; không ghi mapping là đã xác minh nếu chưa đọc nguồn.

## Kiểm tra và báo cáo

- Chạy kiểm tra phù hợp với thay đổi; không báo hoàn tất nếu chưa kiểm tra thực tế.
- Báo cáo mỗi phase gồm: việc đã thực hiện; file/module thay đổi; kiến trúc; thay đổi database; test đã chạy; kết quả; vấn đề/rủi ro; technical debt; việc cho phase tiếp theo.
- Dùng `NOT TESTED`, `BLOCKED`, `MANUAL TEST REQUIRED` cùng lý do cho phần chưa kiểm chứng. Phân biệt kiểm tra tài liệu với test ứng dụng, migration và restore.
- Khi phase hoàn tất hoặc thay đổi, cập nhật trạng thái trong README và hướng dẫn này theo bằng chứng; không tự động chuyển phase khi đầu vào chưa đủ.

## Foundation commands và invariants

- Trong WSL: `bash scripts/setup-local.sh`, sau đó `docker compose up -d app`. Make chỉ tiện ích optional; PHP/Composer chạy trong container.
- `docker compose run --rm --no-deps app php artisan test` dùng riêng bancay_test; không bỏ guard của Tests/CreatesApplication hoặc trỏ destructive test vào operational/legacy DB.
- Dùng `vendor/bin/pint --test`, `composer validate --strict`, `composer check-platform-reqs`, `php artisan exment:checkrequire` trong app container. Audit exit1 phải được báo riêng, không đổi thành PASS.
- Dữ liệu native chỉ ở relational tables; không tạo mirror Exment CustomTable JSON. Exment page extensions phải gọi Actions cho writes, dùng built-in identity và Gate abilities; không generic-update order status/stock/snapshots.
- Money là integer VND; CONTACT không checkout; reservations cùng transaction; item snapshots immutable và status history append-only. Legacy history dùng đường import riêng, không live-order actions.
- Cấu hình/credential local tự sinh, private; không reset initialized Exment qua exment:install khi chỉ muốn chạy migrations. Dùng guarded store:install-local; không seed demo ở production.
- Phase 2 dùng source `exment-plugins/` → runtime `storage/app/plugins/` qua `store:configure-admin`; không đặt versioned source ở app/Plugins vì native v6 migration sẽ di chuyển thư mục đó. Không sửa vendor để nối domain tables.
- StoreAdminGuard chạy trước native permission responder; application Handler giữ đúng HTTP 403/404/405/419/422 cho store plugins. Role bridge dùng direct native User membership; presets không có System/Manage Plugin/Manage Setting grants. Native users/role routes chỉ system administrator.
- Upload phải decode/re-encode JPEG/PNG/WebP qua GD WebP build, tên do server sinh và public storage. Không nhận path từ admin request. Dùng options/variant/stock/status services và owner scopes; không generic update order totals/history/reservations.
- Tests có real process contention trên bancay_test. Không chạy song song nhiều bộ destructive tests trên cùng DB test. Raw logs và temporary synthetic account credentials giữ private, không commit; hoàn tất UI test phải thu hồi test grants/rotate password, giữ local-admin không đổi.

## Frontend Phase3 invariants

- Public views không query DB; `Collection::where` trên DTO navigation là lọc in-memory, không phải SQL. Publication phải kiểm toàn bộ category ancestors; không expose inactive variants/unpublished content.
- Tailwind/Vite bundle chỉ public layout, không thay Exment CSS. `docker compose run --rm --no-deps assets` chạy npm ci/build bằng Node24; `npm test` qua assets service. Chỉ seed StorefrontDemoSeeder local/testing; ảnh là synthetic AI, không coi là catalog thật.
- Cart lưu IDs/quantity trong session, current prices server. Checkout reuse CreateOrder, lock attempt + variants, compare quote, reserve/snapshot/history cùng transaction. `checkout_attempts` là technical table additive ngoài17 domain tables. Không dùng session token/button đơn lẻ thay database idempotency.
- Success route yêu cầu exact session grant, kể cả native admin login không bypass public ownership. Không thêm lookup bare ID/phone-only. Buy Now giữ các cart lines hiện có. Shipping0 là baseline chưa có calculator/payment, không đổi thành quảng cáo miễn ship.
- Local public noindex mặc định, canonical dùng APP_URL. Generic legacy_id resolver chỉ là foundation; Phase4 mappings/import và Phase5 SEO validation còn phải thực hiện riêng.
- Collector Phase3: `scripts/phase3/collect_evidence.py`; verification artifacts: `scripts/phase3/verify_artifacts.py`. Không chạy collectors Phase0/1/2 để ghi đè historical evidence. Raw logs, sessions, credentials và originals giữ private/ignored. Browser matrix ngoài IAB responsive chưa được kiểm chứng.

## Risk review invariants

- Không sửa migrations cũ; tiền VND PHP64-bit<=9223372036854775807, FIXED price non-NULL; migration mới preflight3 tables trước DDL. Invalid rows quarantine, không truncate/reprice.
- Shared public image cleanup sau commit dùng ImageStorage.deleteIfUnused, bảo vệ product/category/article-category/article/banner/logo references. Cleanup/log failure giữ old orphan, không undo committed image. Import phải maintenance/isolation; không bypass reference contract.
- Mapping hiện tại docs/legacy-mapping.md: DRAFT/INACTIVE mặc định, missing/orphan/collision quarantine, history/customer/admin import OFF đến scope được duyệt; không live-order Actions cho history. Không triển khai importer trong risk task.
- Fresh review browser provider lỗi constraints trước navigation; HTTP integration +JS PASS không phải browser E2E. Không ghi đè historical Phase0/1/2/3 evidence; review collector riêng scripts/risk-review/collect_runtime.py.

## Phase4 operations

- Latest explicit user authorization accepts temporary RR-30/RR-34 deferral; do not reinterpret as dependency remediation or public deployment approval.
- Actual migrated staging localhost8002/bancay_phase4_staging uses own account/storage; keep data hidden. Source bancay_phase4_source SELECT-only; never modify original ZIP/SQL/extraction or legacy production.
- Read docs/legacy-migration.md and docs/phase-4-report.md; commands require matching dry-run receipt, verified backups and local Docker db host. --force does not bypass. Existing IDs SKIP; history requires --include-orders and has no live stock/mail effects.
- Private data/reports/media/credentials stay legacy-private/phase4; Git evidence aggregates/IDs/hashes only. QA/repeat clones are disposable, actual stage remains for review. Phase5 is not started automatically.

## Exment v7 current operations

- Official v7 upgrade authorized and completed; docs/exment-v7-report.md and docs/exment-v7/evidence are current. Use ExmentAdminCore\Admin namespace and open-admin assets; no Encore compatibility aliases/vendor patches.
- Composer audit0 advisories/0 abandoned; native Laravel12 email validation replaces the temporary ExmentSafeValidator shim. RR-30/RR-34 exception requirement removed, not bypassed; never reintroduce audit ignores.
- Main8000 and migrated staging8002 run v7, same identities/passwords; both have98 native+domain tables. All17 domain table schemas/checksums unchanged from pre-upgrade backups. Preserve private pre-upgrade backups; do not exment:install/reset initialized DBs.
- Latest tests132/814 +5JS;44 Chrome/Edge/mobile core cases +3 native v7 checks PASS. Native invalid/valid login, admin assets/styles, role restrictions, mail rejection and order/inventory invariants covered.
- Use scripts/exment-v7/verify.py for current artifacts; old phase collectors/verify-foundation.py enforce historical v6 evidence and must not overwrite it. Phase5/6 production readiness still requires its own work; no automatic deployment.

## Current combined local dataset

- Latest user explicitly chose to combine demo and migrated data in one website. Main8000/bancay now121 products/142 variants/72 articles/258 orders;120 products/71 articles published for local preview only. Do not reset, reseed over, delete demo records or replay merge.py.
- Source8002/bancay_phase4_staging remains115/120/67/255, hidden; originals/backups/exclusions preserved. Settings at8000 retain existing demo website values; source settings remain8002/private backups, no auto-brand overwrite.
- ID maps/provenance/verified98-table backups and source SELECT-only reader stored privately under legacy-private/ui-mapping/combined. No credentials/SQL/PII in Git. Merge verified NOOP rerun, original demo field preservation, FK/combination remap, no historical stock side effects.
- Owned admin form entity/newline round-trip and desktop category overflow fixed. Latest verification scripts/ui-mapping/verify.py;137 PHP/860 assertions +44 combined browser cases and full DB↔UI comparison PASS.13 inactive sidebar/content_bottom banner records still lack frontend placements; track RR-26, do not claim100% banner coverage.
- Pure upgrade checkpoint evidence remains historical; Exment v7 verifier accepts the separately verified authorized post-upgrade data merge. No production release/commit/push authorized by this task.
