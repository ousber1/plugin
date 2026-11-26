<?php

/*
 * Payment methods. Online providers are seeded INACTIVE: they need credentials and real
 * provider confirmation (webhook) before an order can be marked as paid.
 */

use App\Core\Database;

return static function (Database $db): void {
    $methods = [
        ['cod', 'Paiement à la livraison', 'Payez en espèces à la réception de votre commande.', null, 0, null, 1, 1],
        ['bank_transfer', 'Virement bancaire', 'Effectuez un virement sur notre compte. La production démarre à réception du paiement.', 'Les coordonnées bancaires (RIB) vous seront communiquées après la commande.', 0, null, 1, 2],
        ['manual', 'Paiement manuel (agence / Cash Plus / Wafacash)', 'Paiement confirmé manuellement par notre équipe.', 'Envoyez-nous le reçu par WhatsApp avec votre numéro de commande.', 0, null, 1, 3],
        ['cmi', 'Carte bancaire (CMI)', 'Paiement en ligne sécurisé par carte — nécessite un contrat CMI.', null, 1, 'cmi', 0, 4],
        ['paypal', 'PayPal', 'Paiement en ligne via PayPal — nécessite une configuration.', null, 1, 'paypal', 0, 5],
    ];
    foreach ($methods as [$code, $name, $desc, $instr, $online, $provider, $active, $sort]) {
        $db->query(
            'INSERT IGNORE INTO payment_methods (code, name, description, instructions, is_online, provider, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $name, $desc, $instr, $online, $provider, $active, $sort]
        );
    }
};
