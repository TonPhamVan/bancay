# Guest cart và checkout

## Giỏ hàng

Session `store.cart` lưu `variant_id => {product_id, quantity}`; không lưu giá do browser đưa. Add merge quantity của cùng variant; update thay số lượng; remove/clear chỉ tác động session hiện tại. Buy Now gọi chính Add rồi chuyển checkout, **giữ các line đã có trong giỏ**; không có một luồng giá riêng.

Mỗi lần đọc/tính lại cart, server load current product/variant, visible category ancestry, ACTIVE status, current sale/regular price và inventory. Variant khác product, product ẩn/future/soft-deleted, CONTACT, thiếu inventory hoặc không đủ available bị chặn. Line hết stock còn hiển thị thông tin public và hướng dẫn giảm/xóa; entity ẩn chỉ hiển thị generic label. Có lỗi bất kỳ thì không hiện nút checkout, tổng các line hợp lệ không phải tổng được phép tạo đơn.

Tiền VND integer qua Money::multiply/add có overflow guard; formatter không convert BIGINT qua float. Discount phần trăm chỉ là nhãn hiển thị. Server từ chối price/unit_price/discount/subtotal/total từ form; checkout còn cấm client items, customer_id, status, shipping_fee, discount_amount và is_legacy. Server dựng items từ session, không tin hidden variant price/totals.

## Quote và idempotency

`GET /thanh-toan` tạo/reuse pending token ngẫu nhiên256bit, TTL30phút, gắn SHA-256 session ID và fingerprint gồm variant/product/quantity/current unit price. Token raw chỉ ở session/form, technical table chỉ giữ hashes, nullable order FK và timestamps. Không lưu contact/address trong bảng này. Một quote đã đổi giá/số lượng cần kiểm tra lại; refresh checkout cấp token mới. Token đã tạo order có thể replay để trả lại **cùng order**, không cấp đơn thứ hai.

`POST /thanh-toan` thực hiện:

1. Form Request validate thông tin liên hệ và allowlist address fields.
2. Transaction lock checkout_attempt, kiểm owner; nếu order_id đã có thì trả order cũ.
3. Lock các variant theo ID tăng dần trước khi so quote; đây là cửa sổ khóa chung với cập nhật giá/SKU.
4. Reload cart và so fingerprint, publication, availability/expiry.
5. Gọi **CreateOrder**, không tự viết orders/items/inventory từ controller. Action tính giá, reserve stock, snapshots, NEW và initial history trong cùng transaction.
6. Link attempt→order và commit. Lỗi trước commit rollback cả reservation, order, snapshots/history và attempt linkage.
7. Sau commit grant quyền xem mã đơn trong session; clear cart/token tương ứng. Replay cũ không xóa giỏ mới. Nếu response/session save bị mất nhưng session owner còn, retry cùng token lấy lại order/grant; không tạo lại order.

Database row lock là hàng rào chống trùng chính; HTTP session block giảm race giữa các tab; nút submit disabled chỉ hỗ trợ UX. Test thật hai fork/kết nối độc lập cùng attempt trả thành công cho cả hai, database chỉ tăng một order và một reservation. Test riêng cho lỗi sau reservation rollback, lần mua chiếc cuối cùng clear cart, quote repricing và replay sau giỏ mới.

## Order và quyền riêng tư

Guest không được tự gắn customer_id; Phase3 không tự tạo customer account/profile. Order giữ contact/address snapshot. Items giữ tên, SKU, mô tả options, unit_price, quantity/subtotal bất biến. Catalog sửa sau đó không đổi lịch sử. Stock physical không giảm lúc NEW; reserved tăng, sử dụng workflow Phase2 để release/deduct đúng trạng thái.

Success route dùng order_number + exact session grant, không dùng ID trần/phone làm quyền xem. Giữ tối đa20 grants gần nhất trong session, session lifetime theo config (local120phút). Page private/no-store/noindex/nofollow. Session khác hoặc mất session trả404, kể cả đang login admin; admin đọc order qua authorized admin route riêng. Ngày giờ success hiển thị GMT+7; database không bị đổi timezone.

Lookup order+phone được **DEFERRED**: chưa có xác minh OTP, rate policy và privacy contract cho khách khôi phục phiên. Không tạo endpoint lookup chỉ bằng ID hay số điện thoại.

## Shipping, thanh toán và giới hạn

Baseline Phase1/2 vẫn shipping_fee=0, discount=0; không có shipping calculator. Giao diện ghi rõ chưa tính phí tự động và cửa hàng trao đổi cách giao khi xác nhận; không quảng cáo chính sách miễn ship. Tổng hiện tại là snapshot trong đơn, không tự đổi sau đó. Không có payment gateway hoặc giả lập thanh toán thành công.

Pending token đã hết hạn không tạo order; chưa có scheduled cleanup. Completed attempts phải được giữ theo policy idempotency trước khi thêm retention; FK restrict tránh xóa order đã liên kết. Reservations không tự expire ở Phase3, admin phải xử lý/hủy theo Phase2. Production cần chốt policy giữ hàng, phí vận chuyển, customer/privacy/retention và shared locks nếu nhiều instance. [Proposal](phase-3/architecture-change-proposal.md), [schema evidence](phase-3/evidence/schema-comparison.json).
