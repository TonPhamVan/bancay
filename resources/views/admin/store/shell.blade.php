@php use App\Admin\StoreResources as R; @endphp
<style>
.store-admin{color:#24332b}.store-admin .store-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}.store-admin .store-nav a{padding:8px 12px;border:1px solid #d5e2d9;border-radius:5px;background:white;color:#245737}.store-admin .store-card{background:white;border:1px solid #dce5df;border-radius:6px;padding:20px;margin-bottom:18px}.store-admin label{display:block;margin-top:10px}.store-admin input:not([type=checkbox]),.store-admin select,.store-admin textarea{width:100%;padding:8px;border:1px solid #bacbbf;border-radius:4px}.store-admin textarea{min-height:90px}.store-admin .store-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.store-admin .store-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:12px 0}.store-admin .store-actions form{margin:0}.store-admin .store-scroll{overflow-x:auto}.store-admin table{width:100%}.store-admin th,.store-admin td{padding:9px;border-bottom:1px solid #e2e9e5;vertical-align:top}.store-admin details{margin:10px 0}.store-admin summary{cursor:pointer;font-weight:600}.store-admin .muted{color:#65746a}.store-admin .thumb{width:64px;height:64px;object-fit:contain}.store-admin .store-stat strong{display:block;font-size:26px;color:#23623a}.store-admin .help{font-size:13px;color:#617467}.store-admin fieldset{border:1px solid #dce5df;border-radius:5px;padding:15px;margin-bottom:18px}.store-admin legend{font-size:17px;width:auto;padding:0 7px;border:0}.store-admin .btn{white-space:normal}
</style>
<div class="store-admin">
    <nav class="store-nav" aria-label="Quản trị cửa hàng">
        <a href="{{ R::url('dashboard') }}">Tổng quan</a>
        @foreach(R::MODULES as $navModule => $entities)
            @can(R::ABILITIES[$navModule])
                @foreach($entities as $navEntity)
                    <a href="{{ R::url($navModule, $navEntity) }}">{{ R::all()[$navEntity]['title'] }}</a>
                @endforeach
            @endcan
        @endforeach
        @if(Exment::user()?->isAdministrator())
            <a href="{{ admin_url('data/user') }}">Người dùng</a>
            <a href="{{ admin_url('role_group') }}">Vai trò / Quyền</a>
            <a href="{{ admin_url('loginuser') }}">Tài khoản đăng nhập</a>
        @endif
    </nav>
    @if(session('store_success'))<div class="alert alert-success" role="status">{{ session('store_success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Chưa lưu được dữ liệu</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('store-content')
</div>
