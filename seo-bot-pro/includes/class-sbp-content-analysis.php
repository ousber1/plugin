<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * On-page content analysis engine (SEO score 0-100) + readability.
 */
class SBP_Content_Analysis {

    /**
     * Analyze a post and return score + suggestions + readability.
     */
    public function analyze( int $post_id, string $keyword = '' ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'score' => 0, 'checks' => [], 'suggestions' => [], 'readability' => [] ];
        }

        $content    = $post->post_content;
        $title      = $post->post_title;
        $plain      = strtolower( wp_strip_all_tags( $content ) );
        $keyword_lc = strtolower( trim( $keyword ) );

        $seo_plugin = SBP_Helpers::get_option( 'seo_plugin', 'rank_math' );

        // Get meta from whichever SEO plugin is active
        $meta_title = get_post_meta( $post_id, '_sbp_meta_title', true );
        if ( ! $meta_title && in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
            $meta_title = get_post_meta( $post_id, 'rank_math_title', true );
        }
        if ( ! $meta_title && in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
            $meta_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
        }

        $meta_desc = get_post_meta( $post_id, '_sbp_meta_description', true );
        if ( ! $meta_desc && in_array( $seo_plugin, [ 'rank_math', 'both' ], true ) ) {
            $meta_desc = get_post_meta( $post_id, 'rank_math_description', true );
        }
        if ( ! $meta_desc && in_array( $seo_plugin, [ 'yoast', 'both' ], true ) ) {
            $meta_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
        }

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

        // 7. External links (5 pts)
        $max += 5;
        $all_links_count = preg_match_all( '/<a\s[^>]*href=["\']https?:\/\//i', $content );
        $internal_count  = preg_match_all( '/<a\s[^>]*href=["\']' . preg_quote( $home, '/' ) . '/i', $content );
        $external_count  = $all_links_count - $internal_count;
        if ( $external_count > 0 ) {
            $checks[] = [ 'label' => __( 'External links', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 5;
        } else {
            $checks[]      = [ 'label' => __( 'External links', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = __( 'Consider adding external links to authoritative sources.', 'seo-bot-pro' );
        }

        // 8. Images have ALT (10 pts)
        $max += 10;
        preg_match_all( '/<img\s[^>]+>/i', $content, $imgs );
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

        // 9. Has subheadings H2/H3 (5 pts)
        $max += 5;
        if ( preg_match( '/<h[23][\s>]/i', $content ) ) {
            $checks[] = [ 'label' => __( 'Subheadings (H2/H3)', 'seo-bot-pro' ), 'pass' => true ];
            $score   += 5;
        } else {
            $checks[]      = [ 'label' => __( 'Subheadings (H2/H3)', 'seo-bot-pro' ), 'pass' => false ];
            $suggestions[] = __( 'Add H2 or H3 subheadings to structure your content.', 'seo-bot-pro' );
        }

        // Keyword-specific checks
        if ( $keyword_lc ) {
            // 10. Keyword in title (10 pts)
            $max += 10;
            if ( str_contains( strtolower( $title ), $keyword_lc ) ) {
                $checks[] = [ 'label' => __( 'Keyword in title', 'seo-bot-pro' ), 'pass' => true ];
                $score   += 10;
            } else {
                $checks[]      = [ 'label' => __( 'Keyword in title', 'seo-bot-pro' ), 'pass' => false ];
                $suggestions[] = __( 'Add your focus keyword to the title.', 'seo-bot-pro' );
            }

            // 11. Keyword in first paragraph (10 pts)
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

            // 12. Keyword in meta description (5 pts)
            $max += 5;
            if ( $meta_desc && str_contains( strtolower( $meta_desc ), $keyword_lc ) ) {
                $checks[] = [ 'label' => __( 'Keyword in meta description', 'seo-bot-pro' ), 'pass' => true ];
                $score   += 5;
            } else {
                $checks[]      = [ 'label' => __( 'Keyword in meta description', 'seo-bot-pro' ), 'pass' => false ];
                $suggestions[] = __( 'Include your keyword in the meta description.', 'seo-bot-pro' );
            }

            // 13. Keyword in subheading (5 pts)
            $max += 5;
            if ( preg_match( '/<h[23][^>]*>.*?' . preg_quote( $keyword_lc, '/' ) . '.*?<\/h[23]>/is', strtolower( $content ) ) ) {
                $checks[] = [ 'label' => __( 'Keyword in subheading', 'seo-bot-pro' ), 'pass' => true ];
                $score   += 5;
            } else {
                $checks[]      = [ 'label' => __( 'Keyword in subheading', 'seo-bot-pro' ), 'pass' => false ];
                $suggestions[] = __( 'Add your keyword to at least one H2/H3 subheading.', 'seo-bot-pro' );
            }

            // 14. Keyword density (5 pts)
            $max += 5;
            if ( $word_count > 0 ) {
                $kw_count = substr_count( $plain, $keyword_lc );
                $density  = ( $kw_count / $word_count ) * 100;
                if ( $density >= 0.5 && $density <= 3.0 ) {
                    $checks[] = [ 'label' => sprintf( __( 'Keyword density (%.1f%%)', 'seo-bot-pro' ), $density ), 'pass' => true ];
                    $score   += 5;
                } else {
                    $checks[]      = [ 'label' => sprintf( __( 'Keyword density (%.1f%%)', 'seo-bot-pro' ), $density ), 'pass' => false ];
                    $suggestions[] = $density < 0.5
                        ? __( 'Keyword density is too low. Use keyword more often.', 'seo-bot-pro' )
                        : __( 'Keyword density is too high. Reduce keyword usage.', 'seo-bot-pro' );
                }
            }
        }

        // Normalize to 0-100
        $final_score = $max > 0 ? (int) round( ( $score / $max ) * 100 ) : 0;

        // Readability analysis
        $readability = $this->analyze_readability( $plain, $word_count );

        return [
            'score'       => $final_score,
            'checks'      => $checks,
            'suggestions' => $suggestions,
            'readability' => $readability,
            'word_count'  => $word_count,
        ];
    }

    /**
     * Basic readability metrics.
     */
    private function analyze_readability( string $plain, int $word_count ): array {
        if ( $word_count < 10 ) {
            return [
                'grade'      => __( 'N/A', 'seo-bot-pro' ),
                'level'      => 'unknown',
                'avg_sentence_length' => 0,
                'suggestions' => [],
            ];
        }

        // Count sentences
        $sentences     = preg_split( '/[.!?]+/', $plain, -1, PREG_SPLIT_NO_EMPTY );
        $sentence_count = count( $sentences );
        $avg_sentence   = $sentence_count > 0 ? round( $word_count / $sentence_count, 1 ) : 0;

        // Count syllables (rough English approximation)
        $words    = preg_split( '/\s+/', trim( $plain ) );
        $syllables = 0;
        foreach ( $words as $w ) {
            $syllables += $this->count_syllables( $w );
        }

        // Flesch Reading Ease
        $flesch = 0;
        if ( $sentence_count > 0 && $word_count > 0 ) {
            $flesch = 206.835
                    - ( 1.015 * ( $word_count / $sentence_count ) )
                    - ( 84.6 * ( $syllables / $word_count ) );
            $flesch = round( max( 0, min( 100, $flesch ) ), 1 );
        }

        // Grade level
        if ( $flesch >= 80 ) {
            $grade = __( 'Very Easy', 'seo-bot-pro' );
            $level = 'good';
        } elseif ( $flesch >= 60 ) {
            $grade = __( 'Easy', 'seo-bot-pro' );
            $level = 'good';
        } elseif ( $flesch >= 40 ) {
            $grade = __( 'Moderate', 'seo-bot-pro' );
            $level = 'ok';
        } elseif ( $flesch >= 20 ) {
            $grade = __( 'Difficult', 'seo-bot-pro' );
            $level = 'bad';
        } else {
            $grade = __( 'Very Difficult', 'seo-bot-pro' );
            $level = 'bad';
        }

        $read_suggestions = [];
        if ( $avg_sentence > 25 ) {
            $read_suggestions[] = __( 'Sentences are too long. Aim for 15-20 words per sentence.', 'seo-bot-pro' );
        }
        if ( $flesch < 40 ) {
            $read_suggestions[] = __( 'Content is hard to read. Use simpler words and shorter sentences.', 'seo-bot-pro' );
        }

        // Check for paragraph length
        $paragraphs = preg_split( '/\n{2,}/', $plain );
        $long_paras = 0;
        foreach ( $paragraphs as $p ) {
            if ( str_word_count( trim( $p ) ) > 150 ) {
                $long_paras++;
            }
        }
        if ( $long_paras > 0 ) {
            $read_suggestions[] = __( 'Some paragraphs are too long. Break them into smaller ones.', 'seo-bot-pro' );
        }

        return [
            'flesch_score'        => $flesch,
            'grade'               => $grade,
            'level'               => $level,
            'avg_sentence_length' => $avg_sentence,
            'suggestions'         => $read_suggestions,
        ];
    }

    /**
     * Rough syllable count for a word.
     */
    private function count_syllables( string $word ): int {
        $word = strtolower( trim( $word ) );
        $word = preg_replace( '/[^a-z]/', '', $word );

        if ( strlen( $word ) <= 3 ) {
            return 1;
        }

        // Remove trailing silent e
        $word = preg_replace( '/e$/', '', $word );

        // Count vowel groups
        preg_match_all( '/[aeiouy]+/', $word, $matches );
        $count = count( $matches[0] );

        return max( 1, $count );
    }
}
