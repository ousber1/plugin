/* HO CREATIVE – lightweight progressive enhancement (no framework, no build step). */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const csrf = () => ($('meta[name=csrf-token]') || {}).content || '';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---- Product configurator: every change is priced by the server ---- */
  const form = $('#configurator');
  if (form) {
    const box = $('#price-box');
    const t = JSON.parse(form.dataset.i18n || '{}');
    let timer, seq = 0;
    const collect = () => {
      const fd = new FormData(form);
      const data = { options: {} };
      for (const [k, v] of fd.entries()) {
        const m = k.match(/^options\[(.+)\]$/);
        if (m) data.options[m[1]] = v; else if (k !== '_token') data[k] = v;
      }
      const qc = $('#qty-custom', form);
      if (qc && !qc.hidden && qc.value) data.quantity = qc.value;
      return data;
    };
    const toggleCustom = () => {
      const size = $('[name=size]', form);
      const custom = $('#custom-size', form);
      if (size && custom) custom.hidden = size.value !== 'custom';
      const qs = $('#qty-select', form), qc = $('#qty-custom', form);
      if (qs && qc) { qc.hidden = qs.value !== 'custom'; qc.disabled = qc.hidden; qs.name = qs.value === 'custom' ? '' : 'quantity'; if (!qc.hidden) qc.name = 'quantity'; }
    };
    const render = (r) => {
      (r.ui.options || []).forEach((o) => {
        const g = $(`[data-option="${CSS.escape(o.code)}"]`, form);
        if (!g) return;
        g.hidden = !o.visible;
        $$('input,select', g).forEach((i) => (i.disabled = !o.visible));
        o.choices.forEach((c) => {
          const el = $(`[data-choice="${CSS.escape(c.code)}"]`, g);
          if (el) {
            el.classList.toggle('unavailable', !c.available);
            const inp = $('input', el); if (inp) inp.disabled = !c.available || !o.visible;
          }
          const opt = $(`option[value="${CSS.escape(c.code)}"]`, g);
          if (opt) { opt.disabled = !c.available; opt.hidden = !c.available; }
        });
        if (o.value) {
          const r2 = $(`input[value="${CSS.escape(o.value)}"]`, g); if (r2) r2.checked = true;
          const sel = $('select', g); if (sel) sel.value = o.value;
        }
      });
      let html = '';
      if (!r.valid) {
        html = `<div class="err" role="alert"><strong>${esc(t.invalid)}</strong><br>${r.errors.map(esc).join('<br>')}</div>`;
      } else {
        html = `<div class="small">${esc(t.total)}</div><div class="total">${esc(r.total_formatted)}</div>
          <div class="small">${esc(t.unit)} : ${esc(r.unit_formatted)} · ${esc(r.quantity)} ${esc(t.pcs)}${r.area_m2 && +r.area_m2 > 0 ? ' · ' + esc(r.area_m2) + ' m²' : ''}</div>
          <div class="rows">${r.components.map((c) => `<div><span>${esc(c.label)}</span><span>${esc(c.formatted)}</span></div>`).join('')}</div>
          <div class="small">⏱ ${esc(t.days.replace(':n', r.production_days))}</div>`;
      }
      box.innerHTML = html;
      const back = $('#back-upload'); if (back) back.hidden = !r.two_sided;
      $$('[data-needs-valid]', form).forEach((b) => (b.disabled = !r.valid));
    };
    const price = () => {
      toggleCustom();
      const my = ++seq;
      box.classList.add('loading');
      fetch(form.dataset.priceUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: JSON.stringify(collect()) })
        .then((r) => r.json()).then((r) => { if (my === seq) render(r); })
        .catch(() => { box.innerHTML = `<div class="err">${esc(t.network)}</div>`; })
        .finally(() => box.classList.remove('loading'));
    };
    form.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(price, 120); });
    form.addEventListener('input', (e) => { if (e.target.matches('input[type=number]')) { clearTimeout(timer); timer = setTimeout(price, 450); } });
    $$('[name=artwork_mode]', form).forEach((r) => r.addEventListener('change', () => {
      $$('[data-mode-panel]', form).forEach((p) => (p.hidden = p.dataset.modePanel !== r.value || !r.checked));
    }));
    const checked = $('[name=artwork_mode]:checked', form);
    if (checked) checked.dispatchEvent(new Event('change'));
    price();
  }

  /* ---- Upload with progress (XHR) ---- */
  $$('form[data-upload]').forEach((f) => {
    const dz = $('.dropzone', f);
    if (dz) {
      ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('drag'); }));
      ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('drag'); }));
      dz.addEventListener('drop', (e) => { const inp = $('input[type=file]', f); if (inp && e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; } });
    }
    f.addEventListener('submit', (e) => {
      if (!window.FormData || !window.XMLHttpRequest) return;
      e.preventDefault();
      const bar = $('.progress i', f), msg = $('.upload-msg', f), btn = $('[type=submit]', f);
      const xhr = new XMLHttpRequest();
      xhr.open(f.method || 'POST', f.action);
      xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = (ev) => { if (bar && ev.lengthComputable) bar.style.width = Math.round((ev.loaded / ev.total) * 100) + '%'; };
      xhr.onload = () => {
        if (btn) btn.disabled = false;
        let r = {}; try { r = JSON.parse(xhr.responseText); } catch (_) {}
        if (xhr.status >= 200 && xhr.status < 300) { if (r.redirect) location.href = r.redirect; else location.reload(); }
        else if (msg) { msg.className = 'upload-msg error-text'; msg.textContent = r.message || (r.errors ? Object.values(r.errors).flat().join(' ') : 'Erreur ' + xhr.status); }
      };
      xhr.onerror = () => { if (btn) btn.disabled = false; if (msg) msg.textContent = 'Erreur réseau'; };
      if (btn) btn.disabled = true;
      xhr.send(new FormData(f));
    });
  });

  /* ---- Tabs ---- */
  $$('[role=tablist]').forEach((tl) => {
    const tabs = $$('[role=tab]', tl);
    tabs.forEach((tab) => tab.addEventListener('click', () => {
      tabs.forEach((x) => { x.setAttribute('aria-selected', x === tab); const p = document.getElementById(x.getAttribute('aria-controls')); if (p) p.hidden = x !== tab; });
    }));
  });

  /* ---- Gallery ---- */
  $$('.gallery-thumbs button').forEach((b) => b.addEventListener('click', () => {
    const main = $('.gallery-main img'); if (main) main.src = b.dataset.src;
    $$('.gallery-thumbs button').forEach((x) => x.setAttribute('aria-current', x === b));
  }));

  /* ---- Confirmations & double-submit protection ---- */
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.dataset.confirm && !confirm(f.dataset.confirm)) { e.preventDefault(); return; }
    if (f.dataset.once !== undefined) { if (f.dataset.sent) { e.preventDefault(); return; } f.dataset.sent = '1'; $$('[type=submit]', f).forEach((b) => (b.disabled = true)); }
  }, true);

  /* ---- Admin sidebar ---- */
  const burger = $('.admin-burger');
  if (burger) burger.addEventListener('click', () => $('.admin-side').classList.toggle('open'));

  /* ---- Kanban drag & drop (server validates every transition) ---- */
  $$('.kanban').forEach((board) => {
    let dragged = null;
    $$('.kcard', board).forEach((c) => {
      c.draggable = true;
      c.addEventListener('dragstart', () => { dragged = c; c.style.opacity = .5; });
      c.addEventListener('dragend', () => { c.style.opacity = 1; });
    });
    $$('.kcol', board).forEach((col) => {
      col.addEventListener('dragover', (e) => { e.preventDefault(); col.classList.add('drop'); });
      col.addEventListener('dragleave', () => col.classList.remove('drop'));
      col.addEventListener('drop', (e) => {
        e.preventDefault(); col.classList.remove('drop');
        if (!dragged || dragged.parentElement === col) return;
        const origin = dragged.parentElement, card = dragged;
        col.appendChild(card);
        fetch(card.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: JSON.stringify({ stage: col.dataset.stage }) })
          .then(async (r) => { if (!r.ok) { const j = await r.json().catch(() => ({})); throw new Error(j.message || (j.errors ? Object.values(j.errors).flat().join('\n') : 'Erreur')); } })
          .catch((err) => { origin.appendChild(card); alert(err.message); });
      });
    });
  });

  /* ---- Dynamic rows (quotes / staff orders / purchase orders) ---- */
  $$('[data-rows]').forEach((wrap) => {
    const tpl = $('template', wrap);
    const list = $('[data-rows-list]', wrap);
    let i = list.children.length;
    const add = $('[data-rows-add]', wrap);
    if (add) add.addEventListener('click', () => { list.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, i++)); document.dispatchEvent(new CustomEvent('rows:added', { detail: list.lastElementChild })); });
    wrap.addEventListener('click', (e) => { if (e.target.matches('[data-rows-remove]')) e.target.closest('[data-row]').remove(); });
  });

  /* ---- Staff line configurator: options come from the server, every change is re-priced ---- */
  const staffLine = (row) => {
    const prod = $('[data-f=product]', row);
    const box = $('[data-f=config]', row);
    const out = $('[data-line-out]', row);
    if (!prod || !box) return;
    const base = prod.name.replace(/\[product_id\]$/, '');
    const key = base.replace(/\W/g, '');
    const price = (initial) => {
      const opt = prod.selectedOptions[0];
      if (!prod.value || !opt || !opt.dataset.url) { box.innerHTML = ''; out.textContent = ''; return; }
      const data = { options: {} };
      if (!initial) {
        $$('[data-sel]', box).forEach((el) => { const k = el.dataset.sel; if (k.startsWith('options.')) data.options[k.slice(8)] = el.value; else data[k] = el.value; });
      } else if (row.dataset.selection) { try { Object.assign(data, JSON.parse(row.dataset.selection)); } catch (_) {} }
      fetch(opt.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: JSON.stringify(data) })
        .then((r) => r.json()).then((r) => {
          const ui = r.ui || {}; let h = '';
          const sel = (k, name, items, value) => `<label class="small">${esc(name)}<select data-sel="${k}" name="${base}[selection][${k.replace('options.', 'options][')}]">${items.map((c) => `<option value="${esc(c.code)}" ${c.code == value ? 'selected' : ''} ${c.available === false ? 'disabled' : ''}>${esc(c.label)}</option>`).join('')}</select></label>`;
          if (ui.quantity) {
            h += `<label class="small">Qté<input type="number" data-sel="quantity" name="${base}[selection][quantity]" value="${esc(ui.quantity.value)}" min="1" list="ql-${key}"></label><datalist id="ql-${key}">${ui.quantity.presets.map((q) => `<option value="${q}">`).join('')}</datalist>`;
          }
          if (ui.size) {
            const items = ui.size.presets.map((p) => ({ code: p.code, label: p.label }));
            if (ui.size.custom) items.push({ code: 'custom', label: 'Sur mesure' });
            h += sel('size', 'Format', items, ui.size.value);
            if (ui.size.value === 'custom') h += `<label class="small">L (mm)<input type="number" step="0.1" data-sel="width" name="${base}[selection][width]" value="${esc(ui.size.width)}"></label><label class="small">H (mm)<input type="number" step="0.1" data-sel="height" name="${base}[selection][height]" value="${esc(ui.size.height)}"></label><input type="hidden" data-sel="unit" name="${base}[selection][unit]" value="mm">`;
          }
          (ui.options || []).filter((o) => o.visible).forEach((o) => { h += sel('options.' + o.code, o.label, o.choices, o.value); });
          box.innerHTML = `<div class="flex">${h}</div>`;
          out.innerHTML = r.valid ? `<b>${esc(r.total_formatted)}</b> HT · ${esc(r.unit_formatted)} / u.` : `<span class="error-text">${(r.errors || []).map(esc).join(' ')}</span>`;
        });
    };
    prod.addEventListener('change', () => { row.dataset.selection = ''; price(true); });
    box.addEventListener('change', () => price(false));
    if (prod.value) price(true);
  };
  $$('[data-staff-line]').forEach(staffLine);
  document.addEventListener('rows:added', (e) => { if (e.detail && e.detail.matches('[data-staff-line]')) staffLine(e.detail); });
})();
