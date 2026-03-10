<?php
/**
 * Classe d'administration principale
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Admin {

    /**
     * Ajouter les menus admin
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            'Imprimerie Pro Maroc',
            'Imprimerie Pro',
            'manage_options',
            'ipm-dashboard',
            array( 'IPM_Admin_Dashboard', 'render' ),
            'dashicons-printer',
            26
        );

        // Sous-menus
        add_submenu_page(
            'ipm-dashboard',
            'Tableau de bord',
            'Tableau de bord',
            'manage_options',
            'ipm-dashboard',
            array( 'IPM_Admin_Dashboard', 'render' )
        );

        add_submenu_page(
            'ipm-dashboard',
            'Produits d\'impression',
            'Produits',
            'manage_options',
            'edit.php?post_type=ipm_product'
        );

        add_submenu_page(
            'ipm-dashboard',
            'Catégories',
            'Catégories',
            'manage_options',
            'edit-tags.php?taxonomy=ipm_category&post_type=ipm_product'
        );

        add_submenu_page(
            'ipm-dashboard',
            'Devis',
            'Devis',
            'manage_options',
            'ipm-quotes',
            array( 'IPM_Admin_Quotes', 'render' )
        );

        add_submenu_page(
            'ipm-dashboard',
            'Fichiers clients',
            'Fichiers',
            'manage_options',
            'ipm-files',
            array( 'IPM_Admin_Files', 'render' )
        );

        add_submenu_page(
            'ipm-dashboard',
            'Livraison',
            'Livraison',
            'manage_options',
            'ipm-shipping',
            array( 'IPM_Admin_Settings', 'render_shipping' )
        );

        add_submenu_page(
            'ipm-dashboard',
            'Réglages',
            'Réglages',
            'manage_options',
            'ipm-settings',
            array( 'IPM_Admin_Settings', 'render' )
        );
    }

    /**
     * Charger les styles admin
     *
     * @param string $hook Page actuelle
     */
    public function enqueue_styles( $hook ) {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_style(
            'ipm-admin',
            IPM_PLUGIN_URL . 'admin/css/ipm-admin.css',
            array(),
            IPM_VERSION
        );
    }

    /**
     * Charger les scripts admin
     *
     * @param string $hook Page actuelle
     */
    public function enqueue_scripts( $hook ) {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script( 'jquery' );

        wp_enqueue_script(
            'ipm-admin',
            IPM_PLUGIN_URL . 'admin/js/ipm-admin.js',
            array( 'jquery' ),
            IPM_VERSION,
            true
        );

        wp_localize_script( 'ipm-admin', 'ipmAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ipm_admin_nonce' ),
        ) );
    }

    /**
     * Vérifier si on est sur une page du plugin
     *
     * @param string $hook
     * @return bool
     */
    private function is_plugin_page( $hook ) {
        $plugin_pages = array(
            'toplevel_page_ipm-dashboard',
            'imprimerie-pro_page_ipm-quotes',
            'imprimerie-pro_page_ipm-files',
            'imprimerie-pro_page_ipm-shipping',
            'imprimerie-pro_page_ipm-settings',
        );

        if ( in_array( $hook, $plugin_pages, true ) ) {
            return true;
        }

        // Pages de produit impression
        global $post_type;
        if ( 'ipm_product' === $post_type || 'ipm_quote' === $post_type ) {
            return true;
        }

        return false;
    }
}

/**
 * Métaboxes pour les produits d'impression
 */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'ipm_product_settings',
        'Paramètres du produit d\'impression',
        'ipm_render_product_metabox',
        'ipm_product',
        'normal',
        'high'
    );
} );

/**
 * Rendu de la métabox produit
 *
 * @param WP_Post $post
 */
function ipm_render_product_metabox( $post ) {
    wp_nonce_field( 'ipm_save_product', 'ipm_product_nonce' );

    $base_price = get_post_meta( $post->ID, '_ipm_base_price', true );
    $sku        = get_post_meta( $post->ID, '_ipm_sku', true );
    $status     = get_post_meta( $post->ID, '_ipm_status', true ) ?: 'active';
    $delay      = get_post_meta( $post->ID, '_ipm_production_delay', true );
    $min_price  = get_post_meta( $post->ID, '_ipm_minimum_price', true );
    $options    = get_post_meta( $post->ID, '_ipm_options', true );
    $gallery    = get_post_meta( $post->ID, '_ipm_gallery', true );

    if ( ! is_array( $options ) ) $options = array();
    if ( ! is_array( $gallery ) ) $gallery = array();

    $all_opts = IPM_Options::get_predefined_options();
    ?>
    <div class="ipm-metabox">
        <div class="ipm-metabox-section">
            <h4>Informations générales</h4>
            <table class="form-table">
                <tr>
                    <th><label for="ipm_base_price">Prix de base (MAD)</label></th>
                    <td><input type="number" id="ipm_base_price" name="ipm_base_price" value="<?php echo esc_attr( $base_price ); ?>" step="0.01" min="0" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="ipm_sku">SKU</label></th>
                    <td><input type="text" id="ipm_sku" name="ipm_sku" value="<?php echo esc_attr( $sku ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="ipm_status">Statut</label></th>
                    <td>
                        <select id="ipm_status" name="ipm_status">
                            <option value="active" <?php selected( $status, 'active' ); ?>>Actif</option>
                            <option value="inactive" <?php selected( $status, 'inactive' ); ?>>Inactif</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="ipm_delay">Délai de production</label></th>
                    <td><input type="text" id="ipm_delay" name="ipm_production_delay" value="<?php echo esc_attr( $delay ); ?>" class="regular-text" placeholder="ex: 3-5 jours ouvrables"></td>
                </tr>
                <tr>
                    <th><label for="ipm_min_price">Prix minimum (MAD)</label></th>
                    <td><input type="number" id="ipm_min_price" name="ipm_minimum_price" value="<?php echo esc_attr( $min_price ); ?>" step="0.01" min="0" class="regular-text"></td>
                </tr>
            </table>
        </div>

        <div class="ipm-metabox-section">
            <h4>Options de personnalisation</h4>
            <p class="description">Sélectionnez les options disponibles pour ce produit :</p>
            <div class="ipm-options-grid">
                <?php foreach ( $all_opts as $key => $opt ) : ?>
                    <label class="ipm-option-checkbox">
                        <input type="checkbox" name="ipm_options[]" value="<?php echo esc_attr( $key ); ?>"
                            <?php checked( in_array( $key, $options, true ) ); ?>>
                        <?php echo esc_html( $opt['label'] ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ipm-metabox-section">
            <h4>Galerie d'images</h4>
            <div id="ipm-gallery-container">
                <?php foreach ( $gallery as $img_id ) : ?>
                    <div class="ipm-gallery-item" data-id="<?php echo esc_attr( $img_id ); ?>">
                        <?php echo wp_get_attachment_image( $img_id, 'thumbnail' ); ?>
                        <button type="button" class="ipm-remove-image">&times;</button>
                        <input type="hidden" name="ipm_gallery[]" value="<?php echo esc_attr( $img_id ); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="ipm-add-gallery-images">Ajouter des images</button>
        </div>

        <?php if ( Imprimerie_Pro_Maroc::is_woocommerce_active() ) : ?>
        <div class="ipm-metabox-section">
            <h4>Synchronisation WooCommerce</h4>
            <?php
            $wc_id = get_post_meta( $post->ID, '_ipm_wc_product_id', true );
            if ( $wc_id ) :
            ?>
                <p>Produit WooCommerce lié : <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $wc_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $wc_id ); ?></a></p>
            <?php endif; ?>
            <label>
                <input type="checkbox" name="ipm_sync_wc" value="1">
                Synchroniser avec WooCommerce à la sauvegarde
            </label>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Sauvegarder les données du produit
 */
add_action( 'save_post_ipm_product', function( $post_id ) {
    if ( ! isset( $_POST['ipm_product_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipm_product_nonce'] ) ), 'ipm_save_product' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $fields = array(
        '_ipm_base_price'       => 'ipm_base_price',
        '_ipm_sku'              => 'ipm_sku',
        '_ipm_status'           => 'ipm_status',
        '_ipm_production_delay' => 'ipm_production_delay',
        '_ipm_minimum_price'    => 'ipm_minimum_price',
    );

    foreach ( $fields as $meta_key => $post_key ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) );
        }
    }

    // Options
    $options = isset( $_POST['ipm_options'] ) ? array_map( 'sanitize_text_field', $_POST['ipm_options'] ) : array();
    update_post_meta( $post_id, '_ipm_options', $options );

    // Galerie
    $gallery = isset( $_POST['ipm_gallery'] ) ? array_map( 'absint', $_POST['ipm_gallery'] ) : array();
    update_post_meta( $post_id, '_ipm_gallery', $gallery );

    // Sync WooCommerce
    if ( ! empty( $_POST['ipm_sync_wc'] ) ) {
        $product = new IPM_Product( $post_id );
        $product->sync_to_woocommerce();
    }
} );
