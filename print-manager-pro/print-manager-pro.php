<?php
/**
 * Plugin Name: Print Manager Pro
 * Plugin URI: https://printmanagerpro.com
 * Description: Transformez WooCommerce en plateforme d'impression en ligne professionnelle. Configurateur de produits, outil de design, gestion ERP complète.
 * Version: 1.0.0
 * Author: Print Manager Pro
 * Author URI: https://printmanagerpro.com
 * Text Domain: print-manager-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PMP_VERSION', '1.0.0' );
define( 'PMP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PMP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
final class Print_Manager_Pro {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once PMP_PLUGIN_DIR . 'includes/database.php';
        require_once PMP_PLUGIN_DIR . 'includes/calculator.php';
        require_once PMP_PLUGIN_DIR . 'includes/designer.php';
        require_once PMP_PLUGIN_DIR . 'includes/uploader.php';
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );

        // WooCommerce product tab
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );

        // Frontend configurator on product page
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_configurator' ) );
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );
        add_filter( 'woocommerce_cart_item_price', array( $this, 'update_cart_item_price' ), 10, 3 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'recalculate_cart_prices' ) );

        // AJAX
        add_action( 'wp_ajax_pmp_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wp_ajax_nopriv_pmp_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wp_ajax_pmp_save_design', array( $this, 'ajax_save_design' ) );
        add_action( 'wp_ajax_nopriv_pmp_save_design', array( $this, 'ajax_save_design' ) );
        add_action( 'wp_ajax_pmp_upload_file', array( $this, 'ajax_upload_file' ) );
        add_action( 'wp_ajax_nopriv_pmp_upload_file', array( $this, 'ajax_upload_file' ) );

        // Admin AJAX
        add_action( 'wp_ajax_pmp_save_machine', array( $this, 'ajax_save_machine' ) );
        add_action( 'wp_ajax_pmp_delete_machine', array( $this, 'ajax_delete_machine' ) );
        add_action( 'wp_ajax_pmp_save_expense', array( $this, 'ajax_save_expense' ) );
        add_action( 'wp_ajax_pmp_delete_expense', array( $this, 'ajax_delete_expense' ) );
        add_action( 'wp_ajax_pmp_get_dashboard_data', array( $this, 'ajax_get_dashboard_data' ) );
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        PMP_Database::create_tables();
        $this->create_print_categories();
        $this->insert_default_cost_settings();
        $this->create_default_print_product();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Create default print product categories.
     */
    private function create_print_categories() {
        $categories = array(
            'Cartes de visite',
            'Flyers',
            'Brochures',
            'Affiches',
            'Bâches',
            'Stickers',
            'Roll-up',
            'Enseignes publicitaires',
            'Impression textile',
            'Packaging',
        );

        foreach ( $categories as $cat ) {
            if ( ! term_exists( $cat, 'product_cat' ) ) {
                wp_insert_term( $cat, 'product_cat' );
            }
        }
    }

    /**
     * Insert default cost settings.
     */
    private function insert_default_cost_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'print_cost_settings';

        if ( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) > 0 ) {
            return;
        }

        $defaults = array(
            array( 'setting_key' => 'paper_cost_per_sheet', 'setting_value' => '0.05', 'description' => 'Coût papier par feuille (€)' ),
            array( 'setting_key' => 'ink_cost_per_sheet', 'setting_value' => '0.03', 'description' => 'Coût encre par feuille (€)' ),
            array( 'setting_key' => 'machine_cost_per_hour', 'setting_value' => '25.00', 'description' => 'Coût machine par heure (€)' ),
            array( 'setting_key' => 'sheets_per_hour', 'setting_value' => '500', 'description' => 'Feuilles imprimées par heure' ),
            array( 'setting_key' => 'profit_margin', 'setting_value' => '40', 'description' => 'Marge bénéficiaire (%)' ),
            array( 'setting_key' => 'bulk_discount_100', 'setting_value' => '5', 'description' => 'Remise à partir de 100 unités (%)' ),
            array( 'setting_key' => 'bulk_discount_500', 'setting_value' => '10', 'description' => 'Remise à partir de 500 unités (%)' ),
            array( 'setting_key' => 'bulk_discount_1000', 'setting_value' => '15', 'description' => 'Remise à partir de 1000 unités (%)' ),
            array( 'setting_key' => 'bulk_discount_5000', 'setting_value' => '20', 'description' => 'Remise à partir de 5000 unités (%)' ),
            array( 'setting_key' => 'finishing_lamination', 'setting_value' => '0.02', 'description' => 'Pelliculage par feuille (€)' ),
            array( 'setting_key' => 'finishing_uv_varnish', 'setting_value' => '0.03', 'description' => 'Vernis UV par feuille (€)' ),
            array( 'setting_key' => 'finishing_folding', 'setting_value' => '0.01', 'description' => 'Pliage par feuille (€)' ),
            array( 'setting_key' => 'finishing_cutting', 'setting_value' => '0.01', 'description' => 'Découpe par feuille (€)' ),
            array( 'setting_key' => 'color_multiplier', 'setting_value' => '1.5', 'description' => 'Multiplicateur couleur vs N/B' ),
            array( 'setting_key' => 'recto_verso_multiplier', 'setting_value' => '1.8', 'description' => 'Multiplicateur recto/verso' ),
            array( 'setting_key' => 'urgency_multiplier_standard', 'setting_value' => '1.0', 'description' => 'Multiplicateur d\'urgence standard' ),
            array( 'setting_key' => 'urgency_multiplier_express', 'setting_value' => '1.25', 'description' => 'Multiplicateur d\'urgence express' ),
            array( 'setting_key' => 'delivery_cost_standard', 'setting_value' => '0', 'description' => 'Coût livraison standard' ),
            array( 'setting_key' => 'delivery_cost_express', 'setting_value' => '60', 'description' => 'Coût livraison express' ),
            array( 'setting_key' => 'delivery_cost_retrait', 'setting_value' => '0', 'description' => 'Coût retrait en magasin' ),
            array( 'setting_key' => 'delivery_zone_casablanca', 'setting_value' => '0', 'description' => 'Frais de livraison Casablanca' ),
            array( 'setting_key' => 'delivery_zone_rabat', 'setting_value' => '10', 'description' => 'Frais de livraison Rabat' ),
            array( 'setting_key' => 'delivery_zone_marrakech', 'setting_value' => '15', 'description' => 'Frais de livraison Marrakech' ),
            array( 'setting_key' => 'delivery_zone_autres', 'setting_value' => '20', 'description' => 'Frais de livraison autres villes' ),
            array( 'setting_key' => 'paper_cost_couche_mat', 'setting_value' => '0.06', 'description' => 'Coût papier couché mat par feuille (€)' ),
            array( 'setting_key' => 'paper_cost_couche_brillant', 'setting_value' => '0.07', 'description' => 'Coût papier couché brillant par feuille (€)' ),
            array( 'setting_key' => 'paper_cost_offset', 'setting_value' => '0.05', 'description' => 'Coût papier offset par feuille (€)' ),
            array( 'setting_key' => 'paper_cost_recycle', 'setting_value' => '0.05', 'description' => 'Coût papier recyclé par feuille (€)' ),
            array( 'setting_key' => 'paper_cost_creation', 'setting_value' => '0.08', 'description' => 'Coût papier création par feuille (€)' ),
            array( 'setting_key' => 'paper_cost_kraft', 'setting_value' => '0.07', 'description' => 'Coût papier kraft par feuille (€)' ),
        );

        foreach ( $defaults as $row ) {
            $wpdb->insert( $table, $row );
        }
    }

    /**
     * Create a default print product (created on plugin activation).
     */
    private function create_default_print_product() {
        $products = array(
            array(
                'title'       => 'Cartes de visite - Print Manager Pro',
                'slug'        => 'pmp-cartes-de-visite',
                'content'     => 'Produit de démonstration pour cartes de visite. Modifiez les options d\'impression et les quantités selon vos besoins.',
                'price'       => '50.00',
                'formats'     => array( 'A6', 'A5' ),
                'papers'      => array( 'couche_mat', 'couche_brillant' ),
                'weights'     => array( '135', '170' ),
                'finishings'  => array( 'lamination', 'rounded_corners' ),
                'quantities'  => '50,100,250,500',
                'category'    => 'Cartes de visite',
            ),
            array(
                'title'       => 'Flyers - Print Manager Pro',
                'slug'        => 'pmp-flyers',
                'content'     => 'Produit de démonstration pour flyers. Modifiez les options d\'impression et les quantités selon vos besoins.',
                'price'       => '100.00',
                'formats'     => array( 'A5', 'A4' ),
                'papers'      => array( 'offset', 'recycle' ),
                'weights'     => array( '135', '170', '250' ),
                'finishings'  => array( 'lamination', 'uv_varnish' ),
                'quantities'  => '100,250,500,1000',
                'category'    => 'Flyers',
            ),
        );

        foreach ( $products as $product ) {
            // Avoid creating duplicates.
            if ( get_page_by_path( $product['slug'], OBJECT, 'product' ) ) {
                continue;
            }

            $product_data = array(
                'post_title'   => $product['title'],
                'post_name'    => $product['slug'],
                'post_content' => $product['content'],
                'post_status'  => 'publish',
                'post_type'    => 'product',
            );

            $product_id = wp_insert_post( $product_data );
            if ( is_wp_error( $product_id ) || ! $product_id ) {
                continue;
            }

            // Basic WooCommerce settings.
            update_post_meta( $product_id, '_visibility', 'visible' );
            update_post_meta( $product_id, '_stock_status', 'instock' );
            update_post_meta( $product_id, '_regular_price', $product['price'] );
            update_post_meta( $product_id, '_price', $product['price'] );

            // Ensure product type is simple (WooCommerce)
            wp_set_object_terms( $product_id, 'simple', 'product_type' );

            // Print Manager Pro metadata (enabled by default).
            update_post_meta( $product_id, '_pmp_is_print_product', 'yes' );
            update_post_meta( $product_id, '_pmp_enable_designer', 'yes' );
            update_post_meta( $product_id, '_pmp_enable_upload', 'yes' );
            update_post_meta( $product_id, '_pmp_formats', $product['formats'] );
            update_post_meta( $product_id, '_pmp_papers', $product['papers'] );
            update_post_meta( $product_id, '_pmp_weights', $product['weights'] );
            update_post_meta( $product_id, '_pmp_finishings', $product['finishings'] );
            update_post_meta( $product_id, '_pmp_quantities', $product['quantities'] );

            if ( ! empty( $product['category'] ) ) {
                $term = get_term_by( 'name', $product['category'], 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    wp_set_object_terms( $product_id, (int) $term->term_id, 'product_cat' );
                }
            }
        }
    }

    /**
     * Admin menu.
     */
    public function admin_menu() {
        add_menu_page(
            'Imprimerie Manager',
            'Imprimerie Manager',
            'manage_options',
            'pmp-dashboard',
            array( $this, 'page_dashboard' ),
            'dashicons-printer',
            30
        );

        add_submenu_page( 'pmp-dashboard', 'Tableau de bord', 'Tableau de bord', 'manage_options', 'pmp-dashboard', array( $this, 'page_dashboard' ) );
        add_submenu_page( 'pmp-dashboard', 'Produits d\'impression', 'Produits d\'impression', 'manage_options', 'edit.php?post_type=product', null );
        add_submenu_page( 'pmp-dashboard', 'Commandes', 'Commandes', 'manage_options', 'edit.php?post_type=shop_order', null );
        add_submenu_page( 'pmp-dashboard', 'Machines', 'Machines', 'manage_options', 'pmp-machines', array( $this, 'page_machines' ) );
        add_submenu_page( 'pmp-dashboard', 'Dépenses', 'Dépenses', 'manage_options', 'pmp-expenses', array( $this, 'page_expenses' ) );
        add_submenu_page( 'pmp-dashboard', 'Revenus', 'Revenus', 'manage_options', 'pmp-revenue', array( $this, 'page_revenue' ) );
        add_submenu_page( 'pmp-dashboard', 'Statistiques', 'Statistiques', 'manage_options', 'pmp-statistics', array( $this, 'page_statistics' ) );
        add_submenu_page( 'pmp-dashboard', 'Paramètres tarification', 'Paramètres tarification', 'manage_options', 'pmp-cost-settings', array( $this, 'page_cost_settings' ) );
        add_submenu_page( 'pmp-dashboard', 'Fournisseurs', 'Fournisseurs', 'manage_options', 'pmp-suppliers', array( $this, 'page_suppliers' ) );
        add_submenu_page( 'pmp-dashboard', 'Clients', 'Clients', 'manage_options', 'pmp-clients', array( $this, 'page_clients' ) );
        add_submenu_page( 'pmp-dashboard', 'Workflow commandes', 'Workflow commandes', 'manage_options', 'pmp-workflow', array( $this, 'page_workflow' ) );
        add_submenu_page( 'pmp-dashboard', 'Devis', 'Devis', 'manage_options', 'pmp-quotes', array( $this, 'page_quotes' ) );
    }

    public function page_dashboard() {
        require_once PMP_PLUGIN_DIR . 'admin/dashboard.php';
    }

    public function page_suppliers() {
        require_once PMP_PLUGIN_DIR . 'admin/suppliers.php';
    }

    public function page_clients() {
        require_once PMP_PLUGIN_DIR . 'admin/clients.php';
    }

    public function page_workflow() {
        require_once PMP_PLUGIN_DIR . 'admin/workflow.php';
    }

    public function page_quotes() {
        require_once PMP_PLUGIN_DIR . 'admin/quotes.php';
    }

    public function page_machines() {
        require_once PMP_PLUGIN_DIR . 'admin/machines.php';
    }

    public function page_expenses() {
        require_once PMP_PLUGIN_DIR . 'admin/expenses.php';
    }

    public function page_revenue() {
        require_once PMP_PLUGIN_DIR . 'admin/revenue.php';
    }

    public function page_statistics() {
        require_once PMP_PLUGIN_DIR . 'admin/statistics.php';
    }

    public function page_cost_settings() {
        require_once PMP_PLUGIN_DIR . 'admin/cost-settings.php';
    }

    /**
     * Admin assets.
     */
    public function admin_assets( $hook ) {
        $pmp_pages = array(
            'toplevel_page_pmp-dashboard',
            'imprimerie-manager_page_pmp-machines',
            'imprimerie-manager_page_pmp-expenses',
            'imprimerie-manager_page_pmp-revenue',
            'imprimerie-manager_page_pmp-statistics',
            'imprimerie-manager_page_pmp-cost-settings',
        );

        if ( in_array( $hook, $pmp_pages, true ) ) {
            wp_enqueue_style( 'pmp-admin', PMP_PLUGIN_URL . 'assets/css/admin.css', array(), PMP_VERSION );
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );
            wp_enqueue_script( 'pmp-charts', PMP_PLUGIN_URL . 'assets/js/charts.js', array( 'jquery', 'chart-js' ), PMP_VERSION, true );
            wp_localize_script( 'pmp-charts', 'pmp_admin', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'pmp_admin_nonce' ),
            ) );
        }
    }

    /**
     * Frontend assets.
     */
    public function frontend_assets() {
        if ( is_product() ) {
            wp_enqueue_style( 'pmp-frontend', PMP_PLUGIN_URL . 'assets/css/frontend.css', array(), PMP_VERSION );
            wp_enqueue_script( 'fabric-js', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js', array(), '5.3.1', true );
            wp_enqueue_script( 'pmp-configurator', PMP_PLUGIN_URL . 'assets/js/configurator.js', array( 'jquery' ), PMP_VERSION, true );
            wp_enqueue_script( 'pmp-designer', PMP_PLUGIN_URL . 'assets/js/designer.js', array( 'jquery', 'fabric-js' ), PMP_VERSION, true );

            wp_localize_script( 'pmp-configurator', 'pmp_config', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'pmp_frontend_nonce' ),
                'currency' => get_woocommerce_currency_symbol(),
                'max_upload_size' => 100 * 1024 * 1024,
            ) );
        }
    }

    /**
     * WooCommerce product data tab.
     */
    public function product_data_tab( $tabs ) {
        $tabs['pmp_printing'] = array(
            'label'    => 'Options d\'impression',
            'target'   => 'pmp_printing_data',
            'class'    => array(),
            'priority' => 80,
        );
        return $tabs;
    }

    /**
     * WooCommerce product data panel.
     */
    public function product_data_panel() {
        global $post;
        $product_id = $post->ID;
        ?>
        <div id="pmp_printing_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_checkbox( array(
                    'id'    => '_pmp_is_print_product',
                    'label' => 'Produit d\'impression',
                    'description' => 'Activer le configurateur d\'impression pour ce produit',
                ) );

                woocommerce_wp_checkbox( array(
                    'id'    => '_pmp_enable_designer',
                    'label' => 'Outil de design',
                    'description' => 'Activer l\'outil de design en ligne',
                ) );

                woocommerce_wp_checkbox( array(
                    'id'    => '_pmp_enable_upload',
                    'label' => 'Upload fichiers',
                    'description' => 'Permettre l\'upload de fichiers',
                ) );
                ?>
            </div>
            <div class="options_group">
                <p class="form-field"><strong>Formats disponibles</strong></p>
                <?php
                $formats = array( 'A3', 'A4', 'A5', 'A6', 'DL', '10x15', '13x18', '21x29.7', 'Personnalisé' );
                $saved_formats = get_post_meta( $product_id, '_pmp_formats', true );
                if ( ! is_array( $saved_formats ) ) {
                    $saved_formats = array();
                }
                foreach ( $formats as $format ) {
                    $checked = in_array( $format, $saved_formats, true ) ? 'checked' : '';
                    echo '<label style="display:inline-block;margin:5px 15px;"><input type="checkbox" name="_pmp_formats[]" value="' . esc_attr( $format ) . '" ' . $checked . '> ' . esc_html( $format ) . '</label>';
                }
                ?>
            </div>
            <div class="options_group">
                <p class="form-field"><strong>Types de papier</strong></p>
                <?php
                $papers = array(
                    'couche_mat' => 'Couché mat',
                    'couche_brillant' => 'Couché brillant',
                    'offset' => 'Offset',
                    'recycle' => 'Recyclé',
                    'creation' => 'Création',
                    'kraft' => 'Kraft',
                );
                $saved_papers = get_post_meta( $product_id, '_pmp_papers', true );
                if ( ! is_array( $saved_papers ) ) {
                    $saved_papers = array();
                }
                foreach ( $papers as $key => $label ) {
                    $checked = in_array( $key, $saved_papers, true ) ? 'checked' : '';
                    echo '<label style="display:inline-block;margin:5px 15px;"><input type="checkbox" name="_pmp_papers[]" value="' . esc_attr( $key ) . '" ' . $checked . '> ' . esc_html( $label ) . '</label>';
                }
                ?>
            </div>
            <div class="options_group">
                <p class="form-field"><strong>Grammages disponibles (g/m²)</strong></p>
                <?php
                $weights = array( '90', '135', '170', '250', '300', '350', '400' );
                $saved_weights = get_post_meta( $product_id, '_pmp_weights', true );
                if ( ! is_array( $saved_weights ) ) {
                    $saved_weights = array();
                }
                foreach ( $weights as $w ) {
                    $checked = in_array( $w, $saved_weights, true ) ? 'checked' : '';
                    echo '<label style="display:inline-block;margin:5px 15px;"><input type="checkbox" name="_pmp_weights[]" value="' . esc_attr( $w ) . '" ' . $checked . '> ' . esc_html( $w ) . 'g</label>';
                }
                ?>
            </div>
            <div class="options_group">
                <p class="form-field"><strong>Finitions disponibles</strong></p>
                <?php
                $finishings = array(
                    'lamination' => 'Pelliculage',
                    'uv_varnish' => 'Vernis UV',
                    'folding' => 'Pliage',
                    'cutting' => 'Découpe',
                    'rounded_corners' => 'Coins arrondis',
                    'embossing' => 'Gaufrage',
                    'hot_foil' => 'Dorure à chaud',
                );
                $saved_finishings = get_post_meta( $product_id, '_pmp_finishings', true );
                if ( ! is_array( $saved_finishings ) ) {
                    $saved_finishings = array();
                }
                foreach ( $finishings as $key => $label ) {
                    $checked = in_array( $key, $saved_finishings, true ) ? 'checked' : '';
                    echo '<label style="display:inline-block;margin:5px 15px;"><input type="checkbox" name="_pmp_finishings[]" value="' . esc_attr( $key ) . '" ' . $checked . '> ' . esc_html( $label ) . '</label>';
                }
                ?>
            </div>
            <div class="options_group">
                <?php
                woocommerce_wp_text_input( array(
                    'id'          => '_pmp_base_price',
                    'label'       => 'Prix de base (€)',
                    'type'        => 'number',
                    'desc_tip'    => true,
                    'description' => 'Prix de base avant calcul automatique',
                    'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
                ) );

                $quantities = get_post_meta( $product_id, '_pmp_quantities', true );
                woocommerce_wp_text_input( array(
                    'id'          => '_pmp_quantities',
                    'label'       => 'Quantités proposées',
                    'description' => 'Séparées par des virgules. Ex: 50,100,250,500,1000,2500,5000',
                    'value'       => $quantities ? $quantities : '50,100,250,500,1000,2500,5000',
                ) );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save product meta.
     */
    public function save_product_meta( $product_id ) {
        if ( ! isset( $_POST['woocommerce_meta_nonce'] ) ) {
            return;
        }

        $checkbox_fields = array( '_pmp_is_print_product', '_pmp_enable_designer', '_pmp_enable_upload' );
        foreach ( $checkbox_fields as $field ) {
            $value = isset( $_POST[ $field ] ) ? 'yes' : 'no';
            update_post_meta( $product_id, $field, $value );
        }

        $array_fields = array( '_pmp_formats', '_pmp_papers', '_pmp_weights', '_pmp_finishings' );
        foreach ( $array_fields as $field ) {
            $values = isset( $_POST[ $field ] ) ? array_map( 'sanitize_text_field', $_POST[ $field ] ) : array();
            update_post_meta( $product_id, $field, $values );
        }

        if ( isset( $_POST['_pmp_base_price'] ) ) {
            update_post_meta( $product_id, '_pmp_base_price', sanitize_text_field( $_POST['_pmp_base_price'] ) );
        }

        if ( isset( $_POST['_pmp_quantities'] ) ) {
            update_post_meta( $product_id, '_pmp_quantities', sanitize_text_field( $_POST['_pmp_quantities'] ) );
        }
    }

    /**
     * Render product configurator on frontend.
     */
    public function render_configurator() {
        global $product;

        if ( ! $product ) {
            return;
        }

        $is_print = get_post_meta( $product->get_id(), '_pmp_is_print_product', true );
        if ( 'yes' !== $is_print ) {
            return;
        }

        $formats    = get_post_meta( $product->get_id(), '_pmp_formats', true );
        $papers     = get_post_meta( $product->get_id(), '_pmp_papers', true );
        $weights    = get_post_meta( $product->get_id(), '_pmp_weights', true );
        $finishings = get_post_meta( $product->get_id(), '_pmp_finishings', true );
        $quantities = get_post_meta( $product->get_id(), '_pmp_quantities', true );
        $enable_designer = get_post_meta( $product->get_id(), '_pmp_enable_designer', true );
        $enable_upload   = get_post_meta( $product->get_id(), '_pmp_enable_upload', true );

        if ( ! is_array( $formats ) )    $formats = array();
        if ( ! is_array( $papers ) )     $papers = array();
        if ( ! is_array( $weights ) )    $weights = array();
        if ( ! is_array( $finishings ) ) $finishings = array();

        $qty_array = $quantities ? explode( ',', $quantities ) : array( 50, 100, 250, 500, 1000 );

        $paper_labels = array(
            'couche_mat'     => 'Couché mat',
            'couche_brillant'=> 'Couché brillant',
            'offset'         => 'Offset',
            'recycle'        => 'Recyclé',
            'creation'       => 'Création',
            'kraft'          => 'Kraft',
        );

        $finishing_labels = array(
            'lamination'      => 'Pelliculage',
            'uv_varnish'      => 'Vernis UV',
            'folding'         => 'Pliage',
            'cutting'         => 'Découpe',
            'rounded_corners' => 'Coins arrondis',
            'embossing'       => 'Gaufrage',
            'hot_foil'        => 'Dorure à chaud',
        );
        ?>
        <div id="pmp-configurator" class="pmp-configurator" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
            <h3>Configurez votre produit</h3>

            <?php if ( ! empty( $formats ) ) : ?>
            <div class="pmp-config-group">
                <label>Format</label>
                <select name="pmp_format" id="pmp-format">
                    <?php foreach ( $formats as $f ) : ?>
                        <option value="<?php echo esc_attr( $f ); ?>"><?php echo esc_html( $f ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $papers ) ) : ?>
            <div class="pmp-config-group">
                <label>Type de papier</label>
                <select name="pmp_paper" id="pmp-paper">
                    <?php foreach ( $papers as $p ) : ?>
                        <option value="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( isset( $paper_labels[ $p ] ) ? $paper_labels[ $p ] : $p ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $weights ) ) : ?>
            <div class="pmp-config-group">
                <label>Grammage</label>
                <select name="pmp_weight" id="pmp-weight">
                    <?php foreach ( $weights as $w ) : ?>
                        <option value="<?php echo esc_attr( $w ); ?>"><?php echo esc_html( $w ); ?>g/m²</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="pmp-config-group">
                <label>Impression</label>
                <select name="pmp_sides" id="pmp-sides">
                    <option value="recto">Recto uniquement</option>
                    <option value="recto_verso">Recto / Verso</option>
                </select>
            </div>

            <div class="pmp-config-group">
                <label>Mode couleur</label>
                <select name="pmp_color" id="pmp-color">
                    <option value="color">Couleur</option>
                    <option value="bw">Noir &amp; Blanc</option>
                </select>
            </div>

            <?php if ( ! empty( $finishings ) ) : ?>
            <div class="pmp-config-group">
                <label>Finitions</label>
                <div class="pmp-finishings">
                    <?php foreach ( $finishings as $fin ) : ?>
                        <label class="pmp-finishing-option">
                            <input type="checkbox" name="pmp_finishing[]" value="<?php echo esc_attr( $fin ); ?>">
                            <?php echo esc_html( isset( $finishing_labels[ $fin ] ) ? $finishing_labels[ $fin ] : $fin ); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="pmp-config-group">
                <label>Quantité</label>
                <select name="pmp_quantity" id="pmp-quantity">
                    <?php foreach ( $qty_array as $q ) : ?>
                        <option value="<?php echo esc_attr( trim( $q ) ); ?>"><?php echo esc_html( trim( $q ) ); ?> ex.</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pmp-config-group">
                <label>Orientation</label>
                <select name="pmp_orientation" id="pmp-orientation">
                    <option value="portrait">Portrait</option>
                    <option value="paysage">Paysage</option>
                </select>
            </div>

            <div class="pmp-config-group">
                <label>Urgence</label>
                <select name="pmp_urgency" id="pmp-urgency">
                    <option value="standard">Standard</option>
                    <option value="express">Express</option>
                </select>
            </div>

            <div class="pmp-config-group">
                <label>Livraison</label>
                <select name="pmp_delivery" id="pmp-delivery">
                    <option value="standard">Standard (2-4 jours)</option>
                    <option value="express">Express (1-2 jours)</option>
                    <option value="retrait">Retrait en magasin</option>
                </select>
            </div>

            <div class="pmp-config-group">
                <label>Zone de livraison</label>
                <select name="pmp_delivery_zone" id="pmp-delivery-zone">
                    <option value="casablanca">Casablanca</option>
                    <option value="rabat">Rabat</option>
                    <option value="marrakech">Marrakech</option>
                    <option value="autres">Autres villes</option>
                </select>
            </div>

            <div class="pmp-config-group">
                <label><input type="checkbox" name="pmp_bat" id="pmp-bat" value="yes"> Demander un BAT (Bon à Tirer)</label>
            </div>

            <div class="pmp-config-group">
                <label>Commentaires</label>
                <textarea name="pmp_comments" id="pmp-comments" rows="3" style="width:100%;max-width:400px;" placeholder="Ajoutez des informations supplémentaires..."></textarea>
            </div>

            <div class="pmp-price-display">
                <div class="pmp-price-loading" style="display:none;">Calcul en cours...</div>
                <div class="pmp-calculated-price">
                    <span class="pmp-unit-price-label">Prix unitaire : </span>
                    <span class="pmp-unit-price">-</span>
                    <br>
                    <span class="pmp-total-price-label">Prix total : </span>
                    <strong class="pmp-total-price">-</strong>
                </div>
            </div>

            <input type="hidden" name="pmp_calculated_price" id="pmp-calculated-price" value="">
            <input type="hidden" name="pmp_design_data" id="pmp-design-data" value="">
            <input type="hidden" name="pmp_uploaded_file" id="pmp-uploaded-file" value="">

            <?php if ( 'yes' === $enable_upload ) : ?>
            <div class="pmp-upload-section">
                <h4>Télécharger votre fichier</h4>
                <p class="pmp-upload-info">Formats acceptés : PDF, AI, PSD, PNG, JPG — Max. 100 Mo</p>
                <input type="file" id="pmp-file-upload" accept=".pdf,.ai,.psd,.png,.jpg,.jpeg">
                <div class="pmp-upload-progress" style="display:none;">
                    <div class="pmp-progress-bar"><div class="pmp-progress-fill"></div></div>
                    <span class="pmp-progress-text">0%</span>
                </div>
                <div class="pmp-upload-result" style="display:none;"></div>
            </div>
            <?php endif; ?>

            <?php if ( 'yes' === $enable_designer ) : ?>
            <div class="pmp-designer-section">
                <h4>Créer votre design</h4>
                <button type="button" id="pmp-open-designer" class="button pmp-btn-designer">Ouvrir l'outil de design</button>
            </div>

            <div id="pmp-designer-modal" class="pmp-modal" style="display:none;">
                <div class="pmp-modal-content">
                    <div class="pmp-modal-header">
                        <h3>Outil de Design</h3>
                        <button type="button" class="pmp-modal-close">&times;</button>
                    </div>
                    <div class="pmp-modal-body">
                        <div class="pmp-designer-toolbar">
                            <button type="button" class="pmp-tool-btn" data-tool="text" title="Ajouter du texte">T</button>
                            <button type="button" class="pmp-tool-btn" data-tool="image" title="Ajouter une image">&#128247;</button>
                            <button type="button" class="pmp-tool-btn" data-tool="line" title="Ligne">─</button>
                            <button type="button" class="pmp-tool-btn" data-tool="rect" title="Rectangle">&#9632;</button>
                            <button type="button" class="pmp-tool-btn" data-tool="triangle" title="Triangle">&#9650;</button>
                            <button type="button" class="pmp-tool-btn" data-tool="circle" title="Cercle">&#9679;</button>
                            <button type="button" class="pmp-tool-btn" data-tool="bring_forward" title="Avant-plan">⇧</button>
                            <button type="button" class="pmp-tool-btn" data-tool="send_backward" title="Arrière-plan">⇩</button>
                            <button type="button" class="pmp-tool-btn" data-tool="undo" title="Annuler">↶</button>
                            <button type="button" class="pmp-tool-btn" data-tool="redo" title="Rétablir">↷</button>
                            <button type="button" class="pmp-tool-btn" data-tool="zoom_in" title="Zoom +">+</button>
                            <button type="button" class="pmp-tool-btn" data-tool="zoom_out" title="Zoom -">−</button>
                            <button type="button" class="pmp-tool-btn" data-tool="delete" title="Supprimer">&#128465;</button>
                            <input type="file" id="pmp-designer-image-input" accept="image/*" style="display:none;">
                            <input type="color" id="pmp-designer-color" value="#000000" title="Couleur">
                            <select id="pmp-designer-font-size">
                                <option value="14">14px</option>
                                <option value="18">18px</option>
                                <option value="24" selected>24px</option>
                                <option value="32">32px</option>
                                <option value="48">48px</option>
                                <option value="64">64px</option>
                            </select>
                        </div>
                        <canvas id="pmp-designer-canvas" width="600" height="400"></canvas>
                    </div>
                    <div class="pmp-modal-footer">
                        <button type="button" class="button" id="pmp-designer-preview">Aperçu</button>
                        <button type="button" class="button" id="pmp-designer-download">Télécharger</button>
                        <button type="button" class="button button-primary" id="pmp-designer-save">Sauvegarder le design</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add configurator data to cart item.
     */
    public function add_cart_item_data( $cart_item_data, $product_id ) {
        $is_print = get_post_meta( $product_id, '_pmp_is_print_product', true );
        if ( 'yes' !== $is_print ) {
            return $cart_item_data;
        }

        $fields = array(
            'pmp_format',
            'pmp_paper',
            'pmp_weight',
            'pmp_sides',
            'pmp_color',
            'pmp_quantity',
            'pmp_orientation',
            'pmp_urgency',
            'pmp_delivery',
            'pmp_delivery_zone',
            'pmp_bat',
            'pmp_comments',
            'pmp_calculated_price',
            'pmp_design_data',
            'pmp_uploaded_file'
        );

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $cart_item_data[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
            }
        }

        if ( isset( $_POST['pmp_finishing'] ) && is_array( $_POST['pmp_finishing'] ) ) {
            $cart_item_data['pmp_finishing'] = array_map( 'sanitize_text_field', $_POST['pmp_finishing'] );
        }

        return $cart_item_data;
    }

    /**
     * Display configuration in cart.
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        $labels = array(
            'pmp_format'      => 'Format',
            'pmp_paper'       => 'Papier',
            'pmp_weight'      => 'Grammage',
            'pmp_sides'       => 'Impression',
            'pmp_color'       => 'Couleur',
            'pmp_quantity'    => 'Quantité',
            'pmp_orientation' => 'Orientation',
            'pmp_urgency'     => 'Urgence',
            'pmp_delivery'    => 'Livraison',
            'pmp_delivery_zone' => 'Zone de livraison',
            'pmp_bat'         => 'BAT demandé',
            'pmp_comments'    => 'Commentaires',
        );

        foreach ( $labels as $key => $label ) {
            if ( isset( $cart_item[ $key ] ) && '' !== $cart_item[ $key ] ) {
                $value = $cart_item[ $key ];

                if ( 'pmp_bat' === $key ) {
                    $value = ( 'yes' === $value || 'on' === $value ) ? 'Oui' : 'Non';
                }

                $item_data[] = array(
                    'key'   => $label,
                    'value' => $value,
                );
            }
        }

        if ( ! empty( $cart_item['pmp_finishing'] ) ) {
            $item_data[] = array(
                'key'   => 'Finitions',
                'value' => implode( ', ', $cart_item['pmp_finishing'] ),
            );
        }

        return $item_data;
    }

    /**
     * Save order item meta.
     */
    public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
        $meta_keys = array(
            'pmp_format',
            'pmp_paper',
            'pmp_weight',
            'pmp_sides',
            'pmp_color',
            'pmp_quantity',
            'pmp_orientation',
            'pmp_urgency',
            'pmp_delivery',
            'pmp_delivery_zone',
            'pmp_bat',
            'pmp_comments',
            'pmp_calculated_price',
            'pmp_uploaded_file',
        );

        foreach ( $meta_keys as $key ) {
            if ( isset( $values[ $key ] ) ) {
                $item->add_meta_data( $key, $values[ $key ] );
            }
        }

        if ( ! empty( $values['pmp_finishing'] ) ) {
            $item->add_meta_data( 'pmp_finishing', implode( ', ', $values['pmp_finishing'] ) );
        }

        if ( ! empty( $values['pmp_design_data'] ) ) {
            $item->add_meta_data( 'pmp_design_data', $values['pmp_design_data'] );
        }
    }

    /**
     * Update cart item price display.
     */
    public function update_cart_item_price( $price, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['pmp_calculated_price'] ) && $cart_item['pmp_calculated_price'] > 0 ) {
            return wc_price( $cart_item['pmp_calculated_price'] );
        }
        return $price;
    }

    /**
     * Recalculate cart prices with configurator data.
     */
    public function recalculate_cart_prices( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['pmp_calculated_price'] ) && $cart_item['pmp_calculated_price'] > 0 ) {
                $cart_item['data']->set_price( floatval( $cart_item['pmp_calculated_price'] ) );
            }
        }
    }

    /**
     * AJAX: Calculate price.
     */
    public function ajax_calculate_price() {
        check_ajax_referer( 'pmp_frontend_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $format     = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : '';
        $paper      = isset( $_POST['paper'] ) ? sanitize_text_field( $_POST['paper'] ) : '';
        $weight     = isset( $_POST['weight'] ) ? sanitize_text_field( $_POST['weight'] ) : '';
        $sides      = isset( $_POST['sides'] ) ? sanitize_text_field( $_POST['sides'] ) : 'recto';
        $color      = isset( $_POST['color'] ) ? sanitize_text_field( $_POST['color'] ) : 'color';
        $quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
        $finishing   = isset( $_POST['finishing'] ) ? array_map( 'sanitize_text_field', $_POST['finishing'] ) : array();

        $calculator = new PMP_Calculator();
        $result = $calculator->calculate( array(
            'product_id' => $product_id,
            'format'     => $format,
            'paper'      => $paper,
            'weight'     => $weight,
            'sides'      => $sides,
            'color'      => $color,
            'quantity'   => $quantity,
            'finishing'  => $finishing,
        ) );

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Save design.
     */
    public function ajax_save_design() {
        check_ajax_referer( 'pmp_frontend_nonce', 'nonce' );

        $design_data = isset( $_POST['design_data'] ) ? wp_unslash( $_POST['design_data'] ) : '';
        $product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( empty( $design_data ) ) {
            wp_send_json_error( array( 'message' => 'Aucune donnée de design.' ) );
        }

        $designer = new PMP_Designer();
        $result = $designer->save_design( $design_data, $product_id );

        if ( $result ) {
            wp_send_json_success( array(
                'message'   => 'Design sauvegardé avec succès.',
                'design_id' => $result,
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Erreur lors de la sauvegarde.' ) );
        }
    }

    /**
     * AJAX: Upload file.
     */
    public function ajax_upload_file() {
        check_ajax_referer( 'pmp_frontend_nonce', 'nonce' );

        $uploader = new PMP_Uploader();
        $result = $uploader->handle_upload();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Save machine.
     */
    public function ajax_save_machine() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'print_machines';

        $data = array(
            'machine_name'     => sanitize_text_field( $_POST['machine_name'] ),
            'cost_per_hour'    => floatval( $_POST['cost_per_hour'] ),
            'maintenance_cost' => floatval( $_POST['maintenance_cost'] ),
            'status'           => sanitize_text_field( $_POST['status'] ),
        );

        $machine_id = isset( $_POST['machine_id'] ) ? absint( $_POST['machine_id'] ) : 0;

        if ( $machine_id > 0 ) {
            $wpdb->update( $table, $data, array( 'id' => $machine_id ) );
        } else {
            $wpdb->insert( $table, $data );
            $machine_id = $wpdb->insert_id;
        }

        wp_send_json_success( array( 'id' => $machine_id, 'message' => 'Machine sauvegardée.' ) );
    }

    /**
     * AJAX: Delete machine.
     */
    public function ajax_delete_machine() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        $machine_id = absint( $_POST['machine_id'] );
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'print_machines', array( 'id' => $machine_id ) );

        wp_send_json_success( array( 'message' => 'Machine supprimée.' ) );
    }

    /**
     * AJAX: Save expense.
     */
    public function ajax_save_expense() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'print_expenses';

        $data = array(
            'category'    => sanitize_text_field( $_POST['category'] ),
            'description' => sanitize_textarea_field( $_POST['description'] ),
            'amount'      => floatval( $_POST['amount'] ),
            'expense_date'=> sanitize_text_field( $_POST['expense_date'] ),
        );

        $expense_id = isset( $_POST['expense_id'] ) ? absint( $_POST['expense_id'] ) : 0;

        if ( $expense_id > 0 ) {
            $wpdb->update( $table, $data, array( 'id' => $expense_id ) );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $table, $data );
            $expense_id = $wpdb->insert_id;
        }

        wp_send_json_success( array( 'id' => $expense_id, 'message' => 'Dépense sauvegardée.' ) );
    }

    /**
     * AJAX: Delete expense.
     */
    public function ajax_delete_expense() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        $expense_id = absint( $_POST['expense_id'] );
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'print_expenses', array( 'id' => $expense_id ) );

        wp_send_json_success( array( 'message' => 'Dépense supprimée.' ) );
    }

    /**
     * AJAX: Get dashboard data.
     */
    public function ajax_get_dashboard_data() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        global $wpdb;

        $year = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : intval( date( 'Y' ) );

        // Monthly revenue from WooCommerce
        $monthly_revenue = array_fill( 1, 12, 0 );
        $orders = wc_get_orders( array(
            'limit'      => -1,
            'status'     => array( 'completed', 'processing' ),
            'date_created' => $year . '-01-01...' . $year . '-12-31',
        ) );

        $total_revenue = 0;
        foreach ( $orders as $order ) {
            $month = intval( $order->get_date_created()->format( 'n' ) );
            $monthly_revenue[ $month ] += floatval( $order->get_total() );
            $total_revenue += floatval( $order->get_total() );
        }

        // Monthly expenses
        $monthly_expenses = array_fill( 1, 12, 0 );
        $expenses_table = $wpdb->prefix . 'print_expenses';
        $expenses = $wpdb->get_results( $wpdb->prepare(
            "SELECT MONTH(expense_date) as month, SUM(amount) as total FROM {$expenses_table} WHERE YEAR(expense_date) = %d GROUP BY MONTH(expense_date)",
            $year
        ) );

        $total_expenses = 0;
        foreach ( $expenses as $exp ) {
            $monthly_expenses[ intval( $exp->month ) ] = floatval( $exp->total );
            $total_expenses += floatval( $exp->total );
        }

        // Machines count
        $machines_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}print_machines" );
        $active_machines = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}print_machines WHERE status = 'active'" );

        wp_send_json_success( array(
            'monthly_revenue'  => array_values( $monthly_revenue ),
            'monthly_expenses' => array_values( $monthly_expenses ),
            'total_revenue'    => $total_revenue,
            'total_expenses'   => $total_expenses,
            'net_profit'       => $total_revenue - $total_expenses,
            'total_orders'     => count( $orders ),
            'machines_count'   => intval( $machines_count ),
            'active_machines'  => intval( $active_machines ),
        ) );
    }
}

// Initialize plugin.
function pmp_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Print Manager Pro</strong> nécessite WooCommerce. Veuillez installer et activer WooCommerce.</p></div>';
        });
        return;
    }
    Print_Manager_Pro::instance();
}
add_action( 'plugins_loaded', 'pmp_init' );
