# Risk review — targeted data-integrity changes

Baseline: b574d19; request: [risk consolidation](request.md). No Phase4 import or production access.

## Confirmed defects

Six new tests fail against baseline on real MariaDB: FIXED NULL price accepted by SQL CHECK; unsigned BIGINT amounts above PHP_INT_MAX accepted for variant/order/item despite integer casts; replacing/deleting a shared image removes a file still referenced elsewhere. Before-fix JUnit is kept private and summarized in evidence. These are actual reproductions, not hypothetical refactors.

## Schema change proposal

Add one new migration; preserve all existing migrations, tables, columns, IDs, FKs and data. Add CHECKs to product_variants/orders/order_items: explicit FIXED price IS NOT NULL and all monetary values within the supported signed64-bit PHP integer domain. Existing positive/sale/total constraints remain. Before ANY DDL, scan all three tables for incompatible rows; fail with counts only, do not silently repair/truncate/reprice. MariaDB DDL is not fully transactional; preflight avoids expected data errors causing partial changes. Operational tooling must still handle exceptional DDL failures explicitly. Down only drops the three added checks. Verify upgrade with existing rows, rejection of bad preflight data, rollback/remigrate, boundary MAX, FKs and full regression on bancay_test. Capture a private pre-change local dump; no production migration.

## Media cleanup change

Reuse the upload storage service for reference-aware, best-effort cleanup after commit. Check ProductImage, Category, ArticleCategory, Article, Banner and logo settings before deleting an old path. Keep files still referenced (including archived product image rows). Validate public path; cleanup failure defers orphan removal and logs a path hash, rather than deleting the newly committed image or making a successful save appear rolled back. Upload failure cleanup of newly generated unreferenced files remains unchanged. No media registry/schema/library rewrite. Concurrent imports must run in maintenance/isolation and never bypass the declared reference contract.

## Dependency decision

Read-only official GitHub Releases API and Packagist checks identify v6.2.15 as the latest stable release available during this review, requiring Laravel ^10.34. No verified stable v7 upgrade path exists yet. Do not install dev branches, change framework constraints against Exment, fork vendor or hide advisories. Recheck Composer audit and actual code applicability. The exact official Laravel10.50.4 TokenGuard already rejects non-string tokens; byte comparison and dedicated regression tests support a FALSE POSITIVE for that specific advisory. Do not equate version-range flags with proven exploitation. Composer audit still fails, and the separate High email-validation advisory has no verified compatible upgrade in the locked Exment6 stack; log-only mail and app CR/LF rules mitigate current exposure but are not an upstream fix. See dependency-assessment.md for the per-advisory decision.
