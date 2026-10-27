@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>Cấu hình cửa hàng</h2>
<form method="post" enctype="multipart/form-data" class="store-card" action="{{ R::url('settings','settings') }}">@csrf
<div class="store-grid">
@foreach(['site_name'=>'Tên website','contact_phone'=>'Điện thoại liên hệ','email'=>'Email','address'=>'Địa chỉ','facebook'=>'Facebook URL','map'=>'Bản đồ URL','meta_title'=>'SEO title mặc định','meta_description'=>'SEO description mặc định'] as $key=>$label)
<label for="setting_{{ $key }}">{{ $label }}<input id="setting_{{ $key }}" name="settings[{{ $key }}]" value="{{ old('settings.'.$key,$settings[$key] ?? '') }}" maxlength="5000"></label>
@endforeach
<label for="logo_upload">Logo<input id="logo_upload" type="file" name="logo_upload" accept="image/jpeg,image/png,image/webp">@if($settings['logo'] ?? null)<img class="thumb" src="{{ Storage::disk('public')->url($settings['logo']) }}" alt="Logo">@endif</label>
</div><div class="store-actions"><button class="btn btn-success">Lưu cấu hình</button></div></form>
@endsection
