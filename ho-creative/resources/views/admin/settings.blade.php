@extends('layouts.admin')
@section('title', 'Paramètres')
@section('content')
<h1>Paramètres</h1>
<form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">@csrf
@foreach($schema as $group => $fields)
  <div class="card"><h2 style="font-size:1.1rem">{{ $group }}</h2><div class="grid-2">
  @foreach($fields as $key => $f)
    @php($name = str_replace('.', '__', $key)) @php($val = $s->get($key))
    <div class="field">
      @switch($f[0])
        @case('bool')<input type="hidden" name="{{ $name }}" value="0"><label class="check"><input type="checkbox" name="{{ $name }}" value="1" @checked(filter_var($val, FILTER_VALIDATE_BOOL))> {{ $f[1] }}</label>@break
        @case('tr')<label>{{ $f[1] }}</label><div class="grid-2"><input name="{{ $name }}[fr]" value="{{ $val['fr'] ?? '' }}" placeholder="FR"><input name="{{ $name }}[ar]" value="{{ $val['ar'] ?? '' }}" placeholder="AR" dir="rtl"></div>@break
        @case('color')<label>{{ $f[1] }}</label><input type="color" name="{{ $name }}" value="{{ $val }}">@break
        @case('select')<label>{{ $f[1] }}</label><select name="{{ $name }}">@foreach($f[2] as $k => $l)<option value="{{ $k }}" @selected($val === $k)>{{ $l }}</option>@endforeach</select>@break
        @case('image')<label>{{ $f[1] }}</label>@if($val)<img src="{{ $val }}" alt="" style="max-height:50px"><label class="check small"><input type="checkbox" name="{{ $name }}_remove" value="1"> retirer</label>@endif<input type="file" name="{{ $name }}" accept="image/*">@break
        @case('list')<label>{{ $f[1] }}</label><input name="{{ $name }}" value="{{ implode(', ', (array) $val) }}">@break
        @case('days')<label>{{ $f[1] }}</label><div class="flex">@foreach([1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'] as $n => $l)<label class="check"><input type="checkbox" name="{{ $name }}[]" value="{{ $n }}" @checked(in_array($n, (array) $val))> {{ $l }}</label>@endforeach</div>@break
        @default<label>{{ $f[1] }}</label><input name="{{ $name }}" value="{{ is_array($val) ? '' : $val }}" @if($f[0] === 'number') type="number" step="any" @endif>
      @endswitch
    </div>
  @endforeach
  </div></div>
@endforeach
<p class="small muted">Numérotation : jetons {YYYY} {YY} {MM} {SEQ:5}. La séquence repart à 1 chaque année et ne réutilise jamais un numéro. Les zones de livraison, jours fériés et modèles d'e-mails se gèrent dans leurs menus dédiés.</p>
<button class="btn btn-accent">Enregistrer les paramètres</button>
</form>
@endsection
