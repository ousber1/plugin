/* BERRADI WALL ART — "Sur mon mur" studio (product page).
 * Three modes: creative room scenes, the customer's own wall photo, and the live camera (pro: real-scale calibration,
 * level indicator, capture). Everything happens in the browser: no photo or camera frame is ever uploaded.
 * Scale: scenes are drawn in centimetres (viewBox 360 × 300 cm), photos use the visible wall width (estimated or
 * calibrated against a known object), so the artwork always appears at its real size.
 */
(function () {
    'use strict';

    const dlg = document.querySelector('[data-wall-studio]');
    if (!dlg) return;

    const q = function (s, r) { return (r || dlg).querySelector(s); };
    const qa = function (s, r) { return Array.prototype.slice.call((r || dlg).querySelectorAll(s)); };
    let t = {};
    try { t = JSON.parse(q('[data-ws-i18n]').textContent || '{}'); } catch (e) { t = {}; }

    const stage = q('[data-ws-stage]');
    const sceneEl = q('[data-ws-scene]');
    const photoEl = q('[data-ws-photo]');
    const videoEl = q('[data-ws-video]');
    const artEl = q('[data-ws-art]');
    const lightEl = q('[data-ws-light]');
    const measureEl = q('[data-ws-measure]');
    const dimEl = q('[data-ws-dim]');
    const hintEl = q('[data-ws-hint]');
    const emptyEl = q('[data-ws-empty]');
    const levelEl = q('[data-ws-level]');
    const form = document.querySelector('[data-product-form]');
    let variants = [];
    try { variants = JSON.parse((form && form.getAttribute('data-variants')) || '[]'); } catch (e) { variants = []; }

    // ------------------------------------------------------------------ constants (centimetres)
    const SCENE_W = 360;
    const SCENE_H = 300;
    const FLOOR_Y = 258;          // wall visible from the floor up to 258 cm
    const PANEL_GAP = 3;          // gap between panels of a multi-panel artwork
    const FRAME_CM = 2.5;         // frame moulding width

    const state = {
        mode: 'creative',
        variant: null,
        artUrl: dlg.getAttribute('data-art') || '',
        artImg: null,
        scene: 'living', wall: '#F4F1EA', floor: 'oak', light: 'day',
        cx: SCENE_W / 2, cy: 150, moved: false,       // creative: centre (cm from left, cm above floor)
        fx: 0.5, fy: 0.42,                            // photo / camera: centre as a fraction of the stage
        visibleCm: 300, calibrated: false,
        photoUrl: null, shotUrl: null, stream: null,
        measuring: false, m: { ax: 0.3, ay: 0.55, bx: 0.7, by: 0.55 },
        tilt: null
    };

    // ------------------------------------------------------------------ furniture (SVG, drawn in cm from the floor line)
    const F = {
        sofa: function (x) {
            return { w: 220, top: 85, svg: '<g transform="translate(' + x + ',' + (FLOOR_Y - 85) + ')">'
                + '<ellipse cx="110" cy="86" rx="118" ry="5" fill="rgba(0,0,0,.12)"/>'
                + '<rect x="4" y="18" width="212" height="52" rx="12" fill="#B6AC9C"/>'
                + '<rect x="0" y="38" width="28" height="44" rx="10" fill="#A69C8C"/><rect x="192" y="38" width="28" height="44" rx="10" fill="#A69C8C"/>'
                + '<rect x="24" y="52" width="172" height="26" rx="7" fill="#C8BFB0"/><line x1="110" y1="53" x2="110" y2="77" stroke="#B1A797" stroke-width="1.2"/>'
                + '<rect x="34" y="30" width="36" height="28" rx="8" fill="#D8CBB1"/><rect x="150" y="30" width="36" height="28" rx="8" fill="#8F9C8A"/>'
                + '<rect x="12" y="80" width="5" height="5" fill="#4F473C"/><rect x="203" y="80" width="5" height="5" fill="#4F473C"/></g>' };
        },
        plant: function (x) {
            return { w: 44, top: 140, svg: '<g transform="translate(' + x + ',' + (FLOOR_Y - 140) + ')">'
                + '<ellipse cx="22" cy="141" rx="22" ry="3" fill="rgba(0,0,0,.12)"/>'
                + '<path d="M22 95 C10 60 2 40 6 18 M22 95 C26 55 34 35 40 10 M22 95 C18 70 20 40 22 2" stroke="#4C6B48" stroke-width="2" fill="none"/>'
                + '<ellipse cx="8" cy="22" rx="7" ry="15" fill="#5E8158" transform="rotate(-20 8 22)"/><ellipse cx="38" cy="16" rx="7" ry="15" fill="#6E9367" transform="rotate(25 38 16)"/>'
                + '<ellipse cx="22" cy="8" rx="6" ry="13" fill="#557A50"/><ellipse cx="13" cy="52" rx="7" ry="14" fill="#6E9367" transform="rotate(-35 13 52)"/>'
                + '<ellipse cx="33" cy="48" rx="7" ry="14" fill="#5E8158" transform="rotate(35 33 48)"/>'
                + '<path d="M6 95 h32 l-4 45 h-24 z" fill="#D9D2C5"/></g>' };
        },
        bed: function (x) {
            return { w: 180, top: 118, svg: '<g transform="translate(' + x + ',' + (FLOOR_Y - 118) + ')">'
                + '<ellipse cx="90" cy="119" rx="96" ry="4" fill="rgba(0,0,0,.12)"/>'
                + '<rect x="0" y="0" width="180" height="96" rx="6" fill="#8C7A66"/><rect x="8" y="8" width="164" height="80" rx="4" fill="#9C8A75"/>'
                + '<rect x="-6" y="70" width="192" height="40" rx="6" fill="#EDE8DF"/><rect x="-6" y="84" width="192" height="26" rx="5" fill="#C9B9A2"/>'
                + '<rect x="14" y="56" width="66" height="24" rx="9" fill="#F7F4EE"/><rect x="100" y="56" width="66" height="24" rx="9" fill="#F7F4EE"/>'
                + '<rect x="0" y="110" width="6" height="8" fill="#4F473C"/><rect x="174" y="110" width="6" height="8" fill="#4F473C"/></g>' };
        },
        nightstand: function (x) {
            return { w: 44, top: 100, svg: '<g transform="translate(' + x + ',' + (FLOOR_Y - 100) + ')">'
                + '<rect x="0" y="48" width="44" height="46" rx="3" fill="#A28C74"/><line x1="2" y1="70" x2="42" y2="70" stroke="#8C7660" stroke-width="1"/>'
                + '<rect x="4" y="94" width="4" height="6" fill="#4F473C"/><rect x="36" y="94" width="4" height="6" fill="#4F473C"/>'
                + '<rect x="19" y="22" width="6" height="26" fill="#3E3A35"/><path d="M8 24 L36 24 L30 4 L14 4 Z" fill="#EFE3CC"/></g>' };
        },
        desk: function (x) {
            return { w: 140, top: 76, svg: '<g transform="translate(' + x + ',' + (FLOOR_Y - 120) + ')">'
                + '<ellipse cx="70" cy="121" rx="74" ry="3" fill="rgba(0,0,0,.12)"/>'
                + '<rect x="0" y="44" width="140" height="5" fill="#6F5B47"/><rect x="6" y="49" width="4" height="71" fill="#3E3A35"/><rect x="130" y="49" width="4" height="71" fill="#3E3A35"/>'
                + '<rect x="84" y="49" width="44" height="22" fill="#7E6955"/><line x1="96" y1="60" x2="116" y2="60" stroke="#5C4A39" stroke-width="1.5"/>'
                + '<rect x="18" y="26" width="34" height="18" rx="1" fill="#2E2E2E"/><rect x="33" y="44" width="4" height="0" fill="#2E2E2E"/>'
                + '<rect x="114" y="10" width="3" height="34" fill="#3E3A35"/><path d="M104 12 L128 12 L122 0 L110 0 Z" fill="#C6A66B"/>'
                + '<rect x="58" y="38" width="22" height="6" rx="1" fill="#D9CDB5"/></g>' };
        },
        table: function (x) {
            return { w: 180, top: 76, svg: '<g transform="translate(' + x + ',' + (FLOOR_Y - 76) + ')">'
                + '<ellipse cx="90" cy="77" rx="92" ry="3" fill="rgba(0,0,0,.12)"/>'
                + '<rect x="0" y="0" width="180" height="6" rx="2" fill="#8C6F55"/><rect x="12" y="6" width="5" height="70" fill="#6F5843"/><rect x="163" y="6" width="5" height="70" fill="#6F5843"/>'
                + '<path d="M78 -16 h24 l-4 16 h-16 z" fill="#E5DCCB"/><path d="M84 -30 c-6 -8 2 -14 6 -6 c4 -8 12 -2 6 6" fill="#8F9C8A"/></g>' };
        },
        chair: function (x) {
            return { w: 46, top: 95, svg: '<g transform="translate(' + x + ',' + (FLOOR_Y - 95) + ')">'
                + '<rect x="4" y="0" width="38" height="50" rx="6" fill="#C9B9A2"/><rect x="0" y="48" width="46" height="8" rx="3" fill="#B9A78E"/>'
                + '<rect x="4" y="56" width="4" height="39" fill="#4F473C"/><rect x="38" y="56" width="4" height="39" fill="#4F473C"/></g>' };
        },
        console: function (x) {
            return { w: 120, top: 80, svg: '<g transform="translate(' + x + ',' + (FLOOR_Y - 118) + ')">'
                + '<ellipse cx="60" cy="119" rx="62" ry="3" fill="rgba(0,0,0,.12)"/>'
                + '<rect x="0" y="38" width="120" height="6" fill="#2F2B27"/><rect x="0" y="44" width="120" height="22" fill="#3B3631"/>'
                + '<line x1="40" y1="44" x2="40" y2="66" stroke="#2A2622" stroke-width="1"/><line x1="80" y1="44" x2="80" y2="66" stroke="#2A2622" stroke-width="1"/>'
                + '<rect x="6" y="66" width="4" height="52" fill="#2F2B27"/><rect x="110" y="66" width="4" height="52" fill="#2F2B27"/>'
                + '<path d="M86 38 c-6 0 -8 -10 -4 -18 c2 -4 2 -8 0 -12 h8 c-2 4 -2 8 0 12 c4 8 2 18 -4 18z" fill="#D7C3A2"/>'
                + '<rect x="16" y="30" width="26" height="8" rx="1" fill="#C6A66B"/></g>' };
        }
    };
    const SCENES = {
        living: [['sofa', 70], ['plant', 302]],
        bedroom: [['nightstand', 34], ['bed', 90], ['nightstand', 282]],
        office: [['plant', 22], ['desk', 110]],
        dining: [['table', 90], ['chair', 72], ['chair', 242]],
        entry: [['console', 120], ['plant', 284]]
    };
    const FLOORS = {
        oak: { base: '#C9AA82', line: 'rgba(110,80,50,.25)', kind: 'planks' },
        walnut: { base: '#7B5B43', line: 'rgba(40,25,15,.35)', kind: 'planks' },
        concrete: { base: '#A9A59E', line: 'rgba(0,0,0,.06)', kind: 'plain' },
        tiles: { base: '#E4DFD6', line: 'rgba(120,110,95,.35)', kind: 'tiles' }
    };

    function shade(hex, pct) {
        const n = parseInt(hex.slice(1), 16);
        const f = function (c) { return Math.max(0, Math.min(255, Math.round(c + (pct < 0 ? c : 255 - c) * pct))); };
        return 'rgb(' + f(n >> 16) + ',' + f((n >> 8) & 255) + ',' + f(n & 255) + ')';
    }

    function sceneSvg() {
        const fl = FLOORS[state.floor] || FLOORS.oak;
        let floor = '<rect x="0" y="' + FLOOR_Y + '" width="' + SCENE_W + '" height="' + (SCENE_H - FLOOR_Y) + '" fill="' + fl.base + '"/>';
        if (fl.kind === 'planks') {
            for (let i = 0, y = FLOOR_Y + 4; y < SCENE_H; i++, y += 4 + i * 2) {
                floor += '<line x1="0" y1="' + y + '" x2="' + SCENE_W + '" y2="' + y + '" stroke="' + fl.line + '" stroke-width="0.6"/>';
            }
        } else if (fl.kind === 'tiles') {
            for (let x = -40; x <= SCENE_W + 40; x += 40) {
                floor += '<line x1="' + (SCENE_W / 2 + (x - SCENE_W / 2) * 0.55) + '" y1="' + FLOOR_Y + '" x2="' + x + '" y2="' + SCENE_H + '" stroke="' + fl.line + '" stroke-width="0.6"/>';
            }
            [FLOOR_Y + 9, FLOOR_Y + 22].forEach(function (y) { floor += '<line x1="0" y1="' + y + '" x2="' + SCENE_W + '" y2="' + y + '" stroke="' + fl.line + '" stroke-width="0.6"/>'; });
        }
        let furniture = '';
        (SCENES[state.scene] || SCENES.living).forEach(function (item) { furniture += F[item[0]](item[1]).svg; });
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + SCENE_W + ' ' + SCENE_H + '" preserveAspectRatio="none" width="100%" height="100%">'
            + '<defs><linearGradient id="wsWall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + shade(state.wall, -0.07) + '"/><stop offset="1" stop-color="' + state.wall + '"/></linearGradient>'
            + '<linearGradient id="wsFloor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="rgba(0,0,0,.18)"/><stop offset=".35" stop-color="rgba(0,0,0,0)"/></linearGradient></defs>'
            + '<rect width="' + SCENE_W + '" height="' + FLOOR_Y + '" fill="url(#wsWall)"/>' + floor
            + '<rect x="0" y="' + FLOOR_Y + '" width="' + SCENE_W + '" height="' + (SCENE_H - FLOOR_Y) + '" fill="url(#wsFloor)"/>'
            + '<rect x="0" y="' + (FLOOR_Y - 7) + '" width="' + SCENE_W + '" height="7" fill="' + shade(state.wall, 0.45) + '"/><line x1="0" y1="' + (FLOOR_Y - 7) + '" x2="' + SCENE_W + '" y2="' + (FLOOR_Y - 7) + '" stroke="rgba(0,0,0,.08)" stroke-width="0.5"/>'
            + furniture + '</svg>';
    }

    function sceneTop() {
        let top = 0;
        (SCENES[state.scene] || []).forEach(function (item) {
            const f = F[item[0]](item[1]);
            // only furniture under the artwork counts (centre third of the wall)
            if (item[1] < SCENE_W * 0.66 && item[1] + f.w > SCENE_W * 0.33) top = Math.max(top, f.top);
        });
        return top;
    }

    // ------------------------------------------------------------------ variant & artwork
    function currentVariant() {
        const input = form && form.querySelector('[data-variant-input]');
        const id = input ? parseInt(input.value, 10) : 0;
        return variants.find(function (v) { return v.id === id; }) || variants[0] || null;
    }
    function artSize() {
        const v = state.variant;
        return v ? { w: Math.max(5, v.width), h: Math.max(5, v.height), panels: Math.max(1, v.panels || 1), frame: v.frameColor || null } : { w: 60, h: 90, panels: 1, frame: null };
    }
    function loadArt(url, done) {
        if (!url) { state.artImg = null; done(); return; }
        if (state.artImg && state.artImg.getAttribute('data-src') === url) { done(); return; }
        const img = new Image();
        img.onload = function () { state.artImg = img; done(); };
        img.onerror = function () { state.artImg = null; done(); };
        img.setAttribute('data-src', url);
        img.src = url;
    }

    // ------------------------------------------------------------------ layout (stage pixels)
    function stageSize() { return { w: stage.clientWidth, h: stage.clientHeight }; }
    function pxPerCm(s) { return state.mode === 'creative' ? s.w / SCENE_W : s.w / state.visibleCm; }
    function defaultCreativePosition() {
        const a = artSize();
        const minCentre = sceneTop() + 22 + a.h / 2;
        state.cx = SCENE_W / 2;
        state.cy = Math.min(Math.max(145, minCentre), FLOOR_Y - a.h / 2 - 4);
    }
    function layout() {
        const s = stageSize();
        const ppc = pxPerCm(s);
        const a = artSize();
        const w = a.w * ppc;
        const h = a.h * ppc;
        let left, top;
        if (state.mode === 'creative') {
            left = (state.cx - a.w / 2) * ppc;
            top = (FLOOR_Y - state.cy - a.h / 2) * ppc;
        } else {
            left = state.fx * s.w - w / 2;
            top = state.fy * s.h - h / 2;
        }
        return { left: left, top: top, w: w, h: h, ppc: ppc, a: a, s: s };
    }

    /** cover-fit of the artwork image over the whole artwork box: returns the drawing scale and crop offsets */
    function coverMap(boxW, boxH) {
        const img = state.artImg;
        if (!img || !img.naturalWidth) return null;
        const sc = Math.max(boxW / img.naturalWidth, boxH / img.naturalHeight);
        return { sc: sc, ox: (img.naturalWidth * sc - boxW) / 2, oy: (img.naturalHeight * sc - boxH) / 2, iw: img.naturalWidth * sc, ih: img.naturalHeight * sc };
    }
    function panelRects(L) {
        const a = L.a;
        const frame = a.frame && dlg.getAttribute('data-effect') === 'is-framed' ? FRAME_CM * L.ppc : 0;
        const gap = a.panels > 1 ? PANEL_GAP * L.ppc : 0;
        const pw = (L.w - gap * (a.panels - 1)) / a.panels;
        const out = [];
        for (let i = 0; i < a.panels; i++) out.push({ x: i * (pw + gap), w: pw, frame: frame });
        return out;
    }

    // ------------------------------------------------------------------ render
    function render() {
        if (!dlg.open) return;
        const L = layout();
        const empty = (state.mode === 'photo' && !state.photoUrl) || (state.mode === 'camera' && !state.stream && !state.shotUrl);
        artEl.classList.toggle('d-none', empty);
        emptyEl.classList.toggle('d-none', !empty);
        if (empty) q('[data-ws-empty-text]').textContent = state.mode === 'photo' ? (t.photo_empty || '') : (t.camera_empty || '');
        artEl.style.left = L.left + 'px';
        artEl.style.top = L.top + 'px';
        artEl.style.width = L.w + 'px';
        artEl.style.height = L.h + 'px';
        artEl.className = 'ws-art ' + (dlg.getAttribute('data-effect') || 'is-canvas') + (empty ? ' d-none' : '') + (drag ? ' is-dragging' : '');
        const map = coverMap(L.w, L.h);
        const rects = panelRects(L);
        let html = '';
        rects.forEach(function (r) {
            const bg = map ? 'background-image:url(\'' + encodeURI(state.artUrl).replace(/'/g, '%27') + '\');background-size:' + map.iw + 'px ' + map.ih + 'px;background-position:' + (-(map.ox + r.x)) + 'px ' + (-map.oy) + 'px;'
                : 'background-image:linear-gradient(135deg,#E9DCC0,#C6A66B);';   // product without a photo
            const fr = r.frame ? 'border:' + r.frame + 'px solid ' + L.a.frame + ';' : '';
            html += '<span class="ws-panel-piece" style="left:' + r.x + 'px;width:' + r.w + 'px;' + bg + fr + '"></span>';
        });
        artEl.innerHTML = html;
        const v = state.variant;
        dimEl.textContent = v ? (v.size || (Math.round(L.a.w) + ' × ' + Math.round(L.a.h) + ' cm')) + (L.a.panels > 1 ? ' · ' + L.a.panels + ' ×' : '') : '';
        // evening light: dim the room, warm spot on the artwork
        if (state.mode === 'creative' && state.light === 'evening') {
            const cx = (L.left + L.w / 2) / L.s.w * 100;
            const cy = (L.top + L.h / 2) / L.s.h * 100;
            lightEl.style.background = 'radial-gradient(ellipse 38% 46% at ' + cx + '% ' + cy + '%, rgba(255,214,150,.22) 0%, rgba(255,214,150,.06) 45%, rgba(14,12,28,.46) 100%)';
            lightEl.classList.remove('d-none');
        } else {
            lightEl.classList.add('d-none');
        }
        // sizes: active chip
        qa('[data-ws-size]').forEach(function (b) { b.classList.toggle('is-active', !!v && b.getAttribute('data-ws-size') === v.size); });
        renderMeasure();
    }

    function setStageRatio(w, h) { stage.style.aspectRatio = w + ' / ' + h; }

    // ------------------------------------------------------------------ modes
    function setMode(mode) {
        if (state.mode === 'camera' && mode !== 'camera') stopCamera();
        state.mode = mode;
        qa('[data-ws-mode]').forEach(function (b) {
            const on = b.getAttribute('data-ws-mode') === mode;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        qa('[data-ws-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-ws-panel') !== mode; });
        q('[data-ws-scale-group]').hidden = mode === 'creative';
        sceneEl.classList.toggle('d-none', mode !== 'creative');
        const showPhoto = (mode === 'photo' && state.photoUrl) || (mode === 'camera' && state.shotUrl && !state.stream);
        photoEl.classList.toggle('d-none', !showPhoto);
        if (showPhoto) {
            const want = mode === 'photo' ? state.photoUrl : state.shotUrl;
            if (photoEl.getAttribute('src') !== want) photoEl.src = want;   // re-assigning would fire "load" again
        }
        videoEl.classList.toggle('d-none', !(mode === 'camera' && state.stream));
        levelEl.classList.toggle('d-none', !(mode === 'camera' && state.stream && state.tilt !== null));
        if (mode === 'creative') setStageRatio(SCENE_W, SCENE_H);
        else if (showPhoto && photoEl.naturalWidth) setStageRatio(photoEl.naturalWidth, photoEl.naturalHeight);
        else if (mode === 'camera' && state.stream && videoEl.videoWidth) setStageRatio(videoEl.videoWidth, videoEl.videoHeight);
        else setStageRatio(4, 3);
        stopMeasure();
        hintEl.textContent = t.hint_drag || '';
        requestAnimationFrame(render);
    }

    // ------------------------------------------------------------------ sizes (kept in sync with the product form)
    function buildSizes() {
        const box = q('[data-ws-sizes]');
        const seen = {};
        box.innerHTML = '';
        variants.forEach(function (v) {
            if (seen[v.size]) return;
            seen[v.size] = true;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'ws-chip';
            b.dir = 'ltr';
            b.setAttribute('data-ws-size', v.size);
            b.textContent = v.size;
            b.addEventListener('click', function () {
                const radio = form && form.querySelector('input[name="opt_size"][value="' + CSS.escape(v.size) + '"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    state.variant = v;
                    afterSizeChange();
                }
            });
            box.appendChild(b);
        });
    }
    function afterSizeChange() {
        if (state.mode === 'creative' && !state.moved) defaultCreativePosition();
        render();
    }
    document.addEventListener('bwa:variant', function (e) {
        state.variant = e.detail || state.variant;
        afterSizeChange();
    });

    // ------------------------------------------------------------------ drag (pointer + keyboard)
    let drag = null;
    artEl.addEventListener('pointerdown', function (e) {
        if (state.measuring) return;
        e.preventDefault();
        artEl.setPointerCapture(e.pointerId);
        drag = { x: e.clientX, y: e.clientY, cx: state.cx, cy: state.cy, fx: state.fx, fy: state.fy };
        artEl.classList.add('is-dragging');
    });
    artEl.addEventListener('pointermove', function (e) {
        if (!drag) return;
        moveBy(e.clientX - drag.x, e.clientY - drag.y, drag);
    });
    const endDrag = function () { drag = null; artEl.classList.remove('is-dragging'); };
    artEl.addEventListener('pointerup', endDrag);
    artEl.addEventListener('pointercancel', endDrag);
    artEl.addEventListener('keydown', function (e) {
        const step = e.shiftKey ? 10 : 2;
        const d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
        if (!d) return;
        e.preventDefault();
        moveBy(d[0], d[1], { cx: state.cx, cy: state.cy, fx: state.fx, fy: state.fy });
    });
    function moveBy(dx, dy, from) {
        const s = stageSize();
        const a = artSize();
        if (state.mode === 'creative') {
            const ppc = s.w / SCENE_W;
            state.cx = Math.min(SCENE_W - a.w / 2 * 0.2, Math.max(a.w / 2 * 0.2, from.cx + dx / ppc));
            state.cy = Math.min(FLOOR_Y + a.h / 2 - 10, Math.max(a.h / 2 * 0.2, from.cy - dy / ppc));
            state.moved = true;
        } else {
            state.fx = Math.min(0.98, Math.max(0.02, from.fx + dx / s.w));
            state.fy = Math.min(0.98, Math.max(0.02, from.fy + dy / s.h));
        }
        render();
    }

    // ------------------------------------------------------------------ own photo (never uploaded)
    q('[data-ws-file]').addEventListener('change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (!file || !/^image\//.test(file.type)) return;
        if (state.photoUrl) URL.revokeObjectURL(state.photoUrl);
        state.photoUrl = URL.createObjectURL(file);
        state.calibrated = false;
        q('[data-ws-calibrated]').hidden = true;
        photoEl.onload = function () { photoEl.onload = null; setMode('photo'); };
        photoEl.src = state.photoUrl;
        photoEl.classList.remove('d-none');
    });

    // ------------------------------------------------------------------ camera (pro)
    const camStart = q('[data-ws-cam-start]');
    const camCapture = q('[data-ws-cam-capture]');
    const camRetake = q('[data-ws-cam-retake]');
    function camMessage(key) {
        emptyEl.classList.remove('d-none');
        q('[data-ws-empty-text]').textContent = t[key] || '';
    }
    async function startCamera() {
        if (!window.isSecureContext) { camMessage('camera_insecure'); return; }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { camMessage('camera_none'); return; }
        requestMotion();
        try {
            state.stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } } });
        } catch (err) {
            state.stream = null;
            camMessage(err && (err.name === 'NotAllowedError' || err.name === 'SecurityError') ? 'camera_denied' : 'camera_none');
            return;
        }
        if (state.shotUrl) { URL.revokeObjectURL(state.shotUrl); state.shotUrl = null; }
        videoEl.srcObject = state.stream;
        videoEl.onloadedmetadata = function () { videoEl.onloadedmetadata = null; videoEl.play().catch(function () {}); setMode('camera'); };
        camStart.classList.add('d-none');
        camCapture.classList.remove('d-none');
        camRetake.classList.add('d-none');
        setMode('camera');
    }
    function stopCamera() {
        if (state.stream) state.stream.getTracks().forEach(function (tr) { tr.stop(); });
        state.stream = null;
        videoEl.srcObject = null;
        window.removeEventListener('devicemotion', onMotion);
        state.tilt = null;
        camCapture.classList.add('d-none');
        camStart.classList.toggle('d-none', !!state.shotUrl);
        camRetake.classList.toggle('d-none', !state.shotUrl);
    }
    camStart.addEventListener('click', startCamera);
    camRetake.addEventListener('click', startCamera);
    camCapture.addEventListener('click', function () {
        if (!state.stream || !videoEl.videoWidth) return;
        const c = document.createElement('canvas');
        c.width = videoEl.videoWidth;
        c.height = videoEl.videoHeight;
        c.getContext('2d').drawImage(videoEl, 0, 0);
        c.toBlob(function (blob) {
            if (!blob) return;
            state.shotUrl = URL.createObjectURL(blob);
            stopCamera();
            photoEl.onload = function () { photoEl.onload = null; setMode('camera'); };
            photoEl.src = state.shotUrl;
        }, 'image/jpeg', 0.92);
    });

    // level: in-plane tilt from gravity, and whether the phone is held vertically (facing the wall)
    function requestMotion() {
        try {
            if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission === 'function') {
                DeviceMotionEvent.requestPermission().then(function (r) { if (r === 'granted') window.addEventListener('devicemotion', onMotion); }).catch(function () {});
            } else if (typeof DeviceMotionEvent !== 'undefined') {
                window.addEventListener('devicemotion', onMotion);
            }
        } catch (e) { /* no sensors */ }
    }
    function onMotion(e) {
        const g = e.accelerationIncludingGravity;
        if (!g || g.x === null || g.y === null || g.z === null) return;
        const angle = (screen.orientation && typeof screen.orientation.angle === 'number') ? screen.orientation.angle : (window.orientation || 0);
        const landscape = Math.abs(angle) === 90 || angle === 270;
        const roll = Math.abs(Math.atan2(landscape ? g.y : g.x, Math.abs(landscape ? g.x : g.y)) * 180 / Math.PI);
        const vertical = Math.abs(g.z) < 2.2;
        state.tilt = roll;
        if (state.mode !== 'camera' || !state.stream) return;
        levelEl.classList.remove('d-none');
        const ok = roll < 1.5 && vertical;
        levelEl.classList.toggle('is-ok', ok);
        q('[data-ws-level-text]').textContent = ok ? (t.level_ok || '') : (!vertical ? (t.level_vertical || '') : (t.level_tilt || '').replace(':deg', roll.toFixed(1)));
    }

    // ------------------------------------------------------------------ real scale: visible width slider + calibration
    const widthInput = q('[data-ws-width]');
    const widthOut = q('[data-ws-width-out]');
    function showWidth() {
        widthOut.textContent = Math.round(state.visibleCm) + ' cm';
        if (state.visibleCm > parseFloat(widthInput.max)) widthInput.max = String(Math.ceil(state.visibleCm));
        if (state.visibleCm < parseFloat(widthInput.min)) widthInput.min = String(Math.floor(state.visibleCm));
        widthInput.value = String(state.visibleCm);
    }
    widthInput.addEventListener('input', function () {
        state.visibleCm = parseFloat(widthInput.value) || 300;
        state.calibrated = false;
        q('[data-ws-calibrated]').hidden = true;
        widthOut.textContent = Math.round(state.visibleCm) + ' cm';
        render();
    });
    q('[data-ws-measure-btn]').addEventListener('click', function () {
        if (state.measuring) { stopMeasure(); return; }
        state.measuring = true;
        q('[data-ws-calib]').classList.remove('d-none');
        measureEl.classList.remove('d-none');
        hintEl.textContent = t.hint_measure || '';
        renderMeasure();
    });
    function stopMeasure() {
        state.measuring = false;
        q('[data-ws-calib]').classList.add('d-none');
        measureEl.classList.add('d-none');
        hintEl.textContent = t.hint_drag || '';
    }
    function renderMeasure() {
        if (!state.measuring) return;
        const s = stageSize();
        measureEl.setAttribute('viewBox', '0 0 ' + s.w + ' ' + s.h);
        const line = q('[data-ws-mline]');
        line.setAttribute('x1', state.m.ax * s.w); line.setAttribute('y1', state.m.ay * s.h);
        line.setAttribute('x2', state.m.bx * s.w); line.setAttribute('y2', state.m.by * s.h);
        const ha = q('[data-ws-handle="a"]');
        const hb = q('[data-ws-handle="b"]');
        ha.setAttribute('cx', state.m.ax * s.w); ha.setAttribute('cy', state.m.ay * s.h);
        hb.setAttribute('cx', state.m.bx * s.w); hb.setAttribute('cy', state.m.by * s.h);
    }
    qa('[data-ws-handle]').forEach(function (h) {
        let on = false;
        h.addEventListener('pointerdown', function (e) { on = true; h.setPointerCapture(e.pointerId); e.preventDefault(); });
        h.addEventListener('pointermove', function (e) {
            if (!on) return;
            const r = stage.getBoundingClientRect();
            const x = Math.min(1, Math.max(0, (e.clientX - r.left) / r.width));
            const y = Math.min(1, Math.max(0, (e.clientY - r.top) / r.height));
            if (h.getAttribute('data-ws-handle') === 'a') { state.m.ax = x; state.m.ay = y; } else { state.m.bx = x; state.m.by = y; }
            renderMeasure();
        });
        const off = function () { on = false; };
        h.addEventListener('pointerup', off);
        h.addEventListener('pointercancel', off);
    });
    qa('[data-ws-ref]').forEach(function (b) {
        b.addEventListener('click', function () {
            qa('[data-ws-ref]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
            q('[data-ws-ref-cm]').value = b.getAttribute('data-ws-ref');
        });
    });
    q('[data-ws-calib-apply]').addEventListener('click', function () {
        const cm = parseFloat(String(q('[data-ws-ref-cm]').value).replace(',', '.'));
        const s = stageSize();
        const px = Math.hypot((state.m.bx - state.m.ax) * s.w, (state.m.by - state.m.ay) * s.h);
        if (!(cm > 0) || px < 10) return;
        state.visibleCm = Math.min(5000, Math.max(10, s.w * cm / px));
        state.calibrated = true;
        q('[data-ws-calibrated]').hidden = false;
        showWidth();
        stopMeasure();
        render();
    });

    // ------------------------------------------------------------------ creative controls
    qa('[data-ws-scene-btn]').forEach(function (b) {
        b.addEventListener('click', function () {
            state.scene = b.getAttribute('data-ws-scene-btn');
            qa('[data-ws-scene-btn]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
            state.moved = false;
            defaultCreativePosition();
            drawScene();
        });
    });
    qa('[data-ws-wall]').forEach(function (b) {
        b.addEventListener('click', function () {
            state.wall = b.getAttribute('data-ws-wall');
            qa('.ws-swatch').forEach(function (x) { x.classList.toggle('is-active', x === b); });
            drawScene();
        });
    });
    q('[data-ws-wall-custom]').addEventListener('input', function (e) {
        if (!/^#[0-9a-f]{6}$/i.test(e.target.value)) return;
        state.wall = e.target.value;
        qa('.ws-swatch').forEach(function (x) { x.classList.toggle('is-active', x.classList.contains('ws-swatch-custom')); });
        drawScene();
    });
    qa('[data-ws-floor]').forEach(function (b) {
        b.addEventListener('click', function () {
            state.floor = b.getAttribute('data-ws-floor');
            qa('[data-ws-floor]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
            drawScene();
        });
    });
    qa('[data-ws-light-btn]').forEach(function (b) {
        b.addEventListener('click', function () {
            state.light = b.getAttribute('data-ws-light-btn');
            qa('[data-ws-light-btn]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
            render();
        });
    });
    function drawScene() { sceneEl.innerHTML = sceneSvg(); render(); }

    q('[data-ws-center]').addEventListener('click', function () {
        if (state.mode === 'creative') { state.moved = false; defaultCreativePosition(); } else { state.fx = 0.5; state.fy = 0.42; }
        render();
    });
    qa('[data-ws-mode]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.getAttribute('data-ws-mode')); }); });

    // ------------------------------------------------------------------ export (download / share)
    function loadImage(src) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = reject;
            img.src = src;
        });
    }
    async function compose() {
        const L = layout();
        let W, H, bg = null;
        if (state.mode === 'creative') {
            W = 1800; H = Math.round(W * SCENE_H / SCENE_W);
            bg = await loadImage(URL.createObjectURL(new Blob([sceneSvg()], { type: 'image/svg+xml' })));
        } else if (state.mode === 'camera' && state.stream) {
            W = videoEl.videoWidth; H = videoEl.videoHeight; bg = videoEl;
        } else {
            bg = photoEl; W = photoEl.naturalWidth; H = photoEl.naturalHeight;
        }
        if (!W || !H) return null;
        const max = 2400;
        if (Math.max(W, H) > max) { const r = max / Math.max(W, H); W = Math.round(W * r); H = Math.round(H * r); }
        const c = document.createElement('canvas');
        c.width = W; c.height = H;
        const ctx = c.getContext('2d');
        ctx.drawImage(bg, 0, 0, W, H);
        if (state.mode === 'creative' && bg.src) URL.revokeObjectURL(bg.src);
        const k = W / L.s.w;
        const box = { x: L.left * k, y: L.top * k, w: L.w * k, h: L.h * k };
        const map = coverMap(box.w, box.h);
        panelRects(L).forEach(function (r) {
            const x = box.x + r.x * k;
            const pw = r.w * k;
            const fr = r.frame * k;
            ctx.save();
            ctx.shadowColor = 'rgba(0,0,0,.35)';
            ctx.shadowBlur = Math.max(6, box.w * 0.04);
            ctx.shadowOffsetY = Math.max(3, box.w * 0.015);
            ctx.fillStyle = r.frame ? (L.a.frame || '#171717') : '#ffffff';
            ctx.fillRect(x, box.y, pw, box.h);
            ctx.restore();
            if (!map) {
                const g = ctx.createLinearGradient(x, box.y, x + pw, box.y + box.h);
                g.addColorStop(0, '#E9DCC0'); g.addColorStop(1, '#C6A66B');
                ctx.fillStyle = g;
                ctx.fillRect(x + fr, box.y + fr, pw - 2 * fr, box.h - 2 * fr);
            }
            if (map && state.artImg) {
                // map is in canvas pixels (computed on box.w × box.h): crop the part of the image under this panel
                const s = map.sc;
                const rx = r.x * k;
                ctx.drawImage(state.artImg, (map.ox + rx + fr) / s, (map.oy + fr) / s, (pw - 2 * fr) / s, (box.h - 2 * fr) / s, x + fr, box.y + fr, pw - 2 * fr, box.h - 2 * fr);
            }
        });
        if (state.mode === 'creative' && state.light === 'evening') {
            const cx = box.x + box.w / 2;
            const cy = box.y + box.h / 2;
            const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, Math.max(W, H) * 0.6);
            g.addColorStop(0, 'rgba(255,214,150,.22)');
            g.addColorStop(0.35, 'rgba(255,214,150,.06)');
            g.addColorStop(1, 'rgba(14,12,28,.46)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H);
        }
        // caption
        const label = (dlg.getAttribute('data-brand') || '') + (dimEl.textContent ? ' · ' + dimEl.textContent : '');
        const fs = Math.max(14, Math.round(W * 0.016));
        ctx.font = '600 ' + fs + 'px Inter, Arial, sans-serif';
        const tw = ctx.measureText(label).width;
        ctx.fillStyle = 'rgba(23,23,23,.72)';
        ctx.fillRect(fs * 0.8, H - fs * 2.6, tw + fs * 1.4, fs * 1.8);
        ctx.fillStyle = '#ffffff';
        ctx.fillText(label, fs * 1.5, H - fs * 1.3);
        return c;
    }
    function fileName() { return 'berradi-' + (dlg.getAttribute('data-slug') || 'tableau') + '-sur-mon-mur.jpg'; }
    q('[data-ws-download]').addEventListener('click', async function () {
        const c = await compose();
        if (!c) return;
        c.toBlob(function (blob) {
            if (!blob) return;
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = fileName();
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
        }, 'image/jpeg', 0.9);
    });
    const shareBtn = q('[data-ws-share]');
    try {
        if (navigator.canShare && navigator.canShare({ files: [new File(['x'], 'x.jpg', { type: 'image/jpeg' })] })) shareBtn.classList.remove('d-none');
    } catch (e) { /* not supported */ }
    shareBtn.addEventListener('click', async function () {
        const c = await compose();
        if (!c) return;
        c.toBlob(function (blob) {
            if (!blob) return;
            const file = new File([blob], fileName(), { type: 'image/jpeg' });
            navigator.share({ files: [file], text: t.shared_text || '' }).catch(function () {});
        }, 'image/jpeg', 0.9);
    });

    // ------------------------------------------------------------------ open / close
    document.querySelectorAll('[data-open-wall]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const main = document.querySelector('[data-main-image]');
            state.artUrl = (main && main.getAttribute('src')) || dlg.getAttribute('data-art') || '';
            state.variant = currentVariant();
            if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
            document.documentElement.classList.add('ws-open');
            loadArt(state.artUrl, function () {
                if (!state.moved) defaultCreativePosition();
                showWidth();
                drawScene();
                setMode(state.mode);
            });
        });
    });
    q('[data-ws-close]').addEventListener('click', function () { if (dlg.close) dlg.close(); else dlg.removeAttribute('open'); });
    dlg.addEventListener('close', function () {
        stopCamera();
        document.documentElement.classList.remove('ws-open');
    });
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(function () { render(); }).observe(stage);
    window.addEventListener('resize', render);
    photoEl.addEventListener('load', function () { if (state.mode !== 'creative') setStageRatio(photoEl.naturalWidth, photoEl.naturalHeight); });

    buildSizes();
})();
