<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all communication with the OpenAI API.
 */
class SBP_AI_Service {

    private string $api_key;
    private string $model;
    private string $language;
    private string $tone;

    public function __construct() {
        $this->api_key  = SBP_Helpers::get_option( 'api_key' );
        $this->model    = SBP_Helpers::get_option( 'model', 'gpt-4o-mini' );
        $this->language = SBP_Helpers::get_option( 'language', 'en' );
        $this->tone     = SBP_Helpers::get_option( 'tone', 'professional' );
    }

    /**
     * Whether the service is configured.
     */
    public function is_configured(): bool {
        return ! empty( $this->api_key );
    }

    /**
     * Optimize a post – returns meta_title, meta_description.
     *
     * @return array|WP_Error
     */
    public function optimize( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain = SBP_Helpers::content_to_plain( $post->post_content );
        $title = $post->post_title;

        $prompt = $this->build_optimize_prompt( $title, $plain );

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    /**
     * Generate FAQs for a post.
     *
     * @return array|WP_Error
     */
    public function generate_faqs( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain = SBP_Helpers::content_to_plain( $post->post_content );
        $prompt = $this->build_faq_prompt( $post->post_title, $plain );

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    /**
     * Generate an image ALT text.
     *
     * @return string|WP_Error
     */
    public function generate_alt( string $context, string $image_url ) {
        $lang_map = [ 'en' => 'English', 'fr' => 'French', 'ar' => 'Arabic' ];
        $lang     = $lang_map[ $this->language ] ?? 'English';

        $prompt = "Generate a concise, descriptive ALT text (max 125 characters) for an image "
                . "found in the following article context. Language: {$lang}.\n\n"
                . "Article context: {$context}\n"
                . "Image URL hint: {$image_url}\n\n"
                . "Return ONLY the ALT text string, nothing else.";

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return sanitize_text_field( trim( $result, '"' ) );
    }

    /**
     * Suggest internal links for a post.
     *
     * @return array|WP_Error  Array of { post_id, title, url, anchor }
     */
    public function suggest_internal_links( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain = SBP_Helpers::content_to_plain( $post->post_content );

        // Get other posts to reference
        $other_posts = get_posts( [
            'post_type'      => SBP_Helpers::post_types(),
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'exclude'        => [ $post_id ],
            'fields'         => 'ids',
        ] );

        if ( empty( $other_posts ) ) {
            return [];
        }

        $titles = [];
        foreach ( $other_posts as $pid ) {
            $titles[] = [
                'id'    => $pid,
                'title' => get_the_title( $pid ),
                'url'   => get_permalink( $pid ),
            ];
        }

        $titles_json = wp_json_encode( $titles );

        $lang_map = [ 'en' => 'English', 'fr' => 'French', 'ar' => 'Arabic' ];
        $lang     = $lang_map[ $this->language ] ?? 'English';

        $prompt = "You are an SEO expert. Given the following article content and a list of other articles on the same site, "
                . "suggest 3-5 internal links that would be relevant to add to the article.\n\n"
                . "Article content:\n{$plain}\n\n"
                . "Available articles:\n{$titles_json}\n\n"
                . "Language: {$lang}\n\n"
                . "Return a JSON array of objects with: id, title, url, anchor (suggested anchor text). "
                . "Return ONLY valid JSON, no markdown.";

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    // ── Private helpers ─────────────────────────────

    private function build_optimize_prompt( string $title, string $content ): string {
        $lang_map = [ 'en' => 'English', 'fr' => 'French', 'ar' => 'Arabic' ];
        $lang     = $lang_map[ $this->language ] ?? 'English';

        return "You are an SEO expert. Optimize the following content for search engines.\n\n"
             . "Title: {$title}\n"
             . "Content: {$content}\n\n"
             . "Language: {$lang}\n"
             . "Tone: {$this->tone}\n\n"
             . "Return a JSON object with exactly these keys:\n"
             . "- meta_title (max 60 characters, include primary keyword)\n"
             . "- meta_description (max 155 characters, compelling and keyword-rich)\n\n"
             . "Return ONLY valid JSON, no markdown code blocks.";
    }

    private function build_faq_prompt( string $title, string $content ): string {
        $lang_map = [ 'en' => 'English', 'fr' => 'French', 'ar' => 'Arabic' ];
        $lang     = $lang_map[ $this->language ] ?? 'English';

        return "You are an SEO expert. Generate 3-5 frequently asked questions with answers "
             . "based on the following content.\n\n"
             . "Title: {$title}\n"
             . "Content: {$content}\n\n"
             . "Language: {$lang}\n"
             . "Tone: {$this->tone}\n\n"
             . "Return a JSON object with a key \"faqs\" containing an array of objects, each with:\n"
             . "- question (string)\n"
             . "- answer (string, 1-3 sentences)\n\n"
             . "Return ONLY valid JSON, no markdown code blocks.";
    }

    /**
     * Call the OpenAI Chat Completions API.
     *
     * @return string|WP_Error  Raw assistant message content.
     */
    private function call_api( string $prompt ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'no_api_key', __( 'OpenAI API key is not configured.', 'seo-bot-pro' ) );
        }

        $body = [
            'model'       => $this->model,
            'messages'    => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
            'temperature' => 0.4,
            'max_tokens'  => 1000,
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? __( 'Unknown API error.', 'seo-bot-pro' );
            return new WP_Error( 'api_error', $msg );
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Parse a JSON string from the AI response.
     *
     * @return array|WP_Error
     */
    private function parse_json_response( string $raw ) {
        // Strip markdown code fences if present.
        $raw = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw ) );
        $raw = preg_replace( '/```\s*$/', '', $raw );

        $decoded = json_decode( trim( $raw ), true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'parse_error', __( 'Failed to parse AI response.', 'seo-bot-pro' ) );
        }

        return $decoded;
    }
}
