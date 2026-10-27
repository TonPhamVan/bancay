@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>{{ $record->{$definition['display']} }}</h2>
<div class="store-card"><div class="store-actions"><a class="btn btn-primary" href="{{ R::url($module,$entity.'/'.$record->id.'/edit') }}">Chỉnh sửa</a><a href="{{ R::url($module,$entity) }}">Danh sách</a></div>
@if($record->image)<img style="max-width:100%;max-height:300px;object-fit:contain" src="{{ Storage::disk('public')->url($record->image) }}" alt="{{ $record->{$definition['display']} }}">@endif
<dl>@foreach($definition['fields'] as $field=>[$label,$type])@if($type!=='file')<dt>{{ $label }}</dt><dd style="white-space:pre-wrap">{{ $record->$field instanceof BackedEnum ? $record->$field->value : $record->$field }}</dd>@endif @endforeach</dl>
@include('admin.store.remove', ['deleteUrl'=>R::url($module,$entity.'/'.$record->id), 'deleteLabel'=>in_array($entity,['articles','customers']) ? 'Lưu trữ / Ngưng hoạt động' : 'Xóa (RESTRICT nếu đang được sử dụng)'])
</div>
@endsection
