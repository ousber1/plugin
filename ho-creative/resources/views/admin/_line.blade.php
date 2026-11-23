<div class="card" data-row data-staff-line data-selection="{{ isset($row['selection']) ? json_encode($row['selection']) : '' }}" style="padding:12px">
  <div class="flex between"><strong>Ligne</strong><button type="button" class="btn btn-sm btn-ghost" data-rows-remove>✕</button></div>
  <div class="grid-2">
    <div class="field"><label class="small">Produit du catalogue</label>
      <select name="lines[{{ $i }}][product_id]" data-f="product"><option value="">— Ligne libre (travail personnalisé) —</option>
        @foreach($products as $p)<option value="{{ $p->id }}" data-url="{{ route('price', $p) }}" @selected(($row['product_id'] ?? null) == $p->id)>{{ $p->t('name') }}</option>@endforeach</select></div>
    <div class="field"><label class="small">Ou description libre</label><input name="lines[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" placeholder="ex. Enseigne lumineuse 2 × 0,5 m"></div>
  </div>
  <div data-f="config"></div>
  <div class="grid-3">
    <div class="field"><label class="small">Qté (ligne libre)</label><input type="number" min="1" name="lines[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? 1 }}"></div>
    <div class="field"><label class="small">P.U. HT (ligne libre)</label><input type="number" step="0.0001" min="0" name="lines[{{ $i }}][unit_price]" value="{{ $row['unit_price'] ?? '' }}"></div>
    <div class="field"><label class="small">Prix calculé (serveur)</label><div data-line-out class="small"></div></div>
  </div>
</div>
