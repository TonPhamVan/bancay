# Quản lý sản phẩm — Phase 2

Vào **Sản phẩm & Tồn kho → Sản phẩm** trong Exment, hoặc `/admin/plugins/bancay_catalog/products`. Cần `catalog.manage`; Product Manager và system administrator được cấp quyền.

1. Tạo danh mục: tên, slug (để trống để sinh từ tiếng Việt), parent, thứ tự, trạng thái, ảnh, SEO. Khi công khai cần ngày công khai. Không tạo quan hệ vòng; không xóa danh mục còn sản phẩm, banner hoặc danh mục con tham chiếu.
2. Tạo sản phẩm với Basic/SEO. Slug unique kể cả sản phẩm đã xóa mềm. Giá và tồn nằm ở SKU. `PUBLISHED` + ngày công khai kiểm soát khả năng bán trong domain; storefront chưa xây.
3. Mở chi tiết sản phẩm để tải nhiều ảnh, nhập alt, chọn ảnh chính và sort. JPEG/PNG/WebP ≤5 MB, ≤8000px mỗi chiều, ≤25 triệu pixels. Server xác minh MIME và decode/re-encode WebP bằng GD, tên ULID, không dùng tên file hoặc đường dẫn do client gửi. SVG/PHP không nhận. Xóa ảnh đang dùng bởi SKU bị từ chối đến khi gỡ liên kết có chủ đích.
4. Thêm nhóm option và values, mỗi dòng một value. Có tối đa 10 nhóm/100 values mỗi nhóm. Thứ tự giữ trong các row relational. Giữ nguyên label giữ nguyên ID; duplicate values bị từ chối. Sửa tên nhóm và thứ tự được; không xóa/đổi tên value đã dùng. Sau khi SKU tồn tại, không thêm/xóa nhóm option để tránh biến đổi nghĩa SKU cũ. Thêm values vào nhóm đang có vẫn được.
5. Tạo từng SKU hoặc **Sinh các tổ hợp còn thiếu**. Generator có xác nhận, tối đa 256 tổ hợp; chỉ tạo missing combinations. SKU mới ở `INACTIVE`, `CONTACT`, tồn 0. Prefix + product ID + hash tạo SKU; constraint global uniqueness vẫn bảo vệ. Chạy lại giữ nguyên SKU/price/stock/IDs đã có. Không tự xóa SKU.
6. Mở từng SKU: giá `FIXED` phải >0 VND nguyên, sale trống hoặc `0<sale<=price`; `CONTACT` để trống cả hai và không được checkout. Chọn ảnh thuộc đúng sản phẩm; bật/tắt trạng thái. Tổ hợp sau tạo giữ nguyên; ngưng SKU cũ nếu không còn bán. Không hard-code tên nhóm/trọng lượng/loại cây.
7. Link **Điều chỉnh tồn kho** cho biết physical/reserved/available. Nhập tồn thực tế mới và lý do; không dưới số đã giữ chỗ. Form gửi expected quantity, nếu có người khác thay đổi sẽ từ chối và yêu cầu tải lại. InventoryService row lock và transaction bảo vệ ghi. Không sửa reserved trực tiếp hoặc làm stock logic trong Blade.

Tìm theo name hoặc SKU, lọc category/status/featured, sort. Grid có ảnh, variant count, price range, khả dụng. CSV product là metadata; Inventory CSV chứa SKU/stock/reserved/available. Quyền export dùng cùng quyền module, không phải link công khai.

**Lưu trữ và xóa mềm sản phẩm** yêu cầu xác nhận trong UI. Product ngừng bán, SKU/inventory/order snapshots vẫn còn. Slug/SKU không được tái sử dụng âm thầm. Chức năng khôi phục trash/bulk import/đổi cấu trúc SKU có đơn lịch sử chưa nằm trong UI hiện tại; ưu tiên đặt status ARCHIVED nếu muốn có thể sửa lại dễ dàng. Article và Customer dùng archive/inactive; orders/history không có nút xóa.

Nội dung được nhập bằng textarea HTML và được Purifier lọc phía server; màn hình chi tiết escape source HTML để kiểm tra an toàn. Chưa bổ sung media library hoặc WYSIWYG/inline uploads. Đây là editor đơn giản trong scope core admin; frontend Phase 3 có thể render nội dung đã sanitize theo context.
