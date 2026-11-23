/* CV builder. Client-side only (localStorage). */
(function () {
    'use strict';
    var K = window.ToolKit, L = K.L, esc = K.esc;
    var root = document.getElementById('tool-app');
    if (!root) return;
    var STORE = K.store('tool:cv:v2');

    var UI = {
        step1: { ar: 'لغة السيرة الذاتية', fr: 'Langue du CV', en: 'CV language' },
        step2: { ar: 'اختر القالب', fr: 'Choisissez un modèle', en: 'Choose a template' },
        all: { ar: 'الكل', fr: 'Tous', en: 'All' }, sidebar: { ar: 'بشريط جانبي', fr: 'Colonne latérale', en: 'Sidebar' },
        header: { ar: 'برأس ملوّن', fr: 'En-tête coloré', en: 'Colored header' }, simple: { ar: 'بسيطة', fr: 'Sobres', en: 'Simple' },
        start: { ar: 'ابدأ إنشاء السيرة', fr: 'Créer mon CV', en: 'Start my CV' },
        resume: { ar: 'متابعة السيرة المحفوظة', fr: 'Reprendre mon CV', en: 'Continue saved CV' },
        info: { ar: 'المعلومات', fr: 'Informations', en: 'Details' }, design: { ar: 'التصميم', fr: 'Design', en: 'Design' },
        sample: { ar: 'ملء بمثال', fr: 'Exemple', en: 'Fill example' }, clear: { ar: 'مسح', fr: 'Effacer', en: 'Clear' },
        back: { ar: 'القالب واللغة', fr: 'Modèle et langue', en: 'Template & language' },
        pdf: { ar: 'تحميل PDF', fr: 'Télécharger PDF', en: 'Download PDF' },
        confirmClear: { ar: 'مسح كل معلومات السيرة؟', fr: 'Effacer tout le CV ?', en: 'Clear the whole CV?' },
        private: { ar: '🔒 تُحفظ سيرتك تلقائيًا في هذا المتصفح فقط ولا تُرسل إلى أي خادم.', fr: '🔒 Votre CV est enregistré dans ce navigateur uniquement.', en: '🔒 Your CV is saved in this browser only.' },
        pdfHint: { ar: 'في نافذة الطباعة اختر «حفظ بصيغة PDF».', fr: 'Dans la fenêtre d’impression, choisissez « Enregistrer en PDF ».', en: 'In the print dialog, choose “Save as PDF”.' },
        tooBig: { ar: 'تعذّر الحفظ: الصورة كبيرة جدًا.', fr: 'Enregistrement impossible : photo trop lourde.', en: 'Could not save: photo too large.' }
    };
    var LANGS = [
        { v: 'fr', name: 'Français', sub: { ar: 'بالفرنسية', fr: 'Français', en: 'French' }, b: 'FR' },
        { v: 'en', name: 'English', sub: { ar: 'بالإنجليزية', fr: 'Anglais', en: 'English' }, b: 'EN' },
        { v: 'ar', name: 'العربية', sub: { ar: 'بالعربية', fr: 'Arabe', en: 'Arabic' }, b: 'AR' }
    ];
    // layout: left | right | band | single
    var TEMPLATES = [
        { v: 'azur', name: { ar: 'أزرق جانبي', fr: 'Azur', en: 'Azure' }, layout: 'left', style: '', color: '#2563eb', color2: '#60a5fa', kind: 'sidebar' },
        { v: 'night', name: { ar: 'كحلي ذهبي', fr: 'Nuit dorée', en: 'Golden night' }, layout: 'left', style: 's-dark', color: '#c9a227', color2: '#f5d76e', kind: 'sidebar' },
        { v: 'emerald', name: { ar: 'زمردي', fr: 'Émeraude', en: 'Emerald' }, layout: 'right', style: '', color: '#0f766e', color2: '#5eead4', kind: 'sidebar' },
        { v: 'lavender', name: { ar: 'ناعم', fr: 'Lavande', en: 'Lavender' }, layout: 'left', style: 's-soft', color: '#7c3aed', color2: '#c4b5fd', kind: 'sidebar' },
        { v: 'formal', name: { ar: 'رسمي', fr: 'Formel', en: 'Formal' }, layout: 'left', style: 's-line', color: '#334155', color2: '#94a3b8', kind: 'sidebar' },
        { v: 'horizon', name: { ar: 'رأس ملوّن', fr: 'Horizon', en: 'Horizon' }, layout: 'band', style: '', color: '#1d4e89', color2: '#5ba3e0', kind: 'header' },
        { v: 'sunset', name: { ar: 'متدرّج', fr: 'Coucher de soleil', en: 'Sunset' }, layout: 'band', style: 's-gradient', color: '#db2777', color2: '#f59e0b', kind: 'header' },
        { v: 'impact', name: { ar: 'جريء', fr: 'Impact', en: 'Impact' }, layout: 'band', style: 's-bold', color: '#e4572e', color2: '#ffb38a', kind: 'header' },
        { v: 'pure', name: { ar: 'بسيط', fr: 'Épuré', en: 'Pure' }, layout: 'single', style: 's-minimal', color: '#1f2430', color2: '#9aa0ad', kind: 'simple' },
        { v: 'classic', name: { ar: 'أنيق', fr: 'Classique', en: 'Classic' }, layout: 'single', style: 's-elegant', color: '#8a6d3b', color2: '#d9c7a3', kind: 'simple' },
        { v: 'path', name: { ar: 'خط زمني', fr: 'Parcours', en: 'Timeline' }, layout: 'single', style: 's-timeline', color: '#0e7490', color2: '#67e8f9', kind: 'simple' },
        { v: 'compact', name: { ar: 'مدمج', fr: 'Compact', en: 'Compact' }, layout: 'single', style: 's-line', color: '#4b37c4', color2: '#a5b4fc', kind: 'simple', size: 's' }
    ];
    var D = {
        fr: { profile: 'Profil', experience: 'Expérience professionnelle', education: 'Formation', skills: 'Compétences', languages: 'Langues', certifications: 'Certifications', projects: 'Projets', references: 'Références', interests: 'Centres d’intérêt', contact: 'Contact', present: 'Présent', birth: 'Né(e) le', license: 'Permis', nationality: 'Nationalité',
              levels: { A1: 'Débutant (A1)', A2: 'Élémentaire (A2)', B1: 'Intermédiaire (B1)', B2: 'Avancé (B2)', C1: 'Autonome (C1)', C2: 'Maîtrise (C2)', native: 'Langue maternelle' } },
        en: { profile: 'Profile', experience: 'Work experience', education: 'Education', skills: 'Skills', languages: 'Languages', certifications: 'Certifications', projects: 'Projects', references: 'References', interests: 'Interests', contact: 'Contact', present: 'Present', birth: 'Born', license: 'Driving licence', nationality: 'Nationality',
              levels: { A1: 'Beginner (A1)', A2: 'Elementary (A2)', B1: 'Intermediate (B1)', B2: 'Upper-intermediate (B2)', C1: 'Advanced (C1)', C2: 'Proficient (C2)', native: 'Native' } },
        ar: { profile: 'نبذة', experience: 'الخبرة المهنية', education: 'التكوين', skills: 'المهارات', languages: 'اللغات', certifications: 'الشهادات', projects: 'المشاريع', references: 'المراجع', interests: 'الاهتمامات', contact: 'التواصل', present: 'حتى الآن', birth: 'تاريخ الازدياد', license: 'رخصة السياقة', nationality: 'الجنسية',
              levels: { A1: 'مبتدئ (A1)', A2: 'أساسي (A2)', B1: 'متوسط (B1)', B2: 'فوق المتوسط (B2)', C1: 'متقدم (C1)', C2: 'متمكن (C2)', native: 'اللغة الأم' } }
    };
    var LEVEL_ORDER = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'native'];

    function blankData() {
        return {
            photo: '', name: '', title: '', phone: '', email: '', address: '', linkedin: '', website: '', birth: '', license: '', nationality: '',
            profile: '',
            experience: [{ role: '', company: '', city: '', start: '', end: '', current: false, missions: '' }],
            education: [{ degree: '', school: '', city: '', start: '', end: '', details: '' }],
            skills: [{ name: '', level: 4 }, { name: '', level: 4 }, { name: '', level: 3 }],
            languages: [{ name: '', level: 'B2' }, { name: '', level: 'B2' }],
            certifications: [], projects: [], references: [], interests: ''
        };
    }
    function tplOf(v) { return TEMPLATES.filter(function (t) { return t.v === v; })[0] || TEMPLATES[0]; }
    function design(v) { var t = tplOf(v); return { template: v, color: t.color, color2: t.color2, font: 'default', size: t.size || 'm', photo: 'circle', levels: true }; }

    var state = STORE.load();
    if (!state || state.v !== 2) state = null;
    var draft = { lang: state ? state.lang : 'fr', template: state ? state.template : 'azur', filter: 'all' };
    var save = K.debounce(function () { if (!STORE.save(state)) alert(L(UI.tooBig)); }, 400);

    function sample(lang) {
        var fr = lang === 'fr', en = lang === 'en';
        var pick = function (a, f, e) { return fr ? f : en ? e : a; };
        return {
            name: pick('سلمى العمراني', 'Salma El Amrani', 'Salma El Amrani'),
            title: pick('مطوّرة ويب Full-Stack', 'Développeuse Web Full-Stack', 'Full-Stack Web Developer'),
            phone: '+212 6 12 34 56 78', email: 'salma.elamrani@example.com', address: pick('الرباط، المغرب', 'Rabat, Maroc', 'Rabat, Morocco'),
            linkedin: 'linkedin.com/in/salma-elamrani', website: 'salma.dev', nationality: pick('مغربية', 'Marocaine', 'Moroccan'), license: 'B',
            profile: pick('مطوّرة ويب بخبرة 4 سنوات في بناء تطبيقات سريعة وسهلة الاستعمال. أحب العمل الجماعي وتعلّم التقنيات الجديدة.',
                'Développeuse web avec 4 ans d’expérience dans la création d’applications rapides et accessibles. J’aime le travail en équipe et apprendre de nouvelles technologies.',
                'Web developer with 4 years of experience building fast, accessible applications. I enjoy teamwork and learning new technologies.'),
            experience: [
                { role: pick('مطوّرة Full-Stack', 'Développeuse Full-Stack', 'Full-Stack Developer'), company: 'Atlas Digital', city: pick('الدار البيضاء', 'Casablanca', 'Casablanca'), start: '2023-02', end: '', current: true,
                  missions: pick('تطوير منصة حجز بلغة PHP وLaravel\nتحسين سرعة الموقع بنسبة 40%\nتأطير مطوّرين متدرّبين', 'Développement d’une plateforme de réservation (PHP / Laravel)\nAmélioration des performances de 40 %\nEncadrement de deux stagiaires', 'Built a booking platform (PHP / Laravel)\nImproved performance by 40%\nMentored two interns') },
                { role: pick('مطوّرة واجهات', 'Développeuse Front-End', 'Front-End Developer'), company: 'Studio Medina', city: pick('الرباط', 'Rabat', 'Rabat'), start: '2021-06', end: '2023-01', current: false,
                  missions: pick('إنجاز مواقع متجاوبة لعملاء في السياحة والتجارة', 'Réalisation de sites responsive pour des clients du tourisme et du commerce', 'Delivered responsive websites for tourism and retail clients') }
            ],
            education: [
                { degree: pick('إجازة مهنية في تطوير المعلوميات', 'Licence professionnelle en développement informatique', 'Professional bachelor in software development'), school: 'Université Mohammed V', city: pick('الرباط', 'Rabat', 'Rabat'), start: '2018-09', end: '2021-06', details: '' }
            ],
            skills: [{ name: 'JavaScript', level: 5 }, { name: 'PHP / Laravel', level: 4 }, { name: 'SQL', level: 4 }, { name: 'UI / UX', level: 3 }],
            languages: [{ name: pick('العربية', 'Arabe', 'Arabic'), level: 'native' }, { name: pick('الفرنسية', 'Français', 'French'), level: 'C1' }, { name: pick('الإنجليزية', 'Anglais', 'English'), level: 'B2' }],
            certifications: [{ name: 'Responsive Web Design', org: 'freeCodeCamp', date: '2022' }],
            projects: [{ name: pick('تطبيق قائمة المهام', 'Application de tâches', 'Task app'), link: 'github.com/salma/tasks', desc: pick('تطبيق ويب بسيط بـ JavaScript', 'Application web en JavaScript', 'A simple JavaScript web app') }],
            references: [],
            interests: pick('القراءة، الجري، التصوير', 'Lecture, course à pied, photographie', 'Reading, running, photography')
        };
    }

    /* ---------------- Rendering ---------------- */
    function ym(v, lg) { if (!v) return ''; var p = v.split('-'); return p.length === 2 ? p[1] + '/' + p[0] : v; }
    function period(it, t, lg) {
        var a = ym(it.start, lg), b = it.current ? t.present : ym(it.end, lg);
        return a || b ? '<span class="cv-date" dir="ltr">' + esc(a) + (a && b ? ' – ' : '') + esc(b) + '</span>' : '';
    }
    function bullets(text) {
        var lines = String(text || '').split('\n').map(function (x) { return x.replace(/^[-•*]\s*/, '').trim(); }).filter(Boolean);
        if (!lines.length) return '';
        return lines.length === 1 ? '<p>' + esc(lines[0]) + '</p>' : '<ul>' + lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>';
    }
    function filled(arr, key) { return (arr || []).filter(function (x) { return String(x[key] || '').trim(); }); }

    function renderDoc(s) {
        var d = s.data, lg = s.lang, t = D[lg], ds = s.design, tpl = tplOf(s.template);
        var sec = function (title, body) { return body ? '<section class="cv-sec"><h2>' + esc(title) + '</h2>' + body + '</section>' : ''; };
        var photo = d.photo && ds.photo !== 'none' ? '<img class="cv-photo" alt="" src="' + d.photo + '">' : '';
        var nameBlock = '<div><div class="cv-name">' + esc(d.name || '—') + '</div>' + (d.title ? '<div class="cv-title">' + esc(d.title) + '</div>' : '') + '</div>';
        var contacts = [['☎', d.phone, 1], ['✉', d.email, 1], ['⌂', d.address], ['in', d.linkedin, 1], ['🔗', d.website, 1], ['★', d.birth && (t.birth + ' ' + d.birth)], ['⚑', d.nationality && (t.nationality + ' : ' + d.nationality)], ['🚗', d.license && (t.license + ' ' + d.license)]]
            .filter(function (c) { return c[1]; })
            .map(function (c) { return '<li><span aria-hidden="true">' + c[0] + '</span><span' + (c[2] ? ' dir="ltr"' : '') + '>' + esc(c[1]) + '</span></li>'; }).join('');
        var contactList = contacts ? '<ul class="cv-contact">' + contacts + '</ul>' : '';

        var exp = filled(d.experience, 'role').map(function (it) {
            return '<div class="cv-item"><div class="cv-item__head"><strong>' + esc(it.role) + '</strong>' + period(it, t, lg) + '</div>'
                + '<div class="cv-org">' + esc([it.company, it.city].filter(Boolean).join(' · ')) + '</div>' + bullets(it.missions) + '</div>';
        }).join('');
        var edu = filled(d.education, 'degree').map(function (it) {
            return '<div class="cv-item"><div class="cv-item__head"><strong>' + esc(it.degree) + '</strong>' + period(it, t, lg) + '</div>'
                + '<div class="cv-org">' + esc([it.school, it.city].filter(Boolean).join(' · ')) + '</div>' + bullets(it.details) + '</div>';
        }).join('');
        var skills = filled(d.skills, 'name');
        var skillsHtml = skills.length ? (ds.levels
            ? skills.map(function (k) { var lv = Math.max(1, Math.min(5, Number(k.level) || 3)); return '<div class="cv-skill"><div><span>' + esc(k.name) + '</span></div><div class="cv-bar"><i style="width:' + lv * 20 + '%"></i></div></div>'; }).join('')
            : '<div class="cv-tags">' + skills.map(function (k) { return '<span>' + esc(k.name) + '</span>'; }).join('') + '</div>') : '';
        var langsHtml = filled(d.languages, 'name').map(function (k) {
            var idx = LEVEL_ORDER.indexOf(k.level) + 1;
            return '<div class="cv-skill"><div><span>' + esc(k.name) + '</span><small>' + esc(t.levels[k.level] || '') + '</small></div>' + (ds.levels && idx ? '<div class="cv-bar"><i style="width:' + Math.round(idx * 100 / 7) + '%"></i></div>' : '') + '</div>';
        }).join('');
        var certs = filled(d.certifications, 'name').map(function (c) { return '<div class="cv-item"><div class="cv-item__head"><strong>' + esc(c.name) + '</strong>' + (c.date ? '<span class="cv-date">' + esc(c.date) + '</span>' : '') + '</div>' + (c.org ? '<div class="cv-org">' + esc(c.org) + '</div>' : '') + '</div>'; }).join('');
        var projects = filled(d.projects, 'name').map(function (p) { return '<div class="cv-item"><div class="cv-item__head"><strong>' + esc(p.name) + '</strong>' + (p.link ? '<span class="cv-date" dir="ltr">' + esc(p.link) + '</span>' : '') + '</div>' + bullets(p.desc) + '</div>'; }).join('');
        var refs = filled(d.references, 'name').map(function (r) { return '<div class="cv-item"><strong>' + esc(r.name) + '</strong>' + (r.role ? '<div class="cv-org">' + esc(r.role) + '</div>' : '') + (r.contact ? '<p dir="ltr">' + esc(r.contact) + '</p>' : '') + '</div>'; }).join('');
        var interests = String(d.interests || '').split(/[,،]/).map(function (x) { return x.trim(); }).filter(Boolean);
        var interestsHtml = interests.length ? '<div class="cv-tags">' + interests.map(function (i) { return '<span>' + esc(i) + '</span>'; }).join('') + '</div>' : '';

        var mainSecs = sec(t.profile, d.profile ? '<p style="margin:0;white-space:pre-line">' + esc(d.profile) + '</p>' : '') + sec(t.experience, exp) + sec(t.education, edu) + sec(t.projects, projects);
        var asideSecs = sec(t.skills, skillsHtml) + sec(t.languages, langsHtml) + sec(t.certifications, certs) + sec(t.interests, interestsHtml) + sec(t.references, refs);

        var body;
        if (tpl.layout === 'left' || tpl.layout === 'right') {
            body = '<aside class="cv-side">' + photo + sec(t.contact, contactList) + sec(t.skills, skillsHtml) + sec(t.languages, langsHtml) + sec(t.interests, interestsHtml) + '</aside>'
                + '<div class="cv-main">' + nameBlock + mainSecs + sec(t.certifications, certs) + sec(t.references, refs) + '</div>';
        } else if (tpl.layout === 'band') {
            body = '<header class="cv-band"><div class="cv-header">' + photo + '<div>' + nameBlock + contactList + '</div></div></header>'
                + '<div class="cv-body"><div>' + mainSecs + '</div><div>' + asideSecs + '</div></div>';
        } else {
            body = '<div class="cv-main"><header class="cv-header">' + photo + '<div style="flex:1">' + nameBlock + '</div></header>'
                + (contactList ? '<div style="margin-top:12px">' + contactList.replace('class="cv-contact"', 'class="cv-contact" style="display:flex;flex-wrap:wrap;gap:4px 18px"') + '</div>' : '')
                + '<div class="cv-cols"><div>' + mainSecs + '</div><div>' + asideSecs + '</div></div></div>';
        }
        var cls = 'a4 cv l-' + tpl.layout + (tpl.style ? ' ' + tpl.style : '') + ' ph-' + ds.photo + (ds.font !== 'default' ? ' f-' + ds.font : '') + (ds.size !== 'm' ? ' s-' + ds.size : '');
        return '<div class="' + cls + '" lang="' + lg + '" dir="' + (lg === 'ar' ? 'rtl' : 'ltr') + '" style="--ac:' + esc(ds.color) + ';--ac2:' + esc(ds.color2) + '">' + body + '</div>';
    }

    /* ---------------- Schemas ---------------- */
    var LEVELS = LEVEL_ORDER.map(function (v) { return { v: v, label: { ar: D.ar.levels[v], fr: D.fr.levels[v], en: D.en.levels[v] } }; });
    function infoSchema() {
        var dates = [
            { k: 'start', type: 'month', half: true, label: { ar: 'البداية', fr: 'Début', en: 'Start' } },
            { k: 'end', type: 'month', half: true, label: { ar: 'النهاية', fr: 'Fin', en: 'End' } }
        ];
        return [
            { id: 'perso', open: true, title: { ar: 'المعلومات الشخصية', fr: 'Informations personnelles', en: 'Personal details' }, fields: [
                { k: 'photo', type: 'image', round: true, maxSize: 420, label: { ar: 'الصورة', fr: 'Photo', en: 'Photo' }, note: { ar: 'اختيارية — صورة مهنية واضحة.', fr: 'Facultative — photo professionnelle.', en: 'Optional — a professional photo.' } },
                { k: 'name', label: { ar: 'الاسم الكامل', fr: 'Nom complet', en: 'Full name' } },
                { k: 'title', label: { ar: 'المسمى الوظيفي', fr: 'Intitulé du poste', en: 'Job title' }, placeholder: { ar: 'مثال: مطوّر ويب', fr: 'Ex. : Développeur Web Full-Stack', en: 'e.g. Web Developer' } },
                { k: 'phone', type: 'tel', dir: 'ltr', half: true, label: { ar: 'الهاتف', fr: 'Téléphone', en: 'Phone' } },
                { k: 'email', type: 'email', dir: 'ltr', half: true, label: { ar: 'البريد الإلكتروني', fr: 'E-mail', en: 'Email' } },
                { k: 'address', half: true, label: { ar: 'المدينة / العنوان', fr: 'Ville / Adresse', en: 'City / Address' } },
                { k: 'linkedin', dir: 'ltr', half: true, label: 'LinkedIn', placeholder: 'linkedin.com/in/…' },
                { k: 'website', dir: 'ltr', half: true, label: { ar: 'الموقع / المعرض', fr: 'Site web / Portfolio', en: 'Website / Portfolio' } },
                { k: 'birth', half: true, label: { ar: 'تاريخ الازدياد (اختياري)', fr: 'Date de naissance (facultatif)', en: 'Date of birth (optional)' } },
                { k: 'nationality', half: true, label: { ar: 'الجنسية (اختياري)', fr: 'Nationalité (facultatif)', en: 'Nationality (optional)' } },
                { k: 'license', half: true, label: { ar: 'رخصة السياقة (اختياري)', fr: 'Permis de conduire (facultatif)', en: 'Driving licence (optional)' } }
            ] },
            { id: 'profile', title: { ar: 'الملخّص المهني', fr: 'Profil professionnel', en: 'Professional profile' }, fields: [
                { k: 'profile', type: 'textarea', rows: 4, label: { ar: 'قدّم نفسك في 3 إلى 4 أسطر', fr: 'Présentez-vous en 3 à 4 lignes', en: 'Introduce yourself in 3–4 lines' } }
            ] },
            { id: 'exp', title: { ar: 'الخبرة المهنية', fr: 'Expérience professionnelle', en: 'Work experience' }, repeater: 'experience',
              addLabel: { ar: 'إضافة خبرة', fr: 'Ajouter une expérience', en: 'Add experience' }, newItem: { role: '', company: '', city: '', start: '', end: '', current: false, missions: '' },
              itemFields: [
                { k: 'role', label: { ar: 'المنصب', fr: 'Poste', en: 'Role' } },
                { k: 'company', half: true, label: { ar: 'الشركة', fr: 'Entreprise', en: 'Company' } },
                { k: 'city', half: true, label: { ar: 'المدينة', fr: 'Ville', en: 'City' } }
              ].concat(dates, [
                { k: 'current', type: 'checkbox', label: { ar: 'منصبي الحالي', fr: 'Poste actuel', en: 'Current role' } },
                { k: 'missions', type: 'textarea', label: { ar: 'المهام (مهمة في كل سطر)', fr: 'Missions (une par ligne)', en: 'Tasks (one per line)' } }
              ]) },
            { id: 'edu', title: { ar: 'التكوين', fr: 'Formation', en: 'Education' }, repeater: 'education',
              addLabel: { ar: 'إضافة تكوين', fr: 'Ajouter une formation', en: 'Add education' }, newItem: { degree: '', school: '', city: '', start: '', end: '', details: '' },
              itemFields: [
                { k: 'degree', label: { ar: 'الشهادة / الدبلوم', fr: 'Diplôme', en: 'Degree' } },
                { k: 'school', half: true, label: { ar: 'المؤسسة', fr: 'Établissement', en: 'School' } },
                { k: 'city', half: true, label: { ar: 'المدينة', fr: 'Ville', en: 'City' } }
              ].concat(dates, [{ k: 'details', type: 'textarea', rows: 2, label: { ar: 'تفاصيل (اختياري)', fr: 'Détails (facultatif)', en: 'Details (optional)' } }]) },
            { id: 'skills', title: { ar: 'المهارات', fr: 'Compétences', en: 'Skills' }, repeater: 'skills',
              addLabel: { ar: 'إضافة مهارة', fr: 'Ajouter une compétence', en: 'Add skill' }, newItem: { name: '', level: 3 },
              itemFields: [
                { k: 'name', half: true, label: { ar: 'المهارة', fr: 'Compétence', en: 'Skill' } },
                { k: 'level', type: 'range', min: 1, max: 5, half: true, label: { ar: 'المستوى', fr: 'Niveau', en: 'Level' } }
              ] },
            { id: 'langs', title: { ar: 'اللغات', fr: 'Langues', en: 'Languages' }, repeater: 'languages',
              addLabel: { ar: 'إضافة لغة', fr: 'Ajouter une langue', en: 'Add language' }, newItem: { name: '', level: 'B1' },
              itemFields: [
                { k: 'name', half: true, label: { ar: 'اللغة', fr: 'Langue', en: 'Language' } },
                { k: 'level', type: 'select', half: true, options: LEVELS, label: { ar: 'المستوى', fr: 'Niveau', en: 'Level' } }
              ] },
            { id: 'certs', title: { ar: 'الشهادات', fr: 'Certifications', en: 'Certifications' }, repeater: 'certifications',
              addLabel: { ar: 'إضافة شهادة', fr: 'Ajouter une certification', en: 'Add certification' }, newItem: { name: '', org: '', date: '' },
              itemFields: [
                { k: 'name', label: { ar: 'اسم الشهادة', fr: 'Intitulé', en: 'Name' } },
                { k: 'org', half: true, label: { ar: 'الجهة', fr: 'Organisme', en: 'Issuer' } },
                { k: 'date', half: true, label: { ar: 'السنة', fr: 'Année', en: 'Year' } }
              ] },
            { id: 'projects', title: { ar: 'المشاريع', fr: 'Projets', en: 'Projects' }, repeater: 'projects',
              addLabel: { ar: 'إضافة مشروع', fr: 'Ajouter un projet', en: 'Add project' }, newItem: { name: '', link: '', desc: '' },
              itemFields: [
                { k: 'name', half: true, label: { ar: 'اسم المشروع', fr: 'Nom du projet', en: 'Project name' } },
                { k: 'link', half: true, dir: 'ltr', label: { ar: 'الرابط', fr: 'Lien', en: 'Link' } },
                { k: 'desc', type: 'textarea', rows: 2, label: { ar: 'الوصف', fr: 'Description', en: 'Description' } }
              ] },
            { id: 'refs', title: { ar: 'المراجع', fr: 'Références', en: 'References' }, repeater: 'references',
              addLabel: { ar: 'إضافة مرجع', fr: 'Ajouter une référence', en: 'Add reference' }, newItem: { name: '', role: '', contact: '' },
              itemFields: [
                { k: 'name', half: true, label: { ar: 'الاسم', fr: 'Nom', en: 'Name' } },
                { k: 'role', half: true, label: { ar: 'المنصب / الشركة', fr: 'Poste / Entreprise', en: 'Role / Company' } },
                { k: 'contact', dir: 'ltr', label: { ar: 'الهاتف أو البريد', fr: 'Téléphone ou e-mail', en: 'Phone or email' } }
              ] },
            { id: 'interests', title: { ar: 'الاهتمامات', fr: 'Centres d’intérêt', en: 'Interests' }, fields: [
                { k: 'interests', label: { ar: 'افصل بينها بفواصل', fr: 'Séparez-les par des virgules', en: 'Separate with commas' }, placeholder: { ar: 'القراءة، الرياضة، السفر', fr: 'Lecture, Football, Voyages', en: 'Reading, Football, Travel' } }
            ] }
        ];
    }
    function designSchema() {
        return [{ id: 'design', open: true, title: { ar: 'المظهر', fr: 'Apparence', en: 'Appearance' }, fields: [
            { k: 'template', type: 'select', label: { ar: 'القالب', fr: 'Modèle', en: 'Template' }, options: TEMPLATES.map(function (t) { return { v: t.v, label: t.name }; }) },
            { k: 'color', type: 'color', half: true, label: { ar: 'اللون الأساسي', fr: 'Couleur principale', en: 'Main color' } },
            { k: 'color2', type: 'color', half: true, label: { ar: 'اللون الثانوي', fr: 'Couleur secondaire', en: 'Secondary color' } },
            { k: 'font', type: 'select', half: true, label: { ar: 'الخط', fr: 'Police', en: 'Font' }, options: [{ v: 'default', label: 'Inter / Tajawal' }, { v: 'tajawal', label: 'Tajawal' }, { v: 'serif', label: 'Serif' }] },
            { k: 'size', type: 'select', half: true, label: { ar: 'حجم النص', fr: 'Taille du texte', en: 'Text size' }, options: [{ v: 's', label: { ar: 'صغير', fr: 'Petit', en: 'Small' } }, { v: 'm', label: { ar: 'متوسط', fr: 'Moyen', en: 'Medium' } }, { v: 'l', label: { ar: 'كبير', fr: 'Grand', en: 'Large' } }] },
            { k: 'photo', type: 'select', half: true, label: { ar: 'شكل الصورة', fr: 'Forme de la photo', en: 'Photo shape' }, options: [{ v: 'circle', label: { ar: 'دائرية', fr: 'Ronde', en: 'Round' } }, { v: 'square', label: { ar: 'مربعة', fr: 'Carrée', en: 'Square' } }, { v: 'none', label: { ar: 'بدون صورة', fr: 'Sans photo', en: 'No photo' } }] },
            { k: 'levels', type: 'checkbox', label: { ar: 'إظهار مستوى المهارات واللغات بأشرطة', fr: 'Afficher les niveaux en barres', en: 'Show levels as bars' } }
        ] }];
    }

    /* ---------------- Views ---------------- */
    function setupView() {
        var langOpts = LANGS.map(function (l) {
            return '<button type="button" class="tk-option" data-lang="' + l.v + '" aria-pressed="' + (draft.lang === l.v) + '"><span class="tk-badge">' + l.b + '</span><span><strong>' + l.name + '</strong><small>' + esc(L(l.sub)) + '</small></span></button>';
        }).join('');
        root.innerHTML = '<div class="tk-wizard">'
            + '<section class="tk-step"><h2><span class="tk-num">1</span>' + esc(L(UI.step1)) + '</h2><div class="tk-options">' + langOpts + '</div></section>'
            + '<section class="tk-step"><h2><span class="tk-num">2</span>' + esc(L(UI.step2)) + '</h2><div class="tk-filters">'
            + ['all', 'sidebar', 'header', 'simple'].map(function (f) { return '<button type="button" class="chip' + (draft.filter === f ? ' is-active' : '') + '" data-filter="' + f + '">' + esc(L(UI[f])) + '</button>'; }).join('')
            + '</div><div class="tk-gallery" id="tk-gallery"></div></section>'
            + '<div class="tk-bar"><span class="small" id="tk-summary"></span><div class="row">'
            + (state ? '<button type="button" class="btn btn--outline" id="tk-resume">' + esc(L(UI.resume)) + '</button>' : '')
            + '<button type="button" class="btn btn--primary btn--lg" id="tk-start">' + esc(L(UI.start)) + '</button></div></div></div>';
        var g = document.getElementById('tk-gallery');
        var demoData = Object.assign(blankData(), sample(draft.lang));
        TEMPLATES.filter(function (t) { return draft.filter === 'all' || t.kind === draft.filter; }).forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'tk-tpl'; b.setAttribute('aria-pressed', draft.template === t.v);
            b.innerHTML = '<div class="tk-thumb">' + renderDoc({ lang: draft.lang, template: t.v, design: design(t.v), data: demoData }) + '</div><span>' + esc(L(t.name)) + '</span>';
            b.onclick = function () { draft.template = t.v; setupView(); };
            g.appendChild(b);
            var thumb = b.querySelector('.tk-thumb');
            requestAnimationFrame(function () { thumb.firstElementChild.style.transform = 'scale(' + thumb.clientWidth / 794 + ')'; });
        });
        root.querySelectorAll('[data-lang]').forEach(function (b) { b.onclick = function () { draft.lang = b.dataset.lang; setupView(); }; });
        root.querySelectorAll('[data-filter]').forEach(function (b) { b.onclick = function () { draft.filter = b.dataset.filter; setupView(); }; });
        document.getElementById('tk-start').onclick = function () {
            var keepDesign = state && state.template === draft.template ? state.design : design(draft.template);
            state = { v: 2, lang: draft.lang, template: draft.template, design: keepDesign, data: state ? state.data : blankData() };
            state.design.template = draft.template;
            STORE.save(state); editorView();
        };
        var resume = document.getElementById('tk-resume');
        if (resume) resume.onclick = editorView;
        document.getElementById('tk-summary').textContent = LANGS.filter(function (l) { return l.v === draft.lang; })[0].name + ' · ' + L(tplOf(draft.template).name);
    }

    function editorView() {
        if (!state) { setupView(); return; }
        state.design.template = state.template;
        root.innerHTML = '<div class="tk-toolbar no-print"><div class="row">'
            + '<button type="button" class="btn btn--ghost btn--sm" id="tk-back">✎ ' + esc(L(UI.back)) + '</button></div>'
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
            frame.innerHTML = renderDoc(state); fit();
            document.getElementById('tk-status').textContent = LANGS.filter(function (l) { return l.v === state.lang; })[0].name + ' · ' + L(tplOf(state.template).name);
        }
        var repaint = K.debounce(paint, 120);
        function onChange(key) {
            if (key === 'template') {
                state.template = state.design.template;
                var t = tplOf(state.template);
                state.design.color = t.color; state.design.color2 = t.color2;
                drawForm();
            }
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
        document.getElementById('tk-back').onclick = function () { draft = { lang: state.lang, template: state.template, filter: 'all' }; setupView(); window.scrollTo(0, 0); };
        document.getElementById('tk-sample').onclick = function () { var photo = state.data.photo; state.data = Object.assign(blankData(), sample(state.lang), { photo: photo }); save(); drawForm(); paint(); };
        document.getElementById('tk-clear').onclick = function () { if (confirm(L(UI.confirmClear))) { state.data = blankData(); save(); drawForm(); paint(); } };
        document.getElementById('tk-pdf').onclick = document.getElementById('tk-pdf2').onclick = function () {
            var prev = document.title; document.title = ('CV ' + (state.data.name || '')).trim().replace(/\s+/g, '_'); window.print(); document.title = prev;
        };
        drawForm(); paint();
        window.scrollTo(0, Math.max(0, root.getBoundingClientRect().top + window.scrollY - 80));
    }

    setupView();
})();
