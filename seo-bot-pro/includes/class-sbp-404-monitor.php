<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 404 Monitor – logs broken URLs and manages redirects.
 */
class SBP_404_Monitor {

    /**
     * Hook into template_redirect to catch 404s.
     */
    public function init() {
        if ( SBP_Helpers::get_option( 'enable_404_monitor', '0' ) === '1' ) {
            add_action( 'template_redirect', [ $this, 'log_404' ], 999 );
        }
    }

    /**
     * Log a 404 hit.
     */
    public function log_404() {
        if ( ! is_404() ) {
            return;
        }

        // Skip bots and crawlers for common static assets
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( empty( $uri ) ) {
            return;
        }

        // Skip common static asset extensions
        if ( preg_match( '/\.(css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot|map)$/i', $uri ) ) {
            return;
        }

        $referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
        $ua       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $logs = get_option( 'sbp_404_logs', [] );

        // Check if URL already logged
        $found = false;
        foreach ( $logs as &$entry ) {
            if ( $entry['url'] === $uri ) {
                $entry['hits']++;
                $entry['last_hit'] = current_time( 'mysql' );
                if ( $referrer && ! in_array( $referrer, $entry['referrers'], true ) ) {
                    $entry['referrers'][] = $referrer;
                    if ( count( $entry['referrers'] ) > 5 ) {
                        $entry['referrers'] = array_slice( $entry['referrers'], -5 );
                    }
                }
                $found = true;
                break;
            }
        }
        unset( $entry );

        if ( ! $found ) {
            $logs[] = [
                'url'       => $uri,
                'hits'      => 1,
                'first_hit' => current_time( 'mysql' ),
                'last_hit'  => current_time( 'mysql' ),
                'referrers' => $referrer ? [ $referrer ] : [],
            ];
        }

        // Keep max 200 entries, sorted by hits
        usort( $logs, function ( $a, $b ) {
            return $b['hits'] - $a['hits'];
        } );
        $logs = array_slice( $logs, 0, 200 );

        update_option( 'sbp_404_logs', $logs, false );
    }

    /**
     * Get logged 404s.
     */
    public function get_logs( int $limit = 50 ): array {
        $logs = get_option( 'sbp_404_logs', [] );
        return array_slice( $logs, 0, $limit );
    }

    /**
     * Clear all 404 logs.
     */
    public function clear_logs() {
        delete_option( 'sbp_404_logs' );
    }

    /**
     * Get all redirects.
     */
    public function get_redirects(): array {
        return get_option( 'sbp_redirects', [] );
    }

    /**
     * Add a redirect.
     */
    public function add_redirect( string $from, string $to, int $code = 301 ): bool {
        $from = sanitize_text_field( $from );
        $to   = esc_url_raw( $to );

        if ( empty( $from ) || empty( $to ) ) {
            return false;
        }

        $redirects = $this->get_redirects();

        // Update if exists
        foreach ( $redirects as &$r ) {
            if ( $r['from'] === $from ) {
                $r['to']   = $to;
                $r['code'] = $code;
                update_option( 'sbp_redirects', $redirects );
                return true;
            }
        }
        unset( $r );

        $redirects[] = [
            'from'    => $from,
            'to'      => $to,
            'code'    => $code,
            'created' => current_time( 'mysql' ),
        ];

        update_option( 'sbp_redirects', $redirects );
        return true;
    }

    /**
     * Delete a redirect by index.
     */
    public function delete_redirect( int $index ): bool {
        $redirects = $this->get_redirects();
        if ( ! isset( $redirects[ $index ] ) ) {
            return false;
        }
        array_splice( $redirects, $index, 1 );
        update_option( 'sbp_redirects', $redirects );
        return true;
    }

    /**
     * Process redirects on template_redirect (runs before 404 check).
     */
    public function process_redirects() {
        $uri       = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $redirects = $this->get_redirects();

        foreach ( $redirects as $r ) {
            if ( $r['from'] === $uri || trailingslashit( $r['from'] ) === $uri || untrailingslashit( $r['from'] ) === $uri ) {
                wp_redirect( $r['to'], $r['code'] );
                exit;
            }
        }
    }
}
