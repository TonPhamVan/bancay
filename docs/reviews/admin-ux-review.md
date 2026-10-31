# Admin UX, Exment Native & Improvement Review

Ngày30/10/2026, Asia/Saigon. Baseline **93c581c**, nhánh feature/develop, sạch trước review. **Chỉ review/test/đề xuất; không sửa application code, schema, cấu hình, plugin hoặc production.** Các thao tác tạo/sửa để test chỉ trên owned QA8004, không áp dụng backlog. Source preservation và cleanup ghi ở cuối evidence.

## 1. Executive Summary

Admin đáp ứng nghiệp vụ cốt lõi và nhân viên có thể quản lý catalog/đơn sau hướng dẫn. Chưa đạt trải nghiệm nhất quán cho nhân viên không rành kỹ thuật: form HTML, lỗi/enum Anh–Việt, publication tách khỏi SKU, thiếu preview và cảnh báo hoàn thiện dữ liệu. Matrix mới giải quyết việc chỉnh4 SKU trên nhiều màn hình; không còn ghi nhận BA-G02 là chức năng thiếu.

**Khuyến nghị Option B, triển khai tăng dần sau review.** Giữ native identity/roles và các domain Actions. Ưu tiên UX, ý nghĩa dashboard và hợp nhất cách nhập liệu; thử native admin-core Form/Grid trên một module nhỏ trước. Không chuyển catalog/order sang CustomTable JSON hoặc bỏ6 plugin chỉ để giảm số plugin.

## 2. Current Admin UX Assessment

Điểm1–5 là đánh giá heuristic của reviewer từ source và UI, **không phải usability study của nhân viên**. Thời gian học/tốc độ làm việc thực tế: NOT MEASURED.

| Tiêu chí | Điểm | Bằng chứng / lý do |
|---|---:|---|
| Dễ học |3|Login vào native dashboard với widget Not Registered, phải chọn Tổng quan cửa hàng; hai tầng menu, thuật ngữ options/SKU/slug |
| Dễ sử dụng |3|CRUD và matrix dùng được; ảnh/options/basic có nút Lưu riêng; HTML editor cần kiến thức nội dung |
| Tốc độ thao tác |3|Matrix lưu4 SKU một request; publish vẫn phải quay form Basic; không next-order/saved filter |
| Tính nhất quán |3|Native en + custom vi; Nháp trong select nhưng DRAFT trong list; currency admin100,000 khác frontend100.000 |
| Hạn chế lỗi |4|Native auth/Gates/CSRF, stock/version locks, duplicate/owner guards, xác nhận xóa; cancel chưa có xác nhận riêng |
| Tìm kiếm dữ liệu |3|Tên/SKU/mã đơn/phone, status/date/category; thiếu OOS filter, source legacy filter và saved views cho domain |
| Trực quan |3|Có thumbnail/counters/matrix; thiếu readiness checklist, preview, ảnh SKU chọn bằng ID/alt |
| Màn hình nhỏ |2|12 page/viewport checks không body overflow; matrix1180px phải cuộn trong vùng, forms dài/menu lặp, chưa test thao tác số lượng SKU lớn trên điện thoại |

### Module matrix

| Module | UX score | Đã đáp ứng / vấn đề | Recommendation | Priority | Evidence |
|---|---:|---|---|---|---|
| Product |3|Basic/SEO, draft/publish/archive; create→detail→edit publish, không preview/readiness tổng |AR03/05/06; native widgets cần adapter giữ Actions |P1|product-form.png; StoreResources; form/product views |
| Category |3|Tree/slug/status/date/image; default draft an toàn; thiếu required hint, preview tree theo nhánh |AR02/16; config native không bind domain trực tiếp |P2|validation.png; RecordEditor; CategoryTreeService |
| Product Options |3|Textarea mỗi dòng, hai nhóm hoạt động; duplicate error làm mất input vừa nhập |AR01/04; giữ cấu trúc đã dùng, thêm input chips/rows có kiểm soát |P1|option-error.json/png; product.blade.php |
| Variant/SKU |4|4 combinations, bulk giá/tồn/status, ảnh, stale guard; bảng rộng, SKU tự sinh khó đọc |AR04/05/07; giữ matrix/Action, không generic edit combo |P1/P2|matrix.png; UpdateVariantMatrix; fresh tests |
| Inventory |3|Thực tế/giữ chỗ/khả dụng, reason/expected quantity; không OOS filter hoặc ledger trong UI |AR08/14; không thay daily logs bằng ledger giả |P1/P2|inventory.png; StockAdjustment; StoreQueries |
| Orders |3|Search/status/date/snapshot/history đúng; status code tiếng Anh, cancel một lần submit; không next-order/legacy badge |AR02/09/10 |P1/P2|order-list/history.png; order.blade.php |
| Customers |3|Profile/address/default/order count; chưa tách quyền export PII, form đơn giản |AR13/16; identity native khác customer business |P2|customer-form.png; CustomerAddresses; permission tests |
| Articles |2|HTML/SEO/publication/upload đầy đủ; editor textarea HTML khó cho content staff |AR06; editor an toàn + preview, không tự thêm media manager |P1|article-form.png; RecordEditor/Purifier |
| Banners |3|Position/category/schedule/image/link; thiếu preview placement và contextual help |AR11; không duplicate gallery/static hero vào banner DB khi chưa chốt quản lý nội dung |P2|banner-form.png; website.banner_positions |
| Settings |3|9 public keys an toàn; không secret; thiếu help cho brand/meta/map/logo |AR11/16; không thay bằng native System Settings cấp quyền rộng |P2|settings.png; SettingsEditor |
| Users/Roles |3|Native User/LoginUser/RoleGroup/presets; Access plugin tương đương manage module, cần hiểu3 màn hình |AR12/13; hướng dẫn least privilege trước, granular permissions sau |P1/P2|roles.png; StoreAccess; StoreAdminGuard |
| Dashboard |2|Có business KPIs; landing khác; public count121 vs visible120 trên QA có fixture; recent trộn legacy/live |AR02/15; chuẩn hóa nghĩa dữ liệu và landing |P1|native-home.png; runtime.json; StoreQueries::dashboard |

Evidence filenames trong bảng nằm dưới [evidence/](evidence/). Native feature presence không được coi là đã cấu hình/thử thành công trên domain.

## 3. Product/Variant UX Assessment

Fresh browser PASS: tạo category15; tạo product122 **DRAFT**; upload ảnh fixture; nhập Kích thước=Nhỏ/Lớn, Chậu=Trắng/Nâu; generate4 SKU; bulk giá100k/tồn10/statusACTIVE, gán ảnh357; save matrix một lần; edit Basic để publish; frontend chọn Lớn/Nâu giá100k, qty2 đặt đơn200k.

Từ form sản phẩm đến publish có **7 lần submit ghi dữ liệu** trong tình huống đã chạy: Basic1, upload1, options2, generate1, matrix1, publish1; không tính nhập field/expand/di chuyển. Tổng click/thời gian thao tác: NOT MEASURED. Matrix4 SKU chỉ cần1 save batch; không cần4 form giá+4 form stock như checkpoint cũ.

**AR01 VERIFIED ISSUE:** nhập `Nhỏ\nNhỏ` cho nhóm hiện có, save bị duplicate validation; textarea render lại `Nhỏ\nLớn`, mất input lỗi. Source binding lấy `$option->values`, không dùng old-input theo group. Matrix giữ input tốt hơn form option; cần thống nhất cách xử lý, không nới constraint để “sửa UX”.

Generator additive/default INACTIVE/CONTACT/0 đúng, nhưng chưa preview số tổ hợp và danh sách thiếu trước submit. Sau có SKU không thêm nhóm/đổi used value là guard có chủ đích; nên giải thích/tạo sản phẩm mới, không bỏ guard. Lưu nháp là lưu explicit, không autosave. No-JS fallback có source noscript, chưa chạy browser tắt JS riêng trong review.

## 4. Order/Inventory UX Assessment

Order260 snapshot100k×2=200k, history NEW→CONFIRMED→CANCELLED, sau hủy physical10/reserved0 được [DB kiểm độc lập](evidence/runtime.json). Filter mã đơn trên list PASS. Từ filtered list:1 click mở detail, submit mặc định CONFIRMED; để hủy chọn CANCELLED +submit. Ghi chú optional. Đây là số control activations của flow này, không suy thành throughput nhân viên.

Cancel không có dialog/reason bắt buộc riêng; đây là **VERIFIED IMPROVEMENT OPPORTUNITY**, không phải exploit hoặc lỗi stock. Không đề xuất bulk ship/cancel ngay; hành động đó cần expected-status từng đơn, eligibility preview, idempotency/history và rollback toàn phần/chiến lược partial failures.

Inventory available đúng domain và frontend; fixed threshold≤5 trên dashboard, danh sách stock chưa có filter OOS. Adjustment logs actor/before/after/reason sau commit; chưa có transaction ledger/query UI. Orders legacy bị guard không chạy live workflow; recent-order không gắn badge khiến staff có thể nhầm việc cần làm.

## 5. Current Plugin Inventory

[Inventory đầy đủ](plugin-inventory.md).6 page plugins1.0.0 active, runtime Plugin.php khớp source; mỗi wrapper chỉ chọn MODULE và dùng StorePage. Không có6 bản business logic riêng. Composer packages và Alpine/Tailwind/Vite là dependencies, không phải plugin Exment. Không phát hiện native auth/customer mirror tự xây.

## 6. Exment Native Capabilities

[Native assessment](exment-native-vs-plugin.md) đối chiếu source installed **Exment7.0.0/ref bf795a9…**, admin-core1.0.1. Native có CustomTable/Column/Form/View/Relation/filter/role/workflow/dashboard/import/export/files/notify/API. Runtime CustomTable registry có13 platform tables, không có catalog/orders mirror. API native tắt. Việc có capability không chứng minh generic Form/Workflow gọi đúng commerce Actions.

## 7. Unnecessary Customizations

Không kết luận plugin nào “dư thừa có thể xóa ngay”. Boilerplate route10 entries giống nhau ở6 config, một số route không dùng ở Dashboard/Settings; controller270lines điều phối nhiều entity; custom list/form tái tạo các primitive có trong admin-core. Đây là cơ hội **SIMPLIFICATION**, chưa là bug security. Không nên thêm abstraction chỉ để giảm số dòng hay gộp plugin làm mất plugin-ID role grants.

## 8. Native Replacement Opportunities

**KEEP NATIVE:** identity/login/role editor, CSRF/shell/routing/operation masking. **KEEP PLUGIN – JUSTIFIED:** normalized catalog, order/inventory invariants, public settings. **SIMPLIFY CUSTOM / NEEDS VERIFICATION:** thử admin-core Grid/Form cho Category/ArticleCategory trước; có constructor Eloquent nhưng save/upload/tree phải chuyển qua Actions, không default ORM writes. Chưa có replacement nào được test tương đương để khuyến nghị REPLACE WITH NATIVE ngay.

## 9. Architecture Simplification

Frontend không gọi Plugin classes; tái sử dụng Models/Actions, chỉ có history actor coupling native LoginUser. Giữ một nguồn relational data. Có thể chia StoreController theo read/command/export khi tiếp tục mở rộng; hiện270lines, không coi line count là blocker. Validation lặp FormRequest/Action là defense có chủ đích; chia shared rules như matrix thay vì xóa validation nội bộ.

## 10. UX Improvement Opportunities

Top10 theo giá trị: **AR01** giữ input/errors options; **AR02** thống nhất ngôn ngữ; **AR03** checklist trước publish/preview; **AR04** option rows +combination preview; **AR05** matrix ảnh thumbnail/compact; **AR06** editor nội dung/SEO preview; **AR08** stock filters; **AR09** cancel confirmation; **AR10** queue xử lý đơn; **AR15** dashboard đúng nghĩa. [Backlog](improvement-backlog.md) ghi acceptance, source, effort/value/risk cho từng mục.

## 11. Business Process Improvements

Đề xuất nháp→ảnh/options→giá/tồn→kiểm tra→publish. Checklist hiển thị các lý do chưa bán được, có warning cho missing image/category hidden/CONTACT/inactive; không tự đổi các business rules được phép CONTACT. Đơn theo state machine hiện tại; không tạo thêm native approval workflow buộc staff duyệt hai lần khi chưa có nghiệp vụ phân cấp.

## 12. Security/Performance Improvements

Fresh180 PHP/1187 assertions,0 fail/skip;5 JS PASS; Composer/npm audit0. Backend role/IDOR/upload/stock/concurrency/snapshot tests nằm trong [case inventory](evidence/test-cases.json); UI lần này đăng nhập bằng system admin clone, **không giả PASS manual UI cho từng manager role**. Không thay personal credential/grant của main. Granular view/write/export/org inheritance là scope debt, không tự coi là exploitable High.

25-row pagination, eager category/images/variants/inventory và customer withCount hiện có; không thấy per-row query trong Blade. Không đo fresh admin query latency/N+1 tại runtime lần này; không khẳng định performance regression hoặc thêm cache khi chưa có bằng chứng. Product detail tải toàn bộ variants, config giới hạn generate256 mỗi lần; cần workload thực trước tối ưu hoặc virtualization.

## 13. Quick Wins

Native menu/role hygiene trong phạm vi preset; đưa cửa hàng thành điểm đến rõ ràng; labels/status/help tiếng Việt; cancel warning; legend ảnh SKU. Những phần thuộc Blade cần minimal custom thay vì gắn nhãn “config only”. Không đổi default locale sang vi mù quáng: package/translation coverage cần kiểm; source hiện `exment.locale=en`.

## 14. Improvement Backlog

[20 đề xuất + acceptance gates](improvement-backlog.md). Phân biệt VERIFIED ISSUE, VERIFIED IMPROVEMENT OPPORTUNITY, PROPOSED IMPROVEMENT và NEEDS VALIDATION. Không tự biến P1 thành P0. Owner: Product Owner ưu tiên/accept; Tech Lead xác minh native adapters; QA kiểm invariant và usability; store staff tham gia UAT.

## 15. Option A/B/C Comparison

[So sánh](improvement-options.md): A giảm friction ít nhất; B giữ domain/permissions, sửa luồng có giá trị và thí điểm native widget; C bổ sung ledger/approval/automation khi business cần. Không đặt mục tiêu “bỏ N plugin” hoặc rewrite storage.

## 16. Recommended Option

**B**, tách B1 UX/content safety trước release và B2 proof-of-concept native widgets sau khi B1 ổn định. B2 chỉ chuyển một module nếu parity/rollback chứng minh được; không cản release chỉ vì chưa refactor. Native sử dụng PARTIAL ở UI metadata là đánh giá đúng; giữ custom business rules là lựa chọn có cơ sở.

## 17. Before Phase 6 Actions

Khuyến nghị cao AR01/02/03/09/15 và một vòng UAT nhân viên trên catalog thật. Đây là recommended work, không tự tạo blanket release blocker. **Acceptance gate đang mở:** BA-V01 chưa có fresh Chrome/Edge cho build matrix; BA-G04 catalog thật chưa có, owner đã xác nhận. Không tự accept hai gate này bằng review heuristic hoặc dữ liệu synthetic.

## 18. Post-Release Improvement Roadmap

[Roadmap](admin-improvement-roadmap.md): saved filters, next-order, image sorting/preview, limited native widget pilot; sau đó audit UI/granular permissions và ledger khi có business need. Không đưa payment/shipping/multi-seller vào review này.

## 19. Risks & Evidence

Fresh IAB12 responsive observations1440/768/390/320 ×product/order/article form không body overflow. Console warn/error rỗng. Dashboard/form/native role screens và synthetic order screenshots có trong evidence; không đưa customer records/SQL/env/password vào Git. Beforeunload test: attempted navigation khi dirty giữ URL product, không thấy dialog qua provider; **NEEDS VALIDATION** cho browser/PJAX; không ghi thành mất dữ liệu đã reproduce.

QA environment dùng helper hiện có với run ID riêng,98 base tables copy/verify +2 native views; snapshots/private mail. [QA receipts](../business-ui-remediation/adminreview-20261030/evidence/). Chỉ fixtures QA/category15/product122/4SKUs/order260 được tạo; không final import, không code/config changes. Main/stage preservation và QA disposal xác nhận [cleanup](../business-ui-remediation/adminreview-20261030/evidence/cleanup.json). [Source baseline251 files](evidence/source-baseline.json) và [verification](evidence/verification.json) kiểm source không đổi.

Preservation cụ thể: toàn bộ domain data main và toàn bộ98 stage tables khớp baseline. Riêng `admin_operation_log` trên main có thay đổi trong review window; không suy nguồn hoạt động, không rollback log để làm checksum khớp. Không tuyên bố toàn bộ main98 tables identical. Source/data fixes không được áp dụng lên main; owned QA DB/container/account đã được thu hồi.

## 20. Final Admin Readiness

- Admin dùng được cho core sau hướng dẫn; chưa phải mọi nghiệp vụ phù hợp nhân viên không biết HTML.
- Product/variant đã thuận tiện hơn rõ nhờ matrix; options/error retention, publish và preview còn friction.
- Không có plugin được chứng minh dư thừa có thể bỏ an toàn ngay. Native identity/roles dùng đúng; native CRUD metadata không drop-in với relational store, admin-core widgets đáng thử.
- Không thấy Critical/High exploitable admin defect mới trong review; chưa có pentest/production certification hoặc staff usability study.
- Core synthetic E2E/regression PASS không thay thế content/business acceptance và branded-browser gate. P5-B01/02/03 production backup/DirectAdmin/final-source vẫn ngoài phạm vi admin và giữ NOT READY toàn dự án.

**ADMIN UX STATUS: NEEDS IMPROVEMENT**

**EXMENT NATIVE UTILIZATION: PARTIAL**

**PHASE 6 ADMIN STATUS: NOT READY** — còn acceptance evidence BA-V01 và catalog/UAT BA-G04; không phải kết luận toàn bộ admin bị hỏng. Không có risk mới được tự ACCEPT. Chỉ báo cáo/đề xuất; không tự triển khai backlog hoặc bắt đầu Phase6.
