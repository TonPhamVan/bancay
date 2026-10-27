@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>Khách hàng · {{ $record->name }}</h2>
<div class="store-card"><p>{{ $record->phone }} · {{ $record->email }} · {{ $record->status->value }}</p><p>Ngày tạo: {{ $record->created_at }}</p><a class="btn btn-primary" href="{{ R::url('customers','customers/'.$record->id.'/edit') }}">Sửa khách hàng</a></div>
<section class="store-card"><h3>Địa chỉ</h3>
@foreach($record->addresses as $address)
<details><summary>{{ $address->recipient_name }} · {{ $address->line1 }} {{ $address->is_default ? '(Mặc định)' : '' }}</summary>@include('admin.store.address-form', ['address'=>$address])
<form method="post" action="{{ R::url('customers','customers/'.$record->id.'/actions/delete-address') }}">@csrf<input type="hidden" name="address_id" value="{{ $address->id }}"><label><input type="checkbox" name="confirm" value="1" required> Xác nhận xóa địa chỉ hồ sơ (không sửa đơn cũ)</label><button class="btn btn-danger">Xóa địa chỉ</button></form></details>
@endforeach
<details><summary>Thêm địa chỉ</summary>@include('admin.store.address-form', ['address'=>null])</details></section>
@if($orders !== null)<section class="store-card"><h3>Lịch sử đơn hàng</h3><table><thead><tr><th>Mã đơn</th><th>Trạng thái</th><th>Tổng</th><th>Ngày đặt</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><a href="{{ R::url('orders','orders/'.$order->id) }}">{{ $order->order_number }}</a></td><td>{{ $order->status->value }}</td><td>{{ \App\Support\IntegerDisplay::format($order->total) }}</td><td>{{ $order->created_at }}</td></tr>@empty<tr><td colspan="4">Chưa có đơn được liên kết với hồ sơ.</td></tr>@endforelse</tbody></table>{{ $orders->links() }}</section>@endif
<section class="store-card">@include('admin.store.remove', ['deleteUrl'=>R::url('customers','customers/'.$record->id), 'deleteLabel'=>'Ngưng hoạt động khách hàng'])</section>
@endsection
