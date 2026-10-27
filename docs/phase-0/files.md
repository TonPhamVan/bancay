# G. File Migration Inventory

Nguồn: ZIP đã kiểm chứng CRC và SHA-256; đối soát với DB đã restore. [Inventory toàn bộ 3.644 file](evidence/archive-inventory.csv), gồm [3.321 file document root](evidence/source-inventory.csv) và 323 file logs/stats/protected-list ngoài document root; [inventory 2.947 file thuộc upload/images/ckeditor/ckfinder](evidence/file-inventory.csv); [file thiếu](evidence/missing-file-references.csv); [duplicate theo SHA-256](evidence/duplicate-files.json).

| Vùng | File thường | Bytes | Vai trò / phân loại |
| --- | ---: | ---: | --- |
| upload/ | 1.843 | 582.070.658 | Media nghiệp vụ và CKFinder; MIGRATE các file cần giữ; REVIEW phần còn lại |
| images/ | 157 | 11.097.001 | Logo/static icons/placeholders/admin UI; MIGRATE approved brand assets; REBUILD/DROP UI assets |
| ckeditor/ | 314 | 3.324.183 | Editor bundle/samples/icons; REPLACE, không coi là article uploads |
| ckfinder/ | 633 | 3.670.990 | File manager bundle, userfiles/sample assets; REPLACE code; REVIEW user content nếu có |

Không thấy `.php/.phtml/.phar/.exe/.sh` trong upload/ của snapshot. Đây không phải kết quả malware scan hay chứng minh MIME an toàn; SVG/HTML và file có extension khác vẫn cần kiểm tra khi migrate.

## Mapping storage dự kiến

| Legacy path / nguồn reference | Đích dự kiến | Quy tắc / trạng thái |
| --- | --- | --- |
| upload/sp/{mY(created_at)}/{image[i]} | storage/app/public/products/{product-key}/gallery/{safe-file} | JSON image giữ thứ tự; lưu legacy_path→new_path; không tính lại folder theo ngày hiện tại |
| upload/sp/{mY(created_at)}/{phanloai.img[i]} | storage/app/public/products/{product-key}/variants/{variant-key}/{safe-file} | Liên kết theo index variant, không chỉ theo SKU đang trùng |
| upload/news/{tblbaiviet.image} | storage/app/public/articles/{article-key}/{safe-file} | Ảnh thumbnail/article cover; ảnh trong HTML là references riêng |
| upload/images/... trong content/sapo HTML | storage/app/public/content/{safe-file} hoặc ownership xác định | Rewrite src sau mapping; không phá ảnh dùng chung; kiểm tra lazyload/srcset/CSS URL bổ sung ở Phase 4 |
| upload/category/{tbldanhmuc.image} | storage/app/public/categories/{category-key}/{safe-file} | Category image; không nhầm với upload/ của article categories |
| upload/{tblchuyenmuc.image} | storage/app/public/article-categories/{category-key}/{safe-file} | Source upload handler dùng thư mục upload gốc |
| tblbanner.file (đã có prefix upload/banner/) | storage/app/public/banners/{banner-key}/{safe-file} | Không tự prefix thêm lần nữa; validate placement/link |
| upload/slider/{tblslider.image} | storage/app/public/banners/slider/{banner-key}/{safe-file} | Banner placement slider |
| tbladmin.image (thường upload/avatar/...) | storage/app/public/avatars/{user-key}/{safe-file} | REVIEW avatar/PII và account được giữ; không công khai inventory danh tính |
| images/{tblfooter.logo} | storage/app/public/settings/{safe-file} | Giữ logo đã được duyệt; UI icons cũ không mặc định migrate |
| CKFinder config baseUrl=/upload/ | Map theo nội dung thực tế, không theo tên thư mục ckfinder/ | CKFinder ghi dưới upload/, không chỉ ckfinder/userfiles; giữ file cần dùng và thay cơ chế upload |

Đây là mapping draft, chưa tạo Laravel storage hay copy file sang hệ mới.

## Kết quả đối soát

- 840 đường dẫn local khác nhau có reference trong trường DB/HTML được scan; 834 có file, **6 không có trong archive**.
- 2.113 file trong bốn vùng chưa thấy reference từ DB. **Không coi đó là file mồ côi để xóa**: có icons/editor/vendor assets, references từ PHP/JS/CSS, thumbnails và nội dung ngoài phạm vi src/poster đã parse.
- 329 nhóm nội dung trùng SHA-256, tương ứng 537 bản sao ngoài bản đầu; gồm cả tài nguyên thư viện. Chưa deduplicate hoặc thay đường dẫn.
- Nhận diện folder product dựa theo timezone Asia/Saigon (+07) và created_at; đối soát khớp không thay thế việc xác minh php.ini timezone hosting, đặc biệt record sát ranh tháng.

6 đường dẫn thiếu nằm ở content bài 52, content sản phẩm 8 và 2 gallery references của sản phẩm 102. Hai filename của product 102 có đoạn tên không có extension ảnh bình thường. Danh sách đầy đủ và owner IDs có trong CSV, không sửa dữ liệu gốc.

MUST MIGRATE: ảnh catalog/content đang sử dụng, giữ ownership và mapping đường dẫn. SHOULD MIGRATE: media của entities ẩn còn cần lịch sử. OPTIONAL: thumbnail có thể tạo lại. DROP khỏi ứng dụng mới: bundles/editor samples/OS metadata/debug UI sau khi đối chiếu; vẫn giữ trong backup gốc.

NOT TESTED: mở/render từng ảnh, EXIF/MIME/malware scan, toàn bộ URL CSS/srcset/lazyload, tính đầy đủ so với filesystem production chưa được cung cấp. Phase 4 phải validate nội dung file và kiểm tra ảnh trước/sau migration, không chỉ copy theo extension.
