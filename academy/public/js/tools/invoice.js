/* Invoice / quote / proforma designer. Client-side only (localStorage). */
(function () {
    'use strict';
    var K = window.ToolKit, L = K.L, esc = K.esc, nl2br = K.nl2br;
    var root = document.getElementById('tool-app');
    if (!root) return;
    var STORE = K.store('tool:invoice:v2');

    /* ---------------- Interface texts (ar / fr / en) ---------------- */
    var UI = {
        step1: { ar: 'لغة الفاتورة', fr: 'Langue du document', en: 'Document language' },
        step2: { ar: 'نوع الوثيقة', fr: 'Type de document', en: 'Document type' },
        step3: { ar: 'اختر القالب', fr: 'Choisissez un modèle', en: 'Choose a template' },
        all: { ar: 'الكل', fr: 'Tous', en: 'All' }, colorful: { ar: 'ملوّنة', fr: 'Colorés', en: 'Colorful' }, simple: { ar: 'بسيطة', fr: 'Sobres', en: 'Simple' },
        start: { ar: 'ابدأ التصميم', fr: 'Commencer', en: 'Start designing' },
        resume: { ar: 'متابعة الوثيقة المحفوظة', fr: 'Reprendre le document', en: 'Continue saved document' },
        info: { ar: 'المعلومات', fr: 'Informations', en: 'Details' }, design: { ar: 'التصميم', fr: 'Design', en: 'Design' },
        sample: { ar: 'ملء بمثال', fr: 'Exemple', en: 'Fill example' }, clear: { ar: 'مسح', fr: 'Effacer', en: 'Clear' },
        back: { ar: 'القالب واللغة', fr: 'Modèle et langue', en: 'Template & language' },
        newDoc: { ar: 'وثيقة جديدة', fr: 'Nouveau document', en: 'New document' },
        pdf: { ar: 'تحميل PDF', fr: 'Télécharger PDF', en: 'Download PDF' },
        confirmClear: { ar: 'مسح كل المعلومات؟', fr: 'Effacer toutes les informations ?', en: 'Clear all details?' },
        confirmNew: { ar: 'بدء وثيقة جديدة؟ ستُحفظ معلومات شركتك وتُمسح البنود والعميل.', fr: 'Nouveau document ? Vos informations d’entreprise sont conservées.', en: 'Start a new document? Your company details are kept.' },
        private: { ar: '🔒 تُحفظ معلوماتك تلقائيًا في هذا المتصفح فقط ولا تُرسل إلى أي خادم.', fr: '🔒 Vos données sont enregistrées dans ce navigateur uniquement.', en: '🔒 Your data is saved in this browser only.' },
        pdfHint: { ar: 'في نافذة الطباعة اختر «حفظ بصيغة PDF».', fr: 'Dans la fenêtre d’impression, choisissez « Enregistrer en PDF ».', en: 'In the print dialog, choose “Save as PDF”.' },
        tooBig: { ar: 'تعذّر الحفظ: الصور كبيرة جدًا. استعمل صورًا أصغر.', fr: 'Enregistrement impossible : images trop lourdes.', en: 'Could not save: images too large.' }
    };
    var LANGS = [
        { v: 'fr', name: 'Français', sub: { ar: 'بالفرنسية', fr: 'Français', en: 'French' }, b: 'FR' },
        { v: 'en', name: 'English', sub: { ar: 'بالإنجليزية', fr: 'Anglais', en: 'English' }, b: 'EN' },
        { v: 'ar', name: 'العربية', sub: { ar: 'بالعربية', fr: 'Arabe', en: 'Arabic' }, b: 'AR' }
    ];
    var TYPES = [
        { v: 'invoice', name: { ar: 'فاتورة', fr: 'Facture', en: 'Invoice' }, sub: 'Facture · Invoice' },
        { v: 'quote', name: { ar: 'عرض أسعار', fr: 'Devis', en: 'Quote' }, sub: 'Devis · Quote' },
        { v: 'proforma', name: { ar: 'فاتورة أولية', fr: 'Facture proforma', en: 'Proforma' }, sub: 'Proforma' }
    ];
    var TEMPLATES = [
        { v: 'classic', name: { ar: 'كلاسيكي', fr: 'Classique', en: 'Classic' }, color: '#4b37c4', color2: '#f5b82e', kind: 'colorful' },
        { v: 'band', name: { ar: 'شريط علوي', fr: 'Bandeau', en: 'Top band' }, color: '#1d4e89', color2: '#f5b82e', kind: 'colorful' },
        { v: 'minimal', name: { ar: 'بسيط', fr: 'Minimal', en: 'Minimal' }, color: '#1f2430', color2: '#9aa0ad', kind: 'simple' },
        { v: 'stripe', name: { ar: 'خط جانبي', fr: 'Liseré', en: 'Side stripe' }, color: '#0f766e', color2: '#99f6e4', kind: 'simple' },
        { v: 'corner', name: { ar: 'زاوية', fr: 'Angle', en: 'Corner' }, color: '#e4572e', color2: '#ffd166', kind: 'colorful' },
        { v: 'split', name: { ar: 'مقسوم', fr: 'Partagé', en: 'Split' }, color: '#3a2a9e', color2: '#f5b82e', kind: 'colorful' },
        { v: 'boxed', name: { ar: 'إطارات', fr: 'Encadré', en: 'Boxed' }, color: '#374151', color2: '#9ca3af', kind: 'simple' },
        { v: 'centered', name: { ar: 'أنيق', fr: 'Élégant', en: 'Elegant' }, color: '#8a6d3b', color2: '#d9c7a3', kind: 'simple' },
        { v: 'wave', name: { ar: 'موجة', fr: 'Vague', en: 'Wave' }, color: '#0e7490', color2: '#22d3ee', kind: 'colorful' },
        { v: 'gradient', name: { ar: 'متدرّج', fr: 'Dégradé', en: 'Gradient' }, color: '#6d28d9', color2: '#06b6d4', kind: 'colorful' },
        { v: 'bold', name: { ar: 'جريء', fr: 'Contrasté', en: 'Bold' }, color: '#1f2430', color2: '#f5b82e', kind: 'colorful' }
    ];
    var CURRENCIES = {
        MAD: { fr: ['dirham', 'dirhams', 'centime', 'centimes'], en: ['dirham', 'dirhams', 'centime', 'centimes'], ar: ['درهم', 'درهم', 'سنتيم', 'سنتيم'], sym: 'DH' },
        EUR: { fr: ['euro', 'euros', 'centime', 'centimes'], en: ['euro', 'euros', 'cent', 'cents'], ar: ['يورو', 'يورو', 'سنت', 'سنت'], sym: '€' },
        USD: { fr: ['dollar', 'dollars', 'cent', 'cents'], en: ['dollar', 'dollars', 'cent', 'cents'], ar: ['دولار', 'دولار', 'سنت', 'سنت'], sym: '$' }
    };

    /* ---------------- Document texts (by document language) ---------------- */
    var D = {
        fr: { invoice: 'Facture', quote: 'Devis', proforma: 'Facture proforma', no: 'N°', date: 'Date', due: 'Échéance', valid: 'Valable jusqu’au', ref: 'Réf.', from: 'Émetteur', to: 'Facturé à', toQ: 'Client', desc: 'Désignation', qty: 'Qté', price: 'P.U. HT', vat: 'TVA', lineTotal: 'Total HT', subtotal: 'Total HT', discount: 'Remise', shipping: 'Livraison', vatOf: 'TVA', ttc: 'Total TTC', deposit: 'Acompte', net: 'Net à payer', words: { invoice: 'Arrêtée la présente facture à la somme de :', quote: 'Arrêté le présent devis à la somme de :', proforma: 'Arrêtée la présente facture proforma à la somme de :' }, payment: 'Paiement', method: 'Mode', terms: 'Conditions', bank: 'Banque', rib: 'RIB', swift: 'SWIFT', notes: 'Notes', sign: 'Signature et cachet', contact: 'Contact', tel: 'Tél.', methods: { transfer: 'Virement bancaire', cash: 'Espèces', check: 'Chèque', card: 'Carte bancaire', other: 'Autre' } },
        en: { invoice: 'Invoice', quote: 'Quote', proforma: 'Proforma invoice', no: 'No.', date: 'Date', due: 'Due date', valid: 'Valid until', ref: 'Ref.', from: 'From', to: 'Bill to', toQ: 'Client', desc: 'Description', qty: 'Qty', price: 'Unit price', vat: 'VAT', lineTotal: 'Amount', subtotal: 'Subtotal', discount: 'Discount', shipping: 'Shipping', vatOf: 'VAT', ttc: 'Total', deposit: 'Deposit', net: 'Balance due', words: { invoice: 'Amount in words:', quote: 'Amount in words:', proforma: 'Amount in words:' }, payment: 'Payment', method: 'Method', terms: 'Terms', bank: 'Bank', rib: 'Account (RIB)', swift: 'SWIFT', notes: 'Notes', sign: 'Signature & stamp', contact: 'Contact', tel: 'Tel.', methods: { transfer: 'Bank transfer', cash: 'Cash', check: 'Cheque', card: 'Card', other: 'Other' } },
        ar: { invoice: 'فاتورة', quote: 'عرض أسعار', proforma: 'فاتورة أولية', no: 'رقم', date: 'التاريخ', due: 'تاريخ الاستحقاق', valid: 'صالح حتى', ref: 'المرجع', from: 'المُصدِر', to: 'العميل', toQ: 'العميل', desc: 'البيان', qty: 'الكمية', price: 'ثمن الوحدة', vat: 'الضريبة', lineTotal: 'المجموع', subtotal: 'المجموع دون ضريبة', discount: 'الخصم', shipping: 'التوصيل', vatOf: 'الضريبة', ttc: 'المجموع مع الضريبة', deposit: 'التسبيق', net: 'الصافي للأداء', words: { invoice: 'حُصرت هذه الفاتورة في مبلغ:', quote: 'حُصر هذا العرض في مبلغ:', proforma: 'حُصرت هذه الفاتورة الأولية في مبلغ:' }, payment: 'الأداء', method: 'الطريقة', terms: 'الشروط', bank: 'البنك', rib: 'رقم الحساب (RIB)', swift: 'SWIFT', notes: 'ملاحظات', sign: 'التوقيع والختم', contact: 'المسؤول', tel: 'الهاتف', methods: { transfer: 'تحويل بنكي', cash: 'نقدًا', check: 'شيك', card: 'بطاقة بنكية', other: 'أخرى' } }
    };

    /* ---------------- Amount in words ---------------- */
    function frWords(n) {
        var u = ['zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        var t = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
        function b100(x, inv) {
            if (x < 20) return u[x];
            var d = Math.floor(x / 10), r = x % 10;
            if (d === 7 || d === 9) return t[d] + (d === 7 && r === 1 ? ' et ' : '-') + u[10 + r];
            if (r === 0) return d === 8 && !inv ? 'quatre-vingts' : t[d];
            if (r === 1 && d !== 8) return t[d] + ' et un';
            return t[d] + '-' + u[r];
        }
        function b1000(x, inv) {
            var h = Math.floor(x / 100), r = x % 100, s = '';
            if (h) s = h === 1 ? 'cent' : u[h] + ' cent' + (r === 0 && !inv ? 's' : '');
            if (r) s += (s ? ' ' : '') + b100(r, inv);
            return s;
        }
        if (n === 0) return 'zéro';
        var parts = [], m = Math.floor(n / 1e6), th = Math.floor((n % 1e6) / 1000), r = n % 1000;
        if (m) parts.push(b1000(m, true) + (m > 1 ? ' millions' : ' million'));
        if (th) parts.push(th === 1 ? 'mille' : b1000(th, true) + ' mille');
        if (r) parts.push(b1000(r, false));
        return parts.join(' ');
    }
    function enWords(n) {
        var u = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        var t = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        function b1000(x) {
            var h = Math.floor(x / 100), r = x % 100, s = h ? u[h] + ' hundred' : '';
            if (r) s += (s ? ' ' : '') + (r < 20 ? u[r] : t[Math.floor(r / 10)] + (r % 10 ? '-' + u[r % 10] : ''));
            return s;
        }
        if (n === 0) return 'zero';
        var parts = [], m = Math.floor(n / 1e6), th = Math.floor((n % 1e6) / 1000), r = n % 1000;
        if (m) parts.push(b1000(m) + ' million');
        if (th) parts.push(b1000(th) + ' thousand');
        if (r) parts.push(b1000(r));
        return parts.join(' ');
    }
    function arWords(n) {
        var o = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        var t = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        var h = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];
        function b1000(x) {
            var p = [], hh = Math.floor(x / 100), r = x % 100;
            if (hh) p.push(h[hh]);
            if (r) p.push(r < 20 ? o[r] : (r % 10 ? o[r % 10] + ' و' + t[Math.floor(r / 10)] : t[Math.floor(r / 10)]));
            return p.join(' و');
        }
        function scale(x, one, two, few, many) {
            if (x === 1) return one;
            if (x === 2) return two;
            if (x <= 10) return b1000(x) + ' ' + few;
            return b1000(x) + ' ' + many;
        }
        if (n === 0) return 'صفر';
        var parts = [], m = Math.floor(n / 1e6), th = Math.floor((n % 1e6) / 1000), r = n % 1000;
        if (m) parts.push(scale(m, 'مليون', 'مليونان', 'ملايين', 'مليون'));
        if (th) parts.push(scale(th, 'ألف', 'ألفان', 'آلاف', 'ألف'));
        if (r) parts.push(b1000(r));
        return parts.join(' و');
    }
    function amountWords(amount, lang, cur) {
        var c = (CURRENCIES[cur] || CURRENCIES.MAD)[lang];
        var whole = Math.floor(Math.round(amount * 100) / 100), cents = Math.round((amount - whole) * 100);
        var fn = lang === 'fr' ? frWords : lang === 'en' ? enWords : arWords;
        var s = fn(whole) + ' ' + (whole > 1 ? c[1] : c[0]);
        if (cents) s += (lang === 'ar' ? ' و' : lang === 'fr' ? ' et ' : ' and ') + fn(cents) + ' ' + (cents > 1 ? c[3] : c[2]);
        return lang === 'ar' ? s : s.charAt(0).toUpperCase() + s.slice(1);
    }
    function money(v, lang, cur) {
        var sym = (CURRENCIES[cur] || CURRENCIES.MAD).sym;
        var n = Number(v) || 0;
        if (lang === 'en') return (cur === 'MAD' ? 'MAD ' : sym) + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        var s = n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        // keep "6 500,00 DH" in left-to-right order inside Arabic documents
        return lang === 'ar' ? '\u2066' + s + '\u00A0' + sym + '\u2069' : s + '\u00A0' + sym;
    }
    function neg(m, lang) { return lang === 'ar' ? '\u2066−' + m + '\u2069' : '−' + m; }
    function fmtDate(iso, lang) {
        if (!iso) return '';
        var p = iso.split('-');
        return lang === 'en' ? p[2] + '/' + p[1] + '/' + p[0] : p[2] + '/' + p[1] + '/' + p[0];
    }

    /* ---------------- State ---------------- */
    function today(off) { var d = new Date(); d.setDate(d.getDate() + (off || 0)); return d.toISOString().slice(0, 10); }
    function blankData(type, keep) {
        var prefix = type === 'quote' ? 'D' : type === 'proforma' ? 'PF' : 'F';
        var d = {
            number: prefix + '-' + new Date().getFullYear() + '-001', date: today(0), due: today(30), reference: '',
            co_logo: '', co_name: '', co_activity: '', co_address: '', co_city: '', co_phone: '', co_email: '', co_web: '',
            co_ice: '', co_if: '', co_rc: '', co_patente: '', co_cnss: '', co_vat: '',
            cl_name: '', cl_contact: '', cl_address: '', cl_city: '', cl_phone: '', cl_email: '', cl_ice: '',
            items: [{ desc: '', details: '', qty: 1, price: '', vat: 20 }],
            discount_type: 'percent', discount: '', shipping: '', shipping_vat: 20, deposit: '',
            pay_method: 'transfer', pay_terms: '', bank: '', rib: '', swift: '',
            notes: '', footer: '', sign_image: '', stamp_image: ''
        };
        if (keep) Object.keys(keep).forEach(function (k) { if (/^(co_|bank|rib|swift|pay_|footer|sign_|stamp_)/.test(k)) d[k] = keep[k]; });
        return d;
    }
    var state = STORE.load();
    if (!state || state.v !== 2) state = null;
    var draft = { lang: 'fr', type: 'invoice', template: 'classic', filter: 'all' };
    if (state) { draft.lang = state.lang; draft.type = state.type; draft.template = state.template; }

    function design(tpl) {
        var t = TEMPLATES.filter(function (x) { return x.v === tpl; })[0] || TEMPLATES[0];
        return { color: t.color, color2: t.color2, font: 'default', size: 'm', currency: 'MAD', words: true, legal: true };
    }
    var save = K.debounce(function () { if (!STORE.save(state)) alert(L(UI.tooBig)); }, 400);

    /* ---------------- Calculations ---------------- */
    function totals(d) {
        var lines = (d.items || []).map(function (it) { var q = Number(it.qty) || 0, p = Number(it.price) || 0; return { ht: q * p, vat: Number(it.vat) || 0 }; });
        var sub = lines.reduce(function (s, l) { return s + l.ht; }, 0);
        var disc = Number(d.discount) || 0;
        var discAmt = d.discount_type === 'percent' ? sub * Math.min(disc, 100) / 100 : Math.min(disc, sub);
        var ratio = sub ? (sub - discAmt) / sub : 1;
        var rates = {};
        lines.forEach(function (l) { rates[l.vat] = (rates[l.vat] || 0) + l.ht * ratio; });
        var ship = Number(d.shipping) || 0;
        if (ship) rates[Number(d.shipping_vat) || 0] = (rates[Number(d.shipping_vat) || 0] || 0) + ship;
        var vats = Object.keys(rates).map(Number).sort(function (a, b) { return a - b; }).filter(function (r) { return r > 0; })
            .map(function (r) { return { rate: r, base: rates[r], amount: rates[r] * r / 100 }; });
        var ht = sub - discAmt + ship;
        var vat = vats.reduce(function (s, v) { return s + v.amount; }, 0);
        var ttc = ht + vat, deposit = Math.min(Number(d.deposit) || 0, ttc);
        return { sub: sub, discAmt: discAmt, ship: ship, ht: ht, vats: vats, vat: vat, ttc: ttc, deposit: deposit, net: ttc - deposit };
    }

    /* ---------------- Rendering the document ---------------- */
    function renderDoc(s) {
        var d = s.data, lg = s.lang, t = D[lg], ds = s.design, cur = ds.currency, tt = totals(d);
        var rtl = lg === 'ar';
        var title = t[s.type];
        var line = function (label, value) { return value ? '<p>' + (label ? esc(label) + ' : ' : '') + esc(value) + '</p>' : ''; };
        var coLines = [d.co_activity && '<small>' + esc(d.co_activity) + '</small>'].filter(Boolean).join('');
        var meta = '<table class="inv-meta"><tr><td>' + t.no + '</td><td dir="ltr">' + esc(d.number) + '</td></tr>'
            + '<tr><td>' + t.date + '</td><td dir="ltr">' + fmtDate(d.date, lg) + '</td></tr>'
            + (d.due ? '<tr><td>' + (s.type === 'quote' ? t.valid : t.due) + '</td><td dir="ltr">' + fmtDate(d.due, lg) + '</td></tr>' : '')
            + (d.reference ? '<tr><td>' + t.ref + '</td><td>' + esc(d.reference) + '</td></tr>' : '') + '</table>';
        var brand = '<div class="inv-brand">' + (d.co_logo ? '<img class="inv-logo" alt="" src="' + d.co_logo + '">' : '')
            + '<div><strong>' + esc(d.co_name) + '</strong>' + coLines + '</div></div>';
        var head = s.template === 'wave'
            ? '<div class="inv-wave"><div class="inv-brand">' + (d.co_logo ? '<img class="inv-logo" alt="" src="' + d.co_logo + '" style="background:#fff;border-radius:8px;padding:4px">' : '') + '<div><strong>' + esc(d.co_name) + '</strong>' + coLines + '</div></div><h1>' + esc(title) + '</h1></div>'
              + '<div class="inv-head"><div></div><div class="inv-title">' + meta + '</div></div>'
            : '<div class="inv-head">' + brand + '<div class="inv-title"><h1>' + esc(title) + '</h1>' + meta + '</div></div>';

        var from = '<div class="inv-party"><h3>' + t.from + '</h3><strong>' + esc(d.co_name) + '</strong>'
            + line('', d.co_address) + line('', d.co_city) + line(t.tel, d.co_phone) + line('', d.co_email) + line('', d.co_web) + '</div>';
        var to = '<div class="inv-party"><h3>' + (s.type === 'quote' ? t.toQ : t.to) + '</h3><strong>' + esc(d.cl_name) + '</strong>'
            + line(t.contact, d.cl_contact) + line('', d.cl_address) + line('', d.cl_city) + line(t.tel, d.cl_phone) + line('', d.cl_email) + line('ICE', d.cl_ice) + '</div>';

        var rows = (d.items || []).filter(function (it) { return it.desc || it.price; }).map(function (it) {
            var q = Number(it.qty) || 0, p = Number(it.price) || 0;
            return '<tr><td><b>' + esc(it.desc) + '</b>' + (it.details ? '<small>' + esc(it.details) + '</small>' : '') + '</td><td class="num">' + q + '</td><td class="num">' + money(p, lg, cur)
                + '</td><td class="num">' + (Number(it.vat) || 0) + '%</td><td class="num">' + money(q * p, lg, cur) + '</td></tr>';
        }).join('') || '<tr><td colspan="5" style="color:#9aa0ad;text-align:center;padding:18px">—</td></tr>';
        var table = '<table class="inv-table"><thead><tr><th>' + t.desc + '</th><th class="num">' + t.qty + '</th><th class="num">' + t.price + '</th><th class="num">' + t.vat + '</th><th class="num">' + t.lineTotal + '</th></tr></thead><tbody>' + rows + '</tbody></table>';

        var tot = '<table class="inv-totals">'
            + '<tr><td>' + t.subtotal + '</td><td>' + money(tt.sub, lg, cur) + '</td></tr>'
            + (tt.discAmt ? '<tr><td>' + t.discount + (d.discount_type === 'percent' ? ' (' + Number(d.discount) + '%)' : '') + '</td><td>' + neg(money(tt.discAmt, lg, cur), lg) + '</td></tr>' : '')
            + (tt.ship ? '<tr><td>' + t.shipping + '</td><td>' + money(tt.ship, lg, cur) + '</td></tr>' : '')
            + tt.vats.map(function (v) { return '<tr><td>' + t.vatOf + ' ' + v.rate + '%</td><td>' + money(v.amount, lg, cur) + '</td></tr>'; }).join('')
            + '<tr class="grand"><td>' + t.ttc + '</td><td>' + money(tt.ttc, lg, cur) + '</td></tr>'
            + (tt.deposit ? '<tr><td>' + t.deposit + '</td><td>' + neg(money(tt.deposit, lg, cur), lg) + '</td></tr><tr class="net"><td>' + t.net + '</td><td>' + money(tt.net, lg, cur) + '</td></tr>' : '')
            + '</table>';
        var words = ds.words ? '<div class="inv-words">' + t.words[s.type] + '<b>' + esc(amountWords(tt.deposit ? tt.net : tt.ttc, lg, cur)) + '</b></div>' : '<div></div>';

        var pay = [d.pay_method && line(t.method, t.methods[d.pay_method] || ''), line(t.terms, d.pay_terms), line(t.bank, d.bank), line(t.rib, d.rib), line(t.swift, d.swift)].join('');
        var info = (pay.replace(/<[^>]+>/g, '').trim() || d.notes)
            ? '<div class="inv-info"><div>' + (pay ? '<h3>' + t.payment + '</h3>' + pay : '') + '</div><div>' + (d.notes ? '<h3>' + t.notes + '</h3><p>' + esc(d.notes) + '</p>' : '') + '</div></div>' : '';
        var sign = '<div class="inv-sign"><h3>' + t.sign + '</h3><div class="inv-signbox">' + (d.sign_image ? '<img alt="" src="' + d.sign_image + '">' : '') + (d.stamp_image ? '<img alt="" src="' + d.stamp_image + '">' : '') + '</div></div>';
        var legal = ds.legal ? [['ICE', d.co_ice], ['IF', d.co_if], ['RC', d.co_rc], ['Patente', d.co_patente], ['CNSS', d.co_cnss], [lg === 'ar' ? 'رقم الضريبة' : 'TVA', d.co_vat]].filter(function (x) { return x[1]; })
            .map(function (x) { return x[0] + ' : ' + esc(x[1]); }).join(' · ') : '';
        var foot = (legal || d.footer) ? '<div class="inv-foot">' + (d.footer ? '<p>' + nl2br(d.footer) + '</p>' : '') + (legal ? '<p dir="ltr">' + legal + '</p>' : '') + '</div>' : '';

        var cls = 'a4 inv t-' + s.template + (ds.font !== 'default' ? ' f-' + ds.font : '') + (ds.size !== 'm' ? ' s-' + ds.size : '');
        return '<div class="' + cls + '" lang="' + lg + '" dir="' + (rtl ? 'rtl' : 'ltr') + '" style="--ac:' + esc(ds.color) + ';--ac2:' + esc(ds.color2) + '">'
            + head + '<div class="inv-rule"></div><div class="inv-parties">' + from + to + '</div>' + table
            + '<div class="inv-bottom">' + words + tot + '</div>' + info + sign + '<div class="inv-spacer"></div>' + foot + '</div>';
    }

    /* ---------------- Sample data ---------------- */
    function sample(lang) {
        var fr = lang !== 'ar', en = lang === 'en';
        return {
            co_name: 'Atlas Web Studio', co_activity: en ? 'Web design & development' : fr ? 'Création de sites web' : 'تصميم وتطوير المواقع',
            co_address: en ? '12 Hassan II Avenue' : fr ? '12, avenue Hassan II' : '12 شارع الحسن الثاني', co_city: en ? 'Casablanca' : fr ? 'Casablanca' : 'الدار البيضاء',
            co_phone: '+212 5 22 00 00 00', co_email: 'contact@atlasweb.ma', co_web: 'www.atlasweb.ma',
            co_ice: '001234567000089', co_if: '12345678', co_rc: '456789', co_patente: '34567890', co_cnss: '9876543',
            cl_name: en ? 'Riad Al Andalous' : fr ? 'Riad Al Andalous SARL' : 'رياض الأندلس ش.م.م', cl_contact: 'M. Karim Alaoui',
            cl_address: en ? '5 Bab Doukkala Street' : fr ? '5, rue Bab Doukkala' : '5 زنقة باب دكالة', cl_city: en ? 'Marrakech' : fr ? 'Marrakech' : 'مراكش', cl_phone: '+212 6 61 00 00 00', cl_email: 'contact@riad-andalous.ma', cl_ice: '002345678000012',
            items: [
                { desc: en ? 'Website design (5 pages)' : fr ? 'Création de site vitrine (5 pages)' : 'تصميم موقع تعريفي (5 صفحات)', details: en ? 'Responsive design, contact form' : fr ? 'Design responsive, formulaire de contact' : 'تصميم متجاوب ونموذج تواصل', qty: 1, price: 6500, vat: 20 },
                { desc: en ? 'Hosting & domain (1 year)' : fr ? 'Hébergement et nom de domaine (1 an)' : 'استضافة ونطاق (سنة)', details: '', qty: 1, price: 900, vat: 20 },
                { desc: en ? 'Training session' : fr ? 'Formation à la prise en main' : 'حصة تدريبية', details: en ? '2 hours' : fr ? '2 heures' : 'ساعتان', qty: 2, price: 400, vat: 20 }
            ],
            discount_type: 'percent', discount: 5, deposit: 2000, reference: 'BC-2026-14',
            pay_method: 'transfer', pay_terms: en ? 'Payment within 30 days' : fr ? 'Paiement à 30 jours' : 'الأداء داخل 30 يومًا', bank: 'Banque Exemple', rib: '011 780 0000123456789012 34',
            notes: en ? 'Thank you for your trust.' : fr ? 'Merci pour votre confiance.' : 'شكرًا لثقتكم.'
        };
    }

    /* ---------------- Form schemas ---------------- */
    var VAT = [0, 7, 10, 14, 20].map(function (r) { return { v: r, label: r + '%' }; });
    function infoSchema() {
        return [
            { id: 'doc', open: true, title: { ar: 'الوثيقة', fr: 'Document', en: 'Document' }, fields: [
                { k: 'number', label: { ar: 'الرقم', fr: 'Numéro', en: 'Number' }, half: true, dir: 'ltr' },
                { k: 'reference', label: { ar: 'المرجع / سند الطلب', fr: 'Référence / Bon de commande', en: 'Reference / PO' }, half: true },
                { k: 'date', type: 'date', label: { ar: 'التاريخ', fr: 'Date', en: 'Date' }, half: true },
                { k: 'due', type: 'date', label: { ar: 'الاستحقاق / الصلاحية', fr: 'Échéance / validité', en: 'Due / valid until' }, half: true }
            ] },
            { id: 'co', title: { ar: 'شركتك', fr: 'Votre entreprise', en: 'Your company' }, fields: [
                { k: 'co_logo', type: 'image', png: true, maxSize: 500, label: { ar: 'الشعار', fr: 'Logo', en: 'Logo' }, note: { ar: 'PNG بخلفية شفافة يعطي أفضل نتيجة.', fr: 'Un PNG transparent donne le meilleur rendu.', en: 'A transparent PNG looks best.' } },
                { k: 'co_name', label: { ar: 'اسم الشركة', fr: 'Raison sociale / Nom', en: 'Company name' } },
                { k: 'co_activity', label: { ar: 'النشاط / الشعار النصي', fr: 'Activité / slogan', en: 'Activity / tagline' } },
                { k: 'co_address', label: { ar: 'العنوان', fr: 'Adresse', en: 'Address' }, half: true },
                { k: 'co_city', label: { ar: 'المدينة', fr: 'Ville', en: 'City' }, half: true },
                { k: 'co_phone', type: 'tel', dir: 'ltr', label: { ar: 'الهاتف', fr: 'Téléphone', en: 'Phone' }, half: true },
                { k: 'co_email', type: 'email', dir: 'ltr', label: { ar: 'البريد الإلكتروني', fr: 'E-mail', en: 'Email' }, half: true },
                { k: 'co_web', dir: 'ltr', label: { ar: 'الموقع', fr: 'Site web', en: 'Website' }, half: true },
                { k: 'co_ice', dir: 'ltr', label: 'ICE', half: true },
                { k: 'co_if', dir: 'ltr', label: { ar: 'التعريف الضريبي (IF)', fr: 'Identifiant fiscal (IF)', en: 'Tax ID (IF)' }, half: true },
                { k: 'co_rc', dir: 'ltr', label: { ar: 'السجل التجاري (RC)', fr: 'Registre de commerce (RC)', en: 'Trade register (RC)' }, half: true },
                { k: 'co_patente', dir: 'ltr', label: { ar: 'الضريبة المهنية', fr: 'Patente', en: 'Patente' }, half: true },
                { k: 'co_cnss', dir: 'ltr', label: 'CNSS', half: true },
                { k: 'co_vat', dir: 'ltr', label: { ar: 'رقم الضريبة (أجنبي)', fr: 'N° TVA (étranger)', en: 'VAT number (foreign)' }, half: true }
            ] },
            { id: 'cl', title: { ar: 'العميل', fr: 'Client', en: 'Client' }, fields: [
                { k: 'cl_name', label: { ar: 'اسم العميل / الشركة', fr: 'Nom du client / Société', en: 'Client / company' } },
                { k: 'cl_contact', label: { ar: 'الشخص المسؤول', fr: 'Contact', en: 'Contact person' }, half: true },
                { k: 'cl_ice', dir: 'ltr', label: { ar: 'ICE العميل', fr: 'ICE du client', en: 'Client ICE' }, half: true },
                { k: 'cl_address', label: { ar: 'العنوان', fr: 'Adresse', en: 'Address' }, half: true },
                { k: 'cl_city', label: { ar: 'المدينة', fr: 'Ville', en: 'City' }, half: true },
                { k: 'cl_phone', type: 'tel', dir: 'ltr', label: { ar: 'الهاتف', fr: 'Téléphone', en: 'Phone' }, half: true },
                { k: 'cl_email', type: 'email', dir: 'ltr', label: { ar: 'البريد الإلكتروني', fr: 'E-mail', en: 'Email' }, half: true }
            ] },
            { id: 'items', open: true, title: { ar: 'المنتجات / الخدمات', fr: 'Articles / Prestations', en: 'Items / Services' }, repeater: 'items',
              addLabel: { ar: 'إضافة سطر', fr: 'Ajouter une ligne', en: 'Add a line' }, newItem: { desc: '', details: '', qty: 1, price: '', vat: 20 },
              itemFields: [
                { k: 'desc', label: { ar: 'البيان', fr: 'Désignation', en: 'Description' } },
                { k: 'details', type: 'textarea', rows: 2, label: { ar: 'تفاصيل (اختياري)', fr: 'Détails (facultatif)', en: 'Details (optional)' } },
                { k: 'qty', type: 'number', min: 0, step: 'any', third: true, label: { ar: 'الكمية', fr: 'Quantité', en: 'Quantity' } },
                { k: 'price', type: 'number', min: 0, step: 'any', third: true, label: { ar: 'ثمن الوحدة دون ضريبة', fr: 'Prix unitaire HT', en: 'Unit price (excl. tax)' } },
                { k: 'vat', type: 'select', third: true, options: VAT, label: { ar: 'الضريبة', fr: 'TVA %', en: 'VAT %' } }
              ] },
            { id: 'extra', title: { ar: 'الخصم والتوصيل والتسبيق', fr: 'Remise, livraison et acompte', en: 'Discount, shipping & deposit' }, fields: [
                { k: 'discount_type', type: 'select', half: true, label: { ar: 'نوع الخصم', fr: 'Type de remise', en: 'Discount type' }, options: [{ v: 'percent', label: '%' }, { v: 'amount', label: { ar: 'مبلغ', fr: 'Montant', en: 'Amount' } }] },
                { k: 'discount', type: 'number', min: 0, step: 'any', half: true, label: { ar: 'الخصم', fr: 'Remise', en: 'Discount' } },
                { k: 'shipping', type: 'number', min: 0, step: 'any', half: true, label: { ar: 'مصاريف التوصيل (دون ضريبة)', fr: 'Frais de livraison HT', en: 'Shipping (excl. tax)' } },
                { k: 'shipping_vat', type: 'select', half: true, options: VAT, label: { ar: 'ضريبة التوصيل', fr: 'TVA livraison', en: 'Shipping VAT' } },
                { k: 'deposit', type: 'number', min: 0, step: 'any', label: { ar: 'التسبيق المدفوع', fr: 'Acompte versé', en: 'Deposit paid' } }
            ] },
            { id: 'pay', title: { ar: 'الأداء', fr: 'Paiement', en: 'Payment' }, fields: [
                { k: 'pay_method', type: 'select', half: true, label: { ar: 'طريقة الأداء', fr: 'Mode de paiement', en: 'Payment method' }, options: [
                    { v: '', label: '—' }, { v: 'transfer', label: { ar: 'تحويل بنكي', fr: 'Virement', en: 'Bank transfer' } }, { v: 'cash', label: { ar: 'نقدًا', fr: 'Espèces', en: 'Cash' } },
                    { v: 'check', label: { ar: 'شيك', fr: 'Chèque', en: 'Cheque' } }, { v: 'card', label: { ar: 'بطاقة', fr: 'Carte', en: 'Card' } }, { v: 'other', label: { ar: 'أخرى', fr: 'Autre', en: 'Other' } }] },
                { k: 'pay_terms', half: true, label: { ar: 'شروط الأداء', fr: 'Conditions de paiement', en: 'Payment terms' }, placeholder: { ar: 'مثال: داخل 30 يومًا', fr: 'Ex. : à 30 jours', en: 'e.g. Net 30' } },
                { k: 'bank', half: true, label: { ar: 'البنك', fr: 'Banque', en: 'Bank' } },
                { k: 'swift', half: true, dir: 'ltr', label: 'SWIFT / BIC' },
                { k: 'rib', dir: 'ltr', label: { ar: 'رقم الحساب البنكي (RIB 24 رقمًا)', fr: 'RIB (24 chiffres)', en: 'Account number (RIB)' } }
            ] },
            { id: 'notes', title: { ar: 'ملاحظات وأسفل الصفحة', fr: 'Notes et pied de page', en: 'Notes & footer' }, fields: [
                { k: 'notes', type: 'textarea', label: { ar: 'ملاحظات', fr: 'Notes', en: 'Notes' } },
                { k: 'footer', type: 'textarea', rows: 2, label: { ar: 'نص أسفل الصفحة', fr: 'Pied de page', en: 'Footer text' } }
            ] },
            { id: 'sign', title: { ar: 'التوقيع والختم', fr: 'Signature et cachet', en: 'Signature & stamp' }, fields: [
                { k: 'sign_image', type: 'signature', png: true, maxSize: 500, label: { ar: 'التوقيع', fr: 'Signature', en: 'Signature' } },
                { k: 'stamp_image', type: 'image', png: true, maxSize: 400, label: { ar: 'الختم (صورة)', fr: 'Cachet (image)', en: 'Stamp (image)' } }
            ] }
        ];
    }
    function designSchema() {
        return [{ id: 'design', open: true, title: { ar: 'المظهر', fr: 'Apparence', en: 'Appearance' }, fields: [
            { k: 'template', type: 'select', label: { ar: 'القالب', fr: 'Modèle', en: 'Template' }, options: TEMPLATES.map(function (t) { return { v: t.v, label: t.name }; }) },
            { k: 'color', type: 'color', half: true, label: { ar: 'اللون الأساسي', fr: 'Couleur principale', en: 'Main color' } },
            { k: 'color2', type: 'color', half: true, label: { ar: 'لون التمييز', fr: 'Couleur d’accent', en: 'Accent color' } },
            { k: 'font', type: 'select', half: true, label: { ar: 'الخط', fr: 'Police', en: 'Font' }, options: [{ v: 'default', label: 'Inter / Tajawal' }, { v: 'tajawal', label: 'Tajawal' }, { v: 'serif', label: 'Serif' }] },
            { k: 'size', type: 'select', half: true, label: { ar: 'حجم النص', fr: 'Taille du texte', en: 'Text size' }, options: [{ v: 's', label: { ar: 'صغير', fr: 'Petit', en: 'Small' } }, { v: 'm', label: { ar: 'متوسط', fr: 'Moyen', en: 'Medium' } }, { v: 'l', label: { ar: 'كبير', fr: 'Grand', en: 'Large' } }] },
            { k: 'currency', type: 'select', half: true, label: { ar: 'العملة', fr: 'Devise', en: 'Currency' }, options: [{ v: 'MAD', label: 'MAD (DH)' }, { v: 'EUR', label: 'EUR (€)' }, { v: 'USD', label: 'USD ($)' }] },
            { k: 'words', type: 'checkbox', label: { ar: 'كتابة المبلغ بالحروف', fr: 'Montant en lettres', en: 'Amount in words' } },
            { k: 'legal', type: 'checkbox', label: { ar: 'إظهار المعلومات القانونية (ICE، IF، RC…) أسفل الصفحة', fr: 'Mentions légales en pied de page (ICE, IF, RC…)', en: 'Legal identifiers in footer (ICE, IF, RC…)' } }
        ] }];
    }

    /* ---------------- Views ---------------- */
    function setupView() {
        var langOpts = LANGS.map(function (l) {
            return '<button type="button" class="tk-option" data-lang="' + l.v + '" aria-pressed="' + (draft.lang === l.v) + '"><span class="tk-badge">' + l.b + '</span><span><strong>' + l.name + '</strong><small>' + esc(L(l.sub)) + '</small></span></button>';
        }).join('');
        var typeOpts = TYPES.map(function (t) {
            return '<button type="button" class="tk-option" data-type="' + t.v + '" aria-pressed="' + (draft.type === t.v) + '"><span><strong>' + esc(L(t.name)) + '</strong><small>' + esc(t.sub) + '</small></span></button>';
        }).join('');
        root.innerHTML = '<div class="tk-wizard">'
            + '<section class="tk-step"><h2><span class="tk-num">1</span>' + esc(L(UI.step1)) + '</h2><div class="tk-options">' + langOpts + '</div></section>'
            + '<section class="tk-step"><h2><span class="tk-num">2</span>' + esc(L(UI.step2)) + '</h2><div class="tk-options">' + typeOpts + '</div></section>'
            + '<section class="tk-step"><h2><span class="tk-num">3</span>' + esc(L(UI.step3)) + '</h2><div class="tk-filters">'
            + ['all', 'colorful', 'simple'].map(function (f) { return '<button type="button" class="chip' + (draft.filter === f ? ' is-active' : '') + '" data-filter="' + f + '">' + esc(L(UI[f])) + '</button>'; }).join('')
            + '</div><div class="tk-gallery" id="tk-gallery"></div></section>'
            + '<div class="tk-bar"><span class="small" id="tk-summary"></span><div class="row">'
            + (state ? '<button type="button" class="btn btn--outline" id="tk-resume">' + esc(L(UI.resume)) + '</button>' : '')
            + '<button type="button" class="btn btn--primary btn--lg" id="tk-start">' + esc(L(UI.start)) + '</button></div></div></div>';
        drawGallery();
        root.querySelectorAll('[data-lang]').forEach(function (b) { b.onclick = function () { draft.lang = b.dataset.lang; setupView(); }; });
        root.querySelectorAll('[data-type]').forEach(function (b) { b.onclick = function () { draft.type = b.dataset.type; setupView(); }; });
        root.querySelectorAll('[data-filter]').forEach(function (b) { b.onclick = function () { draft.filter = b.dataset.filter; setupView(); }; });
        document.getElementById('tk-start').onclick = function () {
            var keep = state ? state.data : null;
            var keepDesign = state && state.template === draft.template ? state.design : design(draft.template);
            state = { v: 2, lang: draft.lang, type: draft.type, template: draft.template, design: keepDesign, data: state ? state.data : blankData(draft.type, keep) };
            if (state.data.number && !new RegExp('^' + (draft.type === 'quote' ? 'D' : draft.type === 'proforma' ? 'PF' : 'F') + '-').test(state.data.number)) state.data.number = blankData(draft.type).number;
            state.design.template = draft.template;
            STORE.save(state); editorView();
        };
        var resume = document.getElementById('tk-resume');
        if (resume) resume.onclick = editorView;
        var tName = (TEMPLATES.filter(function (x) { return x.v === draft.template; })[0] || TEMPLATES[0]).name;
        document.getElementById('tk-summary').textContent = LANGS.filter(function (l) { return l.v === draft.lang; })[0].name + ' · ' + L(TYPES.filter(function (t) { return t.v === draft.type; })[0].name) + ' · ' + L(tName);
    }

    function drawGallery() {
        var g = document.getElementById('tk-gallery');
        var demo = { v: 2, lang: draft.lang, type: draft.type, data: Object.assign(blankData(draft.type), sample(draft.lang)) };
        TEMPLATES.filter(function (t) { return draft.filter === 'all' || t.kind === draft.filter; }).forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'tk-tpl'; b.setAttribute('aria-pressed', draft.template === t.v);
            var s = Object.assign({}, demo, { template: t.v, design: design(t.v) });
            b.innerHTML = '<div class="tk-thumb">' + renderDoc(s) + '</div><span>' + esc(L(t.name)) + '</span>';
            b.onclick = function () { draft.template = t.v; setupView(); };
            g.appendChild(b);
            var thumb = b.querySelector('.tk-thumb');
            requestAnimationFrame(function () { var page = thumb.firstElementChild; page.style.transform = 'scale(' + thumb.clientWidth / 794 + ')'; });
        });
    }

    function editorView() {
        if (!state) { setupView(); return; }
        state.design.template = state.template;
        root.innerHTML = '<div class="tk-toolbar no-print"><div class="row">'
            + '<button type="button" class="btn btn--ghost btn--sm" id="tk-back">✎ ' + esc(L(UI.back)) + '</button>'
            + '<button type="button" class="btn btn--ghost btn--sm" id="tk-new">＋ ' + esc(L(UI.newDoc)) + '</button></div>'
            + '<button type="button" class="btn btn--primary btn--sm" id="tk-pdf">⤓ ' + esc(L(UI.pdf)) + '</button></div>'
            + '<div class="tk-editor"><div class="no-print">'
            + '<div class="tk-tabs" role="tablist"><button type="button" role="tab" aria-selected="true" data-tab="info">' + esc(L(UI.info)) + '</button><button type="button" role="tab" aria-selected="false" data-tab="design">' + esc(L(UI.design)) + '</button></div>'
            + '<div class="tk-mini"><button type="button" class="btn btn--outline btn--sm" id="tk-sample">' + esc(L(UI.sample)) + '</button><button type="button" class="btn btn--ghost btn--sm" id="tk-clear">' + esc(L(UI.clear)) + '</button></div>'
            + '<div id="tk-form"></div><p class="tk-private">' + esc(L(UI.private)) + '</p></div>'
            + '<div class="tk-previewcol"><div class="tk-prevhead no-print"><span id="tk-status"></span><button type="button" class="btn btn--primary btn--sm" id="tk-pdf2">PDF ⤓</button></div>'
            + '<div class="tk-frame printable" id="tk-frame"></div><p class="hint no-print" style="margin-top:8px">' + esc(L(UI.pdfHint)) + '</p></div></div>';

        var frame = document.getElementById('tk-frame');
        var fit = K.fitPreview(frame);
        function paint() {
            frame.innerHTML = renderDoc(state);
            fit();
            var tt = totals(state.data), tName = (TEMPLATES.filter(function (x) { return x.v === state.template; })[0] || TEMPLATES[0]).name;
            document.getElementById('tk-status').textContent = LANGS.filter(function (l) { return l.v === state.lang; })[0].name + ' · ' + L(tName) + ' · \u2066' + money(tt.ttc, state.lang, state.design.currency) + '\u2069';
        }
        var repaint = K.debounce(paint, 120);
        function onChange(key) {
            if (key === 'template') { state.template = state.design.template; }
            save(); repaint();
        }
        var tab = 'info';
        function drawForm() {
            var box = document.getElementById('tk-form');
            if (tab === 'info') K.buildForm(box, infoSchema(), function () { return state.data; }, onChange);
            else K.buildForm(box, designSchema(), function () { return state.design; }, onChange);
        }
        root.querySelectorAll('[data-tab]').forEach(function (b) {
            b.onclick = function () { tab = b.dataset.tab; root.querySelectorAll('[data-tab]').forEach(function (x) { x.setAttribute('aria-selected', x === b); }); drawForm(); };
        });
        document.getElementById('tk-back').onclick = function () { draft = { lang: state.lang, type: state.type, template: state.template, filter: 'all' }; setupView(); window.scrollTo(0, 0); };
        document.getElementById('tk-new').onclick = function () { if (confirm(L(UI.confirmNew))) { state.data = blankData(state.type, state.data); save(); drawForm(); paint(); } };
        document.getElementById('tk-sample').onclick = function () { Object.assign(state.data, sample(state.lang)); save(); drawForm(); paint(); };
        document.getElementById('tk-clear').onclick = function () { if (confirm(L(UI.confirmClear))) { state.data = blankData(state.type); save(); drawForm(); paint(); } };
        document.getElementById('tk-pdf').onclick = document.getElementById('tk-pdf2').onclick = function () {
            var prev = document.title; document.title = (state.data.number || 'document').replace(/[^\w\-]+/g, '_'); window.print(); document.title = prev;
        };
        drawForm(); paint();
        window.scrollTo(0, Math.max(0, root.getBoundingClientRect().top + window.scrollY - 80));
    }

    // Expose pure helpers for automated checks.
    window.InvoiceTool = { amountWords: amountWords, totals: totals, money: money };
    setupView();
})();
