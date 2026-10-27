# K. Backup Verification Report

Ngày: 27/10/2026 (Asia/Saigon). Nguồn do người dùng cung cấp: `E:\Workspace\GitHub\bancay_legacy`. Đây là **backup của các artifact được cung cấp**, không phải chứng nhận đã export đầy đủ production tại thời điểm hiện tại. Không đăng nhập hosting hoặc thay đổi production.

## Checklist trước phân tích sâu

- [x] Xác định hai đầu vào local; bản sao nằm trong thư mục ngoài Git và không ghi đè nguồn.
- [x] Kiểm tra dung lượng/disk; SHA-256 nguồn và bản sao khớp; ghi thời điểm/múi giờ và manifest.
- [x] Kiểm tra paths, entries, symlink, CRC toàn bộ ZIP trước giải nén; chỉ cho symlink nội bộ `private_html → ./public_html`.
- [x] Khôi phục source/files vào thư mục private; tạo SHA-256 từng file thường; giữ nguyên nội dung được giải nén.
- [x] Kiểm tra SQL đọc được, scan DDL và không chạy trên DB thực; restore vào container cô lập.
- [x] Số bản ghi restore khớp độc lập số tuples trong dump; schema export không có record.
- [x] Lập Git baseline riêng an toàn; không refactor business logic; không remote/push.
- [ ] Khôi phục website có đầy đủ PHP extensions/web-server và functional tests: NOT TESTED.
- [ ] Export bổ sung cấu hình hosting/cron/DNS/SSL/DirectAdmin/PHP ini: **DEFERRED BY USER — “backup sau”**.

## Artifacts đã kiểm chứng

| Artifact | Bytes | SHA-256 | Kết quả |
| --- | ---: | --- | --- |
| bancay.zip | 587.368.474 | 6085c236432301c537e4e1488d06022bfcaf92a42ca49d32e9b81daadd52b48a | File tồn tại; nguồn/bản sao khớp; archive CRC PASS |
| caybonmua_db.sql | 1.762.723 | 10d71369e8ae83826aae625b007afe75931eb8c1e22b133081ebc8d9e7879f9c | File tồn tại; nguồn/bản sao khớp; UTF-8 đọc được; local restore PASS |

Backup canonical: `backups/phase-0/2026-10-27/`; full per-file manifest: `manifest.private.json`. Bản giải nén: `legacy-private/phase0/extracted/`. Public summary không có record/credential: [backup-manifest.json](evidence/backup-manifest.json).

ZIP gồm **3.644 file thường**, tổng **615.374.163 byte** và **1 symlink nội bộ**. Document root có 3.321 file; ngoài document root có 323 file, gồm stats, 10 log archives và protected-list rỗng. Các log archives cũng đọc được bằng tar/gzip và đã được phân tích metadata/URLs an toàn; không xuất IP/query/referrer/user-agent.

## Backup coverage

| Thành phần cần backup | Coverage thực tế | Trạng thái |
| --- | --- | --- |
| Source code / config app / environment-specific literals | Có application/, system/, index.php, composer.json, CI config, CKFinder config, standalone scripts trong ZIP | PASS cho artifact cung cấp; FOUND SECRET / CREDENTIAL được giữ private |
| .htaccess / routing | Có document-root .htaccess và application/config/routes.php | PASS archive coverage |
| Product/article/banner/slider/avatar uploads | Có upload/ 1.843 files, images/ 157 files; source mapping đã đối soát | PASS archive coverage; 6 DB references thiếu trong đầu vào |
| Database | Dump có 12 bảng/980 rows; không có routines/triggers được xác định trong dump | PASS provided dump; không xác minh production còn bảng/database khác |
| Logs / stats | Có ngoài public_html; 10 tar.gz archives parse được | PASS archive coverage; logs chứa dữ liệu nhạy cảm nên không vào public Git |
| Cron PHP scripts | Có 10 files trong cron/ | PASS source coverage; không có crontab schedule được cung cấp |
| Email/SMTP config | Helper SMTP + references GUSER/GPWD; chưa đủ cấu hình/call site | PARTIAL; effective service UNKNOWN |
| Domain/DNS/SSL/DirectAdmin config / PHP ini | Base URL + domain path + validation artifact có trong source; chưa có exports cấu hình đầy đủ | DEFERRED BY USER; không phải full hosting backup |

Không gọi sáu file reference thiếu là “backup copy lỗi”: ZIP/SQL đã được copy đúng checksum, nhưng bộ nguồn được cung cấp không có những đường dẫn đó. Cần bổ sung/tìm lại trước migration media.

## Restore test

- MariaDB container `bancay-phase0-audit-20261027`, `--network none`, không publish port, database/data directory tmpfs; không mount data production.
- Image `mariadb:10.3`, digest `sha256:4f08c16ef56f0b097139c917e68d60f2c65d9f2b2defb770ee7e4d3a51bb7864`; server test **10.3.39**, dump ghi **10.3.34**. Đây là test cùng dòng 10.3, không khẳng định exact-patch/runtime parity.
- Import vào database local mới `legacy_audit` thành công; đủ 12 bảng; 980 rows; tất cả counts khớp độc lập với dump. Không tạo/sửa schema production.
- PASS: `mysqlcheck` cả 12 bảng đều OK; container audit đã được xóa sau khi thu thập evidence, tmpfs DB được giải phóng. [Integrity/cleanup evidence](evidence/database-integrity.json). Original SQL vẫn được giữ; không tác động container/database dự án khác.
- Export schema-only, không INSERT records: [legacy-schema.sql](evidence/legacy-schema.sql). Restore/count evidence: [database-summary.json](evidence/database-summary.json).
- Source restore: ZIP CRC + safe extraction + per-file hashes; 362/362 PHP syntax checks PASS trên PHP **7.3.33**; không execute legacy application. [Lint evidence](evidence/php-lint.json).
- NOT TESTED: full website restore, login/order workflows, GD processing, SMTP, external services, server config parity, backup/restore từ toàn hosting hiện tại.

## Git baseline

Repository riêng: `legacy-private/phase0/bancay-legacy`, directory mode 0700 trên WSL. Commit **d4fa09fc8f27ebecb196bd6890e443f14aaede8a**, message **Legacy production baseline**. Có 1.470 tracked files; clean working tree; không remote.

Để không commit credential, baseline chỉ thay credential/license literals bằng marker tại **9 dòng trong 6 file**, giữ số dòng và không refactor nghiệp vụ. Media upload/, .well-known validation token và .DS_Store được giữ trong ZIP gốc, không nằm trong source Git. [Baseline evidence](evidence/baseline.json) ghi đầy đủ file/dòng đã redaction và phần loại trừ, không ghi giá trị.

**Git baseline này không byte-identical với ZIP và không đủ để rollback nguyên trạng một mình.** ZIP/SQL/private manifest là bản gốc canonical phục vụ restore/forensics, bao gồm cả config và media. Raw source trong extracted/ không bị redaction hay sửa. Không push baseline lên remote.

## Tính nhất quán / retention

Checksum chứng minh bản sao khớp artifact, không chứng minh source/files/SQL được export tại cùng một transactional snapshot hoặc mới nhất của production. Giữ các bản gốc và manifest trong nơi backup được kiểm soát; không xóa/ghi đè source. Thư mục backups/ và legacy-private/ bị Git bỏ qua; dữ liệu SQL đầy đủ và log/raw records không vào report public.

Việc bổ sung backup hosting được hoãn theo chỉ định người dùng. Trước staging/cutover phải chốt đầy đủ server/config/cron/SSL/DNS, consistency/final sync và thử rollback. **Không báo DONE cho full operational backup ở thời điểm này.**
