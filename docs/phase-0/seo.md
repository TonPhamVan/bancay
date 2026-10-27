# H. SEO URL Inventory

Nguồn: `application/config/routes.php:54–98`, controllers, shared views, DB đã restore và sitemap trong ZIP. Không crawl production.

| Old URL pattern | Entity / handler | New URL proposal | 301 required? |
| --- | --- | --- | --- |
| /{alias}-sp{id}.html | Product / Site::show_sp | /san-pham/{normalized-slug}-{legacy-id} | Có nếu entity được giữ |
| /{alias}-dm{id}.html | Category / Site::show_danhmuc | /danh-muc/{normalized-slug}-{legacy-id} | Có |
| /{alias}-bv{id}.html | Article / Site::show_baiviet | /bai-viet/{normalized-slug}-{legacy-id} | Có nếu bài được giữ |
| /{alias}-cm{id}.html | ArticleCategory / Site::show_chuyenmuc | /chuyen-muc/{normalized-slug}-{legacy-id} | Có |
| /tat-ca-san-pham | Product list | /san-pham | Có |
| /tat-ca-san-pham?keyword={q}&page={n} | Search; page zero-based | /tim-kiem?q={q}&page={n+1} (draft) | Giữ search semantics; tránh index kết quả tìm kiếm; kiểm tra query normalization |
| Category/article-category?page={n} | Query pagination trong CI Pagination | Canonical route mới với page convention được chốt Phase 3 | Có nếu đường dẫn đổi; giữ tương đương nội dung trang |
| Category?popup=off | Same category + popup cookie | Category canonical mới không query popup | Có; không biến popup query thành URL nội dung riêng |
| /site/show_sp/{id}, /site/show_danhmuc/{id}, /site/show_baiviet/{id}, /site/show_chuyenmuc/{id} | CI implicit routes có thể truy cập | Canonical entity mới | REVIEW analytics/access log; chuẩn hóa nếu có traffic |
| /sieu-thi-cay/{alias}-sp{id}.html | Link trong stale search view | Cần xác minh URL từng được public | REVIEW; không coi là route đang hoạt động vì không có route tương ứng |
| /upload/... | Media legacy | New storage path từ file mapping | Giữ path hoặc redirect hợp lệ nếu media URL đổi; xét hotlinks/HTML trước cutover |

Đề xuất thêm ID trong slug đích nhằm tránh alias/SKU trùng và giữ mapping ổn định; **chưa chốt canonical hoặc xây routes**. Phase 1/3 có thể chọn unique slug khác, miễn cập nhật toàn bộ mapping trước Phase 5. Không redirect hàng loạt mọi URL cũ về home.

## Export URL thực tế

[seo-urls.csv](evidence/seo-urls.csv) có **201 đường dẫn** từ toàn bộ entities: 115 Product + 8 Category + 67 Article + 11 ArticleCategory. Có source_status để không tự publish entity ẩn; mỗi dòng gồm legacy ID, old_path, old_path_encoded, new path draft và điều kiện redirect. Các giá trị alias gốc được giữ nguyên; có alias chứa Unicode/khoảng trắng nên phải bảo toàn cả dạng request percent-encoded. Đích draft được normalize an toàn và gắn ID.

ZIP còn có 10 archive logs đã scan offline: **11.202 dòng request parse được** và **166 entity paths** khác nhau. [seo-log-paths.csv](evidence/seo-log-paths.csv) ghi path và status quan sát; [logs-summary.json](evidence/logs-summary.json) ghi phương pháp/tổng hợp. Đây là bằng chứng lịch sử từ archive, không phải HTTP test hiện tại. Backend/nginx/rotated logs có thể trùng nên không coi counts là unique visits. Không xuất IP, query string, referrer hoặc user-agent.

Đây là danh sách URL sinh từ DB, không phải danh sách URL đã crawl hoặc xác nhận trả HTTP 200. URL alias khác vẫn có thể khớp route theo ID; cần bổ sung từ access logs/Search Console/sitemap hợp lệ khi được cung cấp. Với entity DROP, chọn 410 hoặc đích nội dung tương đương có đánh giá, không đặt 301 về entity không tồn tại.

## SEO rules và vấn đề

- Routes mặc định `site`; route aliases product/category/article/article-category có `.html`. Admin có cả friendly aliases `/them-*.html`, `/xu-ly-*.html`; không đưa admin vào sitemap hay redirect index công khai.
- `application/views/template.php:14`: trang có start_row>0 gắn noindex,follow; trang đầu index,follow. `:16` canonical chỉ xuất nếu có biến cati; chưa thấy các controller hiện tại gán biến này.
- Product có meta fields; category và article controller đọc meta fields không tồn tại trong schema của tương ứng tbldanhmuc/tblbaiviet. Cần fallback được thiết kế rõ trong nền mới.
- `created_at` là UNIX timestamp, không phải published_at đáng tin: bài viết ghi lại timestamp mỗi lần save. Trạng thái xuất bản phải bảo toàn riêng.
- `sitemap.xml` chứa **7 URL của greengen.com.vn**, không phải bancay.vn. Không dùng sitemap này làm nguồn URL chuẩn. `robots.txt` không thay thế inventory URL.
- Hai nhóm alias product trùng; old ID suffix phân biệt được. Helper slug để lại một số dấu chấm/ngoặc; phải giữ exact old URLs, bao gồm URL encoding, trước khi normalize đích.
- `.htaccess` ép HTTP→HTTPS theo SERVER_NAME, không có chính sách canonical host rõ; redirect không ghi R=301. Chưa xác nhận response code/host behavior trên production.

Phase 5 cần kiểm thử: bảng mapping không trùng nguồn/xung đột; target tồn tại; không vòng lặp/chain; đúng query pagination; entity ẩn không bị lộ; ảnh hoạt động; sitemap mới chỉ chứa canonical published URLs. **NOT TESTED:** HTTP status/redirect/robots/canonical thực tế vì chưa chạy website hoặc truy cập production.
