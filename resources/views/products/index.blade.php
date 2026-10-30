@extends('layouts.store')
@section('content')
<div class="wrap"><x-store.breadcrumb :items="$crumbs"/>
<div class="pb-6 sm:pb-8 pt-4 border-b border-stone-200"><p class="eyebrow">Khám phá cửa hàng</p><h1 class="font-display text-4xl sm:text-5xl mt-4 leading-tight">{{ $title }}</h1>
@if(!empty($filters['q']))<p class="mt-4 text-stone-600">Kết quả cho “{{ $filters['q'] }}”</p>@endif
@if(!empty($description))
<details class="group mt-4" id="category-introduction">
<summary class="list-none rounded-sm [&::-webkit-details-marker]:hidden" aria-label="Giới thiệu danh mục" aria-controls="category-description">
<span class="line-clamp-2 max-w-4xl text-sm leading-7 text-stone-600 group-open:hidden">{{ $descriptionPreview }}</span>
<span class="text-link mt-3 group-open:hidden">Xem thêm giới thiệu <span aria-hidden="true">＋</span></span>
<span class="text-link hidden group-open:inline-flex">Thu gọn giới thiệu <span aria-hidden="true">−</span></span>
</summary>
<div id="category-description" class="article-body mt-5 text-sm">{!! $description !!}</div>
</details>
@endif</div>
@if($children->isNotEmpty())<nav aria-label="Danh mục con" class="flex flex-wrap gap-3 mt-6">@foreach($children as $child)<a class="rounded-full border border-stone-300 px-4 py-2 text-sm hover:bg-sage" href="{{ route('store.categories.show', $child->slug) }}">{{ $child->name }} ↗</a>@endforeach</nav>@endif
<div class="flex flex-wrap items-center justify-between gap-4 py-6"><p class="text-sm text-stone-500">{{ $products->total() }} sản phẩm</p><form method="get" class="flex items-center gap-3">@if(!empty($filters['q']))<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif<label for="sort" class="sr-only">Sắp xếp</label><select name="sort" id="sort" class="field text-sm w-40 sm:w-48"><option value="featured" @selected(($filters['sort'] ?? '') === 'featured')>Nổi bật</option><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Mới nhất</option><option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Giá tăng dần</option><option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Giá giảm dần</option></select><button class="text-link min-h-11 whitespace-nowrap shrink-0">Áp dụng</button></form></div>
<div class="card-grid">@forelse($products as $item)<x-store.product-card :product="$item"/>@empty<div class="col-span-full panel text-center py-16"><x-store.icon name="leaf" :size="40" class="mx-auto text-forest"/><h2 class="font-display text-3xl mt-5">Chưa tìm thấy sản phẩm phù hợp.</h2><p class="text-sm text-stone-500 mt-3">Không tìm thấy sản phẩm phù hợp. Bạn thử từ khóa khác hoặc khám phá các danh mục khác nhé.</p><a href="{{ route('store.products.index') }}" class="btn mt-6">Khám phá cửa hàng <x-store.icon name="arrow" :size="17"/></a></div>@endforelse</div><div class="mt-10">{{ $products->links('store.pagination') }}</div></div>
@endsection
