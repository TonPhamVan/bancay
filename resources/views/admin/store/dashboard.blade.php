@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>Tổng quan cửa hàng</h2>
<section class="store-card"><h3>Việc cần làm theo quyền của bạn</h3><div class="store-actions">
@can('catalog.manage')<a class="btn btn-default" href="{{ R::url('catalog','products/create') }}">Tạo sản phẩm nháp</a><a class="btn btn-default" href="{{ R::url('catalog','inventory').'?stock=low' }}">Kiểm tra kho sắp hết</a>@endcan
@can('commerce.manage')<a class="btn btn-default" href="{{ R::url('orders','orders').'?origin=live&status=NEW' }}">Xử lý đơn website mới</a>@endcan
@can('content.manage')<a class="btn btn-default" href="{{ R::url('content','articles/create') }}">Soạn bài viết nháp</a><a class="btn btn-default" href="{{ R::url('content','banners') }}">Kiểm tra banner</a>@endcan
@can('settings.manage')<a class="btn btn-default" href="{{ R::url('settings','settings') }}">Kiểm tra thông tin cửa hàng</a>@endcan
</div><p class="help">Mỗi vai trò chỉ có chức năng được cấp. Xuất CSV có thể chứa thông tin khách hàng; chỉ dùng cho công việc được giao.</p>
@if(Exment::user()?->isAdministrator())<details><summary>Phân biệt hồ sơ, tài khoản đăng nhập và vai trò</summary><p>Người dùng là hồ sơ nhân viên; Tài khoản đăng nhập chứa thông tin xác thực; Vai trò / Quyền cấp chức năng nghiệp vụ. Dùng quyền tối thiểu theo công việc, kiểm tra URL trực tiếp sau khi đổi quyền và không chia sẻ tài khoản quản trị.</p></details>@endif
</section>
<p class="help">Bắt đầu tại đây để quản lý sản phẩm và đơn hàng. Số sản phẩm hiển thị dùng cùng quy tắc danh mục/ngày công khai với website.</p>
<div class="store-grid">
    @forelse($stats as $label => $value)
        <div class="store-card store-stat"><span>{{ $label }}</span><strong>{{ \App\Support\IntegerDisplay::format($value) }}</strong></div>
    @empty
        <div class="store-card">Tài khoản chưa được cấp quyền nghiệp vụ. Liên hệ quản trị viên để gán vai trò.</div>
    @endforelse
</div>
@can('commerce.manage')
<section class="store-card"><h3>Đơn website gần đây</h3><div class="store-actions"><a href="{{ R::url('orders','orders').'?origin=live&status=NEW' }}">Xử lý đơn mới</a><a href="{{ R::url('orders','orders').'?origin=legacy' }}">Xem đơn lịch sử legacy</a></div><div class="store-scroll"><table><thead><tr><th>Đơn</th><th>Khách hàng</th><th>Tổng (VND)</th><th>Trạng thái</th></tr></thead><tbody>
@forelse($recent as $order)<tr><td><a href="{{ R::url('orders', 'orders/'.$order->id) }}">{{ $order->order_number }}</a></td><td>{{ $order->customer_name }}</td><td>{{ \App\Support\IntegerDisplay::format($order->total) }}</td><td>{{ \App\Admin\StoreLabels::status($order->status) }}</td></tr>@empty<tr><td colspan="4">Chưa có đơn website.</td></tr>@endforelse
</tbody></table></div></section>
@endcan
@can('catalog.manage')
<section class="store-card"><h3>SKU hoạt động cần kiểm tra tồn (khả dụng ≤ {{ \App\Services\Admin\StoreQueries::LOW_STOCK }})</h3><p class="help">Bao gồm SKU hết hàng và sản phẩm chưa công khai. Khả dụng = tồn thực tế − giữ chỗ.</p><div class="store-actions"><a href="{{ R::url('catalog','inventory').'?stock=out' }}">Hết hàng</a><a href="{{ R::url('catalog','inventory').'?stock=low' }}">Sắp hết hàng</a><a href="{{ R::url('catalog','inventory') }}">Tất cả tồn kho</a></div><table><thead><tr><th>SKU</th><th>Sản phẩm</th><th>Khả dụng</th></tr></thead><tbody>
@forelse($lowStock as $variant)<tr><td>{{ $variant->sku }}</td><td><a href="{{ R::url('catalog', 'products/'.$variant->product_id) }}">{{ $variant->product->name }}</a></td><td>{{ $variant->inventory->available() }}</td></tr>@empty<tr><td colspan="3">Không có SKU cảnh báo.</td></tr>@endforelse
</tbody></table></section>
@endcan
@endsection
