# Kiểm tra UI mapping và gộp demo + dữ liệu migrate

Người dùng yêu cầu kiểm tra mapping UI, giữ cả hai bộ dữ liệu và chọn **“Gộp cả hai bộ dữ liệu vào cùng một website”**. Đã hoàn tất trên **http://localhost:8000**. Không xóa hoặc ghi đè bản ghi demo; staging8002 và backups gốc vẫn được giữ. Chỉ công khai các bản sao sản phẩm/bài viết trên website local có noindex, không deploy production.

## Dữ liệu hiện tại

| Đối tượng | Demo giữ nguyên | Bổ sung từ staging | Tổng ở8000 |
|---|---:|---:|---:|
| Danh mục sản phẩm |6|8|14|
| Sản phẩm |6|115|**121**|
| SKU / tồn kho |22|120|**142**|
| Ảnh sản phẩm |9|347|356|
| Chuyên mục bài viết |2|11|13|
| Bài viết |5|67|**72**|
| Banner |3|21|24|
| Đơn hàng |3 demo|255 lịch sử|258|
| Khách hàng / địa chỉ |2 /1|0 /0|2 /1|

Frontend hiển thị **120 sản phẩm,71 bài viết**, khớp toàn bộ published records qua5 trang sản phẩm và5 trang bài viết. Một sản phẩm/một bài viết demo vốn là nháp vẫn giữ nháp. Những bản sao từ staging được PUBLISHED với thời điểm preview local mới; created_at gốc giữ nguyên. Đây không phải suy diễn ngày xuất bản lịch sử.

8002 tiếp tục là bản staging nguyên dữ liệu nghiệp vụ:115 products/120 SKUs/67 articles/255 lịch sử, DRAFT và reserved0. Không chuyển app8000 sang DB khác hoặc restore đè DB demo. Native identities/passwords/roles vẫn của8000, không copy2 legacy accounts/MD5.493 invalid legacy orders và2 ambiguous banners vẫn được loại trừ theo quyết định trước.

Cấu hình website dùng bộ settings hiện có ở8000 để giữ thương hiệu/contact/UI demo; không tùy tiện ghi đè global keys hoặc lấy logo nguồn khi tên thương hiệu đang khác. Toàn bộ settings nguồn còn ở8002, SQL backup và private source-settings archive. Catalog/content/orders của hai bộ đã gộp, không thể đồng thời kích hoạt hai giá trị khác nhau cho cùng một setting key.

## Mapping và sửa lỗi UI

[Browser mapping](ui-mapping/evidence/mapping.json) **PASS,0 mismatches**. Đối chiếu dữ liệu DB với HTML UI thực, bao gồm pagination, tất cả form và nested SKU fields; không chỉ dựa vào tên bảng hoặc report cũ:

- 8000:121 products/142 inventory/72 articles/14 categories/13 article categories/24 banners/258 orders;244 edit forms,2,488 fields,142 SKU forms,356 gallery references;10 historical snapshots sampled.
- 8002:115 products/120 inventory/67 articles/8 categories/11 article categories/21 banners/255 orders;222 forms,2,266 fields,120 SKU forms,347 gallery references;10 historical snapshots sampled.
- Fields checked: tên/slug/category/status, textareas/SEO/checkboxes/date inputs, giá/sale/SKU/image/stock, settings. Orders grid totals/status và historical item snapshots readonly. Không export customer/contact values hoặc screenshots vào Git; fixtures chỉ private, public evidence dùng counts/IDs/hash và lỗi theo field.

Phát hiện và sửa hai lỗi form: admin-corev7 đặt Blade withoutDoubleEncoding toàn cục nên literal entities như `&amp;` bị decode khi đưa vào form; riêng meta_description settings là input một dòng làm mất xuống dòng. Owned admin forms nay gọi `e(..., true)` rõ ràng; meta_description dùng textarea. Giá trị giữ nguyên qua HTML parsing, nội dung chứa closing-textarea/script không thoát ra thành DOM script. Chỉ chuẩn hóa CRLF→LF khi đối soát theo quy tắc HTML form; không sửa giá trị đang lưu trong DB.

Dataset lớn cũng bộc lộ desktop navigation chỉ lấy5 root categories. Đã thêm **Danh mục khác** dùng Alpine và collection đã load, không thêm query trong Blade. Danh mục vượt top5 truy cập được trên desktop; mobile vẫn đầy đủ. QA flow được cập nhật để đi qua menu và pagination, không ép fixture lên top homepage. Lỗi cú pháp Blade khi thêm nested loop đã được test phát hiện, sửa và rerun PASS; selector test cũng được sửa để chấp nhận URL navigation absolute.

## Cách gộp và bảo toàn dữ liệu

`LocalDatasetMerger` chỉ hỗ trợ đúng local/testing target, preflight unique slug/SKU/order number/legacy ID, không nhận arbitrary table hoặc SQL. Source staging dùng account **SELECT-only** và read-only session. Backups mới của cả hai DB98 bảng có filename/timestamp/size/SHA-256, restore riêng vào DB tạm, so counts/data checksums/schema và CHECK TABLE; DB tạm đã xóa. Demo media có tar/hash riêng. **BACKUP VERIFIED** — [proof và counts](ui-mapping/evidence/combined-merge.json).

Copy trong transaction: remap toàn bộ FK qua ID maps, bỏ generated columns để DB tự tính, tính lại variant combination_key từ option-value IDs mới. Chép raw historical snapshots/timestamps, giữ NULL links gốc, không gọi live-order workflows, không reserve/deduct tồn vì lịch sử. Actor khácNULL hoặc live source orders bị từ chối. Mỗi field sau insert được đối chiếu trước commit để phát hiện truncation/coercion và rollback; fault injection test xác minh không còn bản ghi dở dang.

**775 media files** đã được copy với SHA-256; collision khác nội dung bị chặn, không overwrite. Mọi bản ghi demo trước gộp được kiểm lại giữ nguyên từng field; native application tables không thay đổi bởi merge. Source staging đủ98 tables giữ nguyên ngay sau copy; các GET/login UI về sau có thể thêm native audit records, không thay đổi dữ liệu nghiệp vụ. Source legacy snapshot/ZIP/SQL/extraction không bị sửa.

Private plan/verified backup/receipt ràng buộc source/target/code/media hashes; chỉ apply sau matching dry-run. Receipt có ID maps và provenance; kiểm tra rerun trả **NOOP, CREATED0**, fingerprint không đổi. Wrapper từ chối replay khi đã có receipt; không dùng force hoặc restore để xử lý duplicate.

## Kết quả kiểm thử

- [Full regression](ui-mapping/evidence/tests.json): **137 tests /860 assertions PASS**,0 failure/error/skip. Thêm tests entity-preserving form/XSS, dataset FK/combination/history remap, bảo vệ demo/settings/reservations, collision/no duplicate, rollback và navigation overflow.
- [Browser trên clone của dataset đã gộp](ui-mapping/evidence/browser-acceptance.json): **44 PASS** — Chrome17, Edge17, Chrome mobile viewport10. Core Home→Category→Product→Variant→Cart→Checkout→Success, menu/pagination, gallery/ảnh phục hồi, article/validation/OOS, native category forms.
- [QA verification](ui-mapping/evidence/browser-data-verification.json): chỉ clone tạo3 đơn synthetic, snapshots và reservation delta đúng. Website8000 vẫn3 đơn demo gốc +255 lịch sử; QA DB/user/container đã xóa, không tạo đơn thử vào website chính.
- [Full published frontend coverage](ui-mapping/evidence/combined-frontend.json):120/120 products và71/71 articles, search bằng migrated SKU và local noindex PASS.
- [Old URL smoke](ui-mapping/evidence/combined-redirects.json): **201/201 URLs trả301 đúng mapped path** trên8000. Đây là smoke cho dataset local, không thay full Phase5 SEO/encoding/query/canonical/release certification.
- Pint, Composer validation/audit và5 JS tests PASS. Không thay Composer dependencies trong task này.

## Giới hạn frontend được xác nhận

Mapping admin và core catalog/order/content đã PASS. **Chưa thể nói mọi banner đều đã gắn vào frontend**:10 sidebar +3 content_bottom chưa có template tiêu thụ; home_slider hiện dùng một banner hợp lệ đầu tiên làm hero, chưa phải carousel8 slides. Các21 banner nguồn vẫn INACTIVE; không tự kích hoạt để che khoảng trống. [Coverage record](ui-mapping/evidence/frontend-coverage.json). Giữ phần này trong RR-26 cho Phase5 UI/SEO; dữ liệu không bị mất.

## Truy cập và kiểm chứng lại

- Website gộp: **http://localhost:8000**; admin: **http://localhost:8000/admin/auth/login**. Tài khoản demo/local-admin hiện có giữ nguyên.
- Bản staging nguồn: http://localhost:8002/admin/auth/login, giữ nguyên dữ liệu migrate để đối chiếu.
- `python3 scripts/ui-mapping/verify.py`: kiểm chứng checkpoint gộp, backup/receipt/hash/UI evidence/source preservation; không import hoặc tạo đơn.
- Không chạy lại `merge.py` để gộp lần hai. `merge.php --verify-rerun` là kiểm chứng NOOP; account reader chỉ SELECT, credential private. Old phase evidence là lịch sử; Exmentv7 verifier đã nhận biết merge được người dùng cho phép sau upgrade.

Chưa commit/push hoặc production release trong task này.
