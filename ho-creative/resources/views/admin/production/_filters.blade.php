<form class="toolbar">
  <div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="N° travail / commande"></div>
  <div class="field"><select name="department"><option value="">Atelier</option>@foreach(['digital' => 'Numérique', 'offset' => 'Offset', 'large_format' => 'Grand format', 'finishing' => 'Façonnage', 'packaging' => 'Packaging', 'studio' => 'Studio'] as $k => $v)<option value="{{ $k }}" @selected(request('department') === $k)>{{ $v }}</option>@endforeach</select></div>
  <div class="field"><select name="assigned"><option value="">Opérateur</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(request('assigned') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
  @isset($withStage)<div class="field"><select name="stage"><option value="">Étape</option>@foreach(\App\Services\ProductionService::STAGES as $s)<option value="{{ $s }}" @selected(request('stage') === $s)>{{ __('stage.'.$s) }}</option>@endforeach</select></div>@endisset
  <button class="btn btn-sm btn-ghost">Filtrer</button>
</form>
