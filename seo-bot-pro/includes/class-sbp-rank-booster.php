<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Rank Booster – features to accelerate search engine rankings.
 *
 * - Content freshness: update modified dates on stale content
 * - Stale content detection and AI rewrite scheduling
 * - Auto internal linking on publish
 * - Orphan page detection
 * - Thin content detection
 * - Keyword cannibalization check
 */
class SBP_Rank_Booster {

    /**
     * Get stale content that hasn't been updated in X days.
     *
     * @param int $days  Minimum days since last update.
     * @param int $limit Max posts to return.
     * @return array     Array of post objects with metadata.
     */
    public function get_stale_content( int $days = 90, int $limit = 50 ): array {
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $posts = get_posts( [
            'post_type'      => SBP_Helpers::post_types(),
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'date_query'     => [
                [
                    'column' => 'post_modified_gmt',
                    'before' => $cutoff,
                ],
            ],
            'orderby'        => 'modified',
            'order'          => 'ASC',
        ] );

        $results = [];
        foreach ( $posts as $post ) {
            $days_old    = (int) ( ( time() - strtotime( $post->post_modified_gmt ) ) / DAY_IN_SECONDS );
            $word_count  = str_word_count( wp_strip_all_tags( $post->post_content ) );
            $has_meta    = ! empty( get_post_meta( $post->ID, '_sbp_meta_title', true ) );

            $results[] = [
                'ID'         => $post->ID,
                'title'      => $post->post_title,
                'post_type'  => $post->post_type,
                'modified'   => $post->post_modified,
                'days_stale' => $days_old,
                'word_count' => $word_count,
                'has_seo'    => $has_meta,
                'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
                'permalink'  => get_permalink( $post->ID ),
            ];
        }

        return $results;
    }

    /**
     * Touch a post to refresh its modified date (signals freshness to search engines).
     *
     * @param int $post_id
     * @return bool
     */
    public function refresh_modified_date( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return false;
        }

        $result = wp_update_post( [
            'ID'                => $post_id,
            'post_modified'     => current_time( 'mysql' ),
            'post_modified_gmt' => current_time( 'mysql', true ),
        ] );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        SBP_Logger::log( $post_id, 'refresh_date', 'success', 'Modified date refreshed' );
        return true;
    }

    /**
     * Bulk refresh modified dates for stale content.
     *
     * @param int $days   Posts older than this many days.
     * @param int $limit  Max posts to refresh.
     * @return array      { refreshed: int, total: int }
     */
    public function bulk_refresh( int $days = 90, int $limit = 20 ): array {
        $stale     = $this->get_stale_content( $days, $limit );
        $refreshed = 0;

        foreach ( $stale as $item ) {
            if ( $this->refresh_modified_date( $item['ID'] ) ) {
                $refreshed++;
            }
        }

        return [ 'refreshed' => $refreshed, 'total' => count( $stale ) ];
    }

    /**
     * Detect orphan pages (no internal links pointing to them).
     *
     * @param int $limit
     * @return array
     */
    public function get_orphan_pages( int $limit = 50 ): array {
        global $wpdb;

        $post_types = SBP_Helpers::post_types();
        $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        // Get all published posts
        $all_posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title, post_type, guid FROM {$wpdb->posts}
             WHERE post_type IN ({$placeholders}) AND post_status = 'publish'
             ORDER BY post_date DESC LIMIT %d",
            array_merge( $post_types, [ $limit * 3 ] )
        ) );

        // Check each post to see if any other post links to it
        $orphans = [];
        foreach ( $all_posts as $post ) {
            $permalink = get_permalink( $post->ID );
            $slug      = basename( untrailingslashit( $permalink ) );

            if ( empty( $slug ) ) {
                continue;
            }

            // Search for internal links to this post in other posts
            $linked = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND ID != %d
                 AND post_content LIKE %s",
                $post->ID,
                '%' . $wpdb->esc_like( $slug ) . '%'
            ) );

            if ( (int) $linked === 0 ) {
                $orphans[] = [
                    'ID'        => $post->ID,
                    'title'     => $post->post_title,
                    'post_type' => $post->post_type,
                    'permalink' => $permalink,
                    'edit_url'  => get_edit_post_link( $post->ID, 'raw' ),
                ];
            }

            if ( count( $orphans ) >= $limit ) {
                break;
            }
        }

        return $orphans;
    }

    /**
     * Detect thin content (posts with very few words).
     *
     * @param int $min_words  Minimum word count threshold.
     * @param int $limit
     * @return array
     */
    public function get_thin_content( int $min_words = 300, int $limit = 50 ): array {
        $posts = get_posts( [
            'post_type'      => SBP_Helpers::post_types(),
            'post_status'    => 'publish',
            'posts_per_page' => $limit * 2,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $thin = [];
        foreach ( $posts as $post ) {
            $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
            if ( $word_count < $min_words ) {
                $thin[] = [
                    'ID'         => $post->ID,
                    'title'      => $post->post_title,
                    'post_type'  => $post->post_type,
                    'word_count' => $word_count,
                    'permalink'  => get_permalink( $post->ID ),
                    'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
                ];
            }

            if ( count( $thin ) >= $limit ) {
                break;
            }
        }

        return $thin;
    }

    /**
     * Detect keyword cannibalization (multiple posts targeting the same keyword).
     *
     * @return array  Grouped by keyword.
     */
    public function get_keyword_cannibalization(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT pm.meta_value AS keyword, pm.post_id, p.post_title
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sbp_focus_keyword'
             AND pm.meta_value != ''
             AND p.post_status = 'publish'
             ORDER BY pm.meta_value ASC"
        );

        // Group by keyword
        $grouped = [];
        foreach ( $rows as $row ) {
            $kw = strtolower( trim( $row->keyword ) );
            if ( ! isset( $grouped[ $kw ] ) ) {
                $grouped[ $kw ] = [];
            }
            $grouped[ $kw ][] = [
                'ID'    => $row->post_id,
                'title' => $row->post_title,
            ];
        }

        // Only return keywords used by 2+ posts
        $cannibalized = [];
        foreach ( $grouped as $keyword => $posts ) {
            if ( count( $posts ) >= 2 ) {
                $cannibalized[ $keyword ] = $posts;
            }
        }

        return $cannibalized;
    }

    /**
     * Get a comprehensive rank boost report / dashboard stats.
     */
    public function get_stats(): array {
        $freshness_days = (int) SBP_Helpers::get_option( 'freshness_days', 90 );

        return [
            'stale_count'        => count( $this->get_stale_content( $freshness_days, 100 ) ),
            'orphan_count'       => count( $this->get_orphan_pages( 100 ) ),
            'thin_count'         => count( $this->get_thin_content( 300, 100 ) ),
            'cannibalized_count' => count( $this->get_keyword_cannibalization() ),
            'freshness_days'     => $freshness_days,
        ];
    }

    /**
     * CRON job: auto-refresh stale content dates and re-ping.
     */
    public function cron_refresh_stale() {
        if ( SBP_Helpers::get_option( 'enable_freshness', '0' ) !== '1' ) {
            return;
        }

        $days  = (int) SBP_Helpers::get_option( 'freshness_days', 90 );
        $result = $this->bulk_refresh( $days, 10 );

        // Ping search engines for refreshed content
        if ( $result['refreshed'] > 0 && SBP_Helpers::get_option( 'auto_ping_publish', '0' ) === '1' ) {
            $indexing = new SBP_Indexing();
            $indexing->ping_sitemap();
        }
    }
}
