> Current local website: **demo + migrated data combined at http://localhost:8000**. [UI mapping/merge report](ui-mapping-report.md).121 products/142 SKUs/72 articles/258 orders;120 products/71 articles visible locally, noindex. Original demo rows, source8002 and backups preserved.137 PHP/860 assertions,44 combined browser cases,0 UI mapping mismatches,201 local redirects PASS.13 inactive banner placements remain frontend work. Previous pure-demo/hidden8000 and pre-merge counts below are historical.

# Nâng Exment v7 / Laravel 12

**UPGRADE STATUS: COMPLETED — LOCAL VERIFIED**. Đã nâng ứng dụng và cả hai database local/staging ngày2026-10-28 (Asia/Saigon). Không deploy production, chuyển domain, import thêm legacy hoặc thay đổi quyết định xuất bản dữ liệu.

## Phiên bản và nguồn chính thức

Đã đọc [release notes do người dùng cung cấp](https://exment.net/docs/#/release_note), [hướng dẫn cập nhật v7](https://exment.net/docs/#/ja/update/v7_0_0) và [release v7.0.0](https://github.com/exceedone/exment/releases/tag/v7.0.0). Search index ban đầu chưa có bản mới; đã xác minh trực tiếp GitHub release API/tag và Packagist p2 trước khi nâng. Bản chính thức không phải dev/prerelease; reference `bf795a90598d436ea83956fcd4f2db83d8526944` khớp composer.lock. Đã đối chiếu thêm [Laravel12 upgrade](https://laravel.com/docs/12.x/upgrade).

| Thành phần | Trước | Sau |
|---|---|---|
| Exment |6.2.15|**7.0.0**|
| Laravel |10.50.4|**12.70.2**|
| Admin library |exceedone/laravel-admin4.2.2|**exceedone/exment-admin-core1.0.1**|
| PHP |8.3.36|8.3.36, đủ yêu cầu upstream và project|
| MariaDB |10.11|10.11, không đổi engine|
| Symfony Mailer/Mime |6.4.46|7.4.19|
| Carbon |2.73.0|3.15.1|
| Passport |11.10.6|12.4.3|
| PHPUnit |10.5.66|11.5.57|

[Version/reference metadata](exment-v7/evidence/versions.json). Dependency resolver chỉ dùng stable packages, không bỏ platform checks hoặc thêm audit ignore. Root constraints theo hướng dẫn: Exment^7.0.0, Laravel^12.68, Tinker^2.10, Collision^8.6, PHPUnit^11.5 và compatible dev tools. Các transitive dependencies được nâng theo graph tương thích; không thêm package CAPTCHA/LDAP/integration không sử dụng.

## File và thay đổi tương thích

- `composer.json`/`composer.lock`: stable stack mới; bỏ transitive admin cũ, laravelcollective/html và Intervention2 không còn được upstream dùng. Không sửa vendor source.
- `app/Admin/bootstrap.php`, `config/admin.php`: đổi namespace sang `ExmentAdminCore\Admin`; giữ cấu hình Exment auth provider/identity; thêm guard và throttle settings theo manual; avatar dùng asset open-admin. Không force-publish đè custom admin config.
- `php artisan exment:update` trên main và staging: publish đúng assets/views/lang và chạy native migrations; sau đó clear config/routes/views, đăng ký lại versioned page plugins bằng `store:configure-admin`. Không gọi exment:install hoặc reset initialized identity.
- Bỏ lớp backport `ExmentSafeValidator` và resolver riêng vì Laravel12 đã có native CR/LF fix. ExmentCustomValidator, extension messages, valid mail và invalid recipient rejection đã kiểm lại. Test chấp nhận đúng native PHP InvalidArgumentException ở mail boundary mới; không nới validation để làm test pass.
- Bỏ yêu cầu chuỗi ngoại lệ RR-30/RR-34 trong migration guard/config/.env example và fixture/scaffolding; audit đã sạch nên không còn lý do giữ bypass acknowledgement cũ. Local target/debug/mail/source-readonly/backup/checksum/receipt guards vẫn giữ.
- Tool tạo QA clone nay clear cache/views riêng để không giữ version metadata v6 từ lần chạy trước. Đã xác minh native footer hiển thịv7.0.0 và không còn asset request `/vendor/laravel-admin/`. Đã xóa thư mục assets v6 không còn được dùng sau khi xác minh backup và không còn active references.
- Giữ cấu trúc Laravel10-style bootstrap/Kernel/Handler theo hướng dẫn tương thích của upstream. Không đổi kiến trúc modular monolith, business schema hoặc API của Actions.

## Backup và database

[Backup proof](exment-v7/evidence/backup-verification.json): đặt hai ứng dụng local maintenance trước thao tác; dump riêng `bancay` và `bancay_phase4_staging` có timestamp/filename/size/SHA-256. Restore mỗi dump vào DB tạm do tool tạo; counts/data checksums/schema đủ87 bảng khớp, CHECK TABLE OK, DB nguồn không đổi; DB tạm đã xóa. **BACKUP VERIFIED**.

Archive trước nâng chứa source hiện tại, các thay đổi chưa commit, vendor, public vendor assets, plugin/storage và private env; SHA-256/readability đã kiểm chứng, chỉ lưu trong `legacy-private/exment-v7/`. Không đưa password, SQL dumps, ảnh nội dung/customer data vào Git. Giữ nguyên mọi file user đã có và không tự tạo commit.

[Database comparison](exment-v7/evidence/database-upgrade.json): native Exment migrations đưa mỗi DB từ87→98 tables. **Toàn bộ17 bảng nghiệp vụ có row counts, extended data checksum và schema hash giữ nguyên**, kiểm lại cả sau regression. Tồn kho, giá, customer data, order snapshots/status histories không bị migrate/ghi lại. Không sửa migrations cũ của application; native migrations của package mới được ghi nhận trong migration ledger bình thường.

Staging vẫn **115 products/120 SKUs/347 product-image rows/120 inventories/67 articles/255 orders, items và histories/21 inactive banners/8 settings**. Catalog/content DRAFT, published_atNULL, reserved0, không tạo live order. [Full reconciliation](exment-v7/evidence/reconciliation.json) PASS;775 referenced media paths checked, source fingerprint vẫn `5e90ab0125aceac97f629ed5d017b042946d8cfc0a2bf01167346d7adfc36658`.

Các quyết định đã duyệt vẫn có hiệu lực:493 invalid historical rows,2 banners và2 legacy accounts giữ ngoài operational import, bản gốc còn đủ;5 media aliases đã xác minh; ảnh article52 dùng fallback đã được người dùng chấp nhận. Không mở lại các câu hỏi này.

## Kiểm tra và kết quả

- [PHP regression](exment-v7/evidence/tests.json): **132 tests /814 assertions PASS**,0 fail/error/skip. Gồm fresh migration/rollback/remigrate, native identity/roles/default deny, CRUD/upload, exact VND, stock/concurrency, order snapshots/status, frontend checkout/idempotency/ownership, reviewed migration/media/receipt guards và native email channel. Assertion count giảm1 do bỏ test của waiver string đã hết tác dụng; không bỏ security test email.
- **5 JS tests PASS**. [Checks](exment-v7/evidence/checks.json): Composer strict validate/platform, Exment requirements và Pint PASS.
- [Composer audit](exment-v7/evidence/composer-audit.json): **PASS —0 advisories,0 abandoned packages**, không ignore/block-disable. [npm audit](exment-v7/evidence/npm-audit.json):0 vulnerabilities.
- [Browser acceptance](exment-v7/evidence/browser-acceptance.json): **44 PASS** — Chrome desktop17, Edge desktop17, Chrome mobile viewport10 trên clone sạch của dữ liệu đã nâng cấp; native forms2 reviewed products/7 articles, gallery/recovered inline images, Home→Category→Product→Variant→Cart→Checkout→Success, article/validation/OOS/navigation.
- [Native v7 checks](exment-v7/evidence/native-admin.json): thêm **3 PASS** cho sai password bị từ chối, đúng password/dashboardv7.0.0/open-admin assets, editor không có page errors hoặc failed local assets. Dashboard/editor screenshots đã xem trực tiếp, chỉ giữ private. Tổng47 browser cases, Safari/iOS NOT TESTED.
- [Browser data verification](exment-v7/evidence/browser-data-verification.json):3 synthetic QA orders đúng snapshots/reserved deltas; main staging vẫn255 history/0 live orders/reserved0 và hidden. QA DB/user/container đã xóa.

Lần chạy test đầu còn maintenance file nên HTTP tests trả503; đã đưa local apps ra maintenance sau khi migration/schema check hoàn tất, rồi rerun toàn suite PASS. Native v7 check ban đầu phát hiện version footer cũ do cache QA lưu từ lần chạy v6; đã sửa tool clear cache/views khi dựng clone, tạo lại clone sạch và rerun core44 +native3 PASS. Đây là các lỗi môi trường/test đã xử lý, không bị che hoặc bỏ case.

## Risk register và phạm vi hoàn thành

[Current register](exment-v7/evidence/risk-register.json): **RR-30 và RR-34 RESOLVED** bằng package/framework upgrade thực tế và auth/mail/regression/audit PASS, không còn chỉ là exception. RR-31/RR-32/RR-33 cũng RESOLVED cho lock hiện tại. RR-29 là historical false positive ởv10; stack hiện tại không còn advisory tương ứng. Các accepted source-data decisions và future Phase5/6 work vẫn tách riêng.

**EXMENT v7 UPGRADE: COMPLETE.** Có thể tiếp tục Phase5 SEO/security/performance/release testing ở local. Việc nâng cấp và audit0 không thay thế Phase5 HTTP redirect checks, production load/penetration/device testing hoặc hosting backup/cutover Phase6. Chưa deploy, commit hoặc push trong task này.

## Truy cập và thao tác tiếp

- Main local: `http://localhost:8000/admin/auth/login`.
- Staging có dữ liệu migrated: `http://localhost:8002/admin/auth/login`.
- Tài khoản/password native hiện có giữ nguyên; private credential files không đổi. Không chia sẻ lại password trong báo cáo.
- Có thể chạy `docker compose exec app composer audit`, `docker compose exec app php artisan test`, `docker exec bancay-phase4-app php artisan legacy:validate` và verifier mới `python3 scripts/exment-v7/verify.py`.
- Các collectors/verifiers Phase1–4 cũ giữ làm bằng chứng lịch sử theo stack/dataset cũ; không chạy để ghi đè evidence hoặc so trực tiếp pinv6 với stackv7.

Rollback chỉ khi cần trên đúng hai local DB đã backup: maintenance, khôi phục archive/lock/vendor và đúng từng SQL pre-upgrade, republish assets bằng bản Exment đã khôi phục, kiểm schema/data/auth rồi up. Không dùng migrate:rollback mù trên native migrations hoặc restore vào production. Chưa rollback vì tất cả required checks PASS; backup restore đã được thử riêng trên disposable DBs.
