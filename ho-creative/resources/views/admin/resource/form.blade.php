@extends('layouts.admin')
@section('title', $def['label'])
@section('content')
<div class="page-head"><h1>{{ $def['label'] }} – {{ $model->exists ? 'modifier' : 'ajouter' }}</h1><a href="{{ route('admin.r.index', $def['key']) }}">← Retour</a></div>
<form class="card" method="post" enctype="multipart/form-data" action="{{ $model->exists ? route('admin.r.update', [$def['key'], $model->id]) : route('admin.r.store', $def['key']) }}" style="max-width:900px">
  @csrf @if($model->exists) @method('put') @endif
  @foreach($def['fields'] as $name => $f)
    @php([$type, $label] = $f)
    @php($val = old($name, $model->{$name}))
    <div class="field">
      @switch($type)
        @case('tr')
          <label>{{ $label }}</label>
          <div class="grid-2"><input name="{{ $name }}[fr]" value="{{ old($name.'.fr', $model->{$name}['fr'] ?? '') }}" placeholder="Français" aria-label="{{ $label }} (FR)"><input name="{{ $name }}[ar]" dir="rtl" lang="ar" value="{{ old($name.'.ar', $model->{$name}['ar'] ?? '') }}" placeholder="العربية" aria-label="{{ $label }} (AR)"></div>
          @break
        @case('tr_area')
          <label>{{ $label }}</label>
          <div class="grid-2"><textarea name="{{ $name }}[fr]" rows="6" aria-label="{{ $label }} (FR)">{{ old($name.'.fr', $model->{$name}['fr'] ?? '') }}</textarea><textarea name="{{ $name }}[ar]" rows="6" dir="rtl" lang="ar" aria-label="{{ $label }} (AR)">{{ old($name.'.ar', $model->{$name}['ar'] ?? '') }}</textarea></div>
          @break
        @case('textarea')
          <label for="f-{{ $name }}">{{ $label }}</label><textarea id="f-{{ $name }}" name="{{ $name }}">{{ $val }}</textarea>
          @break
        @case('json')
          <label for="f-{{ $name }}">{{ $label }}</label><textarea id="f-{{ $name }}" name="{{ $name }}" rows="12" class="code">{{ old($name, $model->{$name} ? json_encode($model->{$name}, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
          @break
        @case('checkbox')
          <input type="hidden" name="{{ $name }}" value="0"><label class="check"><input type="checkbox" name="{{ $name }}" value="1" @checked($val)> {{ $label }}</label>
          @break
        @case('select')
          @php($opts = is_callable($f[3]) ? $f[3]() : $f[3])
          <label for="f-{{ $name }}">{{ $label }}</label>
          <select id="f-{{ $name }}" name="{{ $name }}"><option value="">—</option>@foreach($opts as $k => $v)<option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $v }}</option>@endforeach</select>
          @break
        @case('list')
          <label for="f-{{ $name }}">{{ $label }}</label><input id="f-{{ $name }}" name="{{ $name }}" value="{{ is_array($val) ? implode(', ', $val) : $val }}">
          @break
        @case('image')
          <label for="f-{{ $name }}">{{ $label }}</label>@if($model->{$name})<img src="{{ $model->{$name} }}" alt="" style="max-height:80px;margin-bottom:6px">@endif
          <input type="hidden" name="{{ $name }}_current" value="{{ $model->{$name} }}"><input id="f-{{ $name }}" type="file" name="{{ $name }}_file" accept="image/*">
          @break
        @case('color')
          <label for="f-{{ $name }}">{{ $label }}</label><input id="f-{{ $name }}" type="color" name="{{ $name }}" value="{{ $val ?: '#1B1F3B' }}">
          @break
        @case('date')
          <label for="f-{{ $name }}">{{ $label }}</label><input id="f-{{ $name }}" type="date" name="{{ $name }}" value="{{ $val instanceof \Carbon\CarbonInterface ? $val->format('Y-m-d') : $val }}">
          @break
        @default
          <label for="f-{{ $name }}">{{ $label }}</label><input id="f-{{ $name }}" type="{{ in_array($type, ['number', 'decimal']) ? 'number' : 'text' }}" @if($type === 'decimal') step="0.01" @endif name="{{ $name }}" value="{{ $val }}">
      @endswitch
      @error($name)<div class="error-text">{{ $message }}</div>@enderror
    </div>
  @endforeach
  <button class="btn btn-accent">Enregistrer</button>
</form>
@endsection
