@props(['name', 'label', 'type' => 'text', 'required' => false, 'value' => null])
<div><label class="label" for="{{ $name }}">{{ $label }} @if($required)<span class="text-clay" aria-label="bắt buộc">*</span>@endif</label>
<input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) {{ $attributes->class(['field']) }}></div>
