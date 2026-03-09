<?php
/**
 * Print Manager Pro - Uninstall
 *
 * Removes all plugin data when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = array(
    $wpdb->prefix . 'print_expenses',
    $wpdb->prefix . 'print_machines',
    $wpdb->prefix . 'print_cost_settings',
    $wpdb->prefix . 'print_orders',
    $wpdb->prefix . 'print_designs',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Remove options
delete_option( 'pmp_db_version' );

// Remove product meta
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_pmp_%'" );

// Remove uploaded files directory
$upload_dir = wp_upload_dir();
$pmp_uploads = $upload_dir['basedir'] . '/print-manager-pro';
if ( is_dir( $pmp_uploads ) ) {
    // Recursive delete
    $iterator = new RecursiveDirectoryIterator( $pmp_uploads, RecursiveDirectoryIterator::SKIP_DOTS );
    $files = new RecursiveIteratorIterator( $iterator, RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $files as $file ) {
        if ( $file->isDir() ) {
            rmdir( $file->getRealPath() );
        } else {
            unlink( $file->getRealPath() );
        }
    }
    rmdir( $pmp_uploads );
}
