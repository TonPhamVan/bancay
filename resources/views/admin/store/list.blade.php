@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<div class="store-actions"><h2>{{ $definition['title'] }}</h2>
    @if(!in_array($entity, ['orders','inventory']))<a class="btn btn-success" href="{{ R::url($module, $entity.'/create') }}">Tạo mới</a>@endif
    @if(in_array($entity, ['products','inventory','orders','customers']))<a class="btn btn-default" href="{{ R::url($module, $entity.'/export').'?'.http_build_query(request()->query()) }}">Xuất CSV</a>@endif
</div>
<form method="get" action="{{ R::url($module, $entity) }}" class="store-card">
    <div class="store-grid">
        <div><label for="q">Tìm tên{{ $entity === 'products' ? ' / SKU' : ($entity === 'orders' ? ' / mã đơn / điện thoại' : ($entity === 'customers' ? ' / điện thoại / email' : '')) }}</label><input id="q" name="q" value="{{ request('q') }}" maxlength="200"></div>
        @if($entity !== 'inventory')<div><label for="status">Trạng thái</label><select id="status" name="status"><option value="">Tất cả</option>
            @foreach($entity === 'orders' ? ['NEW','CONFIRMED','PACKING','SHIPPING','COMPLETED','CANCELLED','RETURNED'] : (in_array($entity,['customers','banners']) ? ['ACTIVE','INACTIVE'] : ['DRAFT','PUBLISHED','ARCHIVED']) as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select></div>@endif
        @if(in_array($entity,['products','articles']))<div><label for="category_id">Danh mục</label><select id="category_id" name="category_id"><option value="">Tất cả</option>@foreach($filterChoices as $key=>$text)<option value="{{ $key }}" @selected((string)request('category_id')===(string)$key)>{{ $text }}</option>@endforeach</select></div>@endif
        @if(in_array($entity,['categories','article-categories']))<div><label for="parent_id">Danh mục cha</label><select id="parent_id" name="parent_id"><option value="">Tất cả</option><option value="0" @selected(request('parent_id')==='0')>Danh mục gốc</option>@foreach($filterChoices as $key=>$text)<option value="{{ $key }}" @selected((string)request('parent_id')===(string)$key)>{{ $text }}</option>@endforeach</select></div>@endif
        @if($entity === 'products')<div><label for="featured">Nổi bật</label><select id="featured" name="featured"><option value="">Tất cả</option><option value="1" @selected(request('featured')==='1')>Có</option><option value="0" @selected(request('featured')==='0')>Không</option></select></div>@endif
        @if($entity === 'orders')<div><label for="from">Từ ngày</label><input type="date" id="from" name="from" value="{{ request('from') }}"></div><div><label for="to">Đến ngày</label><input type="date" id="to" name="to" value="{{ request('to') }}"></div>@endif
        <div><label for="sort">Sắp xếp</label><select id="sort" name="sort">@foreach(['newest'=>'Mới nhất','oldest'=>'Cũ nhất','name'=>'Tên / Mã','sort_order'=>'Thứ tự'] as $key=>$label)<option value="{{ $key }}" @selected(request('sort','newest') === $key)>{{ $label }}</option>@endforeach</select></div>
    </div><div class="store-actions"><button class="btn btn-primary">Tìm / Lọc</button><a href="{{ R::url($module,$entity) }}">Xóa bộ lọc</a></div>
</form>
<div class="store-card store-scroll"><table><thead><tr><th>ID</th><th>{{ $entity === 'orders' ? 'Mã đơn / Khách hàng' : 'Thông tin' }}</th>
@if($entity === 'products')<th>Danh mục</th><th>Variants / Giá / Khả dụng</th>@endif
@if($entity === 'orders')<th>Điện thoại</th><th>Tổng (VND)</th>@endif
@if($entity === 'customers')<th>Liên hệ</th><th>Số đơn</th>@endif
@if($entity === 'inventory')<th>Thực tế</th><th>Giữ chỗ</th><th>Khả dụng</th>@else<th>Trạng thái</th>@endif
<th>{{ $entity==='orders' ? 'Ngày đặt' : ($entity==='customers' ? 'Ngày tạo' : 'Cập nhật') }}</th><th>Thao tác</th></tr></thead><tbody>
@forelse($rows as $row)
<tr><td>{{ $row->id }}</td><td>
    @if($entity === 'inventory'){{ $row->variant?->sku }}<br><span class="muted">{{ $row->variant?->product?->name ?? 'Sản phẩm đã lưu trữ' }}</span>
    @else
        @if($entity === 'products' && $row->images->isNotEmpty())<img class="thumb" src="{{ Storage::disk('public')->url(($row->images->firstWhere('is_primary',true) ?? $row->images->first())->path) }}" alt="">@endif
        <a href="{{ R::url($module,$entity.'/'.$row->id) }}">{{ $row->{$definition['display']} }}</a>
        @if($entity === 'orders')<br>{{ $row->customer_name }}@endif
        @if($entity === 'articles')<br><span class="muted">{{ $row->category?->name }}</span>@endif
        @if(in_array($entity,['categories','article-categories']))<br><span class="muted">{{ $row->parent?->name ?? 'Danh mục gốc' }} · Thứ tự {{ $row->sort_order }}</span>@endif
    @endif
</td>
@if($entity === 'products')
    @php $prices=$row->variants->map(fn($v)=>$v->effectivePrice())->filter(fn($p)=>$p!==null); @endphp
    <td>{{ $row->category?->name }}</td><td>{{ $row->variants_count }} SKU<br>{{ $prices->isEmpty() ? 'Liên hệ' : \App\Support\IntegerDisplay::format($prices->min()).' – '.\App\Support\IntegerDisplay::format($prices->max()).' VND' }}<br>Khả dụng: {{ $row->variants->sum(fn($v)=>$v->inventory?->available() ?? 0) }}</td>
@endif
@if($entity === 'orders')<td>{{ $row->customer_phone }}</td><td>{{ \App\Support\IntegerDisplay::format($row->total) }}</td>@endif
@if($entity === 'customers')<td>{{ $row->phone }}<br>{{ $row->email }}</td><td>{{ $row->orders_count }}</td>@endif
@if($entity === 'inventory')<td>{{ $row->quantity }}</td><td>{{ $row->reserved_quantity }}</td><td>{{ $row->available() }}</td>@else<td>{{ $row->status->value }}</td>@endif
<td>{{ in_array($entity,['orders','customers']) ? $row->created_at : $row->updated_at }}</td><td><a href="{{ R::url($module,$entity.'/'.$row->id) }}">Xem</a>@if(!in_array($entity,['orders','inventory'])) · <a href="{{ R::url($module,$entity.'/'.$row->id.'/edit') }}">Sửa</a>@endif</td></tr>
@empty<tr><td colspan="10">Chưa có dữ liệu phù hợp.</td></tr>@endforelse
</tbody></table>{{ $rows->links() }}</div>
@endsection
