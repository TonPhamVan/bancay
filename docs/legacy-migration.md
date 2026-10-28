> Current local website: **demo + migrated data combined at http://localhost:8000**. [UI mapping/merge report](ui-mapping-report.md).121 products/142 SKUs/72 articles/258 orders;120 products/71 articles visible locally, noindex. Original demo rows, source8002 and backups preserved.137 PHP/860 assertions,44 combined browser cases,0 UI mapping mismatches,201 local redirects PASS.13 inactive banner placements remain frontend work. Previous pure-demo/hidden8000 and pre-merge counts below are historical.

> Current stack: **Exment7.0.0 / Laravel12.70.2 / admin-core1.0.1**, PHP8.3.36. [Upgrade report](exment-v7-report.md). Composer audit PASS (0 advisories/0 abandoned), npm audit0; RR-30/RR-34 RESOLVED.132 PHP/814 assertions +5JS and47 browser cases PASS. Both local DBs upgraded;17 business tables unchanged; migrated catalog still hidden. Older v6 pins/audit failures/exceptions below are historical. No production release.

# Local migration operations

Read [Phase4 report](phase-4-report.md) and [authorization](phase-4/authorization.md). Dedicated existing stage: localhost8002, database bancay_phase4_staging, own storage under legacy-private/phase4/storage. Operational bancay remains separate. Native admin local-admin, private generated password in legacy-private/phase4/staging.env; never commit that file.

```sh
docker exec bancay-phase4-app php artisan legacy:migrate --dry-run --include-orders
docker exec bancay-phase4-app php artisan legacy:migrate --force --include-orders
docker exec bancay-phase4-app php artisan legacy:validate
# Targeted catalog preview (parents included):
docker exec bancay-phase4-app php artisan legacy:migrate --only=products --limit=20 --from-id=1 --dry-run
```

Write needs an immediately matching dry-run receipt; no --force bypass. Scope/range/source/target/code/storage/backup changes require new dry-run. Default excludes historical orders; --include-orders explicitly opts into immutable history. Existing legacy IDs SKIP, never update manually edited records. Category dependencies included in full; explicit category-only ranges are bounded and missing parents quarantine. Source snapshots SELECT-only/read-only, effective host db, explicit verified backup proof and local/testing target required; this CLI intentionally cannot run production migration.

Private reports: migration-summary.json, migration-records.json, migration-errors.csv, migration-warnings.csv, media-map.json and legacy-url-map.csv per run. Complete201-row Phase5 URL dataset under legacy-private/phase4/phase5. Reports contain only allowed diagnostics/IDs/hashes; full dumps/source/media/PII stay ignored/private.

Fresh repeat/rollback and UI acceptance used disposable separate DBs/accounts/containers; removed after checks. Actual stage remains hidden. Only restore/reset explicitly owned Phase4 test databases; never operational/legacy production. Phase6 requires a new reviewed production procedure/final source snapshot; current SKIP-only strategy is not blind delta synchronization.

## Content origin remediation

See [risk closure](phase-4/risk-closure.md). New imported HTML uses `/storage/...`. Existing staging content was repaired in124 fields using `python3 scripts/phase4/close_content_risk.py`, with dry plan/fingerprint, fresh verified backup/restore, shared importer lock, stale-plan rejection and reconciliation. Do not call the internal ContentMediaUrls helper as an unguarded production updater. Existing importer receipts are invalidated by source-code/target changes; existing IDs still SKIP, never overwrite edits.

## Approved review decisions

See [applied decisions](phase-4/review-application.md). The private `legacy-private/phase4/review-overrides.json` manifest binds user-approved source category IDs/media SHA-256s, exact source fingerprint and exact staging target. Optional `LEGACY_REVIEW_MANIFEST` selects a different private manifest; enabled migration defaults to the approved local path when present. Wrong target/source/hash fails closed. Runner receipts bind review content; source rows and existing catalog edits are preserved.

The guarded one-time `python3 scripts/phase4/apply_review_decisions.py` requires exactly2 product/7 article additions and fresh verified backup. It has already run; use `--verify-existing` for read/restore-to-scratch/reconciliation/idempotent-rerun checks rather than replaying the nine-record application. The historical fresh_repeat tool/evidence describes the pre-review dataset; do not compare its old113-product fingerprint to current115-product staging. Reviewed original historical NULL links80→7 and163→10 remain unchanged under an explicit allowlist, not a blanket exception.
