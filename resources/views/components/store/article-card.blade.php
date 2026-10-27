@props(['article'])
<article class="min-w-0 group">
<a href="{{ $article['url'] }}" class="block aspect-[3/2] overflow-hidden rounded-xl bg-sage"><img src="{{ $article['image'] }}" alt="{{ $article['title'] }}" width="600" height="400" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105"></a>
<p class="mt-5 text-[10px] uppercase tracking-[.18em] text-stone-500">{{ $article['category'] }} <span class="mx-2">·</span> {{ $article['date'] }}</p>
<h3 class="mt-2 font-display text-2xl leading-tight"><a href="{{ $article['url'] }}" class="hover:text-clay">{{ $article['title'] }}</a></h3>
<p class="mt-3 text-sm leading-6 text-stone-600 line-clamp-2">{{ $article['excerpt'] }}</p>
<a href="{{ $article['url'] }}" class="text-link mt-4">Đọc câu chuyện <x-store.icon name="arrow" :size="16"/></a>
</article>
