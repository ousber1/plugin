<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Static helper utilities.
 */
class SBP_Helpers {

    /**
     * Get a plugin option with default.
     */
    public static function get_option( string $key, $default = '' ) {
        $options = get_option( 'sbp_settings', [] );
        return $options[ $key ] ?? $default;
    }

    /**
     * Supported post types for optimization.
     */
    public static function post_types(): array {
        $types = [ 'post', 'page' ];
        if ( class_exists( 'WooCommerce' ) ) {
            $types[] = 'product';
        }
        return $types;
    }

    /**
     * Check current user capability.
     */
    public static function current_user_can(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Strip content to plain text for AI prompts.
     */
    public static function content_to_plain( string $html ): string {
        $text = wp_strip_all_tags( $html );
        $text = preg_replace( '/\s+/', ' ', $text );
        return mb_substr( trim( $text ), 0, 3000 );
    }

    /**
     * Sanitize the settings array.
     */
    public static function sanitize_settings( array $input ): array {
        $clean = [];

        // Provider
        $allowed_providers = [ 'openai', 'claude' ];
        $clean['provider'] = in_array( $input['provider'] ?? 'openai', $allowed_providers, true )
            ? $input['provider']
            : 'openai';

        // API keys
        $clean['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] ?? '' );
        $clean['claude_api_key'] = sanitize_text_field( $input['claude_api_key'] ?? '' );

        // Model
        $clean['model'] = sanitize_text_field( $input['model'] ?? 'gpt-4o-mini' );

        // Language
        $allowed_langs = array_keys( self::language_labels() );
        $clean['language'] = in_array( $input['language'] ?? 'en', $allowed_langs, true )
            ? $input['language']
            : 'en';

        // Tone
        $allowed_tones = array_keys( self::tone_labels() );
        $clean['tone'] = in_array( $input['tone'] ?? 'professional', $allowed_tones, true )
            ? $input['tone']
            : 'professional';

        // Temperature
        $temp = floatval( $input['temperature'] ?? 0.4 );
        $clean['temperature'] = max( 0.0, min( 1.0, $temp ) );

        // Max tokens
        $tokens = intval( $input['max_tokens'] ?? 1024 );
        $clean['max_tokens'] = max( 256, min( 4096, $tokens ) );

        // SEO plugin target
        $allowed_seo = [ 'rank_math', 'yoast', 'both', 'none' ];
        $clean['seo_plugin'] = in_array( $input['seo_plugin'] ?? 'rank_math', $allowed_seo, true )
            ? $input['seo_plugin']
            : 'rank_math';

        // Open Graph
        $clean['enable_og'] = ! empty( $input['enable_og'] ) ? '1' : '0';

        // Auto-optimize on publish
        $clean['auto_optimize_publish'] = ! empty( $input['auto_optimize_publish'] ) ? '1' : '0';

        // Twitter Cards
        $clean['enable_twitter'] = ! empty( $input['enable_twitter'] ) ? '1' : '0';

        // Indexing & Crawl
        $clean['enable_sitemap']     = ! empty( $input['enable_sitemap'] ) ? '1' : '0';
        $clean['auto_ping_publish']  = ! empty( $input['auto_ping_publish'] ) ? '1' : '0';
        $clean['ping_google']        = ! empty( $input['ping_google'] ) ? '1' : '0';
        $clean['ping_bing']          = ! empty( $input['ping_bing'] ) ? '1' : '0';
        $clean['enable_indexnow']    = ! empty( $input['enable_indexnow'] ) ? '1' : '0';
        $clean['indexnow_api_key']   = sanitize_text_field( $input['indexnow_api_key'] ?? '' );

        // Rank Booster
        $clean['enable_freshness'] = ! empty( $input['enable_freshness'] ) ? '1' : '0';
        $freshness_days = intval( $input['freshness_days'] ?? 90 );
        $clean['freshness_days']   = max( 7, min( 365, $freshness_days ) );

        return $clean;
    }

    /**
     * Language label map.
     */
    public static function language_labels(): array {
        return [
            'en' => __( 'English', 'seo-bot-pro' ),
            'fr' => __( 'French', 'seo-bot-pro' ),
            'ar' => __( 'Arabic', 'seo-bot-pro' ),
            'es' => __( 'Spanish', 'seo-bot-pro' ),
            'de' => __( 'German', 'seo-bot-pro' ),
            'pt' => __( 'Portuguese', 'seo-bot-pro' ),
            'it' => __( 'Italian', 'seo-bot-pro' ),
            'nl' => __( 'Dutch', 'seo-bot-pro' ),
            'tr' => __( 'Turkish', 'seo-bot-pro' ),
            'zh' => __( 'Chinese', 'seo-bot-pro' ),
        ];
    }

    /**
     * Tone label map.
     */
    public static function tone_labels(): array {
        return [
            'professional' => __( 'Professional', 'seo-bot-pro' ),
            'sales'        => __( 'Sales', 'seo-bot-pro' ),
            'neutral'      => __( 'Neutral', 'seo-bot-pro' ),
            'casual'       => __( 'Casual', 'seo-bot-pro' ),
            'formal'       => __( 'Formal', 'seo-bot-pro' ),
            'creative'     => __( 'Creative', 'seo-bot-pro' ),
        ];
    }

    /**
     * OpenAI model options.
     */
    public static function openai_models(): array {
        return [
            'gpt-4o-mini'    => 'GPT-4o Mini (recommended)',
            'gpt-4o'         => 'GPT-4o',
            'gpt-4-turbo'    => 'GPT-4 Turbo',
            'gpt-4.1-mini'   => 'GPT-4.1 Mini',
            'gpt-4.1'        => 'GPT-4.1',
            'gpt-3.5-turbo'  => 'GPT-3.5 Turbo',
        ];
    }

    /**
     * Claude model options.
     */
    public static function claude_models(): array {
        return [
            'claude-sonnet-4-6'           => 'Claude Sonnet 4.6 (recommended)',
            'claude-opus-4-6'             => 'Claude Opus 4.6',
            'claude-haiku-4-5-20251001'   => 'Claude Haiku 4.5',
            'claude-sonnet-4-5-20250514'  => 'Claude Sonnet 4.5',
        ];
    }

    /**
     * SEO plugin options.
     */
    public static function seo_plugin_labels(): array {
        return [
            'rank_math' => __( 'Rank Math SEO', 'seo-bot-pro' ),
            'yoast'     => __( 'Yoast SEO', 'seo-bot-pro' ),
            'both'      => __( 'Both (Rank Math + Yoast)', 'seo-bot-pro' ),
            'none'      => __( 'None (use built-in meta only)', 'seo-bot-pro' ),
        ];
    }
}
