# I. Integration Inventory, cron và runtime dependencies

“Có source/config” không đồng nghĩa dịch vụ còn hoạt động. Không gọi SMTP, webhook, API, scraper hoặc cron trong Phase 0. Credential chỉ được ghi **FOUND SECRET / CREDENTIAL**, không có giá trị trong báo cáo.

| Integration | Purpose | Source location | Environment | Credential required? | Still active? | Need migrate? | Replacement strategy |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Main MariaDB | Application data | application/config/database.php:76 | base_url bancay.vn; local source export | FOUND SECRET / CREDENTIAL at :79–80 | Source active; production connectivity NOT TESTED | Dữ liệu có | DB mới của Laravel; credential qua environment; không tái sử dụng config cũ |
| SMTP / PHPMailer | Email helper | Admin.php:1283–1307 | Host hard-coded; TLS port25 | GUSER/GPWD được tham chiếu nhưng không thấy định nghĩa; thông số đầy đủ UNKNOWN | Chưa có call site hoặc load class được xác nhận | REVIEW | Laravel Mail và queue ở phase được yêu cầu; không test gửi mail thật |
| Facebook Page / Customer Chat | Social widget/fanpage | application/views/template.php:237; bottom-content.php:58; tblfooter.fanpage | Facebook SDK v7.0 string; page widget từ source | App/page credentials chưa có; public Page ID không là secret | Snippet present; hoạt động dịch vụ NOT TESTED | REVIEW | Link/page embed đã duyệt; thay widget sau khi xác minh dịch vụ hiện có |
| Zalo link | Chat/phone contact | application/views/template.php:121 | Public hotline từ settings | Không thấy token/API | Link source present; NOT TESTED | Giữ link nếu còn dùng | Settings có validation; không coi là Zalo API |
| Google Analytics/tag snippet | Tracking config | tblfooter.anatic | Có googletagmanager host trong DB | Tracking config có; secret chưa xác định | Không thấy template hiện tại echo anatic; UNKNOWN | REVIEW | Chỉ thêm snippet đã duyệt ở hệ mới; không chạy HTML tùy ý từ DB |
| Google Maps | Contact map | application/modules/site/views/contact.php:79–95; tblfooter.map | Contact view thiếu controller reachable | Không thấy key trong snippet; cấu hình hiện tại UNKNOWN | View-only; NOT TESTED | REVIEW | Contact page mới + environment/API restrictions nếu thực sự cần |
| Google Fonts / JS CDN | Frontend fonts/admin frameworks | template.php:29; admin/template.php:6,10 | fonts.googleapis.com; jsDelivr; code.jquery.com/latest | Không | Source loaded; external availability NOT TESTED | REVIEW | Pin dependency/build assets phù hợp; Alpine cho interaction; tránh latest trong production |
| Old Googlecode HTTP script | IE compatibility | application/views/template.php:7 | http://css3-mediaqueries-js.googlecode.com/... | Không | Old snippet; NOT TESTED | DROP from new UI | Responsive CSS mới; không copy compatibility script |
| External product link / embed | Content/media link | DB HTML scan: shopee.vn | Public content | Không thấy API credential | Reference only; UNKNOWN | REVIEW | Kiểm tra lại nội dung và link đích; không coi là payment integration |
| Scraper source site | Crawl categories/products/price | cron/laydm.php:13; cron/laytin.php:14,40; cron/cron.php:30 | Domain ngoài và database khác app | FOUND SECRET / CREDENTIAL trong mysqli literals | Cron schedule absent; tables cron_link absent; UNKNOWN | REVIEW | Không tự chạy; DROP khỏi app mới nếu không có yêu cầu nhập dữ liệu hợp lệ |
| Image optimization tools | Optimize image batches | cron/opz.php:39–42 | optipng/jpegoptim executable trên host nếu có | Không | Tool installed/schedule UNKNOWN | OPTIONAL | Image processing được kiểm soát; không exec lệnh ghép filename từ input |
| Payment / shipping / webhook / cloud storage | Chưa thấy implementation thực tế | Toàn source inventory và schema | Không có config/SDK/table được xác nhận | UNKNOWN | NOT FOUND IN SUPPLIED SNAPSHOT | Theo Master Plan Phase 7 khi cần | Không tự thêm integration ở Phase 0/1 |

## Cron / scheduled jobs

| Command / candidate entrypoint | Schedule | Purpose | Dependency | Still required? | Migration plan |
| --- | --- | --- | --- | --- | --- |
| php cron/cron.php (candidate, chưa có crontab command thật) | UNKNOWN; file có HTML meta-refresh 5 giây | Lấy cron_link chưa visited và cập nhật giá tblsanpham | mysqli kết nối DB khác app; HTTP HTML DOM; cron_link | REVIEW | Không mang nguyên script; xác minh nhu cầu trước khi viết command/service ở phase phù hợp |
| php cron/Bản sao cron.php (tên thật dùng Unicode decomposed) | UNKNOWN; cũng meta-refresh | Crawl nội dung/ảnh và insert products | HTTP, GD, tmp, remote files, mysqli | REVIEW | Backup/audit only; không chạy scraper |
| php cron/laydm.php | UNKNOWN | Scrape categories và insert tbldanhmuc | Website ngoài; mysqli config | REVIEW | Giữ nguồn để tra rule; không bật scheduled import mặc định |
| php cron/laytin.php?url=... (HTTP script) | UNKNOWN | Nhận URL và insert crawl links | HTTP user-supplied URL; cron_link | REVIEW | Không đưa endpoint SSRF sang hệ mới |
| php cron/opz.php | UNKNOWN | Tối ưu ảnh bằng external binaries | optipng/jpegoptim; filesystem | OPTIONAL | Làm mới dưới command có path validation nếu cần |
| DirectAdmin cron/email/maintenance/sync jobs | Chưa có export | UNKNOWN | Hosting config | UNKNOWN | **DEFERRED BY USER — “backup sau”** |

Không dùng meta-refresh để suy ra lịch cron của server. Không tìm thấy email job, webhook job hoặc payment sync đã được xác nhận. OS/web server/SSL/DNS/PHP SAPI/extensions hiện tại xem [architecture.md](architecture.md); export cấu hình hosting được hoãn theo chỉ định người dùng, không tự truy cập hosting để thu thập.
