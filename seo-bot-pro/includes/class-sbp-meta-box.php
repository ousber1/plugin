<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Meta box in the post/page/product editor.
 */
class SBP_Meta_Box {

    public function register() {
        foreach ( SBP_Helpers::post_types() as $type ) {
            add_meta_box(
                'sbp_seo_box',
                __( 'SEO Bot Pro – AI Optimizer', 'seo-bot-pro' ),
                [ $this, 'render' ],
                $type,
                'side',
                'high'
            );
        }
    }

    public function render( WP_Post $post ) {
        $meta_title   = get_post_meta( $post->ID, '_sbp_meta_title', true );
        $meta_desc    = get_post_meta( $post->ID, '_sbp_meta_description', true );
        $meta_kw      = get_post_meta( $post->ID, '_sbp_meta_keywords', true );
        $og_title     = get_post_meta( $post->ID, '_sbp_og_title', true );
        $og_desc      = get_post_meta( $post->ID, '_sbp_og_description', true );
        $focus_kw     = get_post_meta( $post->ID, '_sbp_focus_keyword', true );
        $noindex      = get_post_meta( $post->ID, '_sbp_noindex', true );
        $nofollow     = get_post_meta( $post->ID, '_sbp_nofollow', true );
        $canonical    = get_post_meta( $post->ID, '_sbp_canonical', true );
        $schema_type  = get_post_meta( $post->ID, '_sbp_schema_type', true );
        $provider     = SBP_Helpers::get_option( 'provider', 'openai' );
        $provider_lbl = $provider === 'claude' ? 'Claude' : 'OpenAI';
        ?>
        <div class="sbp-meta-box">
            <p class="sbp-provider-badge">
                <span class="sbp-badge sbp-badge-<?php echo esc_attr( $provider ); ?>">
                    <?php echo esc_html( $provider_lbl ); ?>
                </span>
            </p>

            <div class="sbp-meta-info">
                <p><strong><?php esc_html_e( 'Meta Title:', 'seo-bot-pro' ); ?></strong><br>
                    <span id="sbp-meta-title"><?php echo esc_html( $meta_title ?: '—' ); ?></span></p>
                <p><strong><?php esc_html_e( 'Meta Description:', 'seo-bot-pro' ); ?></strong><br>
                    <span id="sbp-meta-desc"><?php echo esc_html( $meta_desc ?: '—' ); ?></span></p>
                <p><strong><?php esc_html_e( 'Focus Keyword:', 'seo-bot-pro' ); ?></strong><br>
                    <span id="sbp-focus-kw"><?php echo esc_html( $focus_kw ?: '—' ); ?></span></p>
                <?php if ( $meta_kw ) : ?>
                    <p><strong><?php esc_html_e( 'Keywords:', 'seo-bot-pro' ); ?></strong><br>
                        <span id="sbp-keywords" class="sbp-keywords-list"><?php echo esc_html( $meta_kw ); ?></span></p>
                <?php endif; ?>
                <?php if ( $og_title ) : ?>
                    <p><strong><?php esc_html_e( 'OG Title:', 'seo-bot-pro' ); ?></strong><br>
                        <span id="sbp-og-title"><?php echo esc_html( $og_title ); ?></span></p>
                <?php endif; ?>
            </div>

            <div class="sbp-action-group">
                <button type="button" class="button button-primary sbp-optimize-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Optimize with AI', 'seo-bot-pro' ); ?>
                </button>

                <button type="button" class="button sbp-keywords-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Generate Keywords', 'seo-bot-pro' ); ?>
                </button>

                <button type="button" class="button sbp-faq-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Generate FAQ', 'seo-bot-pro' ); ?>
                </button>

                <button type="button" class="button sbp-links-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Suggest Links', 'seo-bot-pro' ); ?>
                </button>

                <button type="button" class="button sbp-alt-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Fix Image ALTs', 'seo-bot-pro' ); ?>
                </button>

                <button type="button" class="button sbp-slug-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Optimize Slug', 'seo-bot-pro' ); ?>
                </button>

                <button type="button" class="button sbp-excerpt-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Generate Excerpt', 'seo-bot-pro' ); ?>
                </button>

                <button type="button" class="button sbp-rewrite-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                    <?php esc_html_e( 'Rewrite Content', 'seo-bot-pro' ); ?>
                </button>
            </div>

            <hr>

            <!-- Robots Meta & Canonical -->
            <h4><?php esc_html_e( 'Advanced SEO', 'seo-bot-pro' ); ?></h4>
            <div class="sbp-advanced-seo">
                <label>
                    <input type="checkbox" id="sbp-noindex" value="1"
                        <?php checked( $noindex, '1' ); ?>>
                    <?php esc_html_e( 'noindex', 'seo-bot-pro' ); ?>
                </label>
                <label>
                    <input type="checkbox" id="sbp-nofollow" value="1"
                        <?php checked( $nofollow, '1' ); ?>>
                    <?php esc_html_e( 'nofollow', 'seo-bot-pro' ); ?>
                </label>

                <label for="sbp-canonical" style="display:block;margin-top:6px;">
                    <?php esc_html_e( 'Canonical URL:', 'seo-bot-pro' ); ?>
                </label>
                <input type="url" id="sbp-canonical" class="widefat"
                       value="<?php echo esc_attr( $canonical ); ?>"
                       placeholder="<?php esc_attr_e( 'https://example.com/page', 'seo-bot-pro' ); ?>">

                <label for="sbp-schema-type" style="display:block;margin-top:6px;">
                    <?php esc_html_e( 'Schema Type:', 'seo-bot-pro' ); ?>
                </label>
                <select id="sbp-schema-type" class="widefat">
                    <option value="" <?php selected( $schema_type, '' ); ?>><?php esc_html_e( '— Auto —', 'seo-bot-pro' ); ?></option>
                    <option value="article" <?php selected( $schema_type, 'article' ); ?>><?php esc_html_e( 'Article', 'seo-bot-pro' ); ?></option>
                    <option value="howto" <?php selected( $schema_type, 'howto' ); ?>><?php esc_html_e( 'HowTo', 'seo-bot-pro' ); ?></option>
                    <option value="product" <?php selected( $schema_type, 'product' ); ?>><?php esc_html_e( 'Product', 'seo-bot-pro' ); ?></option>
                    <option value="local_business" <?php selected( $schema_type, 'local_business' ); ?>><?php esc_html_e( 'Local Business', 'seo-bot-pro' ); ?></option>
                </select>

                <button type="button" class="button sbp-save-advanced-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                        style="margin-top:8px;">
                    <?php esc_html_e( 'Save SEO Settings', 'seo-bot-pro' ); ?>
                </button>
            </div>

            <hr>
            <h4><?php esc_html_e( 'Content Analysis', 'seo-bot-pro' ); ?></h4>
            <label for="sbp-keyword"><?php esc_html_e( 'Focus Keyword:', 'seo-bot-pro' ); ?></label>
            <input type="text" id="sbp-keyword" class="widefat"
                   value="<?php echo esc_attr( $focus_kw ); ?>"
                   placeholder="<?php esc_attr_e( 'e.g. best running shoes', 'seo-bot-pro' ); ?>">
            <button type="button" class="button sbp-analyze-btn"
                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                    style="margin-top:6px;">
                <?php esc_html_e( 'Analyze', 'seo-bot-pro' ); ?>
            </button>

            <div id="sbp-analysis-result" style="margin-top:10px;"></div>
            <div id="sbp-action-result" style="margin-top:10px;"></div>
        </div>
        <?php
    }
}
