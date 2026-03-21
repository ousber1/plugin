<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ai           = new SBP_AI_Service();
$configured   = $ai->is_configured();
$provider     = SBP_Helpers::get_option( 'provider', 'openai' );
$provider_labels = [ 'openai' => 'OpenAI', 'claude' => 'Claude (Anthropic)', 'gemini' => 'Google Gemini' ];
$provider_lbl = $provider_labels[ $provider ] ?? 'OpenAI';
$model        = SBP_Helpers::get_option( 'model', 'gpt-4o-mini' );
$seo_plugin   = SBP_Helpers::get_option( 'seo_plugin', 'rank_math' );

// Stats
$total_posts = wp_count_posts( 'post' );
$total_pages = wp_count_posts( 'page' );
$total_products = class_exists( 'WooCommerce' ) ? wp_count_posts( 'product' ) : null;

global $wpdb;
$table          = $wpdb->prefix . 'sbp_logs';
$table_exists   = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
$optimized      = $table_exists ? (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$table} WHERE status = 'success'" ) : 0;
$recent_logs    = $table_exists ? SBP_Logger::get_logs( 10 ) : [];

$seo_labels = SBP_Helpers::seo_plugin_labels();

// Feature statuses
$sitemap_enabled   = SBP_Helpers::get_option( 'enable_sitemap', '0' ) === '1';
$indexnow_enabled  = SBP_Helpers::get_option( 'enable_indexnow', '0' ) === '1';
$auto_ping         = SBP_Helpers::get_option( 'auto_ping_publish', '0' ) === '1';
$breadcrumbs_on    = SBP_Helpers::get_option( 'enable_breadcrumbs', '0' ) === '1';
$monitor_404_on    = SBP_Helpers::get_option( 'enable_404_monitor', '0' ) === '1';
$freshness_on      = SBP_Helpers::get_option( 'enable_freshness', '0' ) === '1';
$auto_opt_publish  = SBP_Helpers::get_option( 'auto_optimize_publish', '0' ) === '1';
$og_enabled        = SBP_Helpers::get_option( 'enable_og', '1' ) === '1';
$twitter_enabled   = SBP_Helpers::get_option( 'enable_twitter', '0' ) === '1';
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'SEO Bot Pro – Dashboard', 'seo-bot-pro' ); ?></h1>

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

    <div class="sbp-cards">
        <div class="sbp-card">
            <h3><?php esc_html_e( 'AI Provider', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-badge sbp-badge-<?php echo esc_attr( $provider ); ?>">
                <?php echo esc_html( $provider_lbl ); ?>
            </span>
            <br><small style="color:#646970;"><?php echo esc_html( $model ); ?></small>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'SEO Plugin', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number" style="font-size:14px;">
                <?php echo esc_html( $seo_labels[ $seo_plugin ] ?? $seo_plugin ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Published Posts', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number"><?php echo esc_html( $total_posts->publish ?? 0 ); ?></span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Published Pages', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number"><?php echo esc_html( $total_pages->publish ?? 0 ); ?></span>
        </div>
        <?php if ( $total_products ) : ?>
            <div class="sbp-card">
                <h3><?php esc_html_e( 'Products', 'seo-bot-pro' ); ?></h3>
                <span class="sbp-card-number"><?php echo esc_html( $total_products->publish ?? 0 ); ?></span>
            </div>
        <?php endif; ?>
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

    <!-- Feature Status -->
    <h2 class="sbp-section-title"><?php esc_html_e( 'Feature Status', 'seo-bot-pro' ); ?></h2>
    <div class="sbp-cards">
        <div class="sbp-card">
            <h3><?php esc_html_e( 'XML Sitemap', 'seo-bot-pro' ); ?></h3>
            <?php if ( $sitemap_enabled ) : ?>
                <span class="sbp-text-success"><?php esc_html_e( 'Active', 'seo-bot-pro' ); ?></span>
                <small><a href="<?php echo esc_url( home_url( '/sbp-sitemap.xml' ) ); ?>" target="_blank"><?php esc_html_e( 'View Sitemap', 'seo-bot-pro' ); ?></a></small>
            <?php else : ?>
                <span class="sbp-text-danger"><?php esc_html_e( 'Disabled', 'seo-bot-pro' ); ?></span>
            <?php endif; ?>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'IndexNow', 'seo-bot-pro' ); ?></h3>
            <span class="<?php echo $indexnow_enabled ? 'sbp-text-success' : 'sbp-text-danger'; ?>">
                <?php echo $indexnow_enabled ? esc_html__( 'Active', 'seo-bot-pro' ) : esc_html__( 'Disabled', 'seo-bot-pro' ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Auto-Ping', 'seo-bot-pro' ); ?></h3>
            <span class="<?php echo $auto_ping ? 'sbp-text-success' : 'sbp-text-danger'; ?>">
                <?php echo $auto_ping ? esc_html__( 'Active', 'seo-bot-pro' ) : esc_html__( 'Disabled', 'seo-bot-pro' ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Auto-Optimize', 'seo-bot-pro' ); ?></h3>
            <span class="<?php echo $auto_opt_publish ? 'sbp-text-success' : 'sbp-text-danger'; ?>">
                <?php echo $auto_opt_publish ? esc_html__( 'Active', 'seo-bot-pro' ) : esc_html__( 'Disabled', 'seo-bot-pro' ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Open Graph', 'seo-bot-pro' ); ?></h3>
            <span class="<?php echo $og_enabled ? 'sbp-text-success' : 'sbp-text-danger'; ?>">
                <?php echo $og_enabled ? esc_html__( 'Active', 'seo-bot-pro' ) : esc_html__( 'Disabled', 'seo-bot-pro' ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Twitter Cards', 'seo-bot-pro' ); ?></h3>
            <span class="<?php echo $twitter_enabled ? 'sbp-text-success' : 'sbp-text-danger'; ?>">
                <?php echo $twitter_enabled ? esc_html__( 'Active', 'seo-bot-pro' ) : esc_html__( 'Disabled', 'seo-bot-pro' ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( '404 Monitor', 'seo-bot-pro' ); ?></h3>
            <span class="<?php echo $monitor_404_on ? 'sbp-text-success' : 'sbp-text-danger'; ?>">
                <?php echo $monitor_404_on ? esc_html__( 'Active', 'seo-bot-pro' ) : esc_html__( 'Disabled', 'seo-bot-pro' ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Breadcrumbs', 'seo-bot-pro' ); ?></h3>
            <span class="<?php echo $breadcrumbs_on ? 'sbp-text-success' : 'sbp-text-danger'; ?>">
                <?php echo $breadcrumbs_on ? esc_html__( 'Active', 'seo-bot-pro' ) : esc_html__( 'Disabled', 'seo-bot-pro' ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Freshness', 'seo-bot-pro' ); ?></h3>
            <span class="<?php echo $freshness_on ? 'sbp-text-success' : 'sbp-text-danger'; ?>">
                <?php echo $freshness_on ? esc_html__( 'Active', 'seo-bot-pro' ) : esc_html__( 'Disabled', 'seo-bot-pro' ); ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Image Provider', 'seo-bot-pro' ); ?></h3>
            <?php
            $img_prov = SBP_Helpers::get_option( 'image_provider', 'dalle' );
            $img_labels = [ 'dalle' => 'DALL-E', 'unsplash' => 'Unsplash', 'pixabay' => 'Pixabay', 'pexels' => 'Pexels' ];
            ?>
            <span class="sbp-card-number" style="font-size:14px;">
                <?php echo esc_html( $img_labels[ $img_prov ] ?? $img_prov ); ?>
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
