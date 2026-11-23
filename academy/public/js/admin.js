/* Admin dashboard enhancements: translation tabs, rich text, repeaters,
   resumable chunked MP4 uploads, quiz editor. No dependencies. */
(function () {
    'use strict';
    var doc = document;
    doc.documentElement.classList.add('js');
    var csrf = (doc.querySelector('meta[name=csrf-token]') || {}).content;

    // Sidebar on mobile
    var side = doc.querySelector('.admin-side');
    doc.querySelectorAll('[data-side-toggle]').forEach(function (b) {
        b.addEventListener('click', function () { side.classList.toggle('is-open'); b.setAttribute('aria-expanded', side.classList.contains('is-open')); });
    });

    // Translation tabs
    doc.querySelectorAll('.tr-field').forEach(function (field) {
        var tabs = field.querySelectorAll('.tr-tabs button'), panels = field.querySelectorAll('.tr-panel');
        function mark() { panels.forEach(function (p, i) { var input = p.querySelector('input, textarea'); tabs[i].classList.toggle('has-value', !!(input && input.value.trim())); }); }
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t, j) { t.setAttribute('aria-selected', i === j ? 'true' : 'false'); panels[j].hidden = i !== j; });
            });
        });
        panels.forEach(function (p, i) { p.hidden = i !== 0; });
        field.addEventListener('input', mark); mark();
    });

    // Minimal rich text editor bound to a textarea (sanitized server-side)
    doc.querySelectorAll('textarea[data-rich]').forEach(function (ta) {
        var wrap = doc.createElement('div'); wrap.className = 'rte';
        var bar = doc.createElement('div'); bar.className = 'rte__bar'; bar.setAttribute('role', 'toolbar'); bar.setAttribute('aria-label', 'أدوات التنسيق');
        var area = doc.createElement('div'); area.className = 'rte__area prose'; area.contentEditable = 'true'; area.setAttribute('role', 'textbox'); area.setAttribute('aria-multiline', 'true');
        area.setAttribute('aria-label', ta.getAttribute('aria-label') || (ta.labels && ta.labels[0] ? ta.labels[0].textContent : 'محتوى'));
        area.dir = ta.dir || 'auto'; area.innerHTML = ta.value;
        [['bold', 'B', 'غامق'], ['italic', 'I', 'مائل'], ['h2', 'H2', 'عنوان'], ['h3', 'H3', 'عنوان فرعي'], ['insertUnorderedList', '•', 'قائمة'], ['insertOrderedList', '1.', 'قائمة مرقمة'], ['blockquote', '❝', 'اقتباس'], ['createLink', '🔗', 'رابط'], ['removeFormat', '⌫', 'إزالة التنسيق'], ['html', '</>', 'تحرير HTML']].forEach(function (c) {
            var b = doc.createElement('button'); b.type = 'button'; b.textContent = c[1]; b.title = c[2]; b.setAttribute('aria-label', c[2]);
            b.addEventListener('click', function () {
                if (c[0] === 'html') { var showing = ta.hidden === false; if (showing) { area.innerHTML = ta.value; } else { ta.value = area.innerHTML; } ta.hidden = showing; area.hidden = !showing; return; }
                area.focus();
                if (c[0] === 'h2' || c[0] === 'h3' || c[0] === 'blockquote') doc.execCommand('formatBlock', false, c[0]);
                else if (c[0] === 'createLink') { var url = prompt('الرابط (https://…)'); if (url && /^(https?:|mailto:|\/)/.test(url)) doc.execCommand('createLink', false, url); }
                else doc.execCommand(c[0], false, null);
                sync();
            });
            bar.appendChild(b);
        });
        function sync() { if (!area.hidden) ta.value = area.innerHTML; ta.dispatchEvent(new Event('input', { bubbles: true })); }
        area.addEventListener('input', sync);
        ta.parentNode.insertBefore(wrap, ta); wrap.appendChild(bar); wrap.appendChild(area); wrap.appendChild(ta);
        ta.hidden = true; ta.classList.add('code-area');
        ta.form && ta.form.addEventListener('submit', function () { if (!area.hidden) ta.value = area.innerHTML; });
    });

    // Generic repeater: [data-repeater] with <template> and [data-add]
    doc.querySelectorAll('[data-repeater]').forEach(function (rep) {
        var tpl = rep.querySelector('template'), list = rep.querySelector('[data-list]');
        var next = list.children.length;
        rep.querySelector('[data-add]').addEventListener('click', function () {
            var html = tpl.innerHTML.replace(/__INDEX__/g, String(next++));
            list.insertAdjacentHTML('beforeend', html);
            var f = list.lastElementChild.querySelector('input, textarea'); if (f) f.focus();
        });
        list.addEventListener('click', function (e) { var b = e.target.closest('[data-remove]'); if (b) b.closest('[data-item]').remove(); });
    });

    // Resumable chunked MP4 upload
    var up = doc.getElementById('video-uploader');
    if (up) {
        var input = up.querySelector('input[type=file]'), bar = up.querySelector('.progress__bar'), status = up.querySelector('[data-status]'), cancelBtn = up.querySelector('[data-cancel]');
        var maxBytes = parseInt(up.dataset.maxBytes, 10), chunkBytes = parseInt(up.dataset.chunkBytes, 10);
        var aborted = false, current = null;
        function say(msg, type) { status.textContent = msg; status.className = 'small ' + (type === 'error' ? 'error' : 'muted'); }
        function req(method, url, body, onProgress) {
            return new Promise(function (resolve, reject) {
                var x = new XMLHttpRequest(); current = x;
                x.open(method, url); x.setRequestHeader('X-CSRF-TOKEN', csrf); x.setRequestHeader('Accept', 'application/json'); x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                if (onProgress) x.upload.onprogress = onProgress;
                x.onload = function () { var data = {}; try { data = JSON.parse(x.responseText); } catch (e) {} resolve({ status: x.status, data: data }); };
                x.onerror = function () { reject(new Error('network')); };
                x.onabort = function () { reject(new Error('abort')); };
                x.send(body);
            });
        }
        function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
        function mb(n) { return (n / 1048576).toFixed(1); }
        input.addEventListener('change', function () { if (input.files[0]) start(input.files[0]); });
        ['dragover', 'dragenter'].forEach(function (ev) { up.addEventListener(ev, function (e) { e.preventDefault(); up.classList.add('is-drag'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { up.addEventListener(ev, function (e) { e.preventDefault(); up.classList.remove('is-drag'); }); });
        up.addEventListener('drop', function (e) { if (e.dataTransfer.files[0]) start(e.dataTransfer.files[0]); });
        cancelBtn.addEventListener('click', function () { aborted = true; if (current) current.abort(); });

        async function start(file) {
            aborted = false;
            if (!/\.mp4$/i.test(file.name) || (file.type && file.type !== 'video/mp4')) { say('صيغة غير مدعومة. ارفع ملف فيديو بصيغة MP4 (H.264/AAC).', 'error'); return; }
            if (file.size > maxBytes) { say('حجم الملف (' + mb(file.size) + ' م.ب) يتجاوز الحد المسموح (' + mb(maxBytes) + ' م.ب).', 'error'); return; }
            input.disabled = true; cancelBtn.hidden = false; bar.style.width = '0%';
            say('جارٍ تحضير الرفع…');
            var init;
            try { init = await req('POST', up.dataset.startUrl, (function () { var f = new FormData(); f.append('name', file.name); f.append('size', file.size); return f; })()); }
            catch (e) { return fail('تعذّر بدء الرفع. تحقّق من اتصالك بالإنترنت.'); }
            if (init.status !== 200) return fail(init.data.message || 'تعذّر بدء الرفع.');
            var chunkUrl = init.data.chunk_url, offset = init.data.received || 0, retries = 0;
            while (offset < file.size) {
                if (aborted) return cancel(init.data.cancel_url);
                var blob = file.slice(offset, Math.min(offset + chunkBytes, file.size));
                var fd = new FormData(); fd.append('offset', offset); fd.append('chunk', blob, 'chunk');
                var res;
                try {
                    res = await req('POST', chunkUrl, fd, function (e) { if (e.lengthComputable) { var pct = Math.min(99, ((offset + e.loaded) / file.size) * 100); bar.style.width = pct.toFixed(1) + '%'; say('جارٍ الرفع: ' + pct.toFixed(0) + '% (' + mb(offset + e.loaded) + ' / ' + mb(file.size) + ' م.ب)'); } });
                } catch (e) {
                    if (aborted) return cancel(init.data.cancel_url);
                    if (++retries > 5) return fail('انقطع الرفع بسبب مشكلة في الاتصال. أعد اختيار الملف لاستئناف الرفع من حيث توقف.');
                    say('انقطع الاتصال، إعادة المحاولة (' + retries + '/5)…'); await sleep(1500 * retries);
                    try { var st = await req('GET', init.data.status_url); if (st.status === 200) offset = st.data.received; } catch (e2) {}
                    continue;
                }
                if (res.status === 409 && typeof res.data.received === 'number') { offset = res.data.received; continue; }
                if (res.status === 429) { await sleep(3000); continue; }
                if (res.status !== 200) return fail(res.data.message || 'فشل رفع جزء من الملف.');
                offset = res.data.received; retries = 0;
            }
            bar.style.width = '100%'; say('جارٍ التحقق من الفيديو وحفظه…');
            var done;
            try { done = await req('POST', init.data.complete_url, new FormData()); } catch (e) { return fail('تعذّر إنهاء الرفع. حاول مجددًا.'); }
            if (done.status !== 200) return fail(done.data.message || 'فشل حفظ الفيديو.');
            say('تم رفع الفيديو وحفظه بنجاح.');
            setTimeout(function () { location.reload(); }, 700);
        }
        function fail(msg) { say(msg, 'error'); input.disabled = false; input.value = ''; cancelBtn.hidden = true; }
        async function cancel(url) { try { await req('DELETE', url); } catch (e) {} fail('أُلغي الرفع.'); }
    }

    // Copy buttons and confirm dialogs (shared with public JS)
    doc.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(btn.getAttribute('data-copy')).then(function () { var t = btn.textContent; btn.textContent = 'تم النسخ ✓'; setTimeout(function () { btn.textContent = t; }, 1500); }); });
    });
    doc.addEventListener('submit', function (e) { var m = e.target.getAttribute('data-confirm'); if (m && !confirm(m)) e.preventDefault(); });
})();

/* Line chart hover: crosshair + tooltip with the day's values. */
(function () {
    document.querySelectorAll('[data-linechart]').forEach(function (plot) {
        var data; try { data = JSON.parse(plot.getAttribute('data-linechart')); } catch (e) { return; }
        var svg = plot.querySelector('svg'), hit = plot.querySelector('.chart__hit'), cross = plot.querySelector('.chart__cross'), tip = plot.querySelector('.chart__tip');
        var W = +hit.dataset.w, padL = +hit.dataset.padL, padR = +hit.dataset.padR, n = data.days.length;
        function show(evt) {
            var r = svg.getBoundingClientRect();
            var vx = (evt.clientX - r.left) * W / r.width;
            var i = Math.max(0, Math.min(n - 1, Math.round((vx - padL) / ((W - padL - padR) / Math.max(1, n - 1)))));
            var px = padL + i * (W - padL - padR) / Math.max(1, n - 1);
            cross.setAttribute('x1', px); cross.setAttribute('x2', px); cross.setAttribute('visibility', 'visible');
            tip.innerHTML = '<b>' + data.days[i] + '</b>' + data.series.map(function (s) { return '<div><i style="background:' + s.color + '"></i>' + s.label + ': <b style="display:inline">' + s.values[i] + '</b></div>'; }).join('');
            tip.hidden = false;
            var left = px * r.width / W;
            tip.style.left = Math.min(r.width - tip.offsetWidth - 4, Math.max(4, left + 12)) + 'px';
        }
        function hide() { cross.setAttribute('visibility', 'hidden'); tip.hidden = true; }
        hit.addEventListener('pointermove', show);
        hit.addEventListener('pointerleave', hide);
    });
})();

/* Show/hide fields based on a select: data-toggle-target="key" + data-show-when="key=value". */
(function () {
    document.querySelectorAll('[data-toggle-target]').forEach(function (sel) {
        var key = sel.getAttribute('data-toggle-target');
        function apply() {
            document.querySelectorAll('[data-show-when^="' + key + '="]').forEach(function (el) {
                el.hidden = el.getAttribute('data-show-when').split('=')[1].split('|').indexOf(sel.value) === -1;
            });
        }
        sel.addEventListener('change', apply); apply();
    });
})();
