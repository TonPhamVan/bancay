# PROMPT – ADMIN UX, EXMENT NATIVE & IMPROVEMENT REVIEW

Bạn là **Senior Laravel/Exment Architect + UX/UI Expert + Product Owner + Code Reviewer + QA Lead**.

Hãy đánh giá toàn diện hệ thống Admin của dự án `bancay.vn` trước Phase 6 – Production Release.

Mục tiêu không chỉ tìm lỗi mà còn **chủ động phát hiện cơ hội cải tiến giao diện, nghiệp vụ, kiến trúc và cách sử dụng Exment**.

**CHỈ REVIEW + TEST + ĐỀ XUẤT. KHÔNG SỬA CODE.**

## 1. Đầu vào bắt buộc

Đọc và đối chiếu:

- Master Plan của dự án.
- Báo cáo Phase 0–5 và Phase 5.5 nếu có.
- Source Laravel + Exment hiện tại.
- Source các plugin/custom extension.
- Exment configuration, Custom Table, Form, Grid, Workflow, Permission.
- Database schema, Models, Services, Actions.
- Báo cáo kiểm thử và Risk Register.
- Tài liệu và source của đúng phiên bản Exment đang sử dụng.
- Yêu cầu khách hàng về giao diện quản lý sản phẩm tương tự Shopee Seller.

Phải kiểm tra source thực tế và Admin UI, không chỉ dựa vào báo cáo cũ.

## 2. Mục tiêu đánh giá

Trả lời 5 câu hỏi chính:

1. Admin hiện tại có dễ sử dụng với nhân viên cửa hàng không?
2. Luồng quản lý sản phẩm, phân loại, SKU, giá, tồn kho và đơn hàng đã thuận tiện chưa?
3. Tại sao sử dụng plugin/custom code thay vì tính năng mặc định của Exment?
4. Có thể giảm bớt plugin, đơn giản hóa kiến trúc mà không mất nghiệp vụ không?
5. Ngoài các vấn đề hiện tại, **nên cải tiến thêm những gì để hệ thống chuyên nghiệp, dễ sử dụng và dễ mở rộng hơn?**

Không chỉ báo cáo phần chưa đạt. Phải chủ động đưa ra các đề xuất có giá trị.

---

# PHẦN A – ADMIN UX REVIEW

## 3. Đánh giá giao diện tổng thể

Kiểm tra thực tế:

- Dashboard
- Sidebar/Menu
- Navigation
- Form
- Grid/List
- Search/Filter
- Validation
- Thông báo
- Dialog xác nhận
- Responsive
- Phân quyền

Chấm điểm từ 1–5 cho:

- Dễ học
- Dễ sử dụng
- Tốc độ thao tác
- Tính nhất quán
- Hạn chế lỗi người dùng
- Khả năng tìm kiếm dữ liệu
- Tính trực quan
- Khả năng sử dụng trên màn hình nhỏ

Cần có bằng chứng và lý do cho từng điểm.

## 4. Review từng module

Bắt buộc đánh giá:

- Product
- Category
- Product Options
- Variants/SKU
- Inventory
- Orders
- Customers
- Articles
- Banners
- Settings
- Users/Roles/Permissions
- Dashboard

Với từng module, xác định:

- Chức năng đã đáp ứng chưa?
- Người dùng có dễ hiểu không?
- Có bước thừa không?
- Có phải nhập dữ liệu lặp lại không?
- Có thao tác gây nhầm lẫn không?
- Có thể cải tiến bằng cấu hình Exment không?
- Có cần custom code thực sự không?

## 5. Product Management UX

Đây là phần ưu tiên cao nhất.

Đánh giá khả năng quản lý sản phẩm theo trải nghiệm Shopee Seller:

```text
Create Product
  ↓
Basic Information
  ↓
Images
  ↓
Classification Group 1
  ↓
Option Values
  ↓
Classification Group 2
  ↓
Variant Combinations
  ↓
SKU / Price / Stock
  ↓
Save / Publish
```

Kiểm tra:

- Tạo nhóm phân loại có dễ không?
- Nhập nhiều option có thuận tiện không?
- Tự sinh combination có đúng không?
- Có chỉnh sửa giá, SKU, tồn kho hàng loạt không?
- Có thể sửa nhanh mà không cần mở nhiều màn hình không?
- Có cảnh báo khi thay đổi option làm ảnh hưởng variant không?
- Có lưu nháp không?
- Validation có rõ ràng không?

Nếu chức năng đúng nhưng khó sử dụng, phải ghi nhận UX issue.

## 6. Order Management UX

Đánh giá:

- Nhận biết đơn mới.
- Xem nhanh chi tiết đơn.
- Lọc theo trạng thái.
- Tìm theo mã đơn/SĐT.
- Cập nhật trạng thái.
- Xem lịch sử xử lý.
- Kiểm tra giá, phân loại, số lượng.
- Hạn chế thao tác nhầm.
- Khả năng xử lý nhiều đơn liên tiếp.

Đo số bước thao tác với một số nghiệp vụ phổ biến nếu có thể.

---

# PHẦN B – EXMENT NATIVE VS PLUGIN

## 7. Plugin Inventory

Thống kê tất cả thành phần đang sử dụng:

```text
EXMENT NATIVE
EXMENT CONFIGURATION
EXMENT PLUGIN
LARAVEL CUSTOM CODE
THIRD-PARTY LIBRARY
```

Với mỗi thành phần ghi:

- Chức năng
- Source location
- Phụ thuộc
- Tình trạng sử dụng thực tế
- Lý do tồn tại
- Có native alternative không
- Maintenance risk
- Upgrade compatibility

Phân biệt rõ plugin Exment với Composer package hoặc frontend library.

## 8. Kiểm tra khả năng mặc định của Exment

Xác minh khả năng sẵn có của:

- Custom Table/Column
- Form
- Grid/List/View
- Relationship/Lookup
- Search/Filter/Sort
- Validation
- Role/Permission
- Workflow
- Dashboard
- Import/Export
- File Attachment
- Notification/Event
- API

Chỉ kết luận tính năng được hỗ trợ khi có bằng chứng từ source, tài liệu đúng phiên bản hoặc UI test thực tế.

## 9. Tại sao phải sử dụng plugin?

Với mỗi plugin, bắt buộc trả lời:

1. Exment mặc định có tính năng tương đương không?
2. Nếu có, tại sao không sử dụng?
3. Native có đáp ứng đầy đủ nghiệp vụ không?
4. Plugin bổ sung lợi ích gì?
5. Plugin có đang làm trùng functionality không?
6. Có cách đơn giản hơn không?
7. Nếu bỏ plugin thì dữ liệu, UI, workflow và frontend bị ảnh hưởng gì?

Phân loại nguyên nhân:

```text
NATIVE NOT SUPPORTED
NATIVE PARTIALLY SUPPORTED
CUSTOM BUSINESS RULE REQUIRED
UX LIMITATION
INTEGRATION REQUIREMENT
UNNECESSARY CUSTOMIZATION
UNKNOWN
```

## 10. Nguyên tắc lựa chọn giải pháp

Ưu tiên:

```text
Exment Native
    ↓
Exment Configuration
    ↓
Supported Extension Mechanism
    ↓
Minimal Laravel Custom Code
    ↓
Custom Exment Plugin
```

Không sửa Exment Core chỉ để tránh sử dụng plugin.

Không ép Exment Custom Table thay thế các bảng nghiệp vụ Laravel nếu không tương thích.

Phải giữ một nguồn dữ liệu chính, tránh duplicate dữ liệu giữa Exment và Laravel.

## 11. Native Replacement Assessment

Với mỗi plugin có thể thay thế, đánh giá:

- Tính tương đương chức năng.
- Khả năng giữ nguyên dữ liệu.
- UX sau khi thay thế.
- Tính tương thích với Exment.
- Chi phí chuyển đổi.
- Regression cần chạy.
- Khả năng rollback.

Kết luận:

```text
KEEP NATIVE
REPLACE WITH NATIVE
SIMPLIFY CUSTOM
KEEP PLUGIN – JUSTIFIED
NEEDS VERIFICATION
```

---

# PHẦN C – CHỦ ĐỘNG ĐỀ XUẤT CẢI TIẾN

## 12. UX Improvement Opportunities

Không chỉ tìm lỗi. Hãy chủ động xác định cách làm tốt hơn.

Đề xuất các cải tiến về:

- Bố cục form.
- Nhóm field theo nghiệp vụ.
- Số click thao tác.
- Search/filter.
- Quick edit – chỉnh sửa nhanh.
- Bulk actions – thao tác hàng loạt.
- Hướng dẫn nhập dữ liệu.
- Validation messages.
- Trạng thái dữ liệu.
- Cảnh báo trước khi xóa/thay đổi.
- Dashboard.
- Responsive.
- Điều hướng giữa các màn hình.

Với mỗi đề xuất phải giải thích rõ:

```text
Current UX
→ Problem/Opportunity
→ Proposed UX
→ Expected Benefit
```

Không đề xuất chung chung như "cải thiện giao diện".

## 13. Product Management Improvement

Đặc biệt đánh giá và đề xuất:

- Form sản phẩm chia thành các section/tab hợp lý.
- Tạo option bằng thao tác thêm dòng trực tiếp.
- Tự sinh Variant Matrix.
- Sửa giá/SKU/tồn kho theo bảng.
- Cập nhật giá hàng loạt.
- Sao chép sản phẩm có sẵn.
- Upload và sắp xếp nhiều ảnh.
- Cảnh báo dữ liệu variant bị thiếu.
- Preview sản phẩm trước khi publish.
- Lưu nháp.
- Cải thiện nhập thông tin SEO.

Không mặc định cần triển khai tất cả. Phải đánh giá **giá trị, độ cần thiết và khả năng thực hiện bằng Exment Native**.

## 14. Inventory Improvement

Đánh giá khả năng cải thiện:

- Xem nhanh tồn kho.
- Filter sản phẩm hết hàng.
- Cảnh báo sắp hết hàng.
- Điều chỉnh số lượng nhanh.
- Lịch sử thay đổi tồn kho.
- Tránh nhập số lượng sai.
- Đồng bộ trạng thái còn hàng/hết hàng lên frontend.

Ưu tiên giải pháp đơn giản phù hợp single-store.

## 15. Order Management Improvement

Đề xuất nếu phù hợp:

- Dashboard đơn mới.
- Tab theo trạng thái.
- Quick status update.
- Xử lý nhiều đơn liên tiếp.
- Filter ngày/khách hàng.
- In thông tin đơn hàng.
- Ghi chú nội bộ.
- Hiển thị lịch sử thao tác.
- Cảnh báo khi hủy đơn.
- Thông báo đơn mới.

Không tự thêm nghiệp vụ vận chuyển/thanh toán phức tạp ngoài scope.

## 16. Business Workflow Improvement

Đề xuất cải thiện quy trình làm việc thực tế của cửa hàng.

Ví dụ:

```text
Thêm sản phẩm
→ Định giá
→ Nhập tồn kho
→ Kiểm tra
→ Đăng bán
```

Hoặc:

```text
Nhận đơn
→ Xác nhận
→ Chuẩn bị
→ Giao hàng
→ Hoàn thành
```

Đánh giá có thể sử dụng Exment Workflow mặc định không.

Chỉ đề xuất Workflow khi quy trình thực tế cần, tránh tăng số thao tác không cần thiết.

## 17. Exment Configuration Improvement

Chủ động tìm những phần có thể cải thiện bằng cấu hình mà không cần viết code.

Ví dụ:

- Form layout
- Default values
- Required fields
- Related records
- Custom Views
- Display columns
- Saved filters
- Permissions
- Workflow
- Dashboard widgets
- Import/Export configuration

Ưu tiên các thay đổi ít rủi ro và dễ bảo trì.

## 18. Architecture Simplification

Đánh giá cơ hội giảm:

- Plugin không cần thiết.
- Code custom trùng chức năng.
- Controller quá lớn.
- Business logic duplicate.
- Database query duplicate.
- Event/Listener không cần thiết.
- Frontend phụ thuộc trực tiếp implementation Exment.
- Những lớp abstraction không tạo giá trị thực tế.

Phải phân biệt:

```text
SIMPLIFICATION
```

với:

```text
RISKY REWRITE
```

Chỉ đề xuất refactor khi có lợi ích rõ và kiểm soát được regression.

## 19. Performance & Maintainability Improvement

Chủ động tìm cơ hội cải tiến:

- N+1 query.
- Grid tải quá nhiều dữ liệu.
- Tìm kiếm chậm.
- Form tải quá nhiều relationship.
- Xử lý ảnh.
- Validation lặp lại.
- Code/plugin khó nâng cấp.
- Thiếu automated tests.

Không yêu cầu tối ưu hóa khi chưa có vấn đề hoặc bằng chứng.

## 20. Security & Data Safety Improvement

Đề xuất cải thiện:

- Role/Permission.
- Kiểm soát thao tác xóa.
- Validation.
- Upload.
- Audit log.
- Truy cập dữ liệu nhạy cảm.
- Tránh thay đổi sai Order/Inventory.
- Khả năng khôi phục thao tác.

Phân biệt cải tiến bảo mật bắt buộc với hardening tùy chọn.

---

# PHẦN D – ĐÁNH GIÁ GIÁ TRỊ ĐỀ XUẤT

## 21. Phân loại đề xuất

Mỗi recommendation phải thuộc một trong:

```text
QUICK WIN
NATIVE REPLACEMENT
UX IMPROVEMENT
BUSINESS PROCESS IMPROVEMENT
ARCHITECTURE SIMPLIFICATION
SECURITY IMPROVEMENT
FUTURE ENHANCEMENT
```

**Quick Win** là cải tiến nhỏ, ít rủi ro, mang lại lợi ích rõ ràng.

## 22. Đánh giá effort, benefit và risk

Với mỗi đề xuất:

| Tiêu chí | Mức |
|---|---|
| Business Value | High/Medium/Low |
| UX Impact | High/Medium/Low |
| Implementation Effort | S/M/L |
| Technical Risk | High/Medium/Low |
| Native Exment Feasibility | Yes/Partial/No/Unknown |
| Regression Impact | High/Medium/Low |

Không đưa ra ước lượng công sức chính xác khi chưa đủ thông tin.

## 23. So sánh trước và sau cải tiến

Đối với các cải tiến quan trọng phải mô tả:

```text
BEFORE
Hiện tại người dùng phải làm gì?

PROBLEM
Khó khăn cụ thể là gì?

AFTER
Đề xuất cách thao tác mới.

BENEFIT
Giảm bao nhiêu bước?
Có dễ hiểu hơn không?
Có giảm nhập sai không?

IMPLEMENTATION
Exment Native / Config / Plugin / Laravel

ACCEPTANCE TEST
Làm sao xác minh cải tiến thành công?
```

Nếu chưa đo được số click/thời gian, ghi `NOT MEASURED` thay vì tự đặt số liệu.

## 24. Đề xuất 3 phương án cải tiến

Tạo 3 mức:

### Option A – Minimal Improvement

- Sửa các UX issue quan trọng.
- Chủ yếu cấu hình Exment.
- Không thay đổi kiến trúc lớn.
- Ít rủi ro nhất.

### Option B – Recommended Improvement

- Cải thiện UX rõ rệt.
- Thay plugin thừa bằng native khi phù hợp.
- Tối ưu Product/Variant Management.
- Đơn giản hóa cấu trúc.
- Phù hợp nhu cầu khách hàng trước release.

### Option C – Advanced Improvement

- Nâng cao trải nghiệm Admin.
- Tối ưu nhiều quy trình.
- Các cải tiến cho quy mô lớn hơn.
- Có thể thực hiện sau Phase 6.

So sánh:

```text
Benefit
Effort
Risk
Maintainability
Upgrade Compatibility
Release Impact
```

Sau đó chọn phương án khuyến nghị và giải thích lý do.

---

# PHẦN E – KIỂM THỬ VÀ BẰNG CHỨNG

## 25. Admin UI Test

Test trực tiếp nếu môi trường cho phép:

```text
Login
→ Create Category
→ Create Product
→ Add Images
→ Add Classification
→ Generate Variants
→ Set Price
→ Set Stock
→ Publish
```

Và:

```text
Order List
→ Order Detail
→ Update Status
→ Verify History
```

Đánh giá kết quả về cả chức năng lẫn UX.

Chụp screenshot nếu có khả năng.

Không ghi PASS nếu chưa thực sự test.

## 26. Evidence Requirements

Mỗi issue/recommendation quan trọng cần có:

- Source location hoặc UI route.
- Current behavior.
- Evidence.
- Business impact.
- Native alternative evidence nếu có.
- Proposed solution.
- Acceptance criteria.

Phân biệt rõ:

```text
VERIFIED ISSUE
VERIFIED IMPROVEMENT OPPORTUNITY
PROPOSED IMPROVEMENT
NEEDS VALIDATION
```

Không coi đề xuất chủ quan là lỗi đã xác minh.

---

# PHẦN F – DELIVERABLES

## 27. Admin UX Report

Tạo bảng:

| Module | UX Score | Issue | Recommendation | Priority | Evidence |
|---|---|---|---|---|---|

## 28. Exment Native vs Plugin Report

Tạo bảng:

| Function | Implementation | Native Support | Why Custom? | Recommendation | Evidence |
|---|---|---|---|---|---|

## 29. Improvement Backlog – bắt buộc

Tạo bảng đầy đủ:

| ID | Module | Current Problem | Proposed Improvement | Solution Type | Priority | Effort | Benefit | Risk | Acceptance Criteria |
|---|---|---|---|---|---|---|---|---|---|

Solution Type:

```text
EXMENT NATIVE
EXMENT CONFIGURATION
SIMPLIFY PLUGIN
KEEP PLUGIN
CUSTOM LARAVEL
UX ONLY
```

Priority:

```text
P0 – Release Blocker
P1 – High Value
P2 – Improvement
P3 – Optional
```

Phải đưa ra đề xuất cụ thể, có thể giao cho developer triển khai.

## 30. Improvement Roadmap

Chia đề xuất thành:

### Before Phase 6

Những cải tiến bắt buộc để khách hàng sử dụng được Admin an toàn và thuận tiện.

### After Phase 6 – Quick Wins

Những thay đổi nhỏ có thể làm sau release.

### Future Enhancement

Những cải tiến nâng cao cần thêm thời gian hoặc xác nhận nghiệp vụ.

Không tự biến mọi đề xuất thành release blocker.

## 31. Báo cáo cuối

Tạo tài liệu:

```text
docs/reviews/
├── admin-ux-review.md
├── exment-native-vs-plugin.md
├── plugin-inventory.md
├── improvement-backlog.md
├── improvement-options.md
└── admin-improvement-roadmap.md
```

Có thể điều chỉnh đường dẫn theo cấu trúc repository.

Báo cáo cuối phải trình bày:

1. Executive Summary
2. Current Admin UX Assessment
3. Product/Variant UX Assessment
4. Order/Inventory UX Assessment
5. Current Plugin Inventory
6. Exment Native Capabilities
7. Unnecessary Customizations
8. Native Replacement Opportunities
9. Architecture Simplification
10. UX Improvement Opportunities
11. Business Process Improvements
12. Security/Performance Improvements
13. Quick Wins
14. Improvement Backlog
15. Option A/B/C Comparison
16. Recommended Option
17. Before Phase 6 Actions
18. Post-Release Improvement Roadmap
19. Risks & Evidence
20. Final Admin Readiness

## 32. Kết luận bắt buộc

Trả lời:

- Admin đã dễ sử dụng chưa?
- Nhân viên không có kiến thức lập trình có quản lý được không?
- Form quản lý sản phẩm/variant đã thuận tiện chưa?
- Có plugin nào đang dư thừa?
- Vì sao chưa tận dụng Exment Native?
- Có thể đơn giản hóa code ở đâu?
- Top 10 cải tiến đáng làm nhất là gì?
- Cải tiến nào nên làm ngay trước Phase 6?
- Cải tiến nào có thể làm sau release?
- Nên chọn Option A, B hay C?

Kết luận trạng thái:

```text
ADMIN UX STATUS:
PASS / NEEDS IMPROVEMENT / NOT READY

EXMENT NATIVE UTILIZATION:
GOOD / PARTIAL / POOR

PHASE 6 ADMIN STATUS:
READY / READY WITH ACCEPTED LOW RISKS / NOT READY
```

Mọi trạng thái phải dựa trên bằng chứng thực tế.

---

# 33. Quy tắc cuối cùng

**CHỈ REVIEW, TEST, PHÂN TÍCH VÀ ĐỀ XUẤT. KHÔNG SỬA CODE.**

Không được:

- Refactor
- Xóa hoặc thay thế plugin
- Thay đổi database
- Sửa Exment Core
- Thay đổi production
- Deploy
- Bắt đầu Phase 6
- Tự triển khai Improvement Backlog

Ưu tiên theo thứ tự:

```text
Đáp ứng đúng nghiệp vụ
    ↓
Dễ sử dụng
    ↓
Exment Native
    ↓
Ít custom code
    ↓
Dễ bảo trì và nâng cấp
    ↓
Hiệu năng và khả năng mở rộng
```

**Mục tiêu cuối cùng: xây dựng Admin cho bancay.vn dễ sử dụng như một hệ thống quản lý cửa hàng chuyên nghiệp, tận dụng tối đa Exment và chỉ dùng plugin/custom code khi có lý do kỹ thuật hoặc nghiệp vụ thực sự.**