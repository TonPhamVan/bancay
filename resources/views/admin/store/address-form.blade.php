@php use App\Admin\StoreResources as R; @endphp
<form method="post" action="{{ R::url('customers','customers/'.$record->id.'/actions/address') }}">@csrf
@if($address)<input type="hidden" name="address_id" value="{{ $address->id }}">@endif
<div class="store-grid">
@foreach(['recipient_name'=>'Tên người nhận','phone'=>'Điện thoại','line1'=>'Địa chỉ dòng 1','line2'=>'Địa chỉ dòng 2','ward'=>'Phường/Xã','district'=>'Quận/Huyện','province'=>'Tỉnh/Thành','country_code'=>'Mã quốc gia','postal_code'=>'Mã bưu chính','label'=>'Nhãn'] as $field=>$label)
<label>{{ $label }}<input name="{{ $field }}" value="{{ $address?->$field ?? ($field==='country_code' ? 'VN' : '') }}" @required(in_array($field,['recipient_name','phone','line1','country_code']))></label>
@endforeach</div><input type="hidden" name="is_default" value="0"><label><input type="checkbox" name="is_default" value="1" @checked($address?->is_default)> Địa chỉ mặc định</label><button class="btn btn-primary">Lưu địa chỉ</button></form>
