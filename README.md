> Latest release readiness review: [report](docs/release-readiness-report.md), [6-item blocker register](docs/release-readiness/blockers.csv), [56-risk register](docs/release-readiness/evidence/risk-register.json).158 PHP/1054 assertions +5JS,47 Chrome/Edge/mobile-viewport,24 responsive,13-step E2E PASS;audits0. Fixed native cron omission,production audit log level andcross-DB workflow-view restore. Portable98-table restore+2 native views+offline new-order ledger verified. Target DirectAdmin confirmed,profile absent. **PHASE 6 STATUS: NOT READY** — P5-B01 current production backup/CI3 routing,P5-B02 actual target config,P5-B03 final-source/candidate receipt. No DNS/docroot/CI3/final migration. Older statements below are historical.

> Latest Phase5: [report](docs/phase-5-report.md), [readiness](docs/phase-5/readiness.md), [current53-risk register](docs/phase-5/evidence/risk-register.json).149 PHP/1033 assertions +5JS,47 Chrome/Edge/mobile cases,24 responsive cases,13-step admin/frontend/admin E2E and5 security HTTP cases PASS;201 redirects/221 sitemap targets PASS. Composer/npm audit0 after isolated symfony/mime7.4.22 patch. Added publication sitemap/environment robots,headers/Host/log masking,scoped banner placements and320px gallery fix. Main98 base tables and all17 domain tables unchanged;both datasets/source/backups preserved. **PHASE 6 STATUS: NOT READY**: P5-B01 production backup/rollback,P5-B02 target TLS/runtime/storage/SMTP,P5-B03 final frozen-source/combined release artifact rehearsal. No production/DNS/final migration. Older statements below are historical.

> Current local website: **demo + migrated data combined at http://localhost:8000**. [UI mapping/merge report](docs/ui-mapping-report.md).121 products/142 SKUs/72 articles/258 orders;120 products/71 articles visible locally, noindex. Original demo rows, source8002 and backups preserved.137 PHP/860 assertions,44 combined browser cases,0 UI mapping mismatches,201 local redirects PASS.13 inactive banner placements remain frontend work. Previous pure-demo/hidden8000 and pre-merge counts below are historical.

> Current stack: **Exment7.0.0 / Laravel12.70.2 / admin-core1.0.1**, PHP8.3.36. [Upgrade report](docs/exment-v7-report.md). Composer audit PASS (0 advisories/0 abandoned), npm audit0; RR-30/RR-34 RESOLVED.132 PHP/814 assertions +5JS and47 browser cases PASS. Both local DBs upgraded;17 business tables unchanged; migrated catalog still hidden. Older v6 pins/audit failures/exceptions below are historical. No production release.

> Latest approved-review application: [report](docs/phase-4/review-application.md). All category decisions applied:115 products/120 SKUs/67 articles, still hidden.5 missing media references resolved,1 fallback accepted;493 historical orders/2 banners/2 legacy users intentionally excluded.201/201 URL targets mapped.132 PHP/815 assertions +5JS and44 browser cases PASS. Dependency audit remains FAIL under local-only exception; Phase5 READY WITH RISKS. Earlier counts/queues below are historical.

> Latest risk closure: [report](docs/phase-4/risk-closure.md). Content URLs repaired in124 fields after verified87-table backup/restore;128 PHP/783 assertions +5JS and37 browser cases PASS. RR-46 RESOLVED. Remaining dependency/source-data/content/identity review risks are recorded; Phase5 READY WITH RISKS, public release blocked.

# bancay.vn — Laravel / Exment storefront

> Current Phase4: [report](docs/phase-4-report.md). Isolated rehearsal completed:113 products/118 SKUs/255 historical orders;126 PHP tests/770 assertions +5JS PASS; native/frontend browser PASS. RR-30/RR-34 temporarily accepted by user; audit still FAIL, production blocked. Phase5 READY WITH RISKS. Earlier NOT READY/import-not-started statements below are historical.

> Latest readiness closure: [report](docs/readiness-closure-report.md). Browser Chrome/Edge/mobile PASS; backup restore VERIFIED;114 PHP/670 assertions +5JS PASS. RR-30/RR-34 remain BLOCKED; Phase4 NOT READY. Post-run local metadata differences recorded for review. Prior browser blockers below are historical.

Một Laravel modular monolith cho một cửa hàng. **Phase 3 Blade storefront đã triển khai local:** home/category/search, sản phẩm và variants, guest cart/checkout/order success, articles/contact/SEO. Exment admin Phase2 dùng chung domain/actions; full regression pass.

Stack đang khóa: **Laravel 10.50.4 / Exment 6.2.15 / PHP 8.3.36 / MariaDB 10.11**. Tạm dùng Exment v6 theo chỉ định; nâng v7/Laravel 12 khi có release và yêu cầu cập nhật riêng.

**PHASE 4 STATUS: NOT READY.** Risk review đã sửa money CHECK/shared-image cleanup,110 PHP tests/656 assertions +5JS PASS. Còn High email dependency remediation và fresh browser verification BLOCKED; Composer audit vẫn FAIL. Critical TokenGuard riêng exact code là false positive đã có proof. **Không deploy production / không tự bắt đầu Phase4.** [Final review](docs/risk-review-report.md), [41-risk register](docs/risk-register.md).

## Tài liệu

- [Master Plan nguyên bản](docs/master-plan.md)
- [Báo cáo Phase 0](docs/phase-0.md)
- [Báo cáo Phase 1 — 24 mục](docs/phase-1-report.md)
- [Báo cáo Phase 2 — 23 mục](docs/phase-2-report.md)
- [Báo cáo Phase 3 — 26 mục](docs/phase-3-report.md)
- [Frontend architecture](docs/frontend-architecture.md), [routes](docs/frontend-routes.md), [cart/checkout](docs/cart-checkout.md), [SEO](docs/seo-frontend.md)
- [Exment Admin / cách cài / entity contracts](docs/exment-admin.md)
- [Quản lý sản phẩm](docs/product-management.md), [Workflow đơn hàng](docs/order-workflow.md), [Phân quyền](docs/permissions.md)
- [Kiến trúc và quyết định](docs/architecture.md)
- [Database design / ERD](docs/database-design.md)
- [Exment mapping](docs/exment-mapping.md)
- [Legacy transformation design](docs/legacy-mapping.md)
- [AGENTS.md](AGENTS.md)

## Chạy local (WSL Ubuntu, từ root repository)

Cần Docker Desktop có WSL integration, Docker Compose, Bash và Python3. Không cần PHP/Composer cài trên host hoặc Make.

```bash
bash scripts/setup-local.sh
docker compose up -d app
```

Setup sinh `.env` và password local ngẫu nhiên nếu chưa có; không ghi đè file đang tồn tại. Exment/system identity cũ trong DB mới không bị reset khi chạy lại setup. DB không publish port; HTTP chỉ bind localhost.

- Storefront: [http://localhost:8000/](http://localhost:8000/)
- Admin: [http://localhost:8000/admin](http://localhost:8000/admin)
- Health: [http://localhost:8000/health](http://localhost:8000/health)
- Menu nghiệp vụ: **Tổng quan cửa hàng** hoặc [dashboard](http://localhost:8000/admin/plugins/bancay_dashboard). Với foundation đã cài, rebuild app image rồi chạy `php artisan store:configure-admin` trong container; không chạy lại raw `exment:install`.
- User code: `local-admin`; password trong `legacy-private/phase1/admin.env` (private, ngoài Git), không có password mặc định trong source.
- Muốn bootstrap thủ công: `docker compose run --rm app php artisan store:initialize-local-admin` sẽ hỏi password ẩn. Command chỉ local/testing và không sửa account đã initialize.

Seed **dữ liệu giả** tùy chọn: product,2 options/4 variants/inventories, customer/address/order/history, article/banner/setting và ảnh JPEG tổng hợp:

```bash
docker compose run --rm app php artisan db:seed --class=AdminDemoSeeder
```

Demo storefront có4 sản phẩm/16 variants,4 category,3 bài và ảnh WebP minh họa AI, chỉ local/testing:

```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed --class=StorefrontDemoSeeder
```

96 PHP tests/556 assertions +5 Node tests pass; desktop/mobile guest checkout đã tạo đơn synthetic. [Evidence](docs/phase-3/evidence/verification.json). Chạy collector mới: `python3 scripts/phase3/collect_evidence.py` (test DB riêng, không chạy nhiều suites song song); artifact verification: `python3 scripts/phase3/verify_artifacts.py`. Không rerun collectors cũ để ghi đè evidence lịch sử.

Không có migration/importer nào đọc production legacy DB. Fixture dùng riêng cho local/testing; AdminDemoSeeder tạo ảnh demo còn thiếu và giữ các bản ghi đã có. Không có dữ liệu catalog production đã migrate.

## Kiểm tra

```bash
docker compose run --rm --no-deps app php artisan about
docker compose run --rm --no-deps app php artisan test
docker compose run --rm --no-deps app vendor/bin/pint --test
docker compose run --rm --no-deps app php artisan exment:checkrequire
docker compose run --rm --no-deps app composer validate --strict
docker compose run --rm --no-deps app composer check-platform-reqs
docker compose run --rm --no-deps app composer audit --locked
```

Tests dùng riêng `bancay_test` và từ chối chạy migrations nếu database khác. Full migrate/rollback/remigrate đã test trên MariaDB thật. Nếu config đã cache khi phát triển, clear config trước khi test; guard sẽ fail thay vì drop nhầm operational DB. `composer audit` hiện **exit1 theo các risks đã ghi nhận**, không phải check PASS.

Frontend build chạy bằng Docker Node24; setup đã gồm npm ci/build. Khi đổi Blade/CSS/JS: `docker compose run --rm --no-deps assets`. Node picker tests: `docker compose run --rm --no-deps assets npm test`. Queue sync, cache/session file, mail log, debug OFF; `php.ini` local và environment defaults có trong docker/config. Staging/production phải có runtime/security/dependency review riêng.

## Phạm vi và bước tiếp theo

Business logic qua Actions/Services, native relational models; không DB-in-Blade, không JSON-copy variants để Exment nhìn thấy. Admin dùng page plugins trong native Exment shell và native identity/roles. Blade/Cart/Checkout và generic legacy redirect foundation đã có trong Phase3. Phase4 là Data Migration; Phase5 đối soát SEO/security/testing; chưa chạy full importer hoặc release. Chỉ bắt đầu phase tiếp theo khi được chỉ định.

Legacy artifacts tại `E:\Workspace\GitHub\bancay_legacy` được giữ riêng; `backups/`, `legacy-private/`, `.env`, raw SQL/records và generated dependencies/assets bị Git bỏ qua. Backup hosting bổ sung vẫn hoãn theo chỉ định “backup sau”. Source/DB production và DNS chưa bị thay đổi.
