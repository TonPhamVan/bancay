> Current [release recheck](release-readiness/recheck-54a0a2f/report.md#7-seo-fixes): [201 actual legacy redirects / 221 sitemap targets](release-readiness/recheck-54a0a2f/seo.json) and [201 source meta comparisons](release-readiness/recheck-54a0a2f/migration-seo.json) PASS; no SEO source fix needed. Local canonical/robots/sitemap/assets verified; actual production ingress/HTTPS/APP_URL still P5-B02. Prior scan evidence below is preserved.

> Latest [release review](release-readiness-report.md):fresh201 redirects/221 targets andsource metadata/assets checks PASS on current build. No SEO rewrite needed. Actual DirectAdmin ingress/HTTPS normalization remains unverified;no DNS/cutover. Prior snapshot evidence below preserved.

# Phase 5 — SEO preservation

Baseline da70bd4, Exment7/Laravel12; Phase5 chưa commit. [HTTP crawl](phase-5/evidence/seo.json), [URL map](phase-5/evidence/legacy-url-map.csv), [source parity](phase-5/evidence/migration-seo.json).

Kiểm toàn bộ201 URLs:115 products,8 categories,67 articles,11 article categories. Nguồn chỉ có8 product categories nên kiểm cả8. Dạng percent-encoded kèm tracking/next query trả301 trực tiếp đúng slug, target200, không loop/chain trong application. Title/slug/meta so đủ201 source records,0 mismatch. Ảnh chuyển định dạng/alias được xác minh bằng reconciliation,copied-media hashes và browser, không so hash WebP với JPEG gốc.

Resolver chỉ chuyển entity được public. Missing/hidden trả404; chưa có bằng chứng xóa vĩnh viễn để trả410; không chuyển tất cả về home. Category/article-category page cũ zero-based chuyển page+1, page0 bỏ query. Product/article bỏ tracking/next. Legacy listing dịch keyword→q và page+1. Query invalid trả validation error. HTTPS/www normalization qua hosting thật cần Phase6 kiểm lại; hop count hiện chỉ được chứng nhận ở application.

Sitemap mới dùng đúng publication/ancestor scope của storefront, gồm home,listings,contact,categories,article categories,published products/articles. Loại draft,future,hidden ancestors,deleted products,cart,checkout,admin,search. XMLWriter escape đúng; không bịa lastmod/priority. Cursor không load gallery/variants. Dataset nhỏ chưa cần sitemap index.

Canonical từ APP_URL, entity self-canonical; page2 giữ?page=2. Sort/search/cart/checkout/success noindex. Meta/title/description/OG/H1 được crawl trên toàn sitemap. Product/BreadcrumbList/Article/Organization JSON-LD parse được; offers chỉ ACTIVE/FIXED, giá và availability từ server; CONTACT không offer0; không rating/review giả. Google Rich Results/Search Console và index outcomes NOT TESTED trên public domain.

Thay robots.txt tĩnh allow-all bằng endpoint theo STOREFRONT_INDEXABLE. Local/staging Disallow:/ kèm X-Robots-Tag noindex. Production chỉ mở sau Phase6 gates, chặn admin/cart/checkout/order/search và khai báo sitemap. Robots không phải access control; remote staging cần IP allowlist/basic auth. Chưa bật index ở môi trường hiện tại.

Crawl internal links/assets không thấy broken links.413 HTML fields main không có old-domain,script/object/embed,on-event,iframe hoặc old upload src; không có anchor cũ cần rewrite. Không rewrite stored content hàng loạt. ContentHtml sanitize trước render và bổ sung intrinsic dimensions/lazy/async cho ảnh local đã validate, không fetch URL editor. Gallery/logo có dimensions. Publication/settings/demo/source đều được bảo toàn; production merchandising/branding review thuộc P5-B03.

Nguồn: [Google site moves](https://developers.google.com/search/docs/crawling-indexing/site-move-with-url-changes), [canonical](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls), [pagination](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading).
