# Kiểm chứng Phase 0 và công cụ tái lập

Các script Python chỉ phục vụ audit offline, không phải application/migration code. Chạy từ root project trong WSL Ubuntu. Mặc định không có production connection; không chạy PHP application hoặc cron.

| Kiểm tra | Kết quả / evidence |
| --- | --- |
| Artifact SHA-256 và archive CRC | PASS: ZIP + SQL copies; 3.644 files và 1 safe internal symlink; [manifest](evidence/backup-manifest.json) |
| Source extraction | PASS byte verification khi extract; full private per-file manifest giữ ngoài Git |
| SQL restore | PASS: MariaDB10.3.39 local;12tables980rows; counts khớp tuple parser; [database summary](evidence/database-summary.json) |
| DB integrity / cleanup | PASS: mysqlcheck cả 12 bảng đều OK; chỉ container audit được xóa sau khi kiểm tra; [integrity evidence](evidence/database-integrity.json) |
| Schema export | DDL-only, không INSERT records; [schema](evidence/legacy-schema.sql) |
| PHP syntax | PASS362/362 trên PHP7.3.33 CLI; không execute code; [lint](evidence/php-lint.json) |
| File/DB reference checks | Đã scan; 6 missing references là finding của đầu vào; không báo toàn bộ media đầy đủ; [files](evidence/missing-file-references.csv) |
| Archived access logs | Đọc10tar/gzip;11.202requestlines;166entitypaths; không HTTP request ra production; [logs](evidence/logs-summary.json) |
| Private Git baseline | Commit đúng message;1470files; no remote; redaction9lines/6files; [baseline](evidence/baseline.json) |
| Unit tests của audit tools | PASS: 5 tests kiểm tra SQL tuple boundaries/escaping, redaction và bảo toàn logic nhập/hash password; không thay thế test ứng dụng |
| Final document/evidence verification | Kết quả do [verify_phase0.py](../../scripts/phase0/verify_phase0.py) ghi vào [verification.json](evidence/verification.json) sau khi kiểm tra toàn bộ deliverables |

## Lệnh audit local

```bash
# Đọc/copy đúng hai file đầu vào; không ghi đè bản backup khác hash.
python3 scripts/phase0/prepare_snapshot.py \
  --source-dir /mnt/e/Workspace/GitHub/bancay_legacy

# Yêu cầu container local đã restore; script từ chối nếu không network=none,
# có published ports hoặc thiếu label bancay.phase=0. Chỉ query dữ liệu local.
python3 scripts/phase0/audit_database.py \
  --dump backups/phase-0/2026-10-27/caybonmua_db.sql

# Đọc private snapshot/records để sinh metadata, file refs, URLs và logs aggregates.
python3 scripts/phase0/audit_assets.py
python3 scripts/phase0/audit_logs.py

# Chỉ tạo lần đầu; từ chối ghi đè repo baseline đã tồn tại.
python3 scripts/phase0/create_baseline.py

python3 -m unittest discover -s scripts/phase0 -p 'test_*.py'
python3 scripts/phase0/verify_phase0.py
git diff --check
```

Các script dùng thư mục snapshot cố định của audit này; không phải importer tổng quát cho nguồn bất kỳ. Không cần chạy lại baseline khi đã có commit. Full original ZIP/SQL phải được giữ private; chỉ schema-only SQL trong evidence được phép vào Git.

Container DB của audit được tạo riêng, không publish port, không mount volume/database production; data là tmpfs. Sau khi kết thúc thu thập, container được dừng/xóa có scope đúng tên/label; cần restore lại container local nếu chạy lại audit_database. Không xóa image hoặc container của dự án khác.

## Giới hạn

**NOT TESTED:** browser/public/admin functional flows, full HTTP legacy restore, SMTP/API/payment/shipping connectivity, malware/MIME của từng media file, live production headers/SSL/DNS, exact hosting runtime/extensions.

**MANUAL TEST REQUIRED:** người vận hành xác nhận business flows đang dùng và đối soát exception data trước import. Backup/export cấu hình hosting hiện **DEFERRED BY USER**; production-release readiness vẫn BLOCKED cho đến khi hoàn tất phần đó và final sync/rollback test.
