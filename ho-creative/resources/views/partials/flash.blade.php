@if(session('ok'))<div class="alert alert-ok" role="status">{{ session('ok') }}</div>@endif
@if(session('warn'))<div class="alert alert-warn" role="status">{{ session('warn') }}</div>@endif
@if(session('err'))<div class="alert alert-err" role="alert">{{ session('err') }}</div>@endif
@if($errors->any())
  <div class="alert alert-err" role="alert"><ul style="margin:0;padding-inline-start:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
