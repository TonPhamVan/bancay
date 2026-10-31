@php
    use App\Admin\StoreResources as R;
    $retryImages = old('image_form') === (string) $record->id;
    $attempted = $retryImages && is_array(old('images')) ? collect(old('images'))->filter(fn($row)=>is_array($row) && is_scalar($row['id'] ?? null))->keyBy('id') : collect();
    $primary = $retryImages ? \App\Admin\StoreLabels::text(old('primary_image_id')) : ($record->images->firstWhere('is_primary',true)?->id ?? $record->images->first()?->id);
@endphp
@if($record->images->isNotEmpty() && $record->images->count() <= 200)
<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/images-batch') }}" data-image-batch data-watch-unsaved @if($retryImages) data-unsaved-initial="true" @endif>
@csrf
<input type="hidden" name="expected_version" value="{{ $retryImages ? \App\Admin\StoreLabels::text(old('expected_version')) : $imageVersion }}">
<p class="help">Chọn một ảnh chính. Dùng Lên/Xuống để sắp xếp, sửa mô tả rồi lưu tất cả một lần. Dữ liệu đang được người khác sửa sẽ bị từ chối để tránh ghi đè.</p>
<div class="store-grid" data-image-rows>
@foreach($record->images as $image)
@php $attempt = $attempted->get($image->id, []); @endphp
<div class="store-card" data-image-row>
<img class="store-gallery-image" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt_text ?: 'Ảnh '.$loop->iteration }}" loading="lazy" width="240" height="160">
<input type="hidden" name="images[{{ $loop->index }}][id]" value="{{ $image->id }}">
<label for="batch_alt_{{ $image->id }}">Mô tả ảnh {{ $loop->iteration }} (alt)</label><input id="batch_alt_{{ $image->id }}" name="images[{{ $loop->index }}][alt_text]" value="{{ \App\Admin\StoreLabels::text(array_key_exists('alt_text',$attempt) ? $attempt['alt_text'] : $image->alt_text) }}" maxlength="255">
<label for="batch_sort_{{ $image->id }}">Thứ tự ảnh {{ $loop->iteration }}</label><input id="batch_sort_{{ $image->id }}" data-image-sort type="number" min="0" max="2147483647" name="images[{{ $loop->index }}][sort_order]" value="{{ \App\Admin\StoreLabels::text($attempt['sort_order'] ?? $image->sort_order) }}" required>
<label><input type="radio" name="primary_image_id" value="{{ $image->id }}" @checked((string)$primary === (string)$image->id) required> Ảnh chính #{{ $image->id }}</label>
<div class="store-actions" data-image-order hidden><button type="button" class="btn btn-default" data-image-up aria-label="Di chuyển ảnh {{ $loop->iteration }} lên">↑ Lên</button><button type="button" class="btn btn-default" data-image-down aria-label="Di chuyển ảnh {{ $loop->iteration }} xuống">↓ Xuống</button></div>
</div>
@endforeach
</div><button class="btn btn-primary">Lưu tất cả mô tả / thứ tự / ảnh chính</button></form>
@elseif($record->images->count()>200)<p class="help">Gallery trên200 ảnh: dùng trình sửa từng ảnh bên dưới.</p>
@endif
<details><summary>Sửa từng ảnh / Xóa ảnh</summary>
<p class="help">Lưu phần đang chỉnh sửa trước khi xóa ảnh. Ảnh đang gắn với SKU được bảo vệ.</p>
<div class="store-grid">
@foreach($record->images as $image)
<div class="store-card"><img class="thumb" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt_text }}" loading="lazy">
<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/image') }}">@csrf<input type="hidden" name="image_id" value="{{ $image->id }}">
<label for="alt_{{ $image->id }}">Mô tả ảnh (alt)</label><input id="alt_{{ $image->id }}" name="alt_text" value="{{ $image->alt_text }}" maxlength="255">
<label for="sort_{{ $image->id }}">Thứ tự ảnh</label><input type="number" min="0" id="sort_{{ $image->id }}" name="sort_order" value="{{ $image->sort_order }}">
<input type="hidden" name="is_primary" value="0"><label><input type="checkbox" name="is_primary" value="1" @checked($image->is_primary)> Ảnh chính</label><button class="btn btn-primary">Lưu ảnh</button></form>
<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/delete-image') }}">@csrf<input type="hidden" name="image_id" value="{{ $image->id }}"><label><input type="checkbox" name="confirm" value="1" required> Xác nhận xóa ảnh #{{ $image->id }}</label><button class="btn btn-danger">Xóa ảnh</button></form>
</div>@endforeach</div></details>
