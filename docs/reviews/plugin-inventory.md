# Inventory — native, configuration, plugin và libraries

Baseline93c581c; [runtime](evidence/runtime.json) và [native hashes](evidence/native-source.json). Exment7.0.0, admin-core1.0.1, Laravel12.70.2, PHP8.3.36. Plugin wrappers dùng `PluginPageBase → StorePage → StoreController → Services/Actions`;6 runtime Plugin.php khớp source và6 plugin active1.0.0. Không refactor/copy/reset runtime trong review.

## Exment plugins

| Component / source | Chức năng & actual use | Vì sao custom / native alternative | Maintenance / upgrade risk | Kết luận; bỏ đi ảnh hưởng gì |
|---|---|---|---|---|
| BancayCatalog, exment-plugins/BancayCatalog | Categories/product/images/options/SKU/stock; UI test fresh | CUSTOM BUSINESS RULE REQUIRED + UX LIMITATION; CustomTable/Form có generic CRUD, không chứng minh atomic normalized combinations/stock | Medium: metadata routes, guard/admin-core integration cần regression | KEEP PLUGIN – JUSTIFIED; bỏ mất catalog/stock admin và catalog.manage grant bridge, frontend data còn nhưng mất đường quản lý |
| BancayOrders, exment-plugins/BancayOrders | list/snapshot/history/status; NEW→CONFIRMED→CANCELLED fresh | CUSTOM BUSINESS RULE REQUIRED; native Workflow có state/history cho CustomValue, không mặc định gọi ChangeOrderStatus/InventoryService | Medium: transaction/history/auth integration | KEEP PLUGIN – JUSTIFIED; generic replacement có thể bỏ side effects/immutable snapshots |
| BancayCustomers, exment-plugins/BancayCustomers | business profile/address/count/export; form fresh, writes qua tests | INTEGRATION REQUIREMENT; native User là platform identity, không nên thay customer bằng admin user; native widgets potential | Low–Medium; PII grants/export | SIMPLIFY CUSTOM; chưa đủ parity để replace; bỏ làm mất profile/address management |
| BancayContent, exment-plugins/BancayContent | articles/categories/banners; forms fresh, writes feature tests | NATIVE PARTIALLY SUPPORTED; native CRUD/file/HTML hỗ trợ trong CustomTable; current relational SEO/publication/owned image pipeline | Medium; editor/view/source mappings | SIMPLIFY CUSTOM; pilot widgets, không duplicate content JSON; bỏ mất content admin, không xóa content rows |
| BancaySettings, exment-plugins/BancaySettings |9 public keys/logo; settings UI read | INTEGRATION REQUIREMENT; native system settings không đồng nghĩa public shop settings; whitelist và safe logo validation phải giữ | Low; keys/URL/image contracts | KEEP PLUGIN – JUSTIFIED; không cấp Manage Setting rộng để thay quyền settings.manage |
| BancayDashboard, exment-plugins/BancayDashboard | DTO KPIs/recent/low stock; fresh read | NATIVE PARTIALLY SUPPORTED; native Dashboard/Box + supported dashboard plugin có thể render DTO, cần permission proof | Low–Medium; mismatch semantics hiện AR15 | NEEDS VERIFICATION cho native dashboard-box adapter; giữ trang hiện có tới khi parity; bỏ mất KPIs, không ảnh hưởng core checkout |

Tất cả phụ thuộc shared `app/Admin/StorePage.php`, StoreController, StoreResources, StoreAccess, native auth/middleware/layout. Không có6 engine độc lập. Config10 routes lặp ở cả Dashboard/Settings; service/guard hiện từ chối entity/verb không hỗ trợ. Simplify route declaration là debt, không chứng minh route mở trái phép.

Gộp6 plugin thành1 cần migrate native plugin permission target IDs, menus/bookmarks và Gate bridge; rollback phải restore config/permission mappings. Không “xóa wrapper” bằng cách cho mọi role Access một plugin tổng.

## Phân loại khác

| Loại | Thành phần / location | Vai trò / usage | Native alternative & decision |
|---|---|---|---|
| EXMENT NATIVE | vendor/exceedone/exment; native User/LoginUser/RoleGroup | Login/session/role editor/user admin, platform metadata, audit/shell | KEEP NATIVE; không auth hoặc users/roles mirror |
| EXMENT CONFIGURATION | config/exment.php; config/admin.php; config/website.php; platform plugins/admin_menu/roles | locale en, API off, menu/grants, public settings keys/banner positions | Giữ small overrides; config thực tế đọc, không mutate |
| SUPPORTED EXTENSION | PluginPageBase; StoreAdminGuard/StoreAccess | native page seam + application Gate/role bridge direct membership | Giữ; org inheritance chưa hỗ trợ, không suy ra native permission configuration tự bridge domain |
| LARAVEL CUSTOM CODE | app/Actions, Models, Services/Admin, InventoryService, CategoryTreeService, admin Blade/JS | relational CRUD, commerce invariants, uploads, matrix | business layer có giá trị; view primitives có thể đơn giản hóa từng phần |
| THIRD-PARTY COMPOSER | exceedone/exment-admin-core1.0.1 | Eloquent Form/Grid + native admin widgets; currently shell/identity, store forms largely custom | Supported widget reuse candidate; không phải một Exment page plugin |
| THIRD-PARTY COMPOSER | mews/purifier3.4.4 + ezyang/htmlpurifier4.19.1 | server HTML sanitize, cần cho product/article | Giữ sanitize dù đổi editor/native UI |
| THIRD-PARTY COMPOSER | exment-git/spout4.0.0 | Exment spreadsheet import/export transitive capability | Không gọi là custom business plugin; không chứng minh generic import an toàn cho domain |
| FRONTEND LIBRARY | Alpine3.17.5, Tailwind4.3.3, Vite8.3.5 | public storefront interaction/assets | Không inject public Tailwind vào Exment; matrix dùng standalone scoped JS |
| DEV TOOLING | PHPUnit/Pint/Node tests, scripts QA | kiểm regression/artifact, không runtime plugin | Không tính vào “số plugin phải giảm” |

Composer/npm audit fresh0; không upgrade package. Inventory này không bao gồm module Phase7 chưa implement. Customer Account/voucher/review/payment/shipping/notification ngoài scope không phải “plugin missing”.
