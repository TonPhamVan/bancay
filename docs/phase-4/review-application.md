> Current stack: **Exment7.0.0 / Laravel12.70.2 / admin-core1.0.1**, PHP8.3.36. [Upgrade report](../exment-v7-report.md). Composer audit PASS (0 advisories/0 abandoned), npm audit0; RR-30/RR-34 RESOLVED.132 PHP/814 assertions +5JS and47 browser cases PASS. Both local DBs upgraded;17 business tables unchanged; migrated catalog still hidden. Older v6 pins/audit failures/exceptions below are historical. No production release.

> Cập nhật quyết định: người dùng đã chỉ định **“1 ảnh thiếu bỏ qua”**. Giữ fallback của article52 và đóng mục tìm ảnh/chờ duyệt này; không còn yêu cầu phục hồi ảnh đó. [Evidence](evidence/media-waiver.json). Các phần kiểm thử và số liệu dữ liệu phía dưới không thay đổi.

# Áp dụng các quyết định duyệt Phase 4

Người dùng đã duyệt từng mục Q01–Q06 và sau đó chỉ định **“làm tất cả theo đề xuất của bạn”**. Đã áp dụng lên local staging `http://localhost:8002`; không sửa source legacy, production, DNS hoặc công bố dữ liệu. [Danh sách quyết định](review-decisions.md).

## Đã thực hiện

| Đối tượng | Quyết định / kết quả |
|---|---|
| Product7 — Cây Phú Quý 01; product10 — Cây lưỡi hổ 01 | Gán category legacy33 — Cây cảnh Nội thất; DRAFT |
| Articles29,31,48,70 | Gán article category legacy9 — Kiến thức về cây; DRAFT. Bài31 tuân theo lựa chọn của người dùng, không dùng đề xuất phong thủy trước đó |
| Articles63,67,69 | Gán article category legacy16 — Cách chăm sóc cây; DRAFT |
|5 tham chiếu ảnh thiếu |3 ảnh trong nội dung product8 dùng các bản đầy đủ đã xác minh cùng tên/hash, không dùng thumbnails.2 đường dẫn product102 chỉ lệch hoa/thường trỏ tới cùng một ảnh đã có trong gallery; xác minh alias, không thêm bản trùng |
|1 ảnh còn thiếu trong article52 | Giữ fallback; không tạo ảnh giả hoặc ghi là phục hồi bản gốc |
|493 đơn lịch sử không hợp lệ | Chấp nhận giữ ở archive/quarantine; không tự điền giá, quantity/contact hoặc ghi thành live order |
|2 banner không rõ category/placement | Giữ cách ly;21 banner đã nhập vẫn INACTIVE |
|2 legacy admin accounts | Chấp nhận không migrate; không copy MD5 hoặc tự cấp quyền; local-admin mới vẫn hoạt động |
| Publication/branding | Giữ toàn bộ catalog/content DRAFT và published_atNULL; chưa phê duyệt công bố thương hiệu/contact lên public |

Kết quả: **115 products,120 variants/SKUs,347 product-image rows,120 inventory,67 articles,255 historical orders/items/histories**. Thêm2 products/2 variants/2 inventory/5 product-image rows và7 articles; sửa một trường description của product8. Catalog/content không còn quarantine vì thiếu category. Còn **497 root exclusions đã được chấp nhận**:493 orders +2 banners +2 identities. Archive nguyên bản còn đủ, không xóa source rows.

201 old URLs hiện có **201 mapped targets,0 target thiếu,0 duplicate source paths**. Đây là dữ liệu chuẩn bị SEO; full HTTP301/query/encoding/publication parity cần làm trong Phase5, chưa bật redirect production. [URL map metadata](evidence/review-seo-url-map.json).

## Guard và backup

[Private approval manifest](../legacy-migration.md) giới hạn đúng target local staging, source fingerprint, category cũ→mới, media replacement paths/SHA-256 và hai historical NULL links đã có từ trước. Importer không sửa source row, giữ source hash nguyên gốc, và receipts bind nội dung review; thay quyết định sau dry-run làm receipt không còn hợp lệ. Existing IDs vẫn SKIP, không generic-upsert giá/nội dung đã biên tập.

Wrapper đặt staging maintenance tạm, dry-run không DB/public-file writes, tạo backup mới với timestamp/filename/size/SHA-256, restore vào DB tạm độc lập, so counts/schema/checksum đủ87 bảng và CHECK TABLE OK rồi xóa DB tạm. Chỉ sau đó mới apply khi target fingerprint vẫn khớp. **BACKUP VERIFIED** — thông số và hash trong [application evidence](evidence/review-application.json); dump và credentials chỉ nằm trong `legacy-private/`.

Sau insert, phần đối chiếu đầu tiên phát hiện hash SHOW CREATE thay đổi do AUTO_INCREMENT counters tăng hợp lệ; đã sửa verifier để chỉ bỏ qua đúng counter này khi so cấu trúc. Không bỏ qua columns/constraints/indexes/FKs. Đã restore lại pre-change dump vào DB tạm để kiểm tra độc lập; structural schema giữ nguyên. Không replay import hoặc restore đè staging để xử lý verifier.

Checksum của **orders/order_items/order_status_histories** giữ nguyên tuyệt đối. Hai NULL product links của historical orders80 và163 được giữ nguyên dù products7/10 nay được bổ sung; reconciler chỉ cho phép đúng cặp legacy order→product đã ghi nhận trong manifest, không bỏ qua reference mismatch nói chung. Giá, quantity, SKU/snapshot/contact/status history không bị sửa, không reserve/deduct tồn kho cũ.

Media repair chỉ áp dụng khi HTML hiện tại khớp bản source đã sanitize trước đó; nếu có manual edit khác thì dừng và giữ edit. Gallery chỉ thêm path chưa có, giữ primary/order/metadata cũ. MIME/decode/size/pixels/hash/containment vẫn bắt buộc; không copy hoặc thay file trong source extraction.

## Kiểm chứng

- [Apply / backup / checksums / idempotent rerun](evidence/review-application.json): PASS. Rerun CREATED0, không quarantine catalog/content, fingerprint giữ nguyên, media repair plan rỗng.
- [Full reconciliation](evidence/review-reconciliation.json): PASS trên mọi imported product/article, category mapping, variants/options/prices/inventory, historical snapshots/dates và referenced media.
- [PHP regression](evidence/review-tests.json): **132 tests /815 assertions PASS**,0 failure/error/skip. Bao gồm receipt binding khi đổi quyết định, source immutable, media hash/source drift, bảo vệ manual edits/timestamps, media idempotency và historical NULL allowlist chính xác.
-5 JS tests PASS. [Checks](evidence/review-checks.json): Pint, Composer strict validate/platform và Exment requirements PASS.
- [Browser](evidence/browser-acceptance.json): **44 PASS** — Chrome desktop17, Edge desktop17, Chrome mobile viewport10. Kiểm tra native forms2 products/7 articles đúng category và DRAFT, ảnh inline đã phục hồi, core checkout/gallery/article/validation/OOS/responsive. Safari/iOS NOT TESTED.
- [Browser data](evidence/browser-data-verification.json):3 synthetic orders trên clone đúng snapshot/reservations. Staging chính vẫn255 history/0 live order/reserved0/catalog hidden. Clone/container/account đã xóa.

## Rủi ro còn lại và phạm vi

[Current risk register](evidence/risk-register.json) ghi nhận việc chọn xử lý dữ liệu đã hoàn tất; không còn chờ câu trả lời phân loại từng bản ghi.493 invalid historical orders/2 banners/2 identities tiếp tục được loại trừ theo chính sách đã duyệt, không được mô tả là đã sửa dữ liệu gốc.1 ảnh dùng fallback, nội dung đã sanitize và branding/publication vẫn cần kiểm thử/duyệt khi làm public release ở Phase5.

[Dependency audit](evidence/review-composer-audit.json) vẫn **FAIL**; RR-30/RR-34 giữ ngoại lệ local theo quyết định hoãn trước. Không nâng stack hàng loạt hoặc thêm audit ignore. Những quyết định dữ liệu ở đây không phải cho phép public staging/production. Hosting backup tiếp tục hoãn theo chỉ định trước, phải có trước Phase6.

Không chạy lại historical fresh-repeat script để đối chiếu fingerprint113-products với staging hiện tại115-products; bằng chứng repeat/rollback cũ mô tả rehearsal trước khi duyệt. `apply_review_decisions.py --verify-existing` thực hiện kiểm chứng lại hiện trạng với backup trước sửa mà không replay9 additions. Chưa commit/push trong đợt này.

**PHASE 5 STATUS: READY WITH RISKS**
