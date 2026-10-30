@extends('layouts.store')
@section('content')
<div class="wrap pt-8 sm:pt-12">
<x-store.breadcrumb :items="[['name'=>'Trang chủ','url'=>'/'],['name'=>'Tư vấn cảnh quan','url'=>null]]"/>
<section class="grid gap-8 lg:grid-cols-2 lg:gap-14 items-center py-5 sm:py-9">
<div><p class="eyebrow">Cây xanh theo không gian của bạn</p><h1 class="font-display text-4xl sm:text-5xl leading-tight mt-4">Tư vấn thiết kế<br>cây xanh & cảnh quan.</h1><p class="mt-6 text-stone-600 leading-8">Từ một góc ban công đến không gian làm việc, cùng {{ $store['name'] }} trao đổi cách chọn cây, chậu và bố trí mảng xanh phù hợp với nhu cầu sử dụng.</p><p class="mt-4 text-sm text-stone-600 leading-7">Tiếp nhận nhu cầu cây nội thất, cây văn phòng và cảnh quan nhỏ tại Hà Nội. Phạm vi giao hàng, khảo sát và hạng mục cụ thể được trao đổi trước khi thống nhất phương án.</p><a class="btn mt-7" href="#nhan-tu-van">Nhận tư vấn thiết kế <x-store.icon name="arrow" :size="17"/></a></div>
<figure><img src="{{ asset($ideas[0]['image']) }}" alt="{{ $ideas[0]['alt'] }}" width="{{ $ideas[0]['width'] }}" height="{{ $ideas[0]['height'] }}" class="w-full aspect-[4/3] object-cover object-[center_75%] rounded-2xl"><figcaption class="text-xs text-stone-500 mt-3">Ảnh ý tưởng minh họa; không phải hồ sơ công trình đã thi công.</figcaption></figure>
</section>
<section class="section border-t border-stone-200" aria-labelledby="landscape-ideas-title"><p class="eyebrow">Tham khảo cách phối cây & tiểu cảnh</p><h2 id="landscape-ideas-title" class="section-title mt-3">Ý tưởng cho góc xanh của bạn.</h2><p class="text-sm text-stone-600 leading-7 mt-4 max-w-3xl">Gợi ý kết hợp cây xanh, chậu và tiểu cảnh cho ban công hoặc góc nhà. Các hình dưới đây là ảnh ý tưởng minh họa, không phải ảnh công trình cửa hàng đã thực hiện.</p>
<div class="grid gap-6 sm:grid-cols-3 mt-7 items-start">
@foreach($ideas as $idea)
<figure class="overflow-hidden rounded-2xl border border-stone-200 bg-white"><img src="{{ asset($idea['image']) }}" alt="{{ $idea['alt'] }}" width="{{ $idea['width'] }}" height="{{ $idea['height'] }}" loading="lazy" decoding="async" class="w-full h-auto"><figcaption class="p-5"><span class="text-xs text-stone-500">Ảnh ý tưởng minh họa</span><h3 class="font-display text-xl mt-2">{{ $idea['title'] }}</h3></figcaption></figure>
@endforeach
</div></section>
<section class="section border-t border-stone-200"><p class="eyebrow">Bạn đang cần một góc xanh như thế nào?</p><h2 class="section-title mt-3 mb-7">Bắt đầu từ nhu cầu thực tế.</h2><div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
@foreach(['Cây xanh văn phòng'=>'Trao đổi về cây nội thất, vị trí bố trí và nhu cầu chăm sóc trong không gian làm việc.', 'Ban công & góc nhà'=>'Gợi ý cách kết hợp cây, chậu và khoảng trống theo diện tích, ánh sáng và thói quen sử dụng.', 'Tiểu cảnh & sân vườn nhỏ'=>'Trao đổi ý tưởng cho mảng xanh, chậu cây và các điểm nhấn trong không gian.', 'Hệ thống tưới'=>'Tiếp nhận thông tin khu vực trồng và nhu cầu tưới để tư vấn lựa chọn phù hợp.', 'Set cây & chậu'=>'Trao đổi cách phối cây, chậu và phụ kiện theo vị trí đặt hoặc nhu cầu làm quà.', 'Chọn cây theo điều kiện thực tế'=>'Chia sẻ mức sáng, diện tích và thời gian chăm sóc để cùng tìm hướng bố trí phù hợp.'] as $title=>$description)
<article class="panel"><x-store.icon name="leaf" :size="24" class="text-forest"/><h3 class="font-display text-2xl mt-4">{{ $title }}</h3><p class="mt-3 text-sm leading-7 text-stone-600">{{ $description }}</p></article>
@endforeach
</div></section>
<section id="nhan-tu-van" class="panel bg-sage scroll-mt-5"><div class="grid lg:grid-cols-2 gap-7"><div><p class="eyebrow">Kết nối với {{ $store['name'] }}</p><h2 class="section-title mt-3">Nhận tư vấn cho không gian của bạn.</h2><p class="text-sm text-stone-600 leading-7 mt-4">Bạn có thể chuẩn bị ảnh hiện trạng, khu vực cần bố trí, diện tích ước tính và nhu cầu chính. Gửi những thông tin này khi trao đổi trực tiếp để cửa hàng có thêm cơ sở tư vấn.</p></div><div class="flex flex-col items-start justify-center gap-4">
@if($store['links']['phone'])<a class="btn" href="{{ $store['links']['phone'] }}"><x-store.icon name="phone" :size="18"/> Gọi tư vấn: {{ $store['settings']['contact_phone'] }}</a>@endif
@if($store['links']['facebook'])<a class="btn btn-outline" href="{{ $store['links']['facebook'] }}" rel="noopener noreferrer">Nhắn Facebook để tư vấn ↗</a>@endif
@if(!$store['links']['phone'] && !$store['links']['facebook'])<a class="btn" href="{{ route('store.contact') }}">Xem thông tin liên hệ <x-store.icon name="arrow" :size="17"/></a>@endif
<p class="text-xs text-stone-500 leading-6">Phương án, hạng mục, chi phí và lịch thực hiện được trao đổi theo nhu cầu cụ thể. Bạn chưa gửi đơn hàng hoặc thanh toán tại trang này.</p></div></div></section>
</div>
@endsection
