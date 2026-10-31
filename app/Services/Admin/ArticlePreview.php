<?php

namespace App\Services\Admin;

use App\Admin\StoreResources;
use App\Models\Article;
use App\Services\Storefront\Catalog;
use App\Services\Storefront\ContentHtml;
use App\Services\Storefront\Seo;

final class ArticlePreview
{
    public function response(Article $article)
    {
        $catalog = app(Catalog::class);
        $catalog->reset();
        $article->load('category');
        $seo = app(Seo::class)->page($article->title, route('store.articles.show', $article->slug, false), $article->meta_description ?: $article->excerpt, true);
        if ($article->meta_title) {
            $seo['title'] = $article->meta_title;
        }

        return response()->view('articles.show', ['article' => $article, 'seo' => $seo, 'store' => $catalog->layout(),
            'preview' => true, 'previewBack' => StoreResources::url('content', 'articles/'.$article->id),
            'crumbs' => [], 'related' => collect(), 'image' => $catalog->display->image($article->image),
            'contentHtml' => app(ContentHtml::class)->render($article->content),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
