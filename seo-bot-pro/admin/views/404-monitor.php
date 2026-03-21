<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$monitor   = new SBP_404_Monitor();
$logs      = $monitor->get_logs( 50 );
$redirects = $monitor->get_redirects();
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'SEO Bot Pro – 404 Monitor & Redirects', 'seo-bot-pro' ); ?></h1>

    <!-- Add Redirect Form -->
    <h2 class="sbp-section-title"><?php esc_html_e( 'Add Redirect', 'seo-bot-pro' ); ?></h2>
    <form method="post" action="" style="margin-bottom:20px;">
        <?php wp_nonce_field( 'sbp_add_redirect', 'sbp_redirect_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sbp-redirect-from"><?php esc_html_e( 'From URL', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sbp-redirect-from" name="redirect_from"
                           class="regular-text" placeholder="/old-page" required>
                    <p class="description"><?php esc_html_e( 'Relative path (e.g., /old-page)', 'seo-bot-pro' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sbp-redirect-to"><?php esc_html_e( 'To URL', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <input type="url" id="sbp-redirect-to" name="redirect_to"
                           class="regular-text" placeholder="<?php echo esc_attr( home_url( '/new-page' ) ); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sbp-redirect-code"><?php esc_html_e( 'Redirect Type', 'seo-bot-pro' ); ?></label>
                </th>
                <td>
                    <select id="sbp-redirect-code" name="redirect_code">
                        <option value="301"><?php esc_html_e( '301 – Permanent', 'seo-bot-pro' ); ?></option>
                        <option value="302"><?php esc_html_e( '302 – Temporary', 'seo-bot-pro' ); ?></option>
                        <option value="307"><?php esc_html_e( '307 – Temporary (strict)', 'seo-bot-pro' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Redirect', 'seo-bot-pro' ), 'primary', 'sbp_add_redirect' ); ?>
    </form>

    <!-- Existing Redirects -->
    <?php if ( ! empty( $redirects ) ) : ?>
        <h2 class="sbp-section-title"><?php esc_html_e( 'Active Redirects', 'seo-bot-pro' ); ?></h2>
        <table class="widefat striped sbp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'From', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'To', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'seo-bot-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $redirects as $i => $r ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $r['from'] ); ?></code></td>
                        <td><a href="<?php echo esc_url( $r['to'] ); ?>" target="_blank"><?php echo esc_html( $r['to'] ); ?></a></td>
                        <td><?php echo esc_html( $r['code'] ); ?></td>
                        <td><?php echo esc_html( $r['created'] ?? '—' ); ?></td>
                        <td>
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field( 'sbp_delete_redirect', 'sbp_redirect_nonce' ); ?>
                                <input type="hidden" name="redirect_index" value="<?php echo esc_attr( $i ); ?>">
                                <button type="submit" name="sbp_delete_redirect" class="button button-small"
                                        onclick="return confirm('Delete this redirect?');">
                                    <?php esc_html_e( 'Delete', 'seo-bot-pro' ); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- 404 Logs -->
    <h2 class="sbp-section-title"><?php esc_html_e( '404 Errors Log', 'seo-bot-pro' ); ?></h2>

    <?php if ( SBP_Helpers::get_option( 'enable_404_monitor', '0' ) !== '1' ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    esc_html__( '404 monitoring is disabled. %s to enable it.', 'seo-bot-pro' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=sbp-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'seo-bot-pro' ) . '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $logs ) ) : ?>
        <p><?php esc_html_e( 'No 404 errors logged yet.', 'seo-bot-pro' ); ?></p>
    <?php else : ?>
        <table class="widefat striped sbp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'URL', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Hits', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Last Hit', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Referrer', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'seo-bot-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $log['url'] ); ?></code></td>
                        <td>
                            <span class="<?php echo $log['hits'] >= 10 ? 'sbp-num-warning' : ''; ?>">
                                <?php echo esc_html( $log['hits'] ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $log['last_hit'] ); ?></td>
                        <td>
                            <?php
                            if ( ! empty( $log['referrers'] ) ) {
                                echo esc_html( implode( ', ', array_slice( $log['referrers'], 0, 2 ) ) );
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="#" class="sbp-create-redirect-link button button-small"
                               data-from="<?php echo esc_attr( $log['url'] ); ?>">
                                <?php esc_html_e( 'Create Redirect', 'seo-bot-pro' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="" style="margin-top:10px;">
            <?php wp_nonce_field( 'sbp_clear_404', 'sbp_404_nonce' ); ?>
            <button type="submit" name="sbp_clear_404" class="button"
                    onclick="return confirm('Clear all 404 logs?');">
                <?php esc_html_e( 'Clear All Logs', 'seo-bot-pro' ); ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    $(document).on('click', '.sbp-create-redirect-link', function(e) {
        e.preventDefault();
        var from = $(this).data('from');
        $('#sbp-redirect-from').val(from);
        $('html, body').animate({ scrollTop: 0 }, 300);
        $('#sbp-redirect-to').focus();
    });
});
</script>
