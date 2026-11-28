/* BERRADI WALL ART — shared front-end behaviour (no inline scripts: CSP friendly) */
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /** fetch() wrapper adding CSRF + JSON headers. */
    window.bwaFetch = function (url, options = {}) {
        const headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
        }, options.headers || {});
        if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        return fetch(url, Object.assign({ credentials: 'same-origin' }, options, { headers }))
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const err = new Error(data.message || ('HTTP ' + res.status));
                    err.status = res.status;
                    err.data = data;
                    throw err;
                }
                return data;
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        // show / hide password
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.getAttribute('data-toggle-password'));
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                const icon = btn.querySelector('i');
                if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        });

        // auto-dismiss toast alerts
        document.querySelectorAll('.toast-stack .alert').forEach(function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity .4s';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 450);
            }, 6000);
        });

        // confirmation for destructive actions: <form data-confirm="...">
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });

        // image input preview: <input type="file" data-preview="#imgId">
        document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
            input.addEventListener('change', function () {
                const target = document.querySelector(input.getAttribute('data-preview'));
                const file = input.files && input.files[0];
                if (!target || !file || !file.type.startsWith('image/')) return;
                const url = URL.createObjectURL(file);
                target.src = url;
                target.classList.remove('d-none');
                target.onload = function () { URL.revokeObjectURL(url); };
            });
        });

        // prevent double submit
        document.querySelectorAll('form[data-once]').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('button[type="submit"]').forEach(function (b) {
                    b.disabled = true;
                    if (b.dataset.loading) b.textContent = b.dataset.loading;
                });
            });
        });

        // check / uncheck all permissions of a module
        document.querySelectorAll('[data-check-group]').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                document.querySelectorAll('input[data-group="' + toggle.getAttribute('data-check-group') + '"]').forEach(function (cb) {
                    cb.checked = toggle.checked;
                });
            });
        });

        // admin sidebar (mobile)
        const sidebar = document.querySelector('.admin-sidebar');
        const backdrop = document.querySelector('.sidebar-backdrop');
        document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                sidebar?.classList.toggle('open');
                backdrop?.classList.toggle('show');
            });
        });
        backdrop?.addEventListener('click', function () {
            sidebar?.classList.remove('open');
            backdrop.classList.remove('show');
        });
    });
})();
