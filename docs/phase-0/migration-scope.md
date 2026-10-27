# F. Data Migration Scope và Gap Analysis

Phân loại dưới đây quyết định **đầu vào cần giữ** và **định hướng hệ mới**, không thực hiện migration. `KEEP` là giữ artifact/nghiệp vụ phục vụ audit; không có nghĩa chạy lại code legacy. Tất cả dữ liệu gốc được giữ trong backup, kể cả phần không nhập hệ mới.

| Thành phần | Classification | Priority | Phạm vi / nguyên tắc |
| --- | --- | --- | --- |
| Original source/SQL/media/config snapshot | KEEP | MUST | Backup private nguyên trạng + manifest; Git source baseline đã redaction; không public credential/PII |
| Product / variant data | MIGRATE | MUST MIGRATE | Toàn bộ dữ liệu cần thiết; status ẩn vẫn ẩn; normalize variants; giữ ID map/SKU aliases; xử lý rỗng/trùng có provenance |
| Product categories | MIGRATE | MUST MIGRATE | 8 categories; xử lý product7/10 orphan cid32 bằng mapping có ghi nhận; không tự gán category công khai ngẫu nhiên |
| Product/content images | MIGRATE | MUST MIGRATE | Giữ file referenced và liên kết; 6 references thiếu được REVIEW; không xóa file chỉ vì thiếu DB reference |
| Articles + article categories | MIGRATE | MUST MIGRATE | Giữ nội dung/SEO/history cần thiết; sanitize; NULL status không tự publish; preserve URL mapping |
| SEO URLs | MIGRATE/REDIRECT | MUST MIGRATE | 201 DB paths + query patterns; thêm traffic/access-log URL khi có; old alias exact; canonical draft được chốt sau |
| Order history | REVIEW | SHOULD MIGRATE | Giữ đầy đủ archive; chỉ nhập rows đạt validation vào commerce history nếu cần; giữ giá snapshot gốc; quarantine dòng lỗi; không biến import thành order mới cần giao |
| Customer contacts on orders | KEEP/REVIEW | SHOULD MIGRATE with retained orders | Snapshot riêng của từng order; không xuất PII; chưa tạo customer accounts từ suy đoán identity |
| tblkhachhang | REVIEW | OPTIONAL | 0 rows; không có dữ liệu để nhập ở snapshot này; foundation Customer vẫn theo Master Plan |
| Admin identities | REVIEW/REBUILD | SHOULD MIGRATE approved accounts | Hai account; provision/reset; không mang password MD5 sang hệ mới; gán quyền mới có kiểm soát |
| Roles/permissions | REBUILD | MUST for admin | Không copy role integer/guard lỗi; dùng quyền theo nghiệp vụ, default deny |
| Banner/slider | MIGRATE/REVIEW | SHOULD MIGRATE | Giữ assets và cấu hình hữu ích; hidden không tự publish; link validate; slider là placement phù hợp |
| Footer/settings | MIGRATE/REVIEW | SHOULD MIGRATE | Contact/branding/SEO public; analytics/maps/social reviewed riêng; không import HTML script tùy ý để thực thi |
| Contact inbox / old CRM features | REVIEW | OPTIONAL | tblcontact không có; tblkhachhang rỗng; nhiều handlers thiếu; không giả định đã có dữ liệu |
| Old CI runtime/controllers/views | DROP from new runtime; KEEP audit copy | DROP | Không copy source làm nền; nghiệp vụ cần giữ đã inventory |
| CKEditor/CKFinder/vendor samples/debug files | REBUILD/DROP | DROP from new runtime | Không đưa bundle cũ hoặc info.php vào deploy mới; ảnh nội dung thực tế tách riêng |
| Scraper/cron/warehouse/type-customer fragments | REVIEW | OPTIONAL | Chưa có schedule/tables đầy đủ; không tự mở rộng hệ mới thành crawler/ERP |
| Counter/unused files/generated thumbnails | REVIEW | OPTIONAL | Chỉ DROP khỏi scope nhập sau đối chiếu; không xóa nguyên bản |

Mapping field/domain chi tiết: [legacy-new-mapping.csv](legacy-new-mapping.csv).

## Quyết định thiết kế draft đã chốt cho đầu vào Phase 1

Người dùng cho phép “chủ động thiết kế” khi hỏi ý nghĩa trạng thái cũ. Chọn chính sách thận trọng `0 → NEW`, `1 → CONFIRMED`; giữ `legacy_status` và dấu nguồn legacy. Đây là **policy migration mới**, không phải kết luận lịch sử rằng 18 đơn status1 đã được xác nhận hoặc hoàn tất theo quy trình cũ. Không ánh xạ sang COMPLETED, không tạo lịch sử giao hàng/thanh toán giả. Import lịch sử phải tắt email, payment/shipping calls và stock reservation; giữ riêng trong tập dữ liệu historical/review trước khi vận hành.

Định hướng SKU: giữ mã nguồn khi hợp lệ và unique; khi rỗng/trùng, cấp SKU xác định theo legacy product ID + variant index và lưu mã gốc dưới legacy metadata. Không đổi bản gốc, không dùng title hoặc SKU trùng làm khóa idempotency. Giá 0 là contact-only/REVIEW, không là hàng miễn phí. Opening inventory là số liệu chưa kiểm kê, không cộng sl product với sl_pl.

Không coi thiếu bảng cũ là lý do thêm warehouse/multi-store/CRM nâng cao vào Phase 1. Snapshot có dấu vết các nghiệp vụ này nhưng Master Plan vẫn là single store và tồn kho cơ bản.

## Gap analysis

| Feature | Legacy support | New system requirement | Gap | Action | Target phase |
| --- | --- | --- | --- | --- | --- |
| Modular architecture | HMVC; logic trong controller/view | Laravel modular monolith; services/actions độc lập Blade | Không có layer nghiệp vụ tái dùng | Thiết kế module/service boundaries | 1 |
| Product variants | Parallel arrays JSON; SKU trùng | ProductVariant/SKU/option relations normalized | Không có identity/constraints riêng | Schema/relationships và validation design | 1; admin2; import4 |
| Prices | giale thường0; giaban có0; chuỗi tiền variants | Authoritative money model và contact-price policy | Không thể copy giaban như sale cho mọi row | Chốt price/sale semantics + quarantine policy | 1; checkout3; import4 |
| Inventory | sl và sl_pl; không giảm khi nhận đơn | Basic stock integrity | Thiếu atomic update/reservation semantics | Thiết kế nguồn tồn duy nhất và transaction boundary | 1 design;3 checkout |
| Orders | One product per row; status boolean; client prices | Multi-item order snapshots + status histories | Thiếu order header/item/history; snapshot không có SKU | Schema mới; transition actions; mapping legacy riêng | 1/2/3; history import4 |
| Cart/checkout | Views cũ nhưng handlers thiếu; modal nhận đơn | Cart và checkout hoạt động | Thiếu flow thực tế đáng tin | Custom Blade controllers dùng Commerce services | 3 |
| Admin CRUD/workflow | Custom; role guards sai; partial broken views | Exment admin + validation/search/filter/permission | Không thể port handlers nguyên trạng | Exment mapping, guarded mutation actions | 1 mapping;2 |
| Customers | tblkhachhang0rows; order contacts only | Customer/Address foundation; account sau | Không có identity/auth customer tin cậy | Schema Customer trước; account ở enhancement | 1;7 |
| Content/SEO | Schema/meta mismatch; sitemap domain khác | Slug/meta/canonical/status/published_at từ đầu | Thiếu canonical/publish records đáng tin | SEO fields design; metadata fallbacks; redirect inventory | 1 design;3 UI;5 validation |
| Storage/uploads | Public paths; unauth CKFinder; 6 missing references | Safe storage/validated upload + mapping | Thiếu authorization/file validation và coverage | Design media ownership; rebuild uploader; transform paths | 1/2/4 |
| Security/auth | MD5, rawSQL, XSS, CSRF off | Laravel Hash/Policies/CSRF/FormRequests | Security fundamentals phải thay | Thiết kế guardrails; không nhập MD5/unsafe HTML | 1/2/3;5 audit |
| Voucher/review/wishlist | Không thấy implementation | Commerce enhancement sau core | Feature mới | Không thêm Phase0/1 | 7 theo scope được chỉ định |
| Payment/shipping | Không SDK/status/history xác nhận | Integration khi core ổn định | Feature mới | Không đoán hoặc bật API | 7 |
| Server/cron/SSL/DNS | ZIP không có hosting exports | Deploy/rollback có đủ config | Infrastructure coverage chưa đủ | Hoãn theo yêu cầu “backup sau”; hoàn tất trước staging/cutover | Trước6; cấu hình staging khi cần |

Không có migration code, Laravel schema, Exment mapping implementation hoặc frontend mới được tạo trong Phase 0.
