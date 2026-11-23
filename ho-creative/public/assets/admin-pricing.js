/* Visual editor for a pricing definition. State lives in `def`; every edit rewrites
   the hidden JSON textarea that is submitted. Mini-syntax used in text fields:
   conditions  "paper=couche350|couche400; sides=rv"   modifiers  "per_unit:0.05; fixed:20; percent:30" */
(function () {
  'use strict';
  const jsonEl = document.getElementById('pe-json');
  if (!jsonEl) return;
  let def = JSON.parse(jsonEl.value || '{}');
  const ui = document.getElementById('pe-ui');
  const raw = document.getElementById('pe-raw');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const csrf = document.querySelector('meta[name=csrf-token]').content;

  const condToStr = (c) => (c ? Object.entries(c).map(([k, v]) => k + '=' + [].concat(v).join('|')).join('; ') : '');
  const strToCond = (s) => { const o = {}; String(s || '').split(';').map((x) => x.trim()).filter(Boolean).forEach((p) => { const [k, v] = p.split('='); if (k && v) o[k.trim()] = v.split('|').map((x) => x.trim()).filter(Boolean); }); return Object.keys(o).length ? o : null; };
  const modToStr = (m) => (m || []).map((x) => x.type + ':' + x.amount).join('; ');
  const strToMod = (s) => String(s || '').split(';').map((x) => x.trim()).filter(Boolean).map((p) => { const [t, a] = p.split(':'); return { type: (t || '').trim(), amount: (a || '0').trim() }; });
  const L = (o, l) => (o && o[l]) || '';

  const defaults = () => {
    def.mode ||= 'tiers'; def.quantity ||= { presets: [], custom: false, min: 1, max: 100000, step: 1 };
    def.dimensions ||= { enabled: false, unit: 'mm', custom: false, presets: [], rounding_mm: 1 };
    def.area ||= { area_step_m2: '0.01', min_area_m2: '0', rates: [] }; def.area.rates ||= [];
    def.tiers ||= []; def.options ||= []; def.production ||= { days: 3 }; def.artwork ||= { back_side_if: null };
    def.dimensions.presets ||= [];
  };

  const input = (path, value, attrs = '') => `<input data-path="${path}" value="${esc(value)}" ${attrs}>`;
  const check = (path, value, label) => `<label class="check"><input type="checkbox" data-path="${path}" data-type="bool" ${value ? 'checked' : ''}> ${label}</label>`;

  const render = () => {
    defaults();
    const d = def, q = d.quantity, dm = d.dimensions;
    let h = `<fieldset><legend>Général</legend><div class="row">
      <label>Mode <select data-path="mode"><option value="tiers" ${d.mode === 'tiers' ? 'selected' : ''}>Paliers de quantité</option><option value="area" ${d.mode === 'area' ? 'selected' : ''}>Surface (m²)</option></select></label>
      <label>Délai prod. (j. ouvrés) ${input('production.days', d.production.days, 'type=number min=0')}</label>
      <label>Frais de calage HT ${input('setup_fee', d.setup_fee, 'type=number step=0.01 min=0')}</label>
      <label>Minimum de facturation HT ${input('min_charge', d.min_charge, 'type=number step=0.01 min=0')}</label></div>
      <div class="row"><label style="flex:1">Verso requis si (condition) ${input('artwork.back_side_if', condToStr(d.artwork.back_side_if), 'data-type=cond placeholder="sides=rv"')}</label></div></fieldset>`;
    h += `<fieldset><legend>Quantités</legend><div class="row">
      <label style="flex:2">Quantités prédéfinies (virgules) ${input('quantity.presets', (q.presets || []).join(', '), 'data-type=intlist')}</label>
      ${check('quantity.custom', q.custom, 'Quantité libre')}
      <label>Min ${input('quantity.min', q.min, 'type=number min=1')}</label><label>Max ${input('quantity.max', q.max, 'type=number min=1')}</label><label>Pas ${input('quantity.step', q.step, 'type=number min=1')}</label></div></fieldset>`;
    h += `<fieldset><legend>Dimensions</legend><div class="row">${check('dimensions.enabled', dm.enabled, 'Activer')} ${check('dimensions.custom', dm.custom, 'Sur-mesure')}
      <label>Unité saisie <select data-path="dimensions.unit"><option ${dm.unit === 'mm' ? 'selected' : ''}>mm</option><option ${dm.unit === 'cm' ? 'selected' : ''}>cm</option></select></label>
      <label>Arrondi (mm) ${input('dimensions.rounding_mm', dm.rounding_mm, 'type=number min=0 step=0.1')}</label></div>
      <div class="row"><label>L min ${input('dimensions.min_w', dm.min_w, 'type=number')}</label><label>L max ${input('dimensions.max_w', dm.max_w, 'type=number')}</label><label>H min ${input('dimensions.min_h', dm.min_h, 'type=number')}</label><label>H max ${input('dimensions.max_h', dm.max_h, 'type=number')}</label></div>
      <div class="small muted">Formats : code · libellé FR · libellé AR · largeur mm · hauteur mm</div>
      ${dm.presets.map((p, i) => `<div class="row">${input(`dimensions.presets.${i}.code`, p.code, 'placeholder=code')}${input(`dimensions.presets.${i}.label.fr`, L(p.label, 'fr'), 'placeholder="Libellé FR"')}${input(`dimensions.presets.${i}.label.ar`, L(p.label, 'ar'), 'placeholder="AR" dir=rtl')}${input(`dimensions.presets.${i}.w`, p.w, 'type=number step=0.1 placeholder=L')}${input(`dimensions.presets.${i}.h`, p.h, 'type=number step=0.1 placeholder=H')}<button type="button" class="btn btn-sm btn-ghost" data-del="dimensions.presets.${i}">✕</button></div>`).join('')}
      <button type="button" class="btn btn-sm btn-ghost" data-add="dimensions.presets">+ Format</button></fieldset>`;
    if (d.mode === 'area') {
      h += `<fieldset><legend>Tarifs au m²</legend><div class="row"><label>Pas de surface (m²) ${input('area.area_step_m2', d.area.area_step_m2, 'type=number step=0.001')}</label><label>Surface min. par pièce (m²) ${input('area.min_area_m2', d.area.min_area_m2, 'type=number step=0.01')}</label></div>
        <div class="small muted">Tranche : à partir de X m² (surface totale) · prix HT / m² · condition optionnelle</div>
        ${d.area.rates.map((r, i) => `<div class="row">${input(`area.rates.${i}.min_m2`, r.min_m2, 'type=number step=0.01 placeholder="dès m²"')}${input(`area.rates.${i}.rate`, r.rate, 'type=number step=0.01 placeholder="DH/m²"')}${input(`area.rates.${i}.match`, condToStr(r.match), 'data-type=cond placeholder="material=bache510"')}<button type="button" class="btn btn-sm btn-ghost" data-del="area.rates.${i}">✕</button></div>`).join('')}
        <button type="button" class="btn btn-sm btn-ghost" data-add="area.rates">+ Tranche</button></fieldset>`;
    } else {
      h += `<fieldset><legend>Paliers de prix (prix total HT pour la quantité)</legend>
        <div class="small muted">Le palier le plus spécifique (le plus de conditions satisfaites) est utilisé. Quantité libre : prix unitaire du plus grand palier ≤ quantité.</div>
        ${d.tiers.map((t, i) => `<div class="row">${input(`tiers.${i}.qty`, t.qty, 'type=number min=1 placeholder=Qté')}${input(`tiers.${i}.price`, t.price, 'type=number step=0.01 min=0 placeholder="Prix HT"')}${input(`tiers.${i}.match`, condToStr(t.match), 'data-type=cond placeholder="paper=couche350; size=a5"')}<button type="button" class="btn btn-sm btn-ghost" data-del="tiers.${i}">✕</button></div>`).join('')}
        <button type="button" class="btn btn-sm btn-ghost" data-add="tiers">+ Palier</button></fieldset>`;
    }
    h += `<fieldset><legend>Options (dans l'ordre d'affichage)</legend>
      <div class="small muted">Conditions : <code>option=choix1|choix2; size=a4</code> – uniquement des options situées AU-DESSUS. Modificateurs : <code>per_unit:0.05; per_thousand:12; per_m2:15; fixed:20; setup:50; percent:30</code></div>`;
    d.options.forEach((o, i) => {
      o.choices ||= [];
      h += `<div class="opt-box"><div class="row"><strong>#${i + 1}</strong>${input(`options.${i}.code`, o.code, 'placeholder=code')}${input(`options.${i}.label.fr`, L(o.label, 'fr'), 'placeholder="Libellé FR"')}${input(`options.${i}.label.ar`, L(o.label, 'ar'), 'placeholder="AR" dir=rtl')}
        <select data-path="options.${i}.display"><option value="select" ${o.display !== 'cards' ? 'selected' : ''}>Liste</option><option value="cards" ${o.display === 'cards' ? 'selected' : ''}>Cartes</option></select>
        ${check(`options.${i}.required`, o.required, 'Obligatoire')}
        <button type="button" class="btn btn-sm btn-ghost" data-move="${i}:-1">↑</button><button type="button" class="btn btn-sm btn-ghost" data-move="${i}:1">↓</button><button type="button" class="btn btn-sm btn-ghost" data-del="options.${i}">✕</button></div>
        <div class="row"><label style="flex:1">Visible si ${input(`options.${i}.visible_if`, condToStr(o.visible_if), 'data-type=cond')}</label><label>Défaut ${input(`options.${i}.default`, o.default)}</label><label style="flex:1">Aide FR ${input(`options.${i}.help.fr`, L(o.help, 'fr'))}</label></div>
        ${o.choices.map((c, j) => `<div class="row choice-row">${input(`options.${i}.choices.${j}.code`, c.code, 'placeholder=code')}${input(`options.${i}.choices.${j}.label.fr`, L(c.label, 'fr'), 'placeholder="Libellé FR"')}${input(`options.${i}.choices.${j}.label.ar`, L(c.label, 'ar'), 'placeholder="AR" dir=rtl')}
          ${input(`options.${i}.choices.${j}.modifiers`, modToStr(c.modifiers), 'data-type=mod placeholder="per_unit:0.05"')}${input(`options.${i}.choices.${j}.available_if`, condToStr(c.available_if), 'data-type=cond placeholder="disponible si"')}
          ${input(`options.${i}.choices.${j}.production_days`, c.production_days ?? '', 'type=number min=0 placeholder="délai=" title="Fixe le délai (ex. express)"')}${input(`options.${i}.choices.${j}.extra_days`, c.extra_days ?? '', 'type=number min=0 placeholder="+jours"')}
          <button type="button" class="btn btn-sm btn-ghost" data-del="options.${i}.choices.${j}">✕</button></div>`).join('')}
        <button type="button" class="btn btn-sm btn-ghost" data-add="options.${i}.choices">+ Choix</button></div>`;
    });
    h += `<button type="button" class="btn btn-sm" data-add="options">+ Option</button></fieldset>`;
    ui.innerHTML = h;
    sync();
  };

  const getRef = (path, create) => {
    const parts = path.split('.');
    let o = def;
    for (let i = 0; i < parts.length - 1; i++) {
      const k = parts[i];
      if (o[k] === undefined || o[k] === null) { if (!create) return [null]; o[k] = /^\d+$/.test(parts[i + 1]) ? [] : {}; }
      o = o[k];
    }
    return [o, parts[parts.length - 1]];
  };

  const sync = () => { jsonEl.value = JSON.stringify(def); raw.value = JSON.stringify(def, null, 2); schedulePreview(); };

  ui.addEventListener('input', (e) => {
    const el = e.target, path = el.dataset.path; if (!path) return;
    const [o, k] = getRef(path, true);
    let v = el.type === 'checkbox' ? el.checked : el.value;
    switch (el.dataset.type) {
      case 'cond': v = strToCond(v); break;
      case 'mod': v = strToMod(v); break;
      case 'intlist': v = String(v).split(',').map((x) => parseInt(x, 10)).filter((x) => x > 0); break;
      case 'bool': v = !!v; break;
      default: if (el.type === 'number') v = v === '' ? null : (path.match(/(price|rate|fee|charge|amount|_m2|min_|max_|\.w$|\.h$|rounding)/) ? v : Number(v));
    }
    if (v === null || v === '' || (Array.isArray(v) && !v.length && el.dataset.type !== 'intlist')) delete o[k]; else o[k] = v;
    sync();
  });
  ui.addEventListener('change', (e) => { if (e.target.matches('select[data-path="mode"],input[data-path="dimensions.enabled"]')) render(); });
  ui.addEventListener('click', (e) => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.add) {
      const [o, k] = getRef(b.dataset.add + '.x', true);
      const tpl = { 'dimensions.presets': { code: '', label: { fr: '', ar: '' }, w: '', h: '' }, 'area.rates': { min_m2: '0', rate: '0' }, tiers: { qty: 100, price: '0' }, options: { code: 'option' + (def.options.length + 1), label: { fr: '', ar: '' }, display: 'select', required: true, choices: [] } };
      o.push(tpl[b.dataset.add] || { code: 'choix' + (o.length + 1), label: { fr: '', ar: '' }, modifiers: [] });
      render();
    } else if (b.dataset.del) {
      const parts = b.dataset.del.split('.'); const idx = +parts.pop(); const [o, k] = getRef(parts.join('.') + '.x', false); o.splice(idx, 1); render();
    } else if (b.dataset.move) {
      const [i, dir] = b.dataset.move.split(':').map(Number); const j = i + dir; if (j < 0 || j >= def.options.length) return;
      [def.options[i], def.options[j]] = [def.options[j], def.options[i]]; render();
    }
  });
  document.getElementById('pe-apply-raw').onclick = () => { try { def = JSON.parse(raw.value); render(); } catch (err) { alert('JSON invalide : ' + err.message); } };

  // ---- live preview against the unsaved draft ----
  const pvIn = document.getElementById('pv-inputs'), pvOut = document.getElementById('pv-result'), pvErr = document.getElementById('pv-errors'), pvLive = document.getElementById('pv-live');
  let pvInput = {}, timer;
  const schedulePreview = () => { clearTimeout(timer); timer = setTimeout(preview, 300); };
  const preview = () => {
    fetch(window.PE_PREVIEW_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: JSON.stringify({ definition: def, input: pvInput }) })
      .then((r) => r.json()).then((r) => {
        pvErr.innerHTML = (r.definition_errors || []).length ? `<div class="alert alert-err small">${r.definition_errors.map(esc).join('<br>')}</div>` : '<div class="alert alert-ok small">Définition valide.</div>';
        const u = r.ui || {}; let h = '';
        if (u.quantity) h += `<label class="small">Quantité <input type="number" data-k="quantity" value="${esc(u.quantity.value)}" list="pv-q"></label><datalist id="pv-q">${u.quantity.presets.map((x) => `<option value="${x}">`).join('')}</datalist>`;
        if (u.size) {
          h += `<label class="small">Format <select data-k="size">${u.size.presets.map((p) => `<option value="${esc(p.code)}" ${p.code === u.size.value ? 'selected' : ''}>${esc(p.label)}</option>`).join('')}${u.size.custom ? `<option value="custom" ${u.size.value === 'custom' ? 'selected' : ''}>Sur mesure</option>` : ''}</select></label>`;
          if (u.size.value === 'custom') h += `<div class="flex"><label class="small">L mm <input type="number" data-k="width" value="${esc(pvInput.width ?? u.size.width)}"></label><label class="small">H mm <input type="number" data-k="height" value="${esc(pvInput.height ?? u.size.height)}"></label></div>`;
        }
        (u.options || []).filter((o) => o.visible).forEach((o) => { h += `<label class="small">${esc(o.label)} <select data-k="options.${esc(o.code)}">${o.choices.map((c) => `<option value="${esc(c.code)}" ${c.code === o.value ? 'selected' : ''} ${c.available ? '' : 'disabled'}>${esc(c.label)}${c.available ? '' : ' (indisponible)'}</option>`).join('')}</select></label>`; });
        h += `<label class="small">Fichier <select data-k="artwork_mode"><option value="">Fichier client</option><option value="design_service" ${pvInput.artwork_mode === 'design_service' ? 'selected' : ''}>Création graphique</option></select></label>`;
        pvIn.innerHTML = h;
        pvOut.innerHTML = r.valid ? `<div class="total">${esc(r.total_formatted)} HT</div><div class="small">${esc(r.unit_formatted)} / u · ${r.area_m2 ? esc(r.area_m2) + ' m² · ' : ''}${r.production_days} j</div><div class="rows">${r.components.map((c) => `<div><span>${esc(c.label)}</span><span>${esc(c.formatted)}</span></div>`).join('')}</div>`
          : `<div class="err">${(r.errors || []).map(esc).join('<br>')}</div>`;
        pvLive.textContent = r.published_total ? 'Même configuration avec la grille en ligne : ' + r.published_total + ' HT' : '';
      });
  };
  pvIn.addEventListener('change', (e) => {
    const k = e.target.dataset.k; if (!k) return;
    if (k.startsWith('options.')) { pvInput.options ||= {}; pvInput.options[k.slice(8)] = e.target.value; } else pvInput[k] = e.target.value;
    preview();
  });
  render();
})();
