# Phase4 migration architecture

Use app/Legacy for source reader, pure transformers, guarded media/content mapping, per-entity transactional writer, private reports/receipt and validator. CLI legacy:migrate supports --dry-run/--only/--limit/--from-id/--force and --include-orders; legacy:validate reconciles imported rows. Source snapshot uses a separate SELECT-only account and read-only sessions. No migration logic in Controller/Blade/Exment forms.

Target is fresh bancay_phase4_* DB on local Docker, own storage/session/cache/runtime plugins; PHP serve --no-reload preserves isolated DB env. Native Exment InstallSeeder initializes system tables and existing app services create normalized catalog. Historical orders use their own writer with immutable snapshots and no inventory/email effects.

No domain schema change required. Identity uses existing legacy_id and banner namespace; existing imported entities SKIP, never overwrite admin edits. Stable IDs/combination keys and deterministic safe media paths support rerun. Conflicting source changes require a new dry-run; don't silently repair.

Dry-run writes only private plan/report artifacts, no DB/business-storage writes. Receipt binds source fingerprint, target domain fingerprint, options, relevant code, backup proof and storage root. --force acknowledges a write run but cannot bypass guard/receipt. Single importer lock; transaction per entity, file cleanup on failure protects shared references.

Mapping: all migrated content DRAFT/published_atNULL; bannersINACTIVE. SKU fallback deterministic with original codes and conflicts reported. Orphan/invalid JSON/quantity/history rows quarantined. Content local src/poster URLs mapped only inside verified media root; executable tags purified, unresolved/unsupported/external URLs flagged for review. Publication for browser acceptance uses only a disposable clone and synthetic checkout contact, then removes clone.

Full legacy URL/record/media/PII reports stay private. Git evidence contains aggregates/IDs/hashes/test statuses only. Backups retain original unmodified ZIP/SQL/extraction; never run original PHP runtime or external integrations.

## Confirmed validation adjustment from dry-run

Two legacy category descriptions have25,047/37,105 characters (29,721/43,651 bytes), fitting existing TEXT but exceeding admin10,000-character input cap. Preserve full sanitized content: raise category description bound to65,535 characters plus explicit65,535-byte DatabaseText rule; migration checks output bytes. No DDL change or new schema needed. Add admin regression for long text and rejection above byte limit. Empty legacy image string means absent optional gallery, not malformed variant JSON; import hidden product with fallback/warning, retain empty source provenance. Article cid0/orphan references remain quarantine.
