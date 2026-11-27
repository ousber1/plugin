/* BERRADI WALL ART — storefront interactions (CSP friendly, no inline JS) */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ------------------------------------------------ payment: send the signed form to the bank once
        const autopost = document.querySelector('form[data-autopost]');
        if (autopost) {
            // only automatically the first time (coming back with the browser's Back button shows the button instead)
            let first = true;
            try {
                first = !sessionStorage.getItem('bwa-posted-' + location.pathname);
                sessionStorage.setItem('bwa-posted-' + location.pathname, '1');
            } catch (e) { first = true; }
            if (first) setTimeout(function () { autopost.submit(); }, 600);
        }
        // ------------------------------------------------ auto-submit filters
        document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
            form.querySelectorAll('select, input[type="radio"]').forEach(function (el) {
                el.addEventListener('change', function () { form.requestSubmit ? form.requestSubmit() : form.submit(); });
            });
        });

        // ------------------------------------------------ product gallery
        const gallery = document.querySelector('[data-gallery]');
        if (gallery) {
            const main = gallery.querySelector('[data-main-image]');
            const roomImg = gallery.querySelector('[data-room-art] img');
            gallery.querySelectorAll('[data-thumb]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    gallery.querySelectorAll('[data-thumb]').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    if (main) main.src = btn.getAttribute('data-thumb');
                    if (roomImg) roomImg.src = btn.getAttribute('data-thumb');
                });
            });
            gallery.querySelectorAll('.view-toggle[data-view]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const view = btn.getAttribute('data-view');
                    gallery.querySelectorAll('.view-toggle[data-view]').forEach(function (b) { b.classList.toggle('active', b === btn); });
                    gallery.querySelectorAll('[data-stage]').forEach(function (s) {
                        s.classList.toggle('d-none', s.getAttribute('data-stage') !== view);
                    });
                });
            });
        }

        // ------------------------------------------------ quantity steppers
        document.querySelectorAll('[data-qty]').forEach(function (box) {
            const input = box.querySelector('input');
            box.querySelectorAll('[data-step]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const min = parseInt(input.min || '1', 10);
                    const max = parseInt(input.max || '99', 10);
                    const next = Math.min(max, Math.max(min, (parseInt(input.value, 10) || 1) + parseInt(btn.getAttribute('data-step'), 10)));
                    input.value = String(next);
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        });

        // ------------------------------------------------ variant selection
        const form = document.querySelector('[data-product-form]');
        if (form) {
            let variants = [];
            try { variants = JSON.parse(form.getAttribute('data-variants') || '[]'); } catch (e) { variants = []; }
            const waBase = form.getAttribute('data-whatsapp') || '';
            const priceEl = document.querySelector('[data-price]');
            const regularEl = document.querySelector('[data-regular]');
            const skuEl = document.querySelector('[data-sku]');
            const variantInput = form.querySelector('[data-variant-input]');
            const unavailable = form.querySelector('[data-unavailable]');
            const buyBtn = form.querySelector('[data-buy]');
            const waBtn = form.querySelector('[data-whatsapp-order]');
            const roomArt = document.querySelector('[data-room-art]');

            const value = function (name) {
                const checked = form.querySelector('input[name="' + name + '"]:checked');
                if (checked) return checked.value;
                const hidden = form.querySelector('input[type="hidden"][name="' + name + '"]');
                return hidden ? hidden.value : null;
            };
            const setLabel = function (key, text) {
                const el = form.querySelector('[data-label="' + key + '"]');
                if (el) el.textContent = text;
            };

            const update = function () {
                const size = value('opt_size');
                const material = value('opt_material');
                const frame = value('opt_frame');
                const match = variants.find(function (v) {
                    return (size === null || v.size === size) && (material === null || v.material === material) && (frame === null || v.frame === frame);
                });

                // grey out options that cannot be combined with the current size
                form.querySelectorAll('input[name="opt_material"], input[name="opt_frame"]').forEach(function (input) {
                    const key = input.name === 'opt_material' ? 'material' : 'frame';
                    const possible = variants.some(function (v) { return (size === null || v.size === size) && v[key] === input.value; });
                    input.closest('.chip')?.classList.toggle('is-disabled', !possible);
                });
                if (size !== null) setLabel('size', size);
                const mat = form.querySelector('input[name="opt_material"]:checked');
                if (mat) setLabel('material', mat.getAttribute('data-name'));
                const fr = form.querySelector('input[name="opt_frame"]:checked');
                if (fr) setLabel('frame', fr.getAttribute('data-name'));

                if (unavailable) unavailable.classList.toggle('d-none', !!match);
                if (buyBtn) buyBtn.disabled = !match || match.out;
                if (!match) return;

                variantInput.value = String(match.id);
                document.dispatchEvent(new CustomEvent('bwa:variant', { detail: match }));
                if (priceEl) priceEl.textContent = match.price;
                if (regularEl) {
                    regularEl.textContent = match.regular;
                    regularEl.classList.toggle('d-none', !match.onSale);
                }
                if (skuEl) skuEl.textContent = match.sku;

                // realistic scale on the wall: the wall shown is 300 cm wide
                if (roomArt) {
                    const widthPct = Math.min(80, Math.max(8, (match.width / 300) * 100));
                    roomArt.style.width = widthPct + '%';
                    roomArt.style.aspectRatio = match.width + ' / ' + match.height;
                    if (match.frameColor) roomArt.style.setProperty('--frame-color', match.frameColor);
                }

                if (waBtn && waBase) {
                    const qty = form.querySelector('input[name="quantity"]').value || '1';
                    const url = new URL(waBase);
                    const text = (url.searchParams.get('text') || '') + '\n— ' + match.name + ' (' + match.sku + ') × ' + qty;
                    url.searchParams.set('text', text);
                    waBtn.href = url.toString();
                }
            };

            form.addEventListener('change', update);
            form.addEventListener('submit', function (e) {
                if (form.getAttribute('action') === '#') e.preventDefault();
            });
            if (variants.length) update();
        }

        // ------------------------------------------------ newsletter (AJAX with graceful fallback)
        document.querySelectorAll('form[data-newsletter]').forEach(function (nf) {
            nf.addEventListener('submit', function (e) {
                if (typeof window.bwaFetch !== 'function') return;
                e.preventDefault();
                const feedback = nf.querySelector('[data-feedback]');
                const button = nf.querySelector('button[type="submit"]');
                button.disabled = true;
                window.bwaFetch(nf.action, { method: 'POST', body: new FormData(nf) })
                    .then(function (data) {
                        feedback.textContent = data.message || '';
                        feedback.classList.remove('text-danger');
                        nf.reset();
                    })
                    .catch(function (err) {
                        feedback.textContent = err.message;
                        feedback.classList.add('text-danger');
                    })
                    .finally(function () { button.disabled = false; });
            });
        });
    });
})();
