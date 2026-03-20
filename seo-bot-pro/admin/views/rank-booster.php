<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$booster = new SBP_Rank_Booster();
$stats   = $booster->get_stats();

$sitemap_enabled  = SBP_Helpers::get_option( 'enable_sitemap', '0' ) === '1';
$indexnow_enabled = SBP_Helpers::get_option( 'enable_indexnow', '0' ) === '1';
$auto_ping        = SBP_Helpers::get_option( 'auto_ping_publish', '0' ) === '1';
$freshness        = SBP_Helpers::get_option( 'enable_freshness', '0' ) === '1';
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'Rank Booster', 'seo-bot-pro' ); ?></h1>
    <p><?php esc_html_e( 'Tools and insights to speed up your search engine rankings and improve crawl efficiency.', 'seo-bot-pro' ); ?></p>

    <!-- ── Quick Stats ──────────────────────────────── -->
    <div class="sbp-cards">
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Stale Content', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number <?php echo $stats['stale_count'] > 10 ? 'sbp-num-warning' : ''; ?>">
                <?php echo esc_html( $stats['stale_count'] ); ?>
            </span>
            <small><?php printf( esc_html__( 'Older than %d days', 'seo-bot-pro' ), $stats['freshness_days'] ); ?></small>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Orphan Pages', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number <?php echo $stats['orphan_count'] > 5 ? 'sbp-num-warning' : ''; ?>">
                <?php echo esc_html( $stats['orphan_count'] ); ?>
            </span>
            <small><?php esc_html_e( 'No internal links', 'seo-bot-pro' ); ?></small>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Thin Content', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number <?php echo $stats['thin_count'] > 5 ? 'sbp-num-warning' : ''; ?>">
                <?php echo esc_html( $stats['thin_count'] ); ?>
            </span>
            <small><?php esc_html_e( 'Under 300 words', 'seo-bot-pro' ); ?></small>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Keyword Cannibalization', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number <?php echo $stats['cannibalized_count'] > 0 ? 'sbp-num-warning' : ''; ?>">
                <?php echo esc_html( $stats['cannibalized_count'] ); ?>
            </span>
            <small><?php esc_html_e( 'Duplicate targets', 'seo-bot-pro' ); ?></small>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Sitemap', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number" style="font-size:14px;">
                <?php if ( $sitemap_enabled ) : ?>
                    <span class="sbp-status sbp-status-success"><?php esc_html_e( 'Active', 'seo-bot-pro' ); ?></span>
                <?php else : ?>
                    <span class="sbp-status sbp-status-error"><?php esc_html_e( 'Disabled', 'seo-bot-pro' ); ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="sbp-card">
            <h3><?php esc_html_e( 'Auto-Index', 'seo-bot-pro' ); ?></h3>
            <span class="sbp-card-number" style="font-size:14px;">
                <?php if ( $auto_ping ) : ?>
                    <span class="sbp-status sbp-status-success"><?php esc_html_e( 'Active', 'seo-bot-pro' ); ?></span>
                <?php else : ?>
                    <span class="sbp-status sbp-status-error"><?php esc_html_e( 'Disabled', 'seo-bot-pro' ); ?></span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- ── Quick Actions ────────────────────────────── -->
    <h2 class="sbp-section-title"><?php esc_html_e( 'Quick Actions', 'seo-bot-pro' ); ?></h2>
    <div class="sbp-booster-actions">
        <div class="sbp-action-card">
            <h3><?php esc_html_e( 'Ping Search Engines Now', 'seo-bot-pro' ); ?></h3>
            <p><?php esc_html_e( 'Manually ping Google, Bing, and IndexNow to notify them of your latest content.', 'seo-bot-pro' ); ?></p>
            <button type="button" class="button button-primary" id="sbp-ping-engines-btn">
                <?php esc_html_e( 'Ping All Engines', 'seo-bot-pro' ); ?>
            </button>
            <span id="sbp-ping-result" style="margin-left:10px;"></span>
        </div>

        <div class="sbp-action-card">
            <h3><?php esc_html_e( 'Submit Sitemap', 'seo-bot-pro' ); ?></h3>
            <p>
                <?php if ( $sitemap_enabled ) : ?>
                    <?php esc_html_e( 'Submit your sitemap URL to Google and Bing for faster crawling.', 'seo-bot-pro' ); ?>
                    <br><code><?php echo esc_html( home_url( '/sbp-sitemap.xml' ) ); ?></code>
                <?php else : ?>
                    <?php esc_html_e( 'Enable the sitemap in Settings first.', 'seo-bot-pro' ); ?>
                <?php endif; ?>
            </p>
            <button type="button" class="button button-primary" id="sbp-submit-sitemap-btn" <?php disabled( ! $sitemap_enabled ); ?>>
                <?php esc_html_e( 'Submit Sitemap', 'seo-bot-pro' ); ?>
            </button>
            <span id="sbp-sitemap-result" style="margin-left:10px;"></span>
        </div>

        <div class="sbp-action-card">
            <h3><?php esc_html_e( 'Bulk Submit All URLs (IndexNow)', 'seo-bot-pro' ); ?></h3>
            <p><?php esc_html_e( 'Submit all your published URLs to IndexNow for instant indexing by Bing, Yandex, and more.', 'seo-bot-pro' ); ?></p>
            <button type="button" class="button button-primary" id="sbp-bulk-indexnow-btn" <?php disabled( ! $indexnow_enabled ); ?>>
                <?php esc_html_e( 'Submit All URLs', 'seo-bot-pro' ); ?>
            </button>
            <span id="sbp-indexnow-result" style="margin-left:10px;"></span>
        </div>

        <div class="sbp-action-card">
            <h3><?php esc_html_e( 'Refresh Stale Content Dates', 'seo-bot-pro' ); ?></h3>
            <p><?php printf( esc_html__( 'Update modified dates on posts older than %d days to signal freshness.', 'seo-bot-pro' ), $stats['freshness_days'] ); ?></p>
            <button type="button" class="button" id="sbp-refresh-stale-btn">
                <?php esc_html_e( 'Refresh Dates', 'seo-bot-pro' ); ?>
            </button>
            <span id="sbp-refresh-result" style="margin-left:10px;"></span>
        </div>
    </div>

    <!-- ── Stale Content ────────────────────────────── -->
    <h2 class="sbp-section-title"><?php esc_html_e( 'Stale Content', 'seo-bot-pro' ); ?></h2>
    <?php
    $stale = $booster->get_stale_content( $stats['freshness_days'], 20 );
    if ( empty( $stale ) ) :
    ?>
        <p><?php esc_html_e( 'No stale content found. All your posts are fresh!', 'seo-bot-pro' ); ?></p>
    <?php else : ?>
        <table class="widefat striped sbp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Title', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Last Modified', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Days Stale', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Words', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'SEO', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'seo-bot-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $stale as $item ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
                        <td><?php echo esc_html( $item['post_type'] ); ?></td>
                        <td><?php echo esc_html( $item['modified'] ); ?></td>
                        <td>
                            <span class="<?php echo $item['days_stale'] > 180 ? 'sbp-text-danger' : 'sbp-text-warning'; ?>">
                                <?php echo esc_html( $item['days_stale'] ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $item['word_count'] ); ?></td>
                        <td>
                            <?php if ( $item['has_seo'] ) : ?>
                                <span class="dashicons dashicons-yes" style="color:#28a745;"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-no" style="color:#dc3545;"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $item['edit_url'] ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'seo-bot-pro' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- ── Orphan Pages ─────────────────────────────── -->
    <h2 class="sbp-section-title"><?php esc_html_e( 'Orphan Pages', 'seo-bot-pro' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Pages with no internal links pointing to them. These are harder for search engines to discover.', 'seo-bot-pro' ); ?></p>
    <?php
    $orphans = $booster->get_orphan_pages( 20 );
    if ( empty( $orphans ) ) :
    ?>
        <p><?php esc_html_e( 'No orphan pages found. Great internal linking!', 'seo-bot-pro' ); ?></p>
    <?php else : ?>
        <table class="widefat striped sbp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Title', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'seo-bot-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $orphans as $item ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
                        <td><?php echo esc_html( $item['post_type'] ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( $item['edit_url'] ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'seo-bot-pro' ); ?></a>
                            <a href="<?php echo esc_url( $item['permalink'] ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'View', 'seo-bot-pro' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- ── Thin Content ─────────────────────────────── -->
    <h2 class="sbp-section-title"><?php esc_html_e( 'Thin Content', 'seo-bot-pro' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Posts with less than 300 words. Search engines prefer substantial, in-depth content.', 'seo-bot-pro' ); ?></p>
    <?php
    $thin = $booster->get_thin_content( 300, 20 );
    if ( empty( $thin ) ) :
    ?>
        <p><?php esc_html_e( 'No thin content found. Your content is substantial!', 'seo-bot-pro' ); ?></p>
    <?php else : ?>
        <table class="widefat striped sbp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Title', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Word Count', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'seo-bot-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $thin as $item ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
                        <td><?php echo esc_html( $item['post_type'] ); ?></td>
                        <td>
                            <span class="<?php echo $item['word_count'] < 100 ? 'sbp-text-danger' : 'sbp-text-warning'; ?>">
                                <?php echo esc_html( $item['word_count'] ); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $item['edit_url'] ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'seo-bot-pro' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- ── Keyword Cannibalization ───────────────────── -->
    <h2 class="sbp-section-title"><?php esc_html_e( 'Keyword Cannibalization', 'seo-bot-pro' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Multiple posts targeting the same focus keyword compete against each other in search results.', 'seo-bot-pro' ); ?></p>
    <?php
    $cannibalized = $booster->get_keyword_cannibalization();
    if ( empty( $cannibalized ) ) :
    ?>
        <p><?php esc_html_e( 'No keyword cannibalization detected. Each post targets a unique keyword!', 'seo-bot-pro' ); ?></p>
    <?php else : ?>
        <table class="widefat striped sbp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Keyword', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Competing Posts', 'seo-bot-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $cannibalized as $keyword => $posts ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $keyword ); ?></strong></td>
                        <td>
                            <?php foreach ( $posts as $p ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $p['ID'], 'raw' ) ); ?>">
                                    <?php echo esc_html( $p['title'] ); ?>
                                </a><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
