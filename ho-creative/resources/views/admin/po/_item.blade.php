<div class="row flex" data-row style="margin-bottom:8px">
  <select name="items[{{ $i }}][material_id]" style="flex:2"><option value="">— Sous-traitance / divers —</option>@foreach($materials as $m)<option value="{{ $m->id }}">{{ $m->name }} ({{ $m->unit }})</option>@endforeach</select>
  <select name="items[{{ $i }}][production_job_id]" style="flex:1" title="Travail sous-traité"><option value="">Travail lié (sous-traitance)</option>@foreach($jobs as $j)<option value="{{ $j->id }}">{{ $j->number }}</option>@endforeach</select>
  <input name="items[{{ $i }}][description]" placeholder="Description" style="flex:2">
  <input type="number" step="0.001" min="0.001" name="items[{{ $i }}][quantity]" placeholder="Qté" required style="width:100px">
  <input type="number" step="0.0001" min="0" name="items[{{ $i }}][unit_cost]" placeholder="Coût u. HT" required style="width:120px">
  <button type="button" class="btn btn-sm btn-ghost" data-rows-remove>✕</button>
</div>
