@props(['price', 'prefix' => ''])
<div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
<span class="font-semibold text-forest">{{ $prefix }}{{ $price['formatted'] }}</span>
@if($price['regular'])<del class="text-xs text-stone-500">{{ $price['regular'] }}</del>@endif
@if($price['discount'])<span class="text-[11px] text-clay">{{ $price['discount'] }}</span>@endif
</div>
