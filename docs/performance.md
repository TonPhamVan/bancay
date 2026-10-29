> Latest [8-page measurements](release-readiness/evidence/performance.json) add Admin Product andAdmin Order to six storefront pages. Five warm samples/page,7–20 queries,no query>100ms;approximately9–21ms local kernel medians. No production SLA claim.

# Phase 5 — Performance baseline

[Measurements/query plans](phase-5/evidence/performance.json):5 warm samples/page,HTTP kernel+Blade+MariaDB trên clone bộ gộp; không phải network/browser latency hoặc production SLA. Cart có migrated SKU thật,checkout200; performance probe không tạo order.

Home/category/product/search/cart/checkout có bounded queries, không query>100ms. Existing 1→21 product/cart-line query growth tests PASS. Product eager-load gallery/options/variants/inventory/related; banner slots thêm2 bounded queries. Category24/page,articles15/page,admin25/page,exports chunk200. EXPLAIN dùng products_browse_index,variant product/status index,order PRIMARY LIMIT25 và orders customer index cho count. Không tái hiện N+1 growth/unbounded public listing.

Media main:0 missing,0 files>1MB,largest626578bytes. Gallery thumbs80×80; logo có dimensions; content images local có intrinsic dimensions/lazy/async, không remote fetch. CSS max-width/auto height; fix gallery grid min-width0 trên320px. Không re-compress/xóa ảnh gốc, thêm CDN hoặc cache nguy hiểm cho stock/cart/checkout. Category/settings chỉ memoize trong request.

[Assets](phase-5/evidence/checks-assets.json):npm ci/test/build/audit PASS. Manifest,bundle raw/gzip sizes trong asset-metrics.json. Responsive/browser ghi console/network,không failed request sau fix.

Deferred: hosting/TLS/network latency,Core Web Vitals/Lighthouse,production load SLA,CDN/srcset,persistent cache. Trước multi-instance phải có shared sessions/locks và media lifecycle; tested target hiện một application instance.
