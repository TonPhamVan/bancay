# Áp dụng quyết định nội dung — 30/10/2026

Người dùng trả lời hai câu hỏi đang chờ sau run R1:

1. File/nguồn SKU, giá, tồn và ảnh thật cho phân bón, dụng cụ, thiết bị tưới, đèn: **“chưa có”**.
2. Nguồn/quyền dùng ảnh cảnh quan: **“Ảnh ý tưởng, được phép dùng”**.

## Catalog

Ghi nhận thiếu nguồn, giữ **BA-G04 BLOCKED — CONTENT REQUIRED**. Không tạo sản phẩm giả, không suy ra đồng ý thu hẹp scope launch, không đánh missing assortment là accepted. [Content inputs hiện tại](../content-inputs.md) và CSV119 candidates R1 vẫn là hàng đợi. Chưa thay catalog/giá/tồn/ảnh SKU, demo publication, decisions Phase4 hoặc snapshot đơn cũ.

## Ảnh ý tưởng được áp dụng

Ba ảnh gốc do người dùng gửi được giữ trong `legacy-private/business-remediation/20261030-r2/originals`, checksum gốc ghi trong [asset receipt](evidence/approved-assets.json). Decode/re-encode qua GD sẵn có thành WebP rộng960px, quality82; không tạo/thay nội dung ảnh. Kích thước gốc và kích thước output có trong receipt; không đưa PNG4MB vào public assets.

| Nội dung | WebP | Kích thước | Bytes |
|---|---|---|---|
| Ban công và tiểu cảnh | balcony-d47c632faafd.webp | 960×1286 | 126776 |
| Tiểu cảnh nước | water-feature-f778c80b9852.webp | 960×1432 | 166630 |
| Phối cây ven lan can | plant-composition-52697b6cc82e.webp | 960×1432 | 165500 |

Tổng458906bytes, so với12232318bytes ảnh gốc. Public assets có tên theo hash tại `public/storefront/landscape-ideas/`. Hero và Open Graph dùng ảnh ban công; gallery hiển thị đủ ba ảnh với alt/dimensions, ảnh gallery lazy-load. Hero crop bằng CSS; gallery giữ tỷ lệ ảnh gốc.

[Trang tư vấn local](http://localhost:8000/tu-van-canh-quan#landscape-ideas-title) ghi rõ **“Ảnh ý tưởng minh họa”**, **“không phải ảnh công trình cửa hàng đã thực hiện”**. Không dùng ảnh làm hồ sơ dự án đã thi công hoặc ảnh catalog hàng thật. Số điện thoại/Facebook và luồng CTA giữ từ settings đã duyệt; không tự gửi tin hoặc gọi.

Files ứng dụng thay đổi: `LandscapeController.php`, `home/landscape.blade.php`, ba asset WebP. Không đổi schema/migration/dependency, không ghi database nghiệp vụ. Original demo/migrated data, admin/local-admin, source/stage và backup giữ nguyên. README/AGENTS/content inputs cập nhật quyết định mới; report R1 được thêm note superseding, các evidence R1 không ghi đè.

## Verification

- Existing BusinessStorefrontTest: **5/5 PASS,34 assertions,0 failed/0 skipped**, chỉ dùng `bancay_test` theo guard. [Output summary](evidence/tests.json).
- Production Vite build PASS; Pint controller PASS. Không chạy lại full commerce regression cho thay đổi static ảnh/view này;180/1187 từ R1 vẫn là checkpoint lịch sử, không gắn là test fresh R2.
- IAB Chromium1440/768/390/320px: tất cả ảnh mới tải được, alt/dimensions đầy đủ,1 H1, không body overflow; console warning/error rỗng. [Browser](evidence/browser.json), [desktop](evidence/gallery-desktop.png), [mobile](evidence/gallery-mobile.png).
- HTTP ảnh và SEO meta kiểm riêng trong [verification](evidence/verification.json): ba ảnh200/WebP/checksum khớp, service200, canonical/noindex/Open Graph đúng. Không rerun historical collectors hoặc overwrite kết quả R1.
- Chrome/Edge/Safari riêng không test trong follow-up này; BA-V01 giữ nguyên.

**Quyền dùng ảnh ý tưởng: RESOLVED / APPLIED. BA-G04 vẫn CONTENT REQUIRED. PHASE6 BUSINESS / PRODUCTION STATUS: NOT READY.** Các mục ảnh sản phẩm/giá/chính sách còn lại và P5-B01/B02/B03 không được tự đóng bởi approval này. Không cutover, production migration, commit hoặc push.
