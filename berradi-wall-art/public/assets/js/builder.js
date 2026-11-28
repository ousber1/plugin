/* BERRADI WALL ART — Custom Wall Art Builder
 * Browser-side image work (preview generation, crop/zoom/rotate with Cropper.js, mockup rendering);
 * the server validates uploads, prices every configuration and re-checks crops/DPI on save.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-builder]');
    const cfgEl = document.getElementById('builder-config');
    if (!root || !cfgEl) return;
    const cfg = JSON.parse(cfgEl.textContent || '{}');
    const $ = (sel, ctx) => (ctx || root).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || root).querySelectorAll(sel));
    const t = (key, rep) => {
        let s = (cfg.i18n && cfg.i18n[key]) || key;
        Object.entries(rep || {}).forEach(([k, v]) => { s = s.split(':' + k).join(String(v)); });
        return s;
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const debounce = (fn, ms) => { let h; return (...a) => { clearTimeout(h); h = setTimeout(() => fn(...a), ms); }; };
    const fmtCm = (v) => (Math.round(v * 10) / 10).toString().replace('.', ',');
    const WALL_CM = 300;

    // ------------------------------------------------------------------ state
    const d = cfg.defaults || {};
    const state = {
        images: [],                 // {uuid, width, height, name, previewUrl, thumbUrl, el (HTMLImageElement)}
        assign: {},                 // panel index -> image index
        crops: {},                  // panel index -> {img, data (preview px), ratio}
        materialId: d.materialId,
        frameId: d.frameId || null,
        layout: d.layout || 'single',
        mode: 'span',
        w: d.width || 40,
        h: d.height || 60,
        orientation: (d.width || 40) > (d.height || 60) ? 'landscape' : ((d.width === d.height) ? 'square' : 'portrait'),
        border: d.border || null,
        finish: d.finish || null,
        qty: 1,
        activePanel: 0,
        quote: null,
        saved: null,
    };
    let cropper = null;
    let cropperKey = '';
    const rotatedCache = new Map();

    const material = () => cfg.materials.find(m => m.id === state.materialId) || cfg.materials[0];
    const frame = () => cfg.frames.find(f => f.id === state.frameId) || null;
    const layout = () => cfg.layouts.find(l => l.code === state.layout) || { code: 'single', panels: 1, gap: 0, name: '1' };
    const panels = () => layout().panels;
    const mode = () => (panels() > 1 ? state.mode : 'span');
    const overall = () => ({ w: state.w * panels() + layout().gap * (panels() - 1), h: state.h });
    const targetRatio = () => (mode() === 'span' ? overall().w / overall().h : state.w / state.h);
    const neededImages = () => (mode() === 'per_panel' ? panels() : 1);
    const printWidthCm = () => (mode() === 'span' ? overall().w : state.w);

    // ------------------------------------------------------------ rendering UI
    function chip(label, active, onClick, extraClass) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'chip-btn' + (active ? ' active' : '') + (extraClass ? ' ' + extraClass : '');
        b.textContent = label;
        b.setAttribute('aria-pressed', active ? 'true' : 'false');
        b.addEventListener('click', onClick);
        return b;
    }

    function renderOptions() {
        const m = material();
        // orientation
        $$('[data-orientation] button').forEach(b => {
            b.classList.toggle('active', b.dataset.value === state.orientation);
            b.setAttribute('aria-pressed', b.dataset.value === state.orientation ? 'true' : 'false');
        });
        // sizes
        const sizesBox = $('[data-sizes]');
        sizesBox.innerHTML = '';
        const list = cfg.sizes
            .filter(s => (state.orientation === 'square' ? s.w === s.h : s.w !== s.h))
            .map(s => (state.orientation === 'landscape' ? { w: Math.max(s.w, s.h), h: Math.min(s.w, s.h) } : (state.orientation === 'portrait' ? { w: Math.min(s.w, s.h), h: Math.max(s.w, s.h) } : s)))
            .filter(s => s.w >= m.min && s.h >= m.min && Math.max(s.w, s.h) <= m.max && Math.min(s.w, s.h) <= m.maxShort);
        list.forEach(s => sizesBox.appendChild(chip(fmtCm(s.w) + ' × ' + fmtCm(s.h) + ' cm', s.w === state.w && s.h === state.h, () => { state.w = s.w; state.h = s.h; changed(); })));
        if (!list.length) sizesBox.innerHTML = '<span class="small text-muted-bwa">' + t('no_preset') + '</span>';
        $('[data-size-range]').textContent = t('size_range', { min: m.min, max: m.max });
        $('[data-custom-w]').value = state.w;
        $('[data-custom-h]').value = state.h;

        // layouts & mode
        const lay = $('[data-layouts]');
        lay.innerHTML = '';
        cfg.layouts.forEach(l => lay.appendChild(chip(l.name, l.code === state.layout, () => { state.layout = l.code; state.crops = {}; changed(true); })));
        $('[data-layout-block]').classList.toggle('d-none', cfg.layouts.length <= 1);
        const modeBox = $('[data-mode]');
        modeBox.classList.toggle('d-none', panels() <= 1);
        $$('button', modeBox).forEach(b => b.classList.toggle('active', b.dataset.value === state.mode));

        // materials
        const mats = $('[data-materials]');
        mats.innerHTML = '';
        cfg.materials.forEach(mm => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'material-card' + (mm.id === state.materialId ? ' active' : '');
            b.innerHTML = '<strong></strong><small></small>';
            b.querySelector('strong').textContent = mm.name;
            b.querySelector('small').textContent = mm.description || '';
            b.addEventListener('click', () => {
                state.materialId = mm.id;
                if (!mm.supportsFrame) state.frameId = null;
                if (mm.type === 'canvas' && !state.border && cfg.borders.length) state.border = cfg.borders[0].code;
                if (mm.type !== 'canvas') state.border = null;
                clampSize();
                changed();
            });
            mats.appendChild(b);
        });

        // borders (canvas only)
        const showBorder = m.type === 'canvas' && cfg.borders.length > 0;
        $('[data-border-block]').classList.toggle('d-none', !showBorder);
        const borders = $('[data-borders]');
        borders.innerHTML = '';
        cfg.borders.forEach(b => borders.appendChild(chip(b.name, b.code === state.border, () => { state.border = b.code; changed(); })));
        // finishes
        $('[data-finish-block]').classList.toggle('d-none', cfg.finishes.length === 0);
        const fin = $('[data-finishes]');
        fin.innerHTML = '';
        cfg.finishes.forEach(f => fin.appendChild(chip(f.name, f.code === state.finish, () => { state.finish = f.code; changed(); })));
        // frames
        const fb = $('[data-frame-block]');
        fb.classList.toggle('d-none', !m.supportsFrame || cfg.frames.length === 0);
        const frames = $('[data-frames]');
        frames.innerHTML = '';
        const none = chip(t('no_frame'), state.frameId === null, () => { state.frameId = null; changed(); }, 'swatch');
        none.insertAdjacentHTML('afterbegin', '<span class="dot none"></span>');
        frames.appendChild(none);
        cfg.frames.forEach(f => {
            const b = chip(f.name, f.id === state.frameId, () => { state.frameId = f.id; changed(); }, 'swatch');
            const dot = document.createElement('span');
            dot.className = 'dot';
            dot.style.background = f.color;
            b.prepend(dot);
            frames.appendChild(b);
        });

        // dimensions summary
        const o = overall();
        $('[data-dims]').textContent = panels() > 1
            ? t('dims_multi', { n: panels(), w: fmtCm(state.w), h: fmtCm(state.h), W: fmtCm(o.w), H: fmtCm(o.h) })
            : t('dims_single', { w: fmtCm(state.w), h: fmtCm(state.h) });
        $('[data-wall-note]').textContent = t('wall_note', { w: fmtCm(o.w), h: fmtCm(o.h) });
        renderPanelTabs();
        renderSteps();
    }

    function clampSize() {
        const m = material();
        const longS = Math.max(state.w, state.h), shortS = Math.min(state.w, state.h);
        if (longS > m.max || shortS > m.maxShort || shortS < m.min) {
            const s = cfg.sizes.find(x => Math.max(x.w, x.h) <= m.max && Math.min(x.w, x.h) <= m.maxShort && Math.min(x.w, x.h) >= m.min);
            if (s) { state.w = state.orientation === 'landscape' ? Math.max(s.w, s.h) : Math.min(s.w, s.h); state.h = state.orientation === 'landscape' ? Math.min(s.w, s.h) : Math.max(s.w, s.h); }
        }
    }

    function renderPanelTabs() {
        const box = $('[data-panel-tabs]');
        const show = mode() === 'per_panel';
        box.classList.toggle('d-none', !show);
        if (!show) { state.activePanel = 0; return; }
        box.innerHTML = '';
        for (let i = 0; i < panels(); i++) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'panel-tab' + (i === state.activePanel ? ' active' : '') + (state.crops[i] ? ' done' : '');
            b.textContent = t('panel_n', { n: i + 1 });
            b.addEventListener('click', () => { state.activePanel = i; openEditor(); renderPanelTabs(); });
            box.appendChild(b);
        }
    }

    function renderSteps() {
        const hasPhoto = Object.keys(state.crops).length >= neededImages();
        const set = (k, done, current) => {
            const li = document.querySelector('[data-step-indicator="' + k + '"]');
            if (li) { li.classList.toggle('done', done); li.classList.toggle('current', current); }
        };
        set('photo', hasPhoto, !hasPhoto);
        set('format', hasPhoto, false);
        set('finish', hasPhoto && !!state.quote, hasPhoto && !state.saved);
        set('save', !!state.saved, false);
    }

    function renderPhotoStrip() {
        const strip = $('[data-photo-strip]');
        strip.hidden = state.images.length === 0;
        $('[data-dropzone]').classList.toggle('d-none', state.images.length > 0);
        const list = $('[data-photo-list]');
        list.innerHTML = '';
        state.images.forEach((img, idx) => {
            const item = document.createElement('div');
            const used = Object.values(state.assign).includes(idx);
            item.className = 'photo-item' + (state.assign[state.activePanel] === idx ? ' active' : '') + (img.uploading ? ' uploading' : '');
            const thumb = document.createElement('button');
            thumb.type = 'button';
            thumb.className = 'photo-thumb';
            thumb.title = img.name || '';
            if (img.thumbUrl || img.previewUrl) {
                const im = document.createElement('img');
                im.src = img.thumbUrl || img.previewUrl;
                im.alt = '';
                thumb.appendChild(im);
            }
            if (img.uploading) {
                thumb.insertAdjacentHTML('beforeend', '<span class="upload-progress"><span style="width:' + (img.progress || 0) + '%"></span></span>');
            } else {
                thumb.addEventListener('click', () => { assignImage(idx); });
            }
            item.appendChild(thumb);
            if (!img.uploading) {
                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'photo-del';
                del.setAttribute('aria-label', t('delete_photo'));
                del.innerHTML = '<i class="bi bi-x"></i>';
                del.addEventListener('click', () => deleteImage(idx));
                item.appendChild(del);
                const meta = document.createElement('small');
                meta.textContent = img.width + '×' + img.height + (used ? ' ✓' : '');
                item.appendChild(meta);
            }
            list.appendChild(item);
        });
    }

    // ---------------------------------------------------------------- uploads
    function makePreview(file) {
        return new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                try {
                    const max = 1600;
                    const s = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                    const c = document.createElement('canvas');
                    c.width = Math.round(img.naturalWidth * s);
                    c.height = Math.round(img.naturalHeight * s);
                    const ctx = c.getContext('2d');
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, c.width, c.height);
                    ctx.drawImage(img, 0, 0, c.width, c.height);
                    c.toBlob((blob) => { URL.revokeObjectURL(url); resolve(blob ? { blob, url: URL.createObjectURL(blob) } : null); }, 'image/jpeg', 0.86);
                } catch (e) {
                    URL.revokeObjectURL(url);
                    resolve(null); // graceful: the server generates the preview
                }
            };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
            img.src = url;
        });
    }

    function uploadFile(file, preview, entry) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('photo', file, file.name);
            if (preview) fd.append('preview', preview.blob, 'preview.jpg');
            fd.append('_token', csrf);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', cfg.urls.upload);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-Token', csrf);
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) { entry.progress = Math.round(e.loaded / e.total * 100); renderPhotoStrip(); }
            };
            xhr.onload = () => {
                let data = {};
                try { data = JSON.parse(xhr.responseText); } catch (e) { /* ignore */ }
                if (xhr.status === 200 && data.ok) resolve(data.image);
                else reject(new Error(data.message || t('upload_failed')));
            };
            xhr.onerror = () => reject(new Error(t('upload_failed')));
            xhr.send(fd);
        });
    }

    async function handleFiles(files) {
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        for (const file of Array.from(files)) {
            if (state.images.filter(i => !i.uploading).length >= cfg.limits.maxFiles) { showError(t('too_many', { max: cfg.limits.maxFiles })); break; }
            if (!allowed.includes(file.type)) { showError(t('bad_type', { name: file.name })); continue; }
            if (file.size > cfg.limits.maxMb * 1024 * 1024) { showError(t('too_big', { name: file.name, max: cfg.limits.maxMb })); continue; }
            const entry = { uploading: true, progress: 0, name: file.name };
            state.images.push(entry);
            renderPhotoStrip();
            const preview = await makePreview(file);
            if (preview) entry.thumbUrl = preview.url;
            renderPhotoStrip();
            try {
                const info = await uploadFile(file, preview, entry);
                Object.assign(entry, {
                    uploading: false, uuid: info.uuid, width: info.width, height: info.height,
                    previewUrl: preview ? preview.url : info.preview, thumbUrl: preview ? preview.url : info.thumb,
                });
                hideError();
                const idx = state.images.indexOf(entry);
                if (state.assign[state.activePanel] === undefined) assignImage(idx);
                else if (mode() === 'per_panel') {
                    for (let p = 0; p < panels(); p++) if (state.assign[p] === undefined) { state.assign[p] = idx; break; }
                }
            } catch (err) {
                state.images.splice(state.images.indexOf(entry), 1);
                showError(err.message);
            }
            renderPhotoStrip();
            renderPanelTabs();
        }
    }

    function deleteImage(idx) {
        const img = state.images[idx];
        if (!img || !window.confirm(t('confirm_delete_photo'))) return;
        window.bwaFetch(cfg.urls.deleteImage.replace('__UUID__', img.uuid), { method: 'POST', body: {} })
            .then(() => {
                state.images.splice(idx, 1);
                const newAssign = {}, newCrops = {};
                Object.entries(state.assign).forEach(([p, i]) => {
                    if (i === idx) return;
                    newAssign[p] = i > idx ? i - 1 : i;
                    if (state.crops[p]) newCrops[p] = Object.assign({}, state.crops[p], { img: newAssign[p] });
                });
                state.assign = newAssign;
                state.crops = newCrops;
                if (state.images.length === 0) destroyCropper();
                openEditor();
                renderPhotoStrip();
                changed();
            })
            .catch(e => showError(e.message));
    }

    // ----------------------------------------------------------------- editor
    function loadImageEl(img) {
        if (img.el && img.el.complete) return Promise.resolve(img.el);
        return new Promise((resolve, reject) => {
            const el = new Image();
            el.onload = () => { img.el = el; resolve(el); };
            el.onerror = reject;
            el.src = img.previewUrl;
        });
    }

    function assignImage(idx) {
        if (mode() === 'per_panel') state.assign[state.activePanel] = idx;
        else state.assign = { 0: idx };
        delete state.crops[mode() === 'per_panel' ? state.activePanel : 0];
        openEditor(true);
        renderPhotoStrip();
    }

    function destroyCropper() {
        if (cropper) { cropper.destroy(); cropper = null; }
        cropperKey = '';
        $('[data-editor]').classList.add('d-none');
        $('[data-toolbar]').hidden = true;
        $('[data-hint]').hidden = true;
    }

    function openEditor(force) {
        const panel = mode() === 'per_panel' ? state.activePanel : 0;
        const idx = state.assign[panel];
        const img = idx !== undefined ? state.images[idx] : null;
        if (!img || img.uploading || typeof window.Cropper === 'undefined') {
            destroyCropper();
            if (state.images.length && !img) showHintPick();
            return;
        }
        const key = panel + '|' + img.uuid + '|' + targetRatio().toFixed(4);
        if (!force && cropper && key === cropperKey) return;
        destroyCropper();
        cropperKey = key;
        const el = $('[data-editor-image]');
        $('[data-editor]').classList.remove('d-none');
        $('[data-toolbar]').hidden = false;
        $('[data-hint]').hidden = false;
        el.onload = null;
        el.src = img.previewUrl;
        const saved = state.crops[panel];
        const start = () => {
            cropper = new window.Cropper(el, {
                viewMode: 1,
                dragMode: 'move',
                aspectRatio: targetRatio(),
                autoCropArea: 0.92,
                cropBoxMovable: false,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,
                background: false,
                guides: true,
                center: true,
                responsive: true,
                restore: false,
                checkOrientation: false, // previews are already upright
                ready() {
                    if (saved && saved.img === idx && Math.abs(saved.ratio - targetRatio()) < 0.001) {
                        cropper.setData(saved.data);
                    }
                    ensureCover(true);
                    storeCrop();
                },
                cropend: () => storeCrop(),
                zoom(e) {
                    const cover = coverRatio();
                    if (e.detail.ratio < cover) { e.preventDefault(); cropper.zoomTo(cover); }
                    setTimeout(storeCrop, 0);
                },
            });
        };
        if (el.complete && el.naturalWidth) start(); else el.onload = start;
    }

    function showHintPick() {
        $('[data-hint]').hidden = false;
        $('[data-hint]').textContent = t('pick_photo_for_panel', { n: state.activePanel + 1 });
    }

    function coverRatio() {
        if (!cropper) return 0;
        const cb = cropper.getCropBoxData();
        const im = cropper.getImageData();
        const rot = Math.abs((im.rotate || 0) % 180) === 90;
        const nw = rot ? im.naturalHeight : im.naturalWidth;
        const nh = rot ? im.naturalWidth : im.naturalHeight;
        return Math.max(cb.width / nw, cb.height / nh);
    }

    function ensureCover(center) {
        if (!cropper) return;
        const cover = coverRatio();
        const cur = cropper.getImageData().width / cropper.getImageData().naturalWidth;
        if (cur < cover - 1e-6) cropper.zoomTo(cover);
        const cb = cropper.getCropBoxData();
        const cv = cropper.getCanvasData();
        let left = cv.left, top = cv.top;
        if (center) { left = cb.left + (cb.width - cv.width) / 2; top = cb.top + (cb.height - cv.height) / 2; }
        left = Math.min(cb.left, Math.max(cb.left + cb.width - cv.width, left));
        top = Math.min(cb.top, Math.max(cb.top + cb.height - cv.height, top));
        cropper.setCanvasData({ left, top });
        updateZoomSlider();
    }

    function updateZoomSlider() {
        if (!cropper) return;
        const cover = coverRatio();
        const cur = cropper.getImageData().width / cropper.getImageData().naturalWidth;
        $('[data-zoom]').value = String(Math.round(Math.min(100, Math.max(0, (cur / cover - 1) / 3 * 100))));
    }

    const storeCrop = debounce(() => {
        if (!cropper) return;
        const panel = mode() === 'per_panel' ? state.activePanel : 0;
        state.crops[panel] = { img: state.assign[panel], data: cropper.getData(true), ratio: targetRatio() };
        updateZoomSlider();
        renderPanelTabs();
        updateQuality();
        scheduleMockup();
        renderSteps();
        updateSaveState();
    }, 120);

    // toolbar
    $$('[data-action]').forEach(b => b.addEventListener('click', () => {
        if (!cropper) return;
        switch (b.dataset.action) {
            case 'zoom-in': cropper.zoom(0.1); break;
            case 'zoom-out': cropper.zoom(-0.1); break;
            case 'rotate-left': cropper.rotate(-90); ensureCover(true); break;
            case 'rotate-right': cropper.rotate(90); ensureCover(true); break;
            case 'center': ensureCover(true); break;
            case 'reset': cropper.reset(); ensureCover(true); break;
        }
        storeCrop();
    }));
    $('[data-zoom]').addEventListener('input', (e) => {
        if (!cropper) return;
        cropper.zoomTo(coverRatio() * (1 + Number(e.target.value) / 100 * 3));
        ensureCover(false);
        storeCrop();
    });

    // ------------------------------------------------------ crops & quality
    /** Crop converted from preview pixels to ORIGINAL photo pixels (what the server and production use). */
    function originalCrop(panel) {
        const c = state.crops[panel];
        if (!c) return null;
        const img = state.images[c.img];
        if (!img || !img.el || !img.el.naturalWidth) return null;
        const f = img.width / img.el.naturalWidth; // previews keep the original proportions (oriented)
        return {
            x: Math.round(c.data.x * f), y: Math.round(c.data.y * f),
            width: Math.round(c.data.width * f), height: Math.round(c.data.height * f),
            rotate: ((c.data.rotate || 0) % 360 + 360) % 360,
        };
    }

    function minDpi() {
        let min = Infinity;
        for (let p = 0; p < neededImages(); p++) {
            const oc = originalCrop(p);
            if (!oc) return null;
            min = Math.min(min, Math.floor(oc.width / (printWidthCm() / 2.54)));
        }
        return min === Infinity ? null : min;
    }

    function updateQuality() {
        const badge = $('[data-quality]');
        const warn = $('[data-dpi-warning]');
        // make sure preview elements are loaded for every assigned image
        Object.values(state.assign).forEach(i => { const im = state.images[i]; if (im && !im.el && im.previewUrl) loadImageEl(im).then(updateQuality).catch(() => {}); });
        const dpi = minDpi();
        if (dpi === null) { badge.classList.add('d-none'); warn.classList.add('d-none'); return; }
        const level = dpi < cfg.limits.dpiMin ? 'low' : (dpi < cfg.limits.dpiWarn ? 'warning' : 'ok');
        badge.className = 'quality-badge ms-auto is-' + level;
        badge.textContent = t('quality_' + level) + ' · ' + dpi + ' dpi';
        warn.classList.toggle('d-none', level === 'ok');
        warn.className = 'alert small mt-3 ' + (level === 'low' ? 'alert-danger' : 'alert-warning') + (level === 'ok' ? ' d-none' : '');
        warn.textContent = level === 'low' ? t('dpi_low', { dpi, min: cfg.limits.dpiMin }) : t('dpi_warning', { dpi });
    }

    // ---------------------------------------------------------------- mockup
    function rotated(img, rot) {
        const key = img.uuid + '|' + rot;
        if (rotatedCache.has(key)) return rotatedCache.get(key);
        const el = img.el;
        const c = document.createElement('canvas');
        const swap = rot % 180 === 90;
        c.width = swap ? el.naturalHeight : el.naturalWidth;
        c.height = swap ? el.naturalWidth : el.naturalHeight;
        const ctx = c.getContext('2d');
        ctx.translate(c.width / 2, c.height / 2);
        ctx.rotate(rot * Math.PI / 180);
        ctx.drawImage(el, -el.naturalWidth / 2, -el.naturalHeight / 2);
        rotatedCache.set(key, c);
        return c;
    }

    /** Draw the composition (panels, gaps, frames) onto a canvas. Returns false when nothing to draw. */
    function drawComposition(canvas, maxPx, withBackground) {
        const n = panels();
        const o = overall();
        const f = frame();
        const frameCm = f ? Math.max(1.5, f.profileMm / 10) : 0;
        const totalWcm = o.w + 2 * frameCm * n;
        const totalHcm = o.h + 2 * frameCm;
        const pad = withBackground ? 0.08 : 0.02;
        const scale = maxPx / Math.max(totalWcm, totalHcm) / (1 + 2 * pad);
        canvas.width = Math.round(totalWcm * scale * (1 + 2 * pad));
        canvas.height = Math.round(totalHcm * scale * (1 + 2 * pad));
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = withBackground ? '#EEE9E1' : 'rgba(0,0,0,0)';
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (withBackground) ctx.fillRect(0, 0, canvas.width, canvas.height);
        const offX = (canvas.width - totalWcm * scale) / 2;
        const offY = (canvas.height - totalHcm * scale) / 2;

        // source for span mode: one crop covering the whole composition
        let spanSrc = null;
        if (mode() === 'span') {
            const c = state.crops[0];
            const img = c ? state.images[c.img] : null;
            if (img && img.el) spanSrc = { canvas: rotated(img, ((c.data.rotate || 0) % 360 + 360) % 360), d: c.data };
        }
        let drawn = false;
        let xCm = 0;
        for (let i = 0; i < n; i++) {
            const px = offX + (xCm + frameCm) * scale;
            const x0 = offX + xCm * scale;
            const pw = state.w * scale;
            const ph = state.h * scale;
            const py = offY + frameCm * scale;
            // shadow + frame
            ctx.save();
            ctx.shadowColor = 'rgba(23,23,23,.28)';
            ctx.shadowBlur = Math.max(6, scale * 3);
            ctx.shadowOffsetX = Math.max(2, scale * 0.8);
            ctx.shadowOffsetY = Math.max(3, scale * 1.2);
            ctx.fillStyle = f ? f.color : '#ddd';
            ctx.fillRect(x0, offY, pw + 2 * frameCm * scale, ph + 2 * frameCm * scale);
            ctx.restore();
            // image
            let src = null;
            if (spanSrc) {
                const d = spanSrc.d;
                const panelStartCm = i * (state.w + layout().gap);
                src = { c: spanSrc.canvas, sx: d.x + d.width * (panelStartCm / o.w), sy: d.y, sw: d.width * (state.w / o.w), sh: d.height };
            } else if (state.crops[i]) {
                const c = state.crops[i];
                const img = state.images[c.img];
                if (img && img.el) src = { c: rotated(img, ((c.data.rotate || 0) % 360 + 360) % 360), sx: c.data.x, sy: c.data.y, sw: c.data.width, sh: c.data.height };
            }
            if (src) {
                ctx.drawImage(src.c, src.sx, src.sy, src.sw, src.sh, px, py, pw, ph);
                drawn = true;
            } else {
                ctx.fillStyle = '#d9d2c5';
                ctx.fillRect(px, py, pw, ph);
                ctx.fillStyle = '#8c8578';
                ctx.font = Math.round(Math.min(pw, ph) / 6) + 'px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('+', px + pw / 2, py + ph / 2);
            }
            if (f) { // inner bevel line
                ctx.strokeStyle = 'rgba(255,255,255,.25)';
                ctx.lineWidth = 1;
                ctx.strokeRect(px - 1, py - 1, pw + 2, ph + 2);
            } else if (material().type === 'canvas') { // canvas depth edge
                ctx.fillStyle = 'rgba(0,0,0,.12)';
                ctx.fillRect(px + pw - Math.max(2, scale * 0.6), py, Math.max(2, scale * 0.6), ph);
            }
            xCm += state.w + 2 * frameCm + layout().gap;
        }
        return drawn;
    }

    const scheduleMockup = debounce(renderMockup, 80);
    function renderMockup() {
        const wall = $('[data-stage="wall"]');
        if (wall.classList.contains('d-none')) return;
        const canvas = $('[data-composition]');
        drawComposition(canvas, 900, false);
        const o = overall();
        const f = frame();
        const frameCm = f ? Math.max(1.5, f.profileMm / 10) : 0;
        const widthCm = o.w + 2 * frameCm * panels();
        $('[data-composition-wrap]').style.width = Math.min(92, widthCm / WALL_CM * 100) + '%';
    }

    $$('[data-stage-tab]').forEach(b => b.addEventListener('click', () => {
        $$('[data-stage-tab]').forEach(x => x.classList.toggle('active', x === b));
        const v = b.dataset.stageTab;
        $$('[data-stage]').forEach(s => s.classList.toggle('d-none', s.dataset.stage !== v));
        $('[data-toolbar]').classList.toggle('d-none', v === 'wall');
        $('[data-hint]').classList.toggle('d-none', v === 'wall');
        if (v === 'wall') {
            Promise.all(Object.values(state.assign).map(i => state.images[i] ? loadImageEl(state.images[i]) : null)).then(renderMockup).catch(renderMockup);
        } else if (cropper) {
            setTimeout(() => { openEditor(true); }, 0);
        }
    }));

    // ----------------------------------------------------------------- price
    function payload() {
        return {
            product: cfg.product ? cfg.product.slug : null,
            material_id: state.materialId,
            frame_id: state.frameId,
            layout: state.layout,
            mode: mode(),
            panel_width_cm: state.w,
            panel_height_cm: state.h,
            border: material().type === 'canvas' ? state.border : null,
            finish: state.finish,
            quantity: state.qty,
        };
    }

    const fetchPrice = debounce(() => {
        const box = $('[data-price-total]');
        box.classList.add('loading');
        window.bwaFetch(cfg.urls.price, { method: 'POST', body: payload() })
            .then((q) => {
                state.quote = q;
                box.textContent = q.total_label;
                $('[data-price-unit]').textContent = state.qty > 1 ? t('unit_price', { price: q.unit_label }) : '';
                const ul = $('[data-breakdown]');
                ul.innerHTML = '';
                q.breakdown.forEach(l => {
                    const li = document.createElement('li');
                    li.className = 'd-flex justify-content-between gap-3';
                    li.innerHTML = '<span></span><span></span>';
                    li.children[0].textContent = l.label;
                    li.children[1].textContent = l.amount;
                    ul.appendChild(li);
                });
                hideError();
            })
            .catch((e) => { state.quote = null; box.textContent = '—'; showError(e.message); })
            .finally(() => { box.classList.remove('loading'); renderSteps(); updateSaveState(); });
    }, 250);

    // ------------------------------------------------------------------ save
    function updateSaveState() {
        const ready = Object.keys(state.crops).filter(p => Number(p) < neededImages()).length >= neededImages();
        const dpi = minDpi();
        const btn = $('[data-save]');
        btn.disabled = !ready || !state.quote || dpi === null || dpi < cfg.limits.dpiMin;
        $('[data-save-help]').textContent = !state.images.length ? t('save_help') : (!ready ? t('need_photos', { n: neededImages() }) : t('save_ready'));
    }

    $('[data-save]').addEventListener('click', () => {
        const btn = $('[data-save]');
        btn.disabled = true;
        btn.dataset.label = btn.dataset.label || btn.innerHTML;
        btn.textContent = t('saving');
        const images = [];
        for (let p = 0; p < neededImages(); p++) {
            const c = state.crops[p];
            images.push({ uuid: state.images[c.img].uuid, panel: p, crop: originalCrop(p) });
        }
        const pc = document.createElement('canvas');
        let preview = null;
        try { drawComposition(pc, 1400, true); preview = pc.toDataURL('image/jpeg', 0.85); } catch (e) { preview = null; }
        window.bwaFetch(cfg.urls.save, { method: 'POST', body: Object.assign(payload(), { images, preview }) })
            .then((res) => {
                state.saved = res.design;
                const box = $('[data-save-result]');
                box.classList.remove('d-none');
                box.innerHTML = '';
                const title = document.createElement('p');
                title.className = 'fw-semibold mb-2';
                title.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>';
                title.append(t('saved_ok', { price: res.design.total_label }));
                const link = document.createElement('a');
                link.className = 'btn btn-gold w-100';
                link.href = res.design.url;
                link.textContent = t('view_design');
                box.append(title, link);
                renderSteps();
            })
            .catch((e) => showError(e.message))
            .finally(() => { btn.innerHTML = btn.dataset.label; updateSaveState(); });
    });

    // ---------------------------------------------------------------- events
    function showError(msg) { const el = $('[data-error]'); el.textContent = msg; el.classList.remove('d-none'); }
    function hideError() { $('[data-error]').classList.add('d-none'); }

    function changed(layoutChanged) {
        state.saved = null;
        $('[data-save-result]').classList.add('d-none');
        if (layoutChanged && mode() === 'span') { const first = state.assign[0]; state.assign = first !== undefined ? { 0: first } : {}; }
        renderOptions();
        openEditor();
        fetchPrice();
        updateQuality();
        scheduleMockup();
        updateSaveState();
    }

    $$('[data-orientation] button').forEach(b => b.addEventListener('click', () => {
        if (state.orientation === b.dataset.value) return;
        state.orientation = b.dataset.value;
        const longS = Math.max(state.w, state.h), shortS = Math.min(state.w, state.h);
        if (state.orientation === 'landscape') { state.w = longS; state.h = shortS; }
        else if (state.orientation === 'portrait') { state.w = shortS; state.h = longS; }
        else {
            const sq = cfg.sizes.find(s => s.w === s.h);
            state.w = state.h = sq ? sq.w : shortS;
        }
        changed();
    }));
    $$('[data-mode] button').forEach(b => b.addEventListener('click', () => {
        state.mode = b.dataset.value;
        state.crops = {};
        if (state.mode === 'per_panel') { const first = state.assign[0]; state.assign = {}; for (let p = 0; p < panels(); p++) if (first !== undefined) state.assign[p] = first; }
        state.activePanel = 0;
        changed();
    }));
    $('[data-apply-custom]').addEventListener('click', () => {
        const w = Math.round(Number($('[data-custom-w]').value) * 10) / 10;
        const h = Math.round(Number($('[data-custom-h]').value) * 10) / 10;
        const m = material();
        if (!(w >= m.min && h >= m.min && Math.max(w, h) <= m.max && Math.min(w, h) <= m.maxShort)) { showError(t('size_range', { min: m.min, max: m.max })); return; }
        state.w = w;
        state.h = h;
        state.orientation = w === h ? 'square' : (w > h ? 'landscape' : 'portrait');
        changed();
    });
    const qtyInput = $('[data-quantity]');
    $$('[data-qty] [data-step]').forEach(b => b.addEventListener('click', () => {
        qtyInput.value = String(Math.min(cfg.limits.maxQty, Math.max(1, (Number(qtyInput.value) || 1) + Number(b.dataset.step))));
        qtyInput.dispatchEvent(new Event('change'));
    }));
    qtyInput.addEventListener('change', () => {
        state.qty = Math.min(cfg.limits.maxQty, Math.max(1, Math.round(Number(qtyInput.value) || 1)));
        qtyInput.value = String(state.qty);
        state.saved = null;
        fetchPrice();
    });

    $$('[data-file-input]').forEach(inp => inp.addEventListener('change', () => { handleFiles(inp.files); inp.value = ''; }));
    const dz = $('[data-dropzone]');
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('dragging'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('dragging'); }));
    dz.addEventListener('drop', (e) => { if (e.dataTransfer && e.dataTransfer.files) handleFiles(e.dataTransfer.files); });

    // recent uploads (page reload / returning visitor)
    (cfg.recent || []).forEach(r => state.images.push({ uuid: r.uuid, width: r.width, height: r.height, name: r.name, previewUrl: r.preview, thumbUrl: r.thumb }));

    renderOptions();
    renderPhotoStrip();
    fetchPrice();
    updateSaveState();
})();
