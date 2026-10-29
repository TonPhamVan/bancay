# DirectAdmin preparation and verification

User confirmed: DirectAdmin, chưa có hồ sơ. Không đăng nhập hosting,đổi DNS/docroot,freeze CI3 hoặc chạy production commands trong review này. [Profile template](../../deployment/directadmin/profile.example.json) intentionally has null target facts;không tự điền local Docker values làm chứng nhận hosting.

DirectAdmin có thể dùng Apache,nginx/nginx+Apache hoặc LiteSpeed;nginx standalone không xử lý .htaccess. Không suy ra web server từ tên panel. [Official supported services](https://www.directadmin.com/technologies.php), [CustomBuild overview](https://docs.directadmin.com/custombuild/custombuild-overview.html), [nginx configuration](https://docs.directadmin.com/webservices/nginx/customizing-nginx.html).

Operator cần cung cấp bản cấu hình đã lược secret hoặc receipt private:PHP CLI/web8.3 và extensions,DB/collation/SQL mode,application directory/public-only docroot mới,old docroot/upstream cho rollback,actual web server/proxy IPs,domain SSL/renewal/HTTP redirect,storage no-execution và private log paths. Không gửi SMTP/DB password trong chat hoặc Git. Certificate của panel/server hostname không tự chứng nhận certificate bancay.vn. [Official SSL locations](https://docs.directadmin.com/webservices/ssl/service-ssls-and-le.html).

## Cron and queue

Runtime discovery xác nhận exment:schedule hourly và exment:safetywatch mỗi phút. Chúng được native service provider đăng ký,dù app/Console/Kernel schedule trống. queue=sync hiện không cần worker riêng;đổi sang database/Redis phải bổ sung worker/retry/failed-jobs và kiểm lại. Không copy các crawler/cron PHP cũ CI3 sang public Laravel.

DirectAdmin user-level cron phải gọi PHP CLI đúng version,cùng application owner. [Wrapper](../../deployment/directadmin/scheduler.sh) yêu cầu hai absolute paths và fail khi chưa cấu hình. Command mẫu,KHÔNG chạy khi còn placeholders:

    BANCAY_PHP_BIN=/VERIFIED/php83 BANCAY_RELEASE_PATH=/VERIFIED/app sh /VERIFIED/app/deployment/directadmin/scheduler.sh >> /VERIFIED/private-logs/scheduler.log 2>&1

Cron expression: mỗi phút. Actual paths/permission/cron installation vẫn P5-B02. Local wrapper argv/fail-closed checks và cả hai native commands trên clone log-mail PASS;không coi đó là cron đã cài trên DirectAdmin. Giữ cron log private,log rotation,disk/inode checks và operator alert owner.

## Runtime preflight and logs

Sau khi operator dựng candidate riêng trong Phase6,chạy bằng verified PHP binary:

    php scripts/release-readiness/preflight.php
    php artisan schedule:list
    composer check-platform-reqs --no-dev
    php artisan exment:checkrequire

Preflight chỉ đọc configuration/runtime,không chứng nhận certificate,production backups hay live routing. Kiểm APP_ENV production,debug off,HTTPS origin,key,Secure/HttpOnly/Lax session,DB config,queue sync,legacy import off,storage link,writable private logs/sessions,PHP/extensions và native schedule discovery. LOG_LEVEL=info giữ audit tồn kho;warning sẽ lọc mất StockAdjustment info events. Controlled500 probe trên clone xác nhận error ghi log nhưng body không lộ exception/path/SQL,info event được giữ.

## Files and restore

Public docroot phải là public/;application,.env,vendor,private backups và Laravel logs ở ngoài docroot. [storage.htaccess](../../deployment/directadmin/storage.htaccess) chỉ là Apache-compatible template;chưa kiểm actual AllowOverride. Nếu nginx/OpenLiteSpeed hoặc proxy serving storage trực tiếp,operator phải áp đúng server rule và thử PHP giả ảnh/private-file access;không giả PASS từ template.

Native Exment views sau scheduler có database-qualified names. Cross-database restore từ raw full SQL có thể lỗi1054. Portable recovery đã test:dump đầy đủ BASE TABLES,lưu nguyên source view definitions riêng,restore data rồi tạo lại đúng2 derived views qua command có guard:

    php artisan store:rebuild-derived-views --database-confirm=EXACT_RESTORED_DATABASE

Local chỉ chấp nhận bancay_test hoặc owned bancay_release_window/verify/candidate_<timestamp>. Production yêu cầu maintenance mode,exact DB confirmation và native+domain schema hiện diện. Không reset Exment,không replay old migrations,không rewrite chuỗi DB name trong toàn bộ SQL/HTML. Dùng target DDL account có SELECT,CREATE VIEW,DROP cần cho CREATE OR REPLACE VIEW;không dùng runtime guest quyền toàn server. Rebuild đặt definer theo target account;giữ SELECT cần thiết cho owner hoặc dùng owner policy DBA đã kiểm. Test view queries và checksum base tables trước mở traffic.

Stored view definitions/failed raw dumps/recovery ledger giữ private. Không tự bỏ view lạ:portable tool chỉ biết hai native views,unknown views phải có restore strategy trước release. Actual target paths/privileges/SSL chưa có hồ sơ nên vẫn BLOCKER P5-B02.
