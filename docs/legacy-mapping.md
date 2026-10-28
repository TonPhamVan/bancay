# Legacy → New Mapping — reviewed before Phase 4

Nguồn: [Phase 0 database](phase-0/database.md), [business rules](phase-0/business-rules.csv), [mapping](phase-0/legacy-new-mapping.csv), [scope](phase-0/migration-scope.md), [SEO](phase-0/seo.md). Đây là **thiết kế transformation**, không phải importer đã chạy. New DB chỉ có dữ liệu giả.

## ID strategy: generate new IDs + legacy reference

Không preserve PK cũ: nguồn có gaps, SKU/alias trùng, hai loại banner/slider có ID namespace khác nhau và variants chưa có PK riêng. New IDs giữ quan hệ mới độc lập; legacy_id unique nullable giúp idempotency/debug/đối soát. Old URL không phụ thuộc việc giữ PK mới: resolve từ mapping old entity+ID, không truy vấn bằng new PK đoán từ ID cũ.

Product/category/article/article-category/customer/order dùng legacy_id riêng từng bảng. Variant dùng product legacy reference + legacy_variant_index gốc; simple variant có fingerprint empty selection, không lấy ma làm identity. Banner dùng legacy_source (`tblbanner`/`tblslider`) + legacy_id. Rollback vẫn dùng backup gốc, không export ngược schema mới để giả làm legacy.

## Catalog mapping

| Legacy field | New target | Transformation / validation |
| --- | --- | --- |
| tblsanpham.id | products.legacy_id | Cấp products.id mới; upsert theo legacy_id trong importer sau; không dùng title/SKU để đoán identity |
| title | products.name | Preserve text có kiểm encoding/length; không silently truncate |
| alias | products.slug + URL mapping | Normalize unique ASCII slug; khi trùng/rỗng suffix deterministic theo legacy ID; lưu exact old alias/path ở migration manifest |
| cid | products.category_id | Resolve qua categories.legacy_id; IDs7/10→cid32 orphan: mặc định quarantine, không ghi product cho đến khi có correction/category ẩn được duyệt có provenance; không gán random public category |
| sapo/content | short_description/description | Sanitize HTML, rewrite local media bằng file map; giữ raw original ngoài new public content |
| status / vip | publication status / featured | Import lần đầu luôn DRAFT, published_at=NULL; giữ nguyên status/vip trong private manifest. Chỉ publish sau đối soát và ngày hiệu lực được duyệt; featured không suy diễn popularity |
| created_at | created_at | UNIX timestamp + timezone đúng; không gọi đó là first published_at nếu không có bằng chứng |
| meta_title/meta_des | meta_title/meta_description | Fallback tên nếu rỗng theo rule được giữ; meta_key chỉ archive/REVIEW, không thêm field chỉ để copy keyword cũ |
| image[] | product_images rows | Giữ thứ tự, chọn ảnh chính rõ; folder upload/sp/{mY(original_created_at)}/; validate file, generated safe path, alt theo approved text |
| ma | default variant sku / legacy_sku | Giữ khi unique/hợp lệ; rỗng hoặc trùng→mã deterministic BC-L{legacy_id}-S; giữ mã gốc trong legacy_sku |
| sl | inventories.quantity của simple variant | Opening stock chưa kiểm kê; reserved=0 khi import catalog. Với product có JSON variants, **không cộng sl vào sl_pl** |
| giagoc/giasi | Archive/REVIEW | Handler hiện tại không dùng để ghi/display pricing chính; không phát minh wholesale/cost feature ở Phase 1 |
| view | Archive/REVIEW | Tất cả115 product views=0 trong snapshot; không dùng làm popularity thật |

### Price policy (VND integer)

Không dùng float. Parse digits theo rule grouping đã xác minh trước khi ép int; mọi amount trong 0..9223372036854775807 và tổng/nhân phải kiểm overflow. Không bỏ dấu phân cách tùy ý. Với simple product:

- `giale > giaban > 0`: FIXED, price=giale, sale_price=giaban.
- `giaban > 0` và giale=0 hoặc giale<=giaban: FIXED, price=giaban, sale_price=NULL; giữ inconsistent comparison-price source trong exception report nếu cần.
- giaban<=0: CONTACT, price/sale=NULL; không đưa vào live paid CreateOrder. Không tự xem là hàng miễn phí hoặc lấy giale làm giá bán.

Với variant: price_pl→price, sale_pl rỗng→NULL; chuỗi có dấu phẩy phải parse theo quy tắc grouping, reject ký tự/decimal/negative không có rule; fixed positive và sale<=price. Không tự giảm sale quá cao về price để “làm sạch”. Mọi lỗi vào quarantine/dry-run report, nguyên bản không đổi.

### Normalize phanloai JSON

Đã xác minh 3 products/8 rows; JSON là parallel arrays, không phải một object/SKU mỗi item:

1. Validate JSON object và mảng pl/pl1/pl2/img/price_pl/sale_pl/ma_pl/sl_pl; row-array lengths phải bằng nhau. pl chứa tên nhóm, không cùng độ dài với số variants.
2. Tạo ProductOption cho từng nhóm pl theo thứ tự. Một nhóm thì pl2 rỗng; hai nhóm phải có đủ hai value mỗi row. Không hard-code option1/2 columns; source >2 nhóm đưa REVIEW thay vì bỏ dữ liệu.
3. Tạo unique ProductOptionValue theo (product, option, original value), xử lý whitespace/case collisions bằng explicit review; không merge option names tùy tiện.
4. Mỗi legacy row i tạo ProductVariant, legacy_variant_index=i; SKU unique thì giữ, nếu trùng/rỗng→BC-L{product_legacy_id}-V{i+1}; giữ original ma_pl. **CB-1/CB-2 và SKU đã trùng trong snapshot**.
5. product_variant_values liên kết một value mỗi option; combination_key là SHA-256 sorted new value IDs. Duplicate combination là lỗi, không tạo SKU trùng tổ hợp.
6. img[i] tạo/link owned ProductImage; inventory quantity parse sl_pl[i], reserved=0; không lấy sl product để bù stock variant. Snapshot không phải kiểm kê thực tế.

Simple products tạo một variant không option, không lưu pricing trên Product. Candidate counts trước scope filtering:115 products,112 simple SKUs +8 variants =120 SKUs. Không coi số này là count phải import bất chấp invalid data.

## Category/content/media mapping

| Legacy | New | Rule |
| --- | --- | --- |
| tbldanhmuc | categories | id→legacy_id; name/alias/mota/image/parent/thutu/status/vip; root0→parent NULL; validate tree/FKs/cycles |
| tblchuyenmuc | article_categories | name/alias/discription/image/parent/menu/thutu/meta_title/meta_des/status; giữ article tree đã có dữ liệu |
| tblbaiviet | articles | title/alias/sapo/content/image/cid; menu/featured/view_count; created_at chỉ recorded date (edit cũ có thể đã ghi đè) |
| Missing SEO fields | New SEO fields nullable/fallback | Schema legacy article/product-category không có meta fields mà controller đọc; không fabricate values như dữ liệu đã xác minh |
| Publication | status + published_at | Ban đầu DRAFT, published_at=NULL cho product/category/article/article-category; ngày hiệu lực mới chỉ đặt sau duyệt, không gọi đó là first publish lịch sử |
| tblbanner/tblslider | banners | namespace legacy_source; tblslider→home_slider; tblbanner a0/a1→sidebar, a4→content_bottom. Giữ original slot trong private manifest và sort ổn định theo slot+thutu+legacy ID; category scope không được bỏ. Ban đầu INACTIVE; unknown slot quarantine. starts/ends NULL nếu không có nguồn |
| tblfooter | settings / environment | Approved public name/logo/contact/address/social/SEO text; không import executable analytics HTML; SMTP/keys vào private environment |
| Upload paths | public disk paths | Mapping ở [Phase 0 files](phase-0/files.md); 6 missing references cần exception/approved placeholder; 2.113 files thiếu DB reference không được tự xóa |

## Orders/customers/admin mapping

Một tblorder row → một historical Order + một OrderItem nếu được duyệt nhập. Không có basket/group key để gộp rows. Customer contact/address snapshot lưu độc lập; không tự tạo customer account từ 748 orders hoặc merge theo phone/email. tblkhachhang hiện0 rows; Customer schema vẫn tồn tại cho nhu cầu mới.

- orders: new ID/order number, legacy_id, legacy_status, **is_legacy=true**; preserve original recorded date/contact/address/note với PII controls.
- Policy do người dùng cho phép chủ động: status0→NEW, status1→CONFIRMED. Đây là mapping mới, không chứng nhận completion/paid/shipped lịch sử. Initial import-history entry phải ghi rõ provenance, không tạo workflow events giả ở các ngày không biết.
- Item snapshot: product_name, giaban integer, soluong, phanloai text từ row nguồn; optional current product link chỉ là reference. **Không tính lại giá bằng catalog hiện tại; không tạo SKU lịch sử giả**. SKU nullable nếu không biết.
- 344 rows giá0 /348 quantity<=0 không thỏa constraints new paid orders. Giữ nguyên archive và quarantine; không ép thành1 hoặc trộn vào live revenue/fulfillment. Có thể cần archival storage riêng ở Phase 4 nếu muốn tra cứu cả rows không hợp lệ; không làm schema live yếu đi để nhận mọi row.
- Không dùng CreateOrder/ChangeOrderStatus cho import history: chúng dành cho đơn mới, có reservations. Import phải không gọi email/payment/shipping/reserve/deduct. is_legacy chặn live status mutation.
- Open legacy orders tại cutover phải được đối soát/chuyển xử lý có chủ đích; không tự biến history NEW thành live fulfillment order với reserved=0 hoặc gây giao/trừ tồn hai lần.
- Admin: approved identities qua native Exment user/LoginUser và forced reset/provision; không nhập pass MD5 hoặc role integers. Warehouse/kho chỉ REVIEW vì tblkho không có; không xây multi-warehouse theo một CSV field.

## Foundation cho Phase 4 (chưa implement importer)

Thiết kế dry-run bắt buộc: read-only nguồn → transformation plan → validation/quarantine → mapping/count/file/URL report, **không DB writes hoặc storage writes trong dry-run**. Real import sau này dùng transaction/chunks, unique legacy references và idempotency; log metadata/error codes, không log credentials/PII. Source old URL mapping không bị thay bằng new IDs một cách suy đoán.

Phase 1 không có command `legacy:migrate-*`, không cấu hình production legacy connection, không chạy dữ liệu thật. Mapping này là đầu vào để xây tooling đúng scope Phase 4; các CSV Phase 0 vẫn giữ nguyên làm evidence.

## Review contract 28/10/2026 — design only

- SKU uniqueness theo collation DB, gồm khác hoa/thường. Reserve toàn bộ candidate SKU trước writes; fallback BC-L{id}-S / BC-L{id}-V{index+1} deterministic. Nếu fallback đụng một mã nguồn hợp lệ hoặc tổ hợp duplicate: quarantine cả conflict group và báo identity; không overwrite/suffix ngẫu nhiên. Giữ source SKU và row index gốc trong private manifest.
- History orders/customer/admin import mặc định OFF; chỉ nhận scope được duyệt riêng ở Phase4. Invalid historical amounts/quantity giữ trong private archive và quarantine; không đưa vào live paid tables. 0→NEW/1→CONFIRMED là policy do người dùng cho chủ động thiết kế, không chứng nhận trạng thái lịch sử.
- 6 missing media references: preserve original mapping, quarantine publication hoặc dùng approved fallback; không đánh dấu file đã khôi phục. Không xóa 2.113 files thiếu DB reference. Nhận ảnh dùng chung phải tuân thủ reference checks; import chạy cô lập/maintenance để tránh reference race với cleanup admin.
- Banner positions trên khớp config/website.php; sidebar/content_bottom chưa được render ở mọi public placement hiện tại. Giữ INACTIVE đến Phase5 xác nhận vị trí; không đổi semantics thành hero hoặc bỏ category scope.
- Mọi entity có exact old URL/alias riêng trong manifest; generic resolver hiện tại không chứng minh 201 URL đã migrated. Query-string/encoding/collision parity phải kiểm trong Phase4/5.
- Source read-only → dry-run không writes → mapping/quarantine/count/price/stock/file/URL reconciliation → approved rehearsal → rerun idempotency → backup/rollback rehearsal. Đây là acceptance contract cho tooling sau, chưa có importer, transformer hay actual import trong task review.

Current readiness và risk statuses: [risk review](risk-review-report.md), [register](risk-register.md).
