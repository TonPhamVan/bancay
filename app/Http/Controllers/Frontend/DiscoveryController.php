<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Catalog;
use App\Services\Storefront\Seo;

final class DiscoveryController extends Controller
{
    public function robots(Seo $seo)
    {
        $body = config('storefront.indexable')
            ? "User-agent: *\nDisallow: /admin\nDisallow: /gio-hang\nDisallow: /thanh-toan\nDisallow: /don-hang/\nDisallow: /tim-kiem\nSitemap: ".$seo->absolute('/sitemap.xml')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }

    public function sitemap(Catalog $catalog, Seo $seo)
    {
        $catalog->reset();
        $paths = ['/', '/san-pham', '/bai-viet', '/lien-he', '/tu-van-canh-quan'];
        foreach ($catalog->categories() as $row) {
            $paths[] = route('store.categories.show', $row->slug, false);
        }
        foreach ($catalog->articleCategories() as $row) {
            $paths[] = route('store.article-categories.show', $row->slug, false);
        }
        // No images/variants are needed for discovery. Cursor bounds memory for a larger catalog.
        foreach (['store.products.show' => $catalog->products(), 'store.articles.show' => $catalog->articles()] as $route => $query) {
            foreach ($query->withoutEagerLoads()->select('slug')->cursor() as $row) {
                $paths[] = route($route, $row->slug, false);
            }
        }
        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach (array_unique($paths) as $path) {
            $xml->startElement('url');
            $xml->writeElement('loc', $seo->absolute($path));
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }
}
