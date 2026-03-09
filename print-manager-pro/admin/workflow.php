<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Accès non autorisé.' );
}
?>
<div class="wrap pmp-admin-wrap">
    <h1>Workflow commandes</h1>
    <p>Gestion du workflow de production (étapes, statuts, actions). Cette fonctionnalité sera développée prochainement.</p>
</div>
