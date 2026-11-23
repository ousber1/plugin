<div class="card" data-row data-staff-line style="padding:12px">
  <div class="flex between"><strong>Ligne</strong><button type="button" class="btn btn-sm btn-ghost" data-rows-remove>✕</button></div>
  <div class="grid-2">
    <div class="field"><label class="small">Produit du catalogue</label><select name="lines[{{ $i }}][product_id]" data-f="product"><option value="">— Travail personnalisé —</option>@foreach($products as $p)<option value="{{ $p->id }}" data-url="{{ route('price', $p) }}">{{ $p->t('name') }}</option>@endforeach</select></div>
    <div class="field"><label class="small">Description (travail personnalisé)</label><input name="lines[{{ $i }}][description]"></div>
  </div>
  <div data-f="config"></div>
  <div class="grid-4">
    <div class="field"><label class="small">Qté (perso.)</label><input type="number" min="1" name="lines[{{ $i }}][quantity]" value="1"></div>
    <div class="field"><label class="small">P.U. HT (perso.)</label><input type="number" step="0.0001" min="0" name="lines[{{ $i }}][unit_price]"></div>
    @can('orders.discount')<div class="field"><label class="small">Remise ligne HT</label><input type="number" step="0.01" min="0" name="lines[{{ $i }}][line_discount]"></div><div class="field"><label class="small">Motif</label><input name="lines[{{ $i }}][line_discount_reason]"></div>@endcan
  </div>
  <input type="hidden" name="lines[{{ $i }}][requires_proof]" value="0"><label class="check small"><input type="checkbox" name="lines[{{ $i }}][requires_proof]" value="1"> BAT requis (travail personnalisé)</label>
  <div data-line-out class="small"></div>
</div>
