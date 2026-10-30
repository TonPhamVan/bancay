# Business/UI remediation — đợt triển khai 30/10/2026

> Update sau phản hồi của người dùng, run20261030-r2: catalog thật **chưa có**, vẫn CONTENT REQUIRED; ba ảnh cảnh quan là **ảnh ý tưởng, được phép dùng**. Đã thay hero và bổ sung gallery minh họa có nhãn đúng nguồn; [report/evidence mới](business-ui-remediation/20261030-r2/report.md). Các số liệu và trạng thái “chưa nhận trả lời/chưa công bố ảnh” bên dưới là checkpoint R1 trước phản hồi; R1 evidence/checksum giữ nguyên, không tái ghi để hợp thức hóa source mới.

Đã triển khai phần code của [kế hoạch](business-ui-remediation-plan.md): bảng chỉnh SKU trong Exment, tổ chức storefront đa ngành, luồng tư vấn cảnh quan và UX nhỏ. **BA-G01 và BA-G02 RESOLVED** theo phạm vi MVP; **BA-G04 vẫn BLOCKED — CONTENT REQUIRED**. Chưa thể kết luận toàn bộ kế hoạch/business acceptance hoàn tất hoặc cho production.

## 1. Baseline và phạm vi

Baseline Git `ba7a3c7`, branch `feature/develop`; source của review, kế hoạch và implementation còn uncommitted. Exment7.0.0 / Laravel12.70.2 / PHP8.3.36 / MariaDB10.11; không đổi dependency, schema hoặc migration. Master Plan, review/plan, architecture, admin/product/inventory/checkout invariants và release recheck hiện tại được giữ làm đầu vào.

Run `20261030-r1`; QA riêng tại8004, `bancay_release_candidate_20261030_001`, private storage và mail log. Clone98 base tables bằng bản dump private checksum-bound, khớp trước test; hai native derived views dựng lại bằng official guarded command. [Receipt](business-ui-remediation/20261030-r1/evidence/qa-environment.json). Main8000 và stage8002 không dùng cho writes thử nghiệm. Không reset/reseed/replay merge/import hoặc tác động production.

## 2. Bảng phân loại / SKU

Trang product có điều hướng section và một bảng: tổ hợp/SKU, loại giá, giá gốc/sale, ảnh, tồn thực tế, giữ chỗ/khả dụng và trạng thái. Áp dụng giá trị cho các dòng đã chọn chỉ điền UI; nút **Lưu N dòng thay đổi** mới ghi. Không thay đổi combination của SKU có sẵn. Generator additive vẫn giữ default CONTACT/INACTIVE/0, không overwrite dữ liệu cũ.

Web endpoint `POST /admin/plugins/bancay_catalog/products/{id}/actions/variant-matrix` đi qua native login, StoreAdminGuard, `catalog.manage`, CSRF, FormRequest và `UpdateVariantMatrix`. JSON tránh truncation do `max_input_vars`; tối đa256 dòng, whitelist fields. SKU unique batch/DB; integer VND giữ dạng string qua JS, gồm PHP_INT_MAX; CONTACT bỏ giá; image/variant phải thuộc product. Một batch một transaction, product/variants/inventories lock theo thứ tự; reuse CatalogEditor và StockAdjustment. Tồn có reason/actor/expected quantity; reserved chỉ đọc.

HMAC state token bao gồm dữ liệu SKU và quantity/reserved, kiểm dưới lock: stale tab hoặc reservation mới bị từ chối trước writes. Lỗi một dòng rollback cả batch và giữ input/UI errors. Không auto retry sau mất kết nối; yêu cầu tải lại kiểm tra. Exment dùng JS/CSS scoped riêng, không kéo Tailwind public vào admin; native PJAX được hỗ trợ bằng event delegation. Khi tắt JS, form chỉnh từng SKU cũ nằm trong noscript. Mobile bảng cuộn ngang có nhãn/focus;320px đã kiểm bằng bàn phím.

Evidence: [matrix](business-ui-remediation/20261030-r1/evidence/admin-matrix-desktop.png), [validation](business-ui-remediation/20261030-r1/evidence/matrix-validation.png), [stale state](business-ui-remediation/20261030-r1/evidence/matrix-stale.png), [reserved guard](business-ui-remediation/20261030-r1/evidence/matrix-reserved-rejection.png).

## 3. Storefront và tư vấn cảnh quan

- Ưu tiên category cây nội thất/văn phòng bằng danh sách alias chính xác trong `config/merchandising.php`. Homepage chỉ hiển thị department có category công khai và sản phẩm thực, lấy tối đa4 sản phẩm/block. Không suy tên product chứa “đèn” thành ngành đèn; category rỗng/ẩn không được dựng block giả.
- Nhãn listing/detail dùng “sản phẩm”; nhãn editorial “Nổi bật”. Public paginator tiếng Việt, current/disabled/prev/next rõ và giữ query/sort. Admin Bootstrap paginator không thay đổi.
- Search mobile luôn hiện; nav gọn hơn để chứa service. Có CTA Hà Nội/indoor và service ở menu/home/footer.
- Landing [tư vấn cảnh quan](http://localhost:8000/tu-van-canh-quan) riêng, sáu nhu cầu tham khảo, meta/canonical/sitemap. CTA đến phone `0888.085.444` và Facebook đã phục hồi từ settings; kiểm href, không gọi/gửi tin thật. Không thêm form lưu lead, mail, schema hoặc payment.
- Dùng banner Onegarden AI đã có trong dự án, ghi rõ minh họa/không phải hồ sơ công trình. Ảnh khách đính kèm vẫn chưa công bố vì chưa có xác nhận nguồn/quyền dùng. Không bịa giá dịch vụ, miễn phí vận chuyển hoặc lịch thi công.

Evidence: [home](business-ui-remediation/20261030-r1/evidence/desktop-home.png), [service desktop](business-ui-remediation/20261030-r1/evidence/service-desktop.png), [mobile](business-ui-remediation/20261030-r1/evidence/service-mobile.png).

## 4. Content review / dữ liệu thật

Tạo [content queue](business-ui-remediation/20261030-r1/content-review.csv) từ public catalog hiện tại:119 candidates,20 High candidates.15 products chưa gallery;4 demo cần quyết định release publication; product119/legacy127 có TEST/non-product variant images và body price mismatch. Các flag generic brand/giá chỉ là candidate cần đọc ngữ cảnh, không phải kết luận119 lỗi.

Chưa có dữ liệu thật cho đầy đủ các ngành launch, giá/tồn/ảnh và chính sách cuối. [Danh sách đầu vào/approval](business-ui-remediation/content-inputs.md) có ID, quyết định cần chốt, owner và quy trình áp dụng. Không ẩn demo, đổi ảnh/giá hoặc sửa nội dung legacy khi chưa có giá trị đúng đã được duyệt. Snapshot public catalog gốc giữ private, hash trong [receipt](business-ui-remediation/20261030-r1/evidence/content-queue.json); `decisions_applied=0`.

Ảnh variant140 của product119 đã kiểm trực quan là ảnh nhà/cảnh quan; không thay tự động bằng cover. Product7 mua được nhưng option labels `ab/cd/a/b/c/d` vẫn cần business duyệt. Technical E2E không chứng nhận nội dung sản phẩm phù hợp bán thật. Known Phase4 category/exclusion/fallback approvals giữ nguyên.

## 5. Automated quality / security

| Check | Kết quả | Evidence |
|---|---|---|
| Full regression Phases1–5 + business changes | **180/180 PASS,1187 assertions,0 failed/0 skipped** | [tests](business-ui-remediation/20261030-r1/evidence/tests.json) |
| New matrix cases | 11 tests/88 assertions PASS trước full run | VariantMatrixTest |
| New storefront cases | 5 tests/34 assertions PASS trước full run | BusinessStorefrontTest |
| Existing JS suite + new script syntax | 5/5 PASS; node --check PASS | [quality](business-ui-remediation/20261030-r1/evidence/quality.json) |
| Vite production build | PASS | quality.json; manifest/assets hashes |
| Pint / composer strict validation / platform / Exment requirements | PASS | quality.json |
| Composer audit | 0 advisories,0 abandoned | [audit](business-ui-remediation/20261030-r1/evidence/composer-audit.json) |
| npm audit | 0 vulnerabilities | [audit](business-ui-remediation/20261030-r1/evidence/npm-audit.json) |

Cases mới kiểm atomic rollback, stale SKU/inventory, reserved guard, ownership, cross-product image/ID, duplicate SKU/IDs, unauthorized roles, unknown stock fields, limits và immutable snapshot. Public text/attributes escape; JSON dataset HEX escaped; JS errors dùng textContent, không render HTML từ response. Không thêm raw input SQL hoặc permission bypass. Không coi tests là chứng nhận security production target.

Lần full regression đầu179 PASS/1 FAIL do expectation sitemap hard-code8 URLs; service mới làm fixture thành9. Test được cập nhật để kiểm service URL và đúng tổng9, sau đó full180 PASS. Giữ `tests-first.json` và raw log private. Verifier E2E đầu có lỗi so legacy_id string với integer; sửa type comparison trong verifier, không sửa DB; giữ `e2e-database-first.json` để truy vết.

## 6. E2E và dữ liệu đơn/tồn

Native Exment UI: tạo category15/product122 synthetic, upload ảnh QA,2 groups×2 values→4 SKU143–146; lưu matrix giá100k/sale90k/tồn10/ACTIVE/ảnh357; đổi SKU trong một lần. Reload đúng. Validation late-row giữ input, stale two-tab reject và tồn dưới reserved reject có screenshot.

Frontend mobile chọn M/Xanh, đặt2→đơn260 tổng180k. Sau đặt sửa SKU4 base150k/sale110k bằng matrix; đơn cũ giữ90k×2=180k. Admin NEW→CONFIRMED→CANCELLED, history đầy đủ; stock10/reserved0. Automated snapshot case còn kiểm100k→150k, đơn vẫn100k. Không đổi rule trừ physical stock khi SHIPPING.

Legacy product7/legacy4: category/image/variant giá80k→cart→checkout→đơn26180k→admin thấy snapshot→CANCELLED, giải phóng reservation. Đây là dữ liệu migrate clone thật, không seed thay thế. [DB assertions11/11 PASS](business-ui-remediation/20261030-r1/evidence/e2e-database.json), [migrated checkout](business-ui-remediation/20261030-r1/evidence/migrated-checkout.png), [history](business-ui-remediation/20261030-r1/evidence/migrated-order-history.png).

**Actual new-industry product E2E: BLOCKED — CONTENT REQUIRED.** Product122 chỉ là synthetic QA thiết bị tưới, không được tính là sản phẩm kinh doanh thật.

## 7. Browser / responsive

Fresh IAB Chromium admin/frontend/admin E2E và service contact discovery PASS.24 page/viewport observations cho home/service/listing/product/cart/checkout tại1440/768/390/320px: không body overflow, không ảnh visible broken. Admin320px: vùng bảng231px, table1180px, keyboard horizontal scroll105px; body không tràn. [Responsive](business-ui-remediation/20261030-r1/evidence/responsive.json), [cart/checkout](business-ui-remediation/20261030-r1/evidence/commerce-responsive.json). Console warning/error rỗng trên bốn tab được kiểm; [logs](business-ui-remediation/20261030-r1/evidence/browser-logs.json).

**Chrome Desktop / Edge Desktop / Chrome Mobile riêng: NOT TESTED lần này** — provider báo browser không khả dụng; native app inventory không có Chrome/Edge đang mở. IAB mobile viewport không phải physical mobile Chrome. Safari/iOS cũng NOT TESTED. Evidence cũ Chrome/Edge vẫn lịch sử, không gắn PASS cho matrix mới. BA-V01 ghi rõ verification còn thiếu trong [browser matrix](business-ui-remediation/20261030-r1/evidence/browser-tests.json).

## 8. HTTP / SEO / integrity / performance

Fresh read-only crawl main8000: **201 actual legacy redirects301→đúng target200,220 sitemap targets200,759 internal assets200**,0 metadata/redirect failures; service có trong sitemap, canonical/H1/meta/noindex đúng, robots local Disallow, URL không tồn tại404. Không đổi301 mappings hoặc SEO của nội dung legacy. [HTTP evidence](business-ui-remediation/20261030-r1/evidence/http-seo.json).

Fresh main audit:17 domain tables,0 FK/duplicate/price/inventory/order/history/cycle violations;786 media references có file,0 ảnh>1MB, largest626578bytes. Đây là kiểm file tồn tại, không có nghĩa ảnh đúng business hoặc mọi product có gallery.121 products/142 variants/72 articles/**259 orders** hiện tại; số258 trong checkpoint cũ là lịch sử. [Integrity](business-ui-remediation/20261030-r1/evidence/data-integrity.json).

QA six-page performance, five warm in-process samples/page: median9.96–21.92ms; max9–22 DB calls,0 query>100ms; tất cả200. Home22 queries bounded; không thêm cache checkout/inventory. [Baseline](business-ui-remediation/20261030-r1/evidence/performance.json). Không phải network/Lighthouse/production load benchmark; reused measurement logic được bind sang clone/output mới, không overwrite lịch sử.

## 9. Bảo toàn / cleanup

[Cleanup receipt](business-ui-remediation/20261030-r1/evidence/cleanup.json) kiểm main domain tables và toàn bộ stage tables so baseline trước QA. Không undo audit-log của main để giả checksum khớp. Owned QA container/database/account được thu hồi sau xác minh; private raw logs/snapshot/dump/media retained, không commit SQL/PII/credential. `admin` và `local-admin` người dùng sở hữu trên main giữ nguyên. Source/ZIP/SQL/production không thay đổi.

## 10. Re-acceptance và việc còn lại

| Area | Kết quả hiện tại | Gate còn lại |
|---|---|---|
| Exment matrix / inventory / snapshots | PASS | External Chrome/Edge rerun BA-V01 |
| Service contact MVP | PASS | Ảnh portfolio thật là phần content tùy nguồn đã duyệt |
| Generic merchandising/mobile labels/search/pagination | PASS | Real assortment BA-G04 |
| Main data integrity / migrated E2E / SEO | PASS | Final-source production proof riêng |
| Real business catalog/publication | **BLOCKED** | BA-G04, owner supplies/approves factual data |
| Final policy/brand/delivery scope | CONTENT REQUIRED | BA-G05/06 |
| Full named browser acceptance | **BLOCKED VERIFICATION** | BA-V01 |
| Production technical readiness | **NOT READY** | P5-B01/P5-B02/P5-B03 unchanged |

Updated [gap register](business-ui-remediation/20261030-r1/gap-register.csv), [acceptance cases](business-ui-remediation/20261030-r1/evidence/browser-tests.json), [content inputs](business-ui-remediation/content-inputs.md). Advanced filters/sticky buy CTA/rich editor remain DEFERRED NON-BLOCKING, không tự gắn ACCEPTED. Không thêm framework, marketplace, ratings/sold count giả hoặc chức năng Phase7.

**PHASE 6 BUSINESS STATUS: NOT READY.** Cần dữ liệu thật/approval BA-G04 và fresh named-browser verification BA-V01 để hoàn tất kế hoạch. **PHASE 6 STATUS: NOT READY** do các production gates cũ còn nguyên. Không DNS, docroot, CI3 write access, final migration hoặc production cutover; không tự commit/push.
