<div class="row" data-row style="display:grid;grid-template-columns:2fr 2fr 1.4fr 2fr 100px 120px auto;gap:8px;margin-bottom:8px;align-items:center">
  <select name="items[{{ $i }}][retail_product_id]" title="Article du magasin"><option value="">— Article magasin —</option>@foreach($retailProducts as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach</select>
  <select name="items[{{ $i }}][material_id]" title="Matière atelier"><option value="">— Matière atelier —</option>@foreach($materials as $m)<option value="{{ $m->id }}">{{ $m->name }} ({{ $m->unit }})</option>@endforeach</select>
  <select name="items[{{ $i }}][production_job_id]" title="Travail sous-traité"><option value="">Sous-traitance…</option>@foreach($jobs as $j)<option value="{{ $j->id }}">{{ $j->number }}</option>@endforeach</select>
  <input name="items[{{ $i }}][description]" placeholder="Description (optionnelle)">
  <input type="number" step="0.001" min="0.001" name="items[{{ $i }}][quantity]" placeholder="Qté" required>
  <input type="number" step="0.0001" min="0" name="items[{{ $i }}][unit_cost]" placeholder="Coût u. HT" required>
  <button type="button" class="btn btn-ghost btn-icon" data-rows-remove aria-label="Retirer">✕</button>
</div>
