# Nội dung cần chốt để hoàn tất business acceptance

Ngày 30/10/2026. Owner quyết định: chủ cửa hàng; owner áp dụng/QA: Tech Lead. [Hàng đợi từng sản phẩm](20261030-r1/content-review.csv). **Cập nhật theo trả lời của người dùng:** chưa có nguồn catalog thật; ba ảnh cảnh quan là ảnh ý tưởng, được phép dùng. Quyền dùng ảnh đã được ghi nhận và áp dụng cho trang tư vấn, xem [receipt](20261030-r2/report.md). Chưa áp dụng quyết định sửa catalog; cả demo và migrated data vẫn giữ nguyên trên local.

## Đầu vào ngành hàng

Với mỗi ngành thực sự bán khi launch (cây nội thất/văn phòng, phân bón, dụng cụ, tưới, đèn, set cây), cung cấp tối thiểu một sản phẩm thật để nghiệm thu. Không tạo category rỗng hoặc sản phẩm tổng hợp để đóng BA-G04. Nếu launch thu hẹp ngành hàng thì cần chủ cửa hàng chốt phạm vi mới.

Người dùng đã xác nhận **“chưa có”** file/nguồn SKU, giá, tồn và ảnh thật cho phân bón, dụng cụ, thiết bị tưới và đèn. Giữ các mục này trong hàng đợi đến khi có dữ liệu; không tự tạo dữ liệu bán hàng, không diễn giải câu trả lời thành chấp thuận thu hẹp scope hoặc accept BA-G04.

Mỗi sản phẩm cần: tên, category, đơn vị bán, SKU, tên nhóm/lựa chọn, giá VND nguyên, giá sale nếu có, tồn thực tế, trạng thái bán, ảnh gốc được phép sử dụng, mô tả/thông số/hướng dẫn. File hiện có hoặc đường dẫn thư mục đều dùng được; không cần đổi format chỉ để nhập liệu.

## Hàng đợi ưu tiên

| Nhóm | ID / Nội dung | Quyết định cần có |
|---|---|---|
| Thiếu gallery | 61, 81, 82, 83, 93, 95, 96, 97, 98, 99, 101, 102, 105, 106, 107 | Cung cấp ảnh đúng; hoặc duyệt trạng thái nháp riêng cho release candidate. File media tham chiếu hiện không bị mất, nhưng các sản phẩm này chưa có gallery. |
| Nội dung demo | 3, 4, 5, 6 | Chốt publication của release candidate; vẫn giữ demo trên local theo yêu cầu trước. Không tự ẩn hoặc xóa cả bộ demo. |
| Product119 / legacy127 | “Cây hạnh phúc hai tầng”: ảnh variant139 có chữ TEST; ảnh variant140 là nhà/cảnh quan; cover là ảnh cây. Body735k/975k khác base100k/sale80k. | Xác nhận ảnh nào thay cho từng variant, nhóm phân loại thật, giá và nội dung đúng. Không chọn ảnh khác hoặc sửa giá chỉ dựa vào suy đoán. |
| Product7 / legacy4 | E2E kỹ thuật PASS; tên nhóm hiện là `ab`/`cd`, lựa chọn `a/b` và `c/d`. | Xác nhận tên phân loại để bán thật; E2E PASS không phải duyệt tính đúng của nội dung kinh doanh. |
| Nhãn/giá trong HTML | Các candidate `LEGACY_BRAND_REVIEW` / `DESCRIPTION_PRICE_REVIEW` trong CSV | Duyệt trong ngữ cảnh. Bộ dò tiền chỉ tìm candidate; số trong mô tả không mặc nhiên là lỗi giá. Không thay chuỗi hàng loạt. |

119 dòng là **candidate review**, không phải119 lỗi đã xác nhận. Trong đó20 candidate High thuộc15 gallery,4 publication demo và product119. Các dòng đã có quyết định trong Phase4 giữ nguyên; không mở lại category/exclusion/image-fallback đã được chấp thuận.

## Dịch vụ, thương hiệu và chính sách

- **RESOLVED / APPLIED:** người dùng xác nhận “Ảnh ý tưởng, được phép dùng”. Ba ảnh ban công/tiểu cảnh/phối cây đã được tối ưu WebP, dùng trong hero/gallery trang tư vấn và ghi rõ “Ảnh ý tưởng minh họa”, không phải công trình cửa hàng đã thi công. Không dùng chúng làm ảnh SKU thật hoặc chứng minh một công trình đã hoàn thành.
- Chốt phạm vi tư vấn/giao hàng/khảo sát/thi công tại Hà Nội. Copy hiện chỉ tiếp nhận trao đổi; không cam kết miễn phí, thời gian hoặc giá dịch vụ.
- Chốt tên thương hiệu/cách nhắc bancay.vn và nội dung legacy caybonmua.vn/GreenHome. Logo, năm setting cửa hàng đã phục hồi theo yêu cầu trước vẫn được giữ.
- Cung cấp chính sách giao nhận, đổi trả và bảo hành thực tế. Chưa tạo cam kết hoặc chính sách giả.

## Quy trình áp dụng sau khi có đầu vào

Ghi `decision`, nguồn ảnh/giá, người duyệt và giá trị đã duyệt vào manifest; giữ snapshot/hash trước thay đổi. Thử trên QA clone mới qua Actions/Admin, xác minh ảnh–options–giá–stock–checkout–redirect. Sau đó mới áp dụng local trong phạm vi được duyệt, ghi receipt trước/sau. Không sửa tay DB để làm đẹp kết quả; không replay merge/import; không thay snapshots đơn cũ. Production có gate riêng.

**BA-G04: BLOCKED — CONTENT REQUIRED.** Hai câu hỏi đã nhận trả lời: catalog chưa có; quyền dùng ảnh ý tưởng đã được duyệt và áp dụng riêng. Các gallery/product119/demo publication/giá/chính sách còn lại chưa có quyết định; không suy rộng approval ảnh sang các mục này.
