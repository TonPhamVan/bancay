# bancay.vn — Laravel / Exment storefront

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
