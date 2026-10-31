# UI acceptance — 31/10/2026

**IAB functional acceptance: PASS for 20 executed case groups. Full browser acceptance: INCOMPLETE. PHASE 6 STATUS: NOT READY.**

The user requested UI acceptance for localhost8000/san-pham and localhost8000/admin. This run resumes the unfinished UX2 checks. It fixes reproduced defects, verifies the current build and leaves the combined main catalog intact. It does not certify production or standalone Chrome/Edge.

## Baseline and scope

- Git `93c581c3976b41d7bcfcf6f44313d11073da8cdb`, branch `feature/develop`; existing review/B1/UX2 changes are uncommitted and preserved. No commit/push performed.
- Exment7.0.0 / admin-core1.0.1 / Laravel12.70.2 / PHP8.3.36 / MariaDB10.11.19. Master Plan and current B1/UX2 implementation, reports, status and tests reviewed. No package/schema migration.
- Main8000: read-only product pagination, category/menu/layout and native login entry. Actual login and business writes were exercised on the owned localhost8004 clone, not main.
- QA `bancay_release_candidate_20261031_002`:98 tables cloned and verified before writes, private storage, log-only email. Synthetic draft product122,2 groups/4 SKUs/2 images; product123 with256 SKUs; two pending order fixtures and draft article73. Existing QA container was restarted after it stopped between turns; fixture was not replayed.
- Automated destructive tests ran serially on guarded bancay_test. No legacy import, production connection, DNS/docroot or cutover action.

## Reproduced defects and fixes

| ID | Defect | Fix and verification |
|---|---|---|
| UI-F01 |101 option values correctly failed server validation, but JS tried to focus a nonexistent row input and stopped initialization |Focus visible source textarea for overflow; preserve all101 lines.100-row boundary and disabled Add verified; original2 values saved again |
| UI-F02 |Array/string/numeric min/max messages used ineffective flat keys and could remain English |Laravel-supported nested min/max messages; HTTP and focused validation regression PASS |
| UI-F03 |A failed save became the new clean fingerprint, so leaving a retained failed attempt could discard input without warning |Failed option/gallery/generic/settings forms carry initial-unsaved state. Stale gallery and option failure leave/stay verified |
| UI-F04 |Overflow→100 values left the mode-toggle label inconsistent |Synchronize toggle text with source visibility; switch to100 rows and back verified |
| UI-F05 |Banner category labelled optional was HTML-required for shared placements, blocking a valid blank selection |Exclude banner category from unconditional HTML required flag; explain conditional rule. Server required_if:position,category remains unchanged; product category remains required |

Changed layers: admin JS, StoreLabels/StoreResources, existing Blade forms and regression tests. No stock/order policy, native grants, content rewriting or schema changes.

## Actual UI evidence

[Machine-readable cases](evidence/browser-cases.json): **20 PASS /2 NOT TESTED /1 BLOCKED**. Each row is a case group, not an assertion count. Earlier B1/UX2 browser evidence remains historical.

| Area | Result | Observed evidence |
|---|---|---|
| Option validation |PASS |101 lines retained; Vietnamese limit; focus textarea;100 rows/Add disabled; saved2 original values |
| Unsaved forms |PASS |In-page Stay/Leave; failed-attempt guard; Back/PJAX Stay keeps URL/input; Escape preserves edit; matrix reset clears dirty |
| Gallery |PASS |Keyboard Enter reorder, alt/primary batch save; second stale tab rejected without overwriting; attempt retained |
| SKU matrix |PASS |Thumbnail follows own image; saved selection survives reload; compact-only toggle stays clean;256 rows; keyboard reaches final row |
| Matrix responsive |PASS |320px document contained; matrix scroll1180px inside216px region; sticky headers; [screenshot](evidence/matrix-256-mobile.png) |
| Order queue |PASS |Search/status/origin/oldest preserved through detail, status update, next eligible row and Back; history visible |
| Article/SEO |PASS |Saved draft private preview with null date/noindex; saved fallback shown in editor; public draft URL shows404 |
| Banner/settings/dashboard |PASS |Placement/sizing/schedule and unsupported slots explained; settings help; clean file form navigation has no false dirty dialog; task shortcuts visible |
| Main products |PASS |Actual page2 link:25–48/119;1440/768/390/320 actual viewport widths, no document overflow or broken loaded images |
| Main mobile category |PASS |320px menu navigation and Enter expand full introduction; no horizontal overflow; legacy text retained |
| Admin form responsive |PASS |Dedicated current tab at1440/768/390/320; no document overflow; [measurements](evidence/admin-responsive.json) |
| QA core checkout |PASS |Published test product;390px gallery/variant→cart→quantity2→checkout→success200000VND; [success](evidence/mobile-order-success.png) |
| Admin after checkout |PASS |Order262 shows SKU/variant2×100000 snapshot, total200000; NEW→CONFIRMED→CANCELLED history; [evidence](evidence/order-snapshot-cancel.png) |
| Main admin entry |PASS |/admin redirects to native login; QA native admin login succeeds. Main credentials/grants unchanged |

A transient in-app tab handle became unavailable; a fresh tab on the same connected browser resumed the existing checkout session. An earlier click to256-row detail did not navigate in an old tab; clean-list replay and direct detail both rendered256 rows, with no console errors. No unverified application fix was made for these transient observations.

## Database verification

[Read-only QA verification](evidence/qa-verification.json): original option values unchanged;2 images/one primary;4 SKUs plus256-row fixture. After cancel: physical stock10, reserved0. Order262 retains unit price100000, quantity2, total200000 and three history entries. This is a synthetic E2E; no main order was created.

[Clone/cleanup receipt](../../business-ui-remediation/ui-acceptance-20261031/evidence/cleanup.json): all main domain row counts/checksums and all staging tables match the initial baseline. Cleanup initially refused because product_images SHOW CREATE hash differed. Investigation proved row count356 and checksum2207693338 identical; the only DDL difference was AUTO_INCREMENT357→358. The original checksum-bound dump and baseline were retained; normalized DDL matched exactly. No origin is inferred, and neither counter nor native audit activity was reverted. Dedicated exact-owned cleanup removed only QA DB/account/container. Main admin/local-admin identities and both data sets remain.

## Automated checks

[Checks](evidence/checks.json): **201 PHP tests /1392 assertions PASS**,0 failed/skipped; **5 JS tests PASS**,0 failed/skipped. Vite production build, Pint, Composer validation/platform and Exment requirements PASS. Composer0 advisories/0 abandoned; npm0 vulnerabilities. Additional node --check passed for both admin scripts. Three regression tests cover overflow retention/initial dirty state, localized limit types and optional shared-banner category. No failed test was disabled.

## Acceptance limits and remaining work

| ID | Status | Required closure |
|---|---|---|
| UX2-V01 native lifecycle |NOT TESTED |Actual user refresh/close warning. Provider reload completed without exposing beforeunload dialog; this is not proof of native prompt behavior. In-page link/form/Back/Escape checks above passed |
| BA-V01 named browsers |BLOCKED |Current standalone Desktop Chrome, Edge and Mobile Chrome core flow. Connected browser inventory exposes IAB only; IAB responsive emulation is not a named-browser PASS |
| UI-L02 Safari/iOS |NOT TESTED |No environment available |
| Staff UAT |PENDING |Owner/staff operational acceptance; no new risk acceptance inferred |
| BA-G04 |CONTENT REQUIRED |Real launch SKU/price/stock/photo approvals remain owner-dependent |
| P5-B01/B02/B03 |BLOCKER |Actual production backup/recovery, DirectAdmin target profile and frozen-source/candidate rehearsal remain outstanding |

Full native adapter replacement, visual rich-text editor, pointer image drag and other deferred enhancements retain their existing backlog status. They were not silently implemented or reclassified as accepted risks.

**The executed IAB UI scope passes. Full UI/browser sign-off remains incomplete for the explicit limitations above. PHASE 6 STATUS: NOT READY.**
