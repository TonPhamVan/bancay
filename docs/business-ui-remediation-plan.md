# Kế hoạch xử lý Business/UI Acceptance gaps

Ngày 30/10/2026. Đầu vào: [review](business-ui-acceptance-report.md), [8 gaps](business-ui-acceptance/gap-register.csv), Master Plan và source hiện tại. **Implementation update:** [báo cáo đợt1](business-ui-remediation-report.md) đã triển khai matrix, merchandising code, consultation MVP và UX nhỏ; content BA-G04 và fresh Chrome/Edge BA-V01 chưa đạt. Các phần bên dưới giữ mô tả kế hoạch và tiêu chí nghiệm thu, không phải tuyên bố đã hoàn tất mọi đợt.

## Mục tiêu và điều kiện hoàn thành

Đóng ba High gaps **BA-G02 → BA-G04 → BA-G01**, để quản trị viên nhập/bán hàng thuận tiện và khách tìm được sản phẩm/dịch vụ đã cam kết. Nội dung được chuẩn bị từ đầu; việc bổ sung landing tư vấn có thể làm trong lúc chờ catalog thực.

Giữ Laravel 12 / Exment 7 / Blade / Alpine và relational Product–Option–Variant–Inventory hiện có. Các thay đổi UX đi qua Actions/Services; không cần nâng framework hoặc thay kiến trúc. Cart, server price, stock reservations, immutable snapshots và checkout idempotency là các invariant phải regression.

Đích nghiệm thu business: không còn High/BLOCKER trực tiếp; admin matrix PASS, khách mua hàng PASS trên desktop/mobile, catalog launch được duyệt và service contact flow PASS. Business PASS vẫn cần đóng các gate kỹ thuật P5-B01/B02/B03 trước production.

## Thứ tự thực hiện

| Đợt | Ưu tiên / Gap | Deliverable | Phụ thuộc / điều kiện đóng |
|---|---|---|---|
| 0 | Chốt đầu vào BA-G04/05/06 | Danh sách ngành hàng launch; hàng đợi duyệt nội dung; bản nháp copy/ảnh/dịch vụ | Dùng dữ liệu thật; phần chưa rõ giữ PENDING, không tự accept |
| 1 | HIGH BA-G02 | Admin phân loại + bảng SKU chỉnh giá/tồn trong cùng trang | Có thể bắt đầu ngay từ source hiện tại; regression commerce/permission PASS |
| 2 | HIGH BA-G04, MEDIUM BA-G03/05/06 | Catalog đã duyệt, homepage đa ngành, nội thất/Hà Nội và copy thống nhất | Chốt assortment/ảnh/giá/nội dung; không bịa sản phẩm hoặc chính sách |
| 3 | HIGH BA-G01 | Landing/section tư vấn cảnh quan, hình/nội dung và CTA có người nhận | MVP dùng số điện thoại/Facebook đã có; ảnh được xác nhận nguồn |
| 4 | MEDIUM/LOW BA-G07/08 | Mobile/search/nhãn/pagination polish được chọn | Sau ba High gaps; enhancement lớn có thể để backlog |
| 5 | Release gate | Re-acceptance, evidence mới, business readiness matrix và kiểm lại technical gates | Không dùng tỷ lệ PASS để bỏ qua High gap |

Đợt 0 là luồng chuẩn bị nội dung đi cùng các đợt sửa code. Không chờ đủ toàn bộ nội dung mới bắt đầu đợt 1. Có thể hoàn tất đợt 3 trong thời gian chờ đầu vào đợt 2.

## Đợt 0 — Assortment và hàng đợi nội dung

Tạo bảng duyệt riêng, có: entity/ID, nguồn demo hoặc migrated, URL hiện tại, vấn đề, ảnh/giá/option hiện tại, đề xuất, quyết định, người duyệt, ảnh hưởng publication. Giữ provenance và bản trước/sau; không sửa snapshot/history của đơn cũ.

Đầu việc cụ thể:

1. Chốt ngành bán ở lần launch: cây nội thất/văn phòng, phân bón, dụng cụ, tưới, đèn/cảnh quan, set cây; đất/giá thể nếu thực sự bán. Nếu khách chọn launch nhỏ hơn yêu cầu hiện tại, ghi scope được chấp thuận và các ngành chuyển sang backlog.
2. Liệt kê 15 visible products chưa có gallery, sản phẩm tên thử như `12111`/`ffff`, ảnh TEST ở product119/legacy127/variant139 và các mâu thuẫn giá mô tả với giá SKU.
3. Với từng item, đưa ra phương án: bổ sung nội dung thật; sửa mapping/ảnh khi có nguồn đúng; hoặc đưa về nháp trong release candidate nếu được duyệt. Không xóa dữ liệu gốc/demo và không tự ẩn cả bộ demo đang được yêu cầu giữ trên local.
4. Chuẩn bị sample catalog thực cho từng ngành bán lúc launch: tên, category, đơn vị/loại, SKU, options, price/sale, stock, ảnh, mô tả/thông số/hướng dẫn. Một sample mỗi ngành là ngưỡng kiểm chứng khả năng; không được hiểu là đã hoàn tất toàn bộ assortment.
5. Chốt brand copy dựa trên Onegarden/logo đã được yêu cầu phục hồi; ghi rõ vai trò GreenHome/bancay.vn nếu cần. Kiểm title/meta/body/ảnh watermark/contact legacy để khách duyệt.

Các đầu vào cần khách cung cấp hoặc xác nhận khi áp dụng: assortment launch và thông số/giá/ảnh thật; phạm vi giao/thi công tại Hà Nội; chính sách giao/đổi trả/bảo hành; nguồn và quyền dùng ảnh cảnh quan. Có thể soạn bản nháp để duyệt, không công bố cam kết chưa xác nhận.

**Nghiệm thu:** mọi item ảnh TEST/tên thử/giá mâu thuẫn có quyết định rõ; mỗi ngành launch có sample thật được mua qua QA; thiếu gallery được giải quyết hoặc có quyết định publication cụ thể. Không đánh dấu BA-G04 RESOLVED khi chỉ thêm category rỗng hoặc seed sản phẩm giả.

## Đợt 1 — Admin phân loại và variant matrix

### Giao diện dự kiến

Trang sản phẩm có điều hướng đến các section: thông tin cơ bản, hình ảnh, mô tả, phân loại/bán hàng, SEO. Cải tiến tập trung vào khu vực phân loại; giữ các chức năng khác đang hoạt động.

Nhóm phân loại hiển thị tên và các lựa chọn rõ ràng. Cho xem trước số tổ hợp trước khi sinh. Hỗ trợ 1–2 nhóm theo nhu cầu chính, đồng thời không làm mất khả năng đọc/sửa dữ liệu có nhiều nhóm hơn đang được hỗ trợ. Sau khi sinh, hiển thị một bảng:

| Tổ hợp | SKU | Loại giá | Giá | Sale | Ảnh | Tồn thực tế | Giữ chỗ / Khả dụng | Trạng thái |
|---|---|---|---|---|---|---|---|---|
| S / Đỏ | Có thể sửa | FIXED/CONTACT | Có thể sửa | Có thể sửa | Chọn ảnh đã upload | Có thể điều chỉnh | Chỉ đọc | ACTIVE/INACTIVE |

Có nút lưu các dòng đã thay đổi và thao tác áp dụng một số giá trị cho các dòng được chọn, kèm xác nhận phạm vi. Error gắn đúng dòng/field; giữ nội dung người dùng vừa nhập khi validation thất bại. Mobile admin cho phép cuộn bảng ngang có nhãn rõ; không ép chín cột vào chiều rộng điện thoại.

### Xử lý phía server

- Thêm endpoint web được authorize cho batch variant; controller mỏng gọi Action điều phối transaction. Reuse validation/ownership từ `CatalogEditor::updateVariant`, create từ `CreateVariant`, tồn từ `StockAdjustment`/`InventoryService`.
- Validate SKU uniqueness trong batch và DB, price/sale integer VND, CONTACT không có giá, image thuộc product, variant thuộc đúng product, giới hạn tối đa 256 tổ hợp. Không cho sửa combination/value IDs của SKU đã tạo hoặc reserved quantity.
- Lưu một batch theo nguyên tắc all-or-nothing. Lock theo thứ tự thống nhất; kiểm stale state của các dòng sửa và expected_quantity khi đổi tồn. Có reason và actor audit cho stock. Một dòng lỗi không để lại các dòng khác đã commit.
- Payload giới hạn kích thước và số dòng; dùng JSON request có CSRF trong web session hoặc cơ chế tương đương đã kiểm chứng để tránh form lớn bị `max_input_vars` cắt mất dữ liệu. Response trả lỗi theo ID dòng. Không tạo frontend/backend riêng.
- Giữ generator additive: chỉ tạo combinations còn thiếu, mặc định INACTIVE/CONTACT/0 và không overwrite SKU/giá/tồn cũ. Đổi cấu trúc nhóm sau khi có SKU vẫn bị guard; UI giải thích giới hạn thay vì bỏ constraint.
- Không sửa migration cũ. MVP này dự kiến không cần schema mới; nếu cần version token persist thì phải có đề xuất migration additive riêng trước khi làm.

Files/layer dự kiến: `resources/views/admin/store/product.blade.php`, partial matrix mới, admin-scoped interaction, `app/Admin/StoreController.php`, Form Request/Action batch mới và feature tests liên quan. Không đưa Tailwind public vào Exment hoặc patch vendor.

**Nghiệm thu BA-G02:** tạo 2 nhóm × 2 values, thấy đủ 4 dòng; sửa SKU/price/sale/image/status/stock trong cùng workflow, lưu batch và reload đúng. Tạo 1 nhóm hoặc simple product cũng chạy. Cross-product ID, duplicate SKU, sale>price, âm tồn, dưới reserved, stale edit, unauthorized request bị reject; lỗi một dòng rollback cả batch. Thay giá/tên sau đặt hàng không làm đổi snapshot đơn cũ.

## Đợt 2 — Catalog và storefront theo ngành

Áp dụng catalog/content từ bảng duyệt bằng Admin/Actions hoặc công cụ guarded dùng approved manifest, có backup và receipt; không replay importer/merge chỉ để sửa vài nội dung. Trước tiên chạy trên QA clone mới, rồi mới áp dụng thay đổi local đã được duyệt.

Frontend cần:

1. Ưu tiên cây nội thất/văn phòng trong navigation và block nổi bật; thêm entry cho các ngành launch thực sự có hàng.
2. Dùng nhãn trung tính “sản phẩm”, “thông tin sản phẩm” cho listing/detail đa ngành. Giữ cách diễn đạt cây xanh ở landing phù hợp.
3. Homepage có các entry ngành hàng rõ và khối sản phẩm được chọn; chỉ hiện block có dữ liệu công khai hợp lệ, tránh category trống/khối giả.
4. Giới thiệu set cây/giải pháp theo product thực có SKU, giá, tồn khi bán theo bộ. Dịch vụ thiết kế nằm ở luồng riêng, không ép vào cart.
5. Ghi phạm vi cây nội thất/dịch vụ Hà Nội theo copy được duyệt, dẫn đến category/CTA. Không thêm geo-block hoặc cam kết miễn phí giao hàng.
6. Duyệt lại old brand/domain, giá ghi trong HTML, ảnh minh họa và thông số. Không rewrite hàng loạt bằng thay chuỗi mù quáng.

Thuộc tính mô tả như ánh sáng, chiều cao, áp lực tưới có thể dùng nội dung hiện tại cho MVP. Chỉ thêm typed attributes khi đã có danh sách field, đơn vị và nhu cầu filter rõ; không biến mọi thông số thành nhóm variant.

**Nghiệm thu BA-G04/03/05/06:** từng ngành launch có đường duyệt/tìm kiếm tới sản phẩm thật; tên/ảnh/option/giá mô tả nhất quán với giá server; mua hàng/admin khớp. Trang home/category/product/contact và checkout thể hiện cùng brand, chính sách và phạm vi phục vụ đã duyệt. Dữ liệu demo/migrated cùng tồn tại theo quyết định người dùng; snapshot/history cũ không bị viết lại.

## Đợt 3 — Tư vấn thiết kế cảnh quan

MVP đề xuất: một landing Blade riêng, có entry ở menu/home/footer, mô tả cây văn phòng, ban công, sân vườn, tiểu cảnh, hệ thống tưới và các dự án nhỏ. Có gallery/ý tưởng và CTA “Nhận tư vấn thiết kế”. Tận dụng phone/Facebook đã phục hồi để tiếp nhận; chỉ thêm Zalo khi đúng tài khoản/link được xác nhận.

Ảnh khách cung cấp được dùng làm tham khảo. Trước khi xuất bản, xác nhận ảnh là công trình thực hay ý tưởng và quyền sử dụng; caption phản ánh đúng nguồn. Soạn thông điệp tiếp nhận tối thiểu: nhu cầu, khu vực, diện tích/không gian và ảnh hiện trạng nếu khách tự gửi qua kênh đã chọn.

Form lưu lead/ảnh upload, notification email, báo giá và CRM chưa nằm trong MVP mặc định. Nếu khách chọn form thay contact action, phải chốt thêm: field bắt buộc, storage/retention, anti-spam, người nhận, trạng thái tiếp nhận và phương án khi gửi mail lỗi; khi đó mới đề xuất schema additive và test riêng. Không để một form gửi đi mà không có người nhận/receipt.

Files dự kiến: route public mới, controller/service chuẩn bị DTO nội dung nếu cần, view Blade dịch vụ, entry trong layout/home, meta/canonical và sitemap cho trang được xuất bản. Không cần bảng service/lead mới cho landing + contact action đơn giản.

**Nghiệm thu BA-G01:** Home → dịch vụ → đọc nội dung/ảnh → CTA đến đúng phone/kênh của cửa hàng trên desktop/mobile. Xác nhận href/receiver, không tự gọi hoặc gửi tin thật trong QA. Nếu có form: invalid input bị reject, valid synthetic request được ghi nhận/receipt, error handling và quyền xem lead được kiểm chứng.

## Đợt 4 — UX nhỏ và backlog

Làm các mục ít rủi ro sau khi ba High gaps đã xử lý: search mobile dễ thấy, nhãn “Nổi bật” thay cho hàm ý popularity chưa đo, pagination tiếng Việt, gọn mô tả dài và ảnh card phù hợp hơn. Kiểm tra lại 320/390/768/1440px.

Advanced filters, sticky buy CTA, rich editor và typed specifications toàn ngành được chia backlog riêng khi có nhu cầu rõ. Không thêm rating/sold count/discount giả, Shopee Live, multi-seller, payment hoặc voucher để làm giao diện giống sàn.

Các mục defer phải ghi impact/mitigation/owner/target; không tự gắn ACCEPTED nếu khách chưa chấp thuận.

## Đợt 5 — Regression và re-acceptance

Tạo run ID, QA database/storage và thư mục evidence mới; không chạy lại collectors ghi đè review 29–30/10 hoặc các phase cũ. Nếu tái dùng helper clone thì phải cấp output/private path mới; helper review hiện tại cố ý giữ source-copy cũ bằng cơ chế không overwrite.

Kiểm tra bắt buộc:

- Regression Phase 1–5; tests mới cho batch atomicity, ownership, stale inventory, price validation; JS/build/Pint và dependency audit phù hợp. Chỉ một bộ destructive tests dùng `bancay_test` tại một thời điểm.
- Admin tạo category/product/images/1–2 groups/generate/matrix save/publish → frontend chọn variant/cart/checkout → admin snapshot/history. Kiểm thêm một sản phẩm migrated và một sản phẩm thực thuộc ngành mới.
- Giá 100.000 → đặt đơn → sửa catalog 150.000, đơn cũ giữ 100.000. Stock10 → đặt2 → reserved2/available8 → cancel → stock10/reserved0; không double restore/duplicate order.
- Desktop Chrome, Edge và Chrome mobile viewport; responsive 320/390/768/1440px cho home/category/product/cart/checkout/service. Physical iOS/Safari chỉ ghi PASS nếu đã chạy thật.
- Kiểm search tiếng Việt/no result, pagination/query, gallery, CONTACT/OOS/inactive, mới/cũ price, meta/canonical/robots/sitemap/legacy301. Kiểm các URL cũ của bản ghi thay publication để tránh soft404/redirect sai.
- Content queue không còn item High thiếu owner/quyết định. Duyệt sample từng ngành launch và toàn bộ known TEST/placeholder/mismatch từ review.
- Lưu screenshot/HTTP/DB aggregate, before/after và checksum preservation; cleanup QA/account/grants, giữ admin/local-admin người dùng sở hữu.

Deliverables: remediation report; gap register cập nhật theo evidence; test/acceptance matrix; content decisions; screenshots trước/sau; business readiness mới. Giữ riêng trạng thái kỹ thuật và nghiệp vụ.

## Gate để sang Phase 6

| Gate | Điều kiện |
|---|---|
| Business | BA-G01/02/04 RESOLVED hoặc scope thay đổi được khách chấp thuận rõ; không còn High/BLOCKER yêu cầu cốt lõi |
| UX | Admin matrix dùng được, mua hàng và service CTA PASS desktop/mobile |
| Content | Launch assortment/ảnh/giá/chính sách được duyệt; không công khai TEST hoặc mâu thuẫn mua hàng chưa giải thích |
| Technical | P5-B01 production backup/CI3 recovery, P5-B02 DirectAdmin target profile, P5-B03 final-source/freeze/candidate proof phải được xác minh riêng |

Hosting backup/profile hiện đã được người dùng hoãn; không hỏi lại hoặc tự truy cập hosting trong quá trình sửa Business/UI. Các task local có thể hoàn tất trước, nhưng không được suy ra quyền cutover hay tự đóng các gate production.

Kế hoạch này không thực hiện DNS/docroot/final production migration/cutover. Hiện tại vẫn **PHASE 6 BUSINESS STATUS: NOT READY**. Đợt1 đã triển khai và kiểm chứng; bước tiếp theo cần dữ liệu/approval trong [content inputs](business-ui-remediation/content-inputs.md) và môi trường Chrome/Edge để đóng phần nghiệm thu còn thiếu. Không tự gắn ACCEPTED cho các đầu vào chưa được duyệt.
