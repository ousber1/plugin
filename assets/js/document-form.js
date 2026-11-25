/* Éditeur de devis / factures : lignes, recherche catalogue, calcul serveur en direct. */
(function () {
  'use strict';
  var form = document.getElementById('docForm');
  if (!form) { return; }
  var cfg = JSON.parse(document.getElementById('docFormConfig').textContent);
  var body = document.getElementById('docLines');
  var empty = document.getElementById('emptyLines');
  var errBox = document.getElementById('calcError');
  var catalogBox = document.getElementById('catalogBox');
  var customerBox = document.getElementById('customerBox');
  var customerInput = customerBox.querySelector('[data-lookup-value]');
  var lines = [];
  var timer = null, seq = 0, dirty = false;
  var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
  var fmt = function (n) { return (Number(n) || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/ /g, ' ') + ' DH'; };
  var num = function (v) { var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; };

  function taxOptions(rate) {
    var rates = cfg.taxes.slice();
    if (rates.indexOf(rate) < 0) { rates.push(rate); }
    return rates.map(function (r) { return '<option value="' + r + '"' + (r === rate ? ' selected' : '') + '>' + r + ' %</option>'; }).join('');
  }

  function render() {
    body.innerHTML = lines.map(function (l, i) {
      var fixedTax = l.type !== 'custom';
      return '<tr data-i="' + i + '">'
        + '<td>' + (l.type !== 'custom' ? '<div class="small text-body-secondary mb-1">' + (l.type === 'pack' ? '<i class="bi bi-boxes"></i> Pack ' : '<i class="bi bi-box"></i> ') + esc(l.code || '') + ' ' + esc(l.name || '') + '</div>' : '')
        + '<textarea class="form-control form-control-sm" data-f="description" rows="1" maxlength="400" placeholder="Désignation' + (l.type === 'custom' ? ' *' : '') + '" aria-label="Désignation">' + esc(l.description || '') + '</textarea>'
        + (l.is_area ? '<div class="hoc-dims"><input class="form-control form-control-sm" type="number" step="0.1" min="0" data-f="width" value="' + esc(l.width || '') + '" placeholder="Largeur cm" aria-label="Largeur (cm)"><span class="align-self-center">×</span><input class="form-control form-control-sm" type="number" step="0.1" min="0" data-f="height" value="' + esc(l.height || '') + '" placeholder="Hauteur cm" aria-label="Hauteur (cm)"><span class="align-self-center small text-body-secondary">cm</span></div>' : '')
        + '<div class="small text-danger d-none" data-err></div></td>'
        + '<td><input class="form-control form-control-sm" type="number" step="' + (l.decimal === false ? '1' : '0.001') + '" min="0" data-f="quantity" value="' + esc(l.quantity) + '" aria-label="Quantité"><div class="small text-body-secondary">' + esc(l.is_area ? 'exemplaire(s)' : (l.unit || '')) + '</div></td>'
        + '<td><input class="form-control form-control-sm" type="number" step="0.01" data-f="unit_price" value="' + esc(l.unit_price) + '" aria-label="Prix unitaire HT">' + (l.is_area ? '<div class="small text-body-secondary">par m²</div>' : '') + '</td>'
        + '<td><input class="form-control form-control-sm" type="number" step="0.5" min="0" max="100" data-f="discount_percent" value="' + esc(l.discount_percent || 0) + '" aria-label="Remise %"></td>'
        + '<td>' + (fixedTax || !cfg.taxEnabled ? '<span class="small">' + esc(l.tax_rate) + ' %</span>' : '<select class="form-select form-select-sm" data-f="tax_rate" aria-label="TVA">' + taxOptions(Number(l.tax_rate)) + '</select>') + '</td>'
        + '<td class="text-end fw-semibold text-nowrap" data-total>' + (l._total != null ? fmt(l._total) : '—') + '</td>'
        + '<td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-link px-1" data-act="up" title="Monter" aria-label="Monter"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>'
        + '<button type="button" class="btn btn-sm btn-link text-danger px-1" data-act="del" title="Retirer" aria-label="Retirer la ligne"><i class="bi bi-trash"></i></button></td></tr>';
    }).join('');
    empty.classList.toggle('d-none', lines.length > 0);
  }

  function payload() {
    var fees = {};
    form.querySelectorAll('.js-fee').forEach(function (f) { fees[f.name.replace(/^fees\[|\]$/g, '')] = num(f.value); });
    return {
      customer_id: customerInput.value ? parseInt(customerInput.value, 10) : 0,
      discount_type: form.discount_type.value, discount_value: num(form.discount_value.value), fees: fees,
      items: lines.map(function (l) {
        return { type: l.type, product_id: l.product_id || null, pack_id: l.pack_id || null, description: l.description || '',
          quantity: num(l.quantity), unit_price: num(l.unit_price), discount_percent: num(l.discount_percent), tax_rate: num(l.tax_rate),
          width: l.is_area ? num(l.width) : null, height: l.is_area ? num(l.height) : null, unit_id: l.unit_id || null,
          // Champs d'affichage conservés en cas de retour au formulaire
          name: l.name, code: l.code, unit: l.unit, is_area: !!l.is_area, decimal: l.decimal !== false };
      })
    };
  }

  function recalc() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      var my = ++seq;
      var data = payload();
      window.HOC.fetchJson(cfg.compute, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(function (r) {
          if (my !== seq) { return; }
          errBox.classList.add('d-none');
          (r.lines || []).forEach(function (l, i) { if (lines[i]) { lines[i]._total = l.line_subtotal; } });
          body.querySelectorAll('tr').forEach(function (tr, i) { var c = tr.querySelector('[data-total]'); if (c && lines[i]) { c.textContent = lines[i]._total != null ? fmt(lines[i]._total) : '—'; } });
          var t = r.totals;
          document.querySelectorAll('#totalsBox [data-t]').forEach(function (el) { el.textContent = t ? fmt(t[el.dataset.t]) : '—'; });
          document.getElementById('vatLines').innerHTML = t ? t.tax_breakdown.map(function (b) {
            return '<div class="hoc-mini text-body-secondary"><span>TVA ' + b.rate + ' % sur ' + fmt(b.base) + '</span><span>' + fmt(b.tax) + '</span></div>';
          }).join('') : '';
        })
        .catch(function (e) { if (my !== seq) { return; } errBox.textContent = e.message; errBox.classList.remove('d-none'); });
    }, 350);
  }

  function addItem(it) {
    if (it.type === 'product' || it.type === 'pack') {
      lines.push({ type: it.type, product_id: it.type === 'product' ? it.id : null, pack_id: it.type === 'pack' ? it.id : null,
        name: it.name, code: it.code, description: it.name, quantity: 1, unit_price: it.price, discount_percent: it.discount || 0,
        tax_rate: it.tax_rate, unit: it.unit, is_area: !!it.is_area, decimal: it.decimal !== false, width: '', height: '' });
    } else {
      lines.push({ type: 'custom', description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate: cfg.taxEnabled ? cfg.defaultRate : 0, unit: '', decimal: true });
    }
    dirty = true;
    render();
    var rows = body.querySelectorAll('tr');
    var last = rows[rows.length - 1];
    if (last) { var f = last.querySelector(lines[lines.length - 1].is_area ? '[data-f="width"]' : (lines[lines.length - 1].type === 'custom' ? '[data-f="description"]' : '[data-f="quantity"]')); if (f) { f.focus(); } }
    recalc();
  }

  body.addEventListener('input', function (e) {
    var tr = e.target.closest('tr'), f = e.target.dataset.f;
    if (!tr || !f) { return; }
    lines[+tr.dataset.i][f] = f === 'tax_rate' ? num(e.target.value) : e.target.value;
    dirty = true;
    recalc();
  });
  body.addEventListener('change', function (e) { if (e.target.dataset.f === 'tax_rate') { lines[+e.target.closest('tr').dataset.i].tax_rate = num(e.target.value); recalc(); } });
  body.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]');
    if (!b) { return; }
    var i = +b.closest('tr').dataset.i;
    if (b.dataset.act === 'del') { lines.splice(i, 1); }
    if (b.dataset.act === 'up' && i > 0) { var t = lines[i - 1]; lines[i - 1] = lines[i]; lines[i] = t; }
    dirty = true;
    render();
    recalc();
  });
  document.getElementById('addCustom').addEventListener('click', function () { addItem({ type: 'custom' }); });
  catalogBox.addEventListener('hoc:lookup', function (e) {
    addItem(e.detail);
    catalogBox.querySelector('[data-lookup-input]').value = '';
    catalogBox.querySelector('[data-lookup-value]').value = '';
  });

  // Le changement de client recharge les prix catalogue (tarif négocié, liste de prix, remise de groupe)
  function syncCatalogUrl() {
    catalogBox.dataset.lookup = cfg.catalog + (cfg.catalog.indexOf('?') >= 0 ? '&' : '?') + 'customer_id=' + encodeURIComponent(customerInput.value || '');
  }
  customerBox.addEventListener('hoc:lookup', function () {
    syncCatalogUrl();
    var ids = lines.filter(function (l) { return l.type === 'product'; }).map(function (l) { return l.product_id; });
    if (!ids.length) { recalc(); return; }
    window.HOC.fetchJson(cfg.prices, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ customer_id: parseInt(customerInput.value, 10), product_ids: ids }) })
      .then(function (r) {
        lines.forEach(function (l) { var p = l.type === 'product' && r.prices[l.product_id]; if (p) { l.unit_price = p.price; l.discount_percent = p.discount; l.tax_rate = p.tax_rate; } });
        render(); recalc();
      }).catch(function () { recalc(); });
  });
  ['discountType', 'discountValue'].forEach(function (id) { document.getElementById(id).addEventListener('input', recalc); });
  form.querySelectorAll('.js-fee').forEach(function (f) { f.addEventListener('input', recalc); });

  form.addEventListener('submit', function (e) {
    if (!lines.length) { e.preventDefault(); errBox.textContent = 'Ajoutez au moins une ligne.'; errBox.classList.remove('d-none'); return; }
    if (!customerInput.value) { e.preventDefault(); customerBox.querySelector('[data-lookup-input]').classList.add('is-invalid'); customerBox.querySelector('[data-lookup-input]').focus(); return; }
    document.getElementById('itemsJson').value = JSON.stringify(payload().items);
    dirty = false;
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  lines = (cfg.items || []).map(function (l) {
    return Object.assign({ discount_percent: 0, width: '', height: '' }, l, { width: l.width || '', height: l.height || '' });
  });
  syncCatalogUrl();
  render();
  recalc();
})();
