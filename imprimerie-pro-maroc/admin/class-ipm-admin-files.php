<?php
/**
 * Gestion admin des fichiers clients
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Admin_Files {

    /**
     * Afficher la page des fichiers
     */
    public static function render() {
        global $wpdb;
        $table = $wpdb->prefix . 'ipm_client_files';
        $page  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $per   = 20;
        $offset = ( $page - 1 ) * $per;

        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

        $where = '1=1';
        if ( $status_filter ) {
            $where = $wpdb->prepare( 'status = %s', $status_filter );
        }

        $total = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );
        $files = $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY uploaded_at DESC LIMIT $per OFFSET $offset",
            ARRAY_A
        );

        $statuses = array(
            'uploaded'   => 'Téléversé',
            'received'   => 'Reçu',
            'verified'   => 'Vérifié',
            'rejected'   => 'Rejeté',
            'processing' => 'En traitement',
        );
        ?>
        <div class="wrap ipm-admin-wrap">
            <h1>
                <span class="dashicons dashicons-media-default"></span>
                Fichiers clients — Imprimerie Pro Maroc
            </h1>

            <div class="ipm-filters">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-files' ) ); ?>"
                   class="ipm-filter-link <?php echo empty( $status_filter ) ? 'active' : ''; ?>">
                    Tous (<?php echo esc_html( $total ); ?>)
                </a>
                <?php foreach ( $statuses as $key => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-files&status=' . $key ) ); ?>"
                       class="ipm-filter-link <?php echo $status_filter === $key ? 'active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fichier</th>
                        <th>Type</th>
                        <th>Taille</th>
                        <th>Client</th>
                        <th>Commande</th>
                        <th>Devis</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $files ) ) : ?>
                        <?php foreach ( $files as $file ) : ?>
                            <?php
                            $user = get_user_by( 'id', $file['customer_id'] );
                            $customer_name = $user ? $user->display_name : 'Invité';
                            ?>
                            <tr>
                                <td><?php echo esc_html( $file['id'] ); ?></td>
                                <td>
                                    <strong><?php echo esc_html( $file['file_name'] ); ?></strong>
                                    <?php if ( $file['notes'] ) : ?>
                                        <br><small><?php echo esc_html( $file['notes'] ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( strtoupper( $file['file_type'] ) ); ?></td>
                                <td><?php echo esc_html( IPM_File_Upload::format_file_size( $file['file_size'] ) ); ?></td>
                                <td><?php echo esc_html( $customer_name ); ?></td>
                                <td>
                                    <?php if ( $file['order_id'] ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $file['order_id'] . '&action=edit' ) ); ?>">
                                            #<?php echo esc_html( $file['order_id'] ); ?>
                                        </a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $file['quote_id'] ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $file['quote_id'] . '&action=edit' ) ); ?>">
                                            DEV-<?php echo esc_html( str_pad( $file['quote_id'], 6, '0', STR_PAD_LEFT ) ); ?>
                                        </a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select class="ipm-file-status-select" data-file-id="<?php echo esc_attr( $file['id'] ); ?>">
                                        <?php foreach ( $statuses as $k => $l ) : ?>
                                            <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $file['status'], $k ); ?>><?php echo esc_html( $l ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $file['uploaded_at'] ) ) ); ?></td>
                                <td>
                                    <?php if ( file_exists( $file['file_path'] ) ) : ?>
                                        <a href="<?php echo esc_url( str_replace( ABSPATH, site_url( '/' ), $file['file_path'] ) ); ?>" class="button button-small" target="_blank" title="Télécharger">
                                            <span class="dashicons dashicons-download"></span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="10">Aucun fichier trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $total_pages = ceil( $total / $per );
            if ( $total_pages > 1 ) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo wp_kses_post( paginate_links( array(
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $page,
                    'total'   => $total_pages,
                ) ) );
                echo '</div></div>';
            }
            ?>
        </div>
        <?php
    }
}
