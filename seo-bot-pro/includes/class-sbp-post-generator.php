<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI-powered post generator – creates and publishes full articles.
 */
class SBP_Post_Generator {

    /**
     * Generate a full post via AI and create it in WordPress.
     *
     * @param array $args {
     *     @type string $topic       Required. Topic/subject of the article.
     *     @type string $post_type   Post type (post, page, product). Default 'post'.
     *     @type string $status      Post status (draft, publish, pending). Default 'draft'.
     *     @type int    $category_id Category ID. Default 0.
     *     @type string $word_count  Target word count (short, medium, long). Default 'medium'.
     *     @type bool   $auto_seo    Auto-optimize SEO after creation. Default true.
     *     @type bool   $auto_faq    Auto-generate FAQ. Default false.
     *     @type string $custom_instructions  Extra instructions for AI.
     * }
     * @return array|WP_Error  { post_id, title, permalink, ... }
     */
    public function generate( array $args ) {
        $ai = new SBP_AI_Service();
        if ( ! $ai->is_configured() ) {
            return new WP_Error( 'no_api_key', __( 'AI API key is not configured.', 'seo-bot-pro' ) );
        }

        $topic       = sanitize_text_field( $args['topic'] ?? '' );
        $post_type   = sanitize_key( $args['post_type'] ?? 'post' );
        $status      = sanitize_key( $args['status'] ?? 'draft' );
        $category_id = absint( $args['category_id'] ?? 0 );
        $word_count  = sanitize_key( $args['word_count'] ?? 'medium' );
        $auto_seo    = ! empty( $args['auto_seo'] );
        $auto_faq    = ! empty( $args['auto_faq'] );
        $instructions = sanitize_textarea_field( $args['custom_instructions'] ?? '' );

        if ( empty( $topic ) ) {
            return new WP_Error( 'no_topic', __( 'Please provide a topic.', 'seo-bot-pro' ) );
        }

        // Generate article content via AI
        $article = $ai->generate_article( $topic, $word_count, $instructions );
        if ( is_wp_error( $article ) ) {
            return $article;
        }

        $title   = sanitize_text_field( $article['title'] ?? $topic );
        $content = wp_kses_post( $article['content'] ?? '' );
        $excerpt = sanitize_text_field( $article['excerpt'] ?? '' );

        if ( empty( $content ) ) {
            return new WP_Error( 'empty_content', __( 'AI returned empty content.', 'seo-bot-pro' ) );
        }

        // Create the post
        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
            'post_type'    => $post_type,
            'post_author'  => get_current_user_id(),
        ];

        if ( $category_id && $post_type === 'post' ) {
            $post_data['post_category'] = [ $category_id ];
        }

        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Store tags if provided
        if ( ! empty( $article['tags'] ) && is_array( $article['tags'] ) ) {
            wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $article['tags'] ) );
        }

        SBP_Logger::log( $post_id, 'generate_post', 'success', wp_json_encode( [
            'topic'  => $topic,
            'status' => $status,
        ] ) );

        // Auto-SEO optimize
        if ( $auto_seo ) {
            $seo_result = $ai->optimize( $post_id );
            if ( ! is_wp_error( $seo_result ) ) {
                $api = new SBP_REST_API();
                // Use reflection to call private apply_meta
                $this->apply_seo_meta( $post_id, $seo_result );
                SBP_Logger::log( $post_id, 'auto_seo', 'success', wp_json_encode( $seo_result ) );
            }
        }

        // Auto-FAQ
        if ( $auto_faq ) {
            $faq_gen = new SBP_FAQ_Generator();
            $faq_gen->generate( $post_id );
        }

        return [
            'post_id'   => $post_id,
            'title'     => $title,
            'permalink' => get_permalink( $post_id ),
            'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
            'view_url'  => get_permalink( $post_id ),
            'status'    => $status,
            'excerpt'   => $excerpt,
        ];
    }

    /**
     * Apply SEO meta (duplicated from REST API to avoid access issues).
     */
    private function apply_seo_meta( int $post_id, array $data ) {
        $seo_plugin = SBP_Helpers::get_option( 'seo_plugin', 'rank_math' );
        $enable_og  = SBP_Helpers::get_option( 'enable_og', '1' );

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

        if ( ! empty( $data['meta_keywords'] ) ) {
            update_post_meta( $post_id, '_sbp_meta_keywords', sanitize_text_field( $data['meta_keywords'] ) );
        }

        if ( $enable_og === '1' ) {
            if ( ! empty( $data['og_title'] ) ) {
                update_post_meta( $post_id, '_sbp_og_title', sanitize_text_field( $data['og_title'] ) );
            }
            if ( ! empty( $data['og_description'] ) ) {
                update_post_meta( $post_id, '_sbp_og_description', sanitize_text_field( $data['og_description'] ) );
            }
        }
    }
}
