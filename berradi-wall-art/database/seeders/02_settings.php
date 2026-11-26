<?php

/*
 * Default settings. Never overwrites a value that already exists (safe to re-run).
 */

use App\Core\Database;

return static function (Database $db): void {
    $defaults = [
        // company
        ['company_name', 'BERRADI WALL ART', 'company', 'string'],
        ['company_legal_name', '', 'company', 'string'],
        ['company_logo', '', 'company', 'file'],
        ['company_email', '', 'company', 'string'],
        ['company_phone', '', 'company', 'string'],
        ['company_whatsapp', '', 'company', 'string'],
        ['company_address', '', 'company', 'text'],
        ['company_city', '', 'company', 'string'],
        ['company_country', 'MA', 'company', 'string'],
        ['company_ice', '', 'company', 'string'],
        ['company_rc', '', 'company', 'string'],
        ['company_if', '', 'company', 'string'],
        ['company_patente', '', 'company', 'string'],
        ['company_cnss', '', 'company', 'string'],
        ['company_bank_name', '', 'company', 'string'],
        ['company_rib', '', 'company', 'string'],
        ['company_website', '', 'company', 'string'],
        ['social_instagram', '', 'company', 'string'],
        ['social_facebook', '', 'company', 'string'],
        ['social_tiktok', '', 'company', 'string'],

        // localization
        ['default_language', 'fr', 'localization', 'string'],
        ['currency_code', 'MAD', 'localization', 'string'],
        ['currency_symbol', 'DH', 'localization', 'string'],
        ['currency_position', 'after', 'localization', 'string'],

        // taxes
        ['tax_enabled', '1', 'taxes', 'bool'],
        ['tax_default_rate', '20', 'taxes', 'decimal'],
        ['tax_prices_include_tax', '1', 'taxes', 'bool'],
        ['tax_label', 'TVA', 'taxes', 'string'],

        // documents
        ['order_prefix', 'BWA', 'documents', 'string'],
        ['invoice_prefix', 'FAC', 'documents', 'string'],
        ['credit_note_prefix', 'AV', 'documents', 'string'],
        ['quotation_prefix', 'DEV', 'documents', 'string'],
        ['payment_prefix', 'REC', 'documents', 'string'],
        ['purchase_prefix', 'ACH', 'documents', 'string'],
        ['invoice_due_days', '0', 'documents', 'int'],
        ['quotation_validity_days', '15', 'documents', 'int'],
        ['invoice_payment_terms', 'Paiement à la livraison ou par virement bancaire.', 'documents', 'text'],
        ['quotation_terms', "Devis valable pour la durée indiquée. Un acompte de 50% est demandé pour lancer la production des créations personnalisées.", 'documents', 'text'],
        ['invoice_footer_note', '', 'documents', 'text'],

        // shipping
        ['shipping_flat_rate', '40.00', 'shipping', 'decimal'],
        ['shipping_free_threshold', '1000.00', 'shipping', 'decimal'],
        ['shipping_min_days', '2', 'shipping', 'int'],
        ['shipping_max_days', '5', 'shipping', 'int'],
        ['production_days', '3', 'shipping', 'int'],

        // orders
        ['order_cancel_allowed_statuses', '["new","awaiting_confirmation","confirmed"]', 'orders', 'json'],
        ['guest_checkout_enabled', '1', 'orders', 'bool'],
        ['low_dpi_warning', '100', 'orders', 'int'],
        ['min_dpi_block', '50', 'orders', 'int'],

        // uploads & privacy
        ['upload_max_mb', '25', 'uploads', 'int'],
        ['upload_max_files', '10', 'uploads', 'int'],
        ['retention_unordered_days', '30', 'uploads', 'int'],
        ['retention_ordered_days', '365', 'uploads', 'int'],

        // mail
        ['mail_driver', 'mail', 'mail', 'string'],
        ['mail_host', '', 'mail', 'string'],
        ['mail_port', '465', 'mail', 'int'],
        ['mail_encryption', 'ssl', 'mail', 'string'],
        ['mail_username', '', 'mail', 'string'],
        ['mail_password', '', 'mail', 'encrypted'],
        ['mail_from_address', '', 'mail', 'string'],
        ['mail_from_name', 'BERRADI WALL ART', 'mail', 'string'],
        ['admin_notification_email', '', 'mail', 'string'],

        // seo
        ['seo_meta_title', 'BERRADI WALL ART — Tableaux & Toiles Personnalisés au Maroc', 'seo', 'string'],
        ['seo_meta_description', 'Transformez vos souvenirs en œuvres d’art : toiles, cadres et posters personnalisés imprimés au Maroc.', 'seo', 'text'],
        ['seo_og_image', '', 'seo', 'file'],
        ['maintenance_mode', '0', 'general', 'bool'],
    ];

    foreach ($defaults as [$key, $value, $group, $type]) {
        $db->query(
            'INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, value_type) VALUES (?, ?, ?, ?)',
            [$key, $value, $group, $type]
        );
    }
};
