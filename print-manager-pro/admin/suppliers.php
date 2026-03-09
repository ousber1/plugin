<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}
?>
<div class="wrap pmp-admin-wrap">
    <h1>Fournisseurs</h1>
    <p>Gestion des fournisseurs à venir. Cette section permet de lister et de gérer les fournisseurs pour vos impressions.</p>
</div>
