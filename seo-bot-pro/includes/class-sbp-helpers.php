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

        $clean['api_key']  = sanitize_text_field( $input['api_key'] ?? '' );
        $clean['model']    = sanitize_text_field( $input['model'] ?? 'gpt-4o-mini' );

        $allowed_langs = [ 'en', 'fr', 'ar' ];
        $clean['language'] = in_array( $input['language'] ?? 'en', $allowed_langs, true )
            ? $input['language']
            : 'en';

        $allowed_tones = [ 'professional', 'sales', 'neutral' ];
        $clean['tone'] = in_array( $input['tone'] ?? 'professional', $allowed_tones, true )
            ? $input['tone']
            : 'professional';

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
        ];
    }
}
