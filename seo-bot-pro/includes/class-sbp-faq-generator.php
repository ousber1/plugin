<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generate FAQs via AI and inject JSON-LD schema.
 */
class SBP_FAQ_Generator {

    /**
     * Generate FAQs for a post, append to content, and save JSON-LD schema.
     *
     * @return array|WP_Error
     */
    public function generate( int $post_id ) {
        $ai     = new SBP_AI_Service();
        $result = $ai->generate_faqs( $post_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $faqs = $result['faqs'] ?? [];
        if ( empty( $faqs ) ) {
            return new WP_Error( 'no_faqs', __( 'No FAQs generated.', 'seo-bot-pro' ) );
        }

        // Build HTML block
        $html = "\n\n<!-- SEO Bot Pro FAQ -->\n<div class=\"sbp-faq-section\">\n";
        $html .= "<h2>" . esc_html__( 'Frequently Asked Questions', 'seo-bot-pro' ) . "</h2>\n";

        foreach ( $faqs as $faq ) {
            $q = sanitize_text_field( $faq['question'] ?? '' );
            $a = wp_kses_post( $faq['answer'] ?? '' );
            $html .= "<div class=\"sbp-faq-item\">\n";
            $html .= "  <h3>{$q}</h3>\n";
            $html .= "  <p>{$a}</p>\n";
            $html .= "</div>\n";
        }
        $html .= "</div>\n";

        // Append to post content
        $post = get_post( $post_id );
        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => $post->post_content . $html,
        ] );

        // Save JSON-LD schema as post meta
        $schema = $this->build_schema( $faqs );
        update_post_meta( $post_id, '_sbp_faq_schema', $schema );

        // Hook to output schema in <head>
        // (We store it; output is handled via wp_head hook below.)

        return [
            'faqs'   => $faqs,
            'schema' => $schema,
        ];
    }

    /**
     * Build FAQPage JSON-LD.
     */
    private function build_schema( array $faqs ): array {
        $items = [];
        foreach ( $faqs as $faq ) {
            $items[] = [
                '@type'          => 'Question',
                'name'           => sanitize_text_field( $faq['question'] ?? '' ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_kses_post( $faq['answer'] ?? '' ),
                ],
            ];
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ];
    }
}

/**
 * Output FAQ JSON-LD in <head> for single posts.
 */
add_action( 'wp_head', function () {
    if ( ! is_singular() ) {
        return;
    }

    $schema = get_post_meta( get_the_ID(), '_sbp_faq_schema', true );
    if ( empty( $schema ) ) {
        return;
    }

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
} );
