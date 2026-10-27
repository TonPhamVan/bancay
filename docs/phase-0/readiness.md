# L. Phase 1 Readiness Report

**PHASE 1 STATUS: READY WITH RISKS**

Đủ bằng chứng local để bắt đầu **thiết kế foundation/schema** khi người dùng chỉ định Phase 1: có source nguyên trạng trong backup, SQL đã restore/đối soát, hiểu product/variant/order/user/role, có mapping, scope và security findings. **Chưa bắt đầu Phase 1 trong task này.**

Không đồng nghĩa đã có full production backup, dữ liệu đã sạch, legacy functional tests đã PASS hoặc có thể cutover. Người dùng đã cho phép chủ động thiết kế mapping trạng thái và hoãn backup cấu hình hosting; không tiếp tục hỏi lại các quyết định đã có.

## Completion criteria và giới hạn

| Criterion | Evidence / trạng thái |
| --- | --- |
| Source backup | PASS provided artifact: ZIP hash/CRC/extraction/manifest |
| Database backup | PASS provided dump: copy/hash/restore12tables980rows; freshness/full production coverage chưa xác minh |
| Upload/image backup | PASS archive coverage; 6 DB references không có trong archive, cần REVIEW |
| Git baseline | PASS private source baseline1470files; secrets redacted; nguyên bản canonical ở ZIP/SQL |
| Functional inventory | PASS static inventory + controller-method export; NOT TESTED runtime flows |
| Database inventory | PASS all12tables; row counts/PK/columns/reference checks; noFKs |
| Business rules | PASS32rules từ controller/model/view/helper/JS/DB; phân biệt rule cần giữ và lỗi phải bỏ |
| Product/variant structure | PASS115products;3products8variants; JSON/index/price/stock/SKU collisions đã xác định |
| Order structure | PASS one-product row snapshot;748rows; boolean status; không items/payment/shipping/history |
| User/role structure | PASS2accounts MD5; roles1–4 + stale5guard; authorization flaws ghi rõ |
| SEO URL pattern | PASS201DBpaths +166paths quan sát từ archived logs; percent encoding included; HTTP current NOT TESTED |
| Integration/security inventory | PASS static inventories; external connectivity NOT TESTED |
| Migration scope / mapping | PASS draft/data classification; không có importer/schema implementation |
| Hosting backup | **DEFERRED BY USER** — DirectAdmin/cron/DNS/SSL/PHP ini export bổ sung sau |

Do các giới hạn trên, chỉ kết luận đã hoàn thành **bộ phân tích và kiểm chứng local**; không báo DONE cho mọi hoạt động Phase 0/production recovery.

## Risks và cách xử lý ở phase sau

| Risk | Quyết định / action | Có chặn thiết kế Phase 1? |
| --- | --- | --- |
| Price/SKU/alias không sạch | Thiết kế unique keys; default variant; exact money; legacy mapping; zero-price contact-only; quarantine | Không; phải có validation/import policy trước Phase4 |
| Variant SKU CB-1/CB-2/SKU trùng | Giữ original code metadata; cấp SKU xác định theo legacy product + index khi không unique | Không; không lấy SKU cũ làm migration identity |
| Product7/10 orphan category32 | Hỗ trợ mapping review/hidden category có provenance; không publish tự động vào category tùy ý | Không; resolve trước import public catalog |
| Historical order prices/quantities không hợp lệ | Archive nguyên bản; only validated history imports; no live checkout/email/stock side effects | Không; cần quarantine/reconciliation trước nhập lịch sử |
| Legacy status0/1 không có nghĩa nghiệp vụ rõ | Người dùng cho phép chủ động thiết kế:0NEW;1CONFIRMED; giữ legacy_status; không suy diễn completed/payment/shipping | Không; mapping policy đã ghi lại |
| 6 references thiếu file | Giữ missing-file manifest; tìm bổ sung hoặc approved placeholder và log exception ở Phase4 | Không chặn schema; chặn công bố ảnh thiếu như thể đã migrate đầy đủ |
| SQLi/CKFinder/XSS/MD5/authz flaws | Không copy handlers; Laravel standards + Exment policies/guarded domain actions; sanitize imported HTML | Không; phải đưa yêu cầu vào thiết kế ngay |
| Bảng/handler cũ thiếu | Không tự recreate stale warehouse/CRM/crawler; target theo Master Plan | Không; runtime legacy một số flows chưa chứng minh hoạt động |
| Server/config/freshness chưa đủ | Hoãn hosting backup theo người dùng; bổ sung trước staging/cutover/final sync | Không chặn thiết kế local; chặn release/rollback readiness |

## Blockers theo phạm vi

- **Phase 1 foundation/schema design:** không có blocker cứng còn lại trong đầu vào local; các rủi ro trên phải được mang vào ADR/schema plan.
- **Production release / full recovery:** BLOCKED cho đến khi có hosting backup, runtime/cron/DNS/SSL inventory, final data consistency và restore/rollback smoke test. Người dùng đã chỉ định backup hosting sau; không lấy thiếu phần này làm lý do tự đăng nhập hoặc sửa production.
- **Legacy functional certification:** NOT TESTED. Cần MANUAL TEST REQUIRED với người vận hành để phân biệt code present và business process đang dùng; không gắn nhãn “working” chỉ từ inventory.

## Đầu vào cụ thể cho Phase 1 khi được chỉ định

1. Xác minh tương thích phiên bản Laravel/Exment/PHP/MySQL-MariaDB từ nguồn chính thức và ghi ADR; chưa chọn/cài dependency trong Phase0.
2. Định nghĩa Catalog/Commerce/Customer/Content/System, model relationships, Service/Action boundaries; Blade/admin chỉ gọi nghiệp vụ qua các lớp phù hợp.
3. Thiết kế Product/Variant/options/images/inventory; money integer VND hoặc exact decimal được chốt rõ; unique SKU; category tree; publication/SEO fields.
4. Thiết kế Order/OrderItem/StatusHistory với immutable snapshots, legacy mapping và transaction/stock constraints; không ép dữ liệu lỗi thành orders hợp lệ.
5. Chuẩn bị Exment integration/mapping: generic CRUD không được bỏ qua invariants của orders/inventory/prices; roles/permissions theo action; chưa xây admin UI Phase2 hoặc frontend Phase3.
6. Chuẩn bị mapping migration/file/URL có idempotency và exception reports; chưa chạy importer Phase4.

PHASE 1 STATUS: **READY WITH RISKS** — có đủ dữ liệu để thiết kế local; chưa đủ để migrate/publish production và phần hosting backup được hoãn theo chỉ định.
