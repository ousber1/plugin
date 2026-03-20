<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings     = get_option( 'sbp_settings', [] );
$provider     = $settings['provider'] ?? 'openai';
$openai_key   = $settings['openai_api_key'] ?? '';
$claude_key   = $settings['claude_api_key'] ?? '';
$model        = $settings['model'] ?? ( $provider === 'claude' ? 'claude-sonnet-4-6' : 'gpt-4o-mini' );
$language     = $settings['language'] ?? 'en';
$tone         = $settings['tone'] ?? 'professional';
$image_provider   = $settings['image_provider'] ?? 'dalle';
$unsplash_key     = $settings['unsplash_api_key'] ?? '';
$pixabay_key      = $settings['pixabay_api_key'] ?? '';
$pexels_key       = $settings['pexels_api_key'] ?? '';
$temperature  = $settings['temperature'] ?? 0.4;
$max_tokens   = $settings['max_tokens'] ?? 1024;
$seo_plugin   = $settings['seo_plugin'] ?? 'rank_math';
$enable_og    = $settings['enable_og'] ?? '1';
$auto_publish     = $settings['auto_optimize_publish'] ?? '0';
$enable_twitter   = $settings['enable_twitter'] ?? '0';
$enable_sitemap   = $settings['enable_sitemap'] ?? '0';
$auto_ping        = $settings['auto_ping_publish'] ?? '0';
$ping_google      = $settings['ping_google'] ?? '1';
$ping_bing        = $settings['ping_bing'] ?? '1';
$enable_indexnow  = $settings['enable_indexnow'] ?? '0';
$indexnow_key     = $settings['indexnow_api_key'] ?? '';
$enable_freshness = $settings['enable_freshness'] ?? '0';
$freshness_days   = $settings['freshness_days'] ?? 90;
$google_verify    = $settings['google_verification'] ?? '';
$bing_verify      = $settings['bing_verification'] ?? '';
$enable_404       = $settings['enable_404_monitor'] ?? '0';
$enable_breadcrumbs = $settings['enable_breadcrumbs'] ?? '0';

settings_errors( 'sbp_settings' );
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'SEO Bot Pro – Settings', 'seo-bot-pro' ); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field( 'sbp_settings_save', 'sbp_settings_nonce' ); ?>

        <!-- ── AI Provider ──────────────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'AI Provider', 'seo-bot-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sbp-provider"><?php esc_html_e( 'Provider', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <select id="sbp-provider" name="sbp[provider]">
                        <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
                        <option value="claude" <?php selected( $provider, 'claude' ); ?>>Claude (Anthropic)</option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Choose your AI provider. Model options update automatically.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>

            <tr class="sbp-openai-row">
                <th scope="row">
                    <label for="sbp-openai-key"><?php esc_html_e( 'OpenAI API Key', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="password" id="sbp-openai-key" name="sbp[openai_api_key]"
                           value="<?php echo esc_attr( $openai_key ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description">
                        <?php if ( $provider === 'claude' ) : ?>
                            <?php esc_html_e( 'Optional but required for AI image generation (DALL-E 3). Get your key from platform.openai.com', 'seo-bot-pro' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Required for text generation + image generation (DALL-E 3). Get your key from platform.openai.com', 'seo-bot-pro' ); ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>

            <tr class="sbp-claude-row" <?php echo $provider === 'openai' ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label for="sbp-claude-key"><?php esc_html_e( 'Claude API Key', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="password" id="sbp-claude-key" name="sbp[claude_api_key]"
                           value="<?php echo esc_attr( $claude_key ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description"><?php esc_html_e( 'Get your key from console.anthropic.com', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sbp-model"><?php esc_html_e( 'AI Model', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <select id="sbp-model" name="sbp[model]">
                        <!-- OpenAI models -->
                        <?php foreach ( SBP_Helpers::openai_models() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"
                                    class="sbp-model-openai"
                                    <?php echo $provider === 'claude' ? 'style="display:none;"' : ''; ?>
                                    <?php selected( $model, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                        <!-- Claude models -->
                        <?php foreach ( SBP_Helpers::claude_models() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"
                                    class="sbp-model-claude"
                                    <?php echo $provider === 'openai' ? 'style="display:none;"' : ''; ?>
                                    <?php selected( $model, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <!-- ── Image Generation ────────────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'Image Generation (Featured Images)', 'seo-bot-pro' ); ?></h2>
        <p class="description" style="margin-bottom:10px;">
            <?php esc_html_e( 'Configure the image provider for auto-generating featured images when creating posts with the AI Post Generator.', 'seo-bot-pro' ); ?>
        </p>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sbp-image-provider"><?php esc_html_e( 'Image Provider', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <select id="sbp-image-provider" name="sbp[image_provider]">
                        <option value="dalle" <?php selected( $image_provider, 'dalle' ); ?>>
                            <?php esc_html_e( 'DALL-E 3 (OpenAI) — AI-generated unique images (paid, requires OpenAI key)', 'seo-bot-pro' ); ?>
                        </option>
                        <option value="unsplash" <?php selected( $image_provider, 'unsplash' ); ?>>
                            <?php esc_html_e( 'Unsplash — Free high-quality stock photos', 'seo-bot-pro' ); ?>
                        </option>
                        <option value="pixabay" <?php selected( $image_provider, 'pixabay' ); ?>>
                            <?php esc_html_e( 'Pixabay — Free stock photos (no attribution required)', 'seo-bot-pro' ); ?>
                        </option>
                        <option value="pexels" <?php selected( $image_provider, 'pexels' ); ?>>
                            <?php esc_html_e( 'Pexels — Free stock photos', 'seo-bot-pro' ); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr class="sbp-img-dalle-row" <?php echo $image_provider !== 'dalle' ? 'style="display:none;"' : ''; ?>>
                <th scope="row"><?php esc_html_e( 'DALL-E Setup', 'seo-bot-pro' ); ?></th>
                <td>
                    <p class="description">
                        <?php esc_html_e( 'DALL-E uses your OpenAI API Key (configured above). Make sure you have billing enabled on your OpenAI account.', 'seo-bot-pro' ); ?>
                        <br><strong><?php esc_html_e( 'Cost: ~$0.04 per image (1792x1024)', 'seo-bot-pro' ); ?></strong>
                    </p>
                </td>
            </tr>

            <tr class="sbp-img-unsplash-row" <?php echo $image_provider !== 'unsplash' ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label for="sbp-unsplash-key"><?php esc_html_e( 'Unsplash API Key', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sbp-unsplash-key" name="sbp[unsplash_api_key]"
                           value="<?php echo esc_attr( $unsplash_key ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'Your Unsplash Access Key', 'seo-bot-pro' ); ?>">
                    <p class="description">
                        <?php
                        printf(
                            esc_html__( 'Free: 50 requests/hour. %1$sGet your key here%2$s → Create App → Copy "Access Key"', 'seo-bot-pro' ),
                            '<a href="https://unsplash.com/developers" target="_blank" rel="noopener">',
                            '</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr class="sbp-img-pixabay-row" <?php echo $image_provider !== 'pixabay' ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label for="sbp-pixabay-key"><?php esc_html_e( 'Pixabay API Key', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sbp-pixabay-key" name="sbp[pixabay_api_key]"
                           value="<?php echo esc_attr( $pixabay_key ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'Your Pixabay API Key', 'seo-bot-pro' ); ?>">
                    <p class="description">
                        <?php
                        printf(
                            esc_html__( 'Free: 100 requests/minute. %1$sGet your key here%2$s → Sign up → API docs → Your API Key', 'seo-bot-pro' ),
                            '<a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener">',
                            '</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr class="sbp-img-pexels-row" <?php echo $image_provider !== 'pexels' ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label for="sbp-pexels-key"><?php esc_html_e( 'Pexels API Key', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sbp-pexels-key" name="sbp[pexels_api_key]"
                           value="<?php echo esc_attr( $pexels_key ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'Your Pexels API Key', 'seo-bot-pro' ); ?>">
                    <p class="description">
                        <?php
                        printf(
                            esc_html__( 'Free: 200 requests/hour. %1$sGet your key here%2$s → Sign up → Image & Video API', 'seo-bot-pro' ),
                            '<a href="https://www.pexels.com/api/" target="_blank" rel="noopener">',
                            '</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
        </table>

        <!-- ── SEO Settings ─────────────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'SEO Settings', 'seo-bot-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sbp-seo-plugin"><?php esc_html_e( 'SEO Plugin Integration', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <select id="sbp-seo-plugin" name="sbp[seo_plugin]">
                        <?php foreach ( SBP_Helpers::seo_plugin_labels() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"
                                <?php selected( $seo_plugin, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Choose which SEO plugin fields to update when optimizing.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Open Graph Tags', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[enable_og]" value="1"
                            <?php checked( $enable_og, '1' ); ?>>
                        <?php esc_html_e( 'Generate Open Graph (og:title, og:description) during optimization', 'seo-bot-pro' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Twitter Cards', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[enable_twitter]" value="1"
                            <?php checked( $enable_twitter, '1' ); ?>>
                        <?php esc_html_e( 'Generate Twitter Card meta tags (twitter:title, twitter:description) during optimization', 'seo-bot-pro' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Auto-Optimize on Publish', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[auto_optimize_publish]" value="1"
                            <?php checked( $auto_publish, '1' ); ?>>
                        <?php esc_html_e( 'Automatically optimize SEO when a post is published', 'seo-bot-pro' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <!-- ── Indexing & Crawling ────────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'Indexing & Crawling', 'seo-bot-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'XML Sitemap', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[enable_sitemap]" value="1"
                            <?php checked( $enable_sitemap, '1' ); ?>>
                        <?php esc_html_e( 'Generate XML sitemap at /sbp-sitemap.xml', 'seo-bot-pro' ); ?>
                    </label>
                    <?php if ( $enable_sitemap === '1' ) : ?>
                        <p class="description">
                            <?php esc_html_e( 'Sitemap URL:', 'seo-bot-pro' ); ?>
                            <code><?php echo esc_html( home_url( '/sbp-sitemap.xml' ) ); ?></code>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Auto-Ping on Publish', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[auto_ping_publish]" value="1"
                            <?php checked( $auto_ping, '1' ); ?>>
                        <?php esc_html_e( 'Automatically notify search engines when content is published or updated', 'seo-bot-pro' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Ping Google', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[ping_google]" value="1"
                            <?php checked( $ping_google, '1' ); ?>>
                        <?php esc_html_e( 'Ping Google when content changes', 'seo-bot-pro' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Ping Bing', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[ping_bing]" value="1"
                            <?php checked( $ping_bing, '1' ); ?>>
                        <?php esc_html_e( 'Ping Bing when content changes', 'seo-bot-pro' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'IndexNow', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[enable_indexnow]" value="1"
                            <?php checked( $enable_indexnow, '1' ); ?>>
                        <?php esc_html_e( 'Enable IndexNow instant indexing (Bing, Yandex, DuckDuckGo, Naver)', 'seo-bot-pro' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'IndexNow sends URLs directly to participating search engines for near-instant crawling.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sbp-indexnow-key"><?php esc_html_e( 'IndexNow API Key', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sbp-indexnow-key" name="sbp[indexnow_api_key]"
                           value="<?php echo esc_attr( $indexnow_key ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'e.g. a1b2c3d4e5f6...', 'seo-bot-pro' ); ?>">
                    <p class="description">
                        <?php esc_html_e( 'Generate a key at indexnow.org. The plugin auto-serves the verification file.', 'seo-bot-pro' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <!-- ── Webmaster Verification ──────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'Webmaster Verification', 'seo-bot-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sbp-google-verify"><?php esc_html_e( 'Google Search Console', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sbp-google-verify" name="sbp[google_verification]"
                           value="<?php echo esc_attr( $google_verify ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'Verification code (content value only)', 'seo-bot-pro' ); ?>">
                    <p class="description"><?php esc_html_e( 'Enter the content value from the Google meta tag verification.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sbp-bing-verify"><?php esc_html_e( 'Bing Webmaster Tools', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sbp-bing-verify" name="sbp[bing_verification]"
                           value="<?php echo esc_attr( $bing_verify ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'Verification code (content value only)', 'seo-bot-pro' ); ?>">
                    <p class="description"><?php esc_html_e( 'Enter the content value from the Bing meta tag verification.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>
        </table>

        <!-- ── Rank Booster ──────────────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'Rank Booster', 'seo-bot-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Content Freshness', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[enable_freshness]" value="1"
                            <?php checked( $enable_freshness, '1' ); ?>>
                        <?php esc_html_e( 'Automatically refresh modified dates on stale content (weekly CRON)', 'seo-bot-pro' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Refreshing modified dates signals freshness to search engines and can boost rankings.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sbp-freshness-days"><?php esc_html_e( 'Freshness Threshold', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="number" id="sbp-freshness-days" name="sbp[freshness_days]"
                           value="<?php echo esc_attr( $freshness_days ); ?>"
                           min="7" max="365" step="1" style="width:80px;">
                    <span><?php esc_html_e( 'days', 'seo-bot-pro' ); ?></span>
                    <p class="description"><?php esc_html_e( 'Content older than this many days is considered stale. (7-365)', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>
        </table>

        <!-- ── Additional Features ─────────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'Additional Features', 'seo-bot-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( '404 Monitor', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[enable_404_monitor]" value="1"
                            <?php checked( $enable_404, '1' ); ?>>
                        <?php esc_html_e( 'Monitor 404 errors and log broken URLs for redirect management', 'seo-bot-pro' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Tracks 404 hits so you can create redirects for broken links.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Breadcrumbs', 'seo-bot-pro' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sbp[enable_breadcrumbs]" value="1"
                            <?php checked( $enable_breadcrumbs, '1' ); ?>>
                        <?php esc_html_e( 'Enable JSON-LD BreadcrumbList schema on all pages', 'seo-bot-pro' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Outputs BreadcrumbList structured data for rich snippets in search results.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>
        </table>

        <!-- ── Content Settings ─────────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'Content Settings', 'seo-bot-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sbp-language"><?php esc_html_e( 'Language', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <select id="sbp-language" name="sbp[language]">
                        <?php foreach ( SBP_Helpers::language_labels() as $code => $label ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>"
                                <?php selected( $language, $code ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sbp-tone"><?php esc_html_e( 'Tone', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <select id="sbp-tone" name="sbp[tone]">
                        <?php foreach ( SBP_Helpers::tone_labels() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"
                                <?php selected( $tone, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <!-- ── Advanced Settings ────────────────────── -->
        <h2 class="sbp-section-title"><?php esc_html_e( 'Advanced Settings', 'seo-bot-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sbp-temperature"><?php esc_html_e( 'Temperature', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="number" id="sbp-temperature" name="sbp[temperature]"
                           value="<?php echo esc_attr( $temperature ); ?>"
                           min="0" max="1" step="0.1" style="width:80px;">
                    <p class="description"><?php esc_html_e( 'AI creativity level. Lower = more focused, Higher = more creative. (0.0 - 1.0)', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sbp-max-tokens"><?php esc_html_e( 'Max Tokens', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="number" id="sbp-max-tokens" name="sbp[max_tokens]"
                           value="<?php echo esc_attr( $max_tokens ); ?>"
                           min="256" max="4096" step="128" style="width:100px;">
                    <p class="description"><?php esc_html_e( 'Maximum response length from the AI. (256 - 4096)', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save Settings', 'seo-bot-pro' ), 'primary', 'sbp_save_settings' ); ?>
    </form>
</div>
