<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scan post images and fix missing ALT text via AI.
 */
class SBP_Image_Alt {

    /**
     * Fix missing ALT tags in a post's content.
     *
     * @return array|WP_Error  { fixed: int, total: int }
     */
    public function fix( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $content = $post->post_content;
        $context = SBP_Helpers::content_to_plain( $content );

        // Find all <img> tags
        preg_match_all( '/<img\s[^>]+>/i', $content, $matches );
        if ( empty( $matches[0] ) ) {
            return [ 'fixed' => 0, 'total' => 0 ];
        }

        $ai    = new SBP_AI_Service();
        $fixed = 0;

        foreach ( $matches[0] as $img_tag ) {
            // Skip if already has a non-empty ALT
            if ( preg_match( '/alt=["\'][^"\']+["\']/i', $img_tag ) ) {
                continue;
            }

            // Extract src
            $src = '';
            if ( preg_match( '/src=["\']([^"\']+)["\']/i', $img_tag, $src_match ) ) {
                $src = $src_match[1];
            }

            $alt = $ai->generate_alt( $context, $src );
            if ( is_wp_error( $alt ) ) {
                continue;
            }

            $alt_attr = 'alt="' . esc_attr( $alt ) . '"';

            // If there's an empty alt="" replace it; otherwise insert alt before >
            if ( preg_match( '/alt=["\']["\']/', $img_tag ) ) {
                $new_tag = preg_replace( '/alt=["\']["\']/', $alt_attr, $img_tag );
            } else {
                $new_tag = preg_replace( '/\/?>$/', $alt_attr . ' />', $img_tag );
            }

            $content = str_replace( $img_tag, $new_tag, $content );
            $fixed++;
        }

        if ( $fixed > 0 ) {
            wp_update_post( [
                'ID'           => $post_id,
                'post_content' => $content,
            ] );
        }

        return [
            'fixed' => $fixed,
            'total' => count( $matches[0] ),
        ];
    }
}
