<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ai         = new SBP_AI_Service();
$configured = $ai->is_configured();
$categories = get_categories( [ 'hide_empty' => false ] );
$provider   = SBP_Helpers::get_option( 'provider', 'openai' );
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'AI Post Generator', 'seo-bot-pro' ); ?></h1>
    <p><?php esc_html_e( 'Generate complete, SEO-optimized articles using AI and publish them automatically.', 'seo-bot-pro' ); ?></p>

    <?php if ( ! $configured ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    esc_html__( 'AI API key not configured. %s to set it up.', 'seo-bot-pro' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=sbp-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'seo-bot-pro' ) . '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="sbp-generator-wrap">
        <div class="sbp-generator-form">
            <table class="form-table">
                <tr>
                    <th><label for="sbp-gen-topic"><?php esc_html_e( 'Topic / Title', 'seo-bot-pro' ); ?></label></th>
                    <td>
                        <input type="text" id="sbp-gen-topic" class="large-text"
                               placeholder="<?php esc_attr_e( 'e.g. 10 Best WordPress Plugins for SEO in 2026', 'seo-bot-pro' ); ?>">
                        <p class="description"><?php esc_html_e( 'Describe the topic or provide a working title. The AI will generate a full article.', 'seo-bot-pro' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><label for="sbp-gen-type"><?php esc_html_e( 'Post Type', 'seo-bot-pro' ); ?></label></th>
                    <td>
                        <select id="sbp-gen-type">
                            <option value="post"><?php esc_html_e( 'Post', 'seo-bot-pro' ); ?></option>
                            <option value="page"><?php esc_html_e( 'Page', 'seo-bot-pro' ); ?></option>
                            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                <option value="product"><?php esc_html_e( 'Product', 'seo-bot-pro' ); ?></option>
                            <?php endif; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="sbp-gen-status"><?php esc_html_e( 'Publish Status', 'seo-bot-pro' ); ?></label></th>
                    <td>
                        <select id="sbp-gen-status">
                            <option value="draft"><?php esc_html_e( 'Draft (review first)', 'seo-bot-pro' ); ?></option>
                            <option value="publish"><?php esc_html_e( 'Publish immediately', 'seo-bot-pro' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pending Review', 'seo-bot-pro' ); ?></option>
                        </select>
                    </td>
                </tr>

                <tr class="sbp-gen-category-row">
                    <th><label for="sbp-gen-category"><?php esc_html_e( 'Category', 'seo-bot-pro' ); ?></label></th>
                    <td>
                        <select id="sbp-gen-category">
                            <option value="0"><?php esc_html_e( '— None —', 'seo-bot-pro' ); ?></option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                    <?php echo esc_html( $cat->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="sbp-gen-length"><?php esc_html_e( 'Article Length', 'seo-bot-pro' ); ?></label></th>
                    <td>
                        <select id="sbp-gen-length">
                            <option value="short"><?php esc_html_e( 'Short (300-500 words)', 'seo-bot-pro' ); ?></option>
                            <option value="medium" selected><?php esc_html_e( 'Medium (800-1200 words)', 'seo-bot-pro' ); ?></option>
                            <option value="long"><?php esc_html_e( 'Long (1500-2500 words)', 'seo-bot-pro' ); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="sbp-gen-instructions"><?php esc_html_e( 'Custom Instructions', 'seo-bot-pro' ); ?></label></th>
                    <td>
                        <textarea id="sbp-gen-instructions" rows="3" class="large-text"
                                  placeholder="<?php esc_attr_e( 'Optional: Add specific instructions, target audience, style notes...', 'seo-bot-pro' ); ?>"></textarea>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( 'Options', 'seo-bot-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="sbp-gen-autoseo" checked>
                            <?php esc_html_e( 'Auto-optimize SEO (meta title, description, keywords, OG tags)', 'seo-bot-pro' ); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" id="sbp-gen-autofaq">
                            <?php esc_html_e( 'Auto-generate FAQ section with schema', 'seo-bot-pro' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" class="button button-primary button-hero" id="sbp-generate-post-btn">
                    <?php esc_html_e( 'Generate Article with AI', 'seo-bot-pro' ); ?>
                </button>
            </p>

            <div id="sbp-gen-result" style="margin-top:20px;"></div>
        </div>
    </div>
</div>
