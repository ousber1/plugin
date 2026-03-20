<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Auto-indexing service – pings search engines and submits URLs for fast crawling.
 *
 * Supports: IndexNow (Bing, Yandex, DuckDuckGo, Naver), Google Ping, Bing Ping.
 */
class SBP_Indexing {

    /**
     * Ping all enabled search engines for a given URL.
     *
     * @param string $url       The URL to submit.
     * @param int    $post_id   Optional post ID for logging.
     * @return array            Results per engine.
     */
    public function ping_all( string $url, int $post_id = 0 ): array {
        $results = [];

        if ( SBP_Helpers::get_option( 'ping_google', '1' ) === '1' ) {
            $results['google'] = $this->ping_google( $url );
        }

        if ( SBP_Helpers::get_option( 'ping_bing', '1' ) === '1' ) {
            $results['bing'] = $this->ping_bing( $url );
        }

        if ( SBP_Helpers::get_option( 'enable_indexnow', '0' ) === '1' ) {
            $results['indexnow'] = $this->submit_indexnow( $url );
        }

        if ( $post_id ) {
            SBP_Logger::log( $post_id, 'index_ping', 'success', wp_json_encode( $results ) );
        }

        return $results;
    }

    /**
     * Ping Google via their sitemap submission endpoint.
     */
    public function ping_google( string $url ): array {
        $sitemap_url = $this->ensure_sitemap_url( $url );
        $ping_url    = 'https://www.google.com/ping?sitemap=' . rawurlencode( $sitemap_url );

        $response = wp_remote_get( $ping_url, [
            'timeout'    => 15,
            'user-agent' => 'SEO Bot Pro/' . SBP_VERSION . ' (WordPress)',
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        return [
            'success' => $code >= 200 && $code < 400,
            'code'    => $code,
            'message' => $code >= 200 && $code < 400 ? 'Sitemap submitted' : 'HTTP ' . $code,
        ];
    }

    /**
     * Ping Bing via their sitemap submission endpoint.
     */
    public function ping_bing( string $url ): array {
        $sitemap_url = $this->ensure_sitemap_url( $url );
        $ping_url    = 'https://www.bing.com/ping?sitemap=' . rawurlencode( $sitemap_url );

        $response = wp_remote_get( $ping_url, [
            'timeout'    => 15,
            'user-agent' => 'SEO Bot Pro/' . SBP_VERSION . ' (WordPress)',
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        return [
            'success' => $code >= 200 && $code < 400,
            'code'    => $code,
            'message' => $code >= 200 && $code < 400 ? 'Sitemap submitted' : 'HTTP ' . $code,
        ];
    }

    /**
     * Ensure we have a valid sitemap URL (not just homepage).
     */
    private function ensure_sitemap_url( string $url ): string {
        // If the URL doesn't look like a sitemap, use the plugin's sitemap
        if ( strpos( $url, 'sitemap' ) === false && strpos( $url, '.xml' ) === false ) {
            // Check if plugin sitemap is enabled
            if ( SBP_Helpers::get_option( 'enable_sitemap', '0' ) === '1' ) {
                return home_url( '/sbp-sitemap.xml' );
            }
            // Fall back to WordPress default sitemap
            return home_url( '/wp-sitemap.xml' );
        }
        return $url;
    }

    /**
     * Submit URL to IndexNow API (Bing, Yandex, DuckDuckGo, Naver, Seznam, Yep).
     */
    public function submit_indexnow( string $url ): array {
        $api_key = SBP_Helpers::get_option( 'indexnow_api_key', '' );
        if ( empty( $api_key ) ) {
            return [ 'success' => false, 'error' => 'IndexNow API key not configured.' ];
        }

        $host = wp_parse_url( home_url(), PHP_URL_HOST );

        $body = [
            'host'    => $host,
            'key'     => $api_key,
            'urlList' => [ $url ],
        ];

        $response = wp_remote_post( 'https://api.indexnow.org/IndexNow', [
            'timeout' => 15,
            'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        return [ 'success' => $code >= 200 && $code < 300, 'code' => $code ];
    }

    /**
     * Bulk submit multiple URLs to IndexNow.
     */
    public function bulk_submit_indexnow( array $urls ): array {
        $api_key = SBP_Helpers::get_option( 'indexnow_api_key', '' );
        if ( empty( $api_key ) ) {
            return [ 'success' => false, 'error' => 'IndexNow API key not configured.' ];
        }

        $host = wp_parse_url( home_url(), PHP_URL_HOST );

        // IndexNow supports up to 10,000 URLs per request
        $chunks  = array_chunk( $urls, 10000 );
        $results = [];

        foreach ( $chunks as $chunk ) {
            $body = [
                'host'    => $host,
                'key'     => $api_key,
                'urlList' => $chunk,
            ];

            $response = wp_remote_post( 'https://api.indexnow.org/IndexNow', [
                'timeout' => 30,
                'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                'body'    => wp_json_encode( $body ),
            ] );

            if ( is_wp_error( $response ) ) {
                $results[] = [ 'success' => false, 'error' => $response->get_error_message(), 'count' => count( $chunk ) ];
            } else {
                $code      = wp_remote_retrieve_response_code( $response );
                $results[] = [ 'success' => $code >= 200 && $code < 300, 'code' => $code, 'count' => count( $chunk ) ];
            }
        }

        return $results;
    }

    /**
     * Auto-ping on post publish/update.
     */
    public function on_publish( int $post_id, WP_Post $post ) {
        if ( ! in_array( $post->post_type, SBP_Helpers::post_types(), true ) ) {
            return;
        }

        if ( SBP_Helpers::get_option( 'auto_ping_publish', '0' ) !== '1' ) {
            return;
        }

        $url = get_permalink( $post_id );
        if ( ! $url ) {
            return;
        }

        // Ping the individual URL
        $this->ping_all( $url, $post_id );

        // Also ping sitemap if enabled
        if ( SBP_Helpers::get_option( 'enable_sitemap', '0' ) === '1' ) {
            $sitemap_url = home_url( '/sbp-sitemap.xml' );
            $this->ping_google( $sitemap_url );
            $this->ping_bing( $sitemap_url );
        }
    }

    /**
     * Submit the sitemap URL to all search engines.
     */
    public function ping_sitemap(): array {
        $sitemap_url = home_url( '/sbp-sitemap.xml' );
        $results     = [];

        if ( SBP_Helpers::get_option( 'ping_google', '1' ) === '1' ) {
            $results['google'] = $this->ping_google( $sitemap_url );
        }

        if ( SBP_Helpers::get_option( 'ping_bing', '1' ) === '1' ) {
            $results['bing'] = $this->ping_bing( $sitemap_url );
        }

        return $results;
    }

    /**
     * Get the IndexNow key file content for verification.
     */
    public static function get_indexnow_key_content(): string {
        return SBP_Helpers::get_option( 'indexnow_api_key', '' );
    }

    /**
     * Serve the IndexNow key verification file.
     * Hooked into template_redirect.
     */
    public function serve_indexnow_key() {
        $api_key = SBP_Helpers::get_option( 'indexnow_api_key', '' );
        if ( empty( $api_key ) ) {
            return;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $expected    = '/' . $api_key . '.txt';

        if ( $request_uri === $expected ) {
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo esc_html( $api_key );
            exit;
        }
    }
}
