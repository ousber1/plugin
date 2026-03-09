<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}

global $wpdb;
$table = $wpdb->prefix . 'print_machines';
$machines = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
?>
<div class="wrap pmp-admin-wrap">
    <h1>Gestion des Machines</h1>

    <div class="pmp-form-section">
        <h2 id="pmp-machine-form-title">Ajouter une machine</h2>
        <form id="pmp-machine-form" class="pmp-admin-form">
            <input type="hidden" id="pmp-machine-id" name="machine_id" value="0">
            <?php wp_nonce_field( 'pmp_admin_nonce', 'pmp_nonce_field' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="machine_name">Nom de la machine</label></th>
                    <td><input type="text" id="machine_name" name="machine_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="cost_per_hour">Coût par heure (€)</label></th>
                    <td><input type="number" id="cost_per_hour" name="cost_per_hour" step="0.01" min="0" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="maintenance_cost">Coût maintenance (€/mois)</label></th>
                    <td><input type="number" id="maintenance_cost" name="maintenance_cost" step="0.01" min="0" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="machine_status">Statut</label></th>
                    <td>
                        <select id="machine_status" name="status">
                            <option value="active">Active</option>
                            <option value="maintenance">En maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Enregistrer</button>
                <button type="button" class="button" id="pmp-machine-cancel" style="display:none;">Annuler</button>
            </p>
        </form>
    </div>

    <div class="pmp-table-section">
        <h2>Liste des machines</h2>
        <table class="wp-list-table widefat fixed striped" id="pmp-machines-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Coût/heure</th>
                    <th>Maintenance/mois</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $machines ) ) : ?>
                    <tr><td colspan="6">Aucune machine enregistrée.</td></tr>
                <?php else : ?>
                    <?php foreach ( $machines as $machine ) : ?>
                        <tr data-id="<?php echo esc_attr( $machine->id ); ?>">
                            <td><?php echo esc_html( $machine->id ); ?></td>
                            <td><?php echo esc_html( $machine->machine_name ); ?></td>
                            <td><?php echo number_format( floatval( $machine->cost_per_hour ), 2, ',', ' ' ); ?> €</td>
                            <td><?php echo number_format( floatval( $machine->maintenance_cost ), 2, ',', ' ' ); ?> €</td>
                            <td>
                                <?php
                                $status_labels = array( 'active' => 'Active', 'maintenance' => 'En maintenance', 'inactive' => 'Inactive' );
                                $status_class = 'pmp-status-' . esc_attr( $machine->status );
                                echo '<span class="pmp-status ' . $status_class . '">' . esc_html( isset( $status_labels[ $machine->status ] ) ? $status_labels[ $machine->status ] : $machine->status ) . '</span>';
                                ?>
                            </td>
                            <td>
                                <button class="button button-small pmp-edit-machine"
                                    data-id="<?php echo esc_attr( $machine->id ); ?>"
                                    data-name="<?php echo esc_attr( $machine->machine_name ); ?>"
                                    data-cost="<?php echo esc_attr( $machine->cost_per_hour ); ?>"
                                    data-maintenance="<?php echo esc_attr( $machine->maintenance_cost ); ?>"
                                    data-status="<?php echo esc_attr( $machine->status ); ?>">
                                    Modifier
                                </button>
                                <button class="button button-small button-link-delete pmp-delete-machine"
                                    data-id="<?php echo esc_attr( $machine->id ); ?>">
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = pmp_admin.nonce;

    $('#pmp-machine-form').on('submit', function(e) {
        e.preventDefault();
        var data = {
            action: 'pmp_save_machine',
            nonce: nonce,
            machine_id: $('#pmp-machine-id').val(),
            machine_name: $('#machine_name').val(),
            cost_per_hour: $('#cost_per_hour').val(),
            maintenance_cost: $('#maintenance_cost').val(),
            status: $('#machine_status').val()
        };
        $.post(pmp_admin.ajax_url, data, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    $('.pmp-edit-machine').on('click', function() {
        var btn = $(this);
        $('#pmp-machine-id').val(btn.data('id'));
        $('#machine_name').val(btn.data('name'));
        $('#cost_per_hour').val(btn.data('cost'));
        $('#maintenance_cost').val(btn.data('maintenance'));
        $('#machine_status').val(btn.data('status'));
        $('#pmp-machine-form-title').text('Modifier la machine');
        $('#pmp-machine-cancel').show();
        $('html,body').animate({scrollTop: 0}, 300);
    });

    $('#pmp-machine-cancel').on('click', function() {
        $('#pmp-machine-id').val(0);
        $('#pmp-machine-form')[0].reset();
        $('#pmp-machine-form-title').text('Ajouter une machine');
        $(this).hide();
    });

    $('.pmp-delete-machine').on('click', function() {
        if (!confirm('Supprimer cette machine ?')) return;
        $.post(pmp_admin.ajax_url, {
            action: 'pmp_delete_machine',
            nonce: nonce,
            machine_id: $(this).data('id')
        }, function(response) {
            if (response.success) location.reload();
        });
    });
});
</script>
