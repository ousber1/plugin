/* HO CREATIVE online editor – Fabric.js 5 (MIT). Design JSON is stored in design
   units (UNITS_PER_MM per mm, origin = top-left of the bleed box). */
(function () {
  'use strict';
  const root = document.getElementById('editor');
  if (!root || !window.fabric) return;
  const cfg = JSON.parse(root.dataset.config);
  const t = JSON.parse(root.dataset.i18n);
  const U = cfg.units_per_mm;
  const W = (cfg.width_mm + 2 * cfg.bleed_mm) * U;
  const H = (cfg.height_mm + 2 * cfg.bleed_mm) * U;
  const stageEl = document.querySelector('.editor-stage');
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  const sides = cfg.sides === 2 ? ['front', 'back'] : ['front'];
  const data = Object.assign({}, cfg.data || {});
  let side = 'front';
  const history = { front: [], back: [] }, future = { front: [], back: [] };
  let restoring = false;

  const canvas = new fabric.Canvas('editor-canvas', { backgroundColor: '#ffffff', preserveObjectStacking: true });
  const fit = () => {
    const avail = Math.max(260, stageEl.clientWidth - 32);
    const zoom = Math.min(avail / W, 620 / H, 3);
    canvas.setDimensions({ width: Math.round(W * zoom), height: Math.round(H * zoom) });
    canvas.setZoom(zoom);
    canvas.requestRenderAll();
  };

  // Guides drawn on the top context only: never part of the export.
  canvas.on('after:render', () => {
    const ctx = canvas.contextTop; if (!ctx) return;
    canvas.clearContext(ctx);
    const z = canvas.getZoom(), b = cfg.bleed_mm * U * z, s = (cfg.bleed_mm + cfg.safe_mm) * U * z;
    const w = W * z, h = H * z;
    ctx.save();
    ctx.fillStyle = 'rgba(228,0,124,0.10)';
    ctx.fillRect(0, 0, w, b); ctx.fillRect(0, h - b, w, b); ctx.fillRect(0, b, b, h - 2 * b); ctx.fillRect(w - b, b, b, h - 2 * b);
    ctx.setLineDash([6, 4]); ctx.lineWidth = 1.5;
    ctx.strokeStyle = '#E4007C'; ctx.strokeRect(b, b, w - 2 * b, h - 2 * b);
    ctx.strokeStyle = '#00A0E3'; ctx.strokeRect(s, s, w - 2 * s, h - 2 * s);
    ctx.restore();
  });

  const snapshot = () => JSON.stringify(canvas.toJSON(['selectable']));
  const record = () => { if (restoring) return; history[side].push(snapshot()); if (history[side].length > 60) history[side].shift(); future[side] = []; dirty(); };
  canvas.on('object:added', record); canvas.on('object:modified', record); canvas.on('object:removed', record);

  // Fabric 5.3 fails to serialise text objects that lack `styles`: normalise older/seeded JSON.
  const normalize = (json) => {
    if (json && Array.isArray(json.objects)) json.objects.forEach((o) => { if (/text/i.test(o.type || '') && !o.styles) o.styles = []; });
    return json;
  };
  const load = (json, cb) => {
    json = normalize(json);
    restoring = true;
    canvas.clear(); canvas.setBackgroundColor('#ffffff', () => {});
    if (json) canvas.loadFromJSON(json, () => { restoring = false; canvas.requestRenderAll(); cb && cb(); });
    else { restoring = false; canvas.requestRenderAll(); cb && cb(); }
  };
  const switchSide = (s) => {
    data[side] = canvas.toJSON(['selectable']);
    side = s;
    document.querySelectorAll('[data-side]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.side === s));
    load(data[side], () => { if (!history[side].length) history[side].push(snapshot()); });
  };

  // ---- toolbar ----
  const $ = (id) => document.getElementById(id);
  const center = () => ({ left: W / 2, top: H / 2, originX: 'center', originY: 'center' });
  $('add-text').onclick = () => { const o = new fabric.IText(t.sample, Object.assign(center(), { fontFamily: 'Arial', fontSize: 6 * U, fill: '#1B1F3B' })); canvas.add(o).setActiveObject(o); };
  $('add-rect').onclick = () => { const o = new fabric.Rect(Object.assign(center(), { width: 20 * U, height: 12 * U, fill: '#E4007C', rx: U, ry: U })); canvas.add(o).setActiveObject(o); };
  $('add-image').onchange = (e) => {
    const f = e.target.files[0]; if (!f) return;
    if (f.size > 15 * 1024 * 1024) { alert(t.too_big); return; }
    const r = new FileReader();
    r.onload = () => fabric.Image.fromURL(r.result, (img) => {
      const max = Math.min((W - 2 * cfg.bleed_mm * U) / img.width, (H - 2 * cfg.bleed_mm * U) / img.height, 1);
      img.set(Object.assign(center(), { scaleX: max * 0.6, scaleY: max * 0.6 }));
      canvas.add(img).setActiveObject(img);
      // effective resolution warning: px of the source / printed size in inches
      const inches = (img.width * img.scaleX / U) / 25.4;
      if (img.width / inches < 200) $('status').textContent = t.low_res;
    });
    r.readAsDataURL(f);
    e.target.value = '';
  };
  const active = () => canvas.getActiveObject();
  $('color').oninput = (e) => { const o = active(); if (o) { o.set('fill', e.target.value); canvas.requestRenderAll(); record(); } };
  $('bg').oninput = (e) => { canvas.setBackgroundColor(e.target.value, () => { canvas.requestRenderAll(); record(); }); };
  $('font').onchange = (e) => { const o = active(); if (o && o.set && 'fontFamily' in o) { o.set('fontFamily', e.target.value); canvas.requestRenderAll(); record(); } };
  $('size').oninput = (e) => { const o = active(); if (o && 'fontSize' in o) { o.set('fontSize', +e.target.value * U); canvas.requestRenderAll(); record(); } };
  $('bold').onclick = () => { const o = active(); if (o && 'fontWeight' in o) { o.set('fontWeight', o.fontWeight === 'bold' ? 'normal' : 'bold'); canvas.requestRenderAll(); record(); } };
  $('align').onchange = (e) => { const o = active(); if (o && 'textAlign' in o) { o.set('textAlign', e.target.value); canvas.requestRenderAll(); record(); } };
  $('front-btn').onclick = () => { const o = active(); if (o) { canvas.bringForward(o); record(); } };
  $('back-btn').onclick = () => { const o = active(); if (o) { canvas.sendBackwards(o); record(); } };
  $('center-btn').onclick = () => { const o = active(); if (o) { canvas.viewportCenterObject(o); o.setCoords(); o.set({ left: W / 2, top: H / 2, originX: 'center', originY: 'center' }); canvas.requestRenderAll(); record(); } };
  $('delete').onclick = () => { canvas.getActiveObjects().forEach((o) => canvas.remove(o)); canvas.discardActiveObject(); canvas.requestRenderAll(); };
  $('undo').onclick = () => { const h = history[side]; if (h.length < 2) return; future[side].push(h.pop()); restoring = true; canvas.loadFromJSON(h[h.length - 1], () => { restoring = false; canvas.requestRenderAll(); }); };
  $('redo').onclick = () => { const f = future[side]; if (!f.length) return; const s = f.pop(); history[side].push(s); restoring = true; canvas.loadFromJSON(s, () => { restoring = false; canvas.requestRenderAll(); }); };
  document.addEventListener('keydown', (e) => {
    if (e.target.matches('input,textarea,select') || (active() && active().isEditing)) return;
    if ((e.key === 'Delete' || e.key === 'Backspace') && active()) { e.preventDefault(); $('delete').click(); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? $('redo').click() : $('undo').click(); }
  });
  document.querySelectorAll('[data-side]').forEach((b) => (b.onclick = () => switchSide(b.dataset.side)));

  let isDirty = false;
  const dirty = () => { isDirty = true; $('status').textContent = t.unsaved; };
  window.addEventListener('beforeunload', (e) => { if (isDirty) { e.preventDefault(); e.returnValue = ''; } });

  // ---- export: render each side off-screen at print resolution ----
  const exportSide = (json) => new Promise((resolve) => {
    const el = document.createElement('canvas');
    const sc = new fabric.StaticCanvas(el, { width: W, height: H, backgroundColor: '#ffffff' });
    sc.loadFromJSON(normalize(json) || {}, () => {
      const targetW = Math.min(cfg.max_px, Math.round((W / U / 25.4) * cfg.dpi));
      const mult = targetW / W;
      const url = sc.toDataURL({ format: 'png', multiplier: Math.min(mult, cfg.max_px / H) });
      sc.dispose(); resolve(url);
    });
  });

  const save = async (final) => {
    data[side] = canvas.toJSON(['selectable']);
    $('status').textContent = final ? t.exporting : t.saving;
    const body = { uuid: cfg.uuid, item: cfg.item, template: cfg.template, product: cfg.product, width_mm: cfg.width_mm, height_mm: cfg.height_mm, bleed_mm: cfg.bleed_mm, safe_mm: cfg.safe_mm, sides: cfg.sides, data, final: !!final };
    if (final) { body.exports = {}; for (const s of sides) body.exports[s] = await exportSide(data[s]); }
    const r = await fetch(cfg.save_url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) { $('status').textContent = j.message || t.error; return; }
    cfg.uuid = j.uuid; isDirty = false; $('status').textContent = t.saved;
    if (final && j.redirect) location.href = j.redirect;
  };
  $('save').onclick = () => save(false);
  $('finish').onclick = () => { if (confirm(t.confirm_finish)) save(true); };

  window.addEventListener('resize', fit);
  fit();
  load(data.front, () => { history.front.push(snapshot()); isDirty = false; $('status').textContent = ''; });
})();
