# bancay.vn — Laravel / Exment foundation

Một Laravel modular monolith cho một cửa hàng. **Phase 2 Exment Admin đã triển khai local: catalog/SKU/ảnh/tồn kho, đơn hàng/workflow, khách hàng, nội dung, settings và native users/roles.** Chưa xây storefront Phase 3.

Stack đang khóa: **Laravel 10.50.4 / Exment 6.2.15 / PHP 8.3.36 / MariaDB 10.11**. Tạm dùng Exment v6 theo chỉ định; nâng v7/Laravel 12 khi có release và yêu cầu cập nhật riêng.

**PHASE 3 STATUS: READY WITH RISKS — local development only.** Composer audit còn dependency findings gồm critical/high; Laravel 10 hết security support. **Không deploy production** với baseline hiện tại. [Risk/ADR](docs/architecture.md), [audit hiện tại](docs/phase-2/evidence/composer-audit.json).

## Tài liệu

- [Master Plan nguyên bản](docs/master-plan.md)
- [Báo cáo Phase 0](docs/phase-0.md)
- [Báo cáo Phase 1 — 24 mục](docs/phase-1-report.md)
- [Báo cáo Phase 2 — 23 mục](docs/phase-2-report.md)
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

- Admin: [http://localhost:8000/admin](http://localhost:8000/admin)
- Health: [http://localhost:8000/health](http://localhost:8000/health)
- Menu nghiệp vụ: **Tổng quan cửa hàng** hoặc [dashboard](http://localhost:8000/admin/plugins/bancay_dashboard). Với foundation đã cài, rebuild app image rồi chạy `php artisan store:configure-admin` trong container; không chạy lại raw `exment:install`.
- User code: `local-admin`; password trong `legacy-private/phase1/admin.env` (private, ngoài Git), không có password mặc định trong source.
- Muốn bootstrap thủ công: `docker compose run --rm app php artisan store:initialize-local-admin` sẽ hỏi password ẩn. Command chỉ local/testing và không sửa account đã initialize.

Seed **dữ liệu giả** tùy chọn: product,2 options/4 variants/inventories, customer/address/order/history, article/banner/setting và ảnh JPEG tổng hợp:

```bash
docker compose run --rm app php artisan db:seed --class=AdminDemoSeeder
```

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

Không có frontend build/npm requirement ở phase này. Queue sync, cache/session file, mail log, debug OFF; `php.ini` local và environment defaults có trong docker/config. Staging/production phải có runtime/security/dependency review riêng.

## Phạm vi và bước tiếp theo

Business logic qua Actions/Services, native relational models; không DB-in-Blade, không JSON-copy variants để Exment nhìn thấy. Admin dùng page plugins trong native Exment shell và native identity/roles. Blade/Cart/Checkout thuộc Phase 3; importer/redirects theo phase tương ứng. Chỉ bắt đầu phase tiếp theo khi được chỉ định.

Legacy artifacts tại `E:\Workspace\GitHub\bancay_legacy` được giữ riêng; `backups/`, `legacy-private/`, `.env`, raw SQL/records và generated dependencies/assets bị Git bỏ qua. Backup hosting bổ sung vẫn hoãn theo chỉ định “backup sau”. Source/DB production và DNS chưa bị thay đổi.
