@php use App\Admin\StoreResources as R; @endphp
@extends('admin.store.shell')
@section('store-content')
<h2>{{ $record->exists ? 'Chỉnh sửa' : 'Tạo mới' }} · {{ $definition['title'] }}</h2>
<form class="store-card" method="post" enctype="multipart/form-data" action="{{ R::url($module, $entity.($record->exists ? '/'.$record->id : '')) }}">
@csrf @if($record->exists) @method('PUT') @endif
@foreach(['Thông tin cơ bản'=>array_filter($definition['fields'],fn($k)=>!str_starts_with($k,'meta_'),ARRAY_FILTER_USE_KEY), 'SEO'=>array_filter($definition['fields'],fn($k)=>str_starts_with($k,'meta_'),ARRAY_FILTER_USE_KEY)] as $section=>$fields)
@if(count($fields))<fieldset><legend>{{ $section }}</legend><div class="store-grid">
@foreach($fields as $field=>[$label,$type])
@php
    $value=old($field,$record->$field);
    if($value instanceof BackedEnum) $value=$value->value;
    if($value instanceof DateTimeInterface) $value=$value->format('Y-m-d\TH:i');
    $value=$value ?? (in_array($field,['sort_order','priority']) ? 0 : ($field==='status' ? (in_array($entity,['customers','banners']) ? 'ACTIVE' : 'DRAFT') : ''));
@endphp
<div><label for="field_{{ $field }}">{{ $label }}</label>
@if(is_array($type) || isset($choices[$type]))
    <select id="field_{{ $field }}" name="{{ $field }}">@if(!is_array($type))<option value="">— Không chọn —</option>@endif
    @foreach(is_array($type) ? $type : $choices[$type] as $key=>$text)<option value="{{ $key }}" @selected((string)$value === (string)$key)>{{ $text }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea id="field_{{ $field }}" name="{{ $field }}" rows="5">{{ $value }}</textarea>
@elseif($type==='checkbox')<input type="hidden" name="{{ $field }}" value="0"><input id="field_{{ $field }}" type="checkbox" name="{{ $field }}" value="1" @checked($value)>
@elseif($type==='file')<input id="field_{{ $field }}" type="file" name="{{ $field }}" accept="image/jpeg,image/png,image/webp"><p class="help">JPEG/PNG/WebP, tối đa 5 MB. Ảnh được mã hóa lại khi lưu.</p>@if($record->image)<img class="thumb" src="{{ Storage::disk('public')->url($record->image) }}" alt="">@endif
@else<input id="field_{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ $value }}" @if($type==='number') min="0" @endif>@endif
@error($field)<span class="text-danger">{{ $message }}</span>@enderror
</div>
@endforeach</div></fieldset>@endif
@endforeach
<div class="store-actions"><button class="btn btn-success">Lưu</button><a href="{{ R::url($module,$entity) }}">Quay lại</a></div>
@if($entity==='products')<p class="help">Sau khi lưu thông tin cơ bản, mở trang chi tiết để quản lý ảnh, options và SKU.</p>@endif
</form>
@endsection
