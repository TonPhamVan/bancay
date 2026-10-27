@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>Tổng quan cửa hàng</h2>
<div class="store-grid">
    @forelse($stats as $label => $value)
        <div class="store-card store-stat"><span>{{ $label }}</span><strong>{{ \App\Support\IntegerDisplay::format($value) }}</strong></div>
    @empty
        <div class="store-card">Tài khoản chưa được cấp quyền nghiệp vụ. Liên hệ quản trị viên để gán vai trò.</div>
    @endforelse
</div>
@can('commerce.manage')
<section class="store-card"><h3>Đơn hàng gần đây</h3><div class="store-scroll"><table><thead><tr><th>Đơn</th><th>Khách hàng</th><th>Tổng (VND)</th><th>Trạng thái</th></tr></thead><tbody>
@forelse($recent as $order)<tr><td><a href="{{ R::url('orders', 'orders/'.$order->id) }}">{{ $order->order_number }}</a></td><td>{{ $order->customer_name }}</td><td>{{ \App\Support\IntegerDisplay::format($order->total) }}</td><td>{{ $order->status->value }}</td></tr>@empty<tr><td colspan="4">Chưa có đơn.</td></tr>@endforelse
</tbody></table></div></section>
@endcan
@can('catalog.manage')
<section class="store-card"><h3>SKU sắp hết hàng (khả dụng ≤ 5)</h3><table><thead><tr><th>SKU</th><th>Sản phẩm</th><th>Khả dụng</th></tr></thead><tbody>
@forelse($lowStock as $variant)<tr><td>{{ $variant->sku }}</td><td><a href="{{ R::url('catalog', 'products/'.$variant->product_id) }}">{{ $variant->product->name }}</a></td><td>{{ $variant->inventory->available() }}</td></tr>@empty<tr><td colspan="3">Không có SKU cảnh báo.</td></tr>@endforelse
</tbody></table></section>
@endcan
@endsection
