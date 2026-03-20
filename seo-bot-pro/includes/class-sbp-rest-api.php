<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API + AJAX handler.
 */
class SBP_REST_API {

    // ── REST routes ─────────────────────────────────

    public function register_routes() {
        register_rest_route( 'seo-bot/v1', '/optimize', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_optimize' ],
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'args' => [
                'post_id' => [
                    'required'          => true,
                    'validate_callback' => function ( $v ) {
                        return is_numeric( $v ) && (int) $v > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
    }

    /**
     * REST: POST /seo-bot/v1/optimize
     */
    public function rest_optimize( WP_REST_Request $request ) {
        $post_id = $request->get_param( 'post_id' );

        $ai     = new SBP_AI_Service();
        $result = $ai->optimize( $post_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
        }

        $this->apply_meta( $post_id, $result );

        SBP_Logger::log( $post_id, 'optimize', 'success', wp_json_encode( $result ) );

        return new WP_REST_Response( [
            'success' => true,
            'data'    => $result,
        ] );
    }

    // ── AJAX endpoints ──────────────────────────────

    /**
     * Optimize a single post via AJAX.
     */
    public function ajax_optimize_post() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );

        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $ai     = new SBP_AI_Service();
        $result = $ai->optimize( $post_id );

        if ( is_wp_error( $result ) ) {
            SBP_Logger::log( $post_id, 'optimize', 'error', $result->get_error_message() );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $this->apply_meta( $post_id, $result );
        SBP_Logger::log( $post_id, 'optimize', 'success', wp_json_encode( $result ) );

        wp_send_json_success( $result );
    }

    /**
     * Bulk optimize via AJAX (processes one at a time, called per-post from JS).
     */
    public function ajax_bulk_optimize() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );

        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $ai     = new SBP_AI_Service();
        $result = $ai->optimize( $post_id );

        if ( is_wp_error( $result ) ) {
            SBP_Logger::log( $post_id, 'bulk_optimize', 'error', $result->get_error_message() );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $this->apply_meta( $post_id, $result );
        SBP_Logger::log( $post_id, 'bulk_optimize', 'success', wp_json_encode( $result ) );

        wp_send_json_success( $result );
    }

    /**
     * Generate FAQ via AJAX.
     */
    public function ajax_generate_faq() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );

        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $faq_gen = new SBP_FAQ_Generator();
        $result  = $faq_gen->generate( $post_id );

        if ( is_wp_error( $result ) ) {
            SBP_Logger::log( $post_id, 'faq', 'error', $result->get_error_message() );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        SBP_Logger::log( $post_id, 'faq', 'success', wp_json_encode( $result ) );
        wp_send_json_success( $result );
    }

    /**
     * Suggest internal links via AJAX.
     */
    public function ajax_suggest_links() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );

        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $linker = new SBP_Internal_Links();
        $result = $linker->suggest( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    /**
     * Fix image ALTs via AJAX.
     */
    public function ajax_fix_image_alts() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );

        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $fixer  = new SBP_Image_Alt();
        $result = $fixer->fix( $post_id );

        if ( is_wp_error( $result ) ) {
            SBP_Logger::log( $post_id, 'image_alt', 'error', $result->get_error_message() );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        SBP_Logger::log( $post_id, 'image_alt', 'success', wp_json_encode( $result ) );
        wp_send_json_success( $result );
    }

    /**
     * Analyze content via AJAX.
     */
    public function ajax_analyze_content() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );

        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $keyword = sanitize_text_field( $_POST['keyword'] ?? '' );

        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $analyzer = new SBP_Content_Analysis();
        $result   = $analyzer->analyze( $post_id, $keyword );

        wp_send_json_success( $result );
    }

    // ── Helpers ─────────────────────────────────────

    /**
     * Apply optimized meta to the post (Rank Math compatible).
     */
    private function apply_meta( int $post_id, array $data ) {
        if ( ! empty( $data['meta_title'] ) ) {
            $title = sanitize_text_field( $data['meta_title'] );
            update_post_meta( $post_id, 'rank_math_title', $title );
            // Fallback generic meta
            update_post_meta( $post_id, '_sbp_meta_title', $title );
        }

        if ( ! empty( $data['meta_description'] ) ) {
            $desc = sanitize_text_field( $data['meta_description'] );
            update_post_meta( $post_id, 'rank_math_description', $desc );
            update_post_meta( $post_id, '_sbp_meta_description', $desc );
        }
    }
}
