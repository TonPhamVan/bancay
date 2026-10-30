@if($paginator->hasPages())
<nav role="navigation" aria-label="Điều hướng phân trang" class="flex flex-wrap items-center justify-between gap-5">
<p class="text-sm text-stone-500">Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} kết quả</p>
<ul class="flex flex-wrap items-center gap-1">
<li>@if($paginator->onFirstPage())<span aria-disabled="true" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-stone-200 px-3 text-sm text-stone-400">Trước</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-10 items-center justify-center rounded-md border border-stone-300 px-3 text-sm hover:bg-sage">Trước</a>@endif</li>
@foreach($elements as $element)
@if(is_string($element))<li><span aria-disabled="true" class="inline-flex min-h-10 min-w-10 items-center justify-center text-stone-500">{{ $element }}</span></li>@else
@foreach($element as $page=>$url)
<li>@if($page===$paginator->currentPage())<span aria-current="page" aria-label="Trang {{ $page }}" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md bg-forest px-2 text-sm font-semibold text-white">{{ $page }}</span>@else<a href="{{ $url }}" aria-label="Trang {{ $page }}" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-stone-300 px-2 text-sm hover:bg-sage">{{ $page }}</a>@endif</li>
@endforeach
@endif
@endforeach
<li>@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-10 items-center justify-center rounded-md border border-stone-300 px-3 text-sm hover:bg-sage">Sau</a>@else<span aria-disabled="true" class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-stone-200 px-3 text-sm text-stone-400">Sau</span>@endif</li>
</ul></nav>
@endif
