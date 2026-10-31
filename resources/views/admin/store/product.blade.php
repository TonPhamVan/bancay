@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>{{ $record->name }} <small>#{{ $record->id }}</small></h2>
<nav class="store-actions" aria-label="Các phần sản phẩm"><a href="#product-basic">Thông tin cơ bản / SEO</a><a href="#images">Hình ảnh</a><a href="#options">Nhóm phân loại</a><a href="#variants">Thông tin bán hàng</a></nav>
<div class="store-actions"><a class="btn btn-primary" href="{{ R::url('catalog','products/'.$record->id.'/edit') }}">Sửa thông tin / SEO</a><a class="btn btn-default" target="_blank" rel="noopener" href="{{ R::url('catalog','products/'.$record->id).'?preview=1' }}">Xem trước trên website</a><a href="{{ R::url('catalog','products') }}">Danh sách sản phẩm</a></div>
<section class="store-card" aria-labelledby="readiness-heading"><h3 id="readiness-heading">Kiểm tra trước khi đăng bán</h3>
<p><strong>{{ $readiness['visible'] ? 'Đang hiển thị trên website' : 'Chưa hiển thị trên website' }}</strong> · {{ $readiness['buyable'] ? 'Có SKU đủ giá và tồn để đặt hàng khi công khai.' : 'Chưa có SKU có thể đặt hàng; SKU Liên hệ hoặc hết hàng vẫn được hiển thị.' }}</p>
<ul class="store-checklist">@foreach($readiness['checks'] as $check)<li><span class="{{ $check['ok'] ? 'text-success' : 'text-warning' }}">{{ $check['ok'] ? '✓ Đạt' : '! Cần kiểm tra' }}</span> — {{ $check['label'] }}</li>@endforeach</ul>
<p class="help">Đây là hướng dẫn kiểm tra, không tự đổi trạng thái. Xem trước chỉ dành cho người có quyền quản lý sản phẩm và không cho đặt hàng.</p></section>
<div class="store-card" id="product-basic"><h3>Thông tin cơ bản</h3><p>{{ $record->category->name }} · {{ $record->slug }} · {{ \App\Admin\StoreLabels::status($record->status) }}</p><p style="white-space:pre-wrap">{{ strip_tags($record->short_description ?? '') }}</p><details><summary>Nội dung và SEO</summary><p style="white-space:pre-wrap">{{ $record->description }}</p><p>{{ $record->meta_title }}<br>{{ $record->meta_description }}</p></details></div>
<section class="store-card" id="images"><h3>Ảnh sản phẩm</h3>
<form method="post" enctype="multipart/form-data" action="{{ R::url('catalog','products/'.$record->id.'/actions/upload') }}">@csrf
<label for="product_images">Thêm ảnh (tối đa 10 ảnh/lần, JPEG/PNG/WebP, 5 MB/ảnh)</label><input id="product_images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" required><button class="btn btn-success">Tải ảnh lên</button></form>
@include('admin.store.image-batch')
</section>
<section class="store-card" id="options"><h3>Nhóm phân loại hàng</h3><p class="help">Ví dụ: Kích thước → Nhỏ, Lớn; Màu chậu → Trắng, Nâu. Mỗi giá trị trên một dòng. Giá trị đang dùng bởi SKU được bảo vệ. Cấu trúc nhóm thuộc tính được giữ ổn định sau khi tạo SKU.</p>
@foreach($record->options as $option)
@php $retry = old('option_form') === $record->id.':'.$option->id; @endphp
<details @if($retry) open @endif><summary>{{ $option->name }} · {{ $option->values->pluck('value')->join(' / ') }}</summary>
<form method="post" data-option-group data-watch-unsaved @if($retry) data-unsaved-initial="true" @endif action="{{ R::url('catalog','products/'.$record->id.'/actions/option') }}">@csrf<input type="hidden" name="option_id" value="{{ $option->id }}">
<label>Tên nhóm<input name="name" value="{{ $retry ? \App\Admin\StoreLabels::text(old('name')) : $option->name }}" required maxlength="100" @if($retry && $errors->has('name')) autofocus aria-invalid="true" @endif></label><label>Thứ tự nhóm<input type="number" name="sort_order" min="0" value="{{ $retry ? \App\Admin\StoreLabels::text(old('sort_order')) : $option->sort_order }}" required @if($retry && !$errors->has('name') && $errors->has('sort_order')) autofocus aria-invalid="true" @endif></label>
@include('admin.store.option-values',['valueKey'=>$option->id,'optionText'=>$retry ? \App\Admin\StoreLabels::lines(old('values')) : $option->values->pluck('value')->join("\n")])
@if($retry)@include('admin.store.option-errors')@endif
<button class="btn btn-primary">Lưu nhóm</button></form>
@if($record->variants->isEmpty())<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/delete-option') }}">@csrf<input type="hidden" name="option_id" value="{{ $option->id }}"><label><input type="checkbox" name="confirm" value="1" required> Xác nhận xóa nhóm và các giá trị</label><button class="btn btn-danger">Xóa nhóm</button></form>@endif
</details>@endforeach
@if($record->variants->isEmpty())
@php $retry = old('option_form') === $record->id.':new'; @endphp
<details @if($retry) open @endif><summary>Thêm nhóm phân loại</summary><form method="post" data-watch-unsaved @if($retry) data-unsaved-initial="true" @endif action="{{ R::url('catalog','products/'.$record->id.'/actions/option') }}">@csrf
<label for="option_name">Tên nhóm</label><input id="option_name" name="name" value="{{ $retry ? \App\Admin\StoreLabels::text(old('name')) : '' }}" required maxlength="100" @if($retry && $errors->has('name')) autofocus aria-invalid="true" @endif><label for="option_sort">Thứ tự</label><input id="option_sort" name="sort_order" type="number" min="0" value="{{ $retry ? \App\Admin\StoreLabels::text(old('sort_order')) : $record->options->count() }}" required @if($retry && !$errors->has('name') && $errors->has('sort_order')) autofocus aria-invalid="true" @endif>
@include('admin.store.option-values',['valueKey'=>'new','optionText'=>$retry ? \App\Admin\StoreLabels::lines(old('values')) : ''])
@if($retry)@include('admin.store.option-errors')@endif
<button class="btn btn-success">Thêm nhóm phân loại</button></form></details>
@endif</section>
<section class="store-card" id="variants"><h3>Thông tin bán hàng / SKU</h3>
<p class="help" data-combination-preview aria-live="polite"></p>
@include('admin.store.variant-matrix')
<details><summary>Sinh các tổ hợp còn thiếu</summary><form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/generate') }}">@csrf
<p><strong>{{ $readiness['combinations'] > 256 ? 'Vượt giới hạn 256' : $readiness['combinations'] }} tổ hợp theo các nhóm hiện tại</strong> · {{ $readiness['existing'] }} SKU đã có. @if($record->options->isEmpty())Sản phẩm không có phân loại sẽ có một SKU mặc định.@endif</p>
<label for="sku_prefix">Tiền tố SKU</label><input id="sku_prefix" name="sku_prefix" required maxlength="50"><p class="help">Tối đa 256 tổ hợp. SKU mới: Tạm ngưng, Liên hệ, tồn 0. SKU/giá/tồn đã có được giữ nguyên. Số tổ hợp được tính từ dữ liệu đã lưu.</p>
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
