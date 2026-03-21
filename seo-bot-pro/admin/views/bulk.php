<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_types = SBP_Helpers::post_types();
$paged      = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page   = 30;

$query = new WP_Query( [
    'post_type'      => $post_types,
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
] );
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'Bulk Optimize', 'seo-bot-pro' ); ?></h1>

    <div style="margin-bottom:15px;">
        <button type="button" class="button button-primary" id="sbp-bulk-optimize-btn">
            <?php esc_html_e( 'Bulk Optimize Selected', 'seo-bot-pro' ); ?>
        </button>
        <span id="sbp-bulk-progress" style="margin-left:12px;"></span>
    </div>

    <table class="widefat striped sbp-table">
        <thead>
            <tr>
                <th style="width:30px;"><input type="checkbox" id="sbp-select-all"></th>
                <th><?php esc_html_e( 'Title', 'seo-bot-pro' ); ?></th>
                <th><?php esc_html_e( 'Type', 'seo-bot-pro' ); ?></th>
                <th><?php esc_html_e( 'Meta Title', 'seo-bot-pro' ); ?></th>
                <th><?php esc_html_e( 'Meta Desc', 'seo-bot-pro' ); ?></th>
                <th><?php esc_html_e( 'Status', 'seo-bot-pro' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $query->have_posts() ) : ?>
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php
                    $pid        = get_the_ID();
                    $meta_title = get_post_meta( $pid, '_sbp_meta_title', true );
                    $meta_desc  = get_post_meta( $pid, '_sbp_meta_description', true );
                    ?>
                    <tr data-post-id="<?php echo esc_attr( $pid ); ?>">
                        <td><input type="checkbox" class="sbp-post-check" value="<?php echo esc_attr( $pid ); ?>"></td>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
                                <?php echo esc_html( get_the_title() ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( get_post_type() ); ?></td>
                        <td class="sbp-cell-meta-title"><?php echo esc_html( $meta_title ?: '—' ); ?></td>
                        <td class="sbp-cell-meta-desc"><?php echo esc_html( mb_substr( $meta_desc, 0, 60 ) ?: '—' ); ?></td>
                        <td class="sbp-cell-status">—</td>
                    </tr>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <tr><td colspan="6"><?php esc_html_e( 'No posts found.', 'seo-bot-pro' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    $total_pages = $query->max_num_pages;
    if ( $total_pages > 1 ) :
    ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( [
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $paged,
                    'total'   => $total_pages,
                ] );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
