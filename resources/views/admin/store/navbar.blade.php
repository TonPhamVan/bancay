@if(Exment::user())
<li><a href="{{ \App\Admin\StoreResources::url('dashboard') }}" title="Tổng quan cửa hàng" aria-label="Tổng quan cửa hàng"><i class="fa fa-leaf" aria-hidden="true"></i><span class="hidden-xs"> Cửa hàng</span></a></li>
@endif
