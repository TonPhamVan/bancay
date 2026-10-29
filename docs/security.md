> Current [release recheck](release-readiness/recheck-54a0a2f/report.md#6-security-fixes): P6-B07 offline recovery validation RESOLVED; immutable existing order/contact snapshots and live workflow history now checked; imported history never instructs fulfillment/restock. Composer/npm audit 0; 164 PHP tests and 5 actual HTTP security cases PASS. [Current source scan](release-readiness/recheck-54a0a2f/source-security.json). Actual DirectAdmin TLS/private-path/upload execution/core config unverified under P5-B02. Older evidence below is historical.

> Latest [release review](release-readiness-report.md):fresh audits0;LOG_LEVEL=info now preserves inventory audit events;production-mode controlled500/info logging PASS. Offline recovery path/SHA/no-overwrite guards andnative derived-view maintenance guard tested. DirectAdmin profile absent,so actual host hardening remains P5-B02. No dependencies changed in this review.

# Phase 5 — Security assessment

[Tests](phase-5/evidence/tests.json), [HTTP verification](phase-5/evidence/security-http.json), [audit](phase-5/evidence/composer-audit.json), [source/history scan](phase-5/evidence/source-security.json). Local application review/regression không phải production penetration certification.

| Area | Result / evidence |
|---|---|
| SQLi | Inventory toàn bộ raw SQL: MIN/COALESCE và stock arithmetic tĩnh; filters dùng binding, sort allowlist. Không thấy raw user input nối SQL. Search Vietnamese,quotes,SQL/HTML-like,wildcards,empty/long/array pass. |
| XSS | Raw content qua Purifier/ContentHtml; JSON-LD HEX escaping; editable admin fields e(value,true). Native shell widgets/config giữ native/admin trust boundary. Closing-textarea/script DOM regression pass. |
| CSRF | Không exempt mutations; HTTP thiếu token419, wrong method/validation có status đúng. |
| Auth/session | Native Hash, không MD5 import; login rotate cookie,HttpOnly/Lax; logout vô hiệu cookie cũ. Removed identity revokes session. Native password reset Hash/redaction regression pass; real reset mail NOT TESTED. |
| Permissions/IDOR | Catalog/commerce/content presets và super-admin được kiểm backend Gates,owner scope/revocation. Public success cần exact session grant, admin login không bypass; child cross-owner rejected. |
| Checkout | Server prices/totals,quote recheck,quantity/ownership/status/stock validation; DB attempt lock/idempotency,concurrent no oversell/duplicate,atomic rollback. |
| Order/inventory | Immutable snapshots/history; mọi workflow edge và terminal rejection; cancel release once,ship deduct,return manual inspection. Browser repricing không đổi snapshot. |
| Upload | Business MIME/GD reencode WebP,5MB/8000px/25Mpx,generated name,path/owner checks. Real browser upload PASS; fake/cross-owner rejected. Native avatar fake PHP/image rejected; generated keys/private admin disk/authorized serving. Production no-execution/storage protection still P5-B02. |
| Secrets | Current source plus493 reachable historical text blobs/8 commits,0 known-pattern findings. No rebuild-Git SECRET ROTATION REQUIRED finding. Scan không exhaustive; original legacy credentials private, không dùng lại cho production mới. |
| Errors/headers | Debug-off synthetic500 giấu SQL/path/internal message;404/419/422 đúng; normal browser no500. Global/error nosniff,SAMEORIGIN,strict-origin referrer,Permissions-Policy deny camera/mic/geolocation. |
| Host/proxy | Production exact APP_URL host allowlist: spoofed Host400,valid host200. Actual ingress/proxy/TLS cần P5-B02, không thêm wildcard trust. |

CSP đã kiểm subset object-src none,base-uri self,frame-ancestors self. Nonce/hash script-src còn Low debt: stock Alpine expression evaluation và native Exment inline scripts cần adapter được test. Không gọi subset là script-injection protection. [OWASP headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html), [CSP](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html), [Alpine CSP](https://alpinejs.dev/advanced/csp).

Fresh audit thấy Medium CVE-2026-45070/PKSA-wtxr-p26d-nn42 trong symfony/mime7.4.19. Chỉ nâng mime7.4.22, tương thích Laravel^7.2 và mailer^7.2|^8.0. [Before](phase-5/evidence/composer-audit-before.json), [lock delta](phase-5/evidence/dependency-change.json). Patched ParameterizedHeader kiểm token characters: constructor/single/bulk injection bị reject, Unicode filename hợp lệ vẫn hoạt động. Native valid/CRLF email tests dùng array transport. Final Composer0 advisories/0 abandoned,npm0. Advisory URL từ audit: https://symfony.com/cve-2026-45070 (browser tool unavailable; package source/audit/solver là bằng chứng). RR-30/RR-34 vẫn RESOLVED; không ignore/bypass/vendor edit.

Admin log adapter dùng đúng v7 recursive input API, mask case-insensitive nested password,CSRF,checkout token,API/app key/secret/token/authorization/SMTP/DB password; giữ URI reset-token masking. Không thêm payload logging. Critical exceptions vào daily log; inventory log actor/delta/reason; image cleanup chỉ path hash; migration reports private. Retention/alert routing ở production runbook.

Chưa có order/admin notification feature nên không tuyên bố đã gửi được. Native mail/reset log/array local, không external recipients. SMTP/sender/recipient/timeouts/delivery,SSL/proxy/secure-cookie/no-execution trên target thật NOT TESTED. Không có confirmed Critical/High application defect trong final candidate; production validation còn là blocker.
