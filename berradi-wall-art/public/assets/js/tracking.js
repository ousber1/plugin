/* BERRADI WALL ART — analytics & advertising tags with consent (no inline script: CSP stays strict).
 * Config comes from <script type="application/json" id="bwa-tracking">. Analytics tags load only with "analytics"
 * consent, advertising pixels only with "marketing" consent. Events are described once (GA4 shape) and translated
 * for every provider with the same event id (deduplication with the server-side Conversions API).
 */
(function () {
    'use strict';

    const cfgEl = document.getElementById('bwa-tracking');
    let cfg = null;
    try { cfg = cfgEl ? JSON.parse(cfgEl.textContent || 'null') : null; } catch (e) { cfg = null; }
    const banner = document.querySelector('[data-consent]');

    // ------------------------------------------------------------------ consent cookie "v1.a1.m0"
    function readConsent() {
        const m = document.cookie.match(/(?:^|;\s*)bwa_consent=v1\.a([01])\.m([01])/);
        return m ? { analytics: m[1] === '1', marketing: m[2] === '1', given: true } : { analytics: false, marketing: false, given: false };
    }
    function writeConsent(c) {
        const v = 'v1.a' + (c.analytics ? 1 : 0) + '.m' + (c.marketing ? 1 : 0);
        document.cookie = 'bwa_consent=' + v + '; Max-Age=' + (180 * 86400) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }

    function loadScript(src) {
        const s = document.createElement('script');
        s.async = true;
        s.src = src;
        document.head.appendChild(s);
    }

    // ------------------------------------------------------------------ loaders (equivalent to the vendors' snippets)
    const loaded = {};
    const L = {
        google: function (ids, c) {
            window.dataLayer = window.dataLayer || [];
            window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
            const g = c.analytics ? 'granted' : 'denied';
            const m = c.marketing ? 'granted' : 'denied';
            window.gtag('consent', 'default', { analytics_storage: g, ad_storage: m, ad_user_data: m, ad_personalization: m });
            window.gtag('js', new Date());
            if (ids.ga4 && c.analytics) window.gtag('config', ids.ga4);
            if (ids.ads && c.marketing) window.gtag('config', ids.ads);
            const first = (c.analytics && ids.ga4) || (c.marketing && ids.ads);
            if (first) loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(first));
        },
        gtm: function (id, c) {
            window.dataLayer = window.dataLayer || [];
            window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
            const m = c.marketing ? 'granted' : 'denied';
            window.gtag('consent', 'default', { analytics_storage: c.analytics ? 'granted' : 'denied', ad_storage: m, ad_user_data: m, ad_personalization: m });
            window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
            loadScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(id));
        },
        meta: function (id) {
            if (window.fbq) return;
            const n = window.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
            if (!window._fbq) window._fbq = n;
            n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
            loadScript('https://connect.facebook.net/en_US/fbevents.js');
            window.fbq('init', id);
            window.fbq('track', 'PageView');
        },
        tiktok: function (id) {
            const w = window;
            w.TiktokAnalyticsObject = 'ttq';
            const ttq = w.ttq = w.ttq || [];
            ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie', 'holdConsent', 'revokeConsent', 'grantConsent'];
            ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; };
            for (let i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
            ttq.instance = function (t) { const e = ttq._i[t] || []; for (let n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]); return e; };
            ttq.load = function (e, n) {
                const r = 'https://analytics.tiktok.com/i18n/pixel/events.js';
                ttq._i = ttq._i || {}; ttq._i[e] = []; ttq._i[e]._u = r; ttq._t = ttq._t || {}; ttq._t[e] = +new Date(); ttq._o = ttq._o || {}; ttq._o[e] = n || {};
                loadScript(r + '?sdkid=' + encodeURIComponent(e) + '&lib=ttq');
            };
            ttq.load(id);
            ttq.page();
        },
        snapchat: function (id) {
            if (window.snaptr) return;
            const a = window.snaptr = function () { a.handleRequest ? a.handleRequest.apply(a, arguments) : a.queue.push(arguments); };
            a.queue = [];
            loadScript('https://sc-static.net/scevent.min.js');
            window.snaptr('init', id, {});
            window.snaptr('track', 'PAGE_VIEW');
        },
        pinterest: function (id) {
            if (!window.pintrk) {
                window.pintrk = function () { window.pintrk.queue.push(Array.prototype.slice.call(arguments)); };
                window.pintrk.queue = []; window.pintrk.version = '3.0';
                loadScript('https://s.pinimg.com/ct/core.js');
            }
            window.pintrk('load', id);
            window.pintrk('page');
        },
        clarity: function (id) {
            window.clarity = window.clarity || function () { (window.clarity.q = window.clarity.q || []).push(arguments); };
            loadScript('https://www.clarity.ms/tag/' + encodeURIComponent(id));
        }
    };

    function allowed(provider, c) {
        return cfg.categories[provider] === 'analytics' ? c.analytics : c.marketing;
    }

    function start(c) {
        if (!cfg) return;
        const ids = cfg.ids;
        if ((ids.ga4 && c.analytics) || (ids.ads && c.marketing)) {
            if (!loaded.google) { loaded.google = true; L.google(ids, c); }
        }
        if (ids.gtm && (c.analytics || c.marketing) && !loaded.gtm) { loaded.gtm = true; L.gtm(ids.gtm, c); }
        ['meta', 'tiktok', 'snapchat', 'pinterest', 'clarity'].forEach(function (p) {
            if (ids[p] && allowed(p, c) && !loaded[p]) { loaded[p] = true; L[p](ids[p]); }
        });
        (cfg.events || []).forEach(function (ev) { send(ev, c); });
        cfg.events = [];
    }

    // ------------------------------------------------------------------ events
    const META = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'Purchase', search: 'Search', generate_lead: 'Lead', sign_up: 'CompleteRegistration', contact: 'Contact' };
    const TIKTOK = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'CompletePayment', search: 'Search', generate_lead: 'SubmitForm', sign_up: 'CompleteRegistration', contact: 'Contact' };
    const SNAP = { view_item: 'VIEW_CONTENT', add_to_cart: 'ADD_CART', begin_checkout: 'START_CHECKOUT', purchase: 'PURCHASE', search: 'SEARCH', sign_up: 'SIGN_UP', generate_lead: 'SUBSCRIBE' };
    const PIN = { view_item: 'pagevisit', add_to_cart: 'addtocart', purchase: 'checkout', search: 'search', generate_lead: 'lead', sign_up: 'signup' };

    function send(ev, c) {
        const ids = cfg.ids;
        const items = ev.items || [];
        const cur = cfg.currency || 'MAD';
        const value = typeof ev.value === 'number' ? ev.value : items.reduce(function (s, i) { return s + i.price * i.quantity; }, 0);
        const qty = items.reduce(function (s, i) { return s + i.quantity; }, 0);
        const ga = { currency: cur, value: value, items: items.map(function (i) { return { item_id: i.id, item_name: i.name, item_category: i.category, item_variant: i.variant, price: i.price, quantity: i.quantity }; }) };
        if (ev.order_id) ga.transaction_id = ev.order_id;
        if (ev.search_term) ga.search_term = ev.search_term;
        if (ev.method) ga.method = ev.method;
        try {
            if (loaded.google && window.gtag) {
                if (ids.ga4 && c.analytics) window.gtag('event', ev.name, Object.assign({ send_to: ids.ga4 }, ga));
                if (ids.ads && c.marketing && ev.name === 'purchase' && cfg.adsPurchaseLabel) {
                    window.gtag('event', 'conversion', { send_to: ids.ads + '/' + cfg.adsPurchaseLabel, value: value, currency: cur, transaction_id: ev.order_id || '' });
                }
            }
            if (loaded.gtm) {
                window.dataLayer.push({ ecommerce: null });
                window.dataLayer.push({ event: ev.name, event_id: ev.id, ecommerce: ga });
            }
            if (loaded.meta && META[ev.name]) {
                const p = { currency: cur, value: value, content_type: 'product', content_ids: items.map(function (i) { return i.id; }), contents: items.map(function (i) { return { id: i.id, quantity: i.quantity }; }), num_items: qty };
                if (ev.search_term) p.search_string = ev.search_term;
                window.fbq('track', META[ev.name], p, { eventID: ev.id });
            }
            if (loaded.tiktok && TIKTOK[ev.name]) {
                window.ttq.track(TIKTOK[ev.name], { contents: items.map(function (i) { return { content_id: i.id, content_name: i.name, quantity: i.quantity, price: i.price }; }), content_type: 'product', value: value, currency: cur, query: ev.search_term || undefined }, { event_id: ev.id });
            }
            if (loaded.snapchat && SNAP[ev.name]) {
                window.snaptr('track', SNAP[ev.name], { price: value, currency: cur, item_ids: items.map(function (i) { return i.id; }), number_items: qty, transaction_id: ev.order_id || undefined, search_string: ev.search_term || undefined, client_dedup_id: ev.id });
            }
            if (loaded.pinterest && PIN[ev.name]) {
                window.pintrk('track', PIN[ev.name], { event_id: ev.id, value: value, currency: cur, order_quantity: qty, order_id: ev.order_id || undefined, search_query: ev.search_term || undefined,
                    line_items: items.map(function (i) { return { product_id: i.id, product_name: i.name, product_price: i.price, product_quantity: i.quantity }; }) });
            }
        } catch (e) { /* a blocked tag must never break the shop */ }
    }

    // WhatsApp / phone clicks count as "contact"
    document.addEventListener('click', function (e) {
        const a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || !cfg) return;
        const href = a.getAttribute('href') || '';
        const method = /wa\.me|whatsapp\.com/i.test(href) ? 'whatsapp' : (/^tel:/i.test(href) ? 'phone' : null);
        if (method) send({ name: 'contact', id: Math.random().toString(36).slice(2), method: method, items: [] }, consentNow());
    });

    function consentNow() {
        return cfg && !cfg.consentRequired ? { analytics: true, marketing: true, given: true } : readConsent();
    }

    // ------------------------------------------------------------------ banner
    function showBanner(open) {
        if (!banner) return;
        banner.hidden = !open;
        const c = readConsent();
        const a = banner.querySelector('[data-consent-analytics]');
        const m = banner.querySelector('[data-consent-marketing]');
        if (a) a.checked = c.analytics;
        if (m) m.checked = c.marketing;
    }
    function choose(c) {
        writeConsent(c);
        showBanner(false);
        start(c);
    }
    if (banner) {
        banner.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-consent-action]');
            if (!btn) return;
            const action = btn.getAttribute('data-consent-action');
            if (action === 'accept') choose({ analytics: true, marketing: true });
            else if (action === 'reject') choose({ analytics: false, marketing: false });
            else if (action === 'customize') banner.querySelector('[data-consent-details]').hidden = false;
            else if (action === 'save') choose({ analytics: !!banner.querySelector('[data-consent-analytics]').checked, marketing: !!banner.querySelector('[data-consent-marketing]').checked });
        });
    }
    document.querySelectorAll('[data-consent-open]').forEach(function (el) {
        el.addEventListener('click', function (e) { e.preventDefault(); showBanner(true); });
    });

    if (!cfg) return;
    if (!cfg.consentRequired) {
        start({ analytics: true, marketing: true });
    } else {
        const c = readConsent();
        if (c.given) start(c); else showBanner(true);
    }
})();
