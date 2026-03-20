<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI-powered post generator – creates and publishes full articles
 * with SEO structure, AI-generated featured images, and internal/external links.
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
     *     @type string $template    Article template (blog, listicle, howto, review, comparison). Default 'blog'.
     *     @type bool   $auto_seo    Auto-optimize SEO after creation. Default true.
     *     @type bool   $auto_faq    Auto-generate FAQ. Default false.
     *     @type bool   $auto_image  Auto-generate featured image via DALL-E. Default false.
     *     @type bool   $auto_links  Auto-inject internal links. Default true.
     *     @type string $custom_instructions  Extra instructions for AI.
     * }
     * @return array|WP_Error  { post_id, title, permalink, ... }
     */
    public function generate( array $args ) {
        $ai = new SBP_AI_Service();
        if ( ! $ai->is_configured() ) {
            return new WP_Error( 'no_api_key', __( 'AI API key is not configured.', 'seo-bot-pro' ) );
        }

        $topic        = sanitize_text_field( $args['topic'] ?? '' );
        $post_type    = sanitize_key( $args['post_type'] ?? 'post' );
        $status       = sanitize_key( $args['status'] ?? 'draft' );
        $category_id  = absint( $args['category_id'] ?? 0 );
        $word_count   = sanitize_key( $args['word_count'] ?? 'medium' );
        $template     = sanitize_key( $args['template'] ?? 'blog' );
        $auto_seo     = ! empty( $args['auto_seo'] );
        $auto_faq     = ! empty( $args['auto_faq'] );
        $auto_image   = ! empty( $args['auto_image'] );
        $auto_links   = ! isset( $args['auto_links'] ) || ! empty( $args['auto_links'] );
        $instructions = sanitize_textarea_field( $args['custom_instructions'] ?? '' );

        if ( empty( $topic ) ) {
            return new WP_Error( 'no_topic', __( 'Please provide a topic.', 'seo-bot-pro' ) );
        }

        // Gather existing site posts for internal linking
        $internal_links = [];
        if ( $auto_links ) {
            $internal_links = $this->get_internal_links_pool();
        }

        // Generate article with SEO-perfect structure
        $article = $ai->generate_seo_article( $topic, $word_count, $template, $instructions, $internal_links );
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

        // Store focus keyword
        if ( ! empty( $article['focus_keyword'] ) ) {
            update_post_meta( $post_id, '_sbp_focus_keyword', sanitize_text_field( $article['focus_keyword'] ) );
        }

        SBP_Logger::log( $post_id, 'generate_post', 'success', wp_json_encode( [
            'topic'    => $topic,
            'template' => $template,
            'status'   => $status,
        ] ) );

        $image_generated = false;

        // Generate featured image
        if ( $auto_image ) {
            $image_prompt = $article['image_prompt'] ?? '';
            if ( empty( $image_prompt ) ) {
                $image_prompt = "Professional blog featured image for article: {$title}. Clean, modern design, high quality.";
            }

            $image_result = $this->generate_and_attach_image( $ai, $post_id, $title, $image_prompt );
            if ( ! is_wp_error( $image_result ) ) {
                $image_generated = true;
                SBP_Logger::log( $post_id, 'generate_image', 'success', 'Featured image generated' );
            } else {
                SBP_Logger::log( $post_id, 'generate_image', 'error', $image_result->get_error_message() );
            }
        }

        // Auto-SEO optimize
        if ( $auto_seo ) {
            $seo_result = $ai->optimize( $post_id );
            if ( ! is_wp_error( $seo_result ) ) {
                $api = new SBP_REST_API();
                $api->apply_meta( $post_id, $seo_result );
                SBP_Logger::log( $post_id, 'auto_seo', 'success', wp_json_encode( $seo_result ) );
            }
        }

        // Auto-FAQ
        if ( $auto_faq ) {
            $faq_gen = new SBP_FAQ_Generator();
            $faq_gen->generate( $post_id );
        }

        return [
            'post_id'         => $post_id,
            'title'           => $title,
            'permalink'       => get_permalink( $post_id ),
            'edit_url'        => get_edit_post_link( $post_id, 'raw' ),
            'view_url'        => get_permalink( $post_id ),
            'status'          => $status,
            'excerpt'         => $excerpt,
            'template'        => $template,
            'image_generated' => $image_generated,
            'has_links'       => $auto_links,
        ];
    }

    /**
     * Get pool of existing published posts for internal linking.
     */
    private function get_internal_links_pool(): array {
        $posts = get_posts( [
            'post_type'      => SBP_Helpers::post_types(),
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ] );

        $links = [];
        foreach ( $posts as $pid ) {
            $links[] = [
                'title' => get_the_title( $pid ),
                'url'   => get_permalink( $pid ),
            ];
        }

        return $links;
    }

    /**
     * Generate an AI image and attach it as featured image.
     *
     * @return int|WP_Error  Attachment ID on success.
     */
    private function generate_and_attach_image( SBP_AI_Service $ai, int $post_id, string $title, string $image_prompt ) {
        // Generate image URL via DALL-E
        $image_url = $ai->generate_image( $image_prompt );
        if ( is_wp_error( $image_url ) ) {
            return $image_url;
        }

        // Download the image to WordPress
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download image to temp file
        $tmp_file = download_url( $image_url, 60 );
        if ( is_wp_error( $tmp_file ) ) {
            return $tmp_file;
        }

        // Build filename from title
        $filename = sanitize_file_name( sanitize_title( $title ) . '-featured.png' );

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp_file,
        ];

        // Upload to media library
        $attach_id = media_handle_sideload( $file_array, $post_id, $title );

        // Clean up temp file on error
        if ( is_wp_error( $attach_id ) ) {
            if ( file_exists( $tmp_file ) ) {
                wp_delete_file( $tmp_file );
            }
            return $attach_id;
        }

        // Set ALT text
        update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );

        // Set as featured image
        set_post_thumbnail( $post_id, $attach_id );

        return $attach_id;
    }
}
