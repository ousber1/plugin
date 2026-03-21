<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * XML Sitemap generator – creates and serves a dynamic sitemap.
 */
class SBP_Sitemap {

    /**
     * Register rewrite rules and hooks.
     */
    public function init() {
        if ( SBP_Helpers::get_option( 'enable_sitemap', '0' ) !== '1' ) {
            return;
        }

        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'serve_sitemap' ] );
    }

    /**
     * Add rewrite rule for /sbp-sitemap.xml.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( '^sbp-sitemap\.xml$', 'index.php?sbp_sitemap=1', 'top' );
        add_rewrite_rule( '^sbp-sitemap-([a-z]+)-?(\d*)\.xml$', 'index.php?sbp_sitemap=1&sbp_sitemap_type=$matches[1]&sbp_sitemap_page=$matches[2]', 'top' );
    }

    /**
     * Register custom query vars.
     */
    public function add_query_vars( array $vars ): array {
        $vars[] = 'sbp_sitemap';
        $vars[] = 'sbp_sitemap_type';
        $vars[] = 'sbp_sitemap_page';
        return $vars;
    }

    /**
     * Serve the sitemap when requested.
     */
    public function serve_sitemap() {
        if ( ! get_query_var( 'sbp_sitemap' ) ) {
            return;
        }

        $type = get_query_var( 'sbp_sitemap_type', '' );
        $page = max( 1, (int) get_query_var( 'sbp_sitemap_page', 1 ) );

        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        if ( empty( $type ) ) {
            echo $this->generate_index(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo $this->generate_urlset( $type, $page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        exit;
    }

    /**
     * Generate the sitemap index (links to sub-sitemaps).
     */
    private function generate_index(): string {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ( SBP_Helpers::post_types() as $type ) {
            $count = $this->get_post_count( $type );
            $pages = max( 1, (int) ceil( $count / 1000 ) );

            for ( $i = 1; $i <= $pages; $i++ ) {
                $suffix = $pages > 1 ? $i : '';
                $xml   .= '  <sitemap>' . "\n";
                $xml   .= '    <loc>' . esc_url( home_url( "/sbp-sitemap-{$type}-{$suffix}.xml" ) ) . '</loc>' . "\n";
                $xml   .= '    <lastmod>' . esc_html( $this->get_last_modified( $type ) ) . '</lastmod>' . "\n";
                $xml   .= '  </sitemap>' . "\n";
            }
        }

        $xml .= '</sitemapindex>';
        return $xml;
    }

    /**
     * Generate a urlset for a specific post type.
     */
    private function generate_urlset( string $type, int $page = 1 ): string {
        $per_page = 1000;
        $offset   = ( $page - 1 ) * $per_page;

        $posts = get_posts( [
            'post_type'      => sanitize_key( $type ),
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_sbp_noindex',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_sbp_noindex',
                    'value'   => '1',
                    'compare' => '!=',
                ],
            ],
        ] );

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Add homepage
        if ( $type === 'page' && $page === 1 ) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url( home_url( '/' ) ) . '</loc>' . "\n";
            $xml .= '    <changefreq>daily</changefreq>' . "\n";
            $xml .= '    <priority>1.0</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        foreach ( $posts as $post_id ) {
            $permalink = get_permalink( $post_id );
            $modified  = get_post_modified_time( 'Y-m-d\TH:i:sP', true, $post_id );
            $priority  = $this->get_priority( $post_id, $type );
            $changefreq = $this->get_changefreq( $post_id );

            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url( $permalink ) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . esc_html( $modified ) . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . esc_html( $changefreq ) . '</changefreq>' . "\n";
            $xml .= '    <priority>' . esc_html( $priority ) . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Get post count for a type.
     */
    private function get_post_count( string $type ): int {
        $counts = wp_count_posts( $type );
        return (int) ( $counts->publish ?? 0 );
    }

    /**
     * Get last modified date for a post type.
     */
    private function get_last_modified( string $type ): string {
        global $wpdb;
        $date = $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $type
        ) );
        return $date ? gmdate( 'Y-m-d\TH:i:s+00:00', strtotime( $date ) ) : gmdate( 'Y-m-d\TH:i:s+00:00' );
    }

    /**
     * Calculate priority based on post type and age.
     */
    private function get_priority( int $post_id, string $type ): string {
        if ( $type === 'page' ) {
            $is_front = (int) get_option( 'page_on_front' ) === $post_id;
            return $is_front ? '1.0' : '0.8';
        }

        if ( $type === 'product' ) {
            return '0.7';
        }

        // Posts: newer = higher priority
        $age_days = ( time() - get_post_time( 'U', true, $post_id ) ) / DAY_IN_SECONDS;
        if ( $age_days < 7 ) {
            return '0.9';
        }
        if ( $age_days < 30 ) {
            return '0.7';
        }
        if ( $age_days < 180 ) {
            return '0.5';
        }
        return '0.4';
    }

    /**
     * Calculate change frequency based on post age.
     */
    private function get_changefreq( int $post_id ): string {
        $age_days = ( time() - get_post_modified_time( 'U', true, $post_id ) ) / DAY_IN_SECONDS;

        if ( $age_days < 1 ) {
            return 'hourly';
        }
        if ( $age_days < 7 ) {
            return 'daily';
        }
        if ( $age_days < 30 ) {
            return 'weekly';
        }
        if ( $age_days < 180 ) {
            return 'monthly';
        }
        return 'yearly';
    }

    /**
     * Get all URLs for the sitemap (used for bulk IndexNow submission).
     */
    public function get_all_urls(): array {
        $urls = [ home_url( '/' ) ];

        foreach ( SBP_Helpers::post_types() as $type ) {
            $posts = get_posts( [
                'post_type'      => $type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => '_sbp_noindex',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_sbp_noindex',
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                ],
            ] );

            foreach ( $posts as $pid ) {
                $urls[] = get_permalink( $pid );
            }
        }

        return $urls;
    }
}
