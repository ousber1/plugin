<?php
/**
 * Activation du plugin
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Activator {

    /**
     * Actions d'activation
     */
    public static function activate() {
        self::create_tables();
        self::create_options();
        self::create_upload_directory();
        self::create_pages();

        // Enregistrer les CPT et flush les rewrite rules
        IPM_Post_Types::register();
        flush_rewrite_rules();

        // Marquer comme activé
        update_option( 'ipm_version', IPM_VERSION );
        update_option( 'ipm_activated', true );
    }

    /**
     * Créer les tables personnalisées
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table des fichiers clients
        $table_files = $wpdb->prefix . 'ipm_client_files';
        $sql_files = "CREATE TABLE IF NOT EXISTS $table_files (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned DEFAULT NULL,
            quote_id bigint(20) unsigned DEFAULT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_type varchar(50) NOT NULL,
            file_size bigint(20) unsigned NOT NULL DEFAULT 0,
            notes text,
            status varchar(50) NOT NULL DEFAULT 'uploaded',
            uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY customer_id (customer_id),
            KEY quote_id (quote_id)
        ) $charset_collate;";

        // Table des tarifs de livraison par zone
        $table_shipping = $wpdb->prefix . 'ipm_shipping_zones';
        $sql_shipping = "CREATE TABLE IF NOT EXISTS $table_shipping (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            zone_name varchar(255) NOT NULL,
            cities text NOT NULL,
            standard_price decimal(10,2) NOT NULL DEFAULT 0,
            express_price decimal(10,2) NOT NULL DEFAULT 0,
            standard_days int(11) NOT NULL DEFAULT 3,
            express_days int(11) NOT NULL DEFAULT 1,
            free_threshold decimal(10,2) DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Table des remises volume
        $table_volume = $wpdb->prefix . 'ipm_volume_discounts';
        $sql_volume = "CREATE TABLE IF NOT EXISTS $table_volume (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            min_quantity int(11) NOT NULL,
            max_quantity int(11) DEFAULT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percentage',
            discount_value decimal(10,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY product_id (product_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_files );
        dbDelta( $sql_shipping );
        dbDelta( $sql_volume );

        // Insérer les zones de livraison par défaut
        self::insert_default_shipping_zones();
    }

    /**
     * Insérer les zones de livraison par défaut
     */
    private static function insert_default_shipping_zones() {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_shipping_zones';

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        if ( $count > 0 ) {
            return;
        }

        $zones = array(
            array(
                'zone_name'      => 'Casablanca',
                'cities'         => 'Casablanca,Mohammedia,Ain Sebaa,Sidi Bernoussi',
                'standard_price' => 25.00,
                'express_price'  => 50.00,
                'standard_days'  => 2,
                'express_days'   => 1,
                'free_threshold' => 500.00,
            ),
            array(
                'zone_name'      => 'Rabat-Salé-Kénitra',
                'cities'         => 'Rabat,Salé,Kénitra,Témara,Skhirat',
                'standard_price' => 35.00,
                'express_price'  => 65.00,
                'standard_days'  => 2,
                'express_days'   => 1,
                'free_threshold' => 700.00,
            ),
            array(
                'zone_name'      => 'Marrakech-Fès-Tanger',
                'cities'         => 'Marrakech,Fès,Tanger,Meknès,Tétouan',
                'standard_price' => 45.00,
                'express_price'  => 80.00,
                'standard_days'  => 3,
                'express_days'   => 1,
                'free_threshold' => 1000.00,
            ),
            array(
                'zone_name'      => 'Autres villes',
                'cities'         => 'Agadir,Oujda,Nador,Beni Mellal,El Jadida,Settat,Khouribga,Laâyoune,Dakhla',
                'standard_price' => 55.00,
                'express_price'  => 95.00,
                'standard_days'  => 4,
                'express_days'   => 2,
                'free_threshold' => 1200.00,
            ),
        );

        foreach ( $zones as $zone ) {
            $wpdb->insert( $table, $zone );
        }
    }

    /**
     * Créer les options par défaut
     */
    private static function create_options() {
        $defaults = array(
            'currency'           => 'MAD',
            'whatsapp_number'    => '',
            'whatsapp_message'   => 'Bonjour, je suis intéressé(e) par {product}. Pouvez-vous me donner plus d\'informations ?',
            'max_file_size'      => 50, // MB
            'allowed_file_types' => 'pdf,png,jpg,jpeg,ai,psd,svg,zip',
            'default_delay'      => '3-5 jours ouvrables',
            'notification_email' => get_option( 'admin_email' ),
            'upload_help_text'   => 'Formats acceptés : PDF, PNG, JPG, AI, PSD, SVG, ZIP. Taille maximale : 50 Mo.',
            'terms_url'          => '',
            'store_pickup'       => true,
            'store_address'      => '',
            'minimum_price'      => 50,
            'enable_quotes'      => true,
            'enable_file_upload' => true,
            'auto_emails'        => true,
        );

        if ( ! get_option( 'ipm_settings' ) ) {
            update_option( 'ipm_settings', $defaults );
        }
    }

    /**
     * Créer le dossier d'upload sécurisé
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $ipm_dir    = $upload_dir['basedir'] . '/ipm-files';

        if ( ! file_exists( $ipm_dir ) ) {
            wp_mkdir_p( $ipm_dir );

            // Fichier .htaccess pour sécuriser
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch '\\.(php|php5|phtml|cgi|pl|py|sh|bash)$'>\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";

            file_put_contents( $ipm_dir . '/.htaccess', $htaccess_content );

            // Fichier index.php vide
            file_put_contents( $ipm_dir . '/index.php', '<?php // Silence is golden' );
        }
    }

    /**
     * Créer les pages du plugin
     */
    private static function create_pages() {
        $pages = array(
            'boutique-impression' => array(
                'title'   => 'Boutique Impression',
                'content' => '[ipm_shop]',
            ),
            'demande-de-devis' => array(
                'title'   => 'Demande de devis',
                'content' => '[ipm_quote_form]',
            ),
            'suivi-commande' => array(
                'title'   => 'Suivi de commande',
                'content' => '[ipm_order_tracking]',
            ),
            'upload-fichier' => array(
                'title'   => 'Envoyer votre fichier',
                'content' => '[ipm_file_upload]',
            ),
            'calculateur-prix' => array(
                'title'   => 'Calculateur de prix',
                'content' => '[ipm_price_calculator]',
            ),
        );

        foreach ( $pages as $slug => $page ) {
            $existing = get_page_by_path( $slug );
            if ( ! $existing ) {
                wp_insert_post( array(
                    'post_title'   => $page['title'],
                    'post_name'    => $slug,
                    'post_content' => $page['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ) );
            }
        }
    }
}
