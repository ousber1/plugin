<?php
/**
 * Fonctions de sécurité
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Security {

    /**
     * Initialiser la sécurité
     */
    public static function init() {
        // Bloquer l'accès direct aux fichiers uploadés
        add_action( 'init', array( __CLASS__, 'protect_uploads' ) );

        // Ajouter les types MIME autorisés
        add_filter( 'upload_mimes', array( __CLASS__, 'add_mime_types' ) );
    }

    /**
     * Protéger le dossier uploads
     */
    public static function protect_uploads() {
        $upload_dir = wp_upload_dir();
        $ipm_dir    = $upload_dir['basedir'] . '/ipm-files';

        if ( ! file_exists( $ipm_dir . '/.htaccess' ) && file_exists( $ipm_dir ) ) {
            $htaccess = "Options -Indexes\n";
            $htaccess .= "<FilesMatch '\\.(php|php5|phtml|cgi|pl|py|sh|bash)$'>\n";
            $htaccess .= "    Deny from all\n";
            $htaccess .= "</FilesMatch>\n";

            file_put_contents( $ipm_dir . '/.htaccess', $htaccess );
        }
    }

    /**
     * Ajouter les types MIME pour l'upload
     *
     * @param array $mimes Types MIME existants
     * @return array
     */
    public static function add_mime_types( $mimes ) {
        $mimes['ai']  = 'application/postscript';
        $mimes['psd'] = 'image/vnd.adobe.photoshop';
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Nettoyer et valider un numéro de téléphone marocain
     *
     * @param string $phone Numéro
     * @return string
     */
    public static function sanitize_phone( $phone ) {
        return preg_replace( '/[^0-9+\s\-]/', '', $phone );
    }

    /**
     * Vérifier un nonce AJAX
     *
     * @param string $nonce_name  Nom du nonce
     * @param string $action_name Action
     * @return bool
     */
    public static function verify_nonce( $nonce_name, $action_name ) {
        if ( ! isset( $_REQUEST[ $nonce_name ] ) ) {
            return false;
        }
        return wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_name ] ) ), $action_name );
    }

    /**
     * Limiter le taux de soumission (anti-spam basique)
     *
     * @param string $action  Action
     * @param int    $seconds Intervalle en secondes
     * @return bool True si autorisé
     */
    public static function rate_limit( $action, $seconds = 30 ) {
        $ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $key = 'ipm_rate_' . md5( $action . $ip );

        $last = get_transient( $key );
        if ( $last && ( time() - $last ) < $seconds ) {
            return false;
        }

        set_transient( $key, time(), $seconds );
        return true;
    }
}

// Initialiser
IPM_Security::init();
