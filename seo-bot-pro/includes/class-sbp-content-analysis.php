<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * On-page content analysis engine (SEO score 0-100).
 */
class SBP_Content_Analysis {

    /**
     * Analyze a post and return score + suggestions.
     */
    public function analyze( int $post_id, string $keyword = '' ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'score' => 0, 'checks' => [], 'suggestions' => [] ];
        }

        $content    = $post->post_content;
        $title      = $post->post_title;
        $plain      = strtolower( wp_strip_all_tags( $content ) );
        $keyword_lc = strtolower( trim( $keyword ) );

        $meta_title = get_post_meta( $post_id, 'rank_math_title', true )
                   ?: get_post_meta( $post_id, '_sbp_meta_title', true );
        $meta_desc  = get_post_meta( $post_id, 'rank_math_description', true )
                   ?: get_post_meta( $post_id, '_sbp_meta_description', true );

        $checks      = [];
        $suggestions = [];
        $score       = 0;
        $max         = 0;

        // 1. Title length (10 pts)
        $max += 10;
        $title_len = mb_strlen( $title );
        if ( $title_len >= 30 && $title_len <= 65 ) {
            $checks[] = [ 'label' => __( 'Title length', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 10;
        } else {
            $checks[]      = [ 'label' => __( 'Title length', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = __( 'Title should be 30-65 characters.', 'seo-bot-pro' );
        }

        // 2. Meta title exists (10 pts)
        $max += 10;
        if ( ! empty( $meta_title ) && mb_strlen( $meta_title ) <= 60 ) {
            $checks[] = [ 'label' => __( 'Meta title', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 10;
        } else {
            $checks[]      = [ 'label' => __( 'Meta title', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = __( 'Add a meta title (max 60 chars).', 'seo-bot-pro' );
        }

        // 3. Meta description (10 pts)
        $max += 10;
        $desc_len = mb_strlen( $meta_desc );
        if ( $desc_len >= 50 && $desc_len <= 160 ) {
            $checks[] = [ 'label' => __( 'Meta description', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 10;
        } else {
            $checks[]      = [ 'label' => __( 'Meta description', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = empty( $meta_desc )
                ? __( 'Add a meta description.', 'seo-bot-pro' )
                : __( 'Meta description should be 50-160 characters.', 'seo-bot-pro' );
        }

        // 4. H1 tag (10 pts)
        $max += 10;
        if ( preg_match( '/<h1[\s>]/i', $content ) ) {
            $checks[] = [ 'label' => __( 'H1 tag exists', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 10;
        } else {
            $checks[]      = [ 'label' => __( 'H1 tag exists', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = __( 'Add an H1 heading to your content.', 'seo-bot-pro' );
        }

        // 5. Content length (15 pts)
        $max      += 15;
        $word_count = str_word_count( $plain );
        if ( $word_count >= 300 ) {
            $checks[] = [ 'label' => __( 'Content length (300+ words)', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 15;
        } else {
            $checks[]      = [ 'label' => __( 'Content length', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = sprintf(
                __( 'Content has %d words. Aim for 300+.', 'seo-bot-pro' ),
                $word_count
            );
        }

        // 6. Internal links (10 pts)
        $max += 10;
        $home = home_url();
        if ( preg_match( '/<a\s[^>]*href=["\']' . preg_quote( $home, '/' ) . '/i', $content ) ) {
            $checks[] = [ 'label' => __( 'Internal links', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 10;
        } else {
            $checks[]      = [ 'label' => __( 'Internal links', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = __( 'Add at least one internal link.', 'seo-bot-pro' );
        }

        // 7. Images have ALT (10 pts)
        $max += 10;
        preg_match_all( '/<img\s[^>]*>/i', $content, $imgs );
        $all_have_alt = true;
        if ( ! empty( $imgs[0] ) ) {
            foreach ( $imgs[0] as $tag ) {
                if ( ! preg_match( '/alt=["\'][^"\']+["\']/i', $tag ) ) {
                    $all_have_alt = false;
                    break;
                }
            }
        }
        if ( empty( $imgs[0] ) || $all_have_alt ) {
            $checks[] = [ 'label' => __( 'Image ALT tags', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 10;
        } else {
            $checks[]      = [ 'label' => __( 'Image ALT tags', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = __( 'Some images are missing ALT text.', 'seo-bot-pro' );
        }

        // Keyword-specific checks (only if keyword provided)
        if ( $keyword_lc ) {
            // 8. Keyword in title (10 pts)
            $max += 10;
            if ( str_contains( strtolower( $title ), $keyword_lc ) ) {
                $checks[] = [ 'label' => __( 'Keyword in title', 'seo-bot-pro' ), 'pass' => true ];
                $score   += 10;
            } else {
                $checks[]      = [ 'label' => __( 'Keyword in title', 'seo-bot-pro' ), 'pass' => false ];
                $suggestions[] = __( 'Add your focus keyword to the title.', 'seo-bot-pro' );
            }

            // 9. Keyword in first paragraph (10 pts)
            $max    += 10;
            $first_p = '';
            if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
                $first_p = strtolower( wp_strip_all_tags( $m[1] ) );
            } else {
                $first_p = mb_substr( $plain, 0, 300 );
            }
            if ( str_contains( $first_p, $keyword_lc ) ) {
                $checks[] = [ 'label' => __( 'Keyword in first paragraph', 'seo-bot-pro' ), 'pass' => true ];
                $score   += 10;
            } else {
                $checks[]      = [ 'label' => __( 'Keyword in first paragraph', 'seo-bot-pro' ), 'pass' => false ];
                $suggestions[] = __( 'Add keyword in the first paragraph.', 'seo-bot-pro' );
            }

            // 10. Keyword density (5 pts)
            $max += 5;
            if ( $word_count > 0 ) {
                $kw_count = substr_count( $plain, $keyword_lc );
                $density  = ( $kw_count / $word_count ) * 100;
                if ( $density >= 0.5 && $density <= 3.0 ) {
                    $checks[] = [ 'label' => __( 'Keyword density', 'seo-bot-pro' ), 'pass' => true ];
                    $score   += 5;
                } else {
                    $checks[]      = [ 'label' => __( 'Keyword density', 'seo-bot-pro' ), 'pass' => false ];
                    $suggestions[] = $density < 0.5
                        ? __( 'Keyword density is too low. Use keyword more often.', 'seo-bot-pro' )
                        : __( 'Keyword density is too high. Reduce keyword usage.', 'seo-bot-pro' );
                }
            }
        }

        // Normalize to 0-100
        $final_score = $max > 0 ? (int) round( ( $score / $max ) * 100 ) : 0;

        return [
            'score'       => $final_score,
            'checks'      => $checks,
            'suggestions' => $suggestions,
        ];
    }
}
