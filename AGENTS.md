# Quy tắc làm việc trong repository bancay

## Nguồn yêu cầu

- Đọc `docs/master-plan.md` trước khi thực hiện mỗi phase. Đây là Master Plan do người dùng cung cấp; không tự ý sửa nội dung gốc.
- Xem `docs/phase-0.md` và báo cáo phase mới nhất để xác định tiến độ bằng bằng chứng thực tế.
- Chỉ định mới nhất: thực hiện **Phase 1 – Foundation & Database Design**, theo `docs/phase-1/request.md`. Tạm dùng Exment v6 theo yêu cầu người dùng; nâng Exment v7/Laravel 12 khi có release và yêu cầu cập nhật riêng. Không tự chuyển sang nhánh dev hoặc triển khai frontend/production.
- Foundation local hiện có Laravel 10.50.4/Exment 6.2.15 và17 domain tables; đọc `docs/phase-1-report.md`, `docs/architecture.md`, `docs/database-design.md`, `docs/exment-mapping.md`, `docs/legacy-mapping.md` trước thay đổi tiếp. Phase 2 READY WITH RISKS cho local, không tự bắt đầu Phase 2.
- Dependency audit còn FAIL (Laravel 10/JWT advisories, gồm critical/high). Người dùng chấp nhận v6 tạm thời; chỉ localhost/synthetic data, không deployment. Không thêm audit ignore hoặc tắt policy toàn project để che lỗi. Lock/risk exception được ghi trong architecture report; v7 upgrade phải chạy lại tests/migrations/auth/audit.
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
