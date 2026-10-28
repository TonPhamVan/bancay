# Quy tắc làm việc trong repository bancay

## Nguồn yêu cầu

- Đọc `docs/master-plan.md` trước khi thực hiện mỗi phase. Đây là Master Plan do người dùng cung cấp; không tự ý sửa nội dung gốc.
- Xem `docs/phase-0.md` và báo cáo phase mới nhất để xác định tiến độ bằng bằng chứng thực tế.
- Chỉ định mới nhất: **Risk consolidation Phases1–3 trước Phase4**, theo `docs/risk-review/request.md`. Đọc `docs/risk-review-report.md` và `docs/risk-register.md`; kết quả review được đóng gói trong commit riêng theo chỉ định người dùng. Không bắt đầu Phase4 trong review. Tạm dùng Exment v6 theo yêu cầu người dùng; nâng Exment v7/Laravel 12 khi có release và yêu cầu cập nhật riêng. Không tự chuyển dependency sang nhánh dev hoặc triển khai production.
- Foundation local Laravel10.50.4/Exment6.2.15;17 domain tables columns/FKs/indexes không đổi, review thêm3 money CHECKs qua migration additive2026_10_28_000002. Phase 2 admin đã triển khai; đọc `docs/phase-2-report.md`, `docs/exment-admin.md`, `docs/product-management.md`, `docs/order-workflow.md`, `docs/permissions.md` cùng tài liệu Phase 1 trước thay đổi tiếp. Phase3 storefront đã kiểm96 PHP tests/556 assertions +5 Node tests; desktop/mobile core checkout pass. Review mới:110 PHP tests/656 assertions +5JS PASS, nhưng Phase4 NOT READY do High email dependency remediation +fresh browser core verification BLOCKED; không tự bắt đầu Phase4. Đọc thêm `docs/frontend-architecture.md`, `docs/cart-checkout.md`, `docs/seo-frontend.md`.
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
