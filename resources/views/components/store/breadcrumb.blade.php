@props(['items' => []])
@if(count($items))
<nav aria-label="Đường dẫn" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-500 py-6">
@foreach($items as $item)
@unless($loop->first)<span aria-hidden="true">/</span>@endunless
@if($loop->last)<span class="text-forest" aria-current="page">{{ $item['name'] }}</span>@else<a class="hover:underline" href="{{ $item['url'] }}">{{ $item['name'] }}</a>@endif
@endforeach
</nav>
@endif
