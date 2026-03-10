<?php
/**
 * Désactivation du plugin
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Deactivator {

    /**
     * Actions de désactivation
     */
    public static function deactivate() {
        flush_rewrite_rules();
        delete_option( 'ipm_activated' );
    }
}
