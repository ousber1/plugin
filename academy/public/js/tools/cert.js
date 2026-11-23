/* Certificate designer: language → type → template, then an editor with live A4 preview,
   signatures, stamp, batch generation from a list or a CSV file. Client-side only. */
(function () {
    'use strict';
    var K = window.ToolKit, L = K.L, esc = K.esc;
    var root = document.getElementById('tool-app');
    if (!root) return;
    var STORE = K.store('tool:cert:v1');

    var UI = {
        step1: { ar: 'لغة الشهادة', fr: 'Langue du certificat', en: 'Certificate language' },
        step2: { ar: 'نوع الشهادة', fr: 'Type de certificat', en: 'Certificate type' },
        step3: { ar: 'اختر القالب', fr: 'Choisissez un modèle', en: 'Choose a template' },
        all: { ar: 'الكل', fr: 'Tous', en: 'All' }, port: { ar: 'عمودية', fr: 'Portrait', en: 'Portrait' }, land: { ar: 'أفقية', fr: 'Paysage', en: 'Landscape' },
        start: { ar: 'ابدأ التصميم', fr: 'Commencer', en: 'Start designing' },
        resume: { ar: 'متابعة التصميم المحفوظ', fr: 'Reprendre', en: 'Continue saved design' },
        info: { ar: 'المعلومات', fr: 'Informations', en: 'Details' }, design: { ar: 'التصميم', fr: 'Design', en: 'Design' },
        sample: { ar: 'ملء بمثال', fr: 'Exemple', en: 'Fill example' }, clear: { ar: 'مسح', fr: 'Effacer', en: 'Clear' },
        back: { ar: 'القالب واللغة', fr: 'Modèle et langue', en: 'Template & language' },
        pdf: { ar: 'تحميل PDF', fr: 'Télécharger PDF', en: 'Download PDF' },
        batchPdf: { ar: 'تحميل {n} شهادة PDF', fr: 'Télécharger {n} certificats PDF', en: 'Download {n} certificates PDF' },
        csv: { ar: 'استيراد من Excel / CSV', fr: 'Importer Excel / CSV', en: 'Import Excel / CSV' },
        csvHint: { ar: 'احفظ ملف Excel بصيغة CSV: نقرأ العمود الأول كأسماء.', fr: 'Enregistrez votre fichier Excel en CSV : la 1re colonne est lue comme noms.', en: 'Save your Excel file as CSV: the first column is read as names.' },
        batchHint: { ar: 'كل شهادة تأخذ رقمًا متتاليًا انطلاقًا من رقم الشهادة.', fr: 'Chaque certificat reçoit un numéro consécutif à partir du numéro saisi.', en: 'Each certificate gets a consecutive number starting from the certificate number.' },
        confirmClear: { ar: 'مسح كل المعلومات؟', fr: 'Tout effacer ?', en: 'Clear everything?' },
        private: { ar: '🔒 تُحفظ معلوماتك تلقائيًا في هذا المتصفح فقط ولا تُرسل إلى أي خادم.', fr: '🔒 Vos données restent dans ce navigateur uniquement.', en: '🔒 Your data is saved in this browser only.' },
        pdfHint: { ar: 'في نافذة الطباعة اختر «حفظ بصيغة PDF» واترك الهوامش «بدون».', fr: 'Dans la fenêtre d’impression, choisissez « Enregistrer en PDF », marges « Aucune ».', en: 'In the print dialog choose “Save as PDF” with margins set to “None”.' },
        tooBig: { ar: 'تعذّر الحفظ: الصور كبيرة جدًا.', fr: 'Enregistrement impossible : images trop lourdes.', en: 'Could not save: images too large.' },
        csvBad: { ar: 'تعذّرت قراءة الملف. استعمل ملف CSV أو TXT.', fr: 'Fichier illisible. Utilisez un CSV ou TXT.', en: 'Could not read the file. Use a CSV or TXT file.' },
        official: { ar: 'هذه الأداة لتصميم شهادات فعالياتك وتكويناتك. لا تُسجَّل الشهادات في نظام التحقق الخاص بالأكاديمية.', fr: 'Outil pour vos propres événements et formations : les certificats ne sont pas enregistrés dans le système de vérification de l’académie.', en: 'For your own events and trainings: certificates are not registered in the academy verification system.' }
    };
    var LANGS = [
        { v: 'ar', name: 'العربية', sub: { ar: 'بالعربية', fr: 'Arabe', en: 'Arabic' }, b: 'AR' },
        { v: 'fr', name: 'Français', sub: { ar: 'بالفرنسية', fr: 'Français', en: 'French' }, b: 'FR' },
        { v: 'en', name: 'English', sub: { ar: 'بالإنجليزية', fr: 'Anglais', en: 'English' }, b: 'EN' }
    ];
    var TYPES = [
        { v: 'success', icon: '🏅', name: { ar: 'نجاح', fr: 'Réussite', en: 'Achievement' } },
        { v: 'participation', icon: '👥', name: { ar: 'مشاركة', fr: 'Participation', en: 'Participation' } },
        { v: 'appreciation', icon: '⭐', name: { ar: 'شكر وتقدير', fr: 'Reconnaissance', en: 'Appreciation' } },
        { v: 'training', icon: '📘', name: { ar: 'تكوين', fr: 'Formation', en: 'Training' } },
        { v: 'internship', icon: '💼', name: { ar: 'تدريب', fr: 'Stage', en: 'Internship' } },
        { v: 'work', icon: '🪪', name: { ar: 'عمل', fr: 'Travail', en: 'Employment' } }
    ];
    var TEMPLATES = [
        { v: 'ornate', o: 'port', name: { ar: 'كلاسيكي مزخرف', fr: 'Classique orné', en: 'Ornate classic' }, c: '#1e2235', c2: '#b8902f' },
        { v: 'academic', o: 'port', name: { ar: 'أكاديمي', fr: 'Académique', en: 'Academic' }, c: '#3a2a9e', c2: '#f5b82e' },
        { v: 'modern', o: 'port', name: { ar: 'عصري', fr: 'Moderne', en: 'Modern' }, c: '#0f766e', c2: '#f59e0b' },
        { v: 'elegant', o: 'port', name: { ar: 'بسيط أنيق', fr: 'Élégant', en: 'Elegant' }, c: '#334155', c2: '#a88a4a' },
        { v: 'zellige', o: 'port', name: { ar: 'زليج مغربي', fr: 'Zellige', en: 'Zellige' }, c: '#0f5c4a', c2: '#c9a227' },
        { v: 'playful', o: 'port', name: { ar: 'مرح', fr: 'Ludique', en: 'Playful' }, c: '#db2777', c2: '#7c3aed' },
        { v: 'premium', o: 'land', name: { ar: 'فخم', fr: 'Prestige', en: 'Premium' }, c: '#141b34', c2: '#d4af37' },
        { v: 'pro', o: 'land', name: { ar: 'تكوين مهني', fr: 'Formation pro', en: 'Professional' }, c: '#1d4ed8', c2: '#38bdf8' },
        { v: 'institution', o: 'land', name: { ar: 'مؤسسة', fr: 'Institution', en: 'Institution' }, c: '#7a1f3d', c2: '#c9a227' },
        { v: 'event', o: 'land', name: { ar: 'مشاركة وحدث', fr: 'Événement', en: 'Event' }, c: '#ea580c', c2: '#2563eb' },
        { v: 'geometric', o: 'land', name: { ar: 'هندسي', fr: 'Géométrique', en: 'Geometric' }, c: '#111827', c2: '#10b981' },
        { v: 'photo', o: 'land', name: { ar: 'مع صورة', fr: 'Avec photo', en: 'With photo' }, c: '#4b37c4', c2: '#f5b82e' }
    ];
    function tplOf(v) { return TEMPLATES.filter(function (t) { return t.v === v; })[0] || TEMPLATES[0]; }
    function typeOf(v) { return TYPES.filter(function (t) { return t.v === v; })[0] || TYPES[0]; }

    /* ---- Wording per language / type / gender ---- */
    var TXT = {
        ar: {
            t1: 'شهادة', t2: { success: 'نجاح', participation: 'مشاركة', appreciation: 'شكر وتقدير', training: 'تكوين', internship: 'تدريب', work: 'عمل' },
            intro: { def: 'تُمنح هذه الشهادة إلى', work: 'نشهد بأن', internship: 'نشهد بأن' },
            prefix: { m: 'السيد', f: 'السيدة' },
            dur: '، بحجم ساعي قدره {duration}',
            body: {
                m: { success: 'تقديرًا لإتمامه بنجاح تكوين «{course}»{dur}، مع تمنياتنا له بدوام التوفيق.', participation: 'لمشاركته الفعّالة في «{course}»{dur}، المنظَّم من طرف {org}.', appreciation: 'عربون شكر وتقدير لجهوده المتميزة ومساهمته القيّمة في «{course}».', training: 'لاستفادته من تكوين «{course}»{dur} واجتيازه بنجاح.', internship: 'أنجز تدريبًا داخل {org} في مجال «{course}»{dur}، وأبان خلاله عن جدية وانضباط.', work: 'يشتغل لدى {org} بصفة «{course}». سُلّمت له هذه الشهادة بطلب منه للإدلاء بها عند الحاجة.' },
                f: { success: 'تقديرًا لإتمامها بنجاح تكوين «{course}»{dur}، مع تمنياتنا لها بدوام التوفيق.', participation: 'لمشاركتها الفعّالة في «{course}»{dur}، المنظَّم من طرف {org}.', appreciation: 'عربون شكر وتقدير لجهودها المتميزة ومساهمتها القيّمة في «{course}».', training: 'لاستفادتها من تكوين «{course}»{dur} واجتيازها بنجاح.', internship: 'أنجزت تدريبًا داخل {org} في مجال «{course}»{dur}، وأبانت خلاله عن جدية وانضباط.', work: 'تشتغل لدى {org} بصفة «{course}». سُلّمت لها هذه الشهادة بطلب منها للإدلاء بها عند الحاجة.' }
            },
            number: 'رقم الشهادة', done: 'حُرّر في {city} بتاريخ {date}', doneNoCity: 'بتاريخ {date}', sign: 'التوقيع', stamp: 'الختم'
        },
        fr: {
            t1: 'Certificat', t2: { success: 'de réussite', participation: 'de participation', appreciation: 'de reconnaissance', training: 'de formation', internship: 'de stage', work: 'de travail' },
            t1Over: { internship: 'Attestation', work: 'Attestation' },
            intro: { def: 'Ce certificat est décerné à', work: 'Nous attestons que', internship: 'Nous attestons que' },
            prefix: { m: 'Monsieur', f: 'Madame' },
            dur: ', d’une durée de {duration}',
            body: {
                m: { success: 'pour avoir suivi et réussi la formation « {course} »{dur}. Nous lui adressons nos sincères félicitations.', participation: 'pour sa participation active à « {course} »{dur}, organisé par {org}.', appreciation: 'en reconnaissance de son engagement et de sa précieuse contribution à « {course} ».', training: 'pour avoir suivi avec succès la formation « {course} »{dur}.', internship: 'a effectué un stage au sein de {org} dans le domaine « {course} »{dur}, faisant preuve de sérieux et d’assiduité.', work: 'est employé au sein de {org} en qualité de « {course} ». La présente attestation est délivrée pour servir et valoir ce que de droit.' },
                f: { success: 'pour avoir suivi et réussi la formation « {course} »{dur}. Nous lui adressons nos sincères félicitations.', participation: 'pour sa participation active à « {course} »{dur}, organisé par {org}.', appreciation: 'en reconnaissance de son engagement et de sa précieuse contribution à « {course} ».', training: 'pour avoir suivi avec succès la formation « {course} »{dur}.', internship: 'a effectué un stage au sein de {org} dans le domaine « {course} »{dur}, faisant preuve de sérieux et d’assiduité.', work: 'est employée au sein de {org} en qualité de « {course} ». La présente attestation est délivrée pour servir et valoir ce que de droit.' }
            },
            number: 'N°', done: 'Fait à {city}, le {date}', doneNoCity: 'Le {date}', sign: 'Signature', stamp: 'Cachet'
        },
        en: {
            t1: 'Certificate', t2: { success: 'of Achievement', participation: 'of Participation', appreciation: 'of Appreciation', training: 'of Training', internship: 'of Internship', work: 'of Employment' },
            intro: { def: 'This certificate is proudly presented to', work: 'This is to certify that', internship: 'This is to certify that' },
            prefix: { m: 'Mr.', f: 'Ms.' },
            dur: ' ({duration})',
            body: {
                m: { success: 'for successfully completing the “{course}” program{dur}. Congratulations on this achievement.', participation: 'for active participation in “{course}”{dur}, organized by {org}.', appreciation: 'in recognition of outstanding dedication and valuable contribution to “{course}”.', training: 'for successfully completing the “{course}” training{dur}.', internship: 'completed an internship at {org} in “{course}”{dur}, showing seriousness and commitment.', work: 'is employed at {org} as “{course}”. This certificate is issued upon request for all legal purposes.' }
            },
            number: 'No.', done: 'Issued in {city} on {date}', doneNoCity: 'Issued on {date}', sign: 'Signature', stamp: 'Stamp'
        }
    };
    TXT.en.body.f = TXT.en.body.m;

    function blankData() {
        return {
            name: '', gender: 'm', number: 'CERT-' + new Date().getFullYear() + '-001', photo: '',
            course: '', duration: '', city: '', date: new Date().toISOString().slice(0, 10),
            logo: '', org: '', orgDesc: '',
            s1name: '', s1role: '', s1img: '', s2name: '', s2role: '', s2img: '', stamp: '',
            title1: '', title2: '', intro: '', body: '', names: ''
        };
    }
    function design(v) { var t = tplOf(v); return { template: v, color: t.c, color2: t.c2, font: 'default', prefix: true, showNumber: true, showLogo: true, nameStyle: 'bold' }; }
    function sample(lang) {
        var p = function (a, f, e) { return lang === 'fr' ? f : lang === 'en' ? e : a; };
        return {
            name: p('ياسين بنعلي', 'Yassine Benali', 'Yassine Benali'), gender: 'm',
            course: p('تطوير الويب المتكامل', 'Développement web full-stack', 'Full-Stack Web Development'),
            duration: p('120 ساعة', '120 heures', '120 hours'), city: p('طنجة', 'Tanger', 'Tangier'),
            org: p('أكاديمية نبراس', 'Académie Nibras', 'Nibras Academy'), orgDesc: p('مركز التكوين في المهن الرقمية', 'Centre de formation aux métiers du numérique', 'Digital skills training centre'),
            s1name: p('محمد العمراني', 'Mohamed El Amrani', 'Mohamed El Amrani'), s1role: p('المدير', 'Directeur', 'Director'),
            s2name: p('سارة الإدريسي', 'Sara El Idrissi', 'Sara El Idrissi'), s2role: p('المكوّنة الرئيسية', 'Formatrice principale', 'Lead trainer'),
            names: p('ياسين بنعلي\nسلمى العلوي\nكريم التازي', 'Yassine Benali\nSalma Alaoui\nKarim Tazi', 'Yassine Benali\nSalma Alaoui\nKarim Tazi')
        };
    }

    var state = STORE.load();
    if (!state || state.v !== 1) state = null;
    var draft = { lang: state ? state.lang : (K.lang === 'fr' || K.lang === 'en' ? K.lang : 'ar'), type: state ? state.type : 'success', template: state ? state.template : 'ornate', filter: 'all' };
    var save = K.debounce(function () { if (!STORE.save(state)) alert(L(UI.tooBig)); }, 400);

    /* ---------------- Rendering ---------------- */
    function fmtDate(v, lang) {
        if (!v) return '';
        var p = String(v).split('-');
        if (p.length !== 3) return v;
        if (lang === 'en') { var m = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][+p[1] - 1]; return m + ' ' + (+p[2]) + ', ' + p[0]; }
        return p[2] + '/' + p[1] + '/' + p[0];
    }
    function fill(str, d, lang) {
        var t = TXT[lang];
        var dur = d.duration ? t.dur.replace('{duration}', d.duration) : '';
        return String(str || '').replace(/\{dur\}/g, dur).replace(/\{course\}/g, d.course || '…').replace(/\{org\}/g, d.org || '…')
            .replace(/\{city\}/g, d.city || '').replace(/\{date\}/g, fmtDate(d.date, lang)).replace(/\{name\}/g, d.name || '').replace(/\{duration\}/g, d.duration || '');
    }
    function deco(tpl) {
        var S = function (inner, vb) { return '<svg class="cert__svg" viewBox="' + (vb || '0 0 100 100') + '" preserveAspectRatio="none" aria-hidden="true">' + inner + '</svg>'; };
        switch (tpl.v) {
            case 'ornate': return '<div class="dc dc-corner dc-tl"><i></i><i></i><b></b><s></s><s></s></div><div class="dc dc-corner dc-br"><i></i><i></i><b></b><s></s><s></s></div>';
            case 'academic': return '<div class="dc dc-band"></div><div class="dc dc-band dc-band--b"></div><div class="dc dc-frame"></div>';
            case 'modern': return S('<polygon points="0,0 58,0 0,22" fill="var(--ac)"/><polygon points="0,0 34,0 0,13" fill="var(--ac2)"/><polygon points="100,100 42,100 100,78" fill="var(--ac)"/><polygon points="100,100 66,100 100,87" fill="var(--ac2)"/>');
            case 'elegant': return '<div class="dc dc-frame dc-frame--double"></div>';
            case 'zellige': return '<div class="dc dc-zellige"></div><div class="dc dc-frame dc-frame--inner"></div>';
            case 'playful': return S('<path d="M0,0 H100 V9 C80,15 65,3 45,9 C28,14 14,5 0,10 Z" fill="var(--ac)"/><path d="M0,0 H100 V5 C75,10 60,1 40,6 C25,10 12,3 0,6 Z" fill="var(--ac2)" opacity=".85"/><path d="M0,100 H100 V91 C82,86 66,96 46,91 C28,86 14,95 0,90 Z" fill="var(--ac2)"/><path d="M0,100 H100 V95 C78,91 62,99 42,94 C26,90 12,97 0,94 Z" fill="var(--ac)" opacity=".9"/><circle cx="88" cy="20" r="2.2" fill="var(--ac2)"/><circle cx="12" cy="80" r="1.6" fill="var(--ac)"/><circle cx="93" cy="74" r="1.2" fill="var(--ac)"/>');
            case 'premium': return '<div class="dc dc-frame dc-frame--gold"></div><div class="dc dc-frame dc-frame--gold2"></div>';
            case 'pro': return '<div class="dc dc-side"></div>' + S('<polygon points="100,0 100,30 82,0" fill="var(--ac2)" opacity=".9"/><polygon points="100,0 100,18 90,0" fill="var(--ac)"/>');
            case 'institution': return '<div class="dc dc-frame dc-frame--thick"></div><div class="dc dc-topbar"></div>';
            case 'event': return '<div class="dc dc-ribbon"><span></span></div>' + S('<circle cx="8" cy="12" r="1.4" fill="var(--ac)"/><circle cx="15" cy="7" r="1" fill="var(--ac2)"/><circle cx="92" cy="88" r="1.6" fill="var(--ac2)"/><circle cx="85" cy="93" r="1" fill="var(--ac)"/><rect x="4" y="88" width="10" height="1.4" fill="var(--ac2)"/><rect x="86" y="10" width="10" height="1.4" fill="var(--ac)"/>');
            case 'geometric': return S('<g fill="none" stroke="var(--ac2)" stroke-width=".25" opacity=".55"><path d="M0,20 L20,0 M0,40 L40,0 M0,60 L60,0 M40,100 L100,40 M60,100 L100,60 M80,100 L100,80"/></g><polygon points="0,0 14,0 0,14" fill="var(--ac)"/><polygon points="100,100 86,100 100,86" fill="var(--ac)"/>') + '<div class="dc dc-frame dc-frame--thin"></div>';
            case 'photo': return '<div class="dc dc-side dc-side--soft"></div>';
        }
        return '';
    }
    function renderCert(s, over) {
        var d = Object.assign({}, s.data, over || {}), lang = s.lang, t = TXT[lang], ds = s.design, tpl = tplOf(s.template), type = s.type;
        var t1 = d.title1 || (t.t1Over && t.t1Over[type]) || t.t1;
        var t2 = d.title2 || t.t2[type];
        var intro = d.intro || t.intro[type] || t.intro.def;
        var gender = d.gender === 'f' ? 'f' : 'm';
        var body = fill(d.body || t.body[gender][type], d, lang);
        var prefix = ds.prefix && d.gender !== 'n' && d.name ? t.prefix[gender] + ' ' : '';
        var done = d.date ? fill(d.city ? t.done : t.doneNoCity, d, lang) : '';
        var sig = function (img, name, role) {
            if (!img && !name && !role) return '';
            return '<div class="cert__sig">' + (img ? '<img src="' + img + '" alt="">' : '<span class="cert__sigline"></span>') + (name ? '<strong>' + esc(name) + '</strong>' : '') + (role ? '<small>' + esc(role) + '</small>' : '') + '</div>';
        };
        var logo = ds.showLogo && d.logo ? '<img class="cert__logo" src="' + d.logo + '" alt="">' : '';
        var head = (logo || d.org) ? '<div class="cert__org">' + logo + '<div>' + (d.org ? '<strong>' + esc(d.org) + '</strong>' : '') + (d.orgDesc ? '<small>' + esc(d.orgDesc) + '</small>' : '') + '</div></div>' : '';
        var number = ds.showNumber && d.number ? '<div class="cert__num"><span>' + esc(t.number) + '</span> <b dir="ltr">' + esc(d.number) + '</b></div>' : '';
        var photo = d.photo && (tpl.v === 'photo' || ds.showPhoto) ? '<img class="cert__photo" src="' + d.photo + '" alt="">' : '';
        var cls = 'a4 cert t-' + tpl.v + ' o-' + tpl.o + (ds.font !== 'default' ? ' f-' + ds.font : '') + ' n-' + (ds.nameStyle || 'bold') + (lang === 'ar' ? ' is-ar' : '');
        return '<div class="' + cls + '" lang="' + lang + '" dir="' + (lang === 'ar' ? 'rtl' : 'ltr') + '" style="--ac:' + esc(ds.color) + ';--ac2:' + esc(ds.color2) + '">'
            + deco(tpl)
            + '<div class="cert__in">'
            + '<header class="cert__head">' + head + number + '</header>'
            + '<div class="cert__main">'
            + (tpl.v === 'photo' ? photo : '')
            + '<div class="cert__title"><span class="t1">' + esc(t1) + '</span><span class="t2">' + esc(t2) + '</span></div>'
            + '<div class="cert__rule"><i></i></div>'
            + '<p class="cert__intro">' + esc(intro) + '</p>'
            + (tpl.v !== 'photo' ? photo : '')
            + '<div class="cert__name">' + esc(prefix + (d.name || '—')) + '</div>'
            + '<p class="cert__body">' + esc(body) + '</p>'
            + (done ? '<p class="cert__done">' + esc(done) + '</p>' : '')
            + '</div>'
            + '<footer class="cert__foot">' + sig(d.s1img, d.s1name, d.s1role)
            + (d.stamp ? '<div class="cert__stamp"><img src="' + d.stamp + '" alt=""></div>' : (tpl.v === 'academic' || tpl.v === 'premium' || tpl.v === 'institution' ? '<div class="cert__seal"><span>★</span></div>' : '<div></div>'))
            + sig(d.s2img, d.s2name, d.s2role) + '</footer>'
            + '</div></div>';
    }
    function pageWidth(tpl) { return tpl.o === 'land' ? 1122 : 793; }

    /* Numbering: CERT-2026-001 → CERT-2026-002 … */
    function nextNumber(base, i) {
        var m = String(base || '').match(/^(.*?)(\d+)(\D*)$/);
        if (!m) return base ? base + '-' + (i + 1) : '';
        var n = String(parseInt(m[2], 10) + i);
        while (n.length < m[2].length) n = '0' + n;
        return m[1] + n + m[3];
    }
    function batchNames() { return String(state.data.names || '').split('\n').map(function (x) { return x.trim(); }).filter(Boolean); }

    /* ---------------- Schemas ---------------- */
    function infoSchema() {
        return [
            { id: 'who', open: true, title: { ar: 'المستفيد', fr: 'Bénéficiaire', en: 'Recipient' }, fields: [
                { k: 'name', label: { ar: 'اسم المستفيد', fr: 'Nom du bénéficiaire', en: 'Recipient name' } },
                { k: 'gender', type: 'select', half: true, label: { ar: 'الجنس', fr: 'Genre', en: 'Gender' }, options: [{ v: 'm', label: { ar: 'ذكر', fr: 'Homme', en: 'Male' } }, { v: 'f', label: { ar: 'أنثى', fr: 'Femme', en: 'Female' } }, { v: 'n', label: { ar: 'بدون لقب', fr: 'Sans civilité', en: 'No title' } }] },
                { k: 'number', dir: 'ltr', half: true, label: { ar: 'رقم الشهادة', fr: 'N° du certificat', en: 'Certificate No.' } },
                { k: 'photo', type: 'image', round: true, maxSize: 420, label: { ar: 'صورة المستفيد (قالب الصورة)', fr: 'Photo (modèle avec photo)', en: 'Photo (photo template)' } }
            ] },
            { id: 'course', open: true, title: { ar: 'التكوين والتاريخ', fr: 'Formation et date', en: 'Program and date' }, fields: [
                { k: 'course', label: { ar: 'اسم التكوين / الحدث / المنصب', fr: 'Formation / événement / poste', en: 'Program / event / position' } },
                { k: 'duration', half: true, label: { ar: 'المدة (مثلًا: 40 ساعة)', fr: 'Durée (ex. 40 heures)', en: 'Duration (e.g. 40 hours)' } },
                { k: 'city', half: true, label: { ar: 'المدينة', fr: 'Ville', en: 'City' } },
                { k: 'date', type: 'date', half: true, label: { ar: 'التاريخ', fr: 'Date', en: 'Date' } }
            ] },
            { id: 'org', title: { ar: 'الجهة المانحة', fr: 'Organisme', en: 'Issuer' }, fields: [
                { k: 'logo', type: 'image', maxSize: 500, png: true, label: { ar: 'الشعار', fr: 'Logo', en: 'Logo' } },
                { k: 'org', label: { ar: 'اسم المؤسسة', fr: 'Nom de l’organisme', en: 'Organization name' } },
                { k: 'orgDesc', label: { ar: 'وصف المؤسسة (اختياري)', fr: 'Description (facultatif)', en: 'Description (optional)' } }
            ] },
            { id: 'signs', title: { ar: 'التوقيعات والختم', fr: 'Signatures et cachet', en: 'Signatures and stamp' }, fields: [
                { k: 's1name', half: true, label: { ar: 'الموقّع الأول — الاسم', fr: 'Signataire 1 — nom', en: 'Signer 1 — name' } },
                { k: 's1role', half: true, label: { ar: 'الصفة', fr: 'Fonction', en: 'Role' } },
                { k: 's1img', type: 'signature', maxSize: 500, png: true, label: { ar: 'صورة التوقيع', fr: 'Signature', en: 'Signature' } },
                { k: 's2name', half: true, label: { ar: 'الموقّع الثاني — الاسم', fr: 'Signataire 2 — nom', en: 'Signer 2 — name' } },
                { k: 's2role', half: true, label: { ar: 'الصفة', fr: 'Fonction', en: 'Role' } },
                { k: 's2img', type: 'signature', maxSize: 500, png: true, label: { ar: 'صورة التوقيع', fr: 'Signature', en: 'Signature' } },
                { k: 'stamp', type: 'image', maxSize: 400, png: true, label: { ar: 'صورة الختم (اختياري)', fr: 'Cachet (facultatif)', en: 'Stamp (optional)' } }
            ] },
            { id: 'texts', title: { ar: 'نصوص الشهادة', fr: 'Textes du certificat', en: 'Certificate texts' }, fields: [
                { k: 'title1', half: true, label: { ar: 'العنوان (السطر 1)', fr: 'Titre (ligne 1)', en: 'Title (line 1)' }, placeholder: function () { var t = TXT[state.lang]; return (t.t1Over && t.t1Over[state.type]) || t.t1; } },
                { k: 'title2', half: true, label: { ar: 'العنوان (السطر 2)', fr: 'Titre (ligne 2)', en: 'Title (line 2)' }, placeholder: function () { return TXT[state.lang].t2[state.type]; } },
                { k: 'intro', label: { ar: 'العبارة قبل الاسم', fr: 'Phrase avant le nom', en: 'Line before the name' }, placeholder: function () { var t = TXT[state.lang]; return t.intro[state.type] || t.intro.def; } },
                { k: 'body', type: 'textarea', rows: 3, label: { ar: 'نص الشهادة', fr: 'Texte', en: 'Text' }, placeholder: function () { return TXT[state.lang].body[state.data.gender === 'f' ? 'f' : 'm'][state.type]; },
                  hint: { ar: 'اتركه فارغًا للنص التلقائي. يمكنك استعمال {name} و{course} و{dur} و{org} و{city} و{date}.', fr: 'Laissez vide pour le texte automatique. Variables : {name} {course} {dur} {org} {city} {date}.', en: 'Leave empty for the automatic text. Variables: {name} {course} {dur} {org} {city} {date}.' } }
            ] },
            { id: 'batch', title: { ar: 'توليد جماعي', fr: 'Génération en lot', en: 'Batch generation' }, fields: [
                { k: 'names', type: 'textarea', rows: 5, label: { ar: 'الأسماء (اسم في كل سطر)', fr: 'Noms (un par ligne)', en: 'Names (one per line)' }, hint: UI.batchHint }
            ], extra: 'batch' }
        ];
    }
    function designSchema() {
        return [{ id: 'design', open: true, title: { ar: 'المظهر', fr: 'Apparence', en: 'Appearance' }, fields: [
            { k: 'template', type: 'select', label: { ar: 'القالب', fr: 'Modèle', en: 'Template' }, options: TEMPLATES.map(function (t) { return { v: t.v, label: { ar: L(t.name) + ' — ' + L(UI[t.o]), fr: t.name.fr + ' — ' + UI[t.o].fr, en: t.name.en + ' — ' + UI[t.o].en } }; }) },
            { k: 'color', type: 'color', half: true, label: { ar: 'اللون الأساسي', fr: 'Couleur principale', en: 'Main color' } },
            { k: 'color2', type: 'color', half: true, label: { ar: 'لون الزخرفة', fr: 'Couleur d’accent', en: 'Accent color' } },
            { k: 'font', type: 'select', half: true, label: { ar: 'الخط', fr: 'Police', en: 'Font' }, options: [{ v: 'default', label: 'Tajawal / Inter' }, { v: 'serif', label: 'Serif' }] },
            { k: 'nameStyle', type: 'select', half: true, label: { ar: 'خط الاسم', fr: 'Style du nom', en: 'Name style' }, options: [{ v: 'bold', label: { ar: 'عريض', fr: 'Gras', en: 'Bold' } }, { v: 'script', label: { ar: 'مائل أنيق', fr: 'Calligraphique', en: 'Script' } }, { v: 'caps', label: { ar: 'أحرف كبيرة', fr: 'Majuscules', en: 'Capitals' } }] },
            { k: 'prefix', type: 'checkbox', label: { ar: 'إضافة اللقب قبل الاسم (السيد / السيدة)', fr: 'Ajouter la civilité (M. / Mme)', en: 'Add title before the name (Mr. / Ms.)' } },
            { k: 'showNumber', type: 'checkbox', label: { ar: 'إظهار رقم الشهادة', fr: 'Afficher le numéro', en: 'Show certificate number' } },
            { k: 'showLogo', type: 'checkbox', label: { ar: 'إظهار الشعار', fr: 'Afficher le logo', en: 'Show logo' } },
            { k: 'showPhoto', type: 'checkbox', label: { ar: 'إظهار صورة المستفيد في كل القوالب', fr: 'Afficher la photo sur tous les modèles', en: 'Show photo on all templates' } }
        ] }];
    }

    /* ---------------- Views ---------------- */
    function thumbHtml(s) {
        var tpl = tplOf(s.template);
        return '<div class="tk-thumb" style="aspect-ratio:' + (tpl.o === 'land' ? '297/210' : '210/297') + '">' + renderCert(s) + '</div>';
    }
    function setupView() {
        var langOpts = LANGS.map(function (l) {
            return '<button type="button" class="tk-option" data-lang="' + l.v + '" aria-pressed="' + (draft.lang === l.v) + '"><span class="tk-badge">' + l.b + '</span><span><strong>' + l.name + '</strong><small>' + esc(L(l.sub)) + '</small></span></button>';
        }).join('');
        var typeOpts = TYPES.map(function (t) {
            return '<button type="button" class="tk-option" data-type="' + t.v + '" aria-pressed="' + (draft.type === t.v) + '"><span class="tk-badge" aria-hidden="true">' + t.icon + '</span><span><strong>' + esc(L(t.name)) + '</strong><small>' + esc(t.name[draft.lang === 'ar' ? 'fr' : draft.lang]) + '</small></span></button>';
        }).join('');
        root.innerHTML = '<div class="tk-wizard">'
            + '<section class="tk-step"><h2><span class="tk-num">1</span>' + esc(L(UI.step1)) + '</h2><div class="tk-options">' + langOpts + '</div></section>'
            + '<section class="tk-step"><h2><span class="tk-num">2</span>' + esc(L(UI.step2)) + '</h2><div class="tk-options">' + typeOpts + '</div></section>'
            + '<section class="tk-step"><h2><span class="tk-num">3</span>' + esc(L(UI.step3)) + '</h2><div class="tk-filters">'
            + ['all', 'port', 'land'].map(function (f) { return '<button type="button" class="chip' + (draft.filter === f ? ' is-active' : '') + '" data-filter="' + f + '">' + esc(L(UI[f])) + '</button>'; }).join('')
            + '</div><div class="tk-gallery tk-gallery--cert" id="tk-gallery"></div></section>'
            + '<div class="tk-bar"><span class="small" id="tk-summary"></span><div class="row">'
            + (state ? '<button type="button" class="btn btn--outline" id="tk-resume">' + esc(L(UI.resume)) + '</button>' : '')
            + '<button type="button" class="btn btn--primary btn--lg" id="tk-start">' + esc(L(UI.start)) + '</button></div></div></div>';
        var g = document.getElementById('tk-gallery');
        var demo = Object.assign(blankData(), sample(draft.lang));
        TEMPLATES.filter(function (t) { return draft.filter === 'all' || t.o === draft.filter; }).forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'tk-tpl'; b.setAttribute('aria-pressed', draft.template === t.v);
            b.innerHTML = thumbHtml({ lang: draft.lang, type: draft.type, template: t.v, design: design(t.v), data: demo }) + '<span>' + esc(L(t.name)) + ' <small class="muted">· ' + esc(L(UI[t.o])) + '</small></span>';
            b.onclick = function () { draft.template = t.v; setupView(); };
            g.appendChild(b);
            var thumb = b.querySelector('.tk-thumb');
            requestAnimationFrame(function () { thumb.firstElementChild.style.transform = 'scale(' + thumb.clientWidth / pageWidth(t) + ')'; });
        });
        root.querySelectorAll('[data-lang]').forEach(function (b) { b.onclick = function () { draft.lang = b.dataset.lang; setupView(); }; });
        root.querySelectorAll('[data-type]').forEach(function (b) { b.onclick = function () { draft.type = b.dataset.type; setupView(); }; });
        root.querySelectorAll('[data-filter]').forEach(function (b) { b.onclick = function () { draft.filter = b.dataset.filter; setupView(); }; });
        document.getElementById('tk-start').onclick = function () {
            var keepDesign = state && state.template === draft.template ? state.design : design(draft.template);
            var data = state ? state.data : Object.assign(blankData(), sample(draft.lang));
            if (state && state.lang !== draft.lang) { data.title1 = data.title2 = data.intro = data.body = ''; }
            state = { v: 1, lang: draft.lang, type: draft.type, template: draft.template, design: keepDesign, data: data };
            state.design.template = draft.template;
            STORE.save(state); editorView();
        };
        var resume = document.getElementById('tk-resume');
        if (resume) resume.onclick = editorView;
        document.getElementById('tk-summary').textContent = LANGS.filter(function (l) { return l.v === draft.lang; })[0].name + ' · ' + L(typeOf(draft.type).name) + ' · ' + L(tplOf(draft.template).name);
    }

    function editorView() {
        if (!state) { setupView(); return; }
        state.design.template = state.template;
        root.innerHTML = '<div class="tk-toolbar no-print"><div class="row">'
            + '<button type="button" class="btn btn--ghost btn--sm" id="tk-back">✎ ' + esc(L(UI.back)) + '</button></div>'
            + '<button type="button" class="btn btn--primary btn--sm" id="tk-pdf">⤓ ' + esc(L(UI.pdf)) + '</button></div>'
            + '<div class="tk-editor no-print"><div>'
            + '<div class="tk-tabs" role="tablist"><button type="button" role="tab" aria-selected="true" data-tab="info">' + esc(L(UI.info)) + '</button><button type="button" role="tab" aria-selected="false" data-tab="design">' + esc(L(UI.design)) + '</button></div>'
            + '<div class="tk-mini"><button type="button" class="btn btn--outline btn--sm" id="tk-sample">' + esc(L(UI.sample)) + '</button><button type="button" class="btn btn--ghost btn--sm" id="tk-clear">' + esc(L(UI.clear)) + '</button></div>'
            + '<div id="tk-form"></div><div id="tk-batch"></div><p class="tk-private">' + esc(L(UI.private)) + '</p><p class="tk-private">ℹ️ ' + esc(L(UI.official)) + '</p></div>'
            + '<div class="tk-previewcol"><div class="tk-prevhead"><span id="tk-status"></span><button type="button" class="btn btn--primary btn--sm" id="tk-pdf2">PDF ⤓</button></div>'
            + '<div class="tk-frame" id="tk-frame"></div><p class="hint" style="margin-top:8px">' + esc(L(UI.pdfHint)) + '</p></div></div>'
            + '<div class="cert-print printable" id="cert-print" aria-hidden="true"></div>';
        var frame = document.getElementById('tk-frame');
        function fit() {
            var page = frame.firstElementChild; if (!page) return;
            var sc = Math.min(1, frame.clientWidth / pageWidth(tplOf(state.template)));
            page.style.transform = 'scale(' + sc + ')';
            frame.style.height = Math.ceil(page.offsetHeight * sc) + 'px';
        }
        window.addEventListener('resize', K.debounce(fit, 80));
        function paint() {
            frame.innerHTML = renderCert(state); fit();
            document.getElementById('tk-status').textContent = LANGS.filter(function (l) { return l.v === state.lang; })[0].name + ' · ' + L(typeOf(state.type).name) + ' · ' + L(tplOf(state.template).name);
            drawBatch();
        }
        var repaint = K.debounce(paint, 120);
        function onChange(key) {
            if (key === 'template') {
                state.template = state.design.template;
                var t = tplOf(state.template);
                state.design.color = t.c; state.design.color2 = t.c2;
                drawForm();
            }
            save(); repaint();
        }
        var tab = 'info';
        function drawForm() {
            var box = document.getElementById('tk-form');
            if (tab === 'info') K.buildForm(box, infoSchema(), function () { return state.data; }, onChange);
            else K.buildForm(box, designSchema(), function () { return state.design; }, onChange);
            document.getElementById('tk-batch').hidden = tab !== 'info';
        }
        function drawBatch() {
            var box = document.getElementById('tk-batch'); if (!box) return;
            var n = batchNames().length;
            box.innerHTML = '<div class="tk-batchbar"><label class="btn btn--outline btn--sm" tabindex="0">' + esc(L(UI.csv)) + '<input type="file" accept=".csv,.txt,text/csv,text/plain" hidden id="tk-csv"></label>'
                + '<button type="button" class="btn btn--primary btn--sm" id="tk-batchpdf"' + (n ? '' : ' disabled') + '>⤓ ' + esc(L(UI.batchPdf).replace('{n}', n)) + '</button></div>'
                + '<p class="hint">' + esc(L(UI.csvHint)) + '</p>';
            document.getElementById('tk-csv').onchange = function (e) {
                var f = e.target.files[0]; if (!f) return;
                if (f.size > 2 * 1024 * 1024 || /\.(xlsx?|ods)$/i.test(f.name)) { alert(L(UI.csvHint)); return; }
                var fr = new FileReader();
                fr.onload = function () {
                    var text = String(fr.result || '').replace(/^﻿/, '');
                    var rows = text.split(/\r?\n/).map(function (line) { return line.split(/[;,\t]/)[0].replace(/^"|"$/g, '').trim(); }).filter(Boolean);
                    if (rows.length && /^(name|nom|الاسم|names|noms)$/i.test(rows[0])) rows.shift();
                    if (!rows.length) { alert(L(UI.csvBad)); return; }
                    state.data.names = rows.slice(0, 500).join('\n'); save(); drawForm(); paint();
                };
                fr.onerror = function () { alert(L(UI.csvBad)); };
                fr.readAsText(f);
            };
            document.getElementById('tk-batchpdf').onclick = function () { printAll(batchNames()); };
        }
        function printAll(names) {
            var tpl = tplOf(state.template);
            var out = document.getElementById('cert-print');
            out.innerHTML = (names && names.length ? names : [state.data.name]).map(function (name, i) {
                return renderCert(state, names && names.length ? { name: name, number: nextNumber(state.data.number, i) } : null);
            }).join('');
            var style = document.getElementById('cert-page-style') || document.head.appendChild(K.el('style', { id: 'cert-page-style' }));
            style.textContent = '@media print { @page { size: A4 ' + (tpl.o === 'land' ? 'landscape' : 'portrait') + '; margin: 0; } }';
            document.body.classList.add('cert-printing');
            var prev = document.title;
            document.title = (L(typeOf(state.type).name) + ' ' + (names && names.length > 1 ? '(' + names.length + ')' : (state.data.name || ''))).trim().replace(/\s+/g, '_');
            var done = function () { document.body.classList.remove('cert-printing'); document.title = prev; window.removeEventListener('afterprint', done); };
            window.addEventListener('afterprint', done);
            setTimeout(function () { window.print(); setTimeout(done, 1500); }, 60);
        }
        root.querySelectorAll('[data-tab]').forEach(function (b) {
            b.onclick = function () { tab = b.dataset.tab; root.querySelectorAll('[data-tab]').forEach(function (x) { x.setAttribute('aria-selected', x === b); }); drawForm(); };
        });
        document.getElementById('tk-back').onclick = function () { draft = { lang: state.lang, type: state.type, template: state.template, filter: 'all' }; setupView(); window.scrollTo(0, 0); };
        document.getElementById('tk-sample').onclick = function () {
            var keep = { logo: state.data.logo, s1img: state.data.s1img, s2img: state.data.s2img, stamp: state.data.stamp, photo: state.data.photo };
            state.data = Object.assign(blankData(), sample(state.lang), keep); save(); drawForm(); paint();
        };
        document.getElementById('tk-clear').onclick = function () { if (confirm(L(UI.confirmClear))) { state.data = blankData(); save(); drawForm(); paint(); } };
        document.getElementById('tk-pdf').onclick = document.getElementById('tk-pdf2').onclick = function () { printAll(null); };
        drawForm(); paint();
        window.scrollTo(0, Math.max(0, root.getBoundingClientRect().top + window.scrollY - 80));
    }

    window.CertDesigner = { render: renderCert, nextNumber: nextNumber, templates: TEMPLATES, types: TYPES };
    setupView();
})();
