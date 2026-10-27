# A. Legacy Architecture Report

Ngày phân tích: 27/10/2026 (Asia/Saigon). Chỉ phân tích bản export local do người dùng cung cấp. **Không xác nhận đây là snapshot mới nhất của production.** Mọi `file:line` trong bộ tài liệu tính từ `domains/bancay.vn/public_html/` trong ZIP gốc, không tính từ project Laravel mới.

## Kiến trúc thực tế

```text
Apache-compatible rewrite (.htaccess; web server thực tế chưa xác nhận)
  → index.php (native session_start)
  → CodeIgniter 3.1.9 + Modular Extensions HMVC 5.5
      → Site / Site_model → public PHP views
      → Admin / Admin_model → admin PHP views
      → mysqli / MySQL Query Builder / raw SQL
  → MariaDB (dump ghi 10.3.34)

CKFinder connector → upload/ (có đường truy cập riêng ngoài CI)
cron/*.php → mysqli + HTTP scraping + filesystem (ngoài CI)
```

Không có Service/Domain layer; controller, model, view và JavaScript đều chứa nghiệp vụ. Các view truy vấn DB trực tiếp. Đây là hệ thống để đọc nghiệp vụ và lấy dữ liệu, không phải nền tảng được tiếp tục phát triển.

## Inventory module

| Module | Mục đích / độ quan trọng | Controller / Model | Tables được dùng | Views / business rules | Dependency |
| --- | --- | --- | --- | --- | --- |
| site | Catalog, nội dung, nhận đơn; Critical | `application/modules/site/controllers/Site.php` (255 dòng), `models/Site_model.php` (243 dòng) | tblsanpham, tbldanhmuc, tblorder, tblbaiviet, tblchuyenmuc, tblfooter; views đọc tblbanner/tblslider | Gallery, JSON variants, giá, đặt mua một sản phẩm, popup, phân trang, đếm view bài viết; shared views tại application/views | HMVC, mysqli, images/locdau/device helpers, jQuery, Slick, Bootstrap |
| admin | Quản trị; Critical | `application/modules/admin/controllers/Admin.php` (1.312 dòng), `models/Admin_model.php` (204 dòng) | Catalog/content/orders/users/footer; tham chiếu tblcontact/tbluser không có trong dump | CRUD, upload, role checks không đồng đều, status toggle, báo cáo dashboard bằng queries trong view | HMVC, CI Upload/Pagination/Form Validation, Image helper, CKEditor/CKFinder |
| core | Hạ tầng routing/loading/cache; High | MY_Router, MY_Loader, MY_Output trong application/core | Không có domain riêng | HMVC routing, output caching; `MY_Output.php:21` đặt cache thành 1/60 phút nếu tham số không rỗng | CI Output, filesystem |
| standalone cron | Scrape/import/giá/ảnh; REVIEW | `cron/cron.php`, `cron/laydm.php`, `cron/laytin.php`, bản sao cron, opz.php | tblsanpham, tbldanhmuc, cron_link; kết nối database khác cấu hình ứng dụng | HTML meta-refresh 5 giây; thao tác DB/file ngoài CI; không có lịch crontab | mysqli, Simple HTML DOM, HTTP, GD; optipng/jpegoptim ở opz.php |
| CKFinder | File manager; Critical về bảo mật | `ckfinder/core/connector/php/connector.php`; `ckfinder/config.php` | Không dùng DB ứng dụng | CheckAuthentication trả true; quyền upload/rename/delete cho role * | CKFinder 2.5.0; filesystem public upload |

`Welcome.php` còn controller mẫu; không phải default controller. Hook file không đăng ký hook và `enable_hooks=FALSE`. Libraries riêng của ứng dụng chủ yếu là placeholder; framework libraries nằm trong system/. Autoload database và form/url helpers (`application/config/autoload.php:61,92`), không autoload CI Session.

Inventory **3.644 file thường toàn archive**: [archive-inventory.csv](evidence/archive-inventory.csv). Riêng document root public_html có **3.321 file**: [source-inventory.csv](evidence/source-inventory.csv). 323 file ngoài document root gồm logs/stats và một protected-list rỗng; không phải source ứng dụng. Inventory 66 method controller (gồm constructor/controller mẫu): [controller-methods.csv](evidence/controller-methods.csv). Kết quả tổng hợp: [source-summary.json](evidence/source-summary.json).

## Dependency inventory

| Dependency / phiên bản đọc từ source | Vị trí | Hiện trạng | Phân loại / hướng mới |
| --- | --- | --- | --- |
| CodeIgniter 3.1.9 | system/core/CodeIgniter.php | Đang làm framework của snapshot | Must replace bằng Laravel; không upgrade tại Phase 0 |
| Modular Extensions HMVC 5.5 | application/third_party/MX/Controller.php:19 | Admin/Site extends MX_Controller | Must replace; giữ phân chia nghiệp vụ trong modular monolith |
| PHPMailer 5.1 + POP3/SMTP classes | class.phpmailer.php, class.pop3.php, class.smtp.php | Có smtpmailer nhưng chưa thấy call site/load class; GUSER/GPWD không thấy định nghĩa trong source ứng dụng | Unknown / Legacy; cấu hình lại Laravel Mail nếu cần |
| CKEditor 4.4.0 | ckeditor/ckeditor.js | Được admin template tải; form bài viết/sản phẩm dùng | Still used in snapshot; thay/tích hợp editor phù hợp Phase 2 |
| CKFinder 2.5.0 | ckfinder/ckfinder.js, config.php | Được admin tải; connector riêng, upload root /upload/ | Must replace hoặc cấu hình bảo mật trong hệ mới; không copy nguyên connector |
| Image helper (không có version xác nhận) | application/helpers/images_helper.php | getimagesize/GD; save có thể copy nguyên ảnh | Still used; rebuild validation + image processing |
| Simple HTML DOM (bundled) | application/helpers/, root, cron/ | Nhiều bản; cron dùng | Legacy / REVIEW nhu cầu scrape |
| jQuery 3.3.1; jQuery latest qua CDN | js/jquery-3.3.1.min.js; admin template:9–10 | Public dùng bản local; admin tải thêm latest; form còn link bản 1.11.0 không có trong archive | Still used / must replace interaction bằng Alpine ở hệ mới |
| Bootstrap 3.3.0; admin CDN 4.3.1 | js/bootstrap.min.js; admin template:6 | Hai thế hệ CSS/JS | Still used; không mang dependency trộn phiên bản sang nền mới |
| Slick; jQuery UI 1.10.4; validation; Uploadify | js/, css/; form/template | Gallery hoạt động qua code; JS validation chủ yếu client-side; nhiều tài nguyên phụ cũ | Still used / Legacy tùy call site; rebuild UX sau |
| Mobile Detect; MooTools | Mobile_Detect.php, helpers/device_helper.php, js/ | Có source; cần test call site thực tế trước loại bỏ | REVIEW |
| Composer manifest | composer.json | Manifest của CI; PHP >=5.3.7; không có composer.lock, vendor hoặc package.json | Legacy manifest; không là dependency lock có thể tái dựng production |
| Payment/shipping SDK | Toàn inventory source | Chưa tìm thấy tích hợp thanh toán/vận chuyển thực tế | Không suy diễn là đang có; bổ sung ở phase được Master Plan chỉ định |

Phiên bản trên đọc từ header trong artifact, không phải kết quả truy vấn server hiện tại. Không đánh giá CVE chỉ dựa vào số phiên bản.

## Runtime/server inventory

| Hạng mục | Bằng chứng hiện có | Phần còn thiếu |
| --- | --- | --- |
| PHP | Header dump: 7.3.33; lint riêng bằng container CLI 7.3.33 | PHP SAPI/ini/extensions của hosting hiện tại chưa có |
| Database | Header dump: MariaDB 10.3.34; restore test: MariaDB 10.3.39 cùng dòng 10.3 | Không khẳng định server production hiện vẫn là phiên bản này |
| Charset/collation | Connection utf8 / utf8_general_ci; bảng trộn utf8 và latin1, có cột utf8_unicode_ci/utf8_vietnamese_ci | Phải giữ encoding đúng khi transform; đích utf8mb4 cần thiết kế Phase 1 |
| OS / Web server / SAPI | Cấu trúc domains/.../public_html, .htaccess, logs có archive mang tên nginx; DirectAdmin theo thông tin người dùng | OS, vai trò/version web server/proxy và PHP-FPM/LSAPI/CGI hiện tại chưa xác nhận |
| Document root | Tương đối: domains/bancay.vn/public_html; private_html là symlink ./public_html | Absolute hosting path/vhost config chưa có |
| Dung lượng | 615.374.163 byte file thường trong ZIP; upload 582.070.658 byte | Disk usage toàn hosting và file không nằm trong ZIP chưa biết |
| SSL / DNS / DirectAdmin | Domain base_url HTTPS; .well-known có artifact validation | Chưa có zone export, DNS records/TTL, cert chain/expiry, vhost/DA export |
| Cron / Email | Có standalone scripts và SMTP helper | Không có crontab/schedule hoặc xác nhận SMTP đang dùng |

Các extension/capability suy ra từ code: mysqli; json; GD (getimagesize/imagecreate/resize); sessions; filesystem/temp/write; cURL cho cron/class/curl.php; allow_url_fopen nếu Simple HTML DOM dùng file_get_contents HTTP; iconv/mbstring có thể cần cho thư viện. **Đây là dependency suy ra, không phải extension inventory của server đã đo.**

## Kiểm chứng và giới hạn

- PASS: cú pháp 362/362 PHP files trên PHP 7.3.33, mount read-only, network none. [Bằng chứng](evidence/php-lint.json).
- PASS: restore SQL local và số bản ghi. Không khởi chạy legacy HTTP server hoặc gửi đơn/mail/scrape.
- NOT TESTED: login, thao tác admin/public, kết nối SMTP/API, scheduling trên hosting.
- MANUAL TEST REQUIRED: người vận hành xác nhận chức năng nào thực sự đang dùng và các cấu hình hosting còn thiếu.
