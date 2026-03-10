<?php
/**
 * Gestion admin des devis
 *
 * @package ImprimerieProMaroc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IPM_Admin_Quotes {

    /**
     * Afficher la page des devis
     */
    public static function render() {
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        $status_labels = IPM_Quote::get_status_labels();

        $args = array(
            'post_type'      => 'ipm_quote',
            'posts_per_page' => 20,
            'paged'          => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( $status_filter ) {
            $args['meta_query'] = array(
                array(
                    'key'   => '_ipm_quote_status',
                    'value' => $status_filter,
                ),
            );
        }

        $quotes = new WP_Query( $args );
        ?>
        <div class="wrap ipm-admin-wrap">
            <h1>
                <span class="dashicons dashicons-media-document"></span>
                Devis — Imprimerie Pro Maroc
            </h1>

            <!-- Filtres -->
            <div class="ipm-filters">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-quotes' ) ); ?>"
                   class="ipm-filter-link <?php echo empty( $status_filter ) ? 'active' : ''; ?>">
                    Tous (<?php echo esc_html( wp_count_posts( 'ipm_quote' )->publish ); ?>)
                </a>
                <?php foreach ( $status_labels as $key => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ipm-quotes&status=' . $key ) ); ?>"
                       class="ipm-filter-link <?php echo $status_filter === $key ? 'active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Type</th>
                        <th>Quantité</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $quotes->have_posts() ) : ?>
                        <?php while ( $quotes->have_posts() ) : $quotes->the_post(); ?>
                            <?php
                            $qid    = get_the_ID();
                            $ref    = 'DEV-' . str_pad( $qid, 6, '0', STR_PAD_LEFT );
                            $fname  = get_post_meta( $qid, '_ipm_quote_first_name', true );
                            $lname  = get_post_meta( $qid, '_ipm_quote_last_name', true );
                            $email  = get_post_meta( $qid, '_ipm_quote_email', true );
                            $phone  = get_post_meta( $qid, '_ipm_quote_phone', true );
                            $type   = get_post_meta( $qid, '_ipm_quote_print_type', true );
                            $qty    = get_post_meta( $qid, '_ipm_quote_quantity', true );
                            $status = get_post_meta( $qid, '_ipm_quote_status', true );
                            $status_lbl = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;

                            $colors = array(
                                'new'      => '#2196F3',
                                'pending'  => '#FF9800',
                                'sent'     => '#9C27B0',
                                'accepted' => '#4CAF50',
                                'refused'  => '#f44336',
                            );
                            $color = isset( $colors[ $status ] ) ? $colors[ $status ] : '#666';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $ref ); ?></strong></td>
                                <td><?php echo esc_html( $fname . ' ' . $lname ); ?></td>
                                <td><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></td>
                                <td><?php echo esc_html( $phone ); ?></td>
                                <td><?php echo esc_html( $type ); ?></td>
                                <td><?php echo esc_html( $qty ?: '-' ); ?></td>
                                <td>
                                    <select class="ipm-quote-status-select" data-quote-id="<?php echo esc_attr( $qid ); ?>">
                                        <?php foreach ( $status_labels as $k => $l ) : ?>
                                            <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><?php echo esc_html( get_the_date( 'd/m/Y H:i' ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $qid . '&action=edit' ) ); ?>" class="button button-small" title="Voir">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </a>
                                    <?php if ( Imprimerie_Pro_Maroc::is_woocommerce_active() && 'sent' === $status ) : ?>
                                        <button class="button button-small ipm-convert-quote" data-quote-id="<?php echo esc_attr( $qid ); ?>" title="Convertir en commande">
                                            <span class="dashicons dashicons-cart"></span>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php wp_reset_postdata(); ?>
                    <?php else : ?>
                        <tr><td colspan="9">Aucun devis trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            // Pagination
            $total_pages = $quotes->max_num_pages;
            if ( $total_pages > 1 ) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo wp_kses_post( paginate_links( array(
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ),
                    'total'   => $total_pages,
                ) ) );
                echo '</div></div>';
            }
            ?>
        </div>
        <?php
    }
}

// Métabox devis
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'ipm_quote_details',
        'Détails du devis',
        'ipm_render_quote_metabox',
        'ipm_quote',
        'normal',
        'high'
    );
} );

function ipm_render_quote_metabox( $post ) {
    $fields = array(
        'Prénom'       => get_post_meta( $post->ID, '_ipm_quote_first_name', true ),
        'Nom'          => get_post_meta( $post->ID, '_ipm_quote_last_name', true ),
        'Email'        => get_post_meta( $post->ID, '_ipm_quote_email', true ),
        'Téléphone'    => get_post_meta( $post->ID, '_ipm_quote_phone', true ),
        'Ville'        => get_post_meta( $post->ID, '_ipm_quote_city', true ),
        'Entreprise'   => get_post_meta( $post->ID, '_ipm_quote_company', true ),
        'Type'         => get_post_meta( $post->ID, '_ipm_quote_print_type', true ),
        'Quantité'     => get_post_meta( $post->ID, '_ipm_quote_quantity', true ),
        'Dimensions'   => get_post_meta( $post->ID, '_ipm_quote_dimensions', true ),
        'Description'  => get_post_meta( $post->ID, '_ipm_quote_description', true ),
        'Date souhaitée' => get_post_meta( $post->ID, '_ipm_quote_desired_date', true ),
    );

    $status = get_post_meta( $post->ID, '_ipm_quote_status', true );
    $amount = get_post_meta( $post->ID, '_ipm_quote_amount', true );
    $status_labels = IPM_Quote::get_status_labels();

    echo '<table class="form-table">';

    foreach ( $fields as $label => $value ) {
        if ( $value ) {
            printf(
                '<tr><th>%s</th><td>%s</td></tr>',
                esc_html( $label ),
                esc_html( $value )
            );
        }
    }

    echo '<tr><th>Statut</th><td>';
    echo '<select name="ipm_quote_status">';
    foreach ( $status_labels as $k => $l ) {
        printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $status, $k, false ), esc_html( $l ) );
    }
    echo '</select></td></tr>';

    printf(
        '<tr><th>Montant du devis (MAD)</th><td><input type="number" name="ipm_quote_amount" value="%s" step="0.01" min="0" class="regular-text"></td></tr>',
        esc_attr( $amount )
    );

    echo '</table>';

    // Fichiers liés
    $files = IPM_File_Upload::get_quote_files( $post->ID );
    if ( ! empty( $files ) ) {
        echo '<h4>Fichiers associés</h4>';
        echo '<ul>';
        foreach ( $files as $file ) {
            printf(
                '<li>%s (%s) - %s</li>',
                esc_html( $file['file_name'] ),
                esc_html( strtoupper( $file['file_type'] ) ),
                esc_html( IPM_File_Upload::format_file_size( $file['file_size'] ) )
            );
        }
        echo '</ul>';
    }
}

// Sauvegarder les méta du devis
add_action( 'save_post_ipm_quote', function( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['ipm_quote_status'] ) ) {
        $status = sanitize_text_field( wp_unslash( $_POST['ipm_quote_status'] ) );
        IPM_Quote::update_status( $post_id, $status );
    }

    if ( isset( $_POST['ipm_quote_amount'] ) ) {
        update_post_meta( $post_id, '_ipm_quote_amount', sanitize_text_field( wp_unslash( $_POST['ipm_quote_amount'] ) ) );
    }
} );
