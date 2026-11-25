<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Définition déclarative des onglets de paramètres.
 * Chaque champ : [libellé, type, options/aide]. Le contrôleur génère le
 * formulaire, valide et enregistre à partir de cette seule définition.
 *
 * Types : text, textarea, email, url, phone, int, decimal, bool, select, color, password, image
 */
final class SettingsSchema
{
    public static function tabs(): array
    {
        return [
            'business' => ['Entreprise', 'bi-building'],
            'currency' => ['Devise & TVA', 'bi-cash-coin'],
            'documents' => ['Documents', 'bi-file-earmark-text'],
            'numbering' => ['Numérotation', 'bi-123'],
            'pos' => ['Point de vente', 'bi-cart3'],
            'stock' => ['Stock & production', 'bi-box-seam'],
            'pdf' => ['Impression & PDF', 'bi-printer'],
            'appearance' => ['Apparence', 'bi-palette'],
            'notifications' => ['Notifications', 'bi-bell'],
            'mail' => ['E-mail', 'bi-envelope'],
            'security' => ['Sécurité & système', 'bi-shield-lock'],
        ];
    }

    /** Champs par onglet. La clé du groupe en base est indiquée par 'group'. */
    public static function fields(string $tab): array
    {
        return match ($tab) {
            'business' => [
                'business_logo'     => ['Logo', 'image', ['help' => 'PNG, JPG ou WEBP, 2 Mo max. Fond transparent recommandé.', 'group' => 'business']],
                'business_name'     => ['Raison sociale', 'text', ['required' => true, 'max' => 150, 'group' => 'business']],
                'business_activity' => ['Activité', 'text', ['max' => 190, 'group' => 'business']],
                'business_address'  => ['Adresse', 'text', ['max' => 255, 'group' => 'business', 'col' => 8]],
                'business_city'     => ['Ville', 'text', ['max' => 100, 'group' => 'business', 'col' => 4]],
                'business_country'  => ['Pays', 'text', ['max' => 60, 'group' => 'business', 'col' => 4]],
                'business_phone'    => ['Téléphone', 'phone', ['group' => 'business', 'col' => 4]],
                'business_whatsapp' => ['WhatsApp', 'phone', ['group' => 'business', 'col' => 4]],
                'business_email'    => ['E-mail', 'email', ['group' => 'business']],
                'business_website'  => ['Site web', 'url', ['group' => 'business']],
                'business_ice'      => ['ICE', 'text', ['pattern' => '/^\d{15}$/', 'pattern_msg' => 'L\'ICE doit contenir exactement 15 chiffres.', 'group' => 'business', 'col' => 4]],
                'business_if'       => ['Identifiant fiscal (IF)', 'text', ['max' => 20, 'group' => 'business', 'col' => 4]],
                'business_rc'       => ['Registre de commerce (RC)', 'text', ['max' => 30, 'group' => 'business', 'col' => 4]],
                'business_patente'  => ['Patente / Taxe professionnelle', 'text', ['max' => 30, 'group' => 'business', 'col' => 4]],
                'business_cnss'     => ['CNSS', 'text', ['max' => 30, 'group' => 'business', 'col' => 4]],
                'business_capital'  => ['Capital social', 'text', ['max' => 50, 'group' => 'business', 'col' => 4]],
                'bank_name'         => ['Banque', 'text', ['max' => 100, 'group' => 'business', 'col' => 4]],
                'bank_rib'          => ['RIB', 'text', ['pattern' => '/^[0-9 ]{24,30}$/', 'pattern_msg' => 'Le RIB doit comporter 24 chiffres.', 'group' => 'business', 'col' => 5]],
                'bank_swift'        => ['Code SWIFT', 'text', ['max' => 15, 'group' => 'business', 'col' => 3]],
            ],
            'currency' => [
                'currency_code'       => ['Code devise', 'text', ['required' => true, 'pattern' => '/^[A-Z]{3}$/', 'pattern_msg' => 'Code devise : 3 lettres majuscules.', 'group' => 'currency', 'col' => 3]],
                'currency_symbol'     => ['Symbole', 'text', ['required' => true, 'max' => 5, 'group' => 'currency', 'col' => 3]],
                'currency_position'   => ['Position du symbole', 'select', ['options' => ['after' => 'Après le montant', 'before' => 'Avant le montant'], 'group' => 'currency', 'col' => 3]],
                'currency_decimals'   => ['Décimales', 'select', ['options' => ['0' => '0', '2' => '2', '3' => '3'], 'group' => 'currency', 'col' => 3]],
                'decimal_separator'   => ['Séparateur décimal', 'select', ['options' => [',' => 'Virgule (1 250,50)', '.' => 'Point (1 250.50)'], 'group' => 'currency', 'col' => 6]],
                'thousands_separator' => ['Séparateur de milliers', 'select', ['options' => [' ' => 'Espace (1 250)', '.' => 'Point (1.250)', ',' => 'Virgule (1,250)', '' => 'Aucun (1250)'], 'group' => 'currency', 'col' => 6]],
                'tax_enabled'         => ['Entreprise assujettie à la TVA', 'bool', ['group' => 'tax']],
                'prices_include_tax'  => ['Les prix du catalogue sont saisis TTC', 'bool', ['group' => 'tax']],
            ],
            'documents' => [
                'quotation_validity_days'  => ['Validité des devis (jours)', 'int', ['min' => 0, 'max' => 365, 'group' => 'documents', 'col' => 4]],
                'invoice_due_days'         => ['Échéance des factures (jours)', 'int', ['min' => 0, 'max' => 365, 'group' => 'documents', 'col' => 4]],
                'default_discount_percent' => ['Remise par défaut (%)', 'decimal', ['min' => 0, 'max' => 100, 'group' => 'documents', 'col' => 4]],
                'default_payment_terms'    => ['Conditions de paiement par défaut', 'textarea', ['max' => 1000, 'group' => 'documents']],
                'quotation_footer'         => ['Mentions en pied de devis', 'textarea', ['max' => 1000, 'group' => 'documents']],
                'invoice_footer'           => ['Mentions en pied de facture', 'textarea', ['max' => 1000, 'group' => 'documents']],
                'amount_in_words'          => ['Afficher le montant en lettres sur les factures', 'bool', ['group' => 'documents']],
            ],
            'pos' => [
                'require_cash_session'   => ['Exiger une caisse ouverte pour vendre', 'bool', ['group' => 'pos']],
                'auto_print_receipt'     => ['Imprimer le ticket automatiquement après la vente', 'bool', ['group' => 'pos']],
                'allow_negative_stock'   => ['Autoriser la vente avec un stock insuffisant', 'bool', ['group' => 'pos']],
                'receipt_width'          => ['Largeur du ticket', 'select', ['options' => ['58' => '58 mm', '80' => '80 mm'], 'group' => 'pos', 'col' => 6]],
                'max_discount_percent'   => ['Remise maximale sans autorisation (%)', 'decimal', ['min' => 0, 'max' => 100, 'group' => 'pos', 'col' => 6]],
                'receipt_header'         => ['En-tête du ticket', 'textarea', ['max' => 500, 'group' => 'pos']],
                'receipt_footer'         => ['Pied du ticket', 'textarea', ['max' => 500, 'group' => 'pos']],
                'loyalty_enabled'        => ['Programme de fidélité actif', 'bool', ['group' => 'loyalty']],
                'points_per_amount'      => ['Points gagnés par tranche de 100 DH', 'decimal', ['min' => 0, 'max' => 1000, 'group' => 'loyalty', 'col' => 6]],
            ],
            'stock' => [
                'auto_deduct_sales'       => ['Déduire automatiquement le stock lors des ventes', 'bool', ['group' => 'stock']],
                'auto_deduct_production'  => ['Déduire les matières lors de la production', 'bool', ['group' => 'stock']],
                'low_stock_alerts'        => ['Alertes de stock faible', 'bool', ['group' => 'stock']],
                'valuation_method'        => ['Valorisation du stock', 'select', ['options' => ['weighted_average' => 'Coût moyen pondéré (CMUP)', 'last_cost' => 'Dernier prix d\'achat'], 'group' => 'stock', 'col' => 6]],
                'default_deadline_days'   => ['Délai de production par défaut (jours)', 'int', ['min' => 0, 'max' => 90, 'group' => 'production', 'col' => 6]],
                'auto_create_from_sale'   => ['Créer un ordre de production pour chaque vente d\'impression', 'bool', ['group' => 'production']],
            ],
            'pdf' => [
                'paper_size'      => ['Format papier', 'select', ['options' => ['A4' => 'A4', 'A5' => 'A5', 'Letter' => 'Letter'], 'group' => 'pdf', 'col' => 6]],
                'show_qr_code'    => ['QR code de vérification sur les documents', 'bool', ['group' => 'pdf']],
                'show_signature'  => ['Zone de signature', 'bool', ['group' => 'pdf']],
                'show_stamp'      => ['Zone de cachet', 'bool', ['group' => 'pdf']],
                'stamp_image'     => ['Image du cachet', 'image', ['help' => 'PNG transparent recommandé.', 'group' => 'pdf']],
                'signature_image' => ['Image de la signature', 'image', ['help' => 'PNG transparent recommandé.', 'group' => 'pdf']],
            ],
            'appearance' => [
                'default_theme'  => ['Thème par défaut', 'select', ['options' => ['auto' => 'Automatique (système)', 'light' => 'Clair', 'dark' => 'Sombre'], 'group' => 'appearance', 'col' => 6]],
                'accent_color'   => ['Couleur d\'accent', 'color', ['group' => 'appearance', 'col' => 6]],
                'items_per_page' => ['Lignes par page dans les listes', 'select', ['options' => ['10' => '10', '25' => '25', '50' => '50', '100' => '100'], 'group' => 'appearance', 'col' => 6]],
                'date_format'    => ['Format de date', 'select', ['options' => ['d/m/Y' => '31/12/2026', 'd-m-Y' => '31-12-2026', 'Y-m-d' => '2026-12-31'], 'group' => 'system', 'col' => 6]],
            ],
            'mail' => [
                'mail_from_name'    => ['Nom de l\'expéditeur', 'text', ['max' => 100, 'group' => 'mail', 'col' => 6]],
                'mail_from_address' => ['Adresse de l\'expéditeur', 'email', ['group' => 'mail', 'col' => 6]],
                'smtp_host'         => ['Serveur SMTP', 'text', ['max' => 190, 'help' => 'Laisser vide pour utiliser la fonction mail() du serveur.', 'group' => 'mail', 'col' => 6]],
                'smtp_port'         => ['Port', 'int', ['min' => 1, 'max' => 65535, 'group' => 'mail', 'col' => 3]],
                'smtp_encryption'   => ['Chiffrement', 'select', ['options' => ['tls' => 'STARTTLS', 'ssl' => 'SSL', 'none' => 'Aucun'], 'group' => 'mail', 'col' => 3]],
                'smtp_username'     => ['Utilisateur SMTP', 'text', ['max' => 190, 'group' => 'mail', 'col' => 6]],
                'smtp_password'     => ['Mot de passe SMTP', 'password', ['help' => 'Laisser vide pour conserver le mot de passe actuel.', 'group' => 'mail', 'col' => 6]],
            ],
            'security' => [
                'session_lifetime_minutes' => ['Déconnexion après inactivité (minutes)', 'int', ['min' => 5, 'max' => 1440, 'group' => 'security', 'col' => 6]],
                'password_min_length'      => ['Longueur minimale des mots de passe', 'int', ['min' => 8, 'max' => 64, 'group' => 'security', 'col' => 6]],
                'max_login_attempts'       => ['Tentatives de connexion avant verrouillage', 'int', ['min' => 3, 'max' => 20, 'group' => 'security', 'col' => 6]],
                'lockout_minutes'          => ['Durée du verrouillage (minutes)', 'int', ['min' => 1, 'max' => 1440, 'group' => 'security', 'col' => 6]],
                'backup_retention'         => ['Nombre de sauvegardes conservées', 'int', ['min' => 1, 'max' => 100, 'group' => 'backup', 'col' => 6]],
                'auto_backup_enabled'      => ['Sauvegarde automatique (nécessite une tâche cron)', 'bool', ['group' => 'backup']],
                'auto_backup_frequency'    => ['Fréquence de sauvegarde', 'select', ['options' => ['daily' => 'Quotidienne', 'weekly' => 'Hebdomadaire'], 'group' => 'backup', 'col' => 6]],
            ],
            default => [],
        };
    }
}
