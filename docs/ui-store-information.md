# Local legacy store information — 29/10/2026

User explicitly requested restoration of the old store information after the logo restoration. This authorizes replacing the corresponding demo public settings on main8000 only.

Source: supplied CI3 `tblfooter` record ID 2. Fields applied through `SetPublicSetting`, inside one transaction:

| Current setting | Source |
|---|---|
| site_name | tblfooter.name |
| contact_phone | tblfooter.tuvan1; tuvan2 has the same value |
| email | tblfooter.email |
| address | tblfooter.diachi; only city provided, no street address invented |
| facebook | Exact Facebook URL extracted from tblfooter.fanpage; HTML widget not copied |

The legacy map value ends at `/maps/pl`; this incomplete location URL was not applied. Main map stays NULL and contact page retains its updating notice. Logo, meta settings and other keys compare unchanged. No legacy rich footer HTML, analytics/scripts, dated offers or embedded widgets were copied. No migration replay, catalog write, source/staging write or production action.

Verification: HTTP 200 on home, contact and product-list page 2. All three footers contain restored store name/address/phone/email/Facebook; `tel:` punctuation normalized, `mailto:` valid, logo retained. Contact map notice remains visible. Main/stage integrity audits PASS, settings counts 9/8; source JSON SHA-256 unchanged. Browser UI/device verification was not rerun for this settings-only update.

Original settings snapshot, source-bound input, transaction receipt and HTTP/audit results remain private in `legacy-private/ui-fixes/store-information-*`. To undo, restore only these five keys from the previous-settings receipt, preserving the independently restored logo and all other data. This local change does not close P5-B01/P5-B02/P5-B03 or authorize Phase 6 cutover. Historical release evidence is preserved.
