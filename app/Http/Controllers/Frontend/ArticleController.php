<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Catalog;
use App\Services\Storefront\ContentHtml;
use App\Services\Storefront\Seo;
use Illuminate\Http\Request;

final class ArticleController extends Controller
{
    public function __construct(private readonly Catalog $catalog, private readonly Seo $seo) {}

    public function index(Request $request, ?string $slug = null)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $category = $slug ? $this->catalog->articleCategories()->firstWhere('slug', $slug) : null;
        if ($slug) {
            abort_unless($category, 404);
        }
        $articles = $this->catalog->articles()->when($category, fn ($q) => $q->where('article_category_id', $category->id))->latest('id')->paginate(15)->withQueryString();
        $crumbs = $this->catalog->breadcrumbs($category?->id, true);
        $path = $category ? route('store.article-categories.show', $slug, false) : '/bai-viet';
        if ((int) $request->query('page', 1) > 1) {
            $path .= '?page='.(int) $request->query('page');
        }
        $seo = $this->seo->page($category?->name ?? 'Chuyện của lá', $path, $category?->meta_description, false, $crumbs);
        if ($category?->meta_title) {
            $seo['title'] = $category->meta_title;
        }
        $articles->through(fn ($a) => $this->catalog->display->articleCard($a));

        return view('articles.index', compact('articles', 'category', 'crumbs', 'seo'));
    }

    public function show(string $slug)
    {
        $article = $this->catalog->articles()->where('slug', $slug)->firstOrFail();
        $crumbs = $this->catalog->breadcrumbs($article->article_category_id, true);
        $crumbs[] = ['name' => $article->title, 'url' => route('store.articles.show', $slug, false)];
        $seo = $this->seo->article($article, $crumbs);
        $contentHtml = app(ContentHtml::class)->render($article->content ?? '');
        $image = $this->catalog->display->image($article->image);
        $related = $this->catalog->articles()->where('article_category_id', $article->article_category_id)->whereKeyNot($article->id)->limit(3)->get()->map(fn ($a) => $this->catalog->display->articleCard($a));

        return view('articles.show', compact('article', 'crumbs', 'seo', 'contentHtml', 'image', 'related'));
    }
}
