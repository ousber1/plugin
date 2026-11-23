/* Shared engine for the document tools (invoice designer, CV builder).
   Renders schema-driven accordion forms bound to a state object, with
   repeaters, image uploads, a signature pad, local storage and preview fitting.
   Everything stays in the browser. No dependencies. */
(function () {
    'use strict';
    var uiLang = (document.documentElement.lang || 'ar').slice(0, 2);

    function L(v) {
        if (v == null) return '';
        if (typeof v === 'string') return v;
        return v[uiLang] || v.ar || v.fr || v.en || '';
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function nl2br(s) { return esc(s).replace(/\n/g, '<br>'); }
    function debounce(fn, ms) { var t; return function () { var a = arguments; clearTimeout(t); t = setTimeout(function () { fn.apply(null, a); }, ms); }; }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function el(tag, attrs, html) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (attrs[k] === false || attrs[k] == null) return;
            n.setAttribute(k, attrs[k] === true ? '' : attrs[k]);
        });
        if (html != null) n.innerHTML = html;
        return n;
    }
    var uid = 0;
    function nextId(p) { uid += 1; return (p || 'f') + uid; }

    function store(key) {
        return {
            load: function () { try { return JSON.parse(localStorage.getItem(key)); } catch (e) { return null; } },
            save: function (o) {
                try { localStorage.setItem(key, JSON.stringify(o)); return true; }
                catch (e) { return false; } // quota (very large images)
            },
            clear: function () { try { localStorage.removeItem(key); } catch (e) {} }
        };
    }

    /* Read an image file, downscale it and return a data URL (keeps storage small). */
    function readImage(file, maxSize, keepPng) {
        return new Promise(function (resolve, reject) {
            if (!file || !/^image\/(png|jpe?g|webp|gif)$/.test(file.type)) { reject(new Error('type')); return; }
            if (file.size > 8 * 1024 * 1024) { reject(new Error('size')); return; }
            var fr = new FileReader();
            fr.onload = function () {
                var img = new Image();
                img.onload = function () {
                    var scale = Math.min(1, maxSize / Math.max(img.width, img.height));
                    var c = document.createElement('canvas');
                    c.width = Math.round(img.width * scale); c.height = Math.round(img.height * scale);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    var png = keepPng || file.type === 'image/png' || file.type === 'image/gif';
                    resolve(c.toDataURL(png ? 'image/png' : 'image/jpeg', 0.88));
                };
                img.onerror = function () { reject(new Error('decode')); };
                img.src = fr.result;
            };
            fr.onerror = function () { reject(new Error('read')); };
            fr.readAsDataURL(file);
        });
    }

    var T = {
        add: { ar: 'إضافة', fr: 'Ajouter', en: 'Add' },
        up: { ar: 'نقل للأعلى', fr: 'Monter', en: 'Move up' },
        down: { ar: 'نقل للأسفل', fr: 'Descendre', en: 'Move down' },
        remove: { ar: 'حذف', fr: 'Supprimer', en: 'Remove' },
        upload: { ar: 'رفع صورة', fr: 'Importer une image', en: 'Upload image' },
        clearImg: { ar: 'إزالة', fr: 'Retirer', en: 'Remove' },
        draw: { ar: 'ارسم توقيعك', fr: 'Dessinez votre signature', en: 'Draw your signature' },
        clearPad: { ar: 'مسح الرسم', fr: 'Effacer', en: 'Clear' },
        badImage: { ar: 'صورة غير صالحة (PNG أو JPG، حتى 8 م.ب).', fr: 'Image invalide (PNG ou JPG, 8 Mo max).', en: 'Invalid image (PNG or JPG, up to 8 MB).' }
    };

    /* ---------------------------------------------------------------
       Form builder
       schema: [{ id, title, open, fields:[...]} | { id, title, repeater:'key', itemTitle, itemFields:[...], newItem:{}, addLabel }]
       field: { k, label, type, half, hint, placeholder, dir, options:[{v,label}], min, max, step, show:(data)=>bool }
       ------------------------------------------------------------- */
    function buildForm(container, schema, getData, onChange) {
        container.innerHTML = '';
        schema.forEach(function (section) {
            var det = el('details', { class: 'tk-sec', open: section.open ? true : false });
            det.appendChild(el('summary', {}, '<span>' + esc(L(section.title)) + '</span>' + (section.sub ? '<small>' + esc(L(section.sub)) + '</small>' : '')));
            var body = el('div', { class: 'tk-sec__body' });
            det.appendChild(body);
            if (section.repeater) renderRepeater(body, section, getData, onChange);
            else renderFields(body, section.fields, getData(), onChange);
            container.appendChild(det);
        });
    }

    function renderFields(body, fields, obj, onChange) {
        var grid = el('div', { class: 'tk-grid' });
        body.appendChild(grid);
        fields.forEach(function (f) { grid.appendChild(renderField(f, obj, onChange)); });
    }

    function renderField(f, obj, onChange) {
        var id = nextId();
        if (typeof f.placeholder === 'function') f = Object.assign({}, f, { placeholder: f.placeholder() });
        var wrap = el('div', { class: 'tk-field' + (f.half ? ' is-half' : '') + (f.third ? ' is-third' : '') });
        var label = f.type === 'checkbox' ? null : el('label', { for: id }, esc(L(f.label)) + (f.hint ? ' <small>' + esc(L(f.hint)) + '</small>' : ''));
        if (label) wrap.appendChild(label);
        var input;
        var val = obj[f.k];
        switch (f.type) {
            case 'textarea':
                input = el('textarea', { id: id, class: 'textarea', rows: f.rows || 3, dir: f.dir || 'auto', placeholder: L(f.placeholder) || false, maxlength: f.max || false });
                input.value = val || '';
                break;
            case 'select':
                input = el('select', { id: id, class: 'select' });
                (f.options || []).forEach(function (o) {
                    var op = el('option', { value: o.v }, esc(L(o.label)));
                    if (String(val) === String(o.v)) op.selected = true;
                    input.appendChild(op);
                });
                break;
            case 'checkbox':
                var lab = el('label', { class: 'check', for: id });
                input = el('input', { id: id, type: 'checkbox' });
                input.checked = !!val;
                lab.appendChild(input);
                lab.appendChild(document.createTextNode(' ' + L(f.label)));
                wrap.appendChild(lab);
                break;
            case 'range':
                input = el('input', { id: id, type: 'range', min: f.min || 1, max: f.max || 5, step: f.step || 1, class: 'tk-range' });
                input.value = val == null ? (f.min || 1) : val;
                break;
            case 'color':
                input = el('input', { id: id, type: 'color', class: 'input tk-color' });
                input.value = val || '#3a2a9e';
                break;
            case 'image':
            case 'signature':
                wrap.appendChild(renderImageField(f, obj, onChange, id));
                return wrap;
            default:
                input = el('input', {
                    id: id, class: 'input' + (f.dir === 'ltr' ? ' input-ltr' : ''), type: f.type || 'text', dir: f.dir || 'auto',
                    placeholder: L(f.placeholder) || false, min: f.min != null ? f.min : false, max: f.max != null && f.type === 'number' ? f.max : false,
                    step: f.step || false, inputmode: f.type === 'number' ? 'decimal' : false
                });
                input.value = val == null ? '' : val;
        }
        var handler = function () {
            var v;
            if (f.type === 'checkbox') v = input.checked;
            else if (f.type === 'number' || f.type === 'range') v = input.value === '' ? '' : Number(input.value);
            else v = input.value;
            obj[f.k] = v;
            onChange(f.k, f.type === 'checkbox' || f.type === 'select');
        };
        input.addEventListener(f.type === 'checkbox' || f.type === 'select' ? 'change' : 'input', handler);
        if (f.type !== 'checkbox') wrap.appendChild(input);
        return wrap;
    }

    function renderImageField(f, obj, onChange, id) {
        var box = el('div', { class: 'tk-image' });
        var thumb = el('div', { class: 'tk-image__thumb' + (f.round ? ' is-round' : '') });
        var actions = el('div', { class: 'tk-image__actions' });
        var file = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp', id: id, class: 'sr-only' });
        var up = el('label', { class: 'btn btn--outline btn--sm', for: id }, esc(L(T.upload)));
        var rm = el('button', { type: 'button', class: 'btn btn--ghost btn--sm' }, esc(L(T.clearImg)));
        var msg = el('p', { class: 'hint', role: 'status' }, f.note ? esc(L(f.note)) : '');
        function paint() {
            thumb.innerHTML = obj[f.k] ? '<img alt="" src="' + obj[f.k] + '">' : '<span aria-hidden="true">＋</span>';
            rm.hidden = !obj[f.k];
        }
        file.addEventListener('change', function () {
            readImage(file.files[0], f.maxSize || 600, f.png).then(function (url) { obj[f.k] = url; paint(); onChange(f.k, true); msg.textContent = f.note ? L(f.note) : ''; })
                .catch(function () { msg.textContent = L(T.badImage); });
            file.value = '';
        });
        rm.addEventListener('click', function () { obj[f.k] = ''; paint(); onChange(f.k, true); });
        actions.appendChild(file); actions.appendChild(up); actions.appendChild(rm);
        box.appendChild(thumb); box.appendChild(actions);
        if (f.type === 'signature') box.appendChild(signaturePad(function (url) { obj[f.k] = url; paint(); onChange(f.k, true); }));
        box.appendChild(msg);
        paint();
        return box;
    }

    function signaturePad(onDone) {
        var wrap = el('div', { class: 'tk-pad' });
        var c = el('canvas', { width: 420, height: 140, 'aria-label': L(T.draw), role: 'img' });
        var bar = el('div', { class: 'row' });
        var clear = el('button', { type: 'button', class: 'btn btn--ghost btn--sm' }, esc(L(T.clearPad)));
        var ctx = c.getContext('2d'), drawing = false, dirty = false;
        ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.strokeStyle = '#1b1a2e';
        function pos(e) { var r = c.getBoundingClientRect(); return { x: (e.clientX - r.left) * c.width / r.width, y: (e.clientY - r.top) * c.height / r.height }; }
        c.addEventListener('pointerdown', function (e) { drawing = true; c.setPointerCapture(e.pointerId); var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
        c.addEventListener('pointermove', function (e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); dirty = true; });
        var end = function () { if (drawing && dirty) onDone(c.toDataURL('image/png')); drawing = false; };
        c.addEventListener('pointerup', end); c.addEventListener('pointerleave', end);
        clear.addEventListener('click', function () { ctx.clearRect(0, 0, c.width, c.height); dirty = false; onDone(''); });
        wrap.appendChild(el('p', { class: 'hint' }, esc(L(T.draw))));
        wrap.appendChild(c); bar.appendChild(clear); wrap.appendChild(bar);
        return wrap;
    }

    function renderRepeater(body, section, getData, onChange) {
        var list = el('div', { class: 'tk-rep' });
        body.appendChild(list);
        function items() { var d = getData(); if (!Array.isArray(d[section.repeater])) d[section.repeater] = []; return d[section.repeater]; }
        function draw() {
            list.innerHTML = '';
            items().forEach(function (item, i, arr) {
                var card = el('div', { class: 'tk-rep__item' });
                var tools = el('div', { class: 'tk-rep__tools' });
                [['up', '↑', i === 0], ['down', '↓', i === arr.length - 1], ['remove', '×', false]].forEach(function (b) {
                    var btn = el('button', { type: 'button', class: 'icon-btn', 'aria-label': L(T[b[0]]) + ' ' + (i + 1), disabled: b[2] }, b[1]);
                    btn.addEventListener('click', function () {
                        var a = items();
                        if (b[0] === 'remove') a.splice(i, 1);
                        else { var j = b[0] === 'up' ? i - 1 : i + 1; var t = a[i]; a[i] = a[j]; a[j] = t; }
                        draw(); onChange(section.repeater, true);
                    });
                    tools.appendChild(btn);
                });
                card.appendChild(tools);
                var fieldsBox = el('div', { class: 'tk-rep__fields' });
                renderFields(fieldsBox, section.itemFields, item, onChange);
                card.appendChild(fieldsBox);
                list.appendChild(card);
            });
        }
        var add = el('button', { type: 'button', class: 'btn btn--outline btn--sm tk-rep__add' }, '＋ ' + esc(L(section.addLabel || T.add)));
        add.addEventListener('click', function () { items().push(clone(section.newItem || {})); draw(); onChange(section.repeater, true); var f = list.lastElementChild && list.lastElementChild.querySelector('input,textarea'); if (f) f.focus(); });
        body.appendChild(add);
        draw();
    }

    /* Scale the A4 page (794px wide) inside its frame to fit on screen; print stays 1:1. */
    function fitPreview(frame) {
        function fit() {
            var page = frame.firstElementChild;
            if (!page) return;
            var s = Math.min(1, frame.clientWidth / 794);
            page.style.transform = 'scale(' + s + ')';
            frame.style.height = Math.ceil(page.offsetHeight * s) + 'px';
        }
        if (!frame.dataset.fit) {
            frame.dataset.fit = '1';
            window.addEventListener('resize', debounce(fit, 80));
        }
        return fit;
    }

    window.ToolKit = {
        lang: uiLang, L: L, esc: esc, nl2br: nl2br, debounce: debounce, clone: clone, el: el,
        store: store, readImage: readImage, buildForm: buildForm, fitPreview: fitPreview
    };
})();
