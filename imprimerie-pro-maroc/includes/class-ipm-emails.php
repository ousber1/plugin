<?php
/**
 * Gestion des emails automatiques
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Emails {

    /**
     * Enregistrer les classes d'emails WooCommerce
     *
     * @param array $emails Classes existantes
     * @return array
     */
    public function register_emails( $emails ) {
        $emails['IPM_Email_File_Received']  = new IPM_Email_File_Received();
        $emails['IPM_Email_File_Validated'] = new IPM_Email_File_Validated();
        $emails['IPM_Email_Order_Shipped']  = new IPM_Email_Order_Shipped();
        return $emails;
    }

    /**
     * Envoyer un email de réception de fichier
     *
     * @param int $order_id ID de la commande
     * @param int $file_id  ID du fichier
     */
    public static function send_file_received( $order_id, $file_id = 0 ) {
        if ( ! class_exists( 'WC_Order' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $email   = $order->get_billing_email();
        $name    = $order->get_billing_first_name();
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $subject = sprintf( 'Fichier reçu pour votre commande #%s', $order->get_order_number() );

        $body = sprintf( '<h2>Bonjour %s,</h2>', esc_html( $name ) );
        $body .= sprintf(
            '<p>Nous avons bien reçu votre fichier pour la commande <strong>#%s</strong>.</p>',
            $order->get_order_number()
        );
        $body .= '<p>Notre équipe va vérifier votre fichier et vous notifier dès que la commande sera en préparation.</p>';
        $body .= '<p>Cordialement,<br>L\'équipe Imprimerie Pro Maroc</p>';

        wp_mail( $email, $subject, $body, $headers );
    }

    /**
     * Envoyer un email de validation de fichier
     *
     * @param int  $order_id ID de la commande
     * @param bool $valid    Fichier valide ou non
     * @param string $reason Raison du rejet
     */
    public static function send_file_validation( $order_id, $valid = true, $reason = '' ) {
        if ( ! class_exists( 'WC_Order' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $email   = $order->get_billing_email();
        $name    = $order->get_billing_first_name();
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        if ( $valid ) {
            $subject = sprintf( 'Fichier validé - Commande #%s', $order->get_order_number() );
            $body    = sprintf( '<h2>Bonjour %s,</h2>', esc_html( $name ) );
            $body   .= '<p>Votre fichier a été vérifié et validé par notre équipe. Votre commande est maintenant en cours de préparation.</p>';
        } else {
            $subject = sprintf( 'Action requise - Fichier commande #%s', $order->get_order_number() );
            $body    = sprintf( '<h2>Bonjour %s,</h2>', esc_html( $name ) );
            $body   .= '<p>Après vérification, votre fichier nécessite des modifications :</p>';
            if ( $reason ) {
                $body .= sprintf( '<blockquote style="border-left:3px solid #e74c3c;padding:10px;margin:15px 0;background:#fdf2f2">%s</blockquote>', esc_html( $reason ) );
            }
            $body .= '<p>Merci de nous renvoyer un fichier corrigé.</p>';
        }

        $body .= '<p>Cordialement,<br>L\'équipe Imprimerie Pro Maroc</p>';

        wp_mail( $email, $subject, $body, $headers );
    }
}

/**
 * Email WC : Fichier reçu
 */
if ( class_exists( 'WC_Email' ) ) {

    class IPM_Email_File_Received extends WC_Email {
        public function __construct() {
            $this->id             = 'ipm_file_received';
            $this->title          = 'Fichier reçu (Impression)';
            $this->description    = 'Email envoyé quand un fichier est reçu pour une commande d\'impression.';
            $this->heading        = 'Fichier reçu';
            $this->subject        = 'Votre fichier a été reçu - Commande #{order_number}';
            $this->customer_email = true;
            $this->email_type     = 'html';

            parent::__construct();
        }

        public function get_default_subject() {
            return 'Votre fichier a été reçu - Commande #{order_number}';
        }

        public function get_default_heading() {
            return 'Fichier reçu pour votre commande';
        }
    }

    class IPM_Email_File_Validated extends WC_Email {
        public function __construct() {
            $this->id             = 'ipm_file_validated';
            $this->title          = 'Fichier validé (Impression)';
            $this->description    = 'Email envoyé quand un fichier est validé.';
            $this->heading        = 'Fichier validé';
            $this->subject        = 'Votre fichier a été validé - Commande #{order_number}';
            $this->customer_email = true;
            $this->email_type     = 'html';

            parent::__construct();
        }

        public function get_default_subject() {
            return 'Votre fichier a été validé - Commande #{order_number}';
        }

        public function get_default_heading() {
            return 'Votre fichier est validé !';
        }
    }

    class IPM_Email_Order_Shipped extends WC_Email {
        public function __construct() {
            $this->id             = 'ipm_order_shipped';
            $this->title          = 'Commande expédiée (Impression)';
            $this->description    = 'Email envoyé quand une commande est expédiée.';
            $this->heading        = 'Commande expédiée';
            $this->subject        = 'Votre commande #{order_number} a été expédiée !';
            $this->customer_email = true;
            $this->email_type     = 'html';

            parent::__construct();
        }

        public function get_default_subject() {
            return 'Votre commande #{order_number} a été expédiée !';
        }

        public function get_default_heading() {
            return 'Votre commande est en route !';
        }
    }
}
