@props(['product'])
<article class="group min-w-0">
<a href="{{ $product['url'] }}" class="relative block overflow-hidden rounded-xl bg-[#eeeae3] aspect-[4/5]">
<img src="{{ $product['image'] }}" alt="{{ $product['alt'] }}" width="600" height="750" loading="lazy" decoding="async" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
@if($product['featured'])<span class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[10px] tracking-widest uppercase text-forest">Được yêu thích</span>@endif
<span class="absolute bottom-3 right-3 flex h-9 w-9 items-center justify-center rounded-full bg-white text-forest transition group-hover:bg-forest group-hover:text-white"><x-store.icon name="arrow" :size="16"/></span>
</a>
<div class="pt-4 space-y-2"><h3 class="font-medium text-sm sm:text-base leading-snug"><a href="{{ $product['url'] }}" class="block hover:text-clay">{{ $product['name'] }}</a></h3>
<x-store.price :price="$product['price']" :prefix="$product['price_prefix']"/>
<p class="text-[11px] text-stone-500">{{ $product['price']['price'] === null ? 'Liên hệ để được tư vấn' : ($product['available'] > 0 ? 'Có sẵn để đặt hàng' : 'Tạm hết hàng') }}</p></div>
</article>
