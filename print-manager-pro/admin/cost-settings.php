<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}

global $wpdb;
$table = $wpdb->prefix . 'print_cost_settings';

// Handle form submission
if ( isset( $_POST['pmp_save_cost_settings'] ) && check_admin_referer( 'pmp_cost_settings_save' ) ) {
    if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
        foreach ( $_POST['settings'] as $key => $value ) {
            $wpdb->update(
                $table,
                array( 'setting_value' => sanitize_text_field( $value ) ),
                array( 'setting_key' => sanitize_text_field( $key ) )
            );
        }
        echo '<div class="notice notice-success"><p>Paramètres sauvegardés avec succès.</p></div>';
    }
}

$settings = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );

$groups = array(
    'Coûts de base' => array( 'paper_cost_per_sheet', 'ink_cost_per_sheet', 'machine_cost_per_hour', 'sheets_per_hour' ),
    'Marge et remises' => array( 'profit_margin', 'bulk_discount_100', 'bulk_discount_500', 'bulk_discount_1000', 'bulk_discount_5000' ),
    'Finitions' => array( 'finishing_lamination', 'finishing_uv_varnish', 'finishing_folding', 'finishing_cutting' ),
    'Multiplicateurs' => array( 'color_multiplier', 'recto_verso_multiplier' ),
    'Urgence & Livraison' => array( 'urgency_multiplier_standard', 'urgency_multiplier_express', 'delivery_cost_standard', 'delivery_cost_express', 'delivery_cost_retrait', 'delivery_zone_casablanca', 'delivery_zone_rabat', 'delivery_zone_marrakech', 'delivery_zone_autres' ),
    'Coûts papier (types)' => array( 'paper_cost_couche_mat', 'paper_cost_couche_brillant', 'paper_cost_offset', 'paper_cost_recycle', 'paper_cost_creation', 'paper_cost_kraft' ),
);

$settings_map = array();
foreach ( $settings as $s ) {
    $settings_map[ $s->setting_key ] = $s;
}
?>
<div class="wrap pmp-admin-wrap">
    <h1>Paramètres de tarification</h1>

    <form method="post">
        <?php wp_nonce_field( 'pmp_cost_settings_save' ); ?>

        <?php foreach ( $groups as $group_name => $keys ) : ?>
            <h2><?php echo esc_html( $group_name ); ?></h2>
            <table class="form-table">
                <?php foreach ( $keys as $key ) :
                    if ( ! isset( $settings_map[ $key ] ) ) continue;
                    $setting = $settings_map[ $key ];
                ?>
                    <tr>
                        <th><label for="setting_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $setting->description ); ?></label></th>
                        <td>
                            <input type="number" id="setting_<?php echo esc_attr( $key ); ?>"
                                name="settings[<?php echo esc_attr( $key ); ?>]"
                                value="<?php echo esc_attr( $setting->setting_value ); ?>"
                                step="0.001" min="0" class="regular-text">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach; ?>

        <p class="submit">
            <input type="submit" name="pmp_save_cost_settings" class="button button-primary" value="Sauvegarder les paramètres">
        </p>
    </form>
</div>
