# Thu gọn phần giới thiệu danh mục — 30/10/2026

Người dùng báo khoảng trắng lớn bên phải tại `/danh-muc/cay-canh-noi-that-legacy-33`. Nguyên nhân: toàn bộ HTML mô tả dài hiển thị ngay trong header với `max-w-2xl`, làm danh sách sản phẩm bắt đầu ở1995.5px trên viewport1280px.

Header mới hiển thị preview tối đa220 ký tự,2 dòng; **Xem thêm giới thiệu / Thu gọn giới thiệu** dùng native details/summary, hoạt động bằng chuột/bàn phím và không cần JavaScript. Preview là plain text được escape, chuẩn hóa khoảng trắng/entities trong Presentation. Nội dung HTML đầy đủ vẫn qua ContentHtml, giữ nguyên trong DOM và mở rộng theo chiều rộng phần nội dung. Không viết lại nội dung legacy, meta, URL, giá hoặc publication.

Files: `Presentation.php` thêm excerpt phục vụ hiển thị; `CatalogController.php` chuẩn bị preview; `products/index.blade.php` thêm disclosure và giảm padding header. Không thay schema/migration/dependency, không ghi database nghiệp vụ; demo/migrated/source/stage/accounts giữ nguyên.

Verification:

- StorefrontTest + BusinessStorefrontTest: **35/35 PASS,278 assertions,0 failed/0 skipped**, trên bancay_test; [summary](ui-category-spacing/evidence/tests.json). Vite production build và Pint2 PHP files PASS.
- [Browser before/after](ui-category-spacing/evidence/verification.json): product grid từ1995.5px xuống595.5px, sớm hơn1400px tại cùng viewport1280px; vẫn24 cards/trang,44 sản phẩm tổng. [Before](ui-category-spacing/evidence/before.png), [after](ui-category-spacing/evidence/after-desktop.png).
- Mở bằng chuột, thu gọn bằng Enter PASS. Full description normalized DOM hash trước/sau cùng `56d6bc869b4048a3cee1f3aa5461d00534bded36858691649fdf5f6da3d18477`; preview ẩn khi mở. Full description rộng1201px ở viewport1280, thay cho cột hẹp.
- IAB1440/768/390/320px: preview56px/2 dòng, không body overflow,24 cards giữ nguyên.320px mở/thu gọn bằng bàn phím PASS, nội dung rộng265px trong body305px, không tràn. [Mobile](ui-category-spacing/evidence/after-mobile.png). Console warnings/errors rỗng.
- Native summary được kiểm bằng AX và CSS locator; Playwright role locator không nhận summary trong provider này, đã chuyển sang selector DOM của chính summary và xác minh kết quả. Không coi lỗi locator là lỗi ứng dụng.

Đây là sửa bố cục local. Không thay nội dung cần business duyệt, không đóng BA-G04/BA-V01 hoặc các gate production; Phase6 vẫn NOT READY. Evidence mới riêng; các run trước giữ nguyên.
