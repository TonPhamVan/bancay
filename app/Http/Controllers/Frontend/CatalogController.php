<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Catalog;
use App\Services\Storefront\ContentHtml;
use App\Services\Storefront\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CatalogController extends Controller
{
    public function __construct(private readonly Catalog $catalog, private readonly Seo $seo) {}

    public function home()
    {
        $featured = $this->catalog->products()->where('featured', true)->latest('id')->limit(4)->get()->map(fn ($p) => $this->catalog->display->card($p));
        $latest = $this->catalog->products()->latest('id')->limit(4)->get()->map(fn ($p) => $this->catalog->display->card($p));
        $categories = $this->catalog->orderedCategories()->where('featured', true)->take(4)->map(fn ($c) => [
            'name' => $c->name, 'url' => route('store.categories.show', $c->slug, false), 'image' => $this->catalog->display->image($c->image)]);
        $banners = $this->catalog->banners('home_slider');
        $secondary = $this->catalog->banners('home_secondary');
        $departments = $this->catalog->departmentBlocks();
        $articles = $this->catalog->articles()->latest('id')->limit(3)->get()->map(fn ($a) => $this->catalog->display->articleCard($a));
        $seo = $this->seo->page('Góc xanh của bạn', '/');
        $seo['structured'][] = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $this->catalog->layout()['name'], 'url' => $this->seo->absolute('/')];

        return view('home.index', compact('featured', 'latest', 'categories', 'banners', 'secondary', 'departments', 'articles', 'seo'));
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $products = $this->catalog->listing($filters);
        $seo = $this->seo->page('Tất cả sản phẩm', $this->pagePath('/san-pham', $request), null, ! empty($filters['sort']));

        return view('products.index', ['products' => $products, 'title' => 'Tìm một chút xanh cho mình', 'seo' => $seo, 'filters' => $filters, 'category' => null, 'children' => collect(), 'crumbs' => [['name' => 'Trang chủ', 'url' => '/']]]);
    }

    public function search(Request $request)
    {
        $filters = $this->filters($request);
        $products = $this->catalog->listing($filters);
        $seo = $this->seo->page('Tìm kiếm sản phẩm', '/tim-kiem', null, true);

        return view('products.index', ['products' => $products, 'title' => 'Kết quả tìm kiếm', 'seo' => $seo, 'filters' => $filters, 'category' => null, 'children' => collect(), 'crumbs' => [['name' => 'Trang chủ', 'url' => '/']]]);
    }

    public function category(Request $request, string $slug)
    {
        $category = $this->catalog->categories()->firstWhere('slug', $slug);
        abort_unless($category, 404);
        $filters = $this->filters($request);
        $products = $this->catalog->listing($filters, $category->id);
        $crumbs = $this->catalog->breadcrumbs($category->id);
        $seo = $this->seo->page($category->name, $this->pagePath(route('store.categories.show', $slug, false), $request), $category->meta_description ?: $category->description, ! empty($filters['sort']), $crumbs);
        if ($category->meta_title) {
            $seo['title'] = $category->meta_title;
        }
        $description = app(ContentHtml::class)->render($category->description ?? '');
        $descriptionPreview = $this->catalog->display->excerpt($description);

        return view('products.index', ['pageBanners' => $this->catalog->bannerSlots($category->id), 'products' => $products, 'title' => $category->name, 'seo' => $seo, 'filters' => $filters,
            'category' => $category, 'description' => $description, 'descriptionPreview' => $descriptionPreview,
            'children' => $this->catalog->categories()->where('parent_id', $category->id), 'crumbs' => $crumbs]);
    }

    public function show(string $slug)
    {
        $product = $this->catalog->product($slug);
        $picker = $this->catalog->picker($product);
        $crumbs = $this->catalog->breadcrumbs($product->category_id);
        $crumbs[] = ['name' => $product->name, 'url' => route('store.products.show', $slug, false)];
        $seo = $this->seo->product($product, $crumbs);
        $gallery = $product->images->map(fn ($i) => ['url' => $this->catalog->display->image($i->path), 'alt' => $i->alt_text ?: $product->name]);
        $related = $this->catalog->products()->where('category_id', $product->category_id)->whereKeyNot($product->id)->limit(4)->get()->map(fn ($p) => $this->catalog->display->card($p));

        return view('products.show', compact('product', 'picker', 'crumbs', 'seo', 'gallery', 'related') + [
            'pageBanners' => $this->catalog->bannerSlots($product->category_id),
            'shortHtml' => app(ContentHtml::class)->render($product->short_description ?? ''), 'contentHtml' => app(ContentHtml::class)->render($product->description ?? '')]);
    }

    public function filters(Request $request): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:120'], 'sort' => ['nullable', Rule::in(['featured', 'newest', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
    }

    private function pagePath(string $path, Request $request): string
    {
        return (int) $request->query('page', 1) > 1 ? $path.'?page='.(int) $request->query('page') : $path;
    }
}
