<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * File Upload handler for Print Manager Pro.
 */
class PMP_Uploader {

    /**
     * Allowed file types.
     */
    private $allowed_types = array(
        'application/pdf'                                                   => 'pdf',
        'application/postscript'                                            => 'ai',
        'image/vnd.adobe.photoshop'                                         => 'psd',
        'application/x-photoshop'                                           => 'psd',
        'image/png'                                                         => 'png',
        'image/jpeg'                                                        => 'jpg',
    );

    /**
     * Max file size in bytes (100 MB).
     */
    private $max_size = 104857600;

    /**
     * Handle file upload via AJAX.
     *
     * @return array|WP_Error Upload result.
     */
    public function handle_upload() {
        if ( empty( $_FILES['pmp_file'] ) ) {
            return new WP_Error( 'no_file', 'Aucun fichier sélectionné.' );
        }

        $file = $_FILES['pmp_file'];

        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', $this->get_upload_error_message( $file['error'] ) );
        }

        // Check file size
        if ( $file['size'] > $this->max_size ) {
            return new WP_Error( 'file_too_large', 'Le fichier dépasse la taille maximale de 100 Mo.' );
        }

        // Validate file extension
        $extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $allowed_extensions = array( 'pdf', 'ai', 'psd', 'png', 'jpg', 'jpeg' );

        if ( ! in_array( $extension, $allowed_extensions, true ) ) {
            return new WP_Error( 'invalid_type', 'Type de fichier non autorisé. Formats acceptés : PDF, AI, PSD, PNG, JPG.' );
        }

        // Allow additional MIME types for WordPress upload
        add_filter( 'upload_mimes', array( $this, 'add_custom_mimes' ) );

        // Use WordPress upload handling
        $upload_dir = wp_upload_dir();
        $pmp_dir = $upload_dir['basedir'] . '/print-manager-pro/' . date( 'Y/m' );

        if ( ! file_exists( $pmp_dir ) ) {
            wp_mkdir_p( $pmp_dir );
        }

        // Secure filename
        $filename = wp_unique_filename( $pmp_dir, sanitize_file_name( $file['name'] ) );
        $filepath = $pmp_dir . '/' . $filename;

        // Move uploaded file
        if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
            return new WP_Error( 'move_failed', 'Erreur lors du déplacement du fichier.' );
        }

        // Set correct permissions
        chmod( $filepath, 0644 );

        // Create .htaccess to protect the directory
        $this->protect_upload_directory( $upload_dir['basedir'] . '/print-manager-pro/' );

        $file_url = $upload_dir['baseurl'] . '/print-manager-pro/' . date( 'Y/m' ) . '/' . $filename;

        remove_filter( 'upload_mimes', array( $this, 'add_custom_mimes' ) );

        return array(
            'file_url'  => $file_url,
            'file_path' => $filepath,
            'file_name' => $filename,
            'file_size' => $file['size'],
            'file_type' => $extension,
            'message'   => 'Fichier téléchargé avec succès.',
        );
    }

    /**
     * Add custom MIME types.
     */
    public function add_custom_mimes( $mimes ) {
        $mimes['ai']  = 'application/postscript';
        $mimes['psd'] = 'image/vnd.adobe.photoshop';
        return $mimes;
    }

    /**
     * Protect upload directory with .htaccess.
     */
    private function protect_upload_directory( $dir ) {
        $htaccess = $dir . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $content = "Options -Indexes\n";
            $content .= "<FilesMatch '\.(php|php5|phtml|cgi|pl|py|sh)$'>\n";
            $content .= "    Deny from all\n";
            $content .= "</FilesMatch>\n";
            file_put_contents( $htaccess, $content );
        }
    }

    /**
     * Get human-readable upload error message.
     */
    private function get_upload_error_message( $error_code ) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
            UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale autorisée.',
            UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement téléchargé.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier n\'a été téléchargé.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque.',
            UPLOAD_ERR_EXTENSION  => 'Une extension PHP a arrêté l\'envoi du fichier.',
        );

        return isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : 'Erreur inconnue lors du téléchargement.';
    }
}
