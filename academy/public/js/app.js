/* Lightweight progressive enhancement for the public site. No dependencies. */
(function () {
    'use strict';
    var doc = document;

    // Accessible dropdowns: click/Enter/Space toggles, Escape closes, arrows move.
    function closeAll(except) {
        doc.querySelectorAll('.dropdown.is-open').forEach(function (d) {
            if (d !== except) {
                d.classList.remove('is-open');
                var t = d.querySelector('[data-dropdown-toggle]');
                if (t) t.setAttribute('aria-expanded', 'false');
            }
        });
    }
    doc.querySelectorAll('[data-dropdown-toggle]').forEach(function (btn) {
        var dd = btn.closest('.dropdown');
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var open = !dd.classList.contains('is-open');
            closeAll(dd);
            dd.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { var first = dd.querySelector('.dropdown__item'); if (first && e.detail === 0) first.focus(); }
        });
        dd.addEventListener('keydown', function (e) {
            var items = Array.prototype.slice.call(dd.querySelectorAll('.dropdown__item'));
            var i = items.indexOf(doc.activeElement);
            if (e.key === 'Escape') { closeAll(); btn.focus(); }
            if (e.key === 'ArrowDown') { e.preventDefault(); (items[i + 1] || items[0]).focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); (items[i - 1] || items[items.length - 1]).focus(); }
        });
    });
    doc.addEventListener('click', function (e) { if (!e.target.closest('.dropdown')) closeAll(); });
    doc.addEventListener('focusin', function (e) { if (!e.target.closest('.dropdown')) closeAll(); });

    // Mobile navigation drawer.
    var mnav = doc.getElementById('mobile-nav');
    function setNav(open) {
        if (!mnav) return;
        mnav.classList.toggle('is-open', open);
        doc.body.classList.toggle('no-scroll', open);
        doc.querySelectorAll('[data-nav-open]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
        if (open) { var f = mnav.querySelector('a, button, summary'); if (f) f.focus(); }
    }
    doc.querySelectorAll('[data-nav-open]').forEach(function (b) { b.addEventListener('click', function () { setNav(true); }); });
    doc.querySelectorAll('[data-nav-close]').forEach(function (b) { b.addEventListener('click', function () { setNav(false); }); });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && mnav && mnav.classList.contains('is-open')) setNav(false); });

    // Share: native sheet when available, otherwise copy link.
    doc.querySelectorAll('[data-share]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = { title: btn.getAttribute('data-title') || doc.title, url: btn.getAttribute('data-share') || location.href };
            if (navigator.share) { navigator.share(data).catch(function () {}); return; }
            copyText(data.url, btn);
        });
    });
    doc.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () { copyText(btn.getAttribute('data-copy'), btn); });
    });
    function copyText(text, btn) {
        var done = function () {
            var label = btn.getAttribute('data-copied') || '✓';
            var old = btn.getAttribute('aria-label');
            btn.setAttribute('aria-label', label);
            var live = doc.getElementById('live-region');
            if (live) live.textContent = label;
            setTimeout(function () { if (old) btn.setAttribute('aria-label', old); }, 2000);
        };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); return; }
        var ta = doc.createElement('textarea'); ta.value = text; doc.body.appendChild(ta); ta.select();
        try { doc.execCommand('copy'); done(); } catch (e) {} ta.remove();
    }

    // Confirmation for destructive forms.
    doc.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) e.preventDefault();
    });

    // Auto-dismiss flash messages.
    doc.querySelectorAll('[data-flash]').forEach(function (el) {
        setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 450); }, 6000);
    });

    // Lesson video: resume position per lesson (stored only in this browser).
    var video = doc.querySelector('video[data-resume-key]');
    if (video) {
        var key = 'resume:' + video.getAttribute('data-resume-key');
        video.addEventListener('loadedmetadata', function () {
            try { var t = parseFloat(localStorage.getItem(key) || '0'); if (t > 5 && t < video.duration - 5) video.currentTime = t; } catch (e) {}
        });
        var last = 0;
        video.addEventListener('timeupdate', function () {
            if (Math.abs(video.currentTime - last) < 5) return; last = video.currentTime;
            try { localStorage.setItem(key, String(Math.floor(video.currentTime))); } catch (e) {}
        });
        video.addEventListener('ended', function () {
            try { localStorage.removeItem(key); } catch (e) {}
            var hint = doc.getElementById('complete-hint'); if (hint) hint.hidden = false;
        });
        video.addEventListener('error', function () {
            var err = doc.getElementById('video-error'); if (err) err.hidden = false;
        });
    }

    // Auto-submit selects inside filter forms (with a submit button fallback).
    doc.querySelectorAll('form[data-autosubmit] select').forEach(function (s) {
        s.addEventListener('change', function () { s.form.submit(); });
    });

    // Character counters for textareas with maxlength
    doc.querySelectorAll('textarea[data-maxcount]').forEach(function (ta) {
        var out = doc.querySelector('[data-count-for="' + ta.id + '"]');
        if (!out) return;
        var upd = function () { out.textContent = ta.value.length; };
        ta.addEventListener('input', upd); upd();
    });

    // Star rating radio groups get keyboard-friendly labels already; highlight on change.
    doc.querySelectorAll('[data-stars]').forEach(function (group) {
        var paint = function () {
            var val = (group.querySelector('input:checked') || {}).value || 0;
            group.querySelectorAll('label').forEach(function (l) { l.classList.toggle('on', parseInt(l.getAttribute('data-v'), 10) <= val); });
        };
        group.addEventListener('change', paint); paint();
    });
})();

/* Builder block entrance animations, cookie notice. */
(function () {
    var anim = document.querySelectorAll('.bk--anim');
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (anim.length) {
        if (!('IntersectionObserver' in window) || reduce) anim.forEach(function (n) { n.classList.add('is-in'); });
        else {
            var io = new IntersectionObserver(function (entries) { entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } }); }, { threshold: .12 });
            anim.forEach(function (n) { io.observe(n); });
        }
    }
    var bar = document.getElementById('cookie-bar');
    if (bar) {
        var ok = false; try { ok = localStorage.getItem('cookie-ok') === '1'; } catch (e) {}
        if (!ok) bar.hidden = false;
        bar.querySelector('[data-cookie-ok]').addEventListener('click', function () { try { localStorage.setItem('cookie-ok', '1'); } catch (e) {} bar.hidden = true; });
    }
})();

/* Certificate delivery choice: show the centre list only for pickup. */
(function () {
    document.querySelectorAll('[data-delivery]').forEach(function (r) {
        r.addEventListener('change', function () {
            var sel = r.form.querySelector('[data-delivery-center]');
            if (sel) sel.hidden = r.form.querySelector('[data-delivery]:checked').value !== 'pickup';
        });
    });
})();

/* Store: product gallery, lightbox and tabs. */
(function () {
    document.querySelectorAll('[data-gallery]').forEach(function (g) {
        var main = g.querySelector('[data-gallery-main]');
        g.querySelectorAll('[data-gallery-thumb]').forEach(function (b) {
            b.addEventListener('click', function () {
                main.src = b.getAttribute('data-gallery-thumb');
                g.querySelectorAll('[data-gallery-thumb]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
            });
        });
        var dlg = document.querySelector('[data-gallery-dialog]'), zoom = g.querySelector('[data-gallery-zoom]');
        if (dlg && zoom && dlg.showModal) {
            zoom.addEventListener('click', function () { dlg.querySelector('img').src = main.src; dlg.showModal(); });
            dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
        } else if (zoom) { zoom.addEventListener('click', function () { window.open(main.src, '_blank'); }); }
    });
    document.querySelectorAll('[data-tabs]').forEach(function (t) {
        var tabs = Array.prototype.slice.call(t.querySelectorAll('[role=tab]'));
        function select(tab) {
            tabs.forEach(function (x) {
                var on = x === tab; x.setAttribute('aria-selected', on ? 'true' : 'false'); x.tabIndex = on ? 0 : -1;
                var p = document.getElementById(x.getAttribute('aria-controls')); if (p) p.hidden = !on;
            });
        }
        tabs.forEach(function (x, i) {
            x.addEventListener('click', function () { select(x); });
            x.addEventListener('keydown', function (e) {
                var d = (e.key === 'ArrowLeft' ? 1 : e.key === 'ArrowRight' ? -1 : 0) * (document.dir === 'rtl' ? 1 : -1);
                if (d) { e.preventDefault(); var n = tabs[(i + d + tabs.length) % tabs.length]; select(n); n.focus(); }
            });
        });
        if (location.hash === '#reviews') { var r = t.querySelector('[aria-controls=tab-rev]'); if (r) select(r); }
    });
})();
