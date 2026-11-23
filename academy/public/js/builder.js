/* Visual page builder (admin). Blocks are edited here, previewed live in an
   iframe (server-rendered), and saved as JSON that the server sanitizes. */
(function () {
    'use strict';
    var B = window.BUILDER, doc = document;
    var csrf = doc.querySelector('meta[name=csrf-token]').content;
    var state = { blocks: B.blocks || [], selected: null, lang: 'ar', tab: 'list', editTab: 'content' };
    var history = [JSON.stringify(state.blocks)], future = [], dirty = false;
    var panel = doc.getElementById('pb-panel');
    var LANG_LABEL = { ar: 'AR', fr: 'FR', en: 'EN' };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function el(tag, attrs, html) { var n = doc.createElement(tag); Object.keys(attrs || {}).forEach(function (k) { if (attrs[k] !== false && attrs[k] != null) n.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }); if (html != null) n.innerHTML = html; return n; }
    function uid() { return Math.random().toString(36).slice(2, 12).padEnd(10, '0'); }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function icon(name) { return '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || ICONS.layers) + '</svg>'; }
    var ICONS = {
        layers: '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>', sparkles: '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"/>',
        list: '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>', 'file-text': '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        eye: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>', arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>', 'arrow-down': '<path d="M12 5v14M6 13l6 6 6-6"/>',
        'check-circle': '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 3 3 5-6"/>', chart: '<path d="M4 20V4M4 20h16"/><path d="m8 15 3-4 3 2 5-6"/>', info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.01"/>',
        whatsapp: '<path d="M4 20l1.3-4A8.5 8.5 0 1 1 8.4 19z"/>', play: '<circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5z" fill="currentColor"/>', book: '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/>',
        'file': '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>', users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1.2-3.5 3.6-5 6.5-5s5.3 1.5 6.5 5"/>',
        award: '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/>', mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>', code: '<path d="m8 7-5 5 5 5M16 7l5 5-5 5"/>'
    };

    /* ---------------- State helpers ---------------- */
    function def(type) { return B.defs[type]; }
    function blankValue(f, type) {
        if (f.type === 'items') {
            var n = { features: 3, steps: 3, faq: 3, buttons: 1, stats: 0, gallery: 0 }[type] || 0;
            var arr = [];
            for (var i = 0; i < n; i++) { var it = {}; f.fields.forEach(function (sf) { it[sf.k] = blankValue(sf, type); }); if (it.title) it.title.ar = 'عنوان ' + (i + 1); if (it.q) it.q.ar = 'سؤال ' + (i + 1) + '؟'; if (it.label) it.label.ar = 'زر'; arr.push(it); }
            return arr;
        }
        var v = f.default != null ? f.default : (f.type === 'checkbox' ? false : (f.type === 'select' ? Object.keys(f.options)[0] : ''));
        return f.tr ? { ar: '', fr: '', en: '' } : v;
    }
    function newBlock(type) {
        var d = def(type), b = { id: uid(), type: type, hidden: false, content: {}, style: {} };
        d.fields.forEach(function (f) { b.content[f.k] = blankValue(f, type); });
        if (b.content.title && b.content.title.ar === '') b.content.title.ar = d.label;
        if (type === 'text' || type === 'html') b.content[type === 'text' ? 'body' : 'code'].ar = '<p>اكتب النص هنا…</p>';
        B.styleFields.forEach(function (f) { b.style[f.k] = f.default != null ? f.default : (f.type === 'checkbox' ? false : ''); });
        Object.assign(b.style, d.style || {});
        return b;
    }
    function find(id) { return state.blocks.filter(function (b) { return b.id === id; })[0]; }
    function trVal(v) { return v && typeof v === 'object' ? (v[state.lang] || '') : (v || ''); }
    function summaryOf(b) {
        var c = b.content, t = c.title || c.body || c.code || c.text || c.q;
        var s = t && typeof t === 'object' ? (t.ar || t.fr || t.en || '') : '';
        return s.replace(/<[^>]+>/g, '').slice(0, 60);
    }

    var commit = debounceFn(function () {
        var snap = JSON.stringify(state.blocks);
        if (snap !== history[history.length - 1]) { history.push(snap); if (history.length > 60) history.shift(); future = []; }
        updateUndo();
    }, 350);
    function changed(opts) {
        dirty = true; commit();
        if (!opts || !opts.noList) renderListOnly();
        refreshPreview();
    }
    function debounceFn(fn, ms) { var t; return function () { var a = arguments; clearTimeout(t); t = setTimeout(function () { fn.apply(null, a); }, ms); }; }
    function updateUndo() { doc.getElementById('pb-undo').disabled = history.length < 2; doc.getElementById('pb-redo').disabled = !future.length; }
    function undo() { if (history.length < 2) return; future.push(history.pop()); state.blocks = JSON.parse(history[history.length - 1]); dirty = true; render(); refreshPreview(true); updateUndo(); }
    function redo() { if (!future.length) return; var s = future.pop(); history.push(s); state.blocks = JSON.parse(s); dirty = true; render(); refreshPreview(true); updateUndo(); }

    /* ---------------- Preview ---------------- */
    var frame = doc.getElementById('pb-preview'), scrollY = 0;
    var refreshPreview = debounceFn(doRefresh, 500);
    function doRefresh() {
        try { scrollY = frame.contentWindow.scrollY || 0; } catch (e) {}
        doc.getElementById('pb-preview-blocks').value = JSON.stringify(state.blocks);
        doc.getElementById('pb-preview-lang').value = state.lang;
        doc.getElementById('pb-preview-form').submit();
    }
    frame.addEventListener('load', function () {
        var w; try { w = frame.contentWindow; } catch (e) { return; }
        try { w.scrollTo(0, scrollY); } catch (e) {}
        var d = w.document;
        d.querySelectorAll('[data-block-id]').forEach(function (sec) {
            sec.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, textarea, select, summary, label')) e.preventDefault();
                select(sec.getAttribute('data-block-id'), false);
            });
        });
        d.querySelectorAll('form').forEach(function (f) { f.addEventListener('submit', function (e) { e.preventDefault(); }); });
        highlight(false);
    });
    function highlight(scroll) {
        var d; try { d = frame.contentWindow.document; } catch (e) { return; }
        d.querySelectorAll('.is-selected').forEach(function (n) { n.classList.remove('is-selected'); });
        if (!state.selected) return;
        var sec = d.querySelector('[data-block-id="' + state.selected + '"]');
        if (sec) { sec.classList.add('is-selected'); if (scroll) sec.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    /* ---------------- Panel rendering ---------------- */
    function render() { if (state.selected && find(state.selected)) renderEditor(); else { state.selected = null; renderLists(); } }
    function select(id, scroll) { state.selected = id; renderEditor(); highlight(scroll !== false); }

    function renderLists() {
        panel.innerHTML = '';
        var tabs = el('div', { class: 'pb-tabs', role: 'tablist' });
        [['list', 'كتل الصفحة'], ['add', '＋ إضافة كتلة']].forEach(function (t) {
            var b = el('button', { type: 'button', role: 'tab', 'aria-selected': state.tab === t[0] ? 'true' : 'false' }, esc(t[1]));
            b.onclick = function () { state.tab = t[0]; renderLists(); };
            tabs.appendChild(b);
        });
        panel.appendChild(tabs);
        var pane = el('div', { class: 'pb-pane', id: 'pb-pane' });
        panel.appendChild(pane);
        if (state.tab === 'add') renderPalette(pane); else renderList(pane);
    }
    function renderListOnly() { if (!state.selected && state.tab === 'list') { var pane = doc.getElementById('pb-pane'); if (pane) renderList(pane); } }

    function renderList(pane) {
        pane.innerHTML = '<p class="pb-hint">اسحب الكتل لترتيبها، أو انقر على أي قسم في المعاينة لتعديله.</p>';
        if (!state.blocks.length) {
            var empty = el('div', { class: 'pb-empty' }, 'الصفحة فارغة.<br><br>');
            var add = el('button', { type: 'button', class: 'btn btn--primary btn--sm' }, '＋ إضافة أول كتلة');
            add.onclick = function () { state.tab = 'add'; renderLists(); };
            empty.appendChild(add); pane.appendChild(empty); return;
        }
        var ul = el('ul', { class: 'pb-list' });
        state.blocks.forEach(function (b, i) {
            var d = def(b.type);
            var li = el('li', { class: 'pb-item' + (b.hidden ? ' is-hidden' : ''), draggable: 'true', 'data-i': i });
            li.innerHTML = '<span class="pb-handle" aria-hidden="true">⋮⋮</span><span class="pb-item__icon">' + icon(d.icon) + '</span>'
                + '<span class="pb-item__label"><strong>' + esc(d.label) + (b.hidden ? ' (مخفية)' : '') + '</strong><small>' + esc(summaryOf(b)) + '</small></span>';
            var acts = el('span', { class: 'pb-item__acts' });
            [['↑', 'تحريك للأعلى', function () { move(i, -1); }, i === 0], ['↓', 'تحريك للأسفل', function () { move(i, 1); }, i === state.blocks.length - 1],
             [b.hidden ? '◌' : '◉', b.hidden ? 'إظهار' : 'إخفاء', function () { b.hidden = !b.hidden; changed(); }], ['⧉', 'نسخ', function () { duplicate(i); }],
             ['🗑', 'حذف', function () { if (confirm('حذف هذه الكتلة؟')) { state.blocks.splice(i, 1); changed(); } }]].forEach(function (a) {
                var btn = el('button', { type: 'button', title: a[1], 'aria-label': a[1] + ' — ' + d.label, disabled: a[3] ? true : false }, a[0]);
                btn.onclick = function (e) { e.stopPropagation(); a[2](); };
                acts.appendChild(btn);
            });
            li.appendChild(acts);
            li.onclick = function () { select(b.id); };
            li.addEventListener('dragstart', function (e) { li.classList.add('is-drag'); e.dataTransfer.setData('text/plain', String(i)); e.dataTransfer.effectAllowed = 'move'; });
            li.addEventListener('dragend', function () { li.classList.remove('is-drag'); });
            li.addEventListener('dragover', function (e) {
                e.preventDefault(); var r = li.getBoundingClientRect(), before = e.clientY < r.top + r.height / 2;
                li.classList.toggle('drop-before', before); li.classList.toggle('drop-after', !before);
            });
            li.addEventListener('dragleave', function () { li.classList.remove('drop-before', 'drop-after'); });
            li.addEventListener('drop', function (e) {
                e.preventDefault(); var from = +e.dataTransfer.getData('text/plain'); var before = li.classList.contains('drop-before');
                li.classList.remove('drop-before', 'drop-after');
                var to = i + (before ? 0 : 1); if (from < to) to--;
                if (from === to || isNaN(from)) return;
                var item = state.blocks.splice(from, 1)[0]; state.blocks.splice(to, 0, item); changed();
            });
            ul.appendChild(li);
        });
        pane.appendChild(ul);
    }
    function move(i, dir) { var j = i + dir; if (j < 0 || j >= state.blocks.length) return; var t = state.blocks[i]; state.blocks[i] = state.blocks[j]; state.blocks[j] = t; changed(); }
    function duplicate(i) { var c = clone(state.blocks[i]); c.id = uid(); state.blocks.splice(i + 1, 0, c); changed(); }

    function renderPalette(pane) {
        pane.innerHTML = '<p class="pb-hint">انقر على كتلة لإضافتها بعد الكتلة المحددة (أو في آخر الصفحة).</p>';
        var grid = el('div', { class: 'pb-palette' }), groups = {};
        Object.keys(B.defs).forEach(function (type) { var g = B.defs[type].group; (groups[g] = groups[g] || []).push(type); });
        Object.keys(groups).forEach(function (g) {
            grid.appendChild(el('h3', {}, esc(g)));
            groups[g].forEach(function (type) {
                var d = def(type), btn = el('button', { type: 'button', class: 'pb-pal' }, icon(d.icon) + '<span>' + esc(d.label) + '</span>');
                btn.onclick = function () {
                    var b = newBlock(type), idx = state.selected ? state.blocks.indexOf(find(state.selected)) + 1 : state.blocks.length;
                    state.blocks.splice(idx, 0, b); changed({ noList: true }); select(b.id);
                };
                grid.appendChild(btn);
            });
        });
        pane.appendChild(grid);
    }

    function renderEditor() {
        var b = find(state.selected), d = def(b.type);
        panel.innerHTML = '';
        var head = el('div', { class: 'pb-edit-head' });
        var back = el('button', { type: 'button', class: 'btn btn--ghost btn--sm', 'aria-label': 'رجوع إلى قائمة الكتل' }, '→ الكتل');
        back.onclick = function () { state.selected = null; state.tab = 'list'; renderLists(); highlight(false); };
        head.appendChild(back); head.appendChild(el('strong', {}, esc(d.label)));
        var dup = el('button', { type: 'button', class: 'btn btn--ghost btn--sm', title: 'نسخ' }, '⧉');
        dup.onclick = function () { duplicate(state.blocks.indexOf(b)); select(state.blocks[state.blocks.indexOf(b) + 1].id); };
        head.appendChild(dup);
        panel.appendChild(head);
        var tabs = el('div', { class: 'pb-tabs', role: 'tablist' });
        [['content', 'المحتوى'], ['style', 'التصميم']].forEach(function (t) {
            var x = el('button', { type: 'button', role: 'tab', 'aria-selected': state.editTab === t[0] ? 'true' : 'false' }, esc(t[1]));
            x.onclick = function () { state.editTab = t[0]; renderEditor(); };
            tabs.appendChild(x);
        });
        panel.appendChild(tabs);
        var pane = el('div', { class: 'pb-pane' });
        if (state.editTab === 'content') {
            if (d.fields.some(function (f) { return f.tr || (f.fields && f.fields.some(function (s) { return s.tr; })); })) pane.appendChild(el('p', { class: 'pb-hint' }, 'تحرّر الآن النسخة ' + LANG_LABEL[state.lang] + '. غيّر اللغة من الأعلى. الحقول الفارغة تعرض النسخة العربية.'));
            renderFields(pane, d.fields, b.content, b.type);
        } else {
            renderFields(pane, B.styleFields, b.style, b.type);
        }
        panel.appendChild(pane);
    }

    function visible(f, obj) { if (!f.show) return true; return Object.keys(f.show).every(function (k) { return String(obj[k]) === String(f.show[k]); }); }

    function renderFields(container, fields, obj, type) {
        fields.forEach(function (f) {
            if (!visible(f, obj)) return;
            var wrap = el('div', { class: 'pb-field' });
            var id = 'pf-' + uid();
            var labelHtml = esc(f.label) + (f.tr ? '<span class="lang">' + LANG_LABEL[state.lang] + '</span>' : '');
            var setVal = function (v, rerender) {
                if (f.tr) { obj[f.k] = Object.assign({ ar: '', fr: '', en: '' }, obj[f.k] || {}); obj[f.k][state.lang] = v; } else obj[f.k] = v;
                changed({ noList: true });
                if (rerender) renderEditor();
            };
            var cur = f.tr ? trVal(obj[f.k]) : obj[f.k];
            var input;
            switch (f.type) {
                case 'textarea':
                    wrap.appendChild(el('label', { for: id }, labelHtml));
                    input = el('textarea', { id: id, class: 'textarea', dir: 'auto', rows: 3 }); input.value = cur || '';
                    input.oninput = function () { setVal(input.value); };
                    break;
                case 'rich':
                    wrap.appendChild(el('div', { class: 'pb-label' }, labelHtml));
                    wrap.appendChild(richEditor(cur || '', setVal));
                    container.appendChild(wrap); return;
                case 'select': case 'category': case 'icon':
                    wrap.appendChild(el('label', { for: id }, labelHtml));
                    input = el('select', { id: id, class: 'select' });
                    var opts = f.type === 'category' ? B.categories.map(function (c) { return [c.v, c.label]; })
                        : f.type === 'icon' ? B.icons.map(function (i) { return [i, i]; })
                        : Object.keys(f.options).map(function (k) { return [k, f.options[k]]; });
                    if (f.type === 'category') opts.unshift(['', '—']);
                    opts.forEach(function (o) { var op = el('option', { value: o[0] }, esc(o[1])); if (String(cur) === String(o[0])) op.selected = true; input.appendChild(op); });
                    input.onchange = function () { setVal(input.value, fields.some(function (x) { return x.show && x.show[f.k] !== undefined; })); };
                    break;
                case 'checkbox':
                    var lab = el('label', { class: 'check' });
                    input = el('input', { type: 'checkbox', id: id }); input.checked = !!cur;
                    input.onchange = function () { setVal(input.checked); };
                    lab.appendChild(input); lab.appendChild(doc.createTextNode(' ' + f.label));
                    wrap.appendChild(lab); container.appendChild(wrap); return;
                case 'color':
                    wrap.appendChild(el('label', { for: id }, labelHtml));
                    input = el('input', { id: id, type: 'color', class: 'input', style: 'padding:3px;height:38px' }); input.value = cur || '#ffffff';
                    input.oninput = function () { setVal(input.value); };
                    break;
                case 'number':
                    wrap.appendChild(el('label', { for: id }, labelHtml));
                    input = el('input', { id: id, type: 'number', class: 'input', min: f.min, max: f.max }); input.value = cur;
                    input.oninput = function () { setVal(input.value === '' ? '' : Number(input.value)); };
                    break;
                case 'image':
                    wrap.appendChild(el('div', { class: 'pb-label' }, labelHtml));
                    wrap.appendChild(imageField(cur, function (v) { setVal(v); }));
                    container.appendChild(wrap); return;
                case 'items':
                    wrap.appendChild(el('div', { class: 'pb-label' }, labelHtml));
                    wrap.appendChild(itemsField(f, obj, type));
                    container.appendChild(wrap); return;
                default: // text, url
                    wrap.appendChild(el('label', { for: id }, labelHtml));
                    input = el('input', { id: id, type: 'text', class: 'input' + (f.type === 'url' ? ' input-ltr' : ''), dir: f.type === 'url' ? 'ltr' : 'auto', placeholder: f.type === 'url' ? '/courses أو https://' : '' });
                    input.value = cur || '';
                    input.oninput = function () { setVal(input.value); };
            }
            wrap.appendChild(input);
            container.appendChild(wrap);
        });
    }

    function itemsField(f, obj, type) {
        var box = el('div', { class: 'pb-items' });
        var list = obj[f.k] = Array.isArray(obj[f.k]) ? obj[f.k] : [];
        list.forEach(function (item, i) {
            var det = el('details', { open: list.length <= 2 ? true : false });
            var first = f.fields[0], title = first.tr ? trVal(item[first.k]) : item[first.k];
            var sum = el('summary', {}, '<span>' + (i + 1) + '. ' + esc(String(title || '').slice(0, 40) || '…') + '</span>');
            [['↑', -1], ['↓', 1], ['×', 0]].forEach(function (a) {
                var btn = el('button', { type: 'button', 'aria-label': a[1] === 0 ? 'حذف العنصر' : 'تحريك' }, a[0]);
                btn.onclick = function (e) {
                    e.preventDefault();
                    if (a[1] === 0) list.splice(i, 1);
                    else { var j = i + a[1]; if (j < 0 || j >= list.length) return; var t = list[i]; list[i] = list[j]; list[j] = t; }
                    changed({ noList: true }); renderEditor();
                };
                sum.appendChild(btn);
            });
            det.appendChild(sum);
            var body = el('div', { class: 'pb-items__body' });
            renderFields(body, f.fields, item, type);
            det.appendChild(body);
            box.appendChild(det);
        });
        var add = el('button', { type: 'button', class: 'btn btn--outline btn--sm' }, '＋ ' + esc(f.add || 'إضافة'));
        add.onclick = function () { var it = {}; f.fields.forEach(function (sf) { it[sf.k] = blankValue(sf, type); }); list.push(it); changed({ noList: true }); renderEditor(); };
        box.appendChild(add);
        return box;
    }

    function imageField(cur, onChange) {
        var box = el('div', { class: 'pb-img' });
        var thumb = el('span', { class: 'pb-img__thumb' });
        var file = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp,image/gif', hidden: true });
        var up = el('button', { type: 'button', class: 'btn btn--outline btn--sm' }, 'رفع صورة');
        var rm = el('button', { type: 'button', class: 'btn btn--ghost btn--sm' }, 'إزالة');
        function paint(v) { thumb.style.backgroundImage = v ? 'url("' + B.mediaBase + v + '")' : ''; rm.hidden = !v; }
        up.onclick = function () { file.click(); };
        rm.onclick = function () { paint(''); onChange(''); };
        file.onchange = function () {
            if (!file.files[0]) return;
            var fd = new FormData(); fd.append('image', file.files[0]);
            up.disabled = true; up.textContent = 'جارٍ الرفع…';
            fetch(B.uploadUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.d.errors ? Object.values(res.d.errors)[0][0] : (res.d.message || 'فشل رفع الصورة.'));
                    paint(res.d.path); onChange(res.d.path);
                })
                .catch(function (e) { toast(e.message, true); })
                .finally(function () { up.disabled = false; up.textContent = 'رفع صورة'; file.value = ''; });
        };
        box.appendChild(thumb); box.appendChild(up); box.appendChild(rm); box.appendChild(file);
        paint(cur);
        return box;
    }

    function richEditor(html, onChange) {
        var wrap = el('div', { class: 'pb-rte' });
        var bar = el('div', { class: 'pb-rte__bar', role: 'toolbar', 'aria-label': 'تنسيق النص' });
        var area = el('div', { class: 'pb-rte__area prose', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', dir: 'auto' });
        area.innerHTML = html;
        [['bold', 'B'], ['italic', 'I'], ['h2', 'H2'], ['h3', 'H3'], ['insertUnorderedList', '•'], ['insertOrderedList', '1.'], ['blockquote', '❝'], ['createLink', '🔗'], ['removeFormat', '⌫']].forEach(function (c) {
            var b = el('button', { type: 'button', 'aria-label': c[0] }, c[1]);
            b.onmousedown = function (e) { e.preventDefault(); };
            b.onclick = function () {
                area.focus();
                if (c[0] === 'h2' || c[0] === 'h3' || c[0] === 'blockquote') doc.execCommand('formatBlock', false, c[0]);
                else if (c[0] === 'createLink') { var u = prompt('الرابط (https://… أو /صفحة)'); if (u && /^(https?:|mailto:|tel:|\/|#)/.test(u)) doc.execCommand('createLink', false, u); }
                else doc.execCommand(c[0], false, null);
                onChange(area.innerHTML);
            };
            bar.appendChild(b);
        });
        area.addEventListener('input', function () { onChange(area.innerHTML); });
        wrap.appendChild(bar); wrap.appendChild(area);
        return wrap;
    }

    /* ---------------- Top bar ---------------- */
    function toast(msg, err) {
        var t = doc.getElementById('pb-toast'); t.textContent = msg; t.classList.toggle('is-error', !!err); t.hidden = false;
        clearTimeout(t._h); t._h = setTimeout(function () { t.hidden = true; }, 3500);
    }
    doc.querySelectorAll('[data-lang]').forEach(function (b) {
        b.onclick = function () { state.lang = b.dataset.lang; doc.querySelectorAll('[data-lang]').forEach(function (x) { x.setAttribute('aria-pressed', x === b); }); render(); doRefresh(); };
    });
    doc.querySelectorAll('[data-device]').forEach(function (b) {
        b.onclick = function () { var w = doc.getElementById('pb-frame-wrap'); w.className = 'pb-frame' + (b.dataset.device === 'desktop' ? '' : ' is-' + b.dataset.device); doc.querySelectorAll('[data-device]').forEach(function (x) { x.setAttribute('aria-pressed', x === b); }); };
    });
    doc.getElementById('pb-undo').onclick = undo;
    doc.getElementById('pb-redo').onclick = redo;
    doc.addEventListener('keydown', function (e) {
        if (e.target.closest && e.target.closest('input, textarea, [contenteditable]')) return;
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); redo(); }
    });
    var saveBtn = doc.getElementById('pb-save');
    saveBtn.onclick = function () {
        saveBtn.disabled = true;
        fetch(B.saveUrl, { method: 'PUT', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ blocks: state.blocks, publish: true }) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) throw new Error(res.d.message || 'تعذّر الحفظ.');
                state.blocks = res.d.blocks; dirty = false; history.push(JSON.stringify(state.blocks));
                doc.getElementById('pb-state').textContent = 'منشورة بالمحرّر — آخر حفظ ' + new Date().toLocaleTimeString('ar-MA', { hour: '2-digit', minute: '2-digit' });
                toast(res.d.message || 'تم الحفظ.'); render(); refreshPreview();
            })
            .catch(function (e) { toast(e.message, true); })
            .finally(function () { saveBtn.disabled = false; });
    };
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    render(); updateUndo(); doRefresh();
    window.PageBuilder = { state: state, select: select };
})();
