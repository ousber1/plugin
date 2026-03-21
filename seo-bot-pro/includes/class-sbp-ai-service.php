<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all communication with AI providers (OpenAI, Claude/Anthropic, Google Gemini).
 */
class SBP_AI_Service {

    private string $provider;
    private string $openai_key;
    private string $claude_key;
    private string $gemini_key;
    private string $model;
    private string $language;
    private string $tone;
    private float  $temperature;
    private int    $max_tokens;

    public function __construct() {
        $this->provider    = SBP_Helpers::get_option( 'provider', 'openai' );
        $this->openai_key  = SBP_Helpers::get_option( 'openai_api_key' );
        $this->claude_key  = SBP_Helpers::get_option( 'claude_api_key' );
        $this->gemini_key  = SBP_Helpers::get_option( 'gemini_api_key' );
        $this->model       = SBP_Helpers::get_option( 'model', $this->default_model() );
        $this->language    = SBP_Helpers::get_option( 'language', 'en' );
        $this->tone        = SBP_Helpers::get_option( 'tone', 'professional' );
        $this->temperature = (float) SBP_Helpers::get_option( 'temperature', 0.4 );
        $this->max_tokens  = (int) SBP_Helpers::get_option( 'max_tokens', 1024 );
    }

    private function default_model(): string {
        if ( $this->provider === 'claude' ) {
            return 'claude-sonnet-4-6';
        }
        if ( $this->provider === 'gemini' ) {
            return 'gemini-2.0-flash';
        }
        return 'gpt-4o-mini';
    }

    /**
     * Whether the active provider is configured.
     */
    public function is_configured(): bool {
        if ( $this->provider === 'claude' ) {
            return ! empty( $this->claude_key );
        }
        if ( $this->provider === 'gemini' ) {
            return ! empty( $this->gemini_key );
        }
        return ! empty( $this->openai_key );
    }

    /**
     * Test the API connection for a given provider.
     *
     * @return array|WP_Error  { provider, model, message }
     */
    public function test_connection( string $provider = '' ) {
        $provider = $provider ?: $this->provider;

        $prompt = 'Respond with exactly: {"status":"ok"}';
        $old_max = $this->max_tokens;
        $this->max_tokens = 64;

        $result = null;
        switch ( $provider ) {
            case 'openai':
                if ( empty( $this->openai_key ) ) {
                    return new WP_Error( 'no_api_key', __( 'OpenAI API key is not set.', 'seo-bot-pro' ) );
                }
                $result = $this->call_openai( $prompt );
                break;
            case 'claude':
                if ( empty( $this->claude_key ) ) {
                    return new WP_Error( 'no_api_key', __( 'Claude API key is not set.', 'seo-bot-pro' ) );
                }
                $result = $this->call_claude( $prompt );
                break;
            case 'gemini':
                if ( empty( $this->gemini_key ) ) {
                    return new WP_Error( 'no_api_key', __( 'Gemini API key is not set.', 'seo-bot-pro' ) );
                }
                $result = $this->call_gemini( $prompt );
                break;
            default:
                $this->max_tokens = $old_max;
                return new WP_Error( 'invalid_provider', __( 'Unknown provider.', 'seo-bot-pro' ) );
        }

        $this->max_tokens = $old_max;

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'provider' => $provider,
            'model'    => $this->model,
            'message'  => __( 'Connection successful.', 'seo-bot-pro' ),
        ];
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

    // ── Image generation ───────────────────────────

    /**
     * Generate/fetch an image based on the configured image provider.
     *
     * Providers:
     *  - dalle    : OpenAI DALL-E 3 (requires OpenAI key, paid)
     *  - unsplash : Unsplash API (requires free Unsplash API key)
     *  - pixabay  : Pixabay API (requires free Pixabay API key)
     *  - pexels   : Pexels API (requires free Pexels API key)
     *
     * @param string $prompt   Description or search query for the image.
     * @param string $topic    The article topic (used as fallback search query).
     * @return string|WP_Error Image URL on success.
     */
    public function generate_image( string $prompt, string $topic = '' ) {
        $image_provider = SBP_Helpers::get_option( 'image_provider', 'dalle' );
        $use_fallback   = (bool) SBP_Helpers::get_option( 'image_fallback', true );

        $result = $this->fetch_image_from_provider( $image_provider, $prompt, $topic );

        // If primary provider failed and fallback is enabled, try free stock providers.
        if ( is_wp_error( $result ) && $use_fallback && $image_provider === 'dalle' ) {
            $fallback_order = [ 'unsplash', 'pixabay', 'pexels' ];
            foreach ( $fallback_order as $fallback ) {
                $fb_result = $this->fetch_image_from_provider( $fallback, $prompt, $topic );
                if ( ! is_wp_error( $fb_result ) ) {
                    return $fb_result;
                }
            }
        }

        return $result;
    }

    /**
     * Fetch image from a specific provider.
     *
     * @return string|WP_Error
     */
    private function fetch_image_from_provider( string $provider, string $prompt, string $topic ) {
        switch ( $provider ) {
            case 'unsplash':
                return $this->fetch_unsplash_image( $topic ?: $prompt );
            case 'pixabay':
                return $this->fetch_pixabay_image( $topic ?: $prompt );
            case 'pexels':
                return $this->fetch_pexels_image( $topic ?: $prompt );
            case 'dalle':
            default:
                return $this->generate_dalle_image( $prompt );
        }
    }

    /**
     * Generate image via OpenAI DALL-E 3.
     */
    private function generate_dalle_image( string $prompt ): string|\WP_Error {
        if ( empty( $this->openai_key ) ) {
            return new WP_Error( 'no_api_key', __( 'OpenAI API key is required for DALL-E image generation. Add your key in Settings > OpenAI API Key.', 'seo-bot-pro' ) );
        }

        $body = [
            'model'  => 'dall-e-3',
            'prompt' => $prompt,
            'n'      => 1,
            'size'   => '1792x1024',
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
            'timeout' => 120,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->openai_key,
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', __( 'DALL-E connection failed: ', 'seo-bot-pro' ) . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? __( 'DALL-E API error.', 'seo-bot-pro' );
            return new WP_Error( 'api_error', 'DALL-E: ' . $msg );
        }

        if ( empty( $data['data'][0]['url'] ) ) {
            return new WP_Error( 'api_error', __( 'DALL-E returned no image URL.', 'seo-bot-pro' ) );
        }

        return $data['data'][0]['url'];
    }

    /**
     * Fetch image from Unsplash API (free).
     */
    private function fetch_unsplash_image( string $query ): string|\WP_Error {
        $api_key = SBP_Helpers::get_option( 'unsplash_api_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'Unsplash API key not configured. Get a free key at unsplash.com/developers and add it in Settings.', 'seo-bot-pro' ) );
        }

        // Extract 2-3 key words for better search
        $search = $this->simplify_search_query( $query );

        $url = add_query_arg( [
            'query'       => $search,
            'orientation' => 'landscape',
            'per_page'    => 1,
            'client_id'   => $api_key,
        ], 'https://api.unsplash.com/search/photos' );

        $response = wp_remote_get( $url, [ 'timeout' => 30 ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', __( 'Unsplash connection failed: ', 'seo-bot-pro' ) . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            return new WP_Error( 'api_error', 'Unsplash: ' . ( $data['errors'][0] ?? "HTTP {$code}" ) );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['results'][0]['urls']['regular'] ) ) {
            return new WP_Error( 'no_image', __( 'Unsplash: No images found for this topic. Try a different topic.', 'seo-bot-pro' ) );
        }

        return $data['results'][0]['urls']['regular'];
    }

    /**
     * Fetch image from Pixabay API (free).
     */
    private function fetch_pixabay_image( string $query ): string|\WP_Error {
        $api_key = SBP_Helpers::get_option( 'pixabay_api_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'Pixabay API key not configured. Get a free key at pixabay.com/api/docs/ and add it in Settings.', 'seo-bot-pro' ) );
        }

        $search = $this->simplify_search_query( $query );

        $url = add_query_arg( [
            'key'          => $api_key,
            'q'            => $search,
            'image_type'   => 'photo',
            'orientation'  => 'horizontal',
            'per_page'     => 3,
            'safesearch'   => 'true',
            'min_width'    => 1200,
        ], 'https://pixabay.com/api/' );

        $response = wp_remote_get( $url, [ 'timeout' => 30 ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', __( 'Pixabay connection failed: ', 'seo-bot-pro' ) . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', "Pixabay: HTTP {$code}" );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['hits'][0]['largeImageURL'] ) ) {
            return new WP_Error( 'no_image', __( 'Pixabay: No images found for this topic. Try a different topic.', 'seo-bot-pro' ) );
        }

        return $data['hits'][0]['largeImageURL'];
    }

    /**
     * Fetch image from Pexels API (free).
     */
    private function fetch_pexels_image( string $query ): string|\WP_Error {
        $api_key = SBP_Helpers::get_option( 'pexels_api_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'Pexels API key not configured. Get a free key at pexels.com/api/ and add it in Settings.', 'seo-bot-pro' ) );
        }

        $search = $this->simplify_search_query( $query );

        $url = add_query_arg( [
            'query'       => $search,
            'orientation' => 'landscape',
            'per_page'    => 1,
            'size'        => 'large',
        ], 'https://api.pexels.com/v1/search' );

        $response = wp_remote_get( $url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => $api_key,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', __( 'Pexels connection failed: ', 'seo-bot-pro' ) . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', "Pexels: HTTP {$code}" );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['photos'][0]['src']['large2x'] ) ) {
            return new WP_Error( 'no_image', __( 'Pexels: No images found for this topic. Try a different topic.', 'seo-bot-pro' ) );
        }

        return $data['photos'][0]['src']['large2x'];
    }

    /**
     * Simplify a long prompt/topic into 2-3 keyword search query.
     */
    private function simplify_search_query( string $text ): string {
        // Remove common stop words and keep 2-4 meaningful words
        $stop_words = [ 'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'shall', 'can', 'to', 'of', 'in', 'for', 'on', 'with', 'at',
            'by', 'from', 'as', 'into', 'about', 'like', 'through', 'after', 'before',
            'between', 'out', 'against', 'during', 'without', 'how', 'what', 'which',
            'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'or', 'and', 'but',
            'if', 'while', 'because', 'so', 'than', 'too', 'very', 'just', 'best', 'top',
            'most', 'your', 'our', 'my', 'its', 'his', 'her', 'their', 'not', 'no', 'nor',
            'only', 'own', 'same', 'also', 'other', 'each', 'every', 'both', 'few', 'more',
            'many', 'such', 'all', 'any', 'some', 'new', 'old', 'why', 'when', 'where' ];

        $words = preg_split( '/\s+/', strtolower( trim( $text ) ) );
        $words = array_filter( $words, function( $w ) use ( $stop_words ) {
            return strlen( $w ) > 2 && ! in_array( $w, $stop_words, true );
        });

        $words = array_values( $words );

        return implode( ' ', array_slice( $words, 0, 3 ) );
    }

    /**
     * Generate an SEO-structured article with internal/external links.
     *
     * @return array|WP_Error  { title, content, excerpt, tags, image_prompt }
     */
    public function generate_seo_article( string $topic, string $length, string $template, string $instructions, array $internal_links = [] ) {
        $lang = $this->lang_label();

        $word_targets = [
            'short'  => '300-500',
            'medium' => '800-1200',
            'long'   => '1500-2500',
        ];
        $target = $word_targets[ $length ] ?? '800-1200';

        $extra = $instructions ? "\nAdditional instructions: {$instructions}\n" : '';

        // Build template instructions
        $template_guide = $this->get_template_guide( $template );

        // Build internal links context
        $links_context = '';
        if ( ! empty( $internal_links ) ) {
            $links_json    = wp_json_encode( array_slice( $internal_links, 0, 20 ) );
            $links_context = "\n\nInternal links available on this website (use 3-5 of them naturally in the content as <a href=\"URL\">anchor text</a>):\n{$links_json}\n";
        }

        $prompt = "You are an expert content writer, SEO specialist, and web developer. Write a complete, perfectly structured blog article.\n\n"
                . "Topic: {$topic}\n"
                . "Language: {$lang}\n"
                . "Tone: {$this->tone}\n"
                . "Target length: {$target} words\n"
                . "Article template: {$template}\n"
                . "{$extra}\n"
                . "SEO STRUCTURE REQUIREMENTS:\n"
                . "{$template_guide}\n"
                . "\nGENERAL REQUIREMENTS:\n"
                . "- Use proper semantic HTML: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>\n"
                . "- Include a compelling introduction paragraph with a hook\n"
                . "- Include a conclusion with a call to action\n"
                . "- Add 1-2 external authority links (reputable sources like Wikipedia, industry leaders) as <a href=\"URL\" target=\"_blank\" rel=\"noopener\">anchor</a>\n"
                . "- Write naturally for humans while being SEO-optimized\n"
                . "- Use the focus keyword naturally throughout (2-3% density)\n"
                . "- Write a compelling meta-friendly excerpt (1-2 sentences)\n"
                . "- Suggest 3-5 relevant tags\n"
                . "- Suggest a detailed image prompt for DALL-E to generate a featured image for this article\n"
                . "{$links_context}\n"
                . "\nReturn a JSON object with:\n"
                . "- title (string): SEO-optimized article title (50-60 chars)\n"
                . "- content (string): full HTML content with all links included\n"
                . "- excerpt (string): 1-2 sentence meta description\n"
                . "- tags (array of strings): 3-5 tags\n"
                . "- focus_keyword (string): primary focus keyword for this article\n"
                . "- image_prompt (string): detailed DALL-E prompt for a professional featured image (describe style, composition, colors)\n\n"
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

    /**
     * Get template-specific structure guide.
     */
    private function get_template_guide( string $template ): string {
        $guides = [
            'blog' =>
                "- H1: Article title (handled by WordPress)\n"
                . "- Introduction paragraph (2-3 sentences with the keyword)\n"
                . "- H2: Main Section 1\n"
                . "  - 2-3 paragraphs with H3 subsections if needed\n"
                . "- H2: Main Section 2\n"
                . "  - 2-3 paragraphs\n"
                . "- H2: Main Section 3\n"
                . "  - 2-3 paragraphs\n"
                . "- H2: Conclusion\n"
                . "  - Summary paragraph + call to action",

            'listicle' =>
                "- Introduction: hook + overview of the list\n"
                . "- H2: #1 Item Name\n"
                . "  - Description, pros/cons, details paragraph\n"
                . "- H2: #2 Item Name\n"
                . "  - Description paragraph\n"
                . "- (continue for 5-10 items depending on length)\n"
                . "- H2: Conclusion / Final Thoughts\n"
                . "  - Summary + recommendation",

            'howto' =>
                "- Introduction: what the reader will learn\n"
                . "- H2: What You Need / Prerequisites\n"
                . "  - Bulleted list of requirements\n"
                . "- H2: Step 1 – [Action]\n"
                . "  - Detailed instruction paragraph\n"
                . "- H2: Step 2 – [Action]\n"
                . "  - Detailed instruction paragraph\n"
                . "- (continue steps)\n"
                . "- H2: Tips & Best Practices\n"
                . "  - Bulleted tips\n"
                . "- H2: Conclusion\n"
                . "  - Summary of steps + encouragement",

            'review' =>
                "- Introduction: what is being reviewed and why\n"
                . "- H2: Overview / What is [Product]?\n"
                . "  - Description paragraph\n"
                . "- H2: Key Features\n"
                . "  - Feature list with descriptions\n"
                . "- H2: Pros and Cons\n"
                . "  - Two-column or bulleted pros/cons\n"
                . "- H2: Pricing\n"
                . "  - Price details\n"
                . "- H2: Who Is It For?\n"
                . "  - Target audience description\n"
                . "- H2: Verdict\n"
                . "  - Final rating/recommendation",

            'comparison' =>
                "- Introduction: what is being compared and why\n"
                . "- H2: Quick Comparison Table\n"
                . "  - HTML table with key differences\n"
                . "- H2: [Option A] – Overview\n"
                . "  - Description, features, pros/cons\n"
                . "- H2: [Option B] – Overview\n"
                . "  - Description, features, pros/cons\n"
                . "- H2: Head-to-Head: Key Differences\n"
                . "  - Detailed comparison by category\n"
                . "- H2: Which Should You Choose?\n"
                . "  - Recommendation based on use case",
        ];

        return $guides[ $template ] ?? $guides['blog'];
    }

    // ── Private helpers ─────────────────────────────

    private function lang_label(): string {
        $labels = SBP_Helpers::language_labels();
        return $labels[ $this->language ] ?? 'English';
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

        if ( $this->provider === 'gemini' ) {
            return $this->call_gemini( $prompt );
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
     * Call the Google Gemini API.
     *
     * @return string|WP_Error
     */
    private function call_gemini( string $prompt ) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->gemini_key;

        $body = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => $this->temperature,
                'maxOutputTokens' => $this->max_tokens,
            ],
        ];

        $response = wp_remote_post( $url, [
            'timeout' => 90,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? __( 'Unknown Gemini API error.', 'seo-bot-pro' );
            return new WP_Error( 'api_error', 'Gemini: ' . $msg );
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ( empty( $text ) ) {
            return new WP_Error( 'api_error', __( 'Empty response from Gemini API.', 'seo-bot-pro' ) );
        }

        return $text;
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
