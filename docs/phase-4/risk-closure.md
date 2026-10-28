> Current stack: **Exment7.0.0 / Laravel12.70.2 / admin-core1.0.1**, PHP8.3.36. [Upgrade report](../exment-v7-report.md). Composer audit PASS (0 advisories/0 abandoned), npm audit0; RR-30/RR-34 RESOLVED.132 PHP/814 assertions +5JS and47 browser cases PASS. Both local DBs upgraded;17 business tables unchanged; migrated catalog still hidden. Older v6 pins/audit failures/exceptions below are historical. No production release.

> Latest approved-review application: [report](review-application.md). All category decisions applied:115 products/120 SKUs/67 articles, still hidden.5 missing media references resolved,1 fallback accepted;493 historical orders/2 banners/2 legacy users intentionally excluded.201/201 URL targets mapped.132 PHP/815 assertions +5JS and44 browser cases PASS. Dependency audit remains FAIL under local-only exception; Phase5 READY WITH RISKS. Earlier counts/queues below are historical.

# Phase 4 — xử lý READY WITH RISKS

## Kết quả hiện tại

**PHASE 5 STATUS: READY WITH RISKS** — đủ điều kiện tiếp tục kiểm thử/SEO ở local; chưa đủ điều kiện public staging hoặc production. Đây là đợt xử lý rủi ro sau migration, không phải xác nhận hoàn tất Phase 5.

Đã đóng lỗi URL ảnh nội dung phụ thuộc `http://localhost:8002`: importer mới ghi `/storage/...`; staging đã sửa **124 trường** ở products/articles. Chỉ thay thuộc tính src/poster của file legacy mang tên SHA-256 và thực sự tồn tại. Các byte nội dung khác, chỉnh sửa admin, thời gian ghi nhận, trạng thái xuất bản và lịch sử đơn được giữ nguyên. Không sửa nguồn legacy, không import thêm hoặc publish dữ liệu.

## Bằng chứng và kiểm tra

- [Content repair](evidence/content-risk-closure.json): dry plan và fingerprint; khóa chung với importer; từ chối plan cũ; transaction; apply xong plan rỗng. Toàn bộ row counts/schema của 87 bảng giữ nguyên; checksum các bảng ngoài nội dung giữ nguyên.
- Backup staging trước sửa: **1,863,977 bytes**, SHA-256 `3d5c329a9a4ae9bf7f55f9326ebe10c25484bd2a38297a567352e80dbd0e0271`, timestamp/filename trong evidence. Restore vào DB tạm riêng; đủ 87 bảng, counts/checksums/schema khớp, CHECK TABLE OK; DB tạm đã xóa. **BACKUP VERIFIED**. Dump chứa dữ liệu riêng tư chỉ lưu trong `legacy-private/`.
- [Reconciliation](evidence/content-reconciliation.json): PASS; source fingerprint không đổi, tồn kho và 255 đơn lịch sử giữ nguyên, không tạo live order trong staging.
- [Regression](evidence/risk-closure-tests.json): **128 PHP tests / 783 assertions PASS**, không fail/error/skip. Thêm case URL độc lập APP_URL, idempotency, bảo vệ nội dung native/chỉnh sửa admin/timestamps, plan stale và file thiếu. **5 JS tests PASS**.
- [Browser](evidence/browser-acceptance.json): Chrome desktop14, Edge desktop14, Chrome mobile viewport9 — **37 PASS**. Chọn bài thực có ảnh inline; xác minh ảnh `/storage/...` load thành công ở origin8003 và không còn request ảnh về8002. Giữ nguyên core checkout/native-admin tests. Lần chạy đầu có selector/fixture test chưa đúng, sau đó một lần Chrome gặp ERR_NO_BUFFER_SPACE cục bộ; đã sửa fixture/selector và rerun toàn bộ trên clone sạch PASS. Safari/iOS NOT TESTED.
- [Browser DB verification](evidence/browser-data-verification.json): 3 đơn synthetic trên clone, snapshot/reserved delta chính xác, staging chính vẫn reserved0/255 history/0 live order và catalog DRAFT. Clone/container/user đã xóa.
- [Checks](evidence/risk-closure-checks.json): Pint, Composer validate strict/platform và Exment requirements PASS. [Composer audit](evidence/risk-closure-composer-audit.json) vẫn FAIL; không thêm ignore hoặc tuyên bố package đã vá. RR-30/RR-34 giữ ngoại lệ local theo chỉ định trước của người dùng.

## Risk register hiện hành

| Mục | Evidence / Action | Test result | Final status / thời điểm xử lý |
|---|---|---|---|
| RR-46 URL ảnh gắn origin local, tách từ RR-44 | Relative URLs + guarded repair124 fields + fresh restore verification | PHP, reconciliation, Chrome/Edge/mobile origin8003 PASS | **RESOLVED** |
| RR-28 Browser | Rerun build hiện tại, core/native flow và ảnh inline | 37 PASS | **RESOLVED**; iOS là giới hạn được ghi rõ |
| RR-42 Database backup | Fresh staging dump + SHA-256 + disposable restore87 tables | BACKUP VERIFIED | **RESOLVED** cho local staging; không thay backup hosting |
| RR-30 / RR-34 Dependency | Audit mới vẫn FAIL, giữ exception đã được người dùng chấp nhận | Package remediation chưa có; local regression PASS | **ACCEPTED** chỉ local; phải xử lý trước public staging/production |
| RR-43 Quarantine | Private review queue506 records:2 products,7 articles,493 orders,2 banners,2 users | Đối soát counts/source archive; không tạo dữ liệu giả | **ACCEPTED** trong rehearsal; owner phải duyệt correction hoặc tiếp tục loại trừ trước cutover |
| RR-44 Media/HTML/publication/branding | Tách origin đã sửa; còn6 missing paths, nội dung ngoài/HTML cần duyệt, branding/contact/ngày publish | Tìm ứng viên trùng tên không phân biệt hoa thường cho5/6; chưa phục hồi | **ACCEPTED** khi dữ liệu còn ẩn; cần duyệt trước public staging |
| RR-45 Admin identities |2 tài khoản nằm trong queue; không import MD5 hoặc suy đoán SuperAdmin | Default deny, native local-admin riêng | **ACCEPTED** local; cấp quyền mới theo mapping duyệt trước cutover |
| RR-26 URL parity | Dataset201 URLs:192 targets ẩn +9 quarantine; review queue cụ thể | Chưa bật redirect/publication; chưa chứng nhận HTTP parity toàn bộ | **DEFERRED** sang Phase5 SEO |

## Hồ sơ cần duyệt

[Queue manifest](evidence/manual-review-queues.json) chỉ xuất số lượng/hash; ID/action và file candidates giữ private tại `legacy-private/phase4/content-risk-closure/`:

- `record-review-queue.json`:506 bản ghi, từng legacy ID/reason/action, không kèm contact/password/hash cũ. Không dùng giá hiện tại bù giá lịch sử, không tự đặt quantity hoặc quan hệ category.
- `missing-media-review.json`:6 đường dẫn cùng candidate path/SHA-256/size. Ba ảnh có ba candidate mỗi ảnh, gồm thumbnail với hash khác; hai file khác chỉ lệch hoa/thường. Một ảnh chưa có candidate. Chưa tự chọn thay thế hoặc ghi lại source; basename không chứng minh đúng bản gốc.
- `url-review-queue.json`:9 URL chưa có target do entity quarantine. Không redirect bừa về trang chủ hoặc tuyên bố SEO parity đã pass.

Các quyết định còn cần dữ liệu/business approval: category32 cho product7/10; article category0; banner a2/category28; sửa/loại trừ lịch sử không hợp lệ; quyền2 admin; ảnh/nội dung/branding/contact/ngày xuất bản. Mọi đối tượng vẫn hidden/quarantine trong lúc chờ duyệt. Hosting backup vẫn hoãn theo yêu cầu trước và cần có trước Phase6.

## Chạy lại công cụ

Trong WSL, `python3 scripts/phase4/close_content_risk.py` chỉ hỗ trợ đúng local `bancay_phase4_staging`: maintenance tạm cho8002, dry plan, backup/restore xác minh vào DB mới, apply khi fingerprint không đổi, reconcile và đưa staging lên lại. Không dùng cho production. Đã sửa xong nên plan hiện rỗng; không cần chạy lại chỉ để tạo thêm dump.

`ContentMediaUrls` là helper nội bộ; dùng wrapper để có đủ target/source guards và fresh backup. Importer vẫn SKIP existing IDs; không dùng `legacy:migrate --force` để ghi đè nội dung đã biên tập. Bằng chứng fresh-repeat/rerun cũ mô tả rehearsal trước bước URL repair; không đồng nhất fingerprint cũ với fingerprint mới.

Chưa commit/push, deploy, chuyển DNS hoặc cutover trong đợt này.
