<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Catalog;
use App\Services\Storefront\Seo;

final class ContactController extends Controller
{
    public function __invoke(Catalog $catalog, Seo $seo)
    {
        $settings = $catalog->settings();

        return view('home.contact', ['seo' => $seo->page('Liên hệ', '/lien-he'),
            'mapUrl' => $catalog->safeLink($settings['map'] ?? null), 'facebookUrl' => $catalog->safeLink($settings['facebook'] ?? null)]);
    }
}
