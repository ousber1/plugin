<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema markup (JSON-LD) generator for posts/pages/products.
 */
class SBP_Schema {

    /**
     * Register the wp_head hook.
     */
    public function init() {
        add_action( 'wp_head', [ $this, 'output_schema' ], 1 );
    }

    /**
     * Output JSON-LD schema on singular pages.
     */
    public function output_schema() {
        if ( ! is_singular() ) {
            return;
        }

        $post_id = get_the_ID();
        $post    = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        // Check if schema is enabled
        $schema_type = get_post_meta( $post_id, '_sbp_schema_type', true );
        if ( empty( $schema_type ) || $schema_type === 'none' ) {
            // Auto-detect based on post type
            if ( $post->post_type === 'product' && class_exists( 'WooCommerce' ) ) {
                return; // WooCommerce handles product schema
            }
            if ( empty( $schema_type ) ) {
                $schema_type = 'article'; // default
            }
            if ( $schema_type === 'none' ) {
                return;
            }
        }

        $schema = null;

        switch ( $schema_type ) {
            case 'article':
                $schema = $this->build_article_schema( $post );
                break;
            case 'howto':
                $schema = $this->build_howto_schema( $post );
                break;
            case 'product':
                $schema = $this->build_product_schema( $post );
                break;
            case 'local_business':
                $schema = $this->build_local_business_schema( $post );
                break;
        }

        if ( $schema ) {
            echo '<script type="application/ld+json">'
               . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
               . "</script>\n";
        }
    }

    /**
     * Article schema.
     */
    private function build_article_schema( WP_Post $post ): array {
        $meta_desc = get_post_meta( $post->ID, '_sbp_meta_description', true );
        $author    = get_the_author_meta( 'display_name', $post->post_author );
        $thumb     = get_the_post_thumbnail_url( $post->ID, 'full' );

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => $post->post_title,
            'description'   => $meta_desc ?: wp_trim_words( $post->post_content, 30 ),
            'author'        => [
                '@type' => 'Person',
                'name'  => $author,
            ],
            'datePublished' => get_the_date( 'c', $post ),
            'dateModified'  => get_the_modified_date( 'c', $post ),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post->ID ),
            ],
        ];

        if ( $thumb ) {
            $schema['image'] = $thumb;
        }

        $site_name = get_bloginfo( 'name' );
        if ( $site_name ) {
            $schema['publisher'] = [
                '@type' => 'Organization',
                'name'  => $site_name,
            ];
            $logo = get_site_icon_url();
            if ( $logo ) {
                $schema['publisher']['logo'] = [
                    '@type' => 'ImageObject',
                    'url'   => $logo,
                ];
            }
        }

        return $schema;
    }

    /**
     * HowTo schema – parses H2/H3 as steps.
     */
    private function build_howto_schema( WP_Post $post ): array {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            'name'        => $post->post_title,
            'description' => get_post_meta( $post->ID, '_sbp_meta_description', true )
                           ?: wp_trim_words( $post->post_content, 30 ),
        ];

        // Extract steps from H2/H3 headings
        $steps = [];
        if ( preg_match_all( '/<h[23][^>]*>(.*?)<\/h[23]>/is', $post->post_content, $matches ) ) {
            foreach ( $matches[1] as $i => $heading ) {
                $steps[] = [
                    '@type' => 'HowToStep',
                    'name'  => wp_strip_all_tags( $heading ),
                    'text'  => wp_strip_all_tags( $heading ),
                    'position' => $i + 1,
                ];
            }
        }

        if ( ! empty( $steps ) ) {
            $schema['step'] = $steps;
        }

        return $schema;
    }

    /**
     * Basic Product schema.
     */
    private function build_product_schema( WP_Post $post ): array {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $post->post_title,
            'description' => get_post_meta( $post->ID, '_sbp_meta_description', true )
                           ?: wp_trim_words( $post->post_content, 30 ),
        ];

        $thumb = get_the_post_thumbnail_url( $post->ID, 'full' );
        if ( $thumb ) {
            $schema['image'] = $thumb;
        }

        // WooCommerce price
        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $post->ID );
            if ( $product ) {
                $schema['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => $product->get_price(),
                    'priceCurrency' => get_woocommerce_currency(),
                    'availability'  => $product->is_in_stock()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url'           => get_permalink( $post->ID ),
                ];
            }
        }

        return $schema;
    }

    /**
     * Local Business schema.
     */
    private function build_local_business_schema( WP_Post $post ): array {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            'name'        => $post->post_title,
            'description' => get_post_meta( $post->ID, '_sbp_meta_description', true )
                           ?: wp_trim_words( $post->post_content, 30 ),
            'url'         => get_permalink( $post->ID ),
        ];
    }
}
