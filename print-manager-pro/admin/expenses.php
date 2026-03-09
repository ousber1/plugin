<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}

global $wpdb;
$table = $wpdb->prefix . 'print_expenses';

$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page = 20;
$offset = ( $current_page - 1 ) * $per_page;

$filter_cat = isset( $_GET['filter_cat'] ) ? sanitize_text_field( $_GET['filter_cat'] ) : '';
$filter_month = isset( $_GET['filter_month'] ) ? sanitize_text_field( $_GET['filter_month'] ) : '';

$where = '1=1';
$params = array();
if ( $filter_cat ) {
    $where .= ' AND category = %s';
    $params[] = $filter_cat;
}
if ( $filter_month ) {
    $where .= ' AND DATE_FORMAT(expense_date, "%%Y-%%m") = %s';
    $params[] = $filter_month;
}

$total = $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) : "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
$query = "SELECT * FROM {$table} WHERE {$where} ORDER BY expense_date DESC LIMIT {$per_page} OFFSET {$offset}";
$expenses = $params ? $wpdb->get_results( $wpdb->prepare( $query, $params ) ) : $wpdb->get_results( $query );

$categories = array(
    'paper'       => 'Achat papier',
    'ink'         => 'Achat encre',
    'maintenance' => 'Maintenance machines',
    'electricity' => 'Électricité',
    'salary'      => 'Salaires',
    'delivery'    => 'Livraison',
    'rent'        => 'Loyer',
    'other'       => 'Autre',
);

$total_pages = ceil( $total / $per_page );
?>
<div class="wrap pmp-admin-wrap">
    <h1>Gestion des Dépenses</h1>

    <div class="pmp-form-section">
        <h2 id="pmp-expense-form-title">Ajouter une dépense</h2>
        <form id="pmp-expense-form" class="pmp-admin-form">
            <input type="hidden" id="pmp-expense-id" name="expense_id" value="0">

            <table class="form-table">
                <tr>
                    <th><label for="expense_category">Catégorie</label></th>
                    <td>
                        <select id="expense_category" name="category" required>
                            <?php foreach ( $categories as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="expense_description">Description</label></th>
                    <td><input type="text" id="expense_description" name="description" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="expense_amount">Montant (€)</label></th>
                    <td><input type="number" id="expense_amount" name="amount" step="0.01" min="0" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="expense_date">Date</label></th>
                    <td><input type="date" id="expense_date" name="expense_date" class="regular-text" required value="<?php echo date( 'Y-m-d' ); ?>"></td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Enregistrer</button>
                <button type="button" class="button" id="pmp-expense-cancel" style="display:none;">Annuler</button>
            </p>
        </form>
    </div>

    <div class="pmp-table-section">
        <h2>Liste des dépenses</h2>

        <div class="pmp-filters">
            <form method="get">
                <input type="hidden" name="page" value="pmp-expenses">
                <select name="filter_cat">
                    <option value="">Toutes catégories</option>
                    <?php foreach ( $categories as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filter_cat, $key ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="month" name="filter_month" value="<?php echo esc_attr( $filter_month ); ?>">
                <button type="submit" class="button">Filtrer</button>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Catégorie</th>
                    <th>Description</th>
                    <th>Montant</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $expenses ) ) : ?>
                    <tr><td colspan="5">Aucune dépense trouvée.</td></tr>
                <?php else : ?>
                    <?php foreach ( $expenses as $exp ) : ?>
                        <tr>
                            <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $exp->expense_date ) ) ); ?></td>
                            <td><?php echo esc_html( isset( $categories[ $exp->category ] ) ? $categories[ $exp->category ] : $exp->category ); ?></td>
                            <td><?php echo esc_html( $exp->description ); ?></td>
                            <td><?php echo number_format( floatval( $exp->amount ), 2, ',', ' ' ); ?> €</td>
                            <td>
                                <button class="button button-small pmp-edit-expense"
                                    data-id="<?php echo esc_attr( $exp->id ); ?>"
                                    data-category="<?php echo esc_attr( $exp->category ); ?>"
                                    data-description="<?php echo esc_attr( $exp->description ); ?>"
                                    data-amount="<?php echo esc_attr( $exp->amount ); ?>"
                                    data-date="<?php echo esc_attr( $exp->expense_date ); ?>">
                                    Modifier
                                </button>
                                <button class="button button-small button-link-delete pmp-delete-expense"
                                    data-id="<?php echo esc_attr( $exp->id ); ?>">
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $page_links = paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'total'     => $total_pages,
                        'current'   => $current_page,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ) );
                    echo $page_links;
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = pmp_admin.nonce;

    $('#pmp-expense-form').on('submit', function(e) {
        e.preventDefault();
        $.post(pmp_admin.ajax_url, {
            action: 'pmp_save_expense',
            nonce: nonce,
            expense_id: $('#pmp-expense-id').val(),
            category: $('#expense_category').val(),
            description: $('#expense_description').val(),
            amount: $('#expense_amount').val(),
            expense_date: $('#expense_date').val()
        }, function(response) {
            if (response.success) location.reload();
            else alert(response.data.message);
        });
    });

    $('.pmp-edit-expense').on('click', function() {
        var btn = $(this);
        $('#pmp-expense-id').val(btn.data('id'));
        $('#expense_category').val(btn.data('category'));
        $('#expense_description').val(btn.data('description'));
        $('#expense_amount').val(btn.data('amount'));
        $('#expense_date').val(btn.data('date'));
        $('#pmp-expense-form-title').text('Modifier la dépense');
        $('#pmp-expense-cancel').show();
        $('html,body').animate({scrollTop: 0}, 300);
    });

    $('#pmp-expense-cancel').on('click', function() {
        $('#pmp-expense-id').val(0);
        $('#pmp-expense-form')[0].reset();
        $('#pmp-expense-form-title').text('Ajouter une dépense');
        $(this).hide();
    });

    $('.pmp-delete-expense').on('click', function() {
        if (!confirm('Supprimer cette dépense ?')) return;
        $.post(pmp_admin.ajax_url, {
            action: 'pmp_delete_expense',
            nonce: nonce,
            expense_id: $(this).data('id')
        }, function(response) {
            if (response.success) location.reload();
        });
    });
});
</script>
