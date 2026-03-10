<?php
/**
 * Gestion du téléversement de fichiers
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_File_Upload {

    /**
     * Types de fichiers autorisés par défaut
     *
     * @var array
     */
    private static $default_types = array(
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'ai'   => 'application/postscript',
        'psd'  => 'image/vnd.adobe.photoshop',
        'svg'  => 'image/svg+xml',
        'zip'  => 'application/zip',
    );

    /**
     * Traiter l'upload d'un fichier
     *
     * @param array $file      Fichier $_FILES
     * @param int   $customer_id ID client
     * @param array $meta      Métadonnées (order_id, quote_id, product_id, notes)
     * @return array|WP_Error
     */
    public static function handle_upload( $file, $customer_id, $meta = array() ) {
        // Vérification de sécurité
        $validation = self::validate_file( $file );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Dossier de destination
        $upload_dir = wp_upload_dir();
        $ipm_dir    = $upload_dir['basedir'] . '/ipm-files';
        $sub_dir    = $ipm_dir . '/' . gmdate( 'Y/m' );

        if ( ! file_exists( $sub_dir ) ) {
            wp_mkdir_p( $sub_dir );
        }

        // Générer un nom de fichier sécurisé
        $ext       = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $safe_name = wp_unique_filename( $sub_dir, sanitize_file_name( $file['name'] ) );
        $dest_path = $sub_dir . '/' . $safe_name;

        // Déplacer le fichier
        if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
            return new WP_Error( 'upload_failed', 'Erreur lors du téléversement du fichier.' );
        }

        // Sécuriser les permissions
        chmod( $dest_path, 0644 );

        // Enregistrer en base de données
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_client_files';

        $data = array(
            'customer_id' => absint( $customer_id ),
            'file_name'   => sanitize_file_name( $file['name'] ),
            'file_path'   => $dest_path,
            'file_type'   => $ext,
            'file_size'   => $file['size'],
            'notes'       => isset( $meta['notes'] ) ? sanitize_textarea_field( $meta['notes'] ) : '',
            'status'      => 'uploaded',
            'uploaded_at' => current_time( 'mysql' ),
        );

        if ( ! empty( $meta['order_id'] ) ) {
            $data['order_id'] = absint( $meta['order_id'] );
        }
        if ( ! empty( $meta['quote_id'] ) ) {
            $data['quote_id'] = absint( $meta['quote_id'] );
        }
        if ( ! empty( $meta['product_id'] ) ) {
            $data['product_id'] = absint( $meta['product_id'] );
        }

        $wpdb->insert( $table, $data );
        $file_id = $wpdb->insert_id;

        return array(
            'id'        => $file_id,
            'file_name' => $data['file_name'],
            'file_type' => $ext,
            'file_size' => $file['size'],
        );
    }

    /**
     * Valider un fichier avant upload
     *
     * @param array $file Fichier $_FILES
     * @return true|WP_Error
     */
    public static function validate_file( $file ) {
        // Vérifier erreur upload
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', 'Erreur lors du téléversement.' );
        }

        // Vérifier la taille
        $max_size = (int) Imprimerie_Pro_Maroc::get_option( 'max_file_size', 50 ) * 1024 * 1024;
        if ( $file['size'] > $max_size ) {
            return new WP_Error(
                'file_too_large',
                sprintf( 'Le fichier dépasse la taille maximale autorisée (%d Mo).', $max_size / ( 1024 * 1024 ) )
            );
        }

        // Vérifier le type de fichier
        $ext            = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $allowed_string = Imprimerie_Pro_Maroc::get_option( 'allowed_file_types', 'pdf,png,jpg,jpeg,ai,psd,svg,zip' );
        $allowed        = array_map( 'trim', explode( ',', $allowed_string ) );

        if ( ! in_array( $ext, $allowed, true ) ) {
            return new WP_Error(
                'invalid_type',
                sprintf( 'Type de fichier non autorisé. Formats acceptés : %s.', implode( ', ', $allowed ) )
            );
        }

        // Vérification MIME
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime  = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        // Vérifier que le MIME correspond à l'extension
        $safe_mimes = array(
            'pdf'  => array( 'application/pdf' ),
            'png'  => array( 'image/png' ),
            'jpg'  => array( 'image/jpeg' ),
            'jpeg' => array( 'image/jpeg' ),
            'svg'  => array( 'image/svg+xml', 'text/xml', 'application/xml' ),
            'zip'  => array( 'application/zip', 'application/x-zip-compressed' ),
            'ai'   => array( 'application/postscript', 'application/pdf', 'application/illustrator' ),
            'psd'  => array( 'image/vnd.adobe.photoshop', 'application/octet-stream' ),
        );

        if ( isset( $safe_mimes[ $ext ] ) && ! in_array( $mime, $safe_mimes[ $ext ], true ) ) {
            // Permettre application/octet-stream pour certains formats binaires
            if ( ! in_array( $ext, array( 'ai', 'psd', 'zip' ), true ) || 'application/octet-stream' !== $mime ) {
                return new WP_Error( 'mime_mismatch', 'Le type de fichier ne correspond pas à son extension.' );
            }
        }

        // Vérification anti-PHP dans le contenu (sécurité)
        if ( in_array( $ext, array( 'svg' ), true ) ) {
            $content = file_get_contents( $file['tmp_name'] );
            if ( preg_match( '/<script|on\w+\s*=/i', $content ) ) {
                return new WP_Error( 'unsafe_svg', 'Le fichier SVG contient du contenu potentiellement dangereux.' );
            }
        }

        return true;
    }

    /**
     * Récupérer les fichiers d'un client
     *
     * @param int $customer_id ID du client
     * @return array
     */
    public static function get_customer_files( $customer_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_client_files';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE customer_id = %d ORDER BY uploaded_at DESC",
                $customer_id
            ),
            ARRAY_A
        );
    }

    /**
     * Récupérer les fichiers d'une commande
     *
     * @param int $order_id ID de la commande
     * @return array
     */
    public static function get_order_files( $order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_client_files';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE order_id = %d ORDER BY uploaded_at DESC",
                $order_id
            ),
            ARRAY_A
        );
    }

    /**
     * Récupérer les fichiers d'un devis
     *
     * @param int $quote_id ID du devis
     * @return array
     */
    public static function get_quote_files( $quote_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_client_files';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE quote_id = %d ORDER BY uploaded_at DESC",
                $quote_id
            ),
            ARRAY_A
        );
    }

    /**
     * Mettre à jour le statut d'un fichier
     *
     * @param int    $file_id ID du fichier
     * @param string $status  Nouveau statut
     * @return bool
     */
    public static function update_status( $file_id, $status ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_client_files';

        $allowed_statuses = array( 'uploaded', 'received', 'verified', 'rejected', 'processing' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            return false;
        }

        return (bool) $wpdb->update(
            $table,
            array( 'status' => $status ),
            array( 'id' => absint( $file_id ) )
        );
    }

    /**
     * Lier un fichier à une commande
     *
     * @param int $file_id  ID du fichier
     * @param int $order_id ID de la commande
     * @return bool
     */
    public static function link_to_order( $file_id, $order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_client_files';
        return (bool) $wpdb->update(
            $table,
            array( 'order_id' => absint( $order_id ) ),
            array( 'id' => absint( $file_id ) )
        );
    }

    /**
     * Supprimer un fichier
     *
     * @param int $file_id ID du fichier
     * @return bool
     */
    public static function delete_file( $file_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_client_files';

        $file = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $file_id ),
            ARRAY_A
        );

        if ( ! $file ) {
            return false;
        }

        // Supprimer le fichier physique
        if ( file_exists( $file['file_path'] ) ) {
            wp_delete_file( $file['file_path'] );
        }

        // Supprimer l'entrée BDD
        return (bool) $wpdb->delete( $table, array( 'id' => $file_id ) );
    }

    /**
     * Formater la taille du fichier
     *
     * @param int $bytes Taille en octets
     * @return string
     */
    public static function format_file_size( $bytes ) {
        if ( $bytes >= 1048576 ) {
            return round( $bytes / 1048576, 2 ) . ' Mo';
        } elseif ( $bytes >= 1024 ) {
            return round( $bytes / 1024, 2 ) . ' Ko';
        }
        return $bytes . ' octets';
    }
}
