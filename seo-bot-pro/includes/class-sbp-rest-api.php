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
            'success'  => true,
            'provider' => $ai->get_provider(),
            'data'     => $result,
        ] );
    }

    // ── AJAX endpoints ──────────────────────────────

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

    /**
     * Generate keywords via AJAX.
     */
    public function ajax_generate_keywords() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );

        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $ai     = new SBP_AI_Service();
        $result = $ai->generate_keywords( $post_id );

        if ( is_wp_error( $result ) ) {
            SBP_Logger::log( $post_id, 'keywords', 'error', $result->get_error_message() );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // Store keywords as post meta
        if ( ! empty( $result['primary'] ) ) {
            update_post_meta( $post_id, '_sbp_focus_keyword', sanitize_text_field( $result['primary'] ) );

            // Rank Math focus keyword
            $seo_plugin = SBP_Helpers::get_option( 'seo_plugin', 'rank_math' );
            if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
                update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $result['primary'] ) );
            }
            if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $result['primary'] ) );
            }
        }
        if ( ! empty( $result['keywords'] ) && is_array( $result['keywords'] ) ) {
            $kw_string = implode( ', ', array_map( 'sanitize_text_field', $result['keywords'] ) );
            update_post_meta( $post_id, '_sbp_keywords', $kw_string );
        }

        SBP_Logger::log( $post_id, 'keywords', 'success', wp_json_encode( $result ) );
        wp_send_json_success( $result );
    }

    /**
     * Optimize slug via AJAX.
     */
    public function ajax_optimize_slug() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );

        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $ai     = new SBP_AI_Service();
        $result = $ai->optimize_slug( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // Apply the slug
        if ( ! empty( $result['slug'] ) ) {
            $slug = sanitize_title( $result['slug'] );
            wp_update_post( [
                'ID'        => $post_id,
                'post_name' => $slug,
            ] );
            $result['slug']      = $slug;
            $result['permalink'] = get_permalink( $post_id );
        }

        SBP_Logger::log( $post_id, 'slug', 'success', wp_json_encode( $result ) );
        wp_send_json_success( $result );
    }

    /**
     * Called on post publish to auto-optimize.
     */
    public function auto_optimize_on_publish( int $post_id, WP_Post $post ) {
        // Only for supported post types
        if ( ! in_array( $post->post_type, SBP_Helpers::post_types(), true ) ) {
            return;
        }

        // Skip if already optimized
        if ( get_post_meta( $post_id, '_sbp_meta_title', true ) ) {
            return;
        }

        $ai = new SBP_AI_Service();
        if ( ! $ai->is_configured() ) {
            return;
        }

        $result = $ai->optimize( $post_id );
        if ( is_wp_error( $result ) ) {
            SBP_Logger::log( $post_id, 'auto_publish', 'error', $result->get_error_message() );
            return;
        }

        $this->apply_meta( $post_id, $result );
        SBP_Logger::log( $post_id, 'auto_publish', 'success', wp_json_encode( $result ) );
    }

    // ── Helpers ─────────────────────────────────────

    /**
     * Apply optimized meta to the post (Rank Math + Yoast compatible).
     */
    private function apply_meta( int $post_id, array $data ) {
        $seo_plugin = SBP_Helpers::get_option( 'seo_plugin', 'rank_math' );
        $enable_og  = SBP_Helpers::get_option( 'enable_og', '1' );

        // Meta title
        if ( ! empty( $data['meta_title'] ) ) {
            $title = sanitize_text_field( $data['meta_title'] );
            update_post_meta( $post_id, '_sbp_meta_title', $title );

            if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
                update_post_meta( $post_id, 'rank_math_title', $title );
            }
            if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_title', $title );
            }
        }

        // Meta description
        if ( ! empty( $data['meta_description'] ) ) {
            $desc = sanitize_text_field( $data['meta_description'] );
            update_post_meta( $post_id, '_sbp_meta_description', $desc );

            if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
                update_post_meta( $post_id, 'rank_math_description', $desc );
            }
            if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
            }
        }

        // Meta keywords
        if ( ! empty( $data['meta_keywords'] ) ) {
            $keywords = sanitize_text_field( $data['meta_keywords'] );
            update_post_meta( $post_id, '_sbp_meta_keywords', $keywords );
        }

        // Open Graph
        if ( $enable_og === '1' ) {
            if ( ! empty( $data['og_title'] ) ) {
                $og_title = sanitize_text_field( $data['og_title'] );
                update_post_meta( $post_id, '_sbp_og_title', $og_title );

                if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
                    update_post_meta( $post_id, 'rank_math_facebook_title', $og_title );
                }
                if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
                    update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', $og_title );
                }
            }
            if ( ! empty( $data['og_description'] ) ) {
                $og_desc = sanitize_text_field( $data['og_description'] );
                update_post_meta( $post_id, '_sbp_og_description', $og_desc );

                if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
                    update_post_meta( $post_id, 'rank_math_facebook_description', $og_desc );
                }
                if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
                    update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', $og_desc );
                }
            }
        }
    }
}
