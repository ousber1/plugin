<?php
/**
 * Gestionnaire AJAX
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Ajax {

    /**
     * Initialiser les actions AJAX
     */
    public static function init() {
        // Calcul de prix (public)
        add_action( 'wp_ajax_ipm_calculate_price', array( __CLASS__, 'calculate_price' ) );
        add_action( 'wp_ajax_nopriv_ipm_calculate_price', array( __CLASS__, 'calculate_price' ) );

        // Soumission devis (public)
        add_action( 'wp_ajax_ipm_submit_quote', array( __CLASS__, 'submit_quote' ) );
        add_action( 'wp_ajax_nopriv_ipm_submit_quote', array( __CLASS__, 'submit_quote' ) );

        // Upload fichier (connecté)
        add_action( 'wp_ajax_ipm_upload_file', array( __CLASS__, 'upload_file' ) );
        add_action( 'wp_ajax_nopriv_ipm_upload_file', array( __CLASS__, 'upload_file' ) );

        // Suivi commande (public)
        add_action( 'wp_ajax_ipm_track_order', array( __CLASS__, 'track_order' ) );
        add_action( 'wp_ajax_nopriv_ipm_track_order', array( __CLASS__, 'track_order' ) );

        // Ajout au panier (public)
        add_action( 'wp_ajax_ipm_add_to_cart', array( __CLASS__, 'add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_ipm_add_to_cart', array( __CLASS__, 'add_to_cart' ) );

        // Récupérer les options d'un produit (public)
        add_action( 'wp_ajax_ipm_get_product_options', array( __CLASS__, 'get_product_options' ) );
        add_action( 'wp_ajax_nopriv_ipm_get_product_options', array( __CLASS__, 'get_product_options' ) );

        // Admin AJAX
        add_action( 'wp_ajax_ipm_update_quote_status', array( __CLASS__, 'update_quote_status' ) );
        add_action( 'wp_ajax_ipm_convert_quote', array( __CLASS__, 'convert_quote_to_order' ) );
        add_action( 'wp_ajax_ipm_update_file_status', array( __CLASS__, 'update_file_status' ) );
        add_action( 'wp_ajax_ipm_save_shipping_zone', array( __CLASS__, 'save_shipping_zone' ) );
        add_action( 'wp_ajax_ipm_delete_shipping_zone', array( __CLASS__, 'delete_shipping_zone' ) );
        add_action( 'wp_ajax_ipm_get_dashboard_stats', array( __CLASS__, 'get_dashboard_stats' ) );
    }

    /**
     * Calculer le prix
     */
    public static function calculate_price() {
        check_ajax_referer( 'ipm_calculate_price', 'ipm_nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'Produit invalide.' ) );
        }

        // Collecter les options
        $options = array();
        $all_opts = IPM_Options::get_predefined_options();

        foreach ( $all_opts as $key => $opt ) {
            if ( isset( $_POST[ $key ] ) ) {
                if ( is_array( $_POST[ $key ] ) ) {
                    $options[ $key ] = array_map( 'sanitize_text_field', $_POST[ $key ] );
                } else {
                    $options[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
                }
            }
        }

        // Quantité personnalisée
        if ( isset( $_POST['custom_quantity'] ) && absint( $_POST['custom_quantity'] ) > 0 ) {
            $options['quantity'] = absint( $_POST['custom_quantity'] );
        } elseif ( isset( $options['quantity'] ) && 'custom' !== $options['quantity'] ) {
            $options['quantity'] = absint( $options['quantity'] );
        }

        $result = IPM_Price_Calculator::calculate( $product_id, $options );

        wp_send_json_success( $result );
    }

    /**
     * Soumettre un devis
     */
    public static function submit_quote() {
        check_ajax_referer( 'ipm_submit_quote', 'ipm_quote_nonce' );

        $data = array(
            'first_name'   => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
            'last_name'    => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
            'phone'        => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
            'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
            'city'         => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
            'company'      => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '',
            'print_type'   => isset( $_POST['print_type'] ) ? sanitize_text_field( wp_unslash( $_POST['print_type'] ) ) : '',
            'quantity'     => isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0,
            'dimensions'   => isset( $_POST['dimensions'] ) ? sanitize_text_field( wp_unslash( $_POST['dimensions'] ) ) : '',
            'description'  => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'desired_date' => isset( $_POST['desired_date'] ) ? sanitize_text_field( wp_unslash( $_POST['desired_date'] ) ) : '',
        );

        $quote_id = IPM_Quote::create( $data );

        if ( is_wp_error( $quote_id ) ) {
            wp_send_json_error( array( 'message' => $quote_id->get_error_message() ) );
        }

        // Traiter le fichier si présent
        if ( ! empty( $_FILES['quote_file'] ) && $_FILES['quote_file']['error'] === UPLOAD_ERR_OK ) {
            $customer_id = is_user_logged_in() ? get_current_user_id() : 0;
            IPM_File_Upload::handle_upload( $_FILES['quote_file'], $customer_id, array(
                'quote_id' => $quote_id,
            ) );
        }

        wp_send_json_success( array(
            'message'  => 'Votre demande de devis a été envoyée avec succès ! Nous vous contacterons bientôt.',
            'quote_ref' => 'DEV-' . str_pad( $quote_id, 6, '0', STR_PAD_LEFT ),
        ) );
    }

    /**
     * Upload de fichier
     */
    public static function upload_file() {
        check_ajax_referer( 'ipm_upload_file', 'ipm_upload_nonce' );

        if ( empty( $_FILES['print_file'] ) ) {
            wp_send_json_error( array( 'message' => 'Aucun fichier sélectionné.' ) );
        }

        $customer_id = is_user_logged_in() ? get_current_user_id() : 0;

        $meta = array(
            'notes' => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
        );

        // Lier à une commande
        if ( ! empty( $_POST['order_id'] ) ) {
            $meta['order_id'] = absint( $_POST['order_id'] );
        } elseif ( ! empty( $_POST['order_number'] ) ) {
            $order_number = sanitize_text_field( wp_unslash( $_POST['order_number'] ) );
            // Chercher la commande
            if ( class_exists( 'WC_Order' ) ) {
                $orders = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
                foreach ( wc_get_orders( array( 'limit' => -1 ) ) as $order ) {
                    if ( $order->get_order_number() == $order_number ) {
                        $meta['order_id'] = $order->get_id();
                        break;
                    }
                }
            }
        }

        $result = IPM_File_Upload::handle_upload( $_FILES['print_file'], $customer_id, $meta );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Notification
        if ( ! empty( $meta['order_id'] ) ) {
            IPM_Emails::send_file_received( $meta['order_id'], $result['id'] );
        }

        wp_send_json_success( array(
            'message'   => 'Votre fichier a été téléversé avec succès !',
            'file_name' => $result['file_name'],
            'file_size' => IPM_File_Upload::format_file_size( $result['file_size'] ?? 0 ),
        ) );
    }

    /**
     * Suivi de commande
     */
    public static function track_order() {
        check_ajax_referer( 'ipm_track_order', 'ipm_tracking_nonce' );

        if ( ! class_exists( 'WC_Order' ) ) {
            wp_send_json_error( array( 'message' => 'Le suivi de commande n\'est pas disponible.' ) );
        }

        $order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
        $email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

        if ( ! $order_number || ! $email ) {
            wp_send_json_error( array( 'message' => 'Veuillez remplir tous les champs.' ) );
        }

        // Rechercher la commande
        $order = wc_get_order( absint( $order_number ) );

        if ( ! $order || $order->get_billing_email() !== $email ) {
            wp_send_json_error( array( 'message' => 'Commande non trouvée. Vérifiez vos informations.' ) );
        }

        $status       = $order->get_status();
        $statuses     = IPM_Order_Status::get_custom_statuses();
        $all_statuses = wc_get_order_statuses();

        // Construire la timeline
        $timeline = array();
        $current_found = false;

        // Statuts standard WooCommerce d'abord
        $wc_flow = array( 'wc-pending', 'wc-processing' );
        foreach ( $wc_flow as $s ) {
            $is_current = ( 'wc-' . $status === $s );
            $timeline[] = array(
                'status'  => $s,
                'label'   => isset( $all_statuses[ $s ] ) ? $all_statuses[ $s ] : $s,
                'active'  => ! $current_found,
                'current' => $is_current,
            );
            if ( $is_current ) {
                $current_found = true;
            }
        }

        // Statuts personnalisés
        foreach ( $statuses as $s => $args ) {
            $is_current = ( 'wc-' . $status === $s );
            $timeline[] = array(
                'status'  => $s,
                'label'   => $args['label'],
                'color'   => $args['color'],
                'active'  => ! $current_found,
                'current' => $is_current,
            );
            if ( $is_current ) {
                $current_found = true;
            }
        }

        wp_send_json_success( array(
            'order_number' => $order->get_order_number(),
            'status'       => $status,
            'status_label' => isset( $all_statuses[ 'wc-' . $status ] ) ? $all_statuses[ 'wc-' . $status ] : $status,
            'date'         => $order->get_date_created()->format( 'd/m/Y H:i' ),
            'total'        => $order->get_total() . ' MAD',
            'timeline'     => $timeline,
        ) );
    }

    /**
     * Ajouter au panier WooCommerce
     */
    public static function add_to_cart() {
        check_ajax_referer( 'ipm_calculate_price', 'ipm_nonce' );

        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 'message' => 'WooCommerce n\'est pas disponible.' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'Produit invalide.' ) );
        }

        $product = new IPM_Product( $product_id );
        $wc_product_id = $product->get_wc_product_id();

        if ( ! $wc_product_id || ! wc_get_product( $wc_product_id ) ) {
            // Synchroniser
            $wc_product_id = $product->sync_to_woocommerce();
        }

        if ( ! $wc_product_id ) {
            wp_send_json_error( array( 'message' => 'Erreur lors de la création du produit.' ) );
        }

        // Collecter les options sélectionnées
        $options = array();
        $all_opts = IPM_Options::get_predefined_options();

        foreach ( $all_opts as $key => $opt ) {
            if ( isset( $_POST[ $key ] ) ) {
                if ( is_array( $_POST[ $key ] ) ) {
                    $options[ $key ] = array_map( 'sanitize_text_field', $_POST[ $key ] );
                } else {
                    $options[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
                }
            }
        }

        if ( isset( $_POST['custom_quantity'] ) && absint( $_POST['custom_quantity'] ) > 0 ) {
            $options['quantity'] = absint( $_POST['custom_quantity'] );
        }

        // Calculer le prix
        $price_result = IPM_Price_Calculator::calculate( $product_id, $options );

        // Ajouter au panier avec les méta
        $cart_item_data = array(
            'ipm_product_id' => $product_id,
            'ipm_options'    => $options,
            'ipm_price'      => $price_result['total'],
            'ipm_quantity'   => $price_result['quantity'],
        );

        $cart_item_key = WC()->cart->add_to_cart( $wc_product_id, 1, 0, array(), $cart_item_data );

        if ( $cart_item_key ) {
            // Mettre à jour le prix dans le panier
            WC()->cart->cart_contents[ $cart_item_key ]['data']->set_price( $price_result['total'] );

            wp_send_json_success( array(
                'message'  => 'Produit ajouté au panier !',
                'cart_url' => wc_get_cart_url(),
                'total'    => IPM_Price_Calculator::format_price( $price_result['total'] ),
            ) );
        }

        wp_send_json_error( array( 'message' => 'Erreur lors de l\'ajout au panier.' ) );
    }

    /**
     * Récupérer les options d'un produit (AJAX)
     */
    public static function get_product_options() {
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'Produit invalide.' ) );
        }

        $product  = new IPM_Product( $product_id );
        $options  = $product->get_options();
        $all_opts = IPM_Options::get_predefined_options();

        $result = array();
        foreach ( $options as $opt_key ) {
            if ( isset( $all_opts[ $opt_key ] ) ) {
                $result[ $opt_key ] = $all_opts[ $opt_key ];
            }
        }

        wp_send_json_success( array(
            'options'    => $result,
            'base_price' => $product->get_base_price(),
        ) );
    }

    // --- Admin AJAX ---

    /**
     * Mettre à jour le statut d'un devis
     */
    public static function update_quote_status() {
        check_ajax_referer( 'ipm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        $quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
        $status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

        if ( IPM_Quote::update_status( $quote_id, $status ) ) {
            wp_send_json_success( array( 'message' => 'Statut mis à jour.' ) );
        }

        wp_send_json_error( array( 'message' => 'Erreur lors de la mise à jour.' ) );
    }

    /**
     * Convertir un devis en commande
     */
    public static function convert_quote_to_order() {
        check_ajax_referer( 'ipm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        $quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
        $result   = IPM_Quote::convert_to_order( $quote_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'  => 'Commande créée avec succès.',
            'order_id' => $result,
            'edit_url' => admin_url( 'post.php?post=' . $result . '&action=edit' ),
        ) );
    }

    /**
     * Mettre à jour le statut d'un fichier
     */
    public static function update_file_status() {
        check_ajax_referer( 'ipm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        $file_id = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;
        $status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

        if ( IPM_File_Upload::update_status( $file_id, $status ) ) {
            wp_send_json_success( array( 'message' => 'Statut du fichier mis à jour.' ) );
        }

        wp_send_json_error( array( 'message' => 'Erreur lors de la mise à jour.' ) );
    }

    /**
     * Sauvegarder une zone de livraison
     */
    public static function save_shipping_zone() {
        check_ajax_referer( 'ipm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        $data = array(
            'id'             => isset( $_POST['zone_id'] ) ? absint( $_POST['zone_id'] ) : 0,
            'zone_name'      => isset( $_POST['zone_name'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_name'] ) ) : '',
            'cities'         => isset( $_POST['cities'] ) ? sanitize_text_field( wp_unslash( $_POST['cities'] ) ) : '',
            'standard_price' => isset( $_POST['standard_price'] ) ? (float) $_POST['standard_price'] : 0,
            'express_price'  => isset( $_POST['express_price'] ) ? (float) $_POST['express_price'] : 0,
            'standard_days'  => isset( $_POST['standard_days'] ) ? absint( $_POST['standard_days'] ) : 3,
            'express_days'   => isset( $_POST['express_days'] ) ? absint( $_POST['express_days'] ) : 1,
            'free_threshold' => isset( $_POST['free_threshold'] ) ? (float) $_POST['free_threshold'] : null,
            'is_active'      => isset( $_POST['is_active'] ),
        );

        $result = IPM_Shipping::save_zone( $data );

        if ( $result !== false ) {
            wp_send_json_success( array( 'message' => 'Zone de livraison sauvegardée.' ) );
        }

        wp_send_json_error( array( 'message' => 'Erreur lors de la sauvegarde.' ) );
    }

    /**
     * Supprimer une zone de livraison
     */
    public static function delete_shipping_zone() {
        check_ajax_referer( 'ipm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès non autorisé.' ) );
        }

        global $wpdb;
        $zone_id = isset( $_POST['zone_id'] ) ? absint( $_POST['zone_id'] ) : 0;
        $table   = $wpdb->prefix . 'ipm_shipping_zones';

        if ( $wpdb->delete( $table, array( 'id' => $zone_id ) ) ) {
            wp_send_json_success( array( 'message' => 'Zone supprimée.' ) );
        }

        wp_send_json_error( array( 'message' => 'Erreur lors de la suppression.' ) );
    }

    /**
     * Statistiques du dashboard
     */
    public static function get_dashboard_stats() {
        check_ajax_referer( 'ipm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $stats = IPM_Admin_Dashboard::get_stats();
        wp_send_json_success( $stats );
    }
}
