<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 50;
$offset   = ( $paged - 1 ) * $per_page;
$total    = SBP_Logger::count();
$logs     = SBP_Logger::get_logs( $per_page, $offset );
$pages    = (int) ceil( $total / $per_page );
?>

<div class="wrap sbp-wrap">
    <h1><?php esc_html_e( 'Optimization Logs', 'seo-bot-pro' ); ?></h1>

    <?php if ( empty( $logs ) ) : ?>
        <p><?php esc_html_e( 'No logs recorded yet.', 'seo-bot-pro' ); ?></p>
    <?php else : ?>
        <table class="widefat striped sbp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Post', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Details', 'seo-bot-pro' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'seo-bot-pro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log->id ); ?></td>
                        <td>
                            <?php
                            $title = get_the_title( $log->post_id );
                            if ( $title ) {
                                echo '<a href="' . esc_url( get_edit_post_link( $log->post_id ) ) . '">'
                                   . esc_html( $title ) . '</a>';
                            } else {
                                echo '#' . esc_html( $log->post_id );
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $log->action_type ); ?></td>
                        <td>
                            <span class="sbp-status sbp-status-<?php echo esc_attr( $log->status ); ?>">
                                <?php echo esc_html( ucfirst( $log->status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <code style="font-size:11px;max-width:300px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?php echo esc_html( mb_substr( $log->details, 0, 120 ) ); ?>
                            </code>
                        </td>
                        <td><?php echo esc_html( $log->created_at ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( [
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $pages,
                    ] );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
