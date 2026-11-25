/* =====================================================================
   HO CREATIVE — Point de vente (application cliente)
   Le navigateur calcule les totaux pour l'affichage avec le même
   algorithme que App\Services\SaleCalculator ; le serveur recalcule et
   contrôle tout à l'encaissement (prix, remises, permissions, crédit).
   ===================================================================== */
(function () {
  'use strict';
  var cfgEl = document.getElementById('posConfig');
  if (!cfgEl) { return; }
  var CFG = JSON.parse(cfgEl.textContent);
  var HOC = window.HOC;
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
  var num = function (v) { var n = parseFloat(String(v == null ? '' : v).replace(/[\s  ]/g, '').replace(',', '.')); return isNaN(n) ? 0 : n; };
  var r2 = function (n) { return Math.round((n + Number.EPSILON) * 100) / 100; };
  var fmt = function (n) {
    var s = r2(n).toFixed(2).split('.');
    return s[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ',' + s[1];
  };
  var money = function (n) { return fmt(n) + ' ' + CFG.config.currency; };
  var bs = function (id) { return bootstrap.Modal.getOrCreateInstance($(id)); };
  var STORE = 'hoc-pos-cart';

  /* ------------------------------------------------------------------
     État du panier
     ------------------------------------------------------------------ */
  var state = {
    customer: { id: CFG.walkIn.id, name: CFG.walkIn.name, walk_in: true },
    items: [], discount_type: 'percent', discount_value: 0, notes: '', draft_id: null
  };
  var seq = 0;

  function save() { try { localStorage.setItem(STORE, JSON.stringify(state)); } catch (e) { /* stockage indisponible */ } }
  function restore() {
    try {
      var s = JSON.parse(localStorage.getItem(STORE) || 'null');
      if (s && Array.isArray(s.items)) { state = s; s.items.forEach(function (it) { it.key = ++seq; }); }
    } catch (e) { /* ignoré */ }
  }

  /** Quantité facturée (surface en m² pour les travaux au m²). */
  function billedQty(it) {
    if (it.is_area) { return Math.round(num(it.width) * num(it.height) / 10000 * num(it.quantity) * 1000) / 1000; }
    return num(it.quantity);
  }

  /** Miroir de SaleCalculator::compute(). */
  function compute() {
    var subtotal = 0;
    var lines = state.items.map(function (it) {
      var gross = r2(billedQty(it) * num(it.unit_price) * (1 - num(it.discount_percent) / 100));
      subtotal += gross;
      return { it: it, gross: gross };
    });
    subtotal = r2(subtotal);
    var dv = num(state.discount_value), discount = 0;
    if (state.discount_type === 'percent') { discount = r2(subtotal * Math.max(0, Math.min(100, dv)) / 100); }
    else if (state.discount_type === 'amount') { discount = r2(Math.max(0, Math.min(subtotal, dv))); }
    var allocated = 0, largest = -1;
    lines.forEach(function (l, i) {
      l.share = subtotal > 0 ? r2(discount * l.gross / subtotal) : 0; allocated += l.share;
      if (largest < 0 || l.gross > lines[largest].gross) { largest = i; }
    });
    if (largest >= 0) { lines[largest].share = r2(lines[largest].share + (discount - allocated)); }
    var tax = 0, net = 0;
    lines.forEach(function (l) {
      var n = r2(l.gross - l.share), t = r2(n * num(l.it.tax_rate) / 100);
      l.net = n; l.tax = t; l.ttc = r2(n + t); l.grossTtc = r2(l.gross * (1 + num(l.it.tax_rate) / 100));
      net += n; tax += t;
    });
    return { lines: lines, subtotal: subtotal, discount: discount, tax: r2(tax), total: r2(net + tax) };
  }

  /* ------------------------------------------------------------------
     Catalogue
     ------------------------------------------------------------------ */
  var grid = $('posGrid'), search = $('posSearch'), currentCat = 'quick', lastItems = [], searchTimer = null, reqSeq = 0;

  function loadProducts(q, cat) {
    var my = ++reqSeq;
    var url = CFG.urls.products + (CFG.urls.products.indexOf('?') >= 0 ? '&' : '?') + new URLSearchParams({ q: q || '', category: cat || '', customer_id: state.customer.id });
    grid.classList.add('loading');
    return HOC.fetchJson(url).then(function (d) {
      if (my !== reqSeq) { return d; }
      lastItems = d.items;
      renderGrid(d.items, q);
      return d;
    }).finally(function () { grid.classList.remove('loading'); });
  }

  function renderGrid(items, q) {
    if (!items.length) {
      grid.innerHTML = '<div class="pos-empty"><i class="bi bi-search"></i>' + (q ? 'Aucun article pour « ' + esc(q) + ' ».' : (currentCat === 'quick' ? 'Aucun bouton rapide : cochez « Bouton rapide au point de vente » sur vos articles les plus vendus.' : 'Aucun article dans cette catégorie.'))
        + '<button class="btn btn-sm btn-outline-dark mt-2" type="button" data-custom>Ajouter un article libre</button></div>';
      return;
    }
    grid.innerHTML = items.map(function (it, i) {
      var low = it.track_stock && it.stock <= 0;
      return '<button type="button" class="pos-card' + (low ? ' out' : '') + '" data-i="' + i + '" style="--accent:' + esc(it.color || '#6c757d') + '">'
        + '<span class="pos-card-img">' + (it.image ? '<img src="' + esc(it.image) + '" alt="" loading="lazy">' : '<i class="bi ' + (it.type === 'pack' ? 'bi-boxes' : (it.is_area ? 'bi-aspect-ratio' : 'bi-box')) + '"></i>') + '</span>'
        + '<span class="pos-card-name">' + esc(it.name) + '</span>'
        + '<span class="pos-card-price">' + money(it.price * (1 + it.tax_rate / 100)) + '<small> / ' + esc(it.unit) + '</small></span>'
        + (it.track_stock ? '<span class="pos-card-stock' + (low ? ' text-danger' : '') + '">Stock ' + fmt(it.stock).replace(',00', '') + '</span>' : '')
        + (it.source === 'customer' ? '<span class="pos-card-tag">Prix client</span>' : (it.source === 'price_list' ? '<span class="pos-card-tag">Tarif</span>' : ''))
        + '</button>';
    }).join('');
  }

  grid.addEventListener('click', function (e) {
    if (e.target.closest('[data-custom]')) { openCustom(); return; }
    var b = e.target.closest('.pos-card'); if (!b) { return; }
    addItem(lastItems[+b.dataset.i]);
  });

  $('posChips').addEventListener('click', function (e) {
    var c = e.target.closest('.pos-chip'); if (!c) { return; }
    document.querySelectorAll('.pos-chip').forEach(function (x) { x.classList.toggle('active', x === c); });
    currentCat = c.dataset.cat; search.value = '';
    loadProducts('', currentCat);
  });

  search.addEventListener('input', function () {
    clearTimeout(searchTimer);
    var q = search.value.trim();
    searchTimer = setTimeout(function () { loadProducts(q, q ? '' : currentCat); }, 220);
  });
  // Scanner de codes-barres : saisie rapide terminée par Entrée → ajout direct si correspondance exacte
  search.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') { return; }
    e.preventDefault();
    clearTimeout(searchTimer);
    var q = search.value.trim(); if (!q) { return; }
    loadProducts(q, '').then(function (d) {
      var hit = d.exact || (d.items.length === 1 ? d.items[0] : null);
      if (hit) { addItem(hit); search.value = ''; search.focus(); }
    });
  });

  /* ------------------------------------------------------------------
     Ajout au panier
     ------------------------------------------------------------------ */
  var pendingArea = null;

  function addItem(p) {
    if (!p) { return; }
    if (p.is_area) { pendingArea = p; openArea(p); return; }
    // Même article sans modification : on incrémente la quantité
    var same = state.items.find(function (it) {
      return it.type === p.type && (it.product_id || it.pack_id) === p.id && !it.is_area && !it.manual_price && num(it.discount_percent) === num(p.discount);
    });
    if (same) { same.quantity = num(same.quantity) + 1; }
    else { state.items.push(fromCatalog(p, {})); }
    render(); flash(state.items.length - 1);
  }

  function fromCatalog(p, extra) {
    return Object.assign({
      key: ++seq, type: p.type, product_id: p.type === 'product' ? p.id : null, pack_id: p.type === 'pack' ? p.id : null,
      name: p.name, code: p.code, unit: p.unit, is_area: !!p.is_area, decimal: !!p.decimal, quantity: 1, width: null, height: null,
      unit_price: p.price, catalog_price: p.price, min_price: p.min_price || 0, discount_percent: p.discount || 0,
      default_discount: p.discount || 0, tax_rate: p.tax_rate, allow_override: p.allow_override !== false, manual_price: false, notes: ''
    }, extra);
  }

  function openArea(p) {
    $('areaProduct').textContent = p.name + ' — ' + money(p.price * (1 + p.tax_rate / 100)) + ' TTC / m²';
    $('aW').value = ''; $('aH').value = ''; $('aQ').value = '1'; areaPreview();
    bs('areaModal').show();
    setTimeout(function () { $('aW').focus(); }, 300);
  }
  function areaPreview() {
    var s = num($('aW').value) * num($('aH').value) / 10000 * Math.max(1, num($('aQ').value));
    var p = pendingArea;
    $('areaPreview').innerHTML = 'Surface : <strong>' + fmt(s).replace(/,?0+$/, '') + ' m²</strong>' + (p ? ' · ' + money(s * p.price * (1 + p.tax_rate / 100)) + ' TTC' : '');
  }
  ['aW', 'aH', 'aQ'].forEach(function (id) { $(id).addEventListener('input', areaPreview); });
  $('areaForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (num($('aW').value) <= 0 || num($('aH').value) <= 0) { return; }
    state.items.push(fromCatalog(pendingArea, { width: num($('aW').value), height: num($('aH').value), quantity: Math.max(1, Math.round(num($('aQ').value))) }));
    bs('areaModal').hide(); render(); flash(state.items.length - 1);
  });

  function openCustom() {
    $('customForm').reset(); bs('customModal').show();
    setTimeout(function () { $('cDesc').focus(); }, 300);
  }
  $('posCustomBtn').addEventListener('click', openCustom);
  $('customForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var rate = num($('cTax').value), price = num($('cPrice').value);
    if ($('cPriceMode').value === 'ttc' && rate > 0) { price = Math.round(price / (1 + rate / 100) * 10000) / 10000; }
    var unitSel = $('cUnit').selectedOptions[0];
    state.items.push({
      key: ++seq, type: 'custom', product_id: null, pack_id: null, name: $('cDesc').value.trim(), description: $('cDesc').value.trim(), code: '',
      unit: unitSel ? unitSel.dataset.symbol : 'u', unit_id: unitSel ? +unitSel.value : null, is_area: false, decimal: true,
      quantity: Math.max(0.001, num($('cQty').value)), unit_price: price, catalog_price: price, min_price: 0, discount_percent: 0, default_discount: 0,
      tax_rate: rate, allow_override: true, manual_price: false, notes: '', save_to_catalog: $('cSave') ? $('cSave').checked : false
    });
    bs('customModal').hide(); render(); flash(state.items.length - 1);
  });

  /* ------------------------------------------------------------------
     Rendu du panier
     ------------------------------------------------------------------ */
  var linesEl = $('posLines');

  function render() {
    var c = compute();
    if (!state.items.length) {
      linesEl.innerHTML = '<div class="pos-empty-cart"><i class="bi bi-cart3"></i><div>Panier vide</div><small>Touchez un article ou scannez un code-barres</small></div>';
    } else {
      linesEl.innerHTML = c.lines.map(function (l, i) {
        var it = l.it, canPrice = CFG.perms.override && it.allow_override, canDisc = CFG.perms.discount;
        var below = it.min_price > 0 && num(it.unit_price) * (1 - num(it.discount_percent) / 100) < it.min_price - 0.0049;
        return '<div class="pos-line' + (below ? ' warn' : '') + '" data-k="' + it.key + '">'
          + '<div class="pos-line-head"><div class="pos-line-name">' + esc(it.name) + (it.type === 'custom' ? ' <span class="badge text-bg-light border">libre</span>' : '')
          + (it.type === 'pack' ? ' <span class="badge text-bg-dark">pack</span>' : '') + (it.manual_price ? ' <span class="badge text-bg-warning">prix modifié</span>' : '')
          + '</div><button class="btn btn-sm btn-link text-danger p-0" data-act="remove" aria-label="Retirer"><i class="bi bi-x-lg"></i></button></div>'
          + (it.is_area ? '<div class="pos-line-dims"><input class="form-control form-control-sm" data-f="width" value="' + fmt(it.width).replace(',00', '') + '" inputmode="decimal" aria-label="Largeur cm"> × '
            + '<input class="form-control form-control-sm" data-f="height" value="' + fmt(it.height).replace(',00', '') + '" inputmode="decimal" aria-label="Hauteur cm"> cm'
            + ' <span class="text-body-secondary">= ' + fmt(billedQty(it)).replace(/,?0+$/, '') + ' m²</span></div>' : '')
          + '<div class="pos-line-body">'
          + '<div class="pos-qty"><button class="btn btn-sm btn-outline-secondary" data-act="minus" aria-label="Moins">−</button>'
          + '<input class="form-control form-control-sm" data-f="quantity" value="' + String(num(it.quantity)).replace('.', ',') + '" inputmode="decimal" aria-label="Quantité">'
          + '<button class="btn btn-sm btn-outline-secondary" data-act="plus" aria-label="Plus">+</button></div>'
          + '<div class="pos-price"><label>PU HT</label><input class="form-control form-control-sm" data-f="unit_price" value="' + fmt(it.unit_price) + '" inputmode="decimal"' + (canPrice ? '' : ' readonly') + ' aria-label="Prix unitaire HT"></div>'
          + '<div class="pos-disc' + (canDisc || num(it.discount_percent) ? '' : ' d-none') + '"><label>Rem. %</label><input class="form-control form-control-sm" data-f="discount_percent" value="' + String(num(it.discount_percent)).replace('.', ',') + '" inputmode="decimal"' + (canDisc ? '' : ' readonly') + ' aria-label="Remise %"></div>'
          + '<div class="pos-line-total">' + money(l.grossTtc) + '<small>TTC · TVA ' + String(num(it.tax_rate)).replace('.', ',') + ' %</small></div>'
          + '</div>'
          + (below ? '<div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> Sous le prix minimum (' + money(it.min_price) + ' HT)' + (CFG.perms.belowMin ? '' : ' : non autorisé') + '</div>' : '')
          + '<input class="form-control form-control-sm pos-line-note' + (it.notes ? '' : ' empty') + '" data-f="notes" value="' + esc(it.notes || '') + '" placeholder="+ précision (finition, couleur, délai…)" maxlength="255" aria-label="Précision">'
          + '</div>';
      }).join('');
    }
    $('posSubtotal').textContent = money(c.subtotal);
    $('posDiscountRow').classList.toggle('d-none', c.discount <= 0);
    $('posDiscount').textContent = '−' + money(c.discount);
    $('posTax').textContent = money(c.tax);
    $('posTotal').textContent = money(c.total);
    $('posPay').disabled = !state.items.length;
    $('posSuspend').disabled = !state.items.length;
    save();
    return c;
  }

  function flash(i) {
    var el = linesEl.children[i];
    if (el) { el.classList.add('added'); el.scrollIntoView({ block: 'nearest' }); setTimeout(function () { el.classList.remove('added'); }, 600); }
  }

  function itemByEl(el) { var k = +el.closest('.pos-line').dataset.k; return state.items.find(function (x) { return x.key === k; }); }

  linesEl.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) { return; }
    var it = itemByEl(b);
    if (b.dataset.act === 'remove') { state.items.splice(state.items.indexOf(it), 1); }
    if (b.dataset.act === 'plus') { it.quantity = num(it.quantity) + 1; }
    if (b.dataset.act === 'minus') { it.quantity = Math.max(it.decimal ? 0.001 : 1, num(it.quantity) - 1); }
    render();
  });
  linesEl.addEventListener('change', function (e) {
    var f = e.target.dataset.f; if (!f) { return; }
    var it = itemByEl(e.target);
    if (f === 'notes') { it.notes = e.target.value.trim(); save(); return; }
    var v = num(e.target.value);
    if (f === 'quantity') { v = it.decimal || it.is_area ? Math.max(0.001, v) : Math.max(1, Math.round(v)); if (it.is_area) { v = Math.max(1, Math.round(v)); } }
    if (f === 'unit_price') { v = Math.max(0, v); it.manual_price = Math.abs(v - it.catalog_price) > 0.0049; }
    if (f === 'discount_percent') { v = Math.max(0, Math.min(100, v)); if (v > CFG.config.maxDiscount && !CFG.perms.override) { alert('Remise maximale autorisée : ' + CFG.config.maxDiscount + ' %'); v = CFG.config.maxDiscount; } }
    if (f === 'width' || f === 'height') { v = Math.max(0.1, v); }
    it[f] = v; render();
  });
  linesEl.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.matches('input')) { e.target.blur(); } });

  $('posDiscountValue').addEventListener('input', function () { state.discount_value = num(this.value); render(); });
  $('posDiscountType').addEventListener('change', function () { state.discount_type = this.value; render(); });
  $('posNotesToggle').addEventListener('click', function () { $('posNotes').classList.toggle('d-none'); $('posNotes').focus(); });
  $('posNotes').addEventListener('input', function () { state.notes = this.value; save(); });

  function clearCart() {
    state.items = []; state.discount_value = 0; state.notes = ''; state.draft_id = null;
    $('posDiscountValue').value = ''; $('posNotes').value = ''; $('posNotes').classList.add('d-none');
    setCustomer({ id: CFG.walkIn.id, name: CFG.walkIn.name, walk_in: true }, false);
    render();
  }
  $('posClear').addEventListener('click', function () { if (!state.items.length || confirm('Vider le panier ?')) { clearCart(); search.focus(); } });

  /* ------------------------------------------------------------------
     Client
     ------------------------------------------------------------------ */
  var custBox = $('posCustomerLookup'), custInfo = $('posCustomerInfo');

  function setCustomer(c, reprice) {
    state.customer = c;
    custBox.querySelector('[data-lookup-input]').value = c.walk_in ? '' : c.name;
    custBox.querySelector('[data-lookup-value]').value = c.walk_in ? '' : c.id;
    renderCustomer();
    if (reprice !== false) { repriceCart(); loadProducts(search.value.trim(), search.value.trim() ? '' : currentCat); }
    save();
  }
  function renderCustomer() {
    var c = state.customer;
    if (c.walk_in || !c.balance && c.balance !== 0) {
      custInfo.innerHTML = c.walk_in ? '<span class="text-body-secondary small"><i class="bi bi-shop"></i> Client comptoir — tarif détail</span>' : '';
      return;
    }
    custInfo.innerHTML = '<span class="badge text-bg-light border">' + esc(c.price_list || 'Détail') + '</span>'
      + (c.group_discount ? ' <span class="badge text-bg-light border">Remise groupe ' + String(c.group_discount).replace('.', ',') + ' %</span>' : '')
      + ' <span class="small ' + (c.balance > 0.004 ? 'text-danger' : 'text-body-secondary') + '">Solde ' + money(c.balance) + '</span>'
      + (c.credit_limit > 0 ? ' <span class="small text-body-secondary">· crédit dispo. ' + money(c.credit_available) + '</span>' : '')
      + (c.points ? ' <span class="small text-body-secondary">· ' + c.points + ' pts</span>' : '');
  }
  custBox.addEventListener('hoc:lookup', function (e) {
    HOC.fetchJson(HOC.withId(CFG.urls.customer, e.detail.id)).then(function (d) { setCustomer(d.customer, true); });
  });
  $('posCustomerReset').addEventListener('click', function () { setCustomer({ id: CFG.walkIn.id, name: CFG.walkIn.name, walk_in: true }, true); });

  /** Applique les prix du client aux lignes dont le prix n'a pas été modifié manuellement. */
  function repriceCart() {
    var ids = state.items.filter(function (it) { return it.type === 'product'; }).map(function (it) { return it.product_id; });
    if (!ids.length) { render(); return; }
    HOC.fetchJson(CFG.urls.prices, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ customer_id: state.customer.id, product_ids: ids }) })
      .then(function (d) {
        state.items.forEach(function (it) {
          var p = it.type === 'product' ? d.prices[it.product_id] : null; if (!p) { return; }
          if (!it.manual_price) { it.unit_price = p.price; }
          if (num(it.discount_percent) === num(it.default_discount)) { it.discount_percent = p.discount; }
          it.catalog_price = p.price; it.default_discount = p.discount; it.min_price = p.min_price; it.tax_rate = p.tax_rate;
        });
        render();
      });
  }

  /* ------------------------------------------------------------------
     Encaissement
     ------------------------------------------------------------------ */
  var payState = {};

  function openPay() {
    if (!state.items.length) { return; }
    var c = compute();
    payState = { total: c.total, amounts: {}, refs: {} };
    var cashM = CFG.methods.find(function (m) { return m.cash; });
    if (cashM) { payState.amounts[cashM.id] = c.total; }
    $('payTotal').textContent = money(c.total);
    $('payError').classList.add('d-none');
    var cust = state.customer;
    $('payCreditInfo').innerHTML = cust.walk_in ? 'Client comptoir : paiement intégral requis (sélectionnez un client pour une vente à crédit).'
      : (cust.credit_limit > 0 ? 'Crédit disponible : <strong>' + money(cust.credit_available) + '</strong>' : 'Aucun crédit autorisé pour ce client.');
    renderPay();
    bs('payModal').show();
    setTimeout(function () { var f = $('payMethods').querySelector('input'); if (f) { f.focus(); f.select(); } }, 350);
  }

  function renderPay() {
    var total = payState.total;
    $('payMethods').innerHTML = CFG.methods.filter(function (m) { return !m.credit; }).map(function (m) {
      var v = payState.amounts[m.id];
      var quick = m.cash ? [total, Math.ceil(total / 10) * 10, Math.ceil(total / 50) * 50, Math.ceil(total / 100) * 100, Math.ceil(total / 200) * 200]
        .filter(function (x, i, a) { return a.indexOf(x) === i; }).slice(0, 4) : [];
      return '<div class="pos-pay-method' + (v ? ' active' : '') + '" data-m="' + m.id + '">'
        + '<div class="d-flex align-items-center gap-2"><strong class="flex-grow-1"><i class="bi ' + ({ cash: 'bi-cash', card: 'bi-credit-card', transfer: 'bi-bank', cheque: 'bi-journal-text', mobile: 'bi-phone' }[m.code] || 'bi-wallet2') + '"></i> ' + esc(m.name) + '</strong>'
        + '<input class="form-control" style="max-width:150px" data-amount inputmode="decimal" placeholder="0,00" value="' + (v ? fmt(v) : '') + '" aria-label="Montant ' + esc(m.name) + '">'
        + '<button class="btn btn-sm btn-outline-dark" type="button" data-rest title="Reste à payer">Solde</button></div>'
        + (m.ref ? '<input class="form-control form-control-sm mt-2' + (v ? '' : ' d-none') + '" data-ref placeholder="Référence (n° chèque / virement / transaction) *" value="' + esc(payState.refs[m.id] || '') + '" maxlength="100">' : '')
        + (quick.length ? '<div class="pos-quick-cash mt-2">' + quick.map(function (q) { return '<button type="button" class="btn btn-sm btn-light border" data-quick="' + q + '">' + fmt(q).replace(',00', '') + '</button>'; }).join('') + '</div>' : '')
        + '</div>';
    }).join('');
    updatePaySummary();
  }

  function paidSum() { return r2(Object.keys(payState.amounts).reduce(function (s, k) { return s + num(payState.amounts[k]); }, 0)); }

  function updatePaySummary() {
    var received = paidSum(), total = payState.total;
    var due = r2(Math.max(0, total - received)), change = r2(Math.max(0, received - total));
    $('payReceived').textContent = money(received);
    $('payDue').textContent = money(due);
    $('payDueRow').classList.toggle('text-danger', due > 0);
    $('payChangeRow').classList.toggle('d-none', change <= 0);
    $('payChange').textContent = money(change);
    var cust = state.customer, err = '';
    var cashIn = CFG.methods.filter(function (m) { return m.cash; }).reduce(function (t, m) { return t + num(payState.amounts[m.id]); }, 0);
    if (change > cashIn + 0.004) { err = 'Le montant reçu dépasse le total : seul un paiement en espèces peut donner lieu à un rendu de monnaie.'; }
    CFG.methods.forEach(function (m) {
      if (!err && m.ref && num(payState.amounts[m.id]) > 0 && !(payState.refs[m.id] || '').trim()) { err = 'Saisissez la référence du paiement « ' + m.name + ' » (n° de chèque, de virement ou de transaction).'; }
    });
    if (due > 0.004) {
      if (!CFG.perms.credit) { err = 'Paiement incomplet : vous n\'êtes pas autorisé à vendre à crédit.'; }
      else if (cust.walk_in) { err = 'Paiement incomplet : sélectionnez un client enregistré pour laisser ' + money(due) + ' à crédit.'; }
      else if (!(cust.credit_limit > 0)) { err = 'Ce client n\'a pas de crédit autorisé.'; }
      else if (due > cust.credit_available + 0.004) { err = 'Limite de crédit dépassée (disponible ' + money(cust.credit_available) + ').'; }
    }
    $('payConfirm').innerHTML = due > 0.004 && !err ? '<i class="bi bi-check2-circle"></i> Valider (' + money(due) + ' à crédit)' : '<i class="bi bi-check2-circle"></i> Valider la vente';
    var box = $('payError'); box.textContent = err; box.classList.toggle('d-none', !err);
    $('payConfirm').disabled = !!err;
  }

  $('payMethods').addEventListener('input', function (e) {
    var row = e.target.closest('[data-m]'); if (!row) { return; }
    var id = row.dataset.m;
    if (e.target.matches('[data-amount]')) {
      payState.amounts[id] = num(e.target.value);
      row.classList.toggle('active', payState.amounts[id] > 0);
      var ref = row.querySelector('[data-ref]'); if (ref) { ref.classList.toggle('d-none', !(payState.amounts[id] > 0)); }
      updatePaySummary();
    }
    if (e.target.matches('[data-ref]')) { payState.refs[id] = e.target.value; updatePaySummary(); }
  });
  $('payMethods').addEventListener('click', function (e) {
    var row = e.target.closest('[data-m]'); if (!row) { return; }
    var id = row.dataset.m, input = row.querySelector('[data-amount]');
    if (e.target.closest('[data-quick]')) { payState.amounts[id] = num(e.target.closest('[data-quick]').dataset.quick); }
    else if (e.target.closest('[data-rest]')) {
      var others = paidSum() - num(payState.amounts[id]);
      payState.amounts[id] = r2(Math.max(0, payState.total - others));
    } else { return; }
    input.value = payState.amounts[id] ? fmt(payState.amounts[id]) : '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });

  function payload() {
    return {
      customer_id: state.customer.id, draft_id: state.draft_id, notes: state.notes,
      discount_type: num(state.discount_value) > 0 ? state.discount_type : 'none', discount_value: num(state.discount_value),
      items: state.items.map(function (it) {
        return { type: it.type, product_id: it.product_id, pack_id: it.pack_id, description: it.description || it.name, quantity: num(it.quantity),
          width: it.width, height: it.height, unit_price: num(it.unit_price), discount_percent: num(it.discount_percent), tax_rate: num(it.tax_rate),
          unit_id: it.unit_id || null, notes: it.notes || '', save_to_catalog: !!it.save_to_catalog };
      })
    };
  }

  var busy = false, lastSale = null;
  $('payConfirm').addEventListener('click', function () {
    if (busy) { return; }
    var p = payload();
    p.payments = Object.keys(payState.amounts).filter(function (k) { return num(payState.amounts[k]) > 0; })
      .map(function (k) { return { method_id: +k, amount: num(payState.amounts[k]), reference: payState.refs[k] || '' }; });
    busy = true; $('payConfirm').disabled = true; $('payConfirm').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement…';
    HOC.fetchJson(CFG.urls.checkout, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(p) })
      .then(function (d) {
        lastSale = d.sale;
        bs('payModal').hide();
        $('doneNumber').textContent = 'Ticket ' + d.sale.number + ' · ' + money(d.sale.total);
        $('doneChange').classList.toggle('d-none', !(d.sale.change > 0));
        $('doneChange').innerHTML = 'Monnaie à rendre<strong>' + money(d.sale.change) + '</strong>';
        $('doneDue').classList.toggle('d-none', !(d.sale.due > 0));
        $('doneDue').textContent = 'Reste dû par le client : ' + money(d.sale.due) + ' (ajouté à son compte).';
        $('doneA4').href = d.sale.a4_url; $('doneShow').href = d.sale.show_url;
        clearCart();
        bs('doneModal').show();
        if (CFG.config.autoPrint) { printReceipt(CFG.config.receiptWidth); }
        loadProducts('', currentCat); // stocks à jour
      })
      .catch(function (err) {
        var box = $('payError'); box.textContent = err.message || 'Erreur lors de l\'enregistrement.'; box.classList.remove('d-none');
        $('payConfirm').disabled = false; updatePaySummary();
      })
      .finally(function () { busy = false; });
  });

  /** Impression du ticket dans une fenêtre dédiée (évite d'imprimer le POS). */
  function printReceipt(w) {
    if (!lastSale) { return; }
    var u = lastSale.receipt_url + (lastSale.receipt_url.indexOf('?') >= 0 ? '&' : '?') + 'w=' + w + '&print=1';
    window.open(u, 'hoc_receipt', 'width=420,height=700');
  }
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { printReceipt(+b.dataset.print); }); });
  // « Entrée » pour enchaîner la vente suivante, même pendant l'animation d'ouverture
  var doneShown = false, doneClosePending = false;
  function closeDone() { if (doneShown) { bs('doneModal').hide(); } else { doneClosePending = true; } }
  $('doneModal').addEventListener('shown.bs.modal', function () { doneShown = true; if (doneClosePending) { doneClosePending = false; bs('doneModal').hide(); } });
  $('doneModal').addEventListener('hidden.bs.modal', function () { doneShown = false; search.focus(); });
  $('posPay').addEventListener('click', openPay);

  /* ------------------------------------------------------------------
     Ventes en attente
     ------------------------------------------------------------------ */
  $('posSuspend').addEventListener('click', function () {
    if (!state.items.length) { return; }
    var label = prompt('Nom de la vente en attente (ex. client au téléphone, BAT à valider) :', state.customer.walk_in ? '' : state.customer.name);
    if (label === null) { return; }
    var p = payload(); p.label = label;
    HOC.fetchJson(CFG.urls.suspend, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(p) })
      .then(function () { clearCart(); toast('Vente mise en attente.'); })
      .catch(function (err) { alert(err.message); });
  });

  $('posDrafts').addEventListener('click', function () {
    $('draftsList').innerHTML = '<div class="p-4 text-center"><span class="spinner-border"></span></div>';
    bs('draftsModal').show();
    HOC.fetchJson(CFG.urls.drafts).then(function (d) {
      if (!d.drafts.length) { $('draftsList').innerHTML = '<div class="hoc-empty"><i class="bi bi-inboxes"></i>Aucune vente en attente.</div>'; return; }
      $('draftsList').innerHTML = '<div class="list-group list-group-flush">' + d.drafts.map(function (x) {
        return '<div class="list-group-item d-flex align-items-center gap-3"><div class="flex-grow-1"><strong>' + esc(x.label || x.customer) + '</strong>'
          + '<div class="small text-body-secondary">' + esc(x.customer) + ' · ' + x.lines + ' ligne(s) · ' + esc(x.date) + ' · ' + esc(x.user || '') + '</div></div>'
          + '<strong>' + money(x.total) + '</strong><button class="btn btn-sm btn-dark" data-resume="' + x.id + '">Reprendre</button>'
          + '<button class="btn btn-sm btn-outline-danger" data-del="' + x.id + '" aria-label="Supprimer"><i class="bi bi-trash"></i></button></div>';
      }).join('') + '</div>';
    });
  });
  $('draftsList').addEventListener('click', function (e) {
    var r = e.target.closest('[data-resume]'), del = e.target.closest('[data-del]');
    if (r) {
      if (state.items.length && !confirm('Le panier actuel sera remplacé. Continuer ?')) { return; }
      HOC.fetchJson(HOC.withId(CFG.urls.resume, r.dataset.resume)).then(function (d) {
        var dr = d.draft;
        state.items = dr.items.map(function (it) {
          return Object.assign(it, { key: ++seq, manual_price: Math.abs(it.unit_price - it.catalog_price) > 0.0049, default_discount: it.discount_percent, min_price: 0,
            name: it.type === 'custom' ? it.description : it.name });
        });
        state.discount_type = dr.discount_type === 'none' ? 'percent' : dr.discount_type; state.discount_value = dr.discount_value;
        state.notes = dr.notes || ''; state.draft_id = dr.id;
        $('posDiscountType').value = state.discount_type; $('posDiscountValue').value = state.discount_value || '';
        $('posNotes').value = state.notes;
        HOC.fetchJson(HOC.withId(CFG.urls.customer, dr.customer_id)).then(function (c) { setCustomer(c.customer, false); render(); });
        bs('draftsModal').hide(); render(); toast('Vente reprise : validez-la ou remettez-la en attente.');
      });
    }
    if (del && confirm('Supprimer définitivement cette vente en attente ?')) {
      HOC.fetchJson(HOC.withId(CFG.urls.deleteDraft, del.dataset.del), { method: 'POST' }).then(function () { del.closest('.list-group-item').remove(); });
    }
  });

  function toast(msg) {
    var t = document.createElement('div'); t.className = 'pos-toast'; t.textContent = msg; document.body.appendChild(t);
    setTimeout(function () { t.classList.add('show'); }, 10);
    setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, 2500);
  }

  /* ------------------------------------------------------------------
     Raccourcis clavier
     ------------------------------------------------------------------ */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'F2') { e.preventDefault(); search.focus(); search.select(); }
    if (e.key === 'F9') { e.preventDefault(); if (!document.querySelector('.modal.show')) { openPay(); } }
    if (e.key === 'Enter' && $('doneModal').classList.contains('show')) { e.preventDefault(); closeDone(); }
  });

  /* ------------------------------------------------------------------
     Démarrage
     ------------------------------------------------------------------ */
  restore();
  $('posDiscountType').value = state.discount_type || 'percent';
  $('posDiscountValue').value = state.discount_value || '';
  if (state.notes) { $('posNotes').value = state.notes; $('posNotes').classList.remove('d-none'); }
  if (state.customer && !state.customer.walk_in) {
    HOC.fetchJson(HOC.withId(CFG.urls.customer, state.customer.id))
      .then(function (d) { setCustomer(d.customer, false); repriceCart(); })
      .catch(function () { setCustomer({ id: CFG.walkIn.id, name: CFG.walkIn.name, walk_in: true }, true); });
  } else {
    state.customer = { id: CFG.walkIn.id, name: CFG.walkIn.name, walk_in: true }; renderCustomer();
  }
  render();
  loadProducts('', currentCat);
  if (!document.getElementById('posClosed')) { search.focus(); }
})();
