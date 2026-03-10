<?php
/**
 * Désinstallation du plugin
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Supprimer les options
delete_option( 'ipm_settings' );
delete_option( 'ipm_version' );
delete_option( 'ipm_activated' );

// Supprimer les tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ipm_client_files" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ipm_shipping_zones" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ipm_volume_discounts" );

// Supprimer les posts personnalisés
$post_types = array( 'ipm_product', 'ipm_quote' );
foreach ( $post_types as $post_type ) {
    $posts = get_posts( array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ) );
    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }
}

// Supprimer la taxonomie
$terms = get_terms( array(
    'taxonomy'   => 'ipm_category',
    'hide_empty' => false,
    'fields'     => 'ids',
) );
if ( ! is_wp_error( $terms ) ) {
    foreach ( $terms as $term_id ) {
        wp_delete_term( $term_id, 'ipm_category' );
    }
}

// Supprimer les pages créées
$pages = array( 'boutique-impression', 'demande-de-devis', 'suivi-commande', 'upload-fichier', 'calculateur-prix' );
foreach ( $pages as $slug ) {
    $page = get_page_by_path( $slug );
    if ( $page ) {
        wp_delete_post( $page->ID, true );
    }
}

// Supprimer le dossier d'upload
$upload_dir = wp_upload_dir();
$ipm_dir    = $upload_dir['basedir'] . '/ipm-files';
if ( is_dir( $ipm_dir ) ) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $ipm_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ( $files as $file ) {
        if ( $file->isDir() ) {
            rmdir( $file->getRealPath() );
        } else {
            unlink( $file->getRealPath() );
        }
    }
    rmdir( $ipm_dir );
}

// Nettoyer les transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%ipm_rate_%'" );

// Flush rewrite rules
flush_rewrite_rules();
