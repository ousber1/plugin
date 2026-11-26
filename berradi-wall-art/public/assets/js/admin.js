/* BERRADI WALL ART — admin behaviours (no inline scripts: CSP-safe). */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        const tabsApi = window.bootstrap && window.bootstrap.Tab;

        // ---------------------------------------------------------- tabs synced with the URL hash
        document.querySelectorAll('[data-hash-tabs]').forEach(function (nav) {
            const buttons = Array.from(nav.querySelectorAll('[data-bs-toggle="tab"]'));
            const current = document.querySelector('[data-current-tab]');
            const show = function (btn) { if (btn && tabsApi) tabsApi.getOrCreateInstance(btn).show(); };
            const fromHash = function () {
                const hash = window.location.hash;
                if (!hash || hash.length < 2) return;
                let target = null;
                try { target = document.querySelector(hash); } catch (e) { return; }
                if (!target) return;
                const pane = target.classList.contains('tab-pane') ? target : target.closest('.tab-pane');
                if (pane) show(buttons.find(b => b.getAttribute('data-bs-target') === '#' + pane.id));
                if (target.tagName === 'DETAILS') { target.open = true; setTimeout(() => target.scrollIntoView({ block: 'center' }), 150); }
            };
            buttons.forEach(function (btn) {
                btn.addEventListener('shown.bs.tab', function () {
                    const id = btn.getAttribute('data-bs-target').slice(1);
                    if (current) current.value = id;
                    // the main "save" button belongs to the product form, not to the image/variant sub-forms
                    document.querySelectorAll('.admin-sticky-save[data-hide-on]').forEach(function (bar) {
                        bar.classList.toggle('d-none', bar.getAttribute('data-hide-on').split(' ').indexOf(id) !== -1);
                    });
                    history.replaceState(null, '', '#' + id);
                });
            });
            fromHash();
            window.addEventListener('hashchange', fromHash);
        });

        // ---------------------------------------------------------- invalid field inside a hidden tab: reveal it
        const firstInvalid = document.querySelector('.tab-pane .is-invalid');
        if (firstInvalid && tabsApi) {
            const pane = firstInvalid.closest('.tab-pane');
            const btn = pane && document.querySelector('[data-bs-target="#' + pane.id + '"]');
            if (btn && !pane.classList.contains('active')) tabsApi.getOrCreateInstance(btn).show();
        }

        // ---------------------------------------------------------- bulk selection
        document.querySelectorAll('[data-bulk-form]').forEach(function (form) {
            const bar = form.querySelector('[data-bulk-bar]');
            const count = form.querySelector('[data-bulk-count]');
            const all = form.querySelector('[data-check-all]');
            const items = Array.from(form.querySelectorAll('[data-bulk-item]'));
            const refresh = function () {
                const n = items.filter(i => i.checked).length;
                if (count) count.textContent = String(n);
                if (bar) bar.classList.toggle('show', n > 0);
                if (all) { all.checked = n > 0 && n === items.length; all.indeterminate = n > 0 && n < items.length; }
            };
            items.forEach(i => i.addEventListener('change', refresh));
            if (all) all.addEventListener('change', function () { items.forEach(i => { i.checked = all.checked; }); refresh(); });
            form.addEventListener('submit', function (e) {
                const action = form.querySelector('[name="bulk_action"]');
                if (action && action.value === 'delete' && !window.confirm(form.getAttribute('data-delete-confirm') || 'OK ?')) e.preventDefault();
            });
            refresh();
        });

        // ---------------------------------------------------------- bulk "category" needs the category select
        document.querySelectorAll('[name="bulk_action"]').forEach(function (sel) {
            const cat = sel.form && sel.form.querySelector('[name="bulk_category"]');
            if (!cat) return;
            const sync = function () { cat.classList.toggle('d-none', sel.value !== 'category'); };
            sel.addEventListener('change', sync);
            sync();
        });

        // ---------------------------------------------------------- repeatable rows (order / purchase lines)
        document.querySelectorAll('[data-repeat-add]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const box = document.querySelector(btn.getAttribute('data-repeat-add'));
                const rows = box ? box.querySelectorAll('[data-repeat-row]') : [];
                if (!rows.length) return;
                const clone = rows[rows.length - 1].cloneNode(true);
                const index = rows.length;
                clone.querySelectorAll('[data-name]').forEach(function (el) {
                    el.name = el.getAttribute('data-name').replace('__i__', String(index));
                    if (el.tagName === 'SELECT') { el.selectedIndex = 0; }
                    else if (el.name.endsWith('[quantity]') && el.type === 'number') { el.value = '1'; }
                    else if (!el.name.endsWith('[tax_rate]')) { el.value = ''; }
                });
                box.appendChild(clone);
                const first = clone.querySelector('select, input');
                if (first) first.focus();
            });
        });

        // ---------------------------------------------------------- confirmation on a single button
        document.querySelectorAll('[data-confirm-click]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (!window.confirm(btn.getAttribute('data-confirm-click'))) e.preventDefault();
            });
        });

        // ---------------------------------------------------------- order status: confirm cancellation / return
        document.querySelectorAll('[data-status-form]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const v = form.querySelector('[name="status"]').value;
                if ((v === 'cancelled' || v === 'returned') && !window.confirm(form.getAttribute('data-cancel-confirm'))) e.preventDefault();
            });
        });

        // ---------------------------------------------------------- one-time links: select on focus
        document.querySelectorAll('[data-select-on-focus]').forEach(function (el) {
            el.addEventListener('focus', function () { el.select(); });
        });

        // ---------------------------------------------------------- drag & drop ordering (hidden order[] inputs follow DOM order)
        document.querySelectorAll('[data-sortable]').forEach(function (list) {
            let dragged = null;
            const form = list.closest('form');
            const save = form && form.querySelector('[data-sortable-save]');
            list.querySelectorAll('.sortable-row').forEach(function (row) {
                row.addEventListener('dragstart', function (e) { dragged = row; row.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
                row.addEventListener('dragend', function () { row.classList.remove('dragging'); dragged = null; });
                row.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (!dragged || dragged === row) return;
                    const r = row.getBoundingClientRect();
                    list.insertBefore(dragged, (e.clientY - r.top) > r.height / 2 ? row.nextSibling : row);
                    if (save) save.classList.replace('btn-outline-bwa', 'btn-bwa');
                });
            });
        });
    });
})();
