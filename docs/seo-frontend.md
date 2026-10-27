# SEO public frontend

Ngày:28/10/2026. [Phase0 URL inventory](phase-0/seo.md) là nguồn, không crawl/cutover production trong Phase3.

## Metadata

Seo service xây title/meta description/canonical/Open Graph và JSON-LD; layout chỉ render dữ liệu đã chuẩn bị. Entity meta_title/meta_description ưu tiên; title fallback entity title + site meta_title/site_name, description strip HTML và giới hạn300 ký tự, sau đó fallback settings/name. Product dùng cover image; article dùng featured image; nếu thiếu, fallback settings logo khi có. Không có OG image URL nếu không có nguồn phù hợp.

Canonical origin từ **APP_URL**, không lấy HTTP Host hoặc query redirect của browser. Entity URL dùng slug unique hiện tại. Listing page>1 canonical giữ `?page=N`; sort/query variants canonical về listing path tương ứng, noindex. Search private SEO `noindex,nofollow`; cart/checkout/success luôn noindex,nofollow. Local/staging mặc định `STOREFRONT_INDEXABLE=false` nên public content noindex,follow. Chỉ cân nhắc bật index sau audit/production gates và review sitemap/robots; không tự bật flag trong Phase3.

Basic Open Graph gồm title/description/url/type/locale/image khi có. Product JSON-LD có name/description/image/url và AggregateOffer của ACTIVE FIXED variants; VND integer chuyển chuỗi decimal chính xác, low/high/count và availability dựa trên available stock. CONTACT-only không phát offers giá0. BreadcrumbList theo hierarchy visible; Article có headline/dates/mainEntityOfPage/image; homepage Organization dùng tên/URL settings. Không fake review/rating/brand/person author hoặc dữ liệu tồn kho không kiểm chứng.

JSON-LD encode bằng JSON_HEX_TAG/AMP/APOS/QUOT, nên nội dung `</script>` không thoát khỏi script inert. Metadata HTML dùng escaped Blade. Product/article/category rich HTML được Purifier xử lý; không gắn script/schema tùy ý do editor cung cấp.

## Legacy redirects

Generic resolver nhận `*-sp{id}.html`, `*-dm{id}.html`, `*-bv{id}.html`, `*-cm{id}.html`; tra legacy_id trên public scope, trả301 về current slug, target local. Alias cũ không phải nguồn authorization; entity ẩn/missing trả404, không redirect về home. `next`/external target không được sử dụng. `/tat-ca-san-pham?keyword=q&page=n` dịch keyword→q, zero-based n→n+1 bằng validation/binding.

**BUSINESS RULE CHANGE:** slug mới không bắt buộc suffix legacy ID vì schema đã unique slug. Không thay Phase0 original inventory để giả vờ đích draft đã hoàn tất. Phase4 thiết lập exact mapping/import và normalize aliases/encoding; Phase5 đối soát lựa chọn410/drop, target public, duplicate/loop/chain, old query semantics (đặc biệt category/article-category paging), embedded media và sitemap. Generic resolver hiện bỏ query entity cũ thay vì đoán phân trang tương đương. Implicit CI URLs/stale prefixes/media redirect chưa implement; có backlog rõ.

Sitemap/robots production và Search Console/analytics chưa được cấu hình. Google rich-result validation, crawler/index outcomes và external SEO audit **NOT TESTED** vì local synthetic không được public. Không coi JSON-LD đúng shape là bảo đảm rich results.

## Nguồn kỹ thuật

Thiết kế bundle theo [Alpine installation](https://alpinejs.dev/essentials/installation) và [Tailwind Vite installation](https://tailwindcss.com/docs/installation/using-vite); session blocking theo [Laravel10 session documentation](https://laravel.com/docs/10.x/session#session-blocking). Runtime và dependencies thực tế được kiểm riêng trong [evidence](phase-3/evidence/verification.json), không suy ra từ việc đọc tài liệu.
