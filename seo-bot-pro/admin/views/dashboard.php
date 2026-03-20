<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ai         = new SBP_AI_Service();
$configured = $ai->is_configured();

// Stats
$total_posts = wp_count_posts( 'post' );
$total_pages = wp_count_posts( 'page' );

global $wpdb;
$table          = $wpdb->prefix . 'sbp_logs';
$table_exists   = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
$optimized      = $table_exists ? (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$table} WHERE status = 'success'" ) : 0;
$recent_logs    = $table_exists ? SBP_Logger::get_logs( 10 ) : [];
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'SEO Bot Pro – Dashboard', 'seo-bot-pro' ); ?></h1>

    <?php if ( ! $configured ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    /* translators: %s: settings page link */
                    esc_html__( 'OpenAI API key not configured. %s to set it up.', 'seo-bot-pro' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=sbp-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'seo-bot-pro' ) . '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="sbp-cards">
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Published Posts', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number"><?php echo esc_html( $total_posts->publish ?? 0 ); ?></span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Published Pages', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number"><?php echo esc_html( $total_pages->publish ?? 0 ); ?></span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Optimized', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number"><?php echo esc_html( $optimized ); ?></span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Next CRON Run', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number" style="font-size:14px;">
                <?php
                $next = wp_next_scheduled( 'sbp_daily_optimization' );
                echo $next
                    ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) )
                    : esc_html__( 'Not scheduled', 'seo-bot-pro' );
                ?>
            </span>
        </div>
    </div>

    <h2><?php esc_html_e( 'Recent Activity', 'seo-bot-pro' ); ?></h2>

    <?php if ( empty( $recent_logs ) ) : ?>
        <p><?php esc_html_e( 'No activity yet.', 'seo-bot-pro' ); ?></p>
    <?php else : ?>
        <table class="widefat striped sbp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Post', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'seo-bot-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $recent_logs as $log ) : ?>
                    <tr>
                        <td>
                            <?php
                            $title = get_the_title( $log->post_id );
                            echo $title ? esc_html( $title ) : '#' . esc_html( $log->post_id );
                            ?>
                        </td>
                        <td><?php echo esc_html( $log->action_type ); ?></td>
                        <td>
                            <span class="sbp-status sbp-status-<?php echo esc_attr( $log->status ); ?>">
                                <?php echo esc_html( ucfirst( $log->status ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $log->created_at ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
