<?php
/**
 * Gestion des devis
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Quote {

    /**
     * Statuts possibles d'un devis
     */
    const STATUS_NEW      = 'new';
    const STATUS_PENDING  = 'pending';
    const STATUS_SENT     = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REFUSED  = 'refused';

    /**
     * Créer un nouveau devis
     *
     * @param array $data Données du devis
     * @return int|WP_Error ID du devis ou erreur
     */
    public static function create( $data ) {
        $required = array( 'first_name', 'last_name', 'phone', 'email', 'print_type' );
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 'missing_field', sprintf( 'Le champ %s est obligatoire.', $field ) );
            }
        }

        // Sanitisation
        $sanitized = array(
            'first_name'  => sanitize_text_field( $data['first_name'] ),
            'last_name'   => sanitize_text_field( $data['last_name'] ),
            'phone'       => sanitize_text_field( $data['phone'] ),
            'email'       => sanitize_email( $data['email'] ),
            'city'        => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : '',
            'company'     => isset( $data['company'] ) ? sanitize_text_field( $data['company'] ) : '',
            'print_type'  => sanitize_text_field( $data['print_type'] ),
            'quantity'    => isset( $data['quantity'] ) ? absint( $data['quantity'] ) : 0,
            'dimensions'  => isset( $data['dimensions'] ) ? sanitize_text_field( $data['dimensions'] ) : '',
            'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
            'desired_date' => isset( $data['desired_date'] ) ? sanitize_text_field( $data['desired_date'] ) : '',
        );

        // Valider l'email
        if ( ! is_email( $sanitized['email'] ) ) {
            return new WP_Error( 'invalid_email', 'Adresse email invalide.' );
        }

        // Créer le post
        $title = sprintf(
            'Devis #%s - %s %s',
            gmdate( 'Ymd-His' ),
            $sanitized['first_name'],
            $sanitized['last_name']
        );

        $quote_id = wp_insert_post( array(
            'post_type'   => 'ipm_quote',
            'post_title'  => $title,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $quote_id ) ) {
            return $quote_id;
        }

        // Sauvegarder les métadonnées
        foreach ( $sanitized as $key => $value ) {
            update_post_meta( $quote_id, '_ipm_quote_' . $key, $value );
        }

        update_post_meta( $quote_id, '_ipm_quote_status', self::STATUS_NEW );
        update_post_meta( $quote_id, '_ipm_quote_date', current_time( 'mysql' ) );

        // Associer au client connecté si applicable
        if ( is_user_logged_in() ) {
            update_post_meta( $quote_id, '_ipm_quote_customer_id', get_current_user_id() );
        }

        // Envoyer les notifications
        self::send_notification( $quote_id, 'new' );

        return $quote_id;
    }

    /**
     * Mettre à jour le statut d'un devis
     *
     * @param int    $quote_id ID du devis
     * @param string $status   Nouveau statut
     * @return bool
     */
    public static function update_status( $quote_id, $status ) {
        $valid = array( self::STATUS_NEW, self::STATUS_PENDING, self::STATUS_SENT, self::STATUS_ACCEPTED, self::STATUS_REFUSED );

        if ( ! in_array( $status, $valid, true ) ) {
            return false;
        }

        $old_status = get_post_meta( $quote_id, '_ipm_quote_status', true );
        update_post_meta( $quote_id, '_ipm_quote_status', $status );

        if ( $old_status !== $status ) {
            self::send_notification( $quote_id, $status );
        }

        return true;
    }

    /**
     * Envoyer une notification email
     *
     * @param int    $quote_id ID du devis
     * @param string $type     Type de notification
     */
    public static function send_notification( $quote_id, $type ) {
        $email      = get_post_meta( $quote_id, '_ipm_quote_email', true );
        $first_name = get_post_meta( $quote_id, '_ipm_quote_first_name', true );
        $last_name  = get_post_meta( $quote_id, '_ipm_quote_last_name', true );
        $admin_email = Imprimerie_Pro_Maroc::get_option( 'notification_email', get_option( 'admin_email' ) );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        switch ( $type ) {
            case 'new':
                // Email admin
                $admin_subject = sprintf( '[Nouveau devis] Demande de %s %s', $first_name, $last_name );
                $admin_body    = self::get_admin_notification_body( $quote_id );
                wp_mail( $admin_email, $admin_subject, $admin_body, $headers );

                // Email client
                $client_subject = 'Votre demande de devis a été reçue - Imprimerie Pro Maroc';
                $client_body    = self::get_client_confirmation_body( $quote_id );
                wp_mail( $email, $client_subject, $client_body, $headers );
                break;

            case 'sent':
                $subject = 'Votre devis est prêt - Imprimerie Pro Maroc';
                $body    = self::get_quote_sent_body( $quote_id );
                wp_mail( $email, $subject, $body, $headers );
                break;

            case 'accepted':
                $subject = sprintf( '[Devis accepté] %s %s', $first_name, $last_name );
                wp_mail( $admin_email, $subject, '<p>Le devis a été accepté par le client.</p>', $headers );
                break;
        }
    }

    /**
     * Corps de l'email de notification admin
     *
     * @param int $quote_id ID du devis
     * @return string
     */
    private static function get_admin_notification_body( $quote_id ) {
        $fields = array(
            'Nom'        => get_post_meta( $quote_id, '_ipm_quote_last_name', true ),
            'Prénom'     => get_post_meta( $quote_id, '_ipm_quote_first_name', true ),
            'Email'      => get_post_meta( $quote_id, '_ipm_quote_email', true ),
            'Téléphone'  => get_post_meta( $quote_id, '_ipm_quote_phone', true ),
            'Ville'      => get_post_meta( $quote_id, '_ipm_quote_city', true ),
            'Entreprise' => get_post_meta( $quote_id, '_ipm_quote_company', true ),
            'Type'       => get_post_meta( $quote_id, '_ipm_quote_print_type', true ),
            'Quantité'   => get_post_meta( $quote_id, '_ipm_quote_quantity', true ),
            'Dimensions' => get_post_meta( $quote_id, '_ipm_quote_dimensions', true ),
            'Description' => get_post_meta( $quote_id, '_ipm_quote_description', true ),
            'Date souhaitée' => get_post_meta( $quote_id, '_ipm_quote_desired_date', true ),
        );

        $html = '<h2>Nouvelle demande de devis</h2>';
        $html .= '<table style="border-collapse:collapse;width:100%">';
        foreach ( $fields as $label => $value ) {
            if ( $value ) {
                $html .= sprintf(
                    '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold">%s</td><td style="padding:8px;border:1px solid #ddd">%s</td></tr>',
                    esc_html( $label ),
                    esc_html( $value )
                );
            }
        }
        $html .= '</table>';
        $html .= sprintf(
            '<p><a href="%s">Voir le devis dans l\'administration</a></p>',
            esc_url( admin_url( 'post.php?post=' . $quote_id . '&action=edit' ) )
        );

        return $html;
    }

    /**
     * Corps de l'email de confirmation client
     *
     * @param int $quote_id ID du devis
     * @return string
     */
    private static function get_client_confirmation_body( $quote_id ) {
        $first_name = get_post_meta( $quote_id, '_ipm_quote_first_name', true );

        $html = sprintf( '<h2>Bonjour %s,</h2>', esc_html( $first_name ) );
        $html .= '<p>Nous avons bien reçu votre demande de devis. Notre équipe l\'examine et vous contactera dans les plus brefs délais.</p>';
        $html .= '<p><strong>Référence :</strong> DEV-' . str_pad( $quote_id, 6, '0', STR_PAD_LEFT ) . '</p>';
        $html .= '<p>Si vous avez des questions, n\'hésitez pas à nous contacter.</p>';
        $html .= '<p>Cordialement,<br>L\'équipe Imprimerie Pro Maroc</p>';

        return $html;
    }

    /**
     * Corps de l'email devis envoyé
     *
     * @param int $quote_id ID du devis
     * @return string
     */
    private static function get_quote_sent_body( $quote_id ) {
        $first_name = get_post_meta( $quote_id, '_ipm_quote_first_name', true );
        $amount     = get_post_meta( $quote_id, '_ipm_quote_amount', true );

        $html = sprintf( '<h2>Bonjour %s,</h2>', esc_html( $first_name ) );
        $html .= '<p>Votre devis est prêt !</p>';
        if ( $amount ) {
            $html .= sprintf( '<p><strong>Montant :</strong> %s MAD</p>', esc_html( number_format( (float) $amount, 2, ',', ' ' ) ) );
        }
        $html .= '<p>N\'hésitez pas à nous contacter pour toute question ou pour passer commande.</p>';
        $html .= '<p>Cordialement,<br>L\'équipe Imprimerie Pro Maroc</p>';

        return $html;
    }

    /**
     * Convertir un devis en commande WooCommerce
     *
     * @param int $quote_id ID du devis
     * @return int|WP_Error ID de la commande WooCommerce
     */
    public static function convert_to_order( $quote_id ) {
        if ( ! class_exists( 'WC_Order' ) ) {
            return new WP_Error( 'wc_missing', 'WooCommerce est requis pour créer une commande.' );
        }

        $amount     = (float) get_post_meta( $quote_id, '_ipm_quote_amount', true );
        $email      = get_post_meta( $quote_id, '_ipm_quote_email', true );
        $first_name = get_post_meta( $quote_id, '_ipm_quote_first_name', true );
        $last_name  = get_post_meta( $quote_id, '_ipm_quote_last_name', true );
        $phone      = get_post_meta( $quote_id, '_ipm_quote_phone', true );
        $city       = get_post_meta( $quote_id, '_ipm_quote_city', true );
        $company    = get_post_meta( $quote_id, '_ipm_quote_company', true );
        $print_type = get_post_meta( $quote_id, '_ipm_quote_print_type', true );

        $order = wc_create_order();

        // Ajouter un produit générique
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( sprintf( 'Devis #DEV-%s - %s', str_pad( $quote_id, 6, '0', STR_PAD_LEFT ), $print_type ) );
        $fee->set_total( $amount );
        $order->add_item( $fee );

        // Adresse
        $order->set_billing_first_name( $first_name );
        $order->set_billing_last_name( $last_name );
        $order->set_billing_email( $email );
        $order->set_billing_phone( $phone );
        $order->set_billing_city( $city );
        $order->set_billing_company( $company );
        $order->set_billing_country( 'MA' );

        // Associer au client
        $customer_id = get_post_meta( $quote_id, '_ipm_quote_customer_id', true );
        if ( $customer_id ) {
            $order->set_customer_id( (int) $customer_id );
        }

        $order->set_currency( 'MAD' );
        $order->calculate_totals();
        $order->save();

        // Lier le devis à la commande
        update_post_meta( $quote_id, '_ipm_quote_order_id', $order->get_id() );
        $order->add_order_note( sprintf( 'Commande créée à partir du devis #DEV-%s', str_pad( $quote_id, 6, '0', STR_PAD_LEFT ) ) );

        // Transférer les fichiers
        $files = IPM_File_Upload::get_quote_files( $quote_id );
        foreach ( $files as $file ) {
            IPM_File_Upload::link_to_order( $file['id'], $order->get_id() );
        }

        // Mettre à jour le statut du devis
        self::update_status( $quote_id, self::STATUS_ACCEPTED );

        return $order->get_id();
    }

    /**
     * Récupérer les labels de statut
     *
     * @return array
     */
    public static function get_status_labels() {
        return array(
            self::STATUS_NEW      => 'Nouveau',
            self::STATUS_PENDING  => 'En cours',
            self::STATUS_SENT     => 'Envoyé',
            self::STATUS_ACCEPTED => 'Accepté',
            self::STATUS_REFUSED  => 'Refusé',
        );
    }

    /**
     * Récupérer les devis d'un client
     *
     * @param int $customer_id ID du client
     * @return array
     */
    public static function get_customer_quotes( $customer_id ) {
        return get_posts( array(
            'post_type'      => 'ipm_quote',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_ipm_quote_customer_id',
                    'value' => $customer_id,
                ),
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );
    }
}
