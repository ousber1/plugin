/* BERRADI WALL ART — cart & checkout interactions (progressive enhancement: every form also works without JS) */
(function () {
    'use strict';
    const debounce = (fn, ms) => { let h; return (...a) => { clearTimeout(h); h = setTimeout(() => fn(...a), ms); }; };

    document.addEventListener('DOMContentLoaded', function () {
        // cart: submit the quantity form shortly after a change
        document.querySelectorAll('form[data-autosubmit-qty]').forEach(function (form) {
            const submit = debounce(() => form.submit(), 600);
            form.querySelector('input[name="quantity"]').addEventListener('change', submit);
        });

        // live shipping / totals for a city (cart estimator and checkout city field)
        function liveTotals(url, city) {
            if (!city || city.length < 2 || typeof window.bwaFetch !== 'function') return;
            window.bwaFetch(url, { method: 'POST', body: { city: city } }).then(function (d) {
                const box = document.querySelector('[data-totals]');
                if (!box) return;
                const set = (sel, v) => { const el = box.querySelector(sel); if (el) el.textContent = v; };
                set('[data-total-shipping]', d.shipping);
                set('[data-total-tax]', d.tax);
                set('[data-total-grand]', d.total);
                let est = box.querySelector('[data-total-estimate]');
                if (!est && d.estimate) {
                    est = document.createElement('p');
                    est.className = 'small text-muted-bwa mb-0';
                    est.setAttribute('data-total-estimate', '');
                    box.appendChild(est);
                }
                if (est) est.textContent = d.estimate;
                let hint = box.querySelector('[data-free-hint]');
                if (!hint && d.free_hint) {
                    hint = document.createElement('p');
                    hint.className = 'small text-gold mb-0';
                    hint.setAttribute('data-free-hint', '');
                    box.appendChild(hint);
                }
                if (hint) hint.textContent = d.free_hint;
            }).catch(function () { /* the page still shows server totals on submit */ });
        }

        const estimator = document.querySelector('form[data-shipping-estimator]');
        if (estimator) {
            const input = estimator.querySelector('input[name="city"]');
            input.addEventListener('change', () => liveTotals(estimator.getAttribute('data-url'), input.value));
            estimator.addEventListener('submit', function (e) { e.preventDefault(); liveTotals(estimator.getAttribute('data-url'), input.value); });
        }

        const checkout = document.querySelector('form[data-checkout]');
        if (checkout) {
            const url = checkout.getAttribute('data-shipping-url');
            const city = checkout.querySelector('[data-city]');
            if (city) city.addEventListener('change', () => liveTotals(url, city.value));

            // fill the form from a saved address
            checkout.querySelectorAll('[data-fill-address]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    let a = {};
                    try { a = JSON.parse(btn.getAttribute('data-fill-address')); } catch (e) { return; }
                    Object.entries(a).forEach(([k, v]) => { const f = checkout.querySelector('[name="' + k + '"]'); if (f) f.value = v || ''; });
                    checkout.querySelectorAll('[data-fill-address]').forEach(b => b.classList.toggle('active', b === btn));
                    if (city) liveTotals(url, city.value);
                });
            });

            // optional account creation fields
            checkout.querySelectorAll('[data-toggle-target]').forEach(function (cb) {
                const target = document.querySelector(cb.getAttribute('data-toggle-target'));
                cb.addEventListener('change', () => target && target.classList.toggle('d-none', !cb.checked));
            });
        }
    });
})();
