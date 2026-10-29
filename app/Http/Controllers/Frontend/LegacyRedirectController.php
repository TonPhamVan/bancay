<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Catalog;
use Illuminate\Http\Request;

final class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request, Catalog $catalog, string $legacy)
    {
        abort_unless(preg_match('/-(sp|dm|bv|cm)([0-9]{1,18})\.html\z/u', $legacy, $match), 404);
        $id = (int) $match[2];
        $target = match ($match[1]) {
            'sp' => $catalog->products()->where('legacy_id', $id)->first(),
            'dm' => $catalog->categories()->firstWhere('legacy_id', $id),
            'bv' => $catalog->articles()->where('legacy_id', $id)->first(),
            'cm' => $catalog->articleCategories()->firstWhere('legacy_id', $id),
        };
        abort_unless($target, 404);
        $route = ['sp' => 'store.products.show', 'dm' => 'store.categories.show', 'bv' => 'store.articles.show', 'cm' => 'store.article-categories.show'][$match[1]];

        $parameters = ['slug' => $target->slug];
        if (in_array($match[1], ['dm', 'cm'], true)) {
            $data = $request->validate(['page' => ['nullable', 'integer', 'min:0', 'max:99999']]);
            if (isset($data['page']) && (int) $data['page'] > 0) {
                $parameters['page'] = (int) $data['page'] + 1;
            }
        }

        return redirect(route($route, $parameters, false), 301);
    }

    public function listing(Request $request)
    {
        $data = $request->validate(['keyword' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:0', 'max:99999']]);

        return redirect()->route(empty($data['keyword']) ? 'store.products.index' : 'store.search',
            array_filter(['q' => $data['keyword'] ?? null, 'page' => isset($data['page']) ? $data['page'] + 1 : null], fn ($v) => $v !== null), 301);
    }
}
