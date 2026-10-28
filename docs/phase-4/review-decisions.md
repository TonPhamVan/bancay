# Quyết định duyệt dữ liệu Phase 4

Cập nhật 2026-10-28 (Asia/Saigon). Q01–Q06 được duyệt trực tiếp; các mục còn lại theo chỉ định “làm tất cả theo đề xuất của bạn”. Đã áp dụng lên local staging8002, giữ source legacy/production nguyên vẹn. [Báo cáo và kiểm chứng](review-application.md).

| Mục | Đối tượng | Quyết định đã áp dụng | Trạng thái |
|---|---|---|---|
| Q01 | products legacy ID 7 — Cây Phú Quý 01 | Gán Cây cảnh Nội thất (legacy ID 33); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q02 | products legacy ID 10 — Cây lưỡi hổ 01 | Gán Cây cảnh Nội thất (legacy ID 33); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q03 | articles legacy ID 29 — Top những loại Hoa trồng BAN CÔNG đẹp HÚT MẮT người nhìn. | Gán Kiến thức về cây (legacy ID 9); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q04 | articles legacy ID 31 — Thiết Mộc Lan - Loại cây mang lại nhiều TÀi LỘC cho Gia Chủ - caybonmua.vn. | Gán Kiến thức về cây (legacy ID 9); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q05 | articles legacy ID 48 — Top Những loại cây THÂN GỖ mà bạn Nên Có trong nhà. | Gán Kiến thức về cây (legacy ID 9); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q06 | articles legacy ID 63 — Cách chăm sóc cây Cẩm Tú Cầu Nhật lùn giúp cây tươi tốt. | Gán Cách chăm sóc cây (legacy ID 16); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q07 | articles legacy ID 67 — Cách cắt tỉa cây Cẩm Tú Cầu Nhật Giúp Cây ra bông hoa to đẹp. | Gán Cách chăm sóc cây (legacy ID 16); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q08 | articles legacy ID 69 — Các bệnh thường gặp trên cây Cẩm Tú Cầu mà bạn nên biết. | Gán Cách chăm sóc cây (legacy ID 16); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q09 | articles legacy ID 70 — So sánh giống Cẩm Tú Cầu ở Việt Nam và giống Cẩm Tú Cầu Nhật lùn | Gán Kiến thức về cây (legacy ID 9); giữ DRAFT. | APPLIED TO LOCAL STAGING |
| Q10 | banners | Banner7 missing category28 and banner11 unknown a2 remain excluded/inactive; do not guess placement. | APPROVED RETENTION POLICY ACTIVE |
| Q11 | orders | 493 invalid historical records retained in original archive/quarantine; no invented prices/quantities/contact. | APPROVED RETENTION POLICY ACTIVE |
| Q12 | users | Do not migrate2 legacy identities/MD5 or infer privileges; current native local-admin remains. | APPROVED RETENTION POLICY ACTIVE |
| Q13 | media | Use verified full-resolution candidates for5 absent references; preserve fallback for remaining missing article52 image. | APPLIED TO LOCAL STAGING |
| Q14 | publication | All migrated catalog/content stays DRAFT; banners INACTIVE; no public publication/branding or production approval. | APPROVED RETENTION POLICY ACTIVE |
| Q15 | Ảnh thiếu trong article52 | Người dùng chỉ định “1 ảnh thiếu bỏ qua” ngày 2026-10-28 (Asia/Saigon). Giữ fallback; đóng việc tìm ảnh và chờ duyệt ảnh này. | ACCEPTED — POLICY ACTIVE; không sửa DB |
