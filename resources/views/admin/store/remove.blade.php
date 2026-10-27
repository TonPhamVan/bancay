@php use App\Admin\StoreResources as R; @endphp
<details><summary class="text-danger">{{ $deleteLabel }}</summary><form method="post" action="{{ $deleteUrl }}">@csrf @method('DELETE')
<label><input type="checkbox" name="confirm" value="1" required> Tôi xác nhận thao tác {{ $deleteLabel }}.</label><button class="btn btn-danger">Xác nhận</button></form></details>
