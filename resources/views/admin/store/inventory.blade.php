@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>Điều chỉnh tồn kho · {{ $record->variant->sku }}</h2>
<div class="store-card"><p>{{ $record->variant->product?->name ?? 'Sản phẩm đã lưu trữ' }}</p><p>Thực tế: <strong>{{ $record->quantity }}</strong> · Giữ chỗ: <strong>{{ $record->reserved_quantity }}</strong> · Khả dụng: <strong>{{ $record->available() }}</strong></p>
<form method="post" action="{{ R::url('catalog','inventory/'.$record->id.'/actions/adjust') }}">@csrf<input type="hidden" name="expected_quantity" value="{{ $record->quantity }}">
<label for="stock_quantity">Tồn thực tế mới</label><input id="stock_quantity" type="number" name="quantity" min="{{ $record->reserved_quantity }}" max="4294967295" value="{{ $record->quantity }}" required>
<label for="stock_reason">Lý do điều chỉnh</label><textarea id="stock_reason" name="reason" required minlength="3" maxlength="500"></textarea><button class="btn btn-primary">Lưu tồn kho</button></form>
<p class="help">Không sửa trực tiếp lượng giữ chỗ. Thay đổi được ghi log với người xử lý, trước/sau và lý do. Nếu dữ liệu đã đổi, tải lại trang.</p></div>
@endsection
