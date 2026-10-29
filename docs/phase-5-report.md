> Latest release-blocker review: [report](release-readiness-report.md), [blockers](release-readiness/blockers.csv), [matrix](release-readiness-report.md#17-release-readiness-matrix).158 PHP/1054 assertions +5JS,47 branded browser cases,24 responsive,13-step E2E PASS;audit0. Added tested portable native-view restore,offline order delta,DirectAdmin cron andinfo-level inventory logging. P5-B01/02/03 remain BLOCKER for current hosting/final-source proof. Earlier Phase5 counts below are historical. PHASE6 NOT READY.

# Phase 5 — SEO, Security, Testing & Final Hardening

Ngày29/10/2026,Asia/Saigon. Scope local release-candidate hardening; không production cutover/DNS/final migration. [Readiness](phase-5/readiness.md), [53-risk register](phase-5/evidence/risk-register.json), [verification](phase-5/evidence/verification.json).

## 1. Executive Summary

Application hardening/regression local đạt; **chưa đủ điều kiện production Release Candidate** vì3 release gates ngoài application còn thiếu bằng chứng.149 PHP tests/1033 assertions,5JS,47 branded browser cases,24 responsive cases,13-step admin/public/admin E2E và5 security HTTP cases PASS. Composer/npm audit0. Giữ cả demo+migrated website8000 và staging8002; không mất/sửa domain data.

## 2. Inputs Reviewed / Baseline

[22 input hashes](phase-5/evidence/inputs.json):Master Plan,Phase0–4 reports,reconciliation,SEO inventory,old security,architecture/ERD,tests/manual browser,current risks và approved-review readiness; current UI mapping/v7 proof supersede historical v6/NOTREADY statements. Phase4 gate là READY WITH RISKS nên được bắt đầu Phase5. Không có standalone Phase5-readiness artifact trước task; input gate được xác minh từ Phase4/current merge evidence,không bịa một report cũ.

Commit da70bd4aa22d9827649b27fd7585cdcfb8fb7ad0,branch feature/develop. First inspection clean; baseline collector ghi thêm untracked docs/phase-5 do vừa tạo evidence. Các thay đổi Phase5 hiện chưa commit. [Baseline](phase-5/evidence/baseline.json) có migration ledger và versions:PHP8.3.36,Laravel12.70.2,Exment7.0.0,admin-core1.0.1,MariaDB10.11.19. Environment local Docker,main8000+bancay,source stage8002+bancay_phase4_staging,legacy source SELECT-only. Tests bancay_test,browser QA clone riêng. Symfony mime patch7.4.19→7.4.22 là dependency delta duy nhất. Không migration/schema change ở Phase5.

## 3. Data Integrity Audit

[Main](phase-5/evidence/integrity-main.json), [stage](phase-5/evidence/integrity-staging.json):37 checks mỗi DB PASS,0 Critical/High data defect. Full FK/composite references,tree cycles,duplicate SKU/slugs,inventory,price/sale,empty orders,snapshots,totals/status/history và missing live catalog links.255 historical unknown SKU và2 NULL product links là nguồn đã duyệt,không phải fabricated repair; live orders bắt buộc SKU/reference. Stored HTML/media không bị Phase5 updater sửa.

## 4. Migration Validation

[Fresh source reconciliation](phase-5/evidence/reconciliation.json) PASS,[combined preservation](phase-5/evidence/combined-preservation.json) PASS/NOOP/CREATED0. Source fingerprint giữ nguyên. Không gọi migration/import lại. Counts:

| Entity | Legacy / source intent | Stage migrated | Main combined | Skipped / failed / review |
|---|---:|---:|---:|---|
| Categories |8|8|14|0 unexplained |
| Products |115|115|121|0 quarantined after approval |
| Variants / Inventory |115 products→120 normalized SKUs|120 /120|142 /142|0 mismatch |
| Product images |347 converted/referenced gallery rows|347|356|0 missing referenced file |
| Article categories |11|11|13|0 unexplained |
| Articles |67|67|72|0 quarantine;1 accepted fallback |
| Customers / addresses |0 approved identity import|0 /0|2 /1 demo|No historical-phone identity inference |
| Orders/items/history |748 source orders|255 /255 /255|258 /258 /260|493 invalid source orders intentionally excluded |
| Banners |23 potential banner/slider source rows|21|24|2 ambiguous excluded;21 source inactive |
| Settings |8 mapped public keys|8|8 target keys|Target demo settings retained;source settings remain stage/archive |
| Legacy users |2|0|Existing native only|2 intentionally excluded,no MD5 |

Main published120 products/71 articles;one demo product/article stays draft. Counts reflect snapshot,not final-production-export certification. No unexplained important-entity loss. Main unique media785 references all exist,largest626578bytes;staging775 references all exist,including JSON-cast logo setting. No conflation of rows,references,unique files.

## 5. SEO Review

[SEO details](seo.md),[source parity](phase-5/evidence/migration-seo.json):201 titles/slugs/metas verified,0 mismatch;entity fallbacks reasonable. Full sitemap crawl covers221 published/static targets,metadata/H1/OG/canonical/JSON parse PASS;no fake ratings/prices. No old-domain/unsafe content findings in413 main HTML fields. Google rich-result/index outcomes NOT TESTED locally.

## 6. Legacy Redirect Validation

201 encoded actual legacy URLs→301 correct destination→200,tracking/next dropped,no application loop/chain.115 products,all8 categories,67 articles,11 article categories exceeds requested product/article samples;source only8 product categories. Zero-based category/article-category pagination translated;removed/hidden404,not homepage. Ingress HTTPS/www behavior remains P5-B02.

## 7. Sitemap / Robots / Canonical

New publication-scoped XML sitemap,environment-aware robots,staging X-Robots-Tag;removed static allow-all robots. Pagination self-canonical;private/search noindex. Production index remains gated,false in template until Phase6. Sitemap excludes unpublished/private paths;tests cover production branch without changing actual env.

## 8. Security Assessment

[Assessment](security.md):XSS/raw-output,SQL binding,CSRF,uploads,secrets/history,error exposure,headers/Host reviewed and regression tested. SecurityHeaders applies to normal/error/native responses;production Host allowlist exact APP_URL. CSP tested subset,strict script policy deferred Low. No confirmed release-blocking Critical/High application defect;production infrastructure unverified remains blocker.

## 9. Authentication / Authorization

Native Hash/reset/role capability/revocation tests PASS;no legacy MD5. Product/Order/Content Manager and super-admin backend enforcement tested. HTTP login rotates session,HttpOnly/Lax,logout invalidates old cookie;fresh guest cannot read public/admin order. Granular read/write/export profiles remain Low scope debt. Native mail-reset delivery is NOT TESTED externally.

## 10. Checkout Security

Tampered product/variant/price/totals/quantity/stock,invalid ownership/inactive/OOS,stale quote/category,CSRF,replay and concurrent double submit regressions PASS. Server-owned values,attempt row lock and atomic transaction preserved. No payment gateway or shipment quote feature invented.

## 11. Inventory / Order Integrity

Snapshot immutability after product/name/price/status changes,all workflow edges,terminal rejection,history,ship/cancel side effects PASS. Browser order123000×2 stays246000 after current variant price200000;cancel leavesphysical10,reserved0,history NEW→CONFIRMED→CANCELLED. [DB proof](phase-5/evidence/e2e-database.json). RETURNED remains manual physical inspection,no auto-restock;runbook assigns daily pending reservation review.

## 12. File Upload Security

Business MIME/decode/reencode,size/pixel/generated-path/owner tests PASS;actual browser product PNG upload becomes safe WebP. Native avatar fake PHP disguised image rejected. Source and shared-image cleanup invariant retained. Target webroot/no-execution/private admin storage must be verified under P5-B02;no AV platform added.

## 13. Dependency Audit

Fresh Medium mime advisory fixed with single compatible patch7.4.22. Constructor/single/bulk MIME parameter injection tests and native valid/CRLF mail PASS. Final Composer0 advisories/0 abandoned,npm0 vulnerabilities. No major upgrade,ignore or vendor edit. Previous RR-30/RR-34 resolved v7 state preserved.

## 14. Automated Test Result

[149 tests/1033 assertions](phase-5/evidence/tests.json),0 fail/error/skip;5JS PASS. Includes all Phase1–4 tests and12 Phase5 tests. Pint,Composer strict validation/platform,Exment requirements,Blade compile,npm ci/build PASS. Clean source-only install and cache/HTTP smoke PASS;17 domain schemas identical ignoring AUTO_INCREMENT counters. Fresh native97 base tables+2 views;upgraded main98 base tables+2 views giữ thêm system_logs,UUID names khác theo install. Private clone DB/account removed. [Clean proof](phase-5/evidence/clean-install.json).

## 15. Functional Test Result

[Matrix](phase-5/functional-matrix.csv) covers22 core areas,all PASS local via actual browser and/or feature tests. Category/product CRUD,images,variants,stock,orders/workflow,customer,articles,banner/settings and all public pages covered. Contact is settings-only;no SMTP contact form claimed.

## 16. E2E Test Result

[13-step actual browser](phase-5/evidence/admin-frontend-e2e.json) PASS:admin create category/product/options/variants/upload/price/stock→public category/product/select/cart/checkout/success→admin item price snapshot/status/history/reprice/cancel. Database independently checks totals,stock,history. Original main/stage orders unaffected.

## 17. Migrated Data E2E

[47 browser cases](phase-5/evidence/browser-acceptance.json),[3-order DB proof](phase-5/evidence/browser-data-verification.json) PASS on migrated product4 and recovered product8/article media. Actual browser repeats core flow per Chrome/Edge/mobile;QA-only synthetic contact. Lazy images are scrolled into view before load assertions. No local publication change on source stage8002.

## 18. Responsive Test

[24 page/viewports](phase-5/evidence/responsive.json) PASS:1440desktop,768tablet,390mobile,320narrow ×six core pages. Fixed intrinsic gallery grid overflow with min-width0. Alt/labels/buttons/H1/skip-link/menu/image/network checks pass;not full WCAG certification.

## 19. Cross-Browser Test

Installed Chrome desktop18,Edge desktop18,Chrome mobile viewport11 PASS;browser versions recorded. Console/page/server errors empty in final runs. Safari/iOS NOT TESTED,no runtime/device;physical Android device also not claimed. Initial transient runner network error and test timing issues retained privately;final sequential rerun clean.

## 20. Performance Findings

[Measurements](phase-5/evidence/performance.json),[details](performance.md):5 warm samples on actual-clone dataset,all6 pages200,queries bounded<30 and no query>100ms. Median approximately10–20ms at HTTP kernel,not production latency/SLA. EXPLAIN uses relevant indexes;1→21 query-growth regression PASS. No image>1MB;dimensions/lazy/async added at render;no unsafe stock/cart cache.

## 21. Error / Logging Review

Debug-off500 hides internal path/SQL/message. HTTP404/419/422 and normal200/301 verified. Native v7 operation masking now case-insensitive recursive and includes checkout/API/app/SMTP/DB secrets;password reset path masked. Private daily application logs,inventory actor/delta,hashed media cleanup and migration reports retained. Production mail/reset/critical-error alert routing requires P5-B02;mail array/log isolated,0 real recipients.

## 22. Remaining Risks

53-row register with impact,evidence,action/test,workaround,owner,target phase,release flag. **P5-B01/RR39**:production coherent backup/restore+routing rollback absent;**P5-B02**:target runtime/TLS/proxy/storage/SMTP unverified;**P5-B03**:final frozen-source/combined release artifact rehearsal absent,SKIP-only importer not delta sync. Required fixes and verification in [runbook](production-runbook.md). Source exclusions/media fallback remain accepted by user,not reopened.

## 23. Technical Debt

Low/nonblocking:strict script CSP,physical Safari/iOS/full accessibility/load certification,granular view/write/export,editor/media library,CDN/srcset/cache,ledger/expiry/token retention/return inspection automation. Daily manual operational policy assigned. No Phase7 features added. [Manual queue](phase-5/manual-review.csv) classifies all approved Phase4 groups RESOLVED/ACCEPTED/DROP;new release-data item has Data Owner/Release Lead and blocker ID.

## 24. Production Configuration Checklist

Secret-free .env.production.example and [runbook](production-runbook.md) cover env/debug/origin,DB,secrets,session/cache/queue/mail,filesystem/storage link,webroot/no-execution,TLS/proxy/cookies and index switch. Template prepared;actual values/host checks NOT VERIFIED. No production config modified.

## 25. Final Migration Runbook

Freeze all writes/cron→coherent final backup→restore verify→read-only source/fresh local builder→matching dry/write receipts/reconcile→preserved-demo combined artifact→restore validation→candidate deploy commands. Real supported CLI signatures documented;no fabricated production legacy:migrate/merge flag. Existing guards forbid production use. Unsupported final incremental merge procedure explicitly BLOCKED P5-B03,not claimed complete by writing commands.

## 26. Rollback Plan

[Plan](rollback-plan.md):freeze Laravel traffic/writes,backup new-window data,export/reconcile real orders and stock via correspondence/manual ledger,restore exact old routing/SSL,verify CI3,then reopen legacy writes. Preserve new data read-only;no direct reverse-schema dump or blind restock. Exact production commands/restore rehearsal absent under P5-B01;plan is prepared,production rollback not READY.

## 27. Smoke Test Checklist

Runbook defines home/product/image/cart/checkout/admin/order/snapshot/legacy301/HTTPS/email/logs,ownership/replay negatives,controlled test cancellation and monitoring15/30/60min/24h. Current local subset PASS;cutover validation NOT RUN. Abort on important correctness/security/data loss regardless pass percentage.

## 28. Phase 6 Readiness

| Area | Status | Evidence | Blocker? |
|---|---|---|---|
| Database |PASS|integrity-main/staging|No current data defect |
| Migration |PASS snapshot|reconciliation,combined-preservation|P5-B03 final source/artifact |
| Exment Admin |PASS|full suite,13-step E2E|No application blocker |
| Frontend |PASS|browser/responsive/SEO|P5-B03 release-visible dataset |
| Cart/Checkout |PASS|HTTP/concurrency/browser|No application blocker |
| Order |PASS|snapshot/history/DB proof|No application blocker |
| Inventory |PASS|locks/cancel/ship/DB audit|No application blocker |
| SEO |PASS local|201 redirects/221 sitemap pages|P5-B02 actual ingress/origin |
| Security |FAIL deployment gate|app/audits PASS;target NOT TESTED|P5-B02 |
| Responsive |PASS|24 cases|No |
| Browser |PASS baseline|Chrome/Edge/mobile47|Safari/iOS NOT TESTED,nonblocking baseline limit |
| Performance |PASS local baseline|6 pages/query plans|Target latency NOT TESTED |
| Backup |FAIL production gate|98-table local restore PASS|P5-B01 |
| Rollback |FAIL production gate|Detailed runbook,local restore|P5-B01/P5-B03 rehearsal |

**P5-B01 required verification:**operator coherent production SQL/files/config snapshot with SHA-256 and isolated CI3 restore/routing rollback smoke. **P5-B02:**candidate host platform/TLS/proxy/cookie/storage/SMTP/reset proof. **P5-B03:**fresh freeze/release builder/mixed-dataset provenance,full reconciliation and new-window-order rollback rehearsal. Không triển khai Phase6/cutover trong task này. Chưa commit/push Phase5.

PHASE 6 STATUS: NOT READY
