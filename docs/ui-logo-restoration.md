# Local legacy logo restoration — 29/10/2026

User explicitly requested the old website logo. The CI3 `application/views/header.php` renders `images/{tblfooter.logo}`; the supplied source record points to `logo4 (1).jpg`, visually identified as ONE GARDEN. Original JPEG: 181167 bytes, SHA-256 `39a3e8f9e2097950ba21a9b06650599ddebbc992a9c83154cd48b7236c683c43`.

The already migrated, decoded/re-encoded WebP exists in both main and original staging storage: `settings/legacy/39a3e8f9e2097950ba21a9b06650599ddebbc992a9c83154cd48b7236c683c43.webp`, 22038 bytes, 1228×296, SHA-256 `3072d7e313ac5e48c875db6aa32f9df8ef731c7c4b7ed8c2a71e941fc3892387`.

Applied only the `logo` public setting on local combined database `bancay` using `SetPublicSetting` in a transaction. All other settings compare identical before/after. This is the user's explicit exception to preserving demo branding; site name/contact/meta and both catalog datasets remain unchanged. No importer, reset, source/staging write or production action.

Verification: current homepage HTML references this logo in the header; asset HTTP 200, `image/webp`, response SHA-256 matches the existing migrated asset. The template already supports uploaded logos with bounded dimensions. Original files were not modified. Browser connector verification was unavailable; native Chrome inspection was interrupted by user input, so no responsive/browser PASS is claimed for this change.

Previous logo state and execution/HTTP receipts are private under `legacy-private/ui-fixes/`. No credentials, SQL, private setting values or customer data enter Git. To undo this specific local change, restore the previous logo row from the private receipt (previously absent); do not reset settings/catalog. Asset remains reference-safe and is retained.

Historical release readiness evidence stays unchanged. The earlier checksum checkpoint predates this authorized settings change; it must not be rerun and portrayed as identical current data. Phase 6 remains NOT READY for P5-B01/P5-B02/P5-B03.
