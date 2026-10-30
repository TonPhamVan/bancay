@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>{{ $record->name }} <small>#{{ $record->id }}</small></h2>
<nav class="store-actions" aria-label="Các phần sản phẩm"><a href="#product-basic">Thông tin cơ bản / SEO</a><a href="#images">Hình ảnh</a><a href="#options">Nhóm phân loại</a><a href="#variants">Thông tin bán hàng</a></nav>
<div class="store-actions"><a class="btn btn-primary" href="{{ R::url('catalog','products/'.$record->id.'/edit') }}">Sửa thông tin / SEO</a><a href="{{ R::url('catalog','products') }}">Danh sách sản phẩm</a></div>
<div class="store-card" id="product-basic"><h3>Thông tin cơ bản</h3><p>{{ $record->category->name }} · {{ $record->slug }} · {{ $record->status->value }}</p><p style="white-space:pre-wrap">{{ strip_tags($record->short_description ?? '') }}</p><details><summary>Nội dung và SEO</summary><p style="white-space:pre-wrap">{{ $record->description }}</p><p>{{ $record->meta_title }}<br>{{ $record->meta_description }}</p></details></div>
<section class="store-card" id="images"><h3>Ảnh sản phẩm</h3>
<form method="post" enctype="multipart/form-data" action="{{ R::url('catalog','products/'.$record->id.'/actions/upload') }}">@csrf
<label for="product_images">Thêm ảnh (tối đa 10 ảnh/lần, JPEG/PNG/WebP, 5 MB/ảnh)</label><input id="product_images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" required><button class="btn btn-success">Tải ảnh lên</button></form>
<div class="store-grid">
@foreach($record->images as $image)
<div class="store-card"><img style="max-width:100%;height:140px;object-fit:contain" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt_text }}">
<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/image') }}">@csrf<input type="hidden" name="image_id" value="{{ $image->id }}">
<label for="alt_{{ $image->id }}">Alt text</label><input id="alt_{{ $image->id }}" name="alt_text" value="{{ $image->alt_text }}" maxlength="255">
<label for="sort_{{ $image->id }}">Thứ tự ảnh</label><input type="number" min="0" id="sort_{{ $image->id }}" name="sort_order" value="{{ $image->sort_order }}">
<input type="hidden" name="is_primary" value="0"><label><input type="checkbox" name="is_primary" value="1" @checked($image->is_primary)> Ảnh chính</label><button class="btn btn-primary">Lưu ảnh</button></form>
<details><summary>Xóa ảnh</summary><form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/delete-image') }}">@csrf<input type="hidden" name="image_id" value="{{ $image->id }}"><label><input type="checkbox" name="confirm" value="1" required> Xác nhận xóa ảnh</label><button class="btn btn-danger">Xóa ảnh</button></form></details>
</div>@endforeach</div></section>
<section class="store-card" id="options"><h3>Thuộc tính / Options</h3><p class="help">Mỗi giá trị trên một dòng. Giá trị đang dùng bởi SKU được bảo vệ. Cấu trúc nhóm thuộc tính được giữ ổn định sau khi tạo SKU.</p>
@foreach($record->options as $option)
<details><summary>{{ $option->name }} · {{ $option->values->pluck('value')->join(' / ') }}</summary>
<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/option') }}">@csrf<input type="hidden" name="option_id" value="{{ $option->id }}">
<label>Tên nhóm<input name="name" value="{{ $option->name }}" required maxlength="100"></label><label>Thứ tự nhóm<input type="number" name="sort_order" min="0" value="{{ $option->sort_order }}" required></label>
<label>Giá trị<textarea name="values" required>{{ $option->values->pluck('value')->join("\n") }}</textarea></label><button class="btn btn-primary">Lưu nhóm</button></form>
@if($record->variants->isEmpty())<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/delete-option') }}">@csrf<input type="hidden" name="option_id" value="{{ $option->id }}"><label><input type="checkbox" name="confirm" value="1" required> Xác nhận xóa nhóm và các giá trị</label><button class="btn btn-danger">Xóa nhóm</button></form>@endif
</details>@endforeach
@if($record->variants->isEmpty())
<details><summary>Thêm nhóm thuộc tính</summary><form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/option') }}">@csrf
<label for="option_name">Tên thuộc tính</label><input id="option_name" name="name" required maxlength="100"><label for="option_sort">Thứ tự</label><input id="option_sort" name="sort_order" type="number" min="0" value="{{ $record->options->count() }}" required>
<label for="option_values">Giá trị (mỗi dòng một giá trị)</label><textarea id="option_values" name="values" required></textarea><button class="btn btn-success">Thêm thuộc tính</button></form></details>
@endif</section>
<section class="store-card" id="variants"><h3>Thông tin bán hàng / SKU</h3>
@include('admin.store.variant-matrix')
<details><summary>Sinh các tổ hợp còn thiếu</summary><form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/generate') }}">@csrf
<label for="sku_prefix">Tiền tố SKU</label><input id="sku_prefix" name="sku_prefix" required maxlength="50"><p class="help">Tối đa 256 tổ hợp. SKU mới: INACTIVE, Liên hệ, tồn 0. SKU/giá/tồn đã có được giữ nguyên.</p>
<label><input type="checkbox" name="confirm" value="1" required> Xác nhận tạo các tổ hợp còn thiếu</label><button class="btn btn-primary">Sinh SKU</button></form></details>
<details><summary>Tạo một SKU</summary>@include('admin.store.variant-form', ['variant'=>null])</details>
<noscript><details id="single-sku-editor"><summary>Chỉnh sửa từng SKU</summary>
@forelse($record->variants as $variant)
<details><summary>{{ $variant->sku }} · {{ $variant->optionValues->pluck('value')->join(' / ') ?: 'Mặc định' }} · {{ $variant->status->value }} · {{ $variant->effectivePrice() === null ? 'Liên hệ' : \App\Support\IntegerDisplay::format($variant->effectivePrice()).' VND' }} · Khả dụng {{ $variant->inventory?->available() ?? 0 }}</summary>
@include('admin.store.variant-form', ['variant'=>$variant])
@if($variant->inventory)<div class="store-actions"><a href="{{ R::url('catalog','inventory/'.$variant->inventory->id) }}">Điều chỉnh tồn kho</a><span>Thực tế {{ $variant->inventory->quantity }} / Giữ chỗ {{ $variant->inventory->reserved_quantity }}</span></div>@endif
</details>@empty<p>Chưa có SKU. Tạo thuộc tính trước nếu sản phẩm có nhiều lựa chọn.</p>@endforelse
</details></noscript>
</section>
<section class="store-card">@include('admin.store.remove', ['deleteUrl'=>R::url('catalog','products/'.$record->id), 'deleteLabel'=>'Lưu trữ và xóa mềm sản phẩm'])<p class="help">SKU, tồn kho và snapshot đơn hàng được giữ lại.</p></section>
@endsection
