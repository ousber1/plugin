<?php
/**
 * Intégration WooCommerce
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_WooCommerce {

    /**
     * Initialiser l'intégration
     */
    public function init() {
        // Modifier le prix dans le panier
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_prices' ), 20 );

        // Afficher les options dans le panier
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

        // Sauvegarder les méta lors de la commande
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

        // Afficher les fichiers dans la page commande admin
        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_order_files' ) );

        // Devise MAD
        add_filter( 'woocommerce_currencies', array( $this, 'add_mad_currency' ) );
        add_filter( 'woocommerce_currency_symbol', array( $this, 'add_mad_symbol' ), 10, 2 );

        // HPOS compatibility
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', IPM_PLUGIN_FILE, true );
            }
        });

        // Ajout upload sur page checkout
        add_action( 'woocommerce_after_order_notes', array( $this, 'add_checkout_upload' ) );

        // Traiter l'upload au checkout
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_checkout_upload' ), 10, 3 );

        // Template produit impression
        add_filter( 'single_template', array( $this, 'load_product_template' ) );
    }

    /**
     * Définir les prix personnalisés dans le panier
     *
     * @param WC_Cart $cart
     */
    public function set_cart_prices( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['ipm_price'] ) ) {
                $cart_item['data']->set_price( (float) $cart_item['ipm_price'] );
            }
        }
    }

    /**
     * Afficher les options dans le panier
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( ! isset( $cart_item['ipm_options'] ) ) {
            return $item_data;
        }

        $all_opts = IPM_Options::get_predefined_options();

        foreach ( $cart_item['ipm_options'] as $key => $value ) {
            if ( empty( $value ) || 'quantity' === $key ) {
                continue;
            }

            $label = isset( $all_opts[ $key ]['label'] ) ? $all_opts[ $key ]['label'] : $key;

            if ( is_array( $value ) ) {
                $display_values = array();
                foreach ( $value as $v ) {
                    if ( isset( $all_opts[ $key ]['choices'][ $v ] ) ) {
                        $display_values[] = $all_opts[ $key ]['choices'][ $v ];
                    } else {
                        $display_values[] = $v;
                    }
                }
                $display = implode( ', ', $display_values );
            } elseif ( isset( $all_opts[ $key ]['choices'][ $value ] ) ) {
                $display = $all_opts[ $key ]['choices'][ $value ];
            } elseif ( '1' === $value ) {
                $display = 'Oui';
            } else {
                $display = $value;
            }

            $item_data[] = array(
                'key'   => $label,
                'value' => $display,
            );
        }

        if ( isset( $cart_item['ipm_quantity'] ) && $cart_item['ipm_quantity'] > 1 ) {
            $item_data[] = array(
                'key'   => 'Quantité impression',
                'value' => $cart_item['ipm_quantity'] . ' exemplaires',
            );
        }

        return $item_data;
    }

    /**
     * Ajouter les méta aux items de commande
     *
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     * @param WC_Order              $order
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['ipm_options'] ) ) {
            $item->add_meta_data( '_ipm_options', $values['ipm_options'] );
        }
        if ( isset( $values['ipm_product_id'] ) ) {
            $item->add_meta_data( '_ipm_product_id', $values['ipm_product_id'] );
        }
        if ( isset( $values['ipm_quantity'] ) ) {
            $item->add_meta_data( '_ipm_quantity', $values['ipm_quantity'] );
        }
    }

    /**
     * Afficher les fichiers dans l'admin commande
     *
     * @param WC_Order $order
     */
    public function display_order_files( $order ) {
        $files = IPM_File_Upload::get_order_files( $order->get_id() );

        if ( empty( $files ) ) {
            return;
        }

        echo '<div class="ipm-order-files" style="margin-top:20px">';
        echo '<h3>Fichiers d\'impression</h3>';
        echo '<table class="widefat">';
        echo '<thead><tr><th>Fichier</th><th>Type</th><th>Taille</th><th>Statut</th><th>Date</th></tr></thead>';
        echo '<tbody>';

        foreach ( $files as $file ) {
            $status_labels = array(
                'uploaded'   => '<span style="color:#2196F3">Téléversé</span>',
                'received'   => '<span style="color:#FF9800">Reçu</span>',
                'verified'   => '<span style="color:#4CAF50">Vérifié</span>',
                'rejected'   => '<span style="color:#f44336">Rejeté</span>',
                'processing' => '<span style="color:#9C27B0">En traitement</span>',
            );

            printf(
                '<tr><td><a href="%s" target="_blank">%s</a></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_url( str_replace( ABSPATH, site_url( '/' ), $file['file_path'] ) ),
                esc_html( $file['file_name'] ),
                esc_html( strtoupper( $file['file_type'] ) ),
                esc_html( IPM_File_Upload::format_file_size( $file['file_size'] ) ),
                isset( $status_labels[ $file['status'] ] ) ? $status_labels[ $file['status'] ] : esc_html( $file['status'] ),
                esc_html( wp_date( 'd/m/Y H:i', strtotime( $file['uploaded_at'] ) ) )
            );
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Ajouter la devise MAD
     *
     * @param array $currencies
     * @return array
     */
    public function add_mad_currency( $currencies ) {
        $currencies['MAD'] = 'Dirham marocain';
        return $currencies;
    }

    /**
     * Symbole MAD
     *
     * @param string $symbol
     * @param string $currency
     * @return string
     */
    public function add_mad_symbol( $symbol, $currency ) {
        if ( 'MAD' === $currency ) {
            return 'MAD';
        }
        return $symbol;
    }

    /**
     * Ajouter l'upload au checkout
     *
     * @param WC_Checkout $checkout
     */
    public function add_checkout_upload( $checkout ) {
        // Vérifier si le panier contient des produits impression
        $has_print = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( isset( $item['ipm_product_id'] ) ) {
                $has_print = true;
                break;
            }
        }

        if ( ! $has_print ) {
            return;
        }

        echo '<div class="ipm-checkout-upload">';
        echo '<h3>Fichier d\'impression</h3>';
        echo '<p>Vous pouvez joindre votre fichier maintenant ou l\'envoyer après la commande.</p>';
        echo '<input type="file" name="ipm_checkout_file" accept=".pdf,.png,.jpg,.jpeg,.ai,.psd,.svg,.zip">';
        echo '<small>' . esc_html( Imprimerie_Pro_Maroc::get_option( 'upload_help_text' ) ) . '</small>';
        echo '</div>';
    }

    /**
     * Traiter l'upload lors du checkout
     *
     * @param int      $order_id
     * @param array    $posted_data
     * @param WC_Order $order
     */
    public function process_checkout_upload( $order_id, $posted_data, $order ) {
        if ( empty( $_FILES['ipm_checkout_file'] ) || $_FILES['ipm_checkout_file']['error'] !== UPLOAD_ERR_OK ) {
            return;
        }

        $customer_id = $order->get_customer_id();
        $result = IPM_File_Upload::handle_upload(
            $_FILES['ipm_checkout_file'],
            $customer_id,
            array( 'order_id' => $order_id )
        );

        if ( ! is_wp_error( $result ) ) {
            $order->add_order_note( sprintf(
                'Fichier d\'impression reçu : %s',
                $result['file_name']
            ) );
        }
    }

    /**
     * Charger le template produit impression
     *
     * @param string $template
     * @return string
     */
    public function load_product_template( $template ) {
        if ( is_singular( 'ipm_product' ) ) {
            $custom = IPM_PLUGIN_DIR . 'public/templates/single-ipm-product.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }
        return $template;
    }
}
