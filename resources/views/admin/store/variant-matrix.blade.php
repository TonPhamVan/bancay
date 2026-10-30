@php use App\Admin\StoreResources as R; @endphp
@if(count($matrixRows))
<form method="post" action="{{ R::url('catalog','products/'.$record->id.'/actions/variant-matrix') }}" data-variant-matrix data-rows="{{ json_encode($matrixRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}">
@csrf
<h4>Bảng phân loại hàng</h4>
<p class="help">Chỉnh trực tiếp các dòng rồi lưu một lần. Giá là VND nguyên; nhập giá gốc sẽ chọn Giá cố định. Chọn Liên hệ để bỏ giá. Tồn giữ chỗ và tổ hợp không sửa trực tiếp.</p>
<div data-matrix-status role="status" aria-live="polite" tabindex="-1"></div>
<fieldset data-matrix-controls class="matrix-controls">
<div class="store-actions matrix-bulk">
<label>Áp dụng cho dòng đã chọn<select data-bulk-field><option value="price">Giá gốc</option><option value="sale_price">Giá sale</option><option value="quantity">Tồn thực tế</option><option value="status">Trạng thái</option><option value="pricing_type">Loại giá</option></select></label>
<label>Giá trị<input data-bulk-value type="text" inputmode="numeric" pattern="[0-9]*" aria-label="Giá trị áp dụng hàng loạt"><select data-bulk-choice hidden aria-label="Lựa chọn áp dụng hàng loạt"></select></label>
<button type="button" class="btn btn-default" data-bulk-apply disabled>Áp dụng cho 0 dòng đã chọn</button>
</div>
<div class="store-scroll matrix-scroll" role="region" aria-label="Bảng chỉnh sửa SKU, cuộn ngang để xem đủ cột" tabindex="0">
<table class="variant-matrix"><caption class="help">{{ count($matrixRows) }} SKU · Có thể lưu tối đa 256 dòng thay đổi mỗi lần</caption>
<thead><tr><th><input type="checkbox" data-matrix-select-all aria-label="Chọn tất cả SKU"></th><th scope="col">Phân loại / SKU</th><th scope="col">Loại giá</th><th scope="col">Giá gốc</th><th scope="col">Giá sale</th><th scope="col">Ảnh</th><th scope="col">Tồn thực tế</th><th scope="col">Giữ chỗ / Khả dụng</th><th scope="col">Trạng thái</th></tr></thead>
<tbody>
@foreach($matrixRows as $row)
<tr data-matrix-row="{{ $row['id'] }}">
<td><input type="checkbox" data-matrix-select aria-label="Chọn {{ $row['label'] }}"></td>
<td class="matrix-identity"><strong>{{ $row['label'] }}</strong><label for="matrix-sku-{{ $row['id'] }}" class="muted">SKU</label><input id="matrix-sku-{{ $row['id'] }}" data-field="sku" value="{{ $row['sku'] }}" required maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9._\-]*"><div data-row-errors class="text-danger" aria-live="polite"></div></td>
<td><select data-field="pricing_type" aria-label="Loại giá {{ $row['label'] }}"><option value="CONTACT" @selected($row['pricing_type']==='CONTACT')>Liên hệ</option><option value="FIXED" @selected($row['pricing_type']==='FIXED')>Giá cố định</option></select></td>
<td><input data-field="price" type="text" inputmode="numeric" pattern="[0-9]*" value="{{ $row['price'] }}" aria-label="Giá gốc {{ $row['label'] }}"></td>
<td><input data-field="sale_price" type="text" inputmode="numeric" pattern="[0-9]*" value="{{ $row['sale_price'] }}" aria-label="Giá sale {{ $row['label'] }}"></td>
<td><select data-field="image_id" aria-label="Ảnh {{ $row['label'] }}"><option value="">Không chọn</option>@foreach($record->images as $image)<option value="{{ $image->id }}" @selected($row['image_id']===(string)$image->id)>#{{ $image->id }} {{ $image->alt_text ?: 'Ảnh '.$loop->iteration }}</option>@endforeach</select></td>
<td><input data-field="quantity" type="text" inputmode="numeric" pattern="[0-9]*" value="{{ $row['quantity'] }}" required aria-label="Tồn thực tế {{ $row['label'] }}"></td>
<td><span data-reserved>{{ $row['reserved'] }}</span> / <strong data-available>{{ $row['available'] }}</strong></td>
<td><select data-field="status" aria-label="Trạng thái {{ $row['label'] }}"><option value="ACTIVE" @selected($row['status']==='ACTIVE')>Hoạt động</option><option value="INACTIVE" @selected($row['status']==='INACTIVE')>Tạm ngưng</option></select></td>
</tr>
@endforeach
</tbody></table></div>
<label for="matrix-stock-reason">Lý do điều chỉnh tồn kho (bắt buộc khi thay đổi tồn)</label><input id="matrix-stock-reason" data-stock-reason maxlength="500" placeholder="Ví dụ: kiểm kê thực tế">
<div class="store-actions"><button class="btn btn-success" type="submit" data-matrix-save disabled>Lưu 0 dòng thay đổi</button><button type="button" class="btn btn-default" data-matrix-reset disabled>Hoàn tác thay đổi chưa lưu</button><span class="help">Áp dụng hàng loạt chỉ điền vào bảng; nhấn Lưu để ghi nhận.</span></div>
</fieldset>
<noscript><p class="text-danger">Bật JavaScript để dùng bảng. Bạn vẫn có thể mở “Chỉnh sửa từng SKU” bên dưới.</p></noscript>
</form>
@endif
