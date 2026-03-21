<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Internal linking suggestions via AI.
 */
class SBP_Internal_Links {

    /**
     * Suggest internal links for a post.
     *
     * @return array|WP_Error
     */
    public function suggest( int $post_id ) {
        $ai = new SBP_AI_Service();
        return $ai->suggest_internal_links( $post_id );
    }

    /**
     * Auto-insert a link into post content.
     */
    public function insert_link( int $post_id, string $anchor, string $url ): bool {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $anchor_esc = esc_html( $anchor );
        $url_esc    = esc_url( $url );
        $link_html  = "<a href=\"{$url_esc}\">{$anchor_esc}</a>";

        $content = $post->post_content;

        // Try to find the anchor text in the content and wrap it
        $pos = stripos( wp_strip_all_tags( $content ), $anchor );
        if ( $pos !== false ) {
            // Replace first occurrence of the anchor text (plain text) with the link
            $content = preg_replace(
                '/' . preg_quote( $anchor, '/' ) . '/i',
                $link_html,
                $content,
                1
            );
        } else {
            // Append a related-links section
            $content .= "\n<p>" . sprintf(
                /* translators: %s: link HTML */
                __( 'Related: %s', 'seo-bot-pro' ),
                $link_html
            ) . "</p>";
        }

        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => $content,
        ] );

        return true;
    }
}
