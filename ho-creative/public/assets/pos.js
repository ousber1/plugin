/* HO CREATIVE – point of sale. Totals shown here are for display only:
   the server re-prices every line from the database when the sale is recorded. */
(function () {
  'use strict';
  const root = document.getElementById('pos');
  if (!root) return;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const csrf = $('meta[name=csrf-token]').content;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const products = JSON.parse(root.dataset.products);
  const byId = Object.fromEntries(products.map((p) => [p.id, p]));
  const canDiscount = root.dataset.canDiscount === '1';
  const cents = (v) => Math.round((+v || 0) * 100);
  const fmt = (c) => { const neg = c < 0; c = Math.abs(c); const s = String(Math.floor(c / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); return (neg ? '-' : '') + s + ',' + String(c % 100).padStart(2, '0') + ' DH'; };
  const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx'.replace(/x/g, () => ((Math.random() * 16) | 0).toString(16)));

  let lines = [];      // {id, qty, disc}
  let payments = [];   // {method, amount(cents), reference}
  let method = 'cash';
  let customer = null;
  let cat = '';
  let saleKey = uuid();
  let busy = false;

  /* ---------- product grid ---------- */
  const grid = $('#grid');
  const renderGrid = () => {
    const q = $('#scan').value.trim().toLowerCase();
    const list = products.filter((p) => (!cat || String(p.cat) === cat) && (!q || p.name.toLowerCase().includes(q) || (p.sku || '').toLowerCase().includes(q) || (p.barcode || '') === q)).slice(0, 300);
    grid.innerHTML = list.length ? list.map((p) => {
      const low = p.stock !== null && p.stock <= 0;
      return `<button type="button" class="pos-item ${low ? 'out' : ''}" data-id="${p.id}">
        <span class="swatch" style="background:${esc(catColor(p.cat))}"></span><b>${esc(p.name)}</b>
        <span class="s">${esc(p.sku)}</span>
        <span class="p">${fmt(cents(p.price))}</span>
        <span class="s ${p.stock !== null && p.stock <= 3 ? 'low' : ''}">${p.stock === null ? 'Sans suivi de stock' : 'Stock : ' + (+p.stock)}</span></button>`;
    }).join('') : '<div class="pos-empty" style="grid-column:1/-1">Aucun article trouvé.</div>';
  };
  const catColors = Object.fromEntries($$('#cats button[data-cat]').filter((b) => b.dataset.cat).map((b) => [b.dataset.cat, b.querySelector('i')?.style.background || '#16193A']));
  const catColor = (id) => catColors[id] || '#C9CBE3';
  grid.addEventListener('click', (e) => { const b = e.target.closest('.pos-item'); if (b) add(+b.dataset.id); });
  $('#cats').addEventListener('click', (e) => {
    const b = e.target.closest('button'); if (!b) return;
    cat = b.dataset.cat; $$('#cats button').forEach((x) => x.classList.toggle('active', x === b)); renderGrid();
  });
  $('#scan').addEventListener('input', renderGrid);
  $('#scan').addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const q = e.target.value.trim(); if (!q) return;
    const exact = products.find((p) => p.barcode === q || (p.sku || '').toLowerCase() === q.toLowerCase());
    const first = exact || (grid.querySelectorAll('.pos-item').length === 1 ? byId[+grid.querySelector('.pos-item').dataset.id] : null);
    if (first) { add(first.id); e.target.value = ''; renderGrid(); } else { err('Code inconnu : ' + q); }
  });

  /* ---------- ticket lines ---------- */
  const add = (id, qty = 1) => {
    const p = byId[id]; if (!p) return;
    const l = lines.find((x) => x.id === id);
    if (l) l.qty = +(l.qty + qty).toFixed(3); else lines.push({ id, qty, disc: 0 });
    err(''); render();
  };
  const lineTotal = (l) => Math.max(0, Math.round(cents(byId[l.id].price) * l.qty) - cents(l.disc));
  const render = () => {
    const box = $('#lines');
    box.innerHTML = lines.length ? lines.map((l, i) => {
      const p = byId[l.id];
      return `<div class="pos-line" data-i="${i}">
        <div><div class="n">${esc(p.name)}</div><div class="u">${fmt(cents(p.price))} × ${l.qty} ${p.unit !== 'piece' ? esc(p.unit) : ''} · TVA ${+p.tax} %</div></div>
        <div class="t">${fmt(lineTotal(l))}</div>
        <div class="tools">
          <span class="qty"><button type="button" data-a="dec" aria-label="Moins">−</button><input type="number" min="0" step="${p.unit === 'piece' ? 1 : 0.001}" value="${l.qty}" data-a="qty" aria-label="Quantité"><button type="button" data-a="inc" aria-label="Plus">+</button></span>
          ${canDiscount ? `<input class="disc" type="number" min="0" step="0.01" value="${l.disc || ''}" placeholder="Remise DH" data-a="disc" aria-label="Remise">` : ''}
          <button type="button" class="btn btn-ghost btn-sm" data-a="del" style="margin-inline-start:auto" aria-label="Retirer">✕</button>
        </div></div>`;
    }).join('') : `<div class="pos-empty"><svg class="ic"><use href="/assets/icons.svg?v=2#cart"/></svg><p>Scannez ou touchez un article pour commencer.</p></div>`;
    totals();
  };
  $('#lines').addEventListener('click', (e) => {
    const b = e.target.closest('[data-a]'); if (!b || b.tagName === 'INPUT') return;
    const i = +b.closest('.pos-line').dataset.i; const p = byId[lines[i].id]; const step = p.unit === 'piece' ? 1 : 0.1;
    if (b.dataset.a === 'inc') lines[i].qty = +(lines[i].qty + step).toFixed(3);
    if (b.dataset.a === 'dec') lines[i].qty = +(lines[i].qty - step).toFixed(3);
    if (b.dataset.a === 'del' || lines[i].qty <= 0) lines.splice(i, 1);
    render();
  });
  $('#lines').addEventListener('change', (e) => {
    const inp = e.target; if (!inp.dataset.a) return;
    const i = +inp.closest('.pos-line').dataset.i;
    if (inp.dataset.a === 'qty') { lines[i].qty = Math.max(0, +inp.value || 0); if (!lines[i].qty) lines.splice(i, 1); }
    if (inp.dataset.a === 'disc') lines[i].disc = Math.max(0, +inp.value || 0);
    render();
  });

  /* ---------- totals & payment ---------- */
  let total = 0;
  const totals = () => {
    let ht = 0, tax = 0, disc = 0; total = 0;
    lines.forEach((l) => {
      const t = lineTotal(l); const r = +byId[l.id].tax;
      const tx = Math.round(t - t / (1 + r / 100)); total += t; tax += tx; ht += t - tx; disc += cents(l.disc);
    });
    $('#s-ht').textContent = fmt(ht); $('#s-tax').textContent = fmt(tax); $('#s-total').textContent = fmt(total);
    $('#s-disc').textContent = '−' + fmt(disc); $('#s-disc-row').hidden = !disc; $('#disc-reason').hidden = !disc;
    $('#pay-total').textContent = lines.length ? fmt(total) : '';
    const paid = payments.reduce((a, p) => a + p.amount, 0) + (cents($('#tender').value) || (method !== 'cash' && !payments.length ? total : 0));
    const change = paid - total;
    $('#change-v').textContent = fmt(Math.max(change, 0));
    $('#change').classList.toggle('neg', change < 0 && lines.length > 0);
    $('#change').firstElementChild.textContent = change < 0 && lines.length ? 'Reste à payer' : 'Rendu monnaie';
    if (change < 0) $('#change-v').textContent = fmt(-change);
    $('#pay').disabled = !lines.length || busy;
    $('#pays').innerHTML = payments.map((p, i) => `<div class="flex between"><span>${esc(label(p.method))} ${p.reference ? '· ' + esc(p.reference) : ''}</span><span>${fmt(p.amount)} <button type="button" class="btn btn-ghost btn-sm" data-rm="${i}">✕</button></span></div>`).join('');
  };
  const label = (m) => ({ cash: 'Espèces', card_in_store: 'Carte', cheque: 'Chèque', bank_transfer: 'Virement' }[m] || m);
  $$('.pay-methods button').forEach((b) => b.addEventListener('click', () => {
    method = b.dataset.m; $$('.pay-methods button').forEach((x) => x.classList.toggle('active', x === b));
    $('#cash-keys').hidden = method !== 'cash'; totals();
  }));
  $('#tender').addEventListener('input', totals);
  $('#cash-keys').addEventListener('click', (e) => {
    const k = e.target.dataset.k; if (!k) return;
    const due = total - payments.reduce((a, p) => a + p.amount, 0);
    $('#tender').value = k === 'exact' ? (Math.max(due, 0) / 100).toFixed(2) : (+$('#tender').value || 0) + +k; totals();
  });
  $('#add-pay').addEventListener('click', () => {
    const a = cents($('#tender').value); if (!a) return;
    payments.push({ method, amount: a, reference: $('#ref').value.trim() || null }); $('#tender').value = ''; $('#ref').value = ''; totals();
  });
  $('#pays').addEventListener('click', (e) => { if (e.target.dataset.rm !== undefined) { payments.splice(+e.target.dataset.rm, 1); totals(); } });
  $('#clear').addEventListener('click', () => { if (!lines.length || confirm('Vider le ticket ?')) reset(); });

  const err = (m) => { $('#pos-err').textContent = m; };
  const reset = () => { lines = []; payments = []; customer = null; saleKey = uuid(); $('#tender').value = ''; $('#ref').value = ''; $('#disc-reason').value = ''; showCustomer(); render(); $('#scan').focus(); };

  const pay = async () => {
    if (!lines.length || busy) return;
    const list = payments.slice();
    const pending = cents($('#tender').value);
    const already = list.reduce((a, p) => a + p.amount, 0);
    if (pending) list.push({ method, amount: pending, reference: $('#ref').value.trim() || null });
    else if (total - already > 0) {
      if (method === 'cash') { err('Saisissez le montant reçu en espèces (ou « Exact »).'); return; }
      list.push({ method, amount: total - already, reference: $('#ref').value.trim() || null });
    }
    busy = true; totals(); err('');
    try {
      const r = await fetch(root.dataset.sell, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ key: saleKey, register: root.dataset.register, customer_id: customer?.id || null, discount_reason: $('#disc-reason').value || null,
          lines: lines.map((l) => ({ product_id: l.id, quantity: l.qty, discount: l.disc || 0 })),
          payments: list.map((p) => ({ method: p.method, amount: (p.amount / 100).toFixed(2), reference: p.reference })) }) });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) { err(j.message && !j.errors ? j.message : Object.values(j.errors || {}).flat().join(' ') || 'Erreur ' + r.status); return; }
      lines.forEach((l) => { if (byId[l.id].stock !== null) byId[l.id].stock = +(byId[l.id].stock - l.qty).toFixed(3); });
      $('#done-num').textContent = j.number + ' · ' + j.total;
      $('#done-change').textContent = j.change;
      $('#done-ticket').href = j.ticket_url; $('#done-sale').href = j.sale_url;
      $('#done').hidden = false; $('#done-new').focus();
      reset(); renderGrid();
    } catch (e) { err('Connexion perdue : la vente n\'a peut-être pas été enregistrée. Réessayez, elle ne sera pas dupliquée.'); }
    finally { busy = false; totals(); }
  };
  $('#pay').addEventListener('click', pay);
  $('#done-new').addEventListener('click', () => { $('#done').hidden = true; $('#scan').focus(); });

  /* ---------- customer ---------- */
  let t;
  const sugg = $('#cust-sugg');
  const showCustomer = () => {
    $('#cust-chip').hidden = !customer;
    $('#cust-chip').innerHTML = customer ? `<span class="pill pill-info">${esc(customer.label)}${customer.phone ? ' · ' + esc(customer.phone) : ''}</span> <button type="button" class="btn btn-ghost btn-sm" id="cust-x">Retirer</button>` : '';
    $('#cust').value = '';
  };
  $('#cust-chip').addEventListener('click', (e) => { if (e.target.id === 'cust-x') { customer = null; showCustomer(); } });
  $('#cust').addEventListener('input', (e) => {
    clearTimeout(t); const q = e.target.value.trim(); if (q.length < 2) { sugg.hidden = true; return; }
    t = setTimeout(async () => {
      const r = await fetch(root.dataset.customers + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
      const list = await r.json();
      sugg.innerHTML = list.length ? list.map((c) => `<button type="button" data-c='${esc(JSON.stringify(c))}'><b>${esc(c.label)}</b><br><span class="tiny muted">${esc(c.phone || '')} ${c.ice ? '· ICE ' + esc(c.ice) : ''}</span></button>`).join('') : '<div class="small muted" style="padding:10px 14px">Aucun client. Utilisez + pour le créer.</div>';
      sugg.hidden = false;
    }, 220);
  });
  sugg.addEventListener('click', (e) => { const b = e.target.closest('[data-c]'); if (!b) return; customer = JSON.parse(b.dataset.c); sugg.hidden = true; showCustomer(); });
  $('#cust-new').addEventListener('click', () => {
    const wrap = document.createElement('div'); wrap.className = 'pos-done'; wrap.innerHTML = $('#new-cust-tpl').innerHTML; document.body.appendChild(wrap);
    const f = wrap.querySelector('form'); f.querySelector('[name=name]').value = $('#cust').value; f.querySelector('[name=name]').focus();
    wrap.querySelector('[data-close]').onclick = () => wrap.remove();
    f.onsubmit = async (e) => {
      e.preventDefault();
      const r = await fetch(root.dataset.customerStore, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: new FormData(f) });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) { alert(Object.values(j.errors || {}).flat().join(' ') || 'Erreur'); return; }
      customer = j; showCustomer(); wrap.remove();
    };
  });

  /* ---------- keyboard ---------- */
  document.addEventListener('keydown', (e) => {
    if (e.key === 'F2') { e.preventDefault(); $('#scan').focus(); }
    if (e.key === 'F9') { e.preventDefault(); pay(); }
    if (e.key === 'Enter' && !$('#done').hidden) { e.preventDefault(); $('#done-new').click(); }
    if (e.key === 'Escape') sugg.hidden = true;
  });

  renderGrid(); render();
})();
