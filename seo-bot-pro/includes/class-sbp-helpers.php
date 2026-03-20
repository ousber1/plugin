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
        $allowed_providers = [ 'openai', 'claude', 'gemini' ];
        $clean['provider'] = in_array( $input['provider'] ?? 'openai', $allowed_providers, true )
            ? $input['provider']
            : 'openai';

        // API keys
        $clean['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] ?? '' );
        $clean['claude_api_key'] = sanitize_text_field( $input['claude_api_key'] ?? '' );
        $clean['gemini_api_key'] = sanitize_text_field( $input['gemini_api_key'] ?? '' );

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
        $clean['max_tokens'] = max( 256, min( 16384, $tokens ) );

        // SEO plugin target
        $allowed_seo = [ 'rank_math', 'yoast', 'aioseo', 'both', 'none' ];
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

        // Verification codes
        $clean['google_verification'] = sanitize_text_field( $input['google_verification'] ?? '' );
        $clean['bing_verification']   = sanitize_text_field( $input['bing_verification'] ?? '' );

        // 404 Monitor
        $clean['enable_404_monitor'] = ! empty( $input['enable_404_monitor'] ) ? '1' : '0';

        // Breadcrumbs
        $clean['enable_breadcrumbs'] = ! empty( $input['enable_breadcrumbs'] ) ? '1' : '0';

        // Image generation
        $allowed_img = [ 'dalle', 'unsplash', 'pixabay', 'pexels' ];
        $clean['image_provider'] = in_array( $input['image_provider'] ?? 'dalle', $allowed_img, true )
            ? $input['image_provider']
            : 'dalle';
        $clean['unsplash_api_key'] = sanitize_text_field( $input['unsplash_api_key'] ?? '' );
        $clean['pixabay_api_key']  = sanitize_text_field( $input['pixabay_api_key'] ?? '' );
        $clean['pexels_api_key']   = sanitize_text_field( $input['pexels_api_key'] ?? '' );
        $clean['image_fallback'] = ! empty( $input['image_fallback'] ) ? '1' : '0';

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
            'ja' => __( 'Japanese', 'seo-bot-pro' ),
            'ko' => __( 'Korean', 'seo-bot-pro' ),
            'ru' => __( 'Russian', 'seo-bot-pro' ),
            'pl' => __( 'Polish', 'seo-bot-pro' ),
            'sv' => __( 'Swedish', 'seo-bot-pro' ),
            'no' => __( 'Norwegian', 'seo-bot-pro' ),
            'da' => __( 'Danish', 'seo-bot-pro' ),
            'fi' => __( 'Finnish', 'seo-bot-pro' ),
            'cs' => __( 'Czech', 'seo-bot-pro' ),
            'ro' => __( 'Romanian', 'seo-bot-pro' ),
            'hu' => __( 'Hungarian', 'seo-bot-pro' ),
            'th' => __( 'Thai', 'seo-bot-pro' ),
            'vi' => __( 'Vietnamese', 'seo-bot-pro' ),
            'id' => __( 'Indonesian', 'seo-bot-pro' ),
            'hi' => __( 'Hindi', 'seo-bot-pro' ),
            'uk' => __( 'Ukrainian', 'seo-bot-pro' ),
        ];
    }

    /**
     * Tone label map.
     */
    public static function tone_labels(): array {
        return [
            'professional'  => __( 'Professional', 'seo-bot-pro' ),
            'sales'         => __( 'Sales', 'seo-bot-pro' ),
            'neutral'       => __( 'Neutral', 'seo-bot-pro' ),
            'casual'        => __( 'Casual', 'seo-bot-pro' ),
            'formal'        => __( 'Formal', 'seo-bot-pro' ),
            'creative'      => __( 'Creative', 'seo-bot-pro' ),
            'humorous'      => __( 'Humorous', 'seo-bot-pro' ),
            'academic'      => __( 'Academic', 'seo-bot-pro' ),
            'persuasive'    => __( 'Persuasive', 'seo-bot-pro' ),
            'conversational' => __( 'Conversational', 'seo-bot-pro' ),
            'authoritative' => __( 'Authoritative', 'seo-bot-pro' ),
            'empathetic'    => __( 'Empathetic', 'seo-bot-pro' ),
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
     * Gemini model options.
     */
    public static function gemini_models(): array {
        return [
            'gemini-2.0-flash'         => 'Gemini 2.0 Flash (recommended)',
            'gemini-2.5-pro-preview'   => 'Gemini 2.5 Pro Preview',
            'gemini-2.5-flash-preview' => 'Gemini 2.5 Flash Preview',
            'gemini-1.5-pro'           => 'Gemini 1.5 Pro',
            'gemini-1.5-flash'         => 'Gemini 1.5 Flash',
        ];
    }

    /**
     * SEO plugin options.
     */
    public static function seo_plugin_labels(): array {
        return [
            'rank_math' => __( 'Rank Math SEO', 'seo-bot-pro' ),
            'yoast'     => __( 'Yoast SEO', 'seo-bot-pro' ),
            'aioseo'    => __( 'All in One SEO', 'seo-bot-pro' ),
            'both'      => __( 'Both (Rank Math + Yoast)', 'seo-bot-pro' ),
            'none'      => __( 'None (use built-in meta only)', 'seo-bot-pro' ),
        ];
    }

    /**
     * Export settings as JSON (without API keys).
     */
    public static function export_settings(): string {
        $settings = get_option( 'sbp_settings', [] );
        // Remove API keys for security
        $safe = $settings;
        unset( $safe['openai_api_key'], $safe['claude_api_key'], $safe['gemini_api_key'],
               $safe['unsplash_api_key'], $safe['pixabay_api_key'], $safe['pexels_api_key'],
               $safe['indexnow_api_key'] );
        return wp_json_encode( $safe, JSON_PRETTY_PRINT );
    }

    /**
     * Import settings from JSON (preserving existing API keys).
     */
    public static function import_settings( string $json ): bool {
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return false;
        }
        // Merge with existing (preserve API keys)
        $current = get_option( 'sbp_settings', [] );
        $merged = array_merge( $current, $data );
        $clean = self::sanitize_settings( $merged );
        // Restore API keys from current
        foreach ( ['openai_api_key','claude_api_key','gemini_api_key','unsplash_api_key','pixabay_api_key','pexels_api_key','indexnow_api_key'] as $key ) {
            if ( isset( $current[$key] ) ) {
                $clean[$key] = $current[$key];
            }
        }
        update_option( 'sbp_settings', $clean );
        return true;
    }
}
