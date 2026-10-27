# Architecture Change Proposal — guest checkout reliability

Ngày 28/10/2026; phạm vi Phase 3. Nền tảng Laravel/Exment, một application/repository/database và17 domain tables giữ nguyên.

**Vấn đề:** schema foundation không có checkout idempotency key. Session token/disabled button đơn lẻ không bảo vệ đầy đủ retry sau khi DB đã commit nhưng response/session-save lỗi, hoặc hai POST đồng thời. Session cart cũng không phải reservation.

**Giải pháp tối thiểu:** session cart lưu variant_id→quantity; Laravel session blocking cho các route cart/checkout. Thêm bảng kỹ thuật `checkout_attempts` trong cùng DB: SHA-256 token unique, SHA-256 session owner, SHA-256 quote fingerprint, nullable order_id FK, timestamps. Checkout token ngẫu nhiên256-bit do server sinh, gắn session; transaction lock attempt row, kiểm cart/publication, gọi CreateOrder (nested transaction), ghi order_id cùng commit. Retry cùng token trả cùng order dù session cart đã clear. Không giữ PII trong bảng attempt; success page chỉ dùng order grant được lưu trong session.

**Ảnh hưởng/migration cost:** một migration additive nhỏ, không sửa bảng/migrations Phase 1, không backfill legacy hay tạo backend/API riêng. Existing admin/actions giữ chữ ký tương thích. Tests dùng riêng bancay_test, kiểm rollback/idempotency/owner isolation. Checkout session dùng file-cache atomic locks hiện có cho local; production phải cấu hình shared lock/session store nếu scale nhiều instance, trong release riêng.

**Backward compatibility:** tất cả caller CreateOrder trước đó tiếp tục hoạt động. Bổ sung publication policy dùng chung cho public surfaces và CreateOrder; giữ caller admin/domain hiện tại tương thích bằng optional storefront flag mặc định false. Catalog ancestor visibility được kiểm lại khi public checkout, không cho đặt sản phẩm thuộc category ẩn qua forged ID.

**Quyết định thực hiện:** thay đổi additive cần thiết để hoàn thành yêu cầu chống duplicate order và IDOR Phase 3; nằm trong phạm vi được giao. Không thay nền tảng hoặc chạy migration production. Order lookup number+phone để backlog vì chưa có OTP và không cần cho core guest checkout; success page session-scoped đủ cho phase này.
