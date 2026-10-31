# Hướng dẫn nhanh cho nhân viên

## Đăng sản phẩm

1. Bấm biểu tượng lá **Cửa hàng** trên thanh Exment → Sản phẩm → Tạo mới. Điền tên/danh mục, để Nháp; có thể bỏ trống slug để tạo từ tên.
2. Mở chi tiết → tải ảnh → thêm nhóm phân loại. Ví dụ Kích thước: mỗi dòng `Nhỏ`, `Lớn`; Màu chậu: mỗi dòng `Trắng`, `Nâu`.
3. Xem số tổ hợp từ các nhóm đã lưu, rồi sinh SKU còn thiếu. SKU mới luôn Tạm ngưng, Liên hệ, tồn0. SKU đã có giữ giá/tồn/ID.
4. Trong bảng SKU, nhập giá VND nguyên, tồn thực tế và trạng thái. Cần lý do khi điều chỉnh tồn. Giá Liên hệ không cho checkout; giá sale không vượt giá gốc. Lưu bảng, tải lại chi tiết để cập nhật checklist.
5. Xem checklist và **Xem trước trên website**. Preview cần quyền quản lý sản phẩm, chỉ có SKU hoạt động và không đặt hàng. Nó không tự xuất bản.
6. Sửa thông tin → chọn Công khai và ngày công khai. Website chỉ hiển thị khi sản phẩm lẫn toàn bộ danh mục cha công khai. Thiếu ảnh/mô tả là cảnh báo cần chủ cửa hàng duyệt.

Khi lỗi nhóm phân loại, nội dung đã gõ giữ lại trong đúng nhóm và con trỏ quay về trường lỗi. Sửa rồi lưu lại. Giá trị đang dùng bởi SKU không được xóa/đổi tên; liên hệ quản trị nếu cần đổi cấu trúc.

## Kiểm tra kho

Tồn khả dụng = tồn thực tế − giữ chỗ. Bộ lọc Hết hàng `≤0`; Sắp hết `1–5`; Còn hàng `>0`. Bộ lọc áp dụng mọi SKU, gồm cả SKU/sản phẩm tạm ngưng. CSV dùng cùng bộ lọc. Dashboard cảnh báo SKU hoạt động có khả dụng≤5, gồm cả sản phẩm chưa công khai.

## Xử lý đơn

Tổng quan → **Xử lý đơn mới** chỉ lấy đơn website hiện tại. Đơn lịch sử legacy được gắn nhãn riêng, không dùng workflow tồn live.

Mở đơn, kiểm tra snapshot tên/SKU/giá/số lượng trước khi chuyển trạng thái. Để hủy, mở phần **Hủy đơn**, đọc mã đơn/số lượng/tác động, tích xác nhận rồi gửi. Ghi chú vẫn tùy chọn. Hủy trước giao giải phóng giữ chỗ, không tăng tồn thực tế; không thể mở lại đơn đã hủy. Giao hàng trừ tồn thực tế; trả hàng không tự nhập kho. Luôn kiểm tra lịch sử sau thao tác.

Tài khoản theo vai trò chỉ thấy chức năng đã được cấp; menu ẩn không thay thế kiểm tra quyền tại server. Không chia sẻ tài khoản quản trị, không xuất CSV dữ liệu khách hàng ngoài mục đích được giao.
