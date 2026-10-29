> Native scheduling requirement:queue=sync needs no worker,but exment:schedule(hourly) andexment:safetywatch(minutely) require an owning-user cron every minute. Use deployment/directadmin/scheduler.sh with verified absolute PHP/release paths andprivate rotated logs. Both commands executed on isolated QA;actual DirectAdmin cron not installed here. LOG_LEVEL=info is required to retain inventory audit.

# Phase 6 production runbook — prepared, NOT authorized to execute

> Latest blocker review: [report](release-readiness-report.md), [DirectAdmin guide](release-readiness/directadmin.md). User confirmed DirectAdmin without a target profile.158 PHP/1054 assertions +5JS PASS. Native scheduler/log-threshold/cross-DB view restore defects fixed and rehearsed;P5-B01/02/03 remain BLOCKER for actual production evidence. SMTP is not assumed a core guest-checkout requirement.

Phase5 thực hiện local hardening; chưa có hosting target,final source hoặc production backup đã verify. Không lấy local restore proof làm bằng chứng production. Owners: Release Lead điều phối, Hosting Operator quản lý ingress/backup, Data Owner xác nhận dữ liệu/freeze, QA Lead kiểm smoke. Mọi gate bên dưới phải có người ký và artifact private, không chỉ checklist được đánh dấu.

## 1. Stop gates

| ID | Reason | Required fix / verification |
|---|---|---|
| P5-B01 / RR-39 | Hosting/files/config/DNS/SSL/cron backup và production restore/rollback chưa được cung cấp; người dùng đã hoãn “backup sau”. | Operator tạo coherent backup,filename/timestamp/size/SHA-256,restore vào isolated host/DB; QA so schema/counts/checksums/media và chạy CI3 smoke. Ghi exact routing rollback target/owner/time. Không tự truy cập hosting hoặc hỏi lại trong Phase5. |
| P5-B02 | Target PHP/extensions/DB/webroot/storage/TLS/proxy/cookies/SMTP chưa kiểm thật. | Kiểm bằng candidate hostname/IP trước switch,valid SSL,HTTP→HTTPS/canonical,secure cookies,private files,no PHP execution in upload,permissions,SMTP controlled recipient/reset delivery/timeout/logs. Không tái dùng local/legacy credentials. |
| P5-B03 | Snapshot supplied không phải final transaction-consistent production export. Importer SKIP existing IDs,không delta-sync; combined preview chứa demo và settings riêng. | Chuẩn bị/rehearse release artifact từ nguồn final đã freeze trên builder cô lập,giữ cả demo và migrated theo yêu cầu; Data Owner xác nhận production-visible SKUs/prices/stock/branding/contact,archive exclusions. Reconcile mọi entity/URL/media/order rồi backup/restore artifact. Không restore main8000 lên production một cách mặc định. |

Không chạy legacy:migrate trong production. TargetGuard chỉ chấp nhận local/testing,DB host db,bancay_phase4_* được khai báo,source SELECT-only,log mail,backup proof và matching dry-run. Không sửa guard/APP_ENV giả trên production để vượt chặn.

## 2. Configuration checklist

[Template không secret](../.env.production.example) đã chuẩn bị; target values là NOT VERIFIED. APP_ENV=production,APP_DEBUG=false,APP_URL=https://bancay.vn (canonical host phải xác nhận),APP_KEY mới private. Runtime DB account least-privilege; backup/DDL account riêng. PHP8.3/extensions/GD-WebP/platform và DB version/collation/SQL mode phải được kiểm bằng composer check-platform-reqs/exment:checkrequire và schema audit trên target.

Single app instance: file sessions/cache có writable directories và backup policy; cookie riêng,HttpOnly/Lax/Secure=true; session lock/storage writable. Nếu reverse proxy, cấu hình explicit trusted IP/CIDR theo ingress thật, không tin wildcard X-Forwarded-*; kiểm scheme/links/cookie và spoofed Host. Redirect www/HTTP về canonical HTTPS,legacy paths về đúng final URLs,không homepage/loop. Kiểm chuỗi redirect qua ingress thực tế,không suy từ localhost.

Queue=sync hiện tại,không giả worker đang chạy. MAIL_MAILER=log cho staging; production SMTP chỉ bật sau controlled connection/sender/recipient/reset/timeout/failure tests. EXMENT_MAIL_SETTING_ENV_FORCE=true để credentials từ env,không legacy SMTP. Không có order-notification/payment integration; không hứa tính năng chưa implement. Tạo production identity riêng qua native workflow,least-privilege roles; test login/revoke/reset; không copy local-admin password hoặc legacy MD5.

Webroot chỉ public/. Chặn .env,.git,private backups/vendor/storage framework/logs và directory listings. Chỉ front-controller index.php được execute; upload/public-storage không execute PHP. Native admin disk phải private qua authorized controller. Storage symlink phải trỏ đúng release shared public media,không source backups. TLS/certificate/renewal,HTTP redirect và Secure cookie phải thử thật. STOREFRONT_INDEXABLE=false trong candidate; chỉ mở ở bước cuối sau smoke/SEO gates.

## 3. Data freeze and final build

1. Thông báo freeze window,owner và cutoff timestamp/timezone. Freeze mọi legacy order/catalog/admin/cron writes tại nguồn; read-only page/maintenance phải test bằng request thử và ghi last source order ID/time/count. Không chỉ chặn menu admin. Không chạy cron export tạo side effects.
2. Khi writes đã dừng, lấy final DB/files/config backup nhất quán; transactional snapshot cho InnoDB và lock/freeze phù hợp với engine khác. Tính SHA-256,kích thước,restore vào isolated verification DB,đối chiếu schema/count/checksum và media. Giữ backup nguồn nguyên vẹn.
3. Restore final export vào source snapshot SELECT-only trên release builder local. Tạo target mới tách biệt,không import đè8000/8002 hoặc database production. Review manifest cũ ràng buộc fingerprint; source thay đổi phải đối chiếu lại affected decisions và tạo manifest đúng target/source,không chỉ sửa hash để vượt guard.
4. Các CLI dưới đây là commands có thật, chỉ dùng khi builder env/backup/manifest đã được thiết lập và kiểm chứng:

       php artisan legacy:migrate --dry-run --include-orders
       php artisan legacy:migrate --include-orders --force
       php artisan legacy:validate

   Cùng scope/range/code/source/target/media/backup/review binding; --force không bỏ guard. Giữ exclusions493 historical orders/2 banners/2 users và fallback đã duyệt,nhưng final source khác phải giải thích delta,không suy fabricated price/qty/status. Existing IDs SKIP nghĩa là không dùng incremental replay để cập nhật rows đã đổi.
5. Giữ hai bộ dữ liệu theo yêu cầu: preserve demo archive/main,build combined candidate trong môi trường cô lập và kiểm FK ID remap/collision/settings policy/order snapshots. Tool merge hiện giới hạn đúng local bancay/bancay_test,không mặc định hỗ trợ arbitrary release DB. P5-B03 chỉ đóng khi thủ tục builder/merge và provenance cho final snapshot được review và rehearsed; không có command production merge được bịa trong runbook.
6. Chạy full regression,browser core,migrated E2E,integrity/201 redirect/SEO/media trên release candidate; stock opening phải do Data Owner xác nhận. Không tự nhập pending legacy history thành live reservations hoặc inferred payments. Export release SQL+media+code lock từ candidate đã freeze/approve,loại QA accounts/sessions/checkout attempts/test orders khỏi deploy artifact bằng thủ tục đã được kiểm (không xóa dữ liệu8000). Hash/restore chứng minh artifact đúng release. Đây còn là gate P5-B03,không thực hiện final migration trong Phase5.

## 4. Deployment command sequence (Phase6 only)

Operator phải điền absolute paths/hostname/DB mới/service names theo target đã xác minh trước khi chạy. Các biến là deployment inputs private,không credential trong Git hoặc shell history. Không có default path/service/DNS mutation tự động. Stop nếu release code/artifact hash,DB identity hoặc restore proof khác receipt.

    cd "$RELEASE_DIR"
    php artisan down --retry=60
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
    composer check-platform-reqs --no-dev
    sha256sum --check "$RELEASE_SHA256_FILE"
    mariadb --defaults-extra-file="$DB_CLIENT_CONFIG" "$CANDIDATE_DB" < "$VERIFIED_RELEASE_SQL"
    php artisan migrate:status
    php artisan migrate --force
    php artisan store:rebuild-derived-views --database-confirm="$CANDIDATE_DB"
    php artisan storage:link
    php artisan exment:checkrequire
    php artisan optimize

Release SQL must be a portable BASE TABLE dump,with original native view definitions archived separately. Scheduler-created workflow views contain source database qualifiers:raw full-view dump reproducedERROR1054 on another DB name. Do not replace database names in the entire SQL/data dump. Restore all base tables first,then rebuild two derived views via the guarded command above in maintenance. Validate view queries and unchanged base-table checksums. Unknown views require a verified strategy,never silently omit them. Target DDL account needs view creation/replacement privileges and a valid retained definer;do not grant broad DDL rights to public/runtime access.

Restore chỉ vào NEW candidate DB được receipt chỉ rõ; DB_CLIENT_CONFIG private0600. Native v7 schema/identity đã nằm trong artifact verified; không exment:install/reset initialized DB. Media copy vào verified shared storage trước storage:link; không rsync --delete từ backup lên source. Build assets tại CI/builder bằng npm ci && npm run build; verify manifest/bundles trong artifact. Optimize chỉ sau final env/storage/proxy config. Web/PHP reload dùng command thật của Hosting Operator sau khi được ghi và rehearsed; chưa có target nên hiện BLOCKED,không đoán tên service.

Giữ candidate restricted,noindex,mail log trong private validation. Kiểm DB connection bằng read-only metadata (không in password),migrations ledger và source-table counts. Sau khi đóng P5-B01/02/03,Release Lead mới cho phép Phase6 switch routing/DNS theo ticket riêng. Phase5 không chạy bất kỳ command production nào ở đây.

## 5. Smoke and cutover validation

Trước khi mở live orders: home/category/product/article/contact200,image/CSS/JS/font200; migrated variant đúng SKU/price/stock; category/search/article pagination và query/canonical; 301 representative20products+all8categories+10articles đúng destination; missing404; robots/sitemap/canonical theo final origin;HTTPS/certificate/HTTP redirect/Host reject/cookie flags;native admin login/product/order/roles hoạt động.

Controlled synthetic checkout: product→variant→cart→checkout→success; kiểm exactly one order,item snapshot,total,reservation;admin nhìn đơn,status/history,cancel giải phóng đúng một lần. Ghi test order IDs,cancel theo workflow; không xóa history hoặc gọi payment thật. Test duplicate submit/ownership negative và kiểm console/network/logs. Không dùng catalog test giả để nhận đơn khách thật khi chưa có merchandising sign-off.

Sau smoke thành công và rollback route sẵn sàng,mở index/robots và live writes theo thứ tự được duyệt; không có khoảng trống legacy writes giữa final export và switch. Theo dõi ngay15/30/60 phút và24giờ:5xx/419/422/429,checkout failures/order totals/reservations,PHP/web errors,disk/inodes,DBconnections/locks,SMTP failure/reset delivery,SSL/assets/301. Chốt owner trực tiếp,ngưỡng abort (checkout broken,data mismatch,stock issue,security exposure) và rollback decision timestamp.

## 6. Observability and operations

Application daily logs tại shared storage/logs/laravel-YYYY-MM-DD.log,access qua operator restricted; native operation logs trong DB,không public. Web/PHP-FPM/system/SSL logs path phải điền theo hosting (P5-B02). Disk/inodes/permissions/media/storage-link check,DB backup jobs/restore age,connections/deadlocks/slow queries;sync queue hiện không có worker,review nếu chuyển driver. Mail connection/error/delivery và provider log cần operator access. Không log request bodies/password/secret/card/token; không bật APP_DEBUG để troubleshoot public traffic.

Daily admin review NEW/reserved orders chống giữ tồn quá lâu,manual cancel chỉ qua workflow;RETURNED không restock đến physical inspection. Chưa có expiry/ledger/job mới. Retention checkout attempts/audit logs cần policy; không xóa order/history. Test backup restoration định kỳ. [Rollback chi tiết](rollback-plan.md) là gate bắt buộc.
