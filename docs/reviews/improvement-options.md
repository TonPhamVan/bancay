# Ba phương án cải tiến

Chỉ là đề xuất, chưa được thực hiện/approve backlog. Effort S/M/L tương đối; business time saving và số click sau cải tiến **NOT MEASURED**.

| Tiêu chí | A — Minimal | B — Recommended | C — Advanced |
|---|---|---|---|
| Phạm vi |Menu/role hygiene/hướng dẫn; sửa old-input/lỗi/labels/cancel warning nhỏ |A +publish checklist/preview, options/generation UX, stock filters, dashboard nghĩa dữ liệu; native widget pilot riêng |B +safe product duplication, stock ledger/UI, fine-grained rights/org, saved work queues, automation theo policy |
| Benefit |Medium: học nhanh hơn, giảm nhập lại |High: giải quyết các điểm có evidence, phù hợp single-store và yêu cầu seller UX |High nếu business thật cần; value của nhiều mục chưa xác nhận |
| Effort |S; native config là phần menu/quyền, Blade errors/labels vẫn cần code |M; chia module, không thay schema commerce ở B1 |L; có thể cần additive schema, worker/SOP và nhiều vòng UAT |
| Risk |Low; permission/menu vẫn cần direct URL tests |Medium; giữ Actions/data, proof-of-concept tách khỏi release path |Medium–High; ledger/roles/automation có concurrency/audit/policy impacts |
| Maintainability |Cải thiện nhỏ; custom list/form vẫn còn |Tốt nếu native widgets giảm code mà không tăng adapter complexity |Có thể tốt ở quy mô phù hợp; rủi ro over-engineering cho1store |
| Upgrade compatibility |Ít thay đổi seam hiện có |Cần test admin-core API/plugin routes, không patch core; optional widget pilot chưa xác minh parity |Nhiều extension/config/storage contracts cần maintain/test |
| Release impact |Không đóng content/browser/hosting gates; có thể triển khai nhanh từng mục |B1 có thể trước release; B2 refactor không thành gate nếu core đang đúng |Phần lớn sau Phase6 và business confirmation; không trì hoãn release chỉ để có nhiều feature |
| Native utilization |Native menus/roles/help +minimal custom fixes |Native identity/shell/permission; supported Form/Grid thử đúng Eloquent model, không mirror |Native workflows/notify chỉ khi event/policy tích hợp được; không ép Order thành CustomValue |
| Rollback |Revert menu/config/labels, giữ authority mappings |Revert adapter/UI từng module; giữ data và Actions ổn định |Cần từng feature migration/retention/delta rollback, không blanket restore DB |

## Khuyến nghị B với hai nhịp

**B1 — trước release, ưu tiên dữ liệu/UX:** AR01 old-input/errors; AR02 labels/hints; AR03 product readiness/preview; AR08 stock filter; AR09 cancel confirmation; AR15 dashboard semantics/entry. Cho staff làm thử một sản phẩm thật và đơn synthetic, ghi vấn đề/mức chấp nhận. Approval nội dung thật vẫn cần chủ cửa hàng; không thể thay bằng fixture.

**B2 — đơn giản hóa có kiểm chứng:** AR16/17 pilot Category Eloquent Form/Grid và route declarations. Trước khi đổi module, chứng minh snapshot data/schema/IDs giữ nguyên, Actions không bypass, role/menu và upload/validation/CSRF/export parity. Nếu không giảm complexity thì giữ implementation hiện tại. Không gộp plugin-ID permissions hoặc native Workflow chỉ để tăng phần trăm dùng native.

Chọn B vì core constraints đã được kiểm, matrix đã có và phần thiếu tập trung ở hướng dẫn/điều hướng/nội dung/consistency. Không cần dựng lại admin hoặc chuyển business database trước release. A phù hợp khi cần giữ scope nhỏ; C chỉ khi có quy trình/khối lượng công việc chứng minh nhu cầu.

**Không thể khẳng định B giảm X% số click/time hoặc xóa Y plugins:** chưa có PoC/UAT sau cải tiến. Acceptance cụ thể trong [backlog](improvement-backlog.md); current six plugins đều có lý do tồn tại, các replacement hiện ở NEEDS VERIFICATION/SIMPLIFY CUSTOM.
