<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}
?>
<div class="wrap pmp-admin-wrap">
    <h1>Devis</h1>
    <p>Gestion des devis à venir. Cette section permettra de créer, envoyer et suivre les devis clients.</p>
</div>
