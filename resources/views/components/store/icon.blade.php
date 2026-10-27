@props(['name', 'size' => 20])
<svg {{ $attributes }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>@break
@case('bag')<path d="M5 7h14l1 14H4L5 7Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/>@break
@case('arrow')<path d="M4 12h16m-6-6 6 6-6 6"/>@break
@case('leaf')<path d="M20 3C9 1 2 7 5 15c8 5 17-1 15-12Z"/><path d="M3 21 16 8"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('close')<path d="m6 6 12 12M6 18 18 6"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@case('phone')<path d="m5 3 4 4-2 3a16 16 0 0 0 7 7l3-2 4 4c-2 6-8 2-12-2S1 5 5 3Z"/>@break
@default<path d="m9 5 7 7-7 7"/>
@endswitch
</svg>
