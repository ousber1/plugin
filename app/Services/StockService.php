<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Settings;

/**
 * Point d'entrée UNIQUE de toute modification de stock (saisie manuelle,
 * inventaire, puis ventes POS, production et achats dans les phases suivantes).
 *
 * - Journal immuable stock_movements (quantité signée, coût, solde après mouvement)
 * - Niveau par emplacement stock_levels (source de vérité) + cache stock_quantity
 * - Coût moyen pondéré (CMUP) recalculé à chaque entrée valorisée
 * - Refus du stock négatif (sauf paramètre « autoriser la vente avec stock insuffisant »)
 * - Alerte « stock faible » quand le seuil minimum est atteint
 */
final class StockService
{
    /** Types de mouvements et leur sens autorisé (+1 entrée, -1 sortie, 0 les deux). */
    public const TYPES = [
        'purchase'        => ['Achat / réception', 1],
        'return'          => ['Retour client', 1],
        'production'      => ['Production (produit fini)', 1],
        'adjustment'      => ['Ajustement', 0],
        'inventory'       => ['Inventaire', 0],
        'sale'            => ['Vente', -1],
        'consumption'     => ['Consommation', -1],
        'loss'            => ['Perte', -1],
        'damaged'         => ['Matière endommagée', -1],
        'supplier_return' => ['Retour fournisseur', -1],
        'transfer'        => ['Transfert', 0],
    ];

    public static function label(string $type): string
    {
        return self::TYPES[$type][0] ?? $type;
    }

    /**
     * Enregistre un mouvement. À appeler de préférence dans la transaction du
     * document d'origine (vente, inventaire…) : tout est annulé ensemble en cas d'erreur.
     *
     * @param string $kind 'product' | 'material'
     * @param float  $quantity quantité signée (+ entrée, − sortie)
     * @param float|null $unitCost coût unitaire HT d'une entrée valorisée (achat) ; sinon coût moyen actuel
     * @param bool|null $allowNegative null = paramètre « allow_negative_stock »
     * @return int identifiant du mouvement
     */
    public static function move(
        string $kind,
        int $itemId,
        float $quantity,
        string $type,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $locationId = null,
        ?bool $allowNegative = null
    ): int {
        if (!in_array($kind, ['product', 'material'], true)) {
            throw new \InvalidArgumentException('Type d\'article invalide.');
        }
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException('Type de mouvement invalide.');
        }
        $quantity = round($quantity, 3);
        if ($quantity == 0.0) {
            throw new \DomainException('La quantité doit être différente de zéro.');
        }
        $direction = self::TYPES[$type][1];
        if ($direction !== 0 && ($quantity > 0 ? 1 : -1) !== $direction) {
            throw new \DomainException('Le sens du mouvement ne correspond pas à son type (« ' . self::label($type) . ' »).');
        }

        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($kind, $itemId, $quantity, $type, $unitCost, $referenceType, $referenceId, $reason, $locationId, $allowNegative): int {
            $locationId ??= (int) Settings::get('default_location_id', 1);
            $table = $kind === 'product' ? 'products' : 'materials';
            $costColumn = $kind === 'product' ? 'purchase_price' : 'unit_cost';

            // Verrouille l'article : deux ventes simultanées ne peuvent pas lire le même stock
            $item = $db->fetch("SELECT id, name, code, {$costColumn} AS cost, stock_quantity, min_stock"
                . ($kind === 'product' ? ', track_stock' : '') . " FROM {$table} WHERE id = ? AND deleted_at IS NULL FOR UPDATE", [$itemId]);
            if (!$item) {
                throw new \DomainException('Article introuvable.');
            }
            if ($kind === 'product' && (int) $item['track_stock'] !== 1) {
                throw new \DomainException('Le stock n\'est pas suivi pour « ' . $item['name'] . ' ».');
            }

            $column = $kind . '_id';
            $level = $db->fetch("SELECT id, quantity FROM stock_levels WHERE location_id = ? AND {$column} = ? FOR UPDATE", [$locationId, $itemId]);
            if (!$level) {
                $level = ['id' => $db->insert('stock_levels', ['location_id' => $locationId, $column => $itemId, 'quantity' => 0]), 'quantity' => 0];
            }
            $before = (float) $level['quantity'];
            $after = round($before + $quantity, 3);

            $allowNegative ??= (bool) Settings::get('allow_negative_stock', false);
            if ($after < 0 && $quantity < 0 && !$allowNegative) {
                throw new \DomainException(sprintf(
                    'Stock insuffisant pour « %s » : disponible %s, demandé %s.',
                    $item['name'], number_fr($before, 3), number_fr(abs($quantity), 3)
                ));
            }

            // Coût moyen pondéré sur les entrées valorisées
            $currentCost = (float) $item['cost'];
            $movementCost = $currentCost;
            if ($quantity > 0 && $unitCost !== null && $unitCost >= 0) {
                $movementCost = $unitCost;
                $totalBefore = max(0.0, (float) $item['stock_quantity']);
                $newCost = Settings::get('valuation_method', 'weighted_average') === 'last_cost' || $totalBefore <= 0
                    ? $unitCost
                    : (($totalBefore * $currentCost) + ($quantity * $unitCost)) / ($totalBefore + $quantity);
                $update = [$costColumn => round($newCost, 4)];
                if ($kind === 'material') {
                    $update['last_purchase_cost'] = round($unitCost, 4);
                }
                $db->update($table, $update, 'id = :id', ['id' => $itemId]);
            }

            $db->update('stock_levels', ['quantity' => $after], 'id = :id', ['id' => $level['id']]);
            $total = (float) $db->fetchColumn("SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE {$column} = ?", [$itemId]);
            $db->update($table, ['stock_quantity' => $total], 'id = :id', ['id' => $itemId]);

            $movementId = $db->insert('stock_movements', [
                'location_id'    => $locationId,
                $column          => $itemId,
                'movement_type'  => $type,
                'quantity'       => $quantity,
                'unit_cost'      => round($movementCost, 4),
                'balance_after'  => $after,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'reason'         => $reason !== null ? mb_substr($reason, 0, 255) : null,
                'user_id'        => Auth::id(),
            ]);

            if ($quantity < 0 && (float) $item['min_stock'] > 0 && $total <= (float) $item['min_stock'] && Settings::get('low_stock_alerts', true)) {
                NotificationService::create(
                    'low_stock',
                    'Stock faible : ' . $item['name'],
                    'Stock actuel ' . number_fr($total, 2) . ' (minimum ' . number_fr($item['min_stock'], 2) . ').',
                    $kind === 'product' ? 'products/' . $itemId : 'materials/' . $itemId,
                    $total <= 0 ? 'danger' : 'warning',
                    null, null, 'low_stock:' . $kind . ':' . $itemId, $kind, $itemId
                );
            }
            return $movementId;
        });
    }

    /** Valeur du stock (quantité × coût moyen) des produits suivis et des matières. */
    public static function valuation(): array
    {
        $db = Database::instance();
        $products = (float) $db->fetchColumn(
            'SELECT COALESCE(SUM(GREATEST(stock_quantity,0) * purchase_price), 0) FROM products WHERE deleted_at IS NULL AND track_stock = 1'
        );
        $materials = (float) $db->fetchColumn(
            'SELECT COALESCE(SUM(GREATEST(stock_quantity,0) * unit_cost), 0) FROM materials WHERE deleted_at IS NULL'
        );
        return ['products' => $products, 'materials' => $materials, 'total' => $products + $materials];
    }
}
