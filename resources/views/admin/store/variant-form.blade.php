@php use App\Admin\StoreResources as R; @endphp
<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/variant') }}">@csrf
@if($variant)<input type="hidden" name="variant_id" value="{{ $variant->id }}">@endif
<div class="store-grid">
<label>SKU<input name="sku" value="{{ $variant?->sku }}" required maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9._\-]*"></label>
<label>Loại giá<select name="pricing_type"><option value="CONTACT" @selected($variant?->pricing_type?->value !== 'FIXED')>Liên hệ</option><option value="FIXED" @selected($variant?->pricing_type?->value === 'FIXED')>Giá cố định</option></select></label>
<label>Giá gốc (VND)<input name="price" type="number" min="1" step="1" value="{{ $variant?->price }}"></label>
<label>Giá sale (VND)<input name="sale_price" type="number" min="1" step="1" value="{{ $variant?->sale_price }}"></label>
<label>Trạng thái SKU<select name="status"><option value="ACTIVE" @selected($variant?->status?->value === 'ACTIVE')>Hoạt động</option><option value="INACTIVE" @selected(!$variant || $variant->status->value === 'INACTIVE')>Tạm ngưng</option></select></label>
<label>Ảnh SKU<select name="image_id"><option value="">Không chọn</option>@foreach($record->images as $image)<option value="{{ $image->id }}" @selected($variant?->image_id == $image->id)>#{{ $image->id }} {{ $image->alt_text }}</option>@endforeach</select></label>
@if(!$variant)
    <label>Tồn ban đầu<input name="quantity" type="number" min="0" value="0" required></label>
    @foreach($record->options as $option)<label>{{ $option->name }}<select name="option_value_ids[]" required>@foreach($option->values as $value)<option value="{{ $value->id }}">{{ $value->value }}</option>@endforeach</select></label>@endforeach
@endif
</div><p class="help">Liên hệ: để trống cả hai giá. Giá cố định phải > 0; sale ≤ giá gốc. Tổ hợp SKU đã tạo không đổi; dùng trạng thái để ngưng bán.</p><button class="btn btn-success">{{ $variant ? 'Lưu SKU' : 'Tạo SKU' }}</button></form>
