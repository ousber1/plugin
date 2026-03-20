<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scheduled (CRON) optimization.
 */
class SBP_Cron {

    /**
     * Daily job: optimize new posts and posts missing meta.
     */
    public function run() {
        $ai = new SBP_AI_Service();
        if ( ! $ai->is_configured() ) {
            return;
        }

        $post_types = SBP_Helpers::post_types();

        // 1. New posts published in the last 24 hours without meta
        $recent = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'date_query'     => [
                [ 'after' => '24 hours ago' ],
            ],
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_sbp_meta_title',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'   => '_sbp_meta_title',
                    'value' => '',
                ],
            ],
            'fields'         => 'ids',
        ] );

        // 2. Older posts without any SEO meta (limit 10)
        $older = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'date_query'     => [
                [ 'before' => '24 hours ago' ],
            ],
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_sbp_meta_title',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'   => '_sbp_meta_title',
                    'value' => '',
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ] );

        $post_ids = array_unique( array_merge( $recent, $older ) );

        $api = new SBP_REST_API();

        foreach ( $post_ids as $pid ) {
            $result = $ai->optimize( $pid );

            if ( is_wp_error( $result ) ) {
                SBP_Logger::log( $pid, 'cron', 'error', $result->get_error_message() );
                continue;
            }

            // Apply meta
            if ( ! empty( $result['meta_title'] ) ) {
                update_post_meta( $pid, 'rank_math_title', sanitize_text_field( $result['meta_title'] ) );
                update_post_meta( $pid, '_sbp_meta_title', sanitize_text_field( $result['meta_title'] ) );
            }
            if ( ! empty( $result['meta_description'] ) ) {
                update_post_meta( $pid, 'rank_math_description', sanitize_text_field( $result['meta_description'] ) );
                update_post_meta( $pid, '_sbp_meta_description', sanitize_text_field( $result['meta_description'] ) );
            }

            SBP_Logger::log( $pid, 'cron', 'success', wp_json_encode( $result ) );

            // Small delay to avoid rate-limiting
            sleep( 2 );
        }
    }
}
