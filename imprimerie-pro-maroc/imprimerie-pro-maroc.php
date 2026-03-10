<?php
/**
 * Plugin Name: Imprimerie Pro Maroc
 * Plugin URI: https://imprimerie-pro.ma
 * Description: Plugin WordPress professionnel pour service d'impression en ligne au Maroc. Compatible WooCommerce, gestion des produits d'impression, devis, upload fichiers, calculateur de prix, livraison Maroc, WhatsApp.
 * Version: 1.0.0
 * Author: Imprimerie Pro
 * Author URI: https://imprimerie-pro.ma
 * Text Domain: suspended-pro-maroc
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constantes du plugin
define( 'IPM_VERSION', '1.0.0' );
define( 'IPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'IPM_PLUGIN_FILE', __FILE__ );

/**
 * Classe principale du plugin Imprimerie Pro Maroc
 */
final class Imprimerie_Pro_Maroc {

    /**
     * Instance unique
     *
     * @var Imprimerie_Pro_Maroc
     */
    private static $instance = null;

    /**
     * Retourne l'instance unique du plugin
     *
     * @return Imprimerie_Pro_Maroc
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur privé
     */
    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->register_post_types();
        $this->init_woocommerce();
    }

    /**
     * Chargement des dépendances
     */
    private function load_dependencies() {
        // Classes de base
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-activator.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-deactivator.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-post-types.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-product.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-options.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-price-calculator.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-file-upload.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-quote.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-order-status.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-shipping.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-whatsapp.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-emails.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-shortcodes.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-ajax.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-security.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-woocommerce.php';
        require_once IPM_PLUGIN_DIR . 'includes/class-ipm-customer-area.php';

        // Admin
        require_once IPM_PLUGIN_DIR . 'admin/class-ipm-admin.php';
        require_once IPM_PLUGIN_DIR . 'admin/class-ipm-admin-dashboard.php';
        require_once IPM_PLUGIN_DIR . 'admin/class-ipm-admin-settings.php';
        require_once IPM_PLUGIN_DIR . 'admin/class-ipm-admin-products.php';
        require_once IPM_PLUGIN_DIR . 'admin/class-ipm-admin-quotes.php';
        require_once IPM_PLUGIN_DIR . 'admin/class-ipm-admin-files.php';

        // Public
        require_once IPM_PLUGIN_DIR . 'public/class-ipm-public.php';
    }

    /**
     * Définir la locale
     */
    private function set_locale() {
        add_action( 'plugins_loaded', function() {
            load_plugin_textdomain(
                'suspended-pro-maroc',
                false,
                dirname( IPM_PLUGIN_BASENAME ) . '/languages/'
            );
        });
    }

    /**
     * Hooks admin
     */
    private function define_admin_hooks() {
        if ( is_admin() ) {
            $admin = new IPM_Admin();
            add_action( 'admin_menu', array( $admin, 'add_admin_menu' ) );
            add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_styles' ) );
            add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) );
        }
    }

    /**
     * Hooks publics
     */
    private function define_public_hooks() {
        $public = new IPM_Public();
        add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_scripts' ) );

        // Shortcodes
        IPM_Shortcodes::init();

        // AJAX
        IPM_Ajax::init();

        // WhatsApp
        $whatsapp = new IPM_WhatsApp();
        add_action( 'wp_footer', array( $whatsapp, 'render_floating_button' ) );

        // Espace client
        $customer = new IPM_Customer_Area();
        add_action( 'init', array( $customer, 'add_endpoints' ) );
        add_filter( 'woocommerce_account_menu_items', array( $customer, 'add_menu_items' ) );
    }

    /**
     * Enregistrer les custom post types
     */
    private function register_post_types() {
        add_action( 'init', array( 'IPM_Post_Types', 'register' ) );
    }

    /**
     * Initialisation WooCommerce
     */
    private function init_woocommerce() {
        if ( class_exists( 'WooCommerce' ) ) {
            $wc = new IPM_WooCommerce();
            $wc->init();

            // Statuts de commande
            $order_status = new IPM_Order_Status();
            add_action( 'init', array( $order_status, 'register_statuses' ) );
            add_filter( 'wc_order_statuses', array( $order_status, 'add_statuses' ) );

            // Livraison
            add_action( 'woocommerce_shipping_init', function() {
                new IPM_Shipping();
            });
            add_filter( 'woocommerce_shipping_methods', function( $methods ) {
                $methods['ipm_morocco_shipping'] = 'IPM_Shipping_Method';
                return $methods;
            });

            // Emails
            $emails = new IPM_Emails();
            add_filter( 'woocommerce_email_classes', array( $emails, 'register_emails' ) );
        }
    }

    /**
     * Vérifier si WooCommerce est actif
     *
     * @return bool
     */
    public static function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Récupérer une option du plugin
     *
     * @param string $key     Clé de l'option
     * @param mixed  $default Valeur par défaut
     * @return mixed
     */
    public static function get_option( $key, $default = '' ) {
        $options = get_option( 'ipm_settings', array() );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }
}

// Activation / Désactivation
register_activation_hook( __FILE__, array( 'IPM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IPM_Deactivator', 'deactivate' ) );

/**
 * Initialisation du plugin
 */
function ipm_init() {
    return Imprimerie_Pro_Maroc::get_instance();
}

// Attendre que les plugins soient chargés pour vérifier WooCommerce
add_action( 'plugins_loaded', 'ipm_init' );
