<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'sbp_settings', [] );
$api_key  = $settings['api_key'] ?? '';
$model    = $settings['model'] ?? 'gpt-4o-mini';
$language = $settings['language'] ?? 'en';
$tone     = $settings['tone'] ?? 'professional';

settings_errors( 'sbp_settings' );
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'SEO Bot Pro – Settings', 'seo-bot-pro' ); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field( 'sbp_settings_save', 'sbp_settings_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sbp-api-key"><?php esc_html_e( 'OpenAI API Key', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="password" id="sbp-api-key" name="sbp[api_key]"
                           value="<?php echo esc_attr( $api_key ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description"><?php esc_html_e( 'Your OpenAI API key. Stored securely in the database.', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sbp-model"><?php esc_html_e( 'AI Model', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <select id="sbp-model" name="sbp[model]">
                        <?php
                        $models = [
                            'gpt-4o-mini' => 'GPT-4o Mini (recommended)',
                            'gpt-4o'      => 'GPT-4o',
                            'gpt-4-turbo' => 'GPT-4 Turbo',
                            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                        ];
                        foreach ( $models as $value => $label ) :
                        ?>
                            <option value="<?php echo esc_attr( $value ); ?>"
                                <?php selected( $model, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

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

        <?php submit_button( __( 'Save Settings', 'seo-bot-pro' ), 'primary', 'sbp_save_settings' ); ?>
    </form>
</div>
