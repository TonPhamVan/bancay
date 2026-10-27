# Quyền và tài khoản — Phase 2

Dùng native Exment User CustomValue + LoginUser + RoleGroup + RoleGroupPermission + RoleGroupUserOrganization. Không tạo bảng users/roles hoặc password Customer mới. Native system administrator hiện tại (`local-admin`) giữ nguyên credential và quyền.

| Vai trò | Catalog / Inventory | Orders | Customers / Addresses | Content / Banners | Public settings | Native users/roles |
| --- | --- | --- | --- | --- | --- | --- |
| Exment System Administrator | Có | Có | Có | Có | Có | Có |
| Product Manager (`bancay_product_manager`) | Có | Không | Không | Không | Không | Không |
| Order Manager (`bancay_order_manager`) | Không | Có | Có | Không | Không | Không |
| Content Manager (`bancay_content_manager`) | Không | Không | Không | Có | Không | Không |
| Chưa gán role | Không | Không | Không | Không | Không | Không |

Permissions core là 5 Laravel abilities: `catalog.manage`, `commerce.manage`, `customers.manage`, `content.manage`, `settings.manage`. Mỗi module có một page plugin tương ứng; native **Access** của plugin là grant manage module. Không cấp Manage Setting/Manage Plugin/System hay quyền bảng User cho presets. View/write/export hiện cùng module capability; chưa tách read-only exporter/price editor riêng. Settings không có preset non-admin mặc định, có thể chủ động cấp Access BancaySettings qua native role editor.

Bridge kiểm tra **direct user membership** trong native role records, plugin active và grant Access; không dựa UI visibility. Không tự cho phép khi global permission setting bị tắt. Không suy rộng quyền native `All Data`/Manage Plugin thành quyền bán hàng. Organization inheritance chưa được bridge, cần gán role trực tiếp cho User. Dashboard chỉ render stats/links thuộc quyền hiện tại.

## Quản trị native

- Người dùng: `/admin/data/user`; tạo/sửa profile, chọn role trong Role Setting và Login Authorization Granted. Gán role trong form User hoặc Step 2 của Role Setting. Chỉ system administrator được truy cập các identity/role routes.
- Role editor: `/admin/role_group`; chọn row → Permission Setting → Plugin Permission Setting. Tick **Access** đúng module, save; Step 2 gán User trực tiếp. Không bật toàn bộ quyền chỉ để làm menu xuất hiện.
- Login management: `/admin/loginuser`, cùng native Login Setting trong User form. Password dùng Hash/bcrypt và native password history/reset flag. Không import MD5; legacy accounts cần provision/reset riêng Phase 4.
- **Vô hiệu hóa đăng nhập native** bằng bỏ Login Authorization Granted/remove login identity; hồ sơ có thể vẫn giữ. Native v6 xóa LoginUser row ở thao tác này, không có cột boolean active trong schema foundation. Kích hoạt lại cần provision credential mới/reset. FK history actor SET NULL giữ history còn nguyên. Không mô tả đây là soft-disable nguyên LoginUser row.
- Reset password dùng cơ chế native; nên force first change, không gửi plain password qua email (local mail log cũng không bật send_password). User tự thao tác nếu là credential cá nhân; tests gọi native service trên synthetic identity. Không thay mật khẩu `local-admin` trong Phase 2.

`StoreAdminGuard` kiểm tra LoginUser/base profile vẫn còn ở mỗi request, chặn phiên cũ khi identity bị xóa, rồi Gate-check module. Scoped child queries chống đổi image/option/variant/address ID sang owner khác. Controller cũng authorize độc lập. Native operation audit redact password/current_password/confirmation/token kể cả escaped/nested JSON.

## Legacy role review

| Legacy | Evidence Phase 0 | Quyết định migration |
| --- | --- | --- |
| 1 | Label admin; cả hai snapshot accounts đều role 1 | Candidate System Administrator, **REVIEW REQUIRED từng người**; không tự grant/import |
| 2 | Label Quản lý nhưng bị guard 1/3/5 loại | REVIEW REQUIRED; tên role không chứng minh phạm vi thực tế |
| 3 | Label Nhân viên; guard catalog chấp nhận | Candidate Product Manager nếu nghiệp vụ duyệt; REVIEW REQUIRED |
| 4 | Label Viết bài; không có snapshot user dùng | Candidate Content Manager sau review, không tự grant |
| 5 | Code guard chấp nhận nhưng không có row tblrole tương ứng | REVIEW REQUIRED, chưa xác định ý nghĩa; không đoán |

Không có legacy identity/password/role được import. Các native test identities Phase 2 dùng dữ liệu tổng hợp; khi test kết thúc thu hồi role/system-admin grant và rotate password ngẫu nhiên, không lưu credential mặc định trong source/Git. Tài khoản thật `local-admin` không đổi.
