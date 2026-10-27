# Phase 0 — Legacy Analysis & Backup

Báo cáo ngày 27/10/2026 (Asia/Saigon), theo [Master Plan](master-plan.md) và [yêu cầu Phase 0](phase-0/request.md).

**PHASE 1 STATUS: READY WITH RISKS.** Đã thực hiện bộ phân tích và kiểm chứng local. Backup cấu hình hosting bổ sung được người dùng hoãn: **“backup sau”**. Không báo DONE cho full production backup/recovery; không bắt đầu Laravel/Exment Phase 1.

## 1. Executive Summary

Đầu vào: `E:\Workspace\GitHub\bancay_legacy\bancay.zip` và `caybonmua_db.sql`. Đã copy riêng, kiểm tra SHA-256/CRC, giải nén an toàn và thử restore DB trong container network none, không mở port. Không thay đổi source/DB/domain/DNS production.

Snapshot gồm 3.644 file thường và 1 symlink; document root có 3.321 file. SQL có **12 bảng / 980 bản ghi**, gồm **115 sản phẩm, 67 bài viết, 748 đơn và 2 admin**. Có **3 sản phẩm với tổng 8 variants**. Đã tạo 12 deliverables A–L cùng CSV/JSON evidence và công cụ audit offline.

Phát hiện ảnh hưởng thiết kế: giá/số lượng nhận từ form; 344 đơn giá 0 và 348 đơn quantity <= 0; SKU/alias trùng; 2 sản phẩm orphan category; 6 file references thiếu; role guards không nhất quán; MD5; raw SQL/XSS/CSRF; CKFinder không xác thực trong config; sitemap domain khác. Không coi mọi chức năng có code/view là chức năng đang hoạt động.

## 2. Current Architecture

CI **3.1.9** + HMVC **5.5**, modules admin/site; controller/model/PHP views truy vấn DB trực tiếp; standalone cron và CKFinder nằm ngoài CI. Dump ghi PHP **7.3.33**, MariaDB **10.3.34**; đây là metadata của export, không phải đo runtime hosting hiện tại.

[A. Architecture / dependencies / server inventory](phase-0/architecture.md), [archive inventory](phase-0/evidence/archive-inventory.csv), [controller methods](phase-0/evidence/controller-methods.csv).

## 3. Functional Inventory

[B. Functional Inventory](phase-0/functional-inventory.csv) có URL, controller/model, tables, input/output, validation, rule, permission, status và migration classification. Method inventory bao phủ 66 method controller, gồm constructor và controller mẫu.

Có source cho home/catalog/search/articles/modal đặt mua/admin CRUD. Contact/cart/old checkout/customer management có views hoặc links nhưng thiếu handlers/bảng; order delete/detail cũng không đầy đủ. Runtime flows vẫn **NOT TESTED**; CODE_PRESENT không có nghĩa PASS chức năng.

## 4. Business Rules

[C. 32 Business Rules](phase-0/business-rules.csv) dẫn source controller/model/view/helper/JavaScript và đối chiếu DB. Bao gồm giá sale, giá 0/liên hệ, VIP/sort, categories, JSON variants, stock display, order snapshot, SEO/slug, popup 365 ngày, article-view 1.200 giây và role guards. Mỗi rule ghi cần giữ hay phải thay behavior không an toàn.

Người dùng cho phép “chủ động thiết kế”: policy legacy status `0 → NEW`, `1 → CONFIRMED`; giữ mã gốc và dấu nguồn legacy. Không suy diễn completed/paid/shipped; import lịch sử không kích hoạt side effects.

## 5. Database Inventory

[D. Database Inventory](phase-0/database.md) bao phủ 12 bảng: row counts, PK, reference fields, cột quan trọng, usage và target. Có [schema-only SQL](phase-0/evidence/legacy-schema.sql) không chứa records và [aggregate evidence](phase-0/evidence/database-summary.json).

Counts từng bảng khớp độc lập dump tuples. Có 11 PK id/auto_increment; tbl_count không PK; không có FK. Charset trộn utf8/latin1 và column collations; có code tham chiếu bảng/cột không có trong snapshot.

## 6. Product/Variant Analysis

115 sản phẩm: 112 simple candidates và 3 có JSON với 8 variant rows; các mảng parse/align được. `pl` là tên nhóm; `pl1/pl2/img/price_pl/sale_pl/ma_pl/sl_pl` liên kết bằng index. Giá variant là chuỗi có dấu phẩy; code tìm theo cặp option, không theo SKU.

15 mã sản phẩm rỗng; mã `2` lặp 3 lần; 2 nhóm alias trùng; SKU variant trùng giữa products và trong cùng product. 111 giale=0, 31 giaban=0; không map mọi giaban thành sale_price hoặc coi 0 là miễn phí. [Mẫu và mapping draft](phase-0/database.md).

## 7. Order Analysis

tblorder chứa một sản phẩm mỗi row, snapshot tên/giá/số lượng/variant text và contact. Không có order_items/group key/payment/shipping/history; không snapshot SKU. 748 rows: status 0 có 730, status 1 có 18; 3 có variant text; 344 giá 0; 348 quantity <= 0. Không sửa/xóa/reprice các rows này.

Định hướng: archive nguyên trạng; nếu nhập lịch sử thì một row hợp lệ thành Order + một OrderItem, giữ provenance và quarantine lỗi; không nhóm basket bằng suy đoán. [Phân tích Order](phase-0/database.md), [migration policy](phase-0/migration-scope.md).

## 8. User/Role Analysis

Hai accounts đều role 1/status 1; hash 32 hex và code xác nhận MD5, không công bố hash/identity. DB có roles 1 admin / 2 Quản lý / 3 Nhân viên / 4 Viết bài; checkrole lại cho 1/3/5, còn user CRUD chỉ checklogin. UI ẩn dropdown không thay authorization. tblkho bị tham chiếu nhưng không có.

Hệ mới provision/reset bằng Hash chuẩn và permission matrix theo nghiệp vụ; không copy MD5/role integers. [Role matrix](phase-0/database.md).

## 9. File/Image Inventory

[G. File Migration Inventory](phase-0/files.md): scan upload/images/ckeditor/ckfinder tổng 2.947 files; 840 unique DB media paths, 834 tồn tại, **6 thiếu**. Có 329 duplicate groups / 537 extra copies. 2.113 files chưa có DB reference **không phải danh sách được phép xóa**.

Đã chuẩn bị mapping gallery/variant/article/category/banner/slider/avatar/logo và embedded HTML. Không copy sang Laravel storage hoặc sửa HTML ở phase này.

## 10. External Integrations

[I. Integration Inventory](phase-0/integrations.md): DB, SMTP helper, Facebook, Zalo, Maps, analytics config, CDN/fonts, scraper DB/site, image tools. Không thấy payment/shipping implementation được xác nhận. Có 10 files trong cron nhưng chưa có schedule export; không chạy cron, scraper hoặc gửi email.

ZIP có 10 log archives và stats ngoài document root; đã đọc offline, không xuất IP/PII. Hosting/DirectAdmin/cron/DNS/SSL/PHP ini exports bổ sung **DEFERRED BY USER**.

## 11. SEO/URL Inventory

[H. SEO Inventory](phase-0/seo.md): patterns sp/dm/bv/cm, search, pagination và implicit paths. [201 DB URLs](phase-0/evidence/seo-urls.csv) có exact path và percent-encoded form; targets là draft, chưa tạo redirects.

10 log archives có 11.202 request lines parse được và 166 entity paths; chỉ xuất safe paths/status aggregates, không khẳng định unique visits. Sitemap có 7 URL của greengen.com.vn; không dùng làm canonical list. HTTP/redirect/headers hiện tại **NOT TESTED**.

## 12. Security Findings

[J. Security Assessment](phase-0/security.md) có 16 findings với severity, file:line, evidence, condition, impact, recommendation và yêu cầu trước migration. Critical conditional finding: CKFinder CheckAuthentication=true và wildcard file write/delete nếu connector reachable. High findings gồm SQLi, order tampering, authorization/IDOR, MD5, CSRF, XSS, secret và file handling.

Chỉ audit; chưa khai thác hoặc sửa production. Credential được ghi FOUND SECRET / CREDENTIAL; không đưa giá trị vào báo cáo/Git mới.

## 13. Legacy → New Mapping

[E. Mapping draft](phase-0/legacy-new-mapping.csv) liên kết domains, fields, transforms, validation, identity strategy và target phase. Nguyên tắc: normalized variants; server snapshots; unique SKU; legacy IDs; status policy giữ original; safe media/exact URL mapping. Không tạo migration code hoặc copy nguyên schema legacy.

## 14. Migration Scope

[F. Data Scope / Gap Analysis](phase-0/migration-scope.md): KEEP evidence; MIGRATE catalog/content/images/URLs và approved settings; REVIEW history/customers/admin/cron; REBUILD runtime/admin/auth/commerce; DROP old runtime/debug/vendor samples khỏi app mới. Original vẫn giữ trong backup.

Features Phase 7/8 không tự triển khai vào Phase 0/1. Blueprint giữ Laravel + Exment + Blade + Alpine.js, single-store modular monolith.

## 15. Risks

Artifact chưa chứng minh cùng transactional snapshot hoặc mới nhất production; dữ liệu không sạch; lịch sử đơn thiếu độ tin cậy; ảnh thiếu; runtime chưa chứng nhận; source/schema mismatch; hosting config chưa đủ và security debt lớn. [Readiness](phase-0/readiness.md) ghi cách xử lý từng rủi ro.

Technical debt: fat controllers, DB-in-view, generic unsafe CRUD/status, duplicated helpers, stale fragments, hard-coded config/dependencies và auth yếu. Không refactor legacy hoặc port những lỗi này sang application mới.

## 16. Blockers

- **Phase 1 foundation/schema design:** không còn blocker cứng từ đầu vào local; phải giữ các rủi ro trong schema/ADR/validation plan.
- **Full backup/release:** BLOCKED đến khi đủ hosting/runtime/cron/DNS/SSL backups và final consistency/rollback test. Người dùng đã hoãn phần hosting; không tự truy cập production để bổ sung.
- **Legacy functional certification:** NOT TESTED; MANUAL TEST REQUIRED với người vận hành. Không gắn nhãn working chỉ dựa trên code.

## 17. Phase 1 Readiness

[L. Readiness Report](phase-0/readiness.md): **READY WITH RISKS** cho thiết kế foundation/database; không phải READY để import/publish production. [K. Backup Verification](phase-0/backup.md) phân biệt supplied artifacts, Git baseline đã redaction và hosting backup còn hoãn.

## 18. Recommended Next Steps và bàn giao

Chờ người dùng chỉ định Phase 1. Khi đó xác minh tương thích Laravel/Exment/PHP/database, ghi ADR, thiết kế domain/schema/relationships/actions và chuẩn bị Exment/migration mapping. Chưa làm frontend, admin UI hoặc real importer trong task này. Hosting backup bổ sung trước staging/cutover theo lịch người dùng.

| Hạng mục báo cáo | Kết quả |
| --- | --- |
| Đã thực hiện | Backup/restore local, Git baseline an toàn, static analysis và deliverables A–L như các mục trên |
| File/module thay đổi | README.md, AGENTS.md, .gitignore, docs/phase-0.md, docs/phase-0/ và scripts/phase0/; Master Plan giữ nguyên |
| Kiến trúc hiện tại | Repository mới chỉ có docs/audit tools; chưa có Laravel/Exment application |
| Database thay đổi | Chỉ DB test restore cô lập; không sửa production hoặc tạo schema Laravel |
| Tests đã chạy | SHA-256/CRC/extraction; SQL restore/counts; PHP 7.3 syntax; data/file/URL/log audits; baseline; unit/document/privacy verification |
| Test results | [Verification report](phase-0/verification.md), [verification.json](phase-0/evidence/verification.json); missing files/data flaws là findings còn mở, không che thành PASS |
| Risks / technical debt | Mục 15–16; giữ rõ NOT TESTED cho HTTP/browser/integration/full-hosting restore |
| Phase tiếp theo | Chỉ bắt đầu Phase 1 khi được chỉ định; chưa triển khai trong task này |

Private original backups/extraction/records/Git baseline nằm ngoài Git của repository mới.

PHASE 1 STATUS: **READY WITH RISKS** — đủ bằng chứng local cho thiết kế; còn data/security/runtime risks và hosting backup đã được hoãn.
