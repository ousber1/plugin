<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}
?>
<div class="wrap pmp-admin-wrap">
    <h1>Clients</h1>
    <p>Gestion des clients à venir. Cette section permettra de suivre les clients et leurs commandes.</p>
</div>
