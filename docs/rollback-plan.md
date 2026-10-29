> Current [release recheck](release-readiness/recheck-54a0a2f/report.md#11-rollback-verification), [window restore](release-readiness/recheck-54a0a2f/window-restore.json): 98 tables + 2 views and identical offline ledger PASS. P6-B07 fixed: existing order financial/contact/identity snapshots are immutable; nullable customer FK may change without contact loss; latest status and appended live transition chain must match. Imported history always KEEP_IMPORTED_HISTORY_DO_NOT_FULFILL_OR_RESTOCK. 12 delta tests/22 assertions PASS; invalid input aborts export and retains raw artifacts. Actual production CI3 routing/restore/final-source proof remains BLOCKED. Earlier rehearsal evidence below is historical.

# Phase 6 rollback plan — prepared, production rehearsal BLOCKED

> Latest [blocker review](release-readiness-report.md):local post-window backup/98-table restore+native-view rebuild+offline recovery ledger PASS. Actual DirectAdmin routing/CI3 runtime remains unverified. Detailed evidence: [window restore](release-readiness/evidence/window-restore.json), [ledger guards](release-readiness/evidence/delta-guards.json). No production action performed.

## Tested data-delta recovery tooling

The rehearsal creates100000×2 order,reserves2 of10,reprices catalog150000 without changing snapshot,cancels/rejects duplicate cancellation,and ships a second150000×2 order leavingphysical8/reserved0. A portable post-window SQL backup restores all98 base tables exactly in another DB. Two native workflow views are regenerated against the new DB name;their original definitions/raw failed dump are retained privately. This fixes source-qualified view restoreERROR1054 without vendor edits or data-string rewrites.

Verified cutoff and restored window JSON contain private order/contact data under legacy-private/release-readiness. Offline export requires SHA256 for both inputs and a new private output path:

    php scripts/release-readiness/export_delta.php "$BASELINE_JSON" "$BASELINE_SHA256" "$WINDOW_JSON" "$WINDOW_SHA256" "$PRIVATE_LEDGER_JSON"

No Laravel bootstrap/DB connection is needed by this exporter. Private containment,checksum mismatch and output overwrite guards PASS. Repeated/live/restored exports are byte-identical. Stable order reference is the manual correspondence/idempotency key;CANCELLED must not fulfill,andSHIPPING/COMPLETED must not fulfill/restock twice. Ledger includes full item/history snapshots and inventory physical/reserved deltas;not only new ID>cutoff,also updates to baseline orders. Immutable-row deletion/change aborts verified export and keeps raw backups for investigation.

Operator must explicitly resolve every reference into CI3 or a controlled manual fulfillment ledger before restoring legacy writes. Tool never writes CI3 or automatically restores stock. Exact hosting paths/routing commands,coherent current source/freeze receipt and CI3 runtime recovery remain P5-B01/02/03. Do not mistake this local data proof for an actual production traffic rollback.

Owner: Release Lead quyết định,Hosting Operator đổi routing/Data Owner đối soát,QA Lead xác minh. [Gates](production-runbook.md) P5-B01/02/03 chưa đóng; local SQL restore không chứng minh restore được hosting thật. Chưa đổi DNS/production hoặc xóa CodeIgniter.

## Before switch

Giữ nguyên CI3 code,media,database và cấu hình routing cũ dưới snapshot coherent đã verify. Ghi exact old/new docroots,vhost/upstream/IP,DNS zone+TTL,SSL/certificate/cron/PHP config và operator commands; cấm xóa/ghi đè chúng trong deploy. Hosting export người dùng hoãn,không tự lấy trong Phase5. Rehearse routing rollback trên isolated target,đo thời gian và smoke CI3; cần restore đúng files+DB/config,kèm hash/count/media proof.

Legacy writes phải bị freeze trước final export và giữ frozen trong new-site window. Ghi cutoff order ID/timestamp,count/checksum và import receipt. Candidate SQL/media/code versions và cả production pre-switch snapshots phải immutable/private. Nếu cần shared media,copy vào new owned storage,không trỏ write của Laravel vào source CI3.

## Abort triggers

Checkout/admin product/order không hoạt động,order total/snapshot sai,oversell/data mismatch,security exposure hoặc routing/SSL/major redirect failure chưa fix được trong window. Không dùng test pass percentage để bỏ qua. Ghi incident timestamp và ngừng nhận đơn mới trước khi thao tác DB/routing.

## Rollback sequence

1. Đặt Laravel maintenance/order freeze tại ingress **và** application,đảm bảo mọi path nhận đơn/worker/cron đều dừng. Giữ incident page/contact thích hợp. Không mở writes CI3 ngay.
2. Tạo backup mới SQL+media+logs/receipt của Laravel sau window,timestamp/size/SHA-256,giữ immutable. Export privately mọi orders tạo sau cutoff,gồm order number,contact/address,items/variant/SKU/snapshots,totals,status/history,reservations; chỉ nhóm được phép xử lý PII có quyền đọc.
3. Phân loại đơn phát sinh:synthetic smoke,real NEW/confirmed,shipping/completed/cancelled. Không xóa hoặc coi tất cả là test. Data Owner lập bảng correspondence mới→legacy/manual ledger và external fulfillment/payment (hiện không integration). Giữ stable external reference để tránh nhập/xử lý hai lần.
4. Không dump Laravel tables vào schema CI3,không gọi legacy importer theo chiều ngược hoặc chế lại giá snapshot. Nếu CI3 không biểu diễn variants/workflow đầy đủ,dùng controlled manual fulfillment ledger/read-only Laravel admin cho phần đơn trong window. Mọi row nhập lại CI3 cần explicit approved mapping/idempotency và record cross-reference; unresolved real orders chặn mở writes.
5. Đối soát tồn physical/reserved và outbound fulfillment trong window. NEW reservations có thể release/cancel qua workflow nếu thực tế chưa bán và có decision;đơn đã giao không được tự cancel/restock. Physical stock legacy phải được Data Owner điều chỉnh có ledger để không bán lại hàng đã tiêu thụ. Không sync bằng tổng quantity trừ mù.
6. Hosting Operator khôi phục exact old routing/docroot/upstream hoặc DNS record đã lưu,khôi phục SSL phù hợp. Nếu đổi DNS phải theo dõi cả old/new destinations trong TTL,giữ Laravel frozen để hai đích không cùng nhận writes. Không restore DB cũ chồng lên database mới chứa real orders.
7. QA kiểm CI3 home/category/product/image,search/cart/order/admin và SSL;kiểm backup order cutoff/history và không có real new-site order thất lạc. Chỉ sau Data Owner sign-off mới mở legacy writes và các cron cần thiết. Test một controlled order,kiểm logs/stock/record IDs,đánh dấu test theo operational policy.
8. Giữ Laravel DB/media read-only và backup để điều tra/reconcile,không xóa rollback artifacts. Thông báo incident/status theo người có thẩm quyền;không tự gửi email khách trong Phase5. Ghi audit người thao tác,commands,hashes,timing,reconciliation và kế hoạch retry.

## Required verification

P5-B01: restored CI3 isolated host hoạt động với đúng config/media/schema và routing rollback command đã thử. P5-B03: simulate ít nhất một real-shaped order trong new-site window,manual ledger/correspondence và stock reconciliation không loss/duplicate. P5-B02: actual DNS/SSL/access/credentials paths documented and verified. Hiện NOT TESTED cho production; không ghi rollback READY chỉ vì tài liệu đã có.
