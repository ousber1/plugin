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

        register_rest_route( 'seo-bot/v1', '/generate-post', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_generate_post' ],
            'permission_callback' => function () {
                return current_user_can( 'publish_posts' );
            },
        ] );
    }

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

    public function rest_generate_post( WP_REST_Request $request ) {
        $gen    = new SBP_Post_Generator();
        $result = $gen->generate( $request->get_params() );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( [ 'success' => true, 'data' => $result ] );
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
        wp_send_json_success( $analyzer->analyze( $post_id, $keyword ) );
    }

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

        if ( ! empty( $result['primary'] ) ) {
            update_post_meta( $post_id, '_sbp_focus_keyword', sanitize_text_field( $result['primary'] ) );
            $seo_plugin = SBP_Helpers::get_option( 'seo_plugin', 'rank_math' );
            if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
                update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $result['primary'] ) );
            }
            if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $result['primary'] ) );
            }
        }
        if ( ! empty( $result['keywords'] ) && is_array( $result['keywords'] ) ) {
            update_post_meta( $post_id, '_sbp_keywords', implode( ', ', array_map( 'sanitize_text_field', $result['keywords'] ) ) );
        }

        SBP_Logger::log( $post_id, 'keywords', 'success', wp_json_encode( $result ) );
        wp_send_json_success( $result );
    }

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

        if ( ! empty( $result['slug'] ) ) {
            $slug = sanitize_title( $result['slug'] );
            wp_update_post( [ 'ID' => $post_id, 'post_name' => $slug ] );
            $result['slug']      = $slug;
            $result['permalink'] = get_permalink( $post_id );
        }

        SBP_Logger::log( $post_id, 'slug', 'success', wp_json_encode( $result ) );
        wp_send_json_success( $result );
    }

    // ── NEW: Generate Post via AJAX ─────────────────

    public function ajax_generate_post() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );
        if ( ! current_user_can( 'publish_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $gen    = new SBP_Post_Generator();
        $result = $gen->generate( [
            'topic'               => sanitize_text_field( $_POST['topic'] ?? '' ),
            'post_type'           => sanitize_key( $_POST['post_type'] ?? 'post' ),
            'status'              => sanitize_key( $_POST['status'] ?? 'draft' ),
            'category_id'         => absint( $_POST['category_id'] ?? 0 ),
            'word_count'          => sanitize_key( $_POST['length'] ?? 'medium' ),
            'auto_seo'            => ! empty( $_POST['auto_seo'] ) && $_POST['auto_seo'] !== '0',
            'auto_faq'            => ! empty( $_POST['auto_faq'] ) && $_POST['auto_faq'] !== '0',
            'custom_instructions' => sanitize_textarea_field( $_POST['instructions'] ?? '' ),
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    // ── NEW: Generate Excerpt via AJAX ──────────────

    public function ajax_generate_excerpt() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );
        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $ai     = new SBP_AI_Service();
        $result = $ai->generate_excerpt( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // Save excerpt
        wp_update_post( [ 'ID' => $post_id, 'post_excerpt' => sanitize_text_field( $result ) ] );

        SBP_Logger::log( $post_id, 'excerpt', 'success', $result );
        wp_send_json_success( [ 'excerpt' => $result ] );
    }

    // ── NEW: Rewrite Content via AJAX ───────────────

    public function ajax_rewrite_content() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );
        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id      = absint( $_POST['post_id'] ?? 0 );
        $instructions = sanitize_textarea_field( $_POST['instructions'] ?? '' );

        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        $ai     = new SBP_AI_Service();
        $result = $ai->rewrite_content( $post_id, $instructions );

        if ( is_wp_error( $result ) ) {
            SBP_Logger::log( $post_id, 'rewrite', 'error', $result->get_error_message() );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // Update the post
        $update = [ 'ID' => $post_id ];
        if ( ! empty( $result['content'] ) ) {
            $update['post_content'] = wp_kses_post( $result['content'] );
        }
        if ( ! empty( $result['excerpt'] ) ) {
            $update['post_excerpt'] = sanitize_text_field( $result['excerpt'] );
        }
        wp_update_post( $update );

        SBP_Logger::log( $post_id, 'rewrite', 'success', 'Content rewritten' );
        wp_send_json_success( $result );
    }

    // ── NEW: Save Robots Meta via AJAX ──────────────

    public function ajax_save_robots_meta() {
        check_ajax_referer( 'sbp_nonce', 'nonce' );
        if ( ! SBP_Helpers::current_user_can() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'seo-bot-pro' ) ], 403 );
        }

        $post_id  = absint( $_POST['post_id'] ?? 0 );
        $noindex  = ! empty( $_POST['noindex'] ) ? '1' : '0';
        $nofollow = ! empty( $_POST['nofollow'] ) ? '1' : '0';
        $canonical = esc_url_raw( $_POST['canonical'] ?? '' );
        $schema_type = sanitize_key( $_POST['schema_type'] ?? 'article' );

        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-bot-pro' ) ] );
        }

        update_post_meta( $post_id, '_sbp_noindex', $noindex );
        update_post_meta( $post_id, '_sbp_nofollow', $nofollow );
        update_post_meta( $post_id, '_sbp_canonical', $canonical );
        update_post_meta( $post_id, '_sbp_schema_type', $schema_type );

        // Sync robots with SEO plugins
        $seo_plugin = SBP_Helpers::get_option( 'seo_plugin', 'rank_math' );
        $robots     = [];
        if ( $noindex === '1' ) {
            $robots[] = 'noindex';
        }
        if ( $nofollow === '1' ) {
            $robots[] = 'nofollow';
        }

        if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
            update_post_meta( $post_id, 'rank_math_robots', $robots );
            if ( $canonical ) {
                update_post_meta( $post_id, 'rank_math_canonical_url', $canonical );
            }
        }
        if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
            if ( $noindex === '1' ) {
                update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
            } else {
                delete_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex' );
            }
            if ( $nofollow === '1' ) {
                update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', '1' );
            } else {
                delete_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow' );
            }
            if ( $canonical ) {
                update_post_meta( $post_id, '_yoast_wpseo_canonical', $canonical );
            }
        }

        wp_send_json_success( [ 'saved' => true ] );
    }

    // ── Auto-optimize on publish ────────────────────

    public function auto_optimize_on_publish( int $post_id, WP_Post $post ) {
        if ( ! in_array( $post->post_type, SBP_Helpers::post_types(), true ) ) {
            return;
        }
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

    // ── Meta application ────────────────────────────

    /**
     * Apply optimized meta (Rank Math + Yoast + Twitter Cards).
     */
    public function apply_meta( int $post_id, array $data ) {
        $seo_plugin   = SBP_Helpers::get_option( 'seo_plugin', 'rank_math' );
        $enable_og    = SBP_Helpers::get_option( 'enable_og', '1' );
        $enable_twitter = SBP_Helpers::get_option( 'enable_twitter', '1' );

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
            update_post_meta( $post_id, '_sbp_meta_keywords', sanitize_text_field( $data['meta_keywords'] ) );
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

        // Twitter Cards – reuse OG data
        if ( $enable_twitter === '1' ) {
            $tw_title = $data['og_title'] ?? $data['meta_title'] ?? '';
            $tw_desc  = $data['og_description'] ?? $data['meta_description'] ?? '';

            if ( $tw_title ) {
                update_post_meta( $post_id, '_sbp_twitter_title', sanitize_text_field( $tw_title ) );
                if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
                    update_post_meta( $post_id, 'rank_math_twitter_title', sanitize_text_field( $tw_title ) );
                }
                if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
                    update_post_meta( $post_id, '_yoast_wpseo_twitter-title', sanitize_text_field( $tw_title ) );
                }
            }
            if ( $tw_desc ) {
                update_post_meta( $post_id, '_sbp_twitter_description', sanitize_text_field( $tw_desc ) );
                if ( in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
                    update_post_meta( $post_id, 'rank_math_twitter_description', sanitize_text_field( $tw_desc ) );
                }
                if ( in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
                    update_post_meta( $post_id, '_yoast_wpseo_twitter-description', sanitize_text_field( $tw_desc ) );
                }
            }
        }
    }
}
