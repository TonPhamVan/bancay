# D. Database Inventory và phân tích Product / Order / User

Nguồn: `caybonmua_db.sql`, đã restore local vào `legacy_audit` trong MariaDB 10.3.39, network none và không publish port. Dump ghi MariaDB 10.3.34/PHP 7.3.33. **Không kết nối DB production.**

[Schema-only SQL](evidence/legacy-schema.sql) chỉ chứa DDL, không có record/credential. [Database summary](evidence/database-summary.json) gồm columns, charset/collation, status, số bản ghi và mẫu product variant không chứa thông tin khách hàng. Bản dump/records đầy đủ nằm ngoài Git.

## Inventory toàn bộ 12 bảng

Tất cả dùng InnoDB; không có foreign key constraint. `id` là PRIMARY KEY và AUTO_INCREMENT của 11 bảng nghiệp vụ; `tbl_count` không có primary key. Không có unique constraint cho SKU/alias/username. Tổng cộng **980 bản ghi**; mỗi số đếm khớp độc lập với số VALUES tuples trong dump.

| Table | Rows | Purpose / PK | Reference fields / cột quan trọng | Used by | Trạng thái / migrate / target |
| --- | ---: | --- | --- | --- | --- |
| tblsanpham | 115 | Product; id | cid→tbldanhmuc; ma/title/alias/image; giale/giaban/giagoc/giasi; sl; phanloai; status/vip/view; created_at/meta_* | Site::show_sp/show_danhmuc/all_sanpham/ajax_pl; Admin::sanpham/add_sanpham; public views | Active source; MIGRATE → Catalog Product/Variant/Image/Inventory |
| tbldanhmuc | 8 | Product categories; id | parent→self; name/alias/image/mota/thutu/vip/status/cron | Site constructor/category; Admin category; menus/home | Active source; MIGRATE → Category |
| tblbaiviet | 67 | Articles; id | cid→tblchuyenmuc; title/alias/image/sapo/content/created_at/status/menu/vip/view | Site::show_baiviet/show_chuyenmuc; Admin article; menus | Active source; MIGRATE → Article |
| tblchuyenmuc | 11 | Article categories; id | parent→self; name/alias/image/discription/thutu/menu/meta_*/status | Site article routes/menus; Admin category | Active source; MIGRATE → ArticleCategory |
| tblorder | 748 | One product request per row; id | product_id→tblsanpham; product_name/giaban/soluong/phanloai; hoten/dienthoai/email/diachi/ghichu/ngaytao/status | Site::show_sp; Admin::order/status and order view | Active source; REVIEW historical migration → Order + one OrderItem per accepted legacy row |
| tbladmin | 2 | Admins; id | role→tblrole; name/fullname/pass/status/image/dienthoai/ngaysinh/kho | Admin login/user management; views | Active source; REVIEW identities → User; rebuild auth |
| tblrole | 4 | Role labels; id | name/status | Admin member form via Admin_model::gettbl | Active labels; REVIEW → Roles/Permissions; behavior differs from names |
| tblbanner | 15 | Advertising; id | cid→category context; name/link/file/type/vip/vitri/status | Admin banner; left/bottom views | Active source; MIGRATE/REVIEW → Banner |
| tblslider | 8 | Home slider; id | title/image/link/content/status | Admin slider; application/views/content.php | Active source; MIGRATE/REVIEW → Banner with placement slider |
| tblfooter | 1 | Site settings/footer; id | name/content/logo/address/contact; meta_*; fanpage/anatic/map/video/status | Site constructor; footer/header/settings form | Active source; MIGRATE approved public settings → System Settings; integration settings review |
| tblkhachhang | 0 | Old CRM/customer table; id | ten/dienthoai/diachi/tientich/thuong/phat/ghichu/type/status | Stale customer views; current controller lacks handlers | Legacy/unknown; OPTIONAL/REVIEW → Customer only if additional real data exists |
| tbl_count | 1 | Old counter; no PK | count | No confirmed application call site | Unknown; OPTIONAL or DROP after usage confirmation |

Chi tiết type/nullability/index/charset xem schema và JSON evidence. Các reference trên là quan hệ suy ra từ code/columns, không phải FK của DB.

## Integrity và chênh lệch schema/code

- Product IDs **7,10** (đều status=1) tham chiếu `cid=32`, nhưng category 32 không có. Quan hệ article category, parent categories, order→product và admin→role không có orphan trong dump.
- Product categories đều parent=0 (8/8). Source hỗ trợ cha/con một cấp khi browse; bài viết có cây parent thực tế. Không suy diễn rằng product category production chưa bao giờ có con.
- 93 products status=1 / 22 status=0; 63 articles status=1 / 3 status=0 / 1 NULL; 6 product categories status=1 / 2 status=0. Không tự biến NULL thành published.
- Code tham chiếu các bảng **không có trong dump**: tblcontact, tbluser, tblkho, tbltypekhach, tblxuat, tblraovat, tblchuyenmucsp, cron_link. Một số nằm ở code cũ không có call site; một số nằm ngay trong member form đang được link. Không tự tạo bảng thiếu để làm code chạy.
- `Site.php:114` đọc meta_* của tbldanhmuc nhưng schema không có các cột này. `Site.php:185` đọc meta_* của tblbaiviet nhưng schema không có. Source models cũ còn dùng uid/catid/hits/ngaydang/tags/nhom/gia không khớp schema hiện có.
- `tblfooter.map` chỉ char(30); hình thức cấu hình thực tế cần đối chiếu với Maps template. `tblbanner` có name nhưng view dùng title. Những điểm này là dấu hiệu mismatch, không phải bằng chứng toàn bộ chức năng không hoạt động.

## Product / Variant analysis

| Thông tin | Dữ liệu / source thực tế | Mapping draft |
| --- | --- | --- |
| Identity | id; ma có thể rỗng/trùng; title | Giữ legacy ID mapping; không dùng ma làm unique key migration |
| SEO | alias; meta_title/meta_key/meta_des; không có canonical/published_at | slug unique đã normalize; meta_des→meta_description; lưu URL cũ; published_at không được tự coi created_at là thời điểm publish |
| Giá | giale là giá gạch/nguyên trong UI; giaban là giá hiển thị/nhận đơn; giagoc/giasi có cột nhưng handler hiện không ghi | Bảo toàn ý nghĩa giá thực tế; không gán mọi giaban thành sale_price một cách máy móc |
| Gallery | image là JSON array tên file | ProductImage rows theo thứ tự; đường dẫn upload/sp/{mY(created_at)}/{filename} |
| Tồn | sl ở product; sl_pl ở variant; không thấy decrement/locking khi nhận đơn | Opening inventory chưa được xác minh; không cộng hai nguồn hoặc tự giả định reservation |
| Content | sapo/content HTML | Giữ nội dung cần thiết; sanitize HTML và rewrite đường dẫn ảnh ở migration sau |
| Status / priority | status=1 public; vip sort/category home; toàn bộ view product=0 trong dump | Tách publish state và featured; popularity không phải tín hiệu sử dụng thật trong snapshot |

**112/115 products không có phanloai JSON. 3 products (IDs 4,5,127, đều public) có tổng 8 variants.** Không có JSON lỗi hoặc lệch độ dài 7 mảng variant trong 3 record này.

JSON thực tế là **các mảng song song**:

- `pl`: tên nhóm thuộc tính (1 hoặc 2 phần tử), không phải danh sách SKU.
- `pl1[i]`, `pl2[i]`: giá trị thuộc tính của dòng i; `pl2[i]=""` khi chỉ một nhóm.
- `img[i]`: ảnh variant cùng thư mục tháng với product; không có ảnh thì frontend dùng images/noimg.jpg.
- `price_pl[i]`, `sale_pl[i]`: chuỗi tiền có dấu phẩy, ví dụ `100,000` và `80,000`.
- `ma_pl[i]`, `sl_pl[i]`: mã và số lượng ở dòng i; sl_pl cũng là chuỗi.

Mẫu đại diện đầy đủ nằm trong JSON summary; không sửa dữ liệu gốc:

| Product ID | Nhóm / tổ hợp | Số dòng | Điểm cần giữ/REVIEW |
| --- | --- | ---: | --- |
| 4 | pl=[ab,cd]; a/c, a/d, b/c, b/d | 4 | SKU CB-1…CB-4; 100.000 regular; 80.000–96.000 sale; stock 1/2/3/4 |
| 5 | pl=[ab]; a và b; pl2 rỗng | 2 | SKU CB-1/CB-2 trùng product 4; stock 100/50; không bảo đảm unique toàn hệ thống |
| 127 | pl=[Màu sắc]; Xanh/Đỏ | 2 | Cả hai ma_pl đều SKU; duplicate ngay trong cùng product; cần SKU mới và mapping rõ |

`js/index.js:14` giới hạn tối đa hai nhóm; `:44` xây cartesian product; `:100` có thể áp cùng một SKU cho tất cả dòng. `Admin.php:913` ghi các mảng theo index. `Site.php:237` chọn variant bằng cặp giá trị, không theo SKU. Chuỗi tiền phải được validate và parse có quy tắc tại Phase 4; không dùng float hoặc chuyển chuỗi bằng ép kiểu PHP.

Vấn đề dữ liệu: **15 ma rỗng**, mã `2` lặp 3 lần, **2 nhóm alias trùng**, SKU variant trùng nêu trên, 31 product giaban=0 và 111 product giale=0. Giá 0 được list hiển thị “Liên hệ” (`application/views/content.php:63`); không tự cho checkout miễn phí. Product detail lại có thể hiển thị 0đ nên cần quyết định nghiệp vụ rõ ràng.

Draft chuyển giá: nếu giale>giaban>0 thì price=giale, sale_price=giaban; nếu giale=0 nhưng giaban>0 thì price=giaban và sale_price=NULL; giaban=0→REVIEW/contact-only, không lấy giale thay giaban tùy tiện. Giagoc/giasi chưa có use case xác nhận. Đây là đề xuất cho Phase 1/4, chưa là transformer đã triển khai.

Đích dự kiến: 115 Product; 112 simple SKU + 8 variant SKU = 120 SKU **trước khi lọc publish/migrate scope và làm sạch**. Option names/values và variant-image liên kết cần bảng riêng. Không lưu lại nguyên khối JSON làm cấu trúc variant hệ mới.

## Order analysis

- `Site.php:29–51`: POST vào product detail tạo **một tblorder row cho một sản phẩm**. Không có order_items, group/cart key, payment/shipping entity hoặc history table trong 12 bảng.
- Có snapshot **product_name, giaban, soluong, phanloai dạng text**. Snapshot không đáng tin làm giá kế toán: tên/giá/product_id/variant/quantity đều đến từ POST, không được server tính lại từ SKU. Không có SKU snapshot; không được điền SKU hiện tại rồi gọi đó là SKU tại lúc mua.
- Customer data nằm trực tiếp trên order: hoten, phone, email, address, note. Không có customer_id hay FK sang tblkhachhang. Không xuất PII trong báo cáo/Git.
- Status mới=0; admin status toggle đổi 0↔1 (`Admin.php:1114`), UI chỉ dấu tick. **Không xác định được ý nghĩa lịch sử của status=1 chỉ từ code.** Theo chỉ định “chủ động thiết kế”, chọn policy migration `0→NEW`, `1→CONFIRMED` và giữ legacy_status; không suy diễn đã COMPLETED hoặc đã thanh toán/giao hàng.
- Không có payment_status, gateway reference, shipping fee/carrier/tracking hay địa chỉ giao hàng cấu trúc. Có địa chỉ text và điện thoại liên hệ; không suy diễn có shipping integration.
- 748 rows: status=0:730, status=1:18; chỉ 3 có variant text. **344 giá bằng 0; 348 số lượng <=0.** Có thể có spam/test/đơn lỗi, nhưng chưa được xác nhận. Không tự sửa hoặc xóa các dòng này.
- Form delete gọi `admin/del_order` nhưng method không tồn tại; detail handler chọn view chitiet_don không có trong archive. Cart/order legacy views tham chiếu controller/columns không còn có.

Đề xuất migration lịch sử: giữ toàn bộ dump làm archive; nếu cần nhập hệ mới, mỗi legacy order hợp lệ thành một Order + một OrderItem, bảo toàn snapshot và thời gian; legacy_id/idempotency mapping rõ. Dòng lỗi đưa vào danh sách REVIEW/quarantine để đối soát, không làm mất dữ liệu hoặc tự reprice theo catalog mới. Không nhóm các rows thành đơn nhiều sản phẩm bằng suy đoán; import history không kích hoạt email, thanh toán, giao hàng hoặc giữ/trừ tồn. Policy status đã chốt theo quyền chủ động thiết kế; quyết định thực thi nhập lịch sử nằm ở scope Phase4.

## User / role analysis

tbladmin có 2 accounts, đều status=1/role=1. pass đều chuỗi 32 hex; `Admin_model.php:195` thực sự so MD5 đầu vào. Không ghi tên đăng nhập/hash/PII vào báo cáo. Role không được suy ra chỉ từ nhãn:

| Role value | Nhãn trong tblrole | Guard thực tế | Chức năng được phép theo code |
| --- | --- | --- | --- |
| 1 | admin | checkrole cho qua | Catalog, article categories, footer; và toàn bộ nhóm chỉ checklogin |
| 2 | Quản lý | checkrole từ chối | Chỉ nhóm checklogin: dashboard/orders/banner/slider/articles và các handler user không có role guard |
| 3 | Nhân viên | checkrole cho qua | Giống role 1 ở handler có checkrole; không thể coi là “ít quyền” |
| 4 | Viết bài | checkrole từ chối | Nhóm chỉ checklogin vẫn bao gồm user CRUD; phạm vi vượt nhãn role |
| 5 | Không có row trong tblrole | Guard cho qua; UI có nhánh role=5 | Legacy code path, chưa có account/role data tương ứng; REVIEW |

`checklogin` chỉ kiểm tra session name; login không lọc status. User CRUD chỉ checklogin; server nhận role/status từ POST, trong khi UI chỉ hiện role selector cho role1/5. Có thể thay đổi role ngoài UI nếu có account thấp quyền; security finding riêng. Member form còn query tblkho không tồn tại.

Đề xuất authentication: provision admin mới bằng Hash chuẩn của Laravel/Exment và quy trình reset mật khẩu; không migrate MD5 thành password mới hoặc giữ wrapper MD5 vô thời hạn. Ánh xạ quyền từ quyền nghiệp vụ được duyệt, tách identity customer/admin; không tái sử dụng role integers. Warehouse/kho chỉ REVIEW vì không có bảng warehouse hay nghiệp vụ tồn đa kho được xác nhận.

Luồng đổi password cũng có dấu hiệu lỗi tại `Admin.php:1076`: điều kiện bọc kết quả so sánh trong MD5; MD5 trả chuỗi không rỗng nên nhánh hash lại luôn có thể chạy. Không lấy handler này làm mẫu logic cập nhật mật khẩu ở hệ mới; cần flow reset/change password chuẩn.
