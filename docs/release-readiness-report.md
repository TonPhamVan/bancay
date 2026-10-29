# Production Release Readiness — Blocker Verification & Remediation

Ngày29/10/2026,Asia/Saigon. Không Production Cutover/DNS/docroot switch/CI3 disable/final production migration. User xác nhận target DirectAdmin nhưng chưa có hồ sơ. [Blocker register](release-readiness/blockers.csv), [56-risk register](release-readiness/evidence/risk-register.json), [verification](release-readiness/evidence/verification.json).

## 1. Executive Summary

Đã xác minh ba blocker gốc bằng source/config/DB/EVD mới và xử lý phần có thể thực thi trong môi trường cô lập. Phát hiện và sửa ba thiếu sót thật:cron native Exment bị bỏ sót,production log threshold lọc mất inventory audit,và workflow views làm cross-database restore lỗi1054 sau scheduler. Rehearsal restore/rollback-data delta PASS;original main/stage/demo/legacy/backups được giữ nguyên.

Fresh regression158 PHP tests/1054 assertions,5JS,47 branded browser cases,24 responsive cases,13-step admin/public/admin E2E,5 actual security HTTP cases PASS. Composer/npm0. Chưa thể xác nhận production READY:current hosting backup/routing,target profile và final-source receipt vẫn chưa có. Không chuyển thiếu bằng chứng thành accepted risk.

## 2. Original Phase 6 Blockers

| ID | Area | Severity | Nội dung / release impact | Evidence | Current status |
|---|---|---|---|---|---|
| P5-B01 / RR39 |Backup/Rollback|High|Thiếu coherent production/DirectAdmin backup và chứng minh CI3/routing recovery thật|backup-matrix.json;window-restore.json|PARTIALLY RESOLVED → BLOCKER |
| P5-B02 |Deployment/Configuration/Security|High|DirectAdmin đã xác nhận,nhưng PHP/web stack/docroot/SSL/proxy/storage/cron/log profile chưa có|production-config-preflight.json;directadmin.md|PARTIALLY RESOLVED → BLOCKER |
| P5-B03 |Migration/Rollback|High|Supplied snapshot không chứng nhận final source/freeze;SKIP-only importer không cập nhật existing rows|rollback-rehearsal.json;offline-delta-export.json;reconciliation.json|PARTIALLY RESOLVED → BLOCKER |

## 3. Blocker Verification

[21 input hashes](release-readiness/evidence/inputs.json) bao gồm MasterPlan,Phase0–5,consolidated review,current risks/security/SEO/tests/browser/reconciliation/runbook/rollback/backup/readiness. Source inspected includes scheduler/providers,log levels,typed catalog/order/inventory actions,legacy guards,DDL view generation and source/current datasets. Baseline da70bd4;Phase5 changes already uncommitted at task start,not discarded. [Baseline](release-readiness/evidence/baseline.json) records dirty paths and user target answer. Stack unchanged Exment7.0.0/Laravel12.70.2/admin-core1.0.1/PHP8.3.36/MariaDB10.11.19.

No database/order/security/checkout blocker reproduced in existing implementation. Old RR30/RR34 ALREADY RESOLVED,audit0 current. Safari/iOS NOT TESTED remains a device limitation,not a fabricated PASS. Email/order-notification absence is approved feature scope,not automatically a release defect;actual SMTP delivery not certified. Remaining risk statuses normalized to RESOLVED/FALSE POSITIVE/ACCEPTED LOW RISK/DEFERRED NON-BLOCKING/BLOCKER.

## 4. Fixes Implemented

| ID | Verification | Action | Test / final status |
|---|---|---|---|
| P6-B04 native scheduler omitted |NEEDS FIX|DirectAdmin cron wrapper,explicit discovery of native hourly/minutely tasks and deployment checklist|Both native commands execute successfully on clone;wrapper argv/missing-path guard PASS;RESOLVED for prepared procedure,actual installation under P5-B02 |
| P6-B05 production warning suppresses audit |NEEDS FIX|.env.production.example LOG_LEVEL=info;runtime preflight|Controlled info/error logs recorded;500 body hides details;RESOLVED |
| P6-B06 restore of native views fails after scheduler |NEEDS FIX|Portable base-table dump+archive original views;guarded store:rebuild-derived-views via official Exment APIs|98 tables exact,2 views queryable under new DB name,ledger reproduced;3 guard/view tests PASS;RESOLVED |

New recovery OrderDelta service/offline exporter preserves changed/new order snapshots,history and inventory deltas,with stable dedup references. Input SHA/lineage,private output containment,exclusive no-overwrite and existing-snapshot/history/removal guards tested. No automatic CI3 or stock writes. Source code changes are CLI/recovery tools,tests,configuration template and docs;no schema migration,vendor patch,architecture rewrite or public feature added.

## 5. Database / Migration Fixes

[Main audit](release-readiness/evidence/main.json), [stage audit](release-readiness/evidence/stage.json):37 checks each PASS. Unique/FK/composite ownership,orphan/tree/status/price/stock/order/item/snapshot/totals/history clean. Historical unknown SKU255 andNULL product links2 remain exactly approved source semantics,not live integrity failures.

[Fresh reconciliation](release-readiness/evidence/reconciliation.json) PASS. Stage:8 categories/115 products/120 SKUs+inventories/347 gallery rows/11 article categories/67 articles/255 historical orders+items+histories/21 banners/8 settings. Main:14 categories/121 products/142 SKUs/72 articles/258 orders/24 banners/8 target settings;120 products/71 articles visible locally.493 invalid historical orders,2 ambiguous banners and2 legacy identities remain intentionally excluded with originals archived. No unexplained loss,new failed import or manual DB repair. No legacy:migrate invoked in this review.

Portable recovery fixes derived SQL views only. Source-qualified view SQL was reproduced failing withERROR1054;raw dumps retained privately. Base tables/data/constraints are dumped without textual SQL rewrite,and two native view definitions regenerated against confirmed target using package APIs. Unknown views fail the tool rather than being silently discarded. Future production command requires maintenance+exact DB confirmation+native/domain schema;local operational main/stage are denied. No old migration is edited.

## 6. Security Fixes

Production log threshold corrected;logs/500 redaction verified. Offline recovery cannot write public files,overwrite a ledger or accept wrong SHA/lineage/changed immutable snapshots. Derived-view command rejects mismatched DB and non-maintenance production. New scripts keep raw SQL/contact/credentials private0600;no secrets in public EVD. Business upload/SQLi/XSS/CSRF/IDOR/session/role/tampering/concurrency regressions remain PASS.

Composer/npm fresh0,0 abandoned packages;no package changed in this review. Known mime CVE7.4.19→7.4.22 patch is ALREADY RESOLVED from Phase5,compatible with current stack and validated by existing MIME/native mail tests. No advisory ignore or major update. [Source/history scan](release-readiness/evidence/source-security.json) is a known-pattern scan,not full penetration certification. Actual DirectAdmin HTTPS/storage protections remain unverified under P5-B02.

## 7. SEO Fixes

No new SEO defect reproduced,so no unrelated rewrite. [Fresh crawl](release-readiness/evidence/seo.json) verifies201 encoded legacy URLs301→correct200,221 sitemap targets,canonical/meta/robots/structured JSON andinternal links/assets.0 broken assets/links.115 products/all8 source categories/67 articles/11 article categories checked. Full source title/slug/meta comparison PASS;missing media policy unchanged,5 recovered aliases+1 approved fallback. Settings logo JSON cast is respected in audit. Actual host HTTPS/www normalization requires target verification,not inferred from localhost.

## 8. Frontend / Browser Fixes

47 current-build Chrome18/Edge18/Chrome mobile viewport11 PASS,core migrated flow and native admin included.24 responsive cases1440/768/390/320 PASS.13-step continuous admin→frontend→admin E2E and5 security HTTP checks PASS. No confirmed UI defect remained,so no UI polish changes. Console/page/server errors empty. Safari/iOS andphysical Android NOT TESTED,no runtime/device;mobile is explicit viewport emulation.

## 9. Order / Inventory Verification

[Rollback rehearsal](release-readiness/evidence/rollback-rehearsal.json) uses validated catalog services/CreateOrder/ChangeOrderStatus in disposable QA only. Physical10,order2→physical10/reserved2/available8. Product price100000→150000 leaves old item100000 andtotal200000. Cancel releases reserved to0;duplicate cancel rejects without second release/history. Second order150000×2=300000 transitions through CONFIRMED/PACKING/SHIPPING,physical8/reserved0. Existing business workflow is preserved.

New-window ledger records exactly2 orders,CANCELLED andSHIPPING,with snapshots/full history/contact privately. Existing baseline orders/history/items remain intact. Stable reference avoids duplicate fulfillment across exports;inventory delta distinguishes physical deduction from reservation release. Automated suite also covers zero/negative/too-large quantities,wrong/inactive product/variant,price/total/stock tampering,double POST,concurrent reservations and HTTP role/IDOR denial.

## 10. Backup Verification

[Backup matrix](release-readiness/evidence/backup-matrix.json):original ZIP587368474bytes andSQL1762723bytes retain known SHA256;all ZIP CRC entries including source/upload subtree pass. SQL restored to owned DB12 tables/980 rows with counts/data checksums matching read-only source. New combined SQL2159445bytes,98 base-table restore/data/schema/checksum/CHECK TABLE PASS;new public-media tar787 regular files extracted and every hash matches. Code/built-assets checkpoint retained privately with source hashes. Original source and media not modified.

[Post-window portable backup](release-readiness/evidence/window-restore.json) has independent timestamp/filename/size/SHA256;98 tables restored exactly,2 derived views rebuilt/queryable under different DB name. Reader account/scratch removed. These are VERIFIED supplied/local artifacts. Full current DirectAdmin production/config/DNS/SSL/cron backup/freshness is NOT VERIFIED;user confirmed no profile. P5-B01 cannot be closed with old/local artifacts alone.

## 11. Rollback Verification

Ten-minute failure procedure:freeze Laravel writes/traffic,backup windowSQL/media/logs,restore windowSQL to isolated recovery DB,regenerate derived views,export delta against verified cutoff,assign each stable recovery reference to manual/CI3 correspondence,account for dispatched stock before reopening legacy writes,then verify old route/CI3. Never restore stale backup over new orders or automatically cancel/restock shipped goods.

[Offline export](release-readiness/evidence/offline-delta-export.json) repeated byte-identically;[restored-window export](release-readiness/evidence/window-restore.json) matches the same ledger checksum. [Guards](release-readiness/evidence/delta-guards.json) reject wrong checksum,public path and overwrite. This proves local data-delta recovery,not actual CI3 runtime/routing recovery on unknown hosting. P5-B01/03 remaining steps and owners are explicit.

## 12. Regression Results

158 PHP/1054 assertions,0 fail/error/skipped;5JS,0 fail/skip. New9 tests cover6 ledger integrity cases and3 derived-view/maintenance guard cases. Full existing Phases1–5 suite run sequentially on bancay_test. Pint,Composer validate/platform,Exment requirement check,Blade compile,npm test/build/audit PASS. Fresh Composer0 advisory/abandoned,npm0. [PHP checks](release-readiness/evidence/checks-php.json), [assets](release-readiness/evidence/checks-assets.json). Core public code has no new business change requiring production execution.

## 13. E2E Results

[Actual browser13-step E2E](release-readiness/evidence/admin-frontend-e2e.json):admin category/product/options/SKU/image/price/stock→public variant/cart/checkout/success→admin snapshot/status/history/reprice/cancel PASS. Browser test creates one synthetic order in QA;no main/stage order writes. Authentication/permissions/customer/content/banner/settings coverage in [manual matrix](release-readiness/manual-matrix.csv) references actual browser andautomated cases rather than asserting every test is manual.

## 14. Migrated Data E2E

Migrated product4 and recovered product8/article images pass current Chrome/Edge/mobile flow. Independent [3-order QA verification](release-readiness/evidence/browser-data-verification.json) verifies snapshot/reservation deltas before additional rehearsal orders. Category/image/options/price/stock/SEO/cart/checkout work on migrated data;not only seeded new product.

## 15. Remaining Risks

P5-B01 current production backup/old routing proof,P5-B02 actual DirectAdmin profile/core hardening/cron/PHP,TLS andpaths,P5-B03 final-source consistency/freeze/changed-row/candidate provenance remain BLOCKER. All are High release-safety gaps,not confirmed exploitable application vulnerabilities. No Critical/High application/order/inventory/data defect remains reproduced after local fixes.

SMTP is not required by existing guest checkout:order/admin notification features are absent,so no fictional delivery PASS or automatic High blocker. Native email-reset delivery NOT TESTED;keep log-only staging and require verified transport or documented operator account-recovery policy before enabling production reset flow. Queue=sync,no worker needed;native cron **is** required and its actual installation belongs to P5-B02. Remaining hardware/CSP/granular-role/editor/CDN/retention debt is deferred nonblocking with owner/target.

## 16. Accepted Risks

| Risk | Reason / impact | Mitigation / target |
|---|---|---|
| RR11 |User explicitly waived1 missing article image;visual fallback only|Keep approved fallback,5 verified aliases andsource originals;no further recovery approval requested |
| RR20 |Native actor SET NULL on deleted identity is accepted design|Immutable history/snapshot and UI indication;retest native auth on upgrades |
| RR40 |Single-instance file sessions/locks accepted boundary|No multi-instance rollout;isolated imports andshared media lifecycle;redesign/test before scale |

Accepted decisions do not waive three production blockers. Source-order/banner/account exclusions remain approved scope with private originals,not unexplained data loss. Do not re-open previously answered category/fallback decisions.

## 17. Release Readiness Matrix

| Area | Status | Evidence | Release Blocking |
|---|---|---|---|
| Architecture |PASS|Typed native domains/CLI scoped tools;no vendor patch|No |
| Database |PASS|37 checks/main+stage;98-table exact restore;view rebuild|No current DB defect |
| Migration |PASS supplied snapshot|Fresh reconciliation/counts/source preservation|P5-B03 final source |
| Exment Admin |PASS|Browser/native permissions/E2E/full suite|No |
| Product/Variant |PASS|Default/1option/2options/inactive/OOS tests|No |
| Inventory |PASS|10/2/cancel/ship/concurrency/snapshot proof|No |
| Order |PASS|100k→150k immutable snapshot/history/ledger|No |
| Cart/Checkout |PASS|Tampering/idempotency/IDOR/browser|No |
| Security |PASS application|Audits0,guards,CSRF/XSS/SQLi/upload/auth|P5-B02 actual host protections |
| SEO |PASS local|201 redirects/221 targets/assets/meta|P5-B02 ingress HTTPS/origin |
| Browser |PASS baseline|Chrome/Edge/mobile viewport47|Safari/iOS not available;explicit scope |
| Responsive |PASS|24 page/viewports|No |
| Performance |PASS local|8 pages incl admin product/order;no slow query|Production SLA NOT TESTED,not invented blocker |
| Email |N/A core checkout|Native array/CRLF tests;log staging|SMTP/reset external NOT TESTED,not automatic core blocker |
| Queue/Cron |PASS prepared|sync;2 native tasks execute;wrapper guards|P5-B02 actual cron installation |
| Backup |FAIL production gate|Supplied/local artifacts VERIFIED|P5-B01 current production/hosting backup |
| Rollback |FAIL production gate|Data-delta restore+ledger PASS|P5-B01 real CI3/routing;P5-B03 final-source receipt |
| Production Runbook |FAIL target gate|DirectAdmin profile/scripts/portable restore prepared|P5-B02 target facts;P5-B03 final input |

Performance medians~9–21ms for8 warm HTTP-kernel samples,7–20 queries;5 samples/page,no query>100ms. Not network/hosting SLA. [Measurements](release-readiness/evidence/performance.json).

## 18. Final Phase 6 Readiness

| Blocker ID | Lý do còn lại | Cần sửa/cung cấp gì | Cần test để đóng |
|---|---|---|---|
| P5-B01 |Chưa có full current DirectAdmin production backup/routing evidence|Hosting Operator tạo coherent SQL/files/config/cron/DNS/SSL artifact với timestamp/size/SHA vàold-route restoration procedure|Isolated CI3 runtime/files/DB restore+old routing/SSL smoke;verify backups current andcoherent |
| P5-B02 |Target DirectAdmin chưa có hồ sơ|Điền verified PHP/web server/proxy/docroot/storage/DB/cron/log/certificate profile;secret giữ private|Candidate hostname/IP platform/TLS/cookie/no-execution/private-file/cron/log smoke,không cần DNS switch |
| P5-B03 |Không có final-source/freeze/candidate receipt|Đối soát changed existing source rows,final combined candidate provenance vàfreeze plan;giữ demo/exclusions đã duyệt|Fresh-source counts/hash/reconciliation,isolated candidate restore/core E2E/window ledger;không chạy final production migration trong task này |

Đã hoàn thành remediation/rehearsal local vàprepared tooling. Thiếu external evidence không thể sửa bằng code hoặc đổi status. Không hỏi lại backup đã hoãn,không tự truy cập hosting. Baseline và working-tree hashes được giữ trong evidence;Git history ghi nhận commit đóng gói.

PHASE 6 STATUS: NOT READY
