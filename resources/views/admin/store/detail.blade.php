@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>{{ $record->{$definition['display']} }}</h2>
@if($entity==='articles')<a class="btn btn-default" target="_blank" rel="noopener" href="{{ R::url('content','articles/'.$record->id).'?preview=1' }}">Xem trước trên website</a>@endif
<div class="store-card"><div class="store-actions"><a class="btn btn-primary" href="{{ R::url($module,$entity.'/'.$record->id.'/edit') }}">Chỉnh sửa</a><a href="{{ R::url($module,$entity) }}">Danh sách</a></div>
@if($record->image)<img style="max-width:100%;max-height:300px;object-fit:contain" src="{{ Storage::disk('public')->url($record->image) }}" alt="{{ $record->{$definition['display']} }}">@endif
<dl>@foreach($definition['fields'] as $field=>[$label,$type])@if($type!=='file')<dt>{{ $label }}</dt><dd style="white-space:pre-wrap">{{ $field === 'status' ? \App\Admin\StoreLabels::status($record->$field) : ($record->$field instanceof BackedEnum ? $record->$field->value : $record->$field) }}</dd>@endif @endforeach</dl>
@include('admin.store.remove', ['deleteUrl'=>R::url($module,$entity.'/'.$record->id), 'deleteLabel'=>in_array($entity,['articles','customers']) ? 'Lưu trữ / Ngưng hoạt động' : 'Xóa (RESTRICT nếu đang được sử dụng)'])
</div>
@endsection
