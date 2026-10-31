# Roadmap đề xuất sau Admin review

Baseline93c581c; không tự triển khai roadmap. Owner chung: Product Owner ưu tiên/accept, Tech Lead phân tích native, QA kiểm correctness/UX, chủ cửa hàng duyệt dữ liệu và phân công staff.

## Before Phase 6

| Nhóm | Việc / ID | Điều kiện hoàn thành |
|---|---|---|
| Bắt buộc — acceptance evidence hiện hữu |AR-G01/BA-V01: fresh Chrome/Edge cho current build; backend roles không chỉ hidden menu |Browser core create/product/matrix/order; negative403/422/stale/upload; evidence theo browser, không coi IAB=Chrome/Edge |
| Bắt buộc — business inputs |AR-G02/BA-G04: launch assortment thật, product119/photo/options/prices/demo publication decision và policies theo queue |Owner approved manifest, QA application riêng, real-sector sample E2E +staff UAT; hiện user xác nhận chưa có catalog, giữ pending |
| Ưu tiên B1, không tự gắn P0 |AR01/02 giữ input/labels, AR03 checklist/preview, AR08 stock filter, AR09 cancel warning, AR15 dashboard |Mỗi mục có acceptance tests trong backlog; Product Owner quyết định scope trước release. Không cần refactor hết để phát hành |
| Operational onboarding |Roles/presets/PII exports, publish/CONTACT/OOS/stock reserved, cancel/ship/returned policy |Staff thao tác không sửa DB; direct forbidden routes reject; hướng dẫn ai review đơn NEW/reservations hằng ngày |
| Gates toàn dự án, ngoài review này |P5-B01 production coherent backup/CI3 recovery; P5-B02 actual DirectAdmin; P5-B03 final-source/freeze/candidate proof |Theo production runbook/release register; không hỏi lại hosting backup đã được hoãn hoặc tự truy cập hosting |

Không có Critical/High exploitable admin issue mới được xác minh để buộc sửa code ngay trong review. Việc Option UX chưa đạt điểm cao không tự trở thành Critical blocker. Chưa dùng trạng thái READY WITH ACCEPTED LOW RISKS vì không có approval mới cho acceptance gates đang mở.

## After Phase 6 — Quick Wins / cải tiến từng đợt

- AR10 giữ filter/next-order queue, AR11 placement help/settings guide, AR12 role-specific shortcuts.
- AR05/19 matrix thumbnail/compact và batch ảnh/keyboard reorder sau khi đã kiểm owner/shared-reference/primary invariants.
- AR16 pilot native admin-core Category grid/form, AR17 route/controller simplification. Tiến hành từng module, có parity+rollback; không migrate dữ liệu sang JSON, không đổi plugin UUID/grants. Có thể làm sớm hơn nếu không ảnh hưởng release và được giao riêng.
- Ghi lại workload/query count trước optimize; không thêm cache inventory/cart hoặc tune N+1 giả định.

## Future Enhancement

- AR07 clone-to-draft khi nhập nhiều sản phẩm gần giống, regenerate unique IDs/slug/SKU, không copy stock/reservations.
- AR13 granular rights/organization inheritance theo phân công thật; migration permission matrix và native/direct API tests.
- AR14 ledger/audit UI khi daily log không đủ yêu cầu truy vết; retention và owner nghiệp vụ được chốt trước additive schema.
- AR20 reservation expiry/return inspection/notification chỉ sau policy được duyệt; retry/idempotency/logging/runbook phải đầy đủ.
- Native approval workflow/notify cần nhu cầu phân cấp rõ và compatibility proof. Không thêm approval để staff phải bấm hai quy trình status khác nhau; không mở payment/shipping/marketplace trong scope review.

## Gate của từng đề xuất khi được triển khai

Source/config baseline → reproduction/prototype trên QA clone → native feasibility proof → minimal fix tại layer đúng → targeted tests → regression scope phù hợp → admin/frontend/order/inventory parity → preservation/cleanup → tài liệu và acceptance của owner. UI/data writes không dùng main để thử; các snapshots/history cũ không bị sửa.

Kết quả review hiện tại: **ADMIN UX NEEDS IMPROVEMENT; EXMENT NATIVE UTILIZATION PARTIAL; PHASE 6 ADMIN NOT READY** do acceptance gates còn mở. Đây không phải authorization triển khai B1/B2/C hoặc cutover.
