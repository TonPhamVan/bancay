@foreach($errors->getMessages() as $field => $messages)
@if(in_array($field, ['name','sort_order','values','options','record']) || str_starts_with($field, 'values.'))
@foreach($messages as $message)<p class="text-danger" role="alert">{{ $message }}</p>@endforeach
@endif
@endforeach
