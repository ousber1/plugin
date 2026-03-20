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
$temperature  = $settings['temperature'] ?? 0.4;
$max_tokens   = $settings['max_tokens'] ?? 1024;
$seo_plugin   = $settings['seo_plugin'] ?? 'rank_math';
$enable_og    = $settings['enable_og'] ?? '1';
$auto_publish   = $settings['auto_optimize_publish'] ?? '0';
$enable_twitter = $settings['enable_twitter'] ?? '0';

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

            <tr class="sbp-openai-row" <?php echo $provider === 'claude' ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label for="sbp-openai-key"><?php esc_html_e( 'OpenAI API Key', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="password" id="sbp-openai-key" name="sbp[openai_api_key]"
                           value="<?php echo esc_attr( $openai_key ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description"><?php esc_html_e( 'Get your key from platform.openai.com', 'seo-bot-pro' ); ?></p>
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
