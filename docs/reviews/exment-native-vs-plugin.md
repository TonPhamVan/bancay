# Exment Native vs Plugin — đúng stack đang cài

Đối chiếu installed Exment**7.0.0**/admin-core**1.0.1**, composer.lock reference/hash và source hiện tại. [Manifest20 files](evidence/native-source.json), [registry runtime](evidence/runtime.json). Không dùng tài liệu v6 làm chứng cứ hành vi v7; các link v6 trong phase reports là lịch sử. Không re-download/patch vendor; upstream byte-for-byte parity không kiểm lại trong task này.

## Capability / implementation matrix

Source native bên dưới tương đối với `vendor/exceedone/exment/src/`.

| Function | Native support / source | Current implementation | Why custom? / assessment |
|---|---|---|---|
| Custom Table/Column | Có: Model/CustomTable, CustomColumn; CustomTableController |13 platform CustomTables;17 domain tables là Eloquent relational | getValueModel():3182 trả `?CustomValue`, không phải adapter sẵn cho Product; KEEP native platform, không mirror domain |
| Form/layout/default/required | Có: CustomFormController::form/createForm; CustomFormBlock/Column |store form.blade +StoreResources fields/RecordEditor rules |Native metadata áp dụng CustomTable; admin-core Form nhận Eloquent, candidate reuse nhưng save/tree/upload qua service cần PoC |
| Grid/List/View | Có: CustomViewController::grid/form; CustomViewFilter/Sort; CustomValueController::index |custom list25rows/filters/export |NATIVE PARTIALLY SUPPORTED; basic UI duplication có thật, chưa có parity proof cho derived fields/Gates/actions |
| Relationships/lookup | Có: CustomRelation parent/child CustomTable IDs; getModelName/getValueModel |Eloquent FKs/composite owners/CategoryTreeService |Native lookup có chức năng cùng loại; không chứng minh unique combination/stock/order snapshot tương đương |
| Search/filter/sort | Có: CustomViewFilter/Sort, CustomViewGridFilter; native grid services |StoreQueries allowlist/bound queries, no saved domain views |Saved filters native có nhưng không tự áp lên plugin HTML list; UX enhancement cần adapter/custom URL presets |
| Validation | Có: CustomValueRule và custom column/form validation |FormRequest +Action/RecordEditor validation +DB checks |Business rule defense giữ; UI messages/required hints nên thống nhất, không xóa Action validation |
| Role/Permission | Có: RoleGroupController::form/formUserOrganization, native LoginUser |Dùng native editor/membership; StoreAccess bridge vào5 Gates |KEEP NATIVE. Plugin Access=manage module; read/write/export và org inheritance cần thiết kế bổ sung |
| Workflow | Có: WorkflowController::form/grid, WorkflowValue/Action/Status gắn CustomValue |OrderStatus enum +ChangeOrderStatus +append-only histories |CUSTOM BUSINESS RULE REQUIRED; direct native workflow chưa bảo đảm reserve/release/ship locks/snapshot/legacy immutability |
| Dashboard | Có: DashboardController::home/form, DashboardBox |Native landing +BancayDashboard custom KPI DTO |INTEGRATION REQUIREMENT; supported PluginDashboardBase có thể render DTO vào native Box, NEEDS VERIFICATION, không fake metric CustomTable |
| Import/Export | Có: Services/DataImportExport/Providers/{Import,Export}/DefaultTableProvider; import dùng getModelName(CustomTable) |whitelisted/chunked custom CSV; guarded legacy importer riêng |Generic domain import chưa an toàn cho invariants; giữ. Read-only CSV có thể reuse writer, không bỏ sanitize formula/PII ACL |
| File attachment | Có: FileController, FileColumnProvider, custom column files |ImageStorage GD decode/reencode +ProductImages ownership/cleanup |Native file capability không chứng minh cùng JPEG/PNG/WebP/pixel/public/shared-reference contract; giữ pipeline |
| Notification/Event | Có: CustomNotifyController, Notify models/CustomValue events |Native platform mail/reset; chưa order notification engine |Phase7 requirement nếu có. Không bật native business notification chỉ vì đã có menu/model; SMTP ngoài scope review |
| API | Có: ApiDataController dataList/Create/Update/Delete gọi CustomTable/value model |`config/exment.php api=false/outside_api=false`; public custom commerce routes không phải generic native API |Không bật API để đơn giản CRUD; giữ surface hiện tại |
| Copy/duplicate | Có: CustomCopyController constructor cần CustomTable; CustomCopyColumn |Chưa duplicate Product UI |NATIVE PARTIALLY SUPPORTED. Safe clone Product/options/images/SKUs cần regenerate identity/draft/zero reserved, không copy orders/stock bừa |

Presence được xác minh bằng source classes/methods, không đồng nghĩa capability đó đã test trọn UI/configuration. Fresh UI có native login/home/role editor; các Form Designer/View/Workflow Designer/import/API tests riêng **NOT TESTED** trong review, không cấu hình native bảng thử để đổi schema.

## Điểm khác nhau quyết định

1. **Exment Native Configuration** là form/view/relations trên CustomTable/CustomValue. Project không có business CustomTable trong registry; tạo mirror cho convenience sẽ phá one-source-of-truth.
2. **Exment admin-core widgets** là Form/Grid có thể nhận Eloquent: `vendor/exceedone/exment-admin-core/src/Grid.php:210` constructor `Eloquent $model`; Form.php:202 nhận model/callback. Đây là cơ hội dùng supported extension; chưa phải drop-in config hay replacement đã chứng minh.
3. Một widget có khả năng render field không đồng nghĩa nó giữ reservation transaction, state history hay safe upload. Tất cả write hooks phải gọi Services/Actions và map errors đúng HTTP; stock/orders không default generic ORM-save.

## Replacement assessment

| Candidate | Function/data equivalence | UX | Conversion / regression / rollback | Decision |
|---|---|---|---|---|
| Identity/roles |Native hiện đang là nguồn chuẩn, không có mirror |Native3 concepts cần hướng dẫn |Không chuyển; kiểm role/direct URL/session revocation hiện có |KEEP NATIVE |
| Category/ArticleCategory form/grid |Eloquent widgets có khả năng; CustomTable metadata không giữ schema như hiện tại |Có thể giảm inconsistency/filter boilerplate |M effort/Medium risk; PoC trên clone, tree/slug/publication/upload/403/422 tests; revert view/route, giữ DB |SIMPLIFY CUSTOM / NEEDS VERIFICATION |
| Customer/articles basic list/form |Fields tương tự CRUD, derived order count/HTML/PII khác |Widgets/editor có lợi |M; safe upload/sanitize/export/access parity; rollback adapter code, không migrate dữ liệu |SIMPLIFY CUSTOM |
| Variant matrix/Inventory |Generic editable grid chưa có equivalence proof cho batch/stale/ownership |Matrix hiện đáp ứng nhu cầu mới |L nếu thay engine; test race/rollback/price/stock/snapshots toàn bộ; rollback UI về Action cũ |KEEP PLUGIN – JUSTIFIED |
| Orders/workflow |Native workflow có state/history nhưng chưa live-stock transaction equivalence |Có thể thêm status labels/queue vào UI hiện tại |L/High risk nếu replatform; all edges/locks/replays/legacy guards/immutable snapshot; không làm trước release |KEEP PLUGIN – JUSTIFIED |
| Dashboard |Read-only DTO có thể giữ dữ liệu, native Box renderer có seam |Một home thống nhất tốt hơn |S/M exploratory; testper-rolemetrics/canonicalvisible scope; rollback default dashboard/menu, giữ plugin |NEEDS VERIFICATION |
| Shop Settings |Native System Settings và shop settings khác semantics/permission |Simple form hiện đủ9keys |No justification để replatform; testlogo/links/whitelist |KEEP PLUGIN – JUSTIFIED |

**Không candidate nào đạt REPLACE WITH NATIVE ngay bằng bằng chứng hiện có.** Không có lý do phải patch Exment core hoặc đưa native Workflow vào Order để tăng “native utilization”. Đề xuất giảm custom UI có kiểm chứng, giữ business Actions và một relational source.
