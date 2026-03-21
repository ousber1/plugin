<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logging to the custom DB table.
 */
class SBP_Logger {

    /**
     * Insert a log entry.
     */
    public static function log( int $post_id, string $action, string $status = 'success', string $details = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sbp_logs';

        $wpdb->insert( $table, [
            'post_id'     => $post_id,
            'action_type' => sanitize_key( $action ),
            'status'      => sanitize_key( $status ),
            'details'     => sanitize_textarea_field( $details ),
            'created_at'  => current_time( 'mysql' ),
        ], [ '%d', '%s', '%s', '%s', '%s' ] );
    }

    /**
     * Retrieve log entries.
     */
    public static function get_logs( int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sbp_logs';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Count total log entries.
     */
    public static function count(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'sbp_logs';

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }
}
