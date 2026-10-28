> Current stack: **Exment7.0.0 / Laravel12.70.2 / admin-core1.0.1**, PHP8.3.36. [Upgrade report](exment-v7-report.md). Composer audit PASS (0 advisories/0 abandoned), npm audit0; RR-30/RR-34 RESOLVED.132 PHP/814 assertions +5JS and47 browser cases PASS. Both local DBs upgraded;17 business tables unchanged; migrated catalog still hidden. Older v6 pins/audit failures/exceptions below are historical. No production release.

> Latest approved-review application: [report](phase-4/review-application.md). All category decisions applied:115 products/120 SKUs/67 articles, still hidden.5 missing media references resolved,1 fallback accepted;493 historical orders/2 banners/2 legacy users intentionally excluded.201/201 URL targets mapped.132 PHP/815 assertions +5JS and44 browser cases PASS. Dependency audit remains FAIL under local-only exception; Phase5 READY WITH RISKS. Earlier counts/queues below are historical.

> Latest follow-up: [risk closure](phase-4/risk-closure.md).124 fields now use portable relative media URLs;128 PHP/783 assertions +5JS and37 browser cases PASS. Remaining business/source exceptions remain tracked.

# Phase 4 — Legacy Data Migration

Ngày28/10/2026 (Asia/Saigon). Baseline360f389 + preserved readiness fixes. [Original request](phase-4/request.md), [latest authorization](phase-4/authorization.md). No production cutover. Changes chưa commit/push.

## 1. Executive Summary

Đã hoàn tất Phase4 rehearsal trên Docker local, nguồn backup chỉ đọc và target bancay_phase4_staging riêng.113 products/118 SKU,8 categories,11 article categories,60 articles,255 historical orders và21 banners được nhập;342 product-image rows,745 media files kiểm được. Không cutover/production/DNS. RR-30/RR-34 được **người dùng chấp nhận tạm hoãn**, không được coi đã vá dependency. [Authorization](phase-4/authorization.md).

## 2. Migration Architecture

app/Legacy tách Source, TargetGuard, transformers, Media, Migrator, Runner, Reconciler và private reports. Transaction per entity; catalog dùng CreateProduct/CreateVariant; history writer không dùng live CreateOrder. Không JSON mirror, không sửa migrations cũ hoặc domain schema. [Proposal](phase-4/architecture-proposal.md).

## 3. Source / Target Environment

Original ZIP/SQL/extraction private bất biến. Source copy bancay_phase4_source có SELECT-only account và read-only sessions; target bancay_phase4_staging có account/storage/session/plugin riêng, effective DB host phải là Docker db. Local app http://localhost:8002, noindex, debugOFF, mail=log, không public API. Actual staging giữ DRAFT/INACTIVE; QA publication chỉ trên clone đã xóa.

## 4. Legacy Tables Processed

Đã kiểm12 tables/980 rows. Catalog/content/settings/banner-slider và tblorder được xử lý; tblkhachhang0 rows. Hai tbladmin vào manual review; tblrole4 rows dùng audit/provenance, không copy integer permissions; tbl_count1 row được giữ trong archive, không nhập counter thiếu use case. [Source counts/backup](phase-4/evidence/backup-verification.json).

## 5. Migration Order

Settings → categories → products/images/options/values/variants/inventories → article categories → articles → customers → optional history orders/items/status entry → banners/slider → user review. Parent graph topologically ordered; full records streamed/chunked100. Chỉ giữ metadata IDs/parents/SKU để lập dependency và chống collision.

## 6. Category Migration

8/8 categories,11/11 article categories; parent/root/slug/status/sort/metadata/media giữ mapping. Hai category descriptions dài vượt cap admin cũ nhưng vẫn vừa TEXT: tăng validation kèm65,535-byte guard, kiểm cả sau Purifier. Native edit regression PASS; không truncate và không DDL change.

## 7. Product Migration

115 read →113 created +2 quarantine: product7/10 tham chiếu category32 không tồn tại.15 empty galleries được coi absent optional media, có warning và fallback; không nhầm thành malformed variants. Tên/SEO/featured/recorded timestamp được kiểm; mọi product DRAFT, published_atNULL, original status/provenance giữ private.

## 8. Variant Transformation

3 source JSON products/8 variant rows được normalize; tổng118 SKUs gồm110 simple+8 variants,4 option groups/8 option values/12 variant-value pivots. Global duplicate/empty/invalid SKU dùng deterministic BC-L{id}-S hoặc-V{index+1}, giữ legacy_sku và index, cảnh báo rõ. Không sửa SKU nguồn; target collision bị quarantine.

## 9. Inventory Migration

118 inventory rows, đúng quantity từ simple sl hoặc variant sl_pl; không cộng hai nguồn. Reserved=0 sau history import; không tự kiểm kê hoặc trừ tồn. Reconciler đối chiếu mọi SKU/price/stock. Live QA orders reserve đúng delta và giữ physical quantity; actual staging vẫn reserved0.

## 10. Article Migration

67 read →60 created +7 quarantine do cid0 không có category hợp lệ.11 article categories giữ hierarchy. Recorded dates đúng UNIX source, không suy diễn first publication. HTML được sanitize và local media rewrite có rule; unresolved/external resources có CONTENT_REWRITE_REQUIRED, raw originals còn trong backup.

## 11. Customer Migration

tblkhachhang0 rows, không tạo customer hoặc merge identities từ tên/phone/email trong orders. Contact/address giữ độc lập trong accepted historical snapshots; không tạo account khách hàng.

## 12. User Migration

Hai legacy admin accounts vào ROLE_MAPPING_REQUIRED; không copy MD5, không auto cấp Super Admin hoặc đoán role. Local-admin của staging là identity bootstrap mới, password private tại legacy-private/phase4/staging.env, không phải migrated legacy user. Approved provisioning/reset cần xử lý trước cutover.

## 13. Order Migration

748 read →255 historical orders +493 quarantine. Mỗi accepted row thành1 order/1 item/1 provenance history entry; LEG-{legacy_id}, is_legacy=true, status0→NEW/1→CONFIRMED. Giá/tên/quantity/variant text/contact/date giữ snapshot gốc; SKU/current variant linkNULL nếu không biết lịch sử. Current product link đúng legacy mapping hoặcNULL khi product quarantined. Không email/stock/payment/shipping effects; native UI readonly.

## 14. Banner / Setting Migration

23 banners/slider →21 accepted +2 quarantine (missing category28 và unknown a2 position). tblslider→home_slider,a0/a1→sidebar,a4→content_bottom; INACTIVE, giữ category scope. Footer1 row tạo8 whitelisted settings; executable analytics/video/content không import, invalid map/social values review. Branding/contact/logo nguồn cần owner approval trước public release.

## 15. File / Image Migration

Originals SHA/CRC và3,644 extracted files đã reverify. MIME/decode/pixel/size/containment checks; JPEG/PNG/WebP re-encode, GIF validated/preserved, hash-based safe names, no overwrite of different content.342 product-image rows;745 referenced public media paths checked.6 missing input references không được gọi là đã phục hồi. Không xóa2,113 unreferenced original files. File map/hashes private.

## 16. Pre-Migration Validation

Guard source/target/database host/account/backup proof/scopes/code/receipt; schema and parent graph checked, exact integer/grouping/overflow validation. Structural/infrastructure errors stop; invalid records quarantine with entity+ID+code, không log full customer data. Không workaround bằng nới constraints.

## 17. Dry-Run Result

PASS: domain fingerprint và public-storage fingerprint không đổi, source fingerprint không đổi. Dry-run chỉ ghi private report/receipt. Receipt bind source, target, options, relevant code, storage and backup hash; --force không bypass được receipt hoặc guard. [Dry-run](phase-4/evidence/dry-run.json).

## 18. Migration Result

COMPLETED WITH QUARANTINE, không entity FAILED. Root counts khớp dry-run. Existing records được SKIP, không overwrite manual edits. Native imported catalog/content vẫn hidden; staging tồn tại để review. [Counts](phase-4/evidence/migration.json), [quarantine aggregate](phase-4/evidence/migration-quarantine.json).

## 19. Reconciliation

PASS:8 categories/113 products/342 images/4 options/8 values/118 variants/12 pivots/118 inventory/255 orders/items/histories/11 article categories/60 articles/21 banners/8 settings. Rerun CREATED0, fingerprints giữ nguyên, không duplicate. Fresh second target cho semantic hashes bằng nhau; restore rollback trên disposable target PASS và đã xóa. [Reconciliation](phase-4/evidence/rerun-reconciliation.json), [fresh repeat/rollback](phase-4/evidence/fresh-repeat-rollback.json).

## 20. Sample Verification

Deterministic sample20 products/20 variants/10 orders/10 articles trong reconciliation; đồng thời full imported rows được so name/price/options/quantity/stock/snapshot/recorded dates và media existence. Report chỉ xuất sample IDs/hashes; contact data không vào Git evidence.

## 21. Exment Verification

Chrome/Edge desktop: native login, product/images/options/variants, product edit, >10k category description, historical snapshot read-only và inventory/customer/article/banner grids PASS. Historical status form absent; no live workflow. Browser screenshots chứa legacy data giữ private, không commit.

## 22. Frontend Verification

Migrated product4 với2 option groups được kiểm Home→Category→Product→Variant→Cart→Checkout→Success trên actual Chrome/Edge desktop và Chrome mobile viewport. Gallery/article/validation/OOS negative fixture PASS.Original rehearsal13+13+8=34 cases PASS; latest risk-closure rerun14+14+9=37 cases PASS, including portable inline media. Chỉ clone có publication và synthetic orders; actual staging không publish hoặc tạo live order. [Browser](phase-4/evidence/browser-acceptance.json), [DB verification](phase-4/evidence/browser-data-verification.json). Safari/iOS NOT TESTED, mobile là emulation.

## 23. Automated Tests

14 migration/admin cases hiện có trên baseline114 (12 ở rehearsal, thêm2 URL-repair tests): dry-run no writes, receipt/stale source/target/backup/exception guards, normalized SKU/options/history, invalid JSON/reference/quantity quarantine, entity/file rollback, rerun preserving edits, range/parent semantics, media/XSS/overflow, category UTF8-byte bounds. Dedicated bancay_test only.

## 24. Regression Results

**128 PHP tests/783 assertions PASS** (latest risk closure; original rehearsal126/770),0 failure/error/skip;5 JS tests PASS. Pint, Composer strict validate/platform, Exment requirements PASS; npm audit0. Composer audit FAIL giữ nguyên, không ignore/vendor patch. [Latest tests](phase-4/evidence/risk-closure-tests.json), [latest checks](phase-4/evidence/risk-closure-checks.json).

## 25. Migration Errors

Record-level quarantine:2 products orphan;7 articles cid0;493 orders (344 missing/zero price,103 invalid/nonpositive quantity after prior price screening,45 missing required text/contact,1 invalid email);2 banners;2 admins. Overlapping bad source conditions được ghi theo first blocking code, không cộng các source defect counts chồng nhau. No silent repair/delete/reprice. Structural blockers hiện không còn trong isolated rehearsal.

## 26. Manual Review Items

Resolve category32 for products7/10; article category0 rows; banner a2/category28; recover/approve missing6 media; inspect HTML/external resources/legacy anchors; approve branding/contact/publication dates; review493 historical rows and2 admin identities/permissions. Originals private archive retained. Quarantine does not mean source rows were cleaned or lost.

## 27. Risks

RR-30/RR-34 temporarily ACCEPTED by latest direct user instruction for isolated local rehearsal only; still unpatched audit findings, must resolve before public staging/production. Content asset origin risk was subsequently resolved with relative URLs and guarded124-field repair; see risk-closure.md. Missing media/HTML/publication/branding still require review. No large-load/penetration/AV/full browser-device certification. Hosting backup remains user-deferred until before Phase6. Current workflow SKIP-only: changed source/manual edits require review/new dry-run, no blind incremental overwrite.

## 28. SEO URL Mapping Prepared

201 source URLs covered:192 actual mapped targets +9 quarantined/manual review,0 duplicate source URLs; exact old and encoded paths preserved. Dataset private at legacy-private/phase4/phase5/legacy-url-map.csv, hash/aggregate public. All real staging targets hidden, no production redirect activated; query/encoding/publication parity must be verified in Phase5. [Metadata](phase-4/evidence/seo-url-map.json).

## 29. Phase 5 Readiness

Migration structure, dry-run, real staging write, rerun, clean-target repeat/rollback, reconciliation, admin/frontend and regression PASS. Manual queue is known and does not weaken schema; plan before cutover explicit above. Dependency exception is user-accepted locally, not release approval. Do not start Phase5 automatically or deploy.

PHASE 5 STATUS: READY WITH RISKS
