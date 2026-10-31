@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>Đơn {{ $record->order_number }}</h2>
<nav class="store-actions" aria-label="Điều hướng hàng đợi đơn"><a class="btn btn-default" href="{{ $queueNav['back'] }}">Quay lại danh sách đang lọc</a>@if($queueNav['next'])<a class="btn btn-primary" href="{{ $queueNav['next'] }}">Đơn tiếp theo cần xử lý →</a>@endif</nav>
<p class="help">Đơn tiếp theo dùng cùng tìm kiếm/ngày/trạng thái/sắp xếp, chỉ gồm đơn website chưa kết thúc. Đơn legacy không thuộc hàng đợi xử lý tồn live.</p>
<div class="store-grid"><section class="store-card"><h3>Khách hàng tại thời điểm đặt</h3><p>{{ $record->customer_name }}<br>{{ $record->customer_phone }}<br>{{ $record->customer_email }}</p>
<p>@foreach($record->shipping_address ?? [] as $value){{ is_scalar($value) ? $value : '' }}<br>@endforeach</p><p>{{ $record->shipping_note }}</p></section>
<section class="store-card"><h3>Tổng tiền ({{ $record->currency }})</h3><dl><dt>Tạm tính</dt><dd>{{ \App\Support\IntegerDisplay::format($record->subtotal) }}</dd><dt>Giảm giá</dt><dd>{{ \App\Support\IntegerDisplay::format($record->discount_amount) }}</dd><dt>Vận chuyển</dt><dd>{{ \App\Support\IntegerDisplay::format($record->shipping_fee) }}</dd><dt>Tổng cộng</dt><dd><strong>{{ \App\Support\IntegerDisplay::format($record->total) }}</strong></dd></dl></section></div>
<section class="store-card store-scroll"><h3>Sản phẩm · Snapshot đơn hàng</h3><table><thead><tr><th>Tên sản phẩm</th><th>SKU / Variant</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th></tr></thead><tbody>
@foreach($record->items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ $item->sku }}<br>{{ $item->variant_description }}</td><td>{{ \App\Support\IntegerDisplay::format($item->unit_price) }}</td><td>{{ $item->quantity }}</td><td>{{ \App\Support\IntegerDisplay::format($item->subtotal) }}</td></tr>@endforeach
</tbody></table></section>
<section class="store-card"><h3>Trạng thái: {{ \App\Admin\StoreLabels::status($record->status) }}</h3>
@if($record->is_legacy)<p>Đơn lịch sử legacy được giữ nguyên, không thực hiện workflow tồn kho live.</p>
@else
@php $next=collect(App\Domain\Commerce\OrderStatus::cases())->filter(fn($s)=>$s !== App\Domain\Commerce\OrderStatus::Cancelled && $record->status->canTransitionTo($s)); @endphp
@if($next->isNotEmpty())<form method="post" action="{{ R::url('orders','orders/'.$record->id.'/actions/status') }}">@csrf<input type="hidden" name="expected_status" value="{{ $record->status->value }}">
@include('admin.store.queue-context')
<label for="order_status">Chuyển sang</label><select id="order_status" name="status">@foreach($next as $status)<option value="{{ $status->value }}">{{ \App\Admin\StoreLabels::status($status) }}</option>@endforeach</select>
<label for="status_note">Ghi chú xử lý (tùy chọn)</label><textarea id="status_note" name="note" maxlength="5000">{{ old('status') !== 'CANCELLED' ? old('note') : '' }}</textarea><button class="btn btn-primary">Đổi trạng thái</button></form>@else<p>Trạng thái kết thúc.</p>@endif
@if($record->status->canTransitionTo(App\Domain\Commerce\OrderStatus::Cancelled))
<details @if(old('status') === 'CANCELLED') open @endif><summary>Hủy đơn {{ $record->order_number }}</summary>
<p>Đơn có {{ $record->items->sum('quantity') }} sản phẩm. Hủy đơn sẽ giải phóng phần tồn đang giữ chỗ cho đơn này; tồn thực tế không tăng. Không thể mở lại đơn đã hủy.</p>
<form method="post" action="{{ R::url('orders','orders/'.$record->id.'/actions/status') }}">@csrf
@include('admin.store.queue-context')
<input type="hidden" name="expected_status" value="{{ $record->status->value }}"><input type="hidden" name="status" value="CANCELLED">
<label for="cancel_note">Ghi chú hủy (tùy chọn)</label><textarea id="cancel_note" name="note" maxlength="5000">{{ old('status') === 'CANCELLED' ? old('note') : '' }}</textarea>
<label><input type="checkbox" name="confirm_cancel" value="1" required> Tôi xác nhận hủy đơn {{ $record->order_number }} và giải phóng giữ chỗ.</label>
@error('confirm_cancel')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
<button class="btn btn-danger">Xác nhận hủy đơn</button></form></details>
@endif
<p class="help">Hủy trước giao: giải phóng giữ chỗ. SHIPPING: trừ tồn thực tế. RETURNED: không tự nhập lại kho, cần kiểm tra hàng nhận về.</p>
@endif</section>
<section class="store-card"><h3>Lịch sử trạng thái</h3><table><thead><tr><th>Thời gian</th><th>Từ → Đến</th><th>Người xử lý</th><th>Ghi chú</th></tr></thead><tbody>
@foreach($record->statusHistories as $history)<tr><td>{{ $history->created_at }}</td><td>{{ $history->from_status ? \App\Admin\StoreLabels::status($history->from_status) : 'Khởi tạo' }} → {{ \App\Admin\StoreLabels::status($history->to_status) }}</td><td>{{ $history->actor?->user_name ?? 'Hệ thống / Không còn tài khoản' }}</td><td>{{ $history->note }}</td></tr>@endforeach
</tbody></table></section>
@endsection
