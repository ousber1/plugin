<?php

/*
 * Default business settings. Every value here can be overridden from
 * Admin → Paramètres (stored in the `settings` table); these defaults are
 * only used until the owner saves their own values.
 */
return [
    'defaults' => [
        'brand.name' => 'HO CREATIVE',
        'brand.tagline' => ['fr' => 'Impression professionnelle au Maroc', 'ar' => 'طباعة احترافية في المغرب'],
        'brand.logo' => null,          // public path, null = built-in SVG logo
        'brand.primary_color' => '#1B1F3B',
        'brand.accent_color' => '#E4007C',

        'company.legal_name' => 'HO CREATIVE (à compléter)',
        'company.address' => 'Adresse à compléter, Maroc',
        'company.city' => 'Casablanca',
        'company.phone' => '',
        'company.email' => '',
        'company.ice' => '',
        'company.rc' => '',
        'company.if' => '',
        'company.patente' => '',
        'company.cnss' => '',
        'company.bank_name' => '',
        'company.rib' => '',
        'company.document_footer' => ['fr' => '', 'ar' => ''],

        'contact.whatsapp' => '',     // international format without +, e.g. 2126XXXXXXXX
        'contact.phone' => '',
        'contact.email' => '',
        'contact.help_url' => '/page/aide',

        'currency' => 'MAD',
        'tax.enabled' => true,
        'tax.rate' => '20.00',        // TVA standard rate – verify with your accountant
        'tax.shipping_taxable' => true,
        'tax.label' => ['fr' => 'TVA', 'ar' => 'الضريبة على القيمة المضافة'],

        'checkout.guest_enabled' => true,
        'checkout.deposit_percent' => '50', // suggested deposit for staff orders / bank transfer
        'payments.bank_transfer' => true,
        'payments.in_store' => true,
        'payments.cod' => true,
        'payments.online' => false,
        'payments.online_provider' => 'cmi',

        'numbering.quote' => 'DV-{YYYY}-{SEQ:5}',
        'numbering.order' => 'CMD-{YYYY}-{SEQ:5}',
        'numbering.proforma' => 'PF-{YYYY}-{SEQ:5}',
        'numbering.invoice' => 'FA-{YYYY}-{SEQ:5}',
        'numbering.credit_note' => 'AV-{YYYY}-{SEQ:5}',
        'numbering.purchase_order' => 'BC-{YYYY}-{SEQ:5}',
        'numbering.delivery_note' => 'BL-{YYYY}-{SEQ:5}',
        'numbering.receipt' => 'RC-{YYYY}-{SEQ:5}',
        'numbering.job' => 'JOB-{YYYY}-{SEQ:5}',
        'numbering.ticket' => 'TK-{YYYY}-{SEQ:6}',

        'retail.registers' => ['Caisse 1'],
        'retail.allow_negative_stock' => false,
        'retail.receipt_footer' => ['fr' => 'Merci de votre visite ! Échange sous 7 jours avec ce ticket.', 'ar' => 'شكراً على زيارتكم! الاستبدال خلال 7 أيام مع هذا الوصل.'],
        'retail.default_tax_rate' => '20.00',

        'quotes.validity_days' => 30,

        'hours.working_days' => [1, 2, 3, 4, 5, 6], // ISO: 1=Mon … 7=Sun
        'hours.cutoff' => '13:00',
        'hours.text' => ['fr' => 'Lun–Sam 9h–19h', 'ar' => 'الاثنين–السبت 9–19'],

        'uploads.max_mb' => 100,
        'uploads.allowed' => ['pdf', 'png', 'jpg', 'jpeg'],
        'uploads.min_dpi' => 250,
        'retention.artwork_days' => 365,  // purge artwork of completed orders after N days (0 = never)
        'retention.cart_days' => 30,

        'seo.title' => ['fr' => 'HO CREATIVE – Imprimerie en ligne au Maroc', 'ar' => 'HO CREATIVE – مطبعة عبر الإنترنت في المغرب'],
        'seo.description' => ['fr' => 'Cartes de visite, flyers, packaging, bâches et PLV imprimés au Maroc. Prix instantanés, BAT en ligne, livraison partout au Maroc.', 'ar' => 'بطاقات الأعمال، المطويات، التغليف واللافتات تطبع في المغرب. أسعار فورية وتوصيل لجميع المدن.'],
    ],

    'locales' => ['fr' => 'Français', 'ar' => 'العربية'],

    'order_channels' => ['web', 'walk_in', 'phone', 'whatsapp', 'b2b', 'email'],

    'payment_methods' => ['bank_transfer', 'in_store', 'cash', 'card_in_store', 'cheque', 'cod', 'online'],

    'retail_payment_methods' => ['cash', 'card_in_store', 'cheque', 'bank_transfer'],

    'production_stages' => [
        'artwork_pending', 'prepress_review', 'proof_pending', 'approved', 'printing',
        'finishing', 'quality_control', 'packing', 'ready_for_pickup', 'shipped', 'delivered', 'cancelled',
    ],

    'material_units' => ['sheet', 'roll', 'm', 'm2', 'piece', 'l', 'kg'],

    'roles' => [
        'owner' => ['*'],
        'cashier' => ['retail.sell', 'retail.cash', 'customers.manage'],
        'sales' => ['dashboard.view', 'retail.sell', 'retail.cash', 'retail.view', 'retail.discount', 'retail.refund', 'customers.manage', 'quotes.manage', 'orders.manage', 'orders.discount', 'payments.record', 'documents.issue', 'delivery.manage', 'production.view', 'catalog.view', 'reviews.manage'],
        'designer' => ['dashboard.view', 'orders.view', 'artwork.manage', 'proofs.manage', 'production.view', 'templates.manage', 'catalog.view'],
        'production' => ['dashboard.view', 'orders.view', 'production.manage', 'stock.consume', 'production.view', 'catalog.view'],
        'stock' => ['dashboard.view', 'retail.manage', 'retail.view', 'stock.manage', 'stock.consume', 'suppliers.manage', 'purchasing.manage', 'production.view'],
        'dispatch' => ['dashboard.view', 'orders.view', 'delivery.manage', 'production.view'],
        'accounting' => ['dashboard.view', 'retail.view', 'retail.refund', 'finance.view', 'orders.view', 'payments.record', 'payments.refund', 'documents.issue', 'expenses.manage', 'reports.view', 'customers.manage', 'purchasing.manage', 'suppliers.manage'],
    ],

    'permissions' => [
        'dashboard.view', 'finance.view', 'catalog.view', 'catalog.manage', 'pricing.manage', 'templates.manage', 'content.manage', 'reviews.manage',
        'customers.manage', 'quotes.manage', 'orders.view', 'orders.manage', 'orders.discount', 'payments.record', 'payments.refund', 'documents.issue',
        'artwork.manage', 'proofs.manage', 'production.view', 'production.manage', 'stock.manage', 'stock.consume', 'suppliers.manage', 'purchasing.manage',
        'expenses.manage', 'delivery.manage', 'reports.view', 'staff.manage', 'settings.manage', 'audit.view',
        'retail.sell', 'retail.view', 'retail.manage', 'retail.discount', 'retail.refund', 'retail.cash',
    ],
];
