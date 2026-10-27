# Public routes và contracts

Ngày: 28/10/2026. Tất cả public storefront chạy web middleware/CSRF; routes admin vẫn trong native Exment. Named route prefix `store.`. [Danh sách route thực tế](phase-3/evidence/routes.json).

| Method | Path | Route suffix | Hành vi |
| --- | --- | --- | --- |
| GET | `/` | home | Hero, categories, featured/latest products, articles |
| GET | `/san-pham` | products.index | Paginate24; q/sort/page |
| GET | `/san-pham/{slug}` | products.show | Gallery/options/availability/related |
| GET | `/danh-muc/{slug}` | categories.show | Visible category + descendants |
| GET | `/tim-kiem` | search | Name / ACTIVE SKU; noindex |
| GET | `/bai-viet` | articles.index | Published articles, paginate15 |
| GET | `/chuyen-muc/{slug}` | article-categories.show | Published article category |
| GET | `/bai-viet/{slug}` | articles.show | Sanitized content + related |
| GET | `/lien-he` | contact | Settings; safe outbound map/social links |
| GET | `/gio-hang` | cart.index | Current prices, invalid-line notices |
| POST | `/gio-hang` | cart.store | product_id, variant_id, quantity, intent |
| PATCH | `/gio-hang/{variant}` | cart.update | quantity only |
| DELETE | `/gio-hang/{variant}` | cart.destroy | Remove own session line |
| DELETE | `/gio-hang` | cart.clear | Clear own cart |
| GET | `/thanh-toan` | checkout.index | Quote + session-bound checkout token |
| POST | `/thanh-toan` | checkout.store | Guest contact/address + token; server builds items |
| GET | `/don-hang/{number}` | orders.show | Exact session grant required, otherwise404 |
| GET | `/tat-ca-san-pham` | legacy.listing |301; keyword→q, page0→page1 |
| GET | `/{alias}-(sp\|dm\|bv\|cm){id}.html` | legacy.entity | Resolve published legacy_id → current slug301 |
| GET | `/health` | health (no store prefix) | Independent JSON health |

Mutating cart and checkout routes use session `block(15,10)`; cart/checkout/success GETs also serialize with those writes. Add/update cart throttle60/minute, checkout POST10/minute. Requests exceeding throttle return429; invalid input uses Laravel validation redirect or422 JSON, missing CSRF419, unsupported method405, absent/hidden entity404. Guest response caching is disabled. Forms use POST + `_method` for PATCH/DELETE.

`q` string max120; sort allowlist featured/newest/price_asc/price_desc; page integer1..100000. Cart positive quantity<=1,000,000 and max50 distinct variant lines, always also limited by available stock. Identifiers and ownership checked server-side; JSON picker IDs are strings to preserve BIGINT precision in JavaScript.

Slug uniqueness and validation remain Phase1/2 rules. New routes use current unique slug without forcing a legacy ID suffix; Phase0 suffix proposal was a draft. Full import must assign unique normalized slugs and retain legacy_id. No thousands-line redirect map; no missing-entity redirect to home. CI implicit `/site/...`, stale `/sieu-thi-cay/...`, legacy media paths and exact old category query pagination need Phase4 mapping / Phase5 review. Existing entity resolver drops legacy extra query parameters including popup, keyword and page; only `/tat-ca-san-pham` translates paging/search today. Do not treat this foundation as complete migration parity.
