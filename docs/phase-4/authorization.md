# Phase4 isolated rehearsal authorization

28/10/2026 (Asia/Saigon). Latest direct user instruction: “bỏ qua Blocker còn lại update sau, tiếp tục chạy lại phase 4”. This explicitly permits isolated Phase4 work despite RR-30/RR-34, superseding the earlier READY-only gate for this local task. It is not dependency remediation and does not authorize production deployment/cutover or hiding Composer audit FAIL.

RR-30/RR-34: ACCEPTED temporarily for isolated local rehearsal under the user's instruction; target compatible stack update before public staging/production. Keep mail log-only, APIs disabled, indexable false, source SELECT-only, target disposable, no hosting/production connection. Browser current build PASS; current-app backup restored and verified.

Phase0 source ZIP/SQL/media checksums must be reverified; new target backup/restore must pass before import. Additional admin_operation_log rows from previous checks are operational audit metadata, not evidence of a catalog/order mutation; preserve and verify operational domain checksums separately.

Scope: migration layer/CLI, source read-only snapshot clone, fresh native Exment staging+domain schema, dry-run receipt, quarantine/mapping, import/re-run/reconciliation/tests and29-section report. Defaults hidden, history only with explicit --include-orders (this Phase4 request authorizes historical rehearsal); admin identities remain manual-review/default-deny, no MD5 or inferred superadmin. No schema weakening or old migration edits.
