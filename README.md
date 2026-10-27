# bancay.vn — Laravel / Exment foundation

Một Laravel modular monolith cho một cửa hàng. **Phase 1 foundation đã được triển khai local; chưa xây storefront hoặc CRUD nghiệp vụ Phase 2.**

Stack đang khóa: **Laravel 10.50.4 / Exment 6.2.15 / PHP 8.3.36 / MariaDB 10.11**. Tạm dùng Exment v6 theo chỉ định; nâng v7/Laravel 12 khi có release và yêu cầu cập nhật riêng.

**PHASE 2 STATUS: READY WITH RISKS — local only.** Composer audit còn6 advisory entries trên2 packages, gồm critical/high; Laravel 10 hết security support. **Không deploy production** với baseline hiện tại. [Risk/ADR](docs/architecture.md), [audit](docs/phase-1/evidence/composer-audit.json).

## Tài liệu

- [Master Plan nguyên bản](docs/master-plan.md)
- [Báo cáo Phase 0](docs/phase-0.md)
- [Báo cáo Phase 1 — 24 mục](docs/phase-1-report.md)
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
- User code: `local-admin`; password trong `legacy-private/phase1/admin.env` (private, ngoài Git), không có password mặc định trong source.
- Muốn bootstrap thủ công: `docker compose run --rm app php artisan store:initialize-local-admin` sẽ hỏi password ẩn. Command chỉ local/testing và không sửa account đã initialize.

Seed **dữ liệu giả** tùy chọn: một product,2 options/4 variants/inventories, images paths giả, customer/address/order/history, article/banner/setting:

```bash
docker compose run --rm app php artisan db:seed
```

Không có migration/importer nào đọc production legacy DB. Các đường dẫn ảnh demo không có file thật; đây là fixture schema, không phải UI/catalog đã migrate.

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

Business logic qua Actions/Services, native relational models; không DB-in-Blade, không JSON-copy variants để Exment nhìn thấy. Native admin screens/role mapping thuộc Phase 2; Blade/Cart/Checkout thuộc Phase 3; importer/redirects theo phase tương ứng. Chỉ bắt đầu phase tiếp theo khi được chỉ định.

Legacy artifacts tại `E:\Workspace\GitHub\bancay_legacy` được giữ riêng; `backups/`, `legacy-private/`, `.env`, raw SQL/records và generated dependencies/assets bị Git bỏ qua. Backup hosting bổ sung vẫn hoãn theo chỉ định “backup sau”. Source/DB production và DNS chưa bị thay đổi.
