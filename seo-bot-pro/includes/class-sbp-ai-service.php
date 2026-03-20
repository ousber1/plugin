<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all communication with AI providers (OpenAI + Claude/Anthropic).
 */
class SBP_AI_Service {

    private string $provider;
    private string $openai_key;
    private string $claude_key;
    private string $model;
    private string $language;
    private string $tone;
    private float  $temperature;
    private int    $max_tokens;

    public function __construct() {
        $this->provider    = SBP_Helpers::get_option( 'provider', 'openai' );
        $this->openai_key  = SBP_Helpers::get_option( 'openai_api_key' );
        $this->claude_key  = SBP_Helpers::get_option( 'claude_api_key' );
        $this->model       = SBP_Helpers::get_option( 'model', $this->default_model() );
        $this->language    = SBP_Helpers::get_option( 'language', 'en' );
        $this->tone        = SBP_Helpers::get_option( 'tone', 'professional' );
        $this->temperature = (float) SBP_Helpers::get_option( 'temperature', 0.4 );
        $this->max_tokens  = (int) SBP_Helpers::get_option( 'max_tokens', 1024 );
    }

    private function default_model(): string {
        return $this->provider === 'claude' ? 'claude-sonnet-4-6' : 'gpt-4o-mini';
    }

    /**
     * Whether the active provider is configured.
     */
    public function is_configured(): bool {
        if ( $this->provider === 'claude' ) {
            return ! empty( $this->claude_key );
        }
        return ! empty( $this->openai_key );
    }

    /**
     * Get the active provider name.
     */
    public function get_provider(): string {
        return $this->provider;
    }

    // ── Optimize ────────────────────────────────────

    /**
     * Optimize a post – returns meta_title, meta_description, meta_keywords, og_title, og_description.
     *
     * @return array|WP_Error
     */
    public function optimize( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain  = SBP_Helpers::content_to_plain( $post->post_content );
        $title  = $post->post_title;
        $prompt = $this->build_optimize_prompt( $title, $plain );

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    // ── Generate FAQs ───────────────────────────────

    /**
     * @return array|WP_Error
     */
    public function generate_faqs( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain  = SBP_Helpers::content_to_plain( $post->post_content );
        $prompt = $this->build_faq_prompt( $post->post_title, $plain );

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    // ── Generate image ALT ──────────────────────────

    /**
     * @return string|WP_Error
     */
    public function generate_alt( string $context, string $image_url ) {
        $lang = $this->lang_label();

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

    // ── Internal links ──────────────────────────────

    /**
     * @return array|WP_Error
     */
    public function suggest_internal_links( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain = SBP_Helpers::content_to_plain( $post->post_content );

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
        $lang        = $this->lang_label();

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

    // ── Keyword generation ──────────────────────────

    /**
     * Generate focus keywords for a post.
     *
     * @return array|WP_Error  { keywords: string[], primary: string }
     */
    public function generate_keywords( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain = SBP_Helpers::content_to_plain( $post->post_content );
        $lang  = $this->lang_label();

        $prompt = "You are an SEO expert. Analyze the following content and generate SEO keywords.\n\n"
                . "Title: {$post->post_title}\n"
                . "Content: {$plain}\n\n"
                . "Language: {$lang}\n\n"
                . "Return a JSON object with:\n"
                . "- primary (string): the single best focus keyword/keyphrase\n"
                . "- keywords (array of strings): 5-10 relevant secondary keywords\n\n"
                . "Return ONLY valid JSON, no markdown.";

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    // ── Slug optimization ───────────────────────────

    /**
     * Suggest an SEO-friendly slug.
     *
     * @return array|WP_Error  { slug: string }
     */
    public function optimize_slug( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $prompt = "You are an SEO expert. Generate the best SEO-friendly URL slug for this article.\n\n"
                . "Title: {$post->post_title}\n\n"
                . "Rules:\n"
                . "- Lowercase, hyphen-separated\n"
                . "- Max 5-6 words\n"
                . "- Include the primary keyword\n"
                . "- No stop words (the, a, an, is, etc.)\n\n"
                . "Return a JSON object with a single key \"slug\" containing the slug string.\n"
                . "Return ONLY valid JSON, no markdown.";

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    // ── Article generation ──────────────────────────

    /**
     * Generate a full article with AI.
     *
     * @return array|WP_Error  { title, content, excerpt, tags }
     */
    public function generate_article( string $topic, string $length = 'medium', string $instructions = '' ) {
        $lang = $this->lang_label();

        $word_targets = [
            'short'  => '300-500',
            'medium' => '800-1200',
            'long'   => '1500-2500',
        ];
        $target = $word_targets[ $length ] ?? '800-1200';

        $extra = $instructions ? "\nAdditional instructions: {$instructions}\n" : '';

        $prompt = "You are an expert content writer and SEO specialist. Write a complete, well-structured blog article.\n\n"
                . "Topic: {$topic}\n"
                . "Language: {$lang}\n"
                . "Tone: {$this->tone}\n"
                . "Target length: {$target} words\n"
                . "{$extra}\n"
                . "Requirements:\n"
                . "- Engaging, SEO-optimized title\n"
                . "- Well-structured HTML content with H2 and H3 subheadings\n"
                . "- Use <p>, <h2>, <h3>, <ul>, <li>, <strong>, <em> tags\n"
                . "- Include an introduction and conclusion\n"
                . "- Write a compelling 1-2 sentence excerpt\n"
                . "- Suggest 3-5 relevant tags\n\n"
                . "Return a JSON object with:\n"
                . "- title (string): the article title\n"
                . "- content (string): full HTML content\n"
                . "- excerpt (string): 1-2 sentence summary\n"
                . "- tags (array of strings): 3-5 tags\n\n"
                . "Return ONLY valid JSON, no markdown code blocks.";

        // Use higher max_tokens for article generation
        $old_max = $this->max_tokens;
        $this->max_tokens = max( $this->max_tokens, 4096 );

        $result = $this->call_api( $prompt );

        $this->max_tokens = $old_max;

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    /**
     * Generate an excerpt for an existing post.
     *
     * @return string|WP_Error
     */
    public function generate_excerpt( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain = SBP_Helpers::content_to_plain( $post->post_content );
        $lang  = $this->lang_label();

        $prompt = "Write a compelling 1-2 sentence excerpt/summary for the following article. "
                . "Language: {$lang}. Tone: {$this->tone}.\n\n"
                . "Title: {$post->post_title}\n"
                . "Content: {$plain}\n\n"
                . "Return ONLY the excerpt text, nothing else.";

        $result = $this->call_api( $prompt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return sanitize_text_field( trim( $result, '"' ) );
    }

    /**
     * Rewrite/improve existing content via AI.
     *
     * @return array|WP_Error  { content, excerpt }
     */
    public function rewrite_content( int $post_id, string $instructions = '' ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found.', 'seo-bot-pro' ) );
        }

        $plain = SBP_Helpers::content_to_plain( $post->post_content );
        $lang  = $this->lang_label();
        $extra = $instructions ? "\nAdditional instructions: {$instructions}\n" : '';

        $prompt = "You are an expert content writer. Rewrite and improve the following article to make it more engaging, "
                . "better structured, and SEO-optimized.\n\n"
                . "Title: {$post->post_title}\n"
                . "Original content: {$plain}\n"
                . "Language: {$lang}\n"
                . "Tone: {$this->tone}\n"
                . "{$extra}\n"
                . "Requirements:\n"
                . "- Improve readability and flow\n"
                . "- Add H2/H3 subheadings if missing\n"
                . "- Use proper HTML tags (<p>, <h2>, <h3>, <ul>, <li>, <strong>)\n"
                . "- Keep the same topic and key information\n"
                . "- Write a new compelling excerpt\n\n"
                . "Return a JSON object with:\n"
                . "- content (string): the rewritten HTML content\n"
                . "- excerpt (string): new 1-2 sentence excerpt\n\n"
                . "Return ONLY valid JSON, no markdown code blocks.";

        $old_max = $this->max_tokens;
        $this->max_tokens = max( $this->max_tokens, 4096 );

        $result = $this->call_api( $prompt );

        $this->max_tokens = $old_max;

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->parse_json_response( $result );
    }

    // ── Private helpers ─────────────────────────────

    private function lang_label(): string {
        $map = [ 'en' => 'English', 'fr' => 'French', 'ar' => 'Arabic', 'es' => 'Spanish', 'de' => 'German', 'pt' => 'Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'tr' => 'Turkish', 'zh' => 'Chinese' ];
        return $map[ $this->language ] ?? 'English';
    }

    private function build_optimize_prompt( string $title, string $content ): string {
        $lang = $this->lang_label();

        return "You are an SEO expert. Optimize the following content for search engines.\n\n"
             . "Title: {$title}\n"
             . "Content: {$content}\n\n"
             . "Language: {$lang}\n"
             . "Tone: {$this->tone}\n\n"
             . "Return a JSON object with exactly these keys:\n"
             . "- meta_title (max 60 characters, include primary keyword)\n"
             . "- meta_description (max 155 characters, compelling and keyword-rich)\n"
             . "- meta_keywords (comma-separated list of 5-8 relevant keywords)\n"
             . "- og_title (max 60 characters, engaging for social media)\n"
             . "- og_description (max 200 characters, compelling for social sharing)\n\n"
             . "Return ONLY valid JSON, no markdown code blocks.";
    }

    private function build_faq_prompt( string $title, string $content ): string {
        $lang = $this->lang_label();

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
     * Route API calls to the correct provider.
     *
     * @return string|WP_Error
     */
    private function call_api( string $prompt ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error(
                'no_api_key',
                sprintf( __( '%s API key is not configured.', 'seo-bot-pro' ), ucfirst( $this->provider ) )
            );
        }

        if ( $this->provider === 'claude' ) {
            return $this->call_claude( $prompt );
        }

        return $this->call_openai( $prompt );
    }

    /**
     * Call the OpenAI Chat Completions API.
     *
     * @return string|WP_Error
     */
    private function call_openai( string $prompt ) {
        $body = [
            'model'       => $this->model,
            'messages'    => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
            'temperature' => $this->temperature,
            'max_tokens'  => $this->max_tokens,
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->openai_key,
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? __( 'Unknown OpenAI API error.', 'seo-bot-pro' );
            return new WP_Error( 'api_error', $msg );
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Call the Anthropic Claude Messages API.
     *
     * @return string|WP_Error
     */
    private function call_claude( string $prompt ) {
        $body = [
            'model'      => $this->model,
            'max_tokens' => $this->max_tokens,
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ];

        // Claude uses temperature in the top-level body (optional)
        if ( $this->temperature > 0 ) {
            $body['temperature'] = $this->temperature;
        }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 90,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->claude_key,
                'anthropic-version'  => '2023-06-01',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? __( 'Unknown Claude API error.', 'seo-bot-pro' );
            return new WP_Error( 'api_error', $msg );
        }

        // Claude returns content as an array of blocks
        if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
            foreach ( $data['content'] as $block ) {
                if ( ( $block['type'] ?? '' ) === 'text' ) {
                    return $block['text'];
                }
            }
        }

        return new WP_Error( 'api_error', __( 'Empty response from Claude API.', 'seo-bot-pro' ) );
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
