# Nguồn ảnh demo Phase3

Ngày tạo:28/10/2026. Ba ảnh mới được sinh bằng OpenAI image generation trong phiên phát triển, dùng để minh họa **dữ liệu tổng hợp local**; không lấy từ Shopee, catalog đối thủ, legacy uploads hoặc ảnh người dùng. Không dùng ảnh này làm xác nhận loại/chất lượng/kích thước hàng bán thật.

| Asset versioned | Nội dung | Dùng trong seeder |
| --- | --- | --- |
| resources/demo-images/hero.webp | Góc vườn ngập nắng, lá Monstera, chậu đất nung; nền trái phù hợp headline | Hero banner, category, article và gallery minh họa |
| resources/demo-images/monstera.webp | Monstera trong chậu đất nung trên nền studio kem | Product/category/article demo Monstera |
| resources/demo-images/zz.webp | Cây kim tiền trong chậu gốm trắng trên nền kem | Product/category/article demo kim tiền |

PNG gốc giữ private tại backups/phase-3, ngoài Git. Bản WebP chuyển định dạng bằng PHP GD quality85; không lấy ảnh fallback SVG làm ảnh sản phẩm chính. Seeder copy vào Laravel public storage, Blade lấy URL từ records; không hardcode đường dẫn demo vào views. Bản SVG fallback là vector leaf/pot tự viết cho file thiếu. Product/article demo ghi rõ hình và nội dung minh họa.

Ảnh catalog production phải được xác nhận quyền sử dụng và khớp SKU thực tế ở phase import/content; không tự thay thế ảnh thật bằng ảnh AI. Checksums/bytes của ba assets nằm trong source manifest của Phase3.
