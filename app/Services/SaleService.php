<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DocumentNumber;
use App\Core\Settings;

/**
 * Ventes au point de vente : encaissement, mise en attente, annulation, retours.
 *
 * Le panier envoyé par le navigateur n'est qu'une INTENTION : produits, quantités,
 * prix saisis. Le serveur recalcule les prix applicables, vérifie chaque
 * dérogation (prix modifié, remise, prix minimum, crédit) au regard des
 * permissions, recalcule les totaux et enregistre tout dans une transaction :
 * vente, lignes, paiements, mouvements de caisse, stock, fidélité, journal.
 */
final class SaleService
{
    /* ==================================================================
       Construction et contrôle des lignes
       ================================================================== */

    /**
     * @param array $items lignes brutes du panier
     * @return array lignes validées prêtes pour SaleCalculator et l'enregistrement
     */
    public static function buildLines(array $items, int $customerId, bool $allowNegativeCustom = false): array
    {
        if (!$items) {
            throw new \DomainException('Le panier est vide.');
        }
        if (count($items) > 300) {
            throw new \DomainException('Trop de lignes dans le panier (300 maximum).');
        }
        $db = Database::instance();
        $productIds = [];
        foreach ($items as $it) {
            if (($it['type'] ?? '') === 'product') {
                $productIds[] = (int) ($it['product_id'] ?? 0);
            }
        }
        $prices = PricingService::forProducts($productIds, $customerId);
        $products = [];
        if ($productIds) {
            $in = implode(',', array_fill(0, count(array_unique($productIds)), '?'));
            foreach ($db->fetchAll(
                "SELECT p.id, p.name, p.name_fr, p.code, p.unit_id, p.track_stock, p.is_active, p.deleted_at, u.is_area, u.symbol, u.allows_decimal
                   FROM products p JOIN units u ON u.id = p.unit_id WHERE p.id IN ({$in})",
                array_values(array_unique($productIds))
            ) as $p) {
                $products[(int) $p['id']] = $p;
            }
        }
        $validRates = array_map('floatval', array_column($db->fetchAll('SELECT rate FROM taxes WHERE is_active = 1'), 'rate'));
        $maxDiscount = (float) Settings::get('max_discount_percent', 20);

        $lines = [];
        foreach (array_values($items) as $n => $it) {
            $label = 'Ligne ' . ($n + 1);
            $type = (string) ($it['type'] ?? '');
            $qty = round((float) ($it['quantity'] ?? 0), 3);
            if ($qty <= 0 || $qty > 1000000) {
                throw new \DomainException("{$label} : quantité invalide.");
            }
            $unitPrice = round((float) ($it['unit_price'] ?? 0), 4);
            $discount = round((float) ($it['discount_percent'] ?? 0), 2);
            // Prix négatif admis uniquement pour les lignes libres des factures (déduction d'acompte)
            if (($unitPrice < 0 && !($allowNegativeCustom && $type === 'custom')) || $discount < 0 || $discount > 100) {
                throw new \DomainException("{$label} : prix ou remise invalide.");
            }
            $notes = mb_substr(trim((string) ($it['notes'] ?? '')), 0, 255) ?: null;
            $line = ['product_id' => null, 'pack_id' => null, 'width' => null, 'height' => null, 'dimension_unit' => null,
                'unit_price' => $unitPrice, 'discount_percent' => $discount, 'notes' => $notes, 'original_price' => $unitPrice,
                'price_overridden' => false, 'unit_cost' => 0.0, 'track_stock' => false, 'pieces' => $qty];

            if ($type === 'product') {
                $pid = (int) ($it['product_id'] ?? 0);
                $p = $products[$pid] ?? null;
                if (!$p || $p['deleted_at'] !== null || !(int) $p['is_active'] || !isset($prices[$pid])) {
                    throw new \DomainException("{$label} : produit indisponible.");
                }
                $pr = $prices[$pid];
                // Désignation personnalisable sur les devis / factures (le POS envoie le nom du catalogue)
                $custom = mb_substr(trim((string) ($it['description'] ?? '')), 0, 400);
                $description = $custom !== '' && $custom !== $p['name'] ? $custom : ($p['name_fr'] ?: $p['name']);
                if ((int) $p['is_area'] === 1) {
                    // Travail au m² : largeur × hauteur (cm) × nombre d'exemplaires
                    $w = round((float) ($it['width'] ?? 0), 2);
                    $h = round((float) ($it['height'] ?? 0), 2);
                    if ($w <= 0 || $h <= 0 || $w > 100000 || $h > 100000) {
                        throw new \DomainException("{$label} : indiquez la largeur et la hauteur (cm) de « {$p['name']} ».");
                    }
                    $line += ['area_pieces' => $qty];
                    $line['width'] = $w;
                    $line['height'] = $h;
                    $line['dimension_unit'] = 'cm';
                    $description .= ' — ' . number_fr($w, 0) . ' × ' . number_fr($h, 0) . ' cm' . ($qty != 1.0 ? ' × ' . number_fr($qty, 0) : '');
                    $qty = round($w * $h / 10000 * $qty, 3);
                    if ($qty <= 0) {
                        throw new \DomainException("{$label} : surface nulle.");
                    }
                } elseif (!(int) $p['allows_decimal'] && floor($qty) != $qty) {
                    throw new \DomainException("{$label} : « {$p['name']} » se vend par unité entière ({$p['symbol']}).");
                }
                // Dérogations tarifaires
                if (abs($unitPrice - $pr['price']) > 0.0049) {
                    if (!$pr['allow_override'] || !Auth::can('pos.price_override')) {
                        throw new \DomainException("{$label} : vous n'êtes pas autorisé à modifier le prix de « {$p['name']} ».");
                    }
                    $line['price_overridden'] = true;
                }
                if ($discount > $pr['discount_percent'] + 0.001) {
                    if (!Auth::can('pos.discount')) {
                        throw new \DomainException("{$label} : vous n'êtes pas autorisé à accorder une remise.");
                    }
                    if ($discount > max($maxDiscount, $pr['discount_percent']) && !Auth::can('pos.price_override')) {
                        throw new \DomainException("{$label} : remise supérieure au maximum autorisé ({$maxDiscount} %).");
                    }
                }
                $effective = $unitPrice * (1 - $discount / 100);
                if ($pr['min_price'] > 0 && $effective < $pr['min_price'] - 0.0049 && !Auth::can('pos.below_min_price')) {
                    throw new \DomainException("{$label} : prix inférieur au prix minimum de « {$p['name']} » (" . money($pr['min_price']) . ' HT).');
                }
                $line['product_id'] = $pid;
                $line['original_price'] = $pr['price'];
                $line['tax_rate'] = $pr['tax_rate'];
                $line['unit_cost'] = $pr['cost'];
                $line['unit_id'] = (int) $p['unit_id'];
                $line['track_stock'] = (bool) (int) $p['track_stock'];
                $line['description'] = $description;
            } elseif ($type === 'pack') {
                $packId = (int) ($it['pack_id'] ?? 0);
                $pack = PricingService::forPack($packId);
                $name = $db->fetchColumn('SELECT name FROM packs WHERE id = ?', [$packId]);
                if (!$pack || !$name) {
                    throw new \DomainException("{$label} : pack indisponible.");
                }
                if (floor($qty) != $qty) {
                    throw new \DomainException("{$label} : un pack se vend par unité entière.");
                }
                if (abs($unitPrice - $pack['price']) > 0.0049 && !Auth::can('pos.price_override')) {
                    throw new \DomainException("{$label} : vous n'êtes pas autorisé à modifier le prix du pack.");
                }
                if ($discount > 0 && !Auth::can('pos.discount')) {
                    throw new \DomainException("{$label} : vous n'êtes pas autorisé à accorder une remise.");
                }
                $line['pack_id'] = $packId;
                $line['original_price'] = $pack['price'];
                $line['price_overridden'] = abs($unitPrice - $pack['price']) > 0.0049;
                $line['tax_rate'] = $pack['tax_rate'];
                $line['unit_cost'] = $pack['cost'];
                $line['unit_id'] = (int) ($db->fetchColumn("SELECT id FROM units WHERE code = 'pack'") ?: 0) ?: null;
                $custom = mb_substr(trim((string) ($it['description'] ?? '')), 0, 400);
                $line['description'] = $custom !== '' && $custom !== $name ? $custom : 'Pack ' . $name;
            } elseif ($type === 'custom') {
                // Article libre (travail sur mesure, service ponctuel)
                $description = mb_substr(trim((string) ($it['description'] ?? '')), 0, 500);
                if ($description === '') {
                    throw new \DomainException("{$label} : désignation obligatoire pour un article libre.");
                }
                $rate = round((float) ($it['tax_rate'] ?? 0), 3);
                if (Settings::get('tax_enabled', true) && !in_array($rate, $validRates, true)) {
                    throw new \DomainException("{$label} : taux de TVA invalide.");
                }
                if ($discount > 0 && !Auth::can('pos.discount')) {
                    throw new \DomainException("{$label} : vous n'êtes pas autorisé à accorder une remise.");
                }
                $unitId = (int) ($it['unit_id'] ?? 0);
                $line['unit_id'] = $unitId && $db->fetchColumn('SELECT id FROM units WHERE id = ?', [$unitId]) ? $unitId : null;
                $line['tax_rate'] = Settings::get('tax_enabled', true) ? $rate : 0.0;
                $line['description'] = $description;
                $line['save_to_catalog'] = !empty($it['save_to_catalog']) && Auth::can('products.create');
            } else {
                throw new \DomainException("{$label} : type d'article inconnu.");
            }
            $line['quantity'] = $qty;
            $lines[] = $line;
        }
        return $lines;
    }

    /** Contrôle de la remise globale. */
    public static function checkGlobalDiscount(string $type, float $value, float $subtotal): void
    {
        if ($type === 'none' || $value <= 0) {
            return;
        }
        if (!Auth::can('pos.discount')) {
            throw new \DomainException('Vous n\'êtes pas autorisé à accorder une remise.');
        }
        $pct = $type === 'percent' ? $value : ($subtotal > 0 ? $value / $subtotal * 100 : 0);
        $max = (float) Settings::get('max_discount_percent', 20);
        if ($pct > $max + 0.001 && !Auth::can('pos.price_override')) {
            throw new \DomainException("Remise globale supérieure au maximum autorisé ({$max} %).");
        }
    }

    /* ==================================================================
       Encaissement
       ================================================================== */

    /**
     * @param array $input ['customer_id', 'items', 'discount_type', 'discount_value', 'payments', 'notes', 'draft_id']
     * @return array{id: int, number: string, total: float, change: float, due: float}
     */
    public static function checkout(array $input): array
    {
        if (!Auth::can('pos.access')) {
            throw new \DomainException('Accès au point de vente refusé.');
        }
        $db = Database::instance();
        $customerId = (int) ($input['customer_id'] ?? 0) ?: (int) Settings::get('walk_in_customer_id', 1);
        $customer = $db->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL AND is_active = 1', [$customerId]);
        if (!$customer) {
            throw new \DomainException('Client introuvable ou inactif.');
        }
        $lines = self::buildLines((array) ($input['items'] ?? []), $customerId);
        $discountType = (string) ($input['discount_type'] ?? 'none');
        $discountType = in_array($discountType, ['none', 'percent', 'amount'], true) ? $discountType : 'none';
        $discountValue = max(0.0, round((float) ($input['discount_value'] ?? 0), 2));
        $calc = SaleCalculator::compute($lines, $discountType, $discountValue);
        self::checkGlobalDiscount($discountType, $discountValue, $calc['subtotal']);
        $total = $calc['total'];

        // --- Paiements -----------------------------------------------------
        $methods = [];
        foreach ($db->fetchAll('SELECT * FROM payment_methods WHERE is_active = 1') as $m) {
            $methods[(int) $m['id']] = $m;
        }
        $payments = [];
        $received = 0.0;
        $cashReceived = 0.0;
        foreach ((array) ($input['payments'] ?? []) as $pay) {
            $mid = (int) ($pay['method_id'] ?? 0);
            $amount = round((float) ($pay['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $m = $methods[$mid] ?? null;
            if (!$m) {
                throw new \DomainException('Mode de paiement invalide.');
            }
            if ((int) $m['is_credit'] === 1) {
                continue; // le crédit est le reste dû, calculé ci-dessous
            }
            $reference = mb_substr(trim((string) ($pay['reference'] ?? '')), 0, 100);
            if ((int) $m['requires_reference'] === 1 && $reference === '') {
                throw new \DomainException('Référence obligatoire pour le paiement par « ' . $m['name'] . ' » (n° de chèque, de virement, de transaction).');
            }
            $payments[] = ['method' => $m, 'amount' => $amount, 'reference' => $reference ?: null];
            $received += $amount;
            if ((int) $m['affects_cash'] === 1) {
                $cashReceived += $amount;
            }
        }
        $received = round($received, 2);
        $change = max(0.0, round($received - $total, 2));
        if ($change > 0 && $change > $cashReceived + 0.001) {
            throw new \DomainException('Le montant reçu dépasse le total : seul un paiement en espèces peut donner lieu à un rendu de monnaie.');
        }
        $applied = round(min($received, $total), 2);
        $due = round($total - $applied, 2);

        // --- Crédit client (paiement partiel ou vente à crédit) ------------
        if ($due > 0.004) {
            if (!Auth::can('pos.credit_sale')) {
                throw new \DomainException('Paiement incomplet : il manque ' . money($due) . '. Vous n\'êtes pas autorisé à vendre à crédit.');
            }
            if ((int) $customer['is_walk_in'] === 1) {
                throw new \DomainException('Paiement incomplet (' . money($due) . ' restant) : sélectionnez un client enregistré pour une vente à crédit.');
            }
            $limit = (float) $customer['credit_limit'];
            $balance = CustomerService::balance($customerId);
            if ($limit <= 0) {
                throw new \DomainException('Ce client n\'a pas de crédit autorisé (limite de crédit à 0 sur sa fiche).');
            }
            if ($balance + $due > $limit + 0.004) {
                throw new \DomainException('Limite de crédit dépassée : solde actuel ' . money($balance) . ' + ' . money($due) . ' > limite ' . money($limit) . '.');
            }
        }

        // --- Caisse ----------------------------------------------------------
        $session = CashService::openSession();
        if (!$session && (Settings::get('require_cash_session', true) || $cashReceived > 0)) {
            throw new \DomainException('La caisse est fermée : ouvrez une session de caisse avant d\'encaisser.');
        }

        $result = $db->transaction(function (Database $db) use ($input, $customer, $customerId, $lines, $calc, $discountType, $discountValue, $payments, $applied, $change, $due, $session): array {
            $now = date('Y-m-d H:i:s');
            $calcLines = $calc['lines'];
            // Création au catalogue des articles libres marqués « ajouter au catalogue »
            foreach ($calcLines as $i => $l) {
                if (!empty($l['save_to_catalog'])) {
                    $pid = $db->insert('products', [
                        'code' => DocumentNumber::next('product'), 'name' => mb_substr($l['description'], 0, 190), 'product_type' => 'service',
                        'unit_id' => $l['unit_id'] ?: (int) ($db->fetchColumn("SELECT id FROM units WHERE code = 'service'") ?: 1),
                        'selling_price' => round($l['unit_price'], 2),
                        'tax_id' => $db->fetchColumn('SELECT id FROM taxes WHERE rate = ? ORDER BY is_default DESC LIMIT 1', [$l['tax_rate']]) ?: null,
                        'created_by' => Auth::id(),
                    ]);
                    $calcLines[$i]['product_id'] = $pid;
                    AuditLog::log('create', 'products', 'product', $pid, 'Service créé depuis le point de vente : ' . $l['description']);
                }
            }
            $costTotal = 0.0;
            foreach ($calcLines as $l) {
                $costTotal += $l['unit_cost'] * $l['quantity'];
            }
            $saleId = $db->insert('sales', [
                'number'          => DocumentNumber::next('sale'),
                'cash_session_id' => $session['id'] ?? null,
                'customer_id'     => $customerId,
                'user_id'         => Auth::id(),
                'status'          => 'completed',
                'sale_date'       => $now,
                'subtotal'        => $calc['subtotal'],
                'discount_type'   => $discountType,
                'discount_value'  => $discountType === 'none' ? 0 : $discountValue,
                'discount_total'  => $calc['discount_total'],
                'tax_total'       => $calc['tax_total'],
                'total'           => $calc['total'],
                'paid_amount'     => $applied,
                'change_amount'   => $change,
                'cost_total'      => round($costTotal, 2),
                'notes'           => mb_substr(trim((string) ($input['notes'] ?? '')), 0, 2000) ?: null,
            ]);
            $number = (string) $db->fetchColumn('SELECT number FROM sales WHERE id = ?', [$saleId]);
            foreach ($calcLines as $i => $l) {
                $db->insert('sale_items', [
                    'sale_id' => $saleId, 'product_id' => $l['product_id'], 'pack_id' => $l['pack_id'],
                    'description' => mb_substr($l['description'], 0, 500), 'width' => $l['width'], 'height' => $l['height'],
                    'dimension_unit' => $l['dimension_unit'], 'quantity' => $l['quantity'], 'unit_id' => $l['unit_id'],
                    'original_price' => $l['original_price'], 'unit_price' => $l['unit_price'],
                    'price_overridden_by' => $l['price_overridden'] ? Auth::id() : null,
                    'discount_percent' => $l['discount_percent'], 'tax_rate' => $l['tax_rate'],
                    'line_subtotal' => $l['line_subtotal'], 'line_tax' => $l['line_tax'], 'line_total' => $l['line_total'],
                    'unit_cost' => round($l['unit_cost'], 4), 'notes' => $l['notes'], 'sort_order' => $i,
                ]);
            }

            // Paiements : un reçu par mode, regroupés (paiement mixte) ; la monnaie rendue est déduite des espèces
            $group = bin2hex(random_bytes(8));
            $changeLeft = $change;
            foreach ($payments as $p) {
                $amount = $p['amount'];
                if ($changeLeft > 0 && (int) $p['method']['affects_cash'] === 1) {
                    $deduct = min($amount, $changeLeft);
                    $amount = round($amount - $deduct, 2);
                    $changeLeft = round($changeLeft - $deduct, 2);
                }
                if ($amount <= 0) {
                    continue;
                }
                $paymentId = $db->insert('payments', [
                    'number' => DocumentNumber::next('receipt'), 'payment_group' => $group, 'direction' => 'in',
                    'customer_id' => $customerId, 'payment_method_id' => (int) $p['method']['id'],
                    'cash_session_id' => $session['id'] ?? null, 'sale_id' => $saleId, 'amount' => $amount,
                    'payment_date' => $now, 'reference' => $p['reference'], 'user_id' => Auth::id(),
                ]);
                if ($session) {
                    CashService::movement((int) $session['id'], 'sale', $amount, (int) $p['method']['id'], 'sale', $saleId, 'Vente ' . $number);
                }
            }

            self::deductStock($calcLines, $saleId, $number);

            // Fidélité
            if (Settings::get('loyalty_enabled', false) && (int) $customer['is_walk_in'] !== 1) {
                $points = (int) floor($calc['total'] / 100 * (float) Settings::get('points_per_amount', 0));
                if ($points > 0) {
                    $db->insert('loyalty_transactions', ['customer_id' => $customerId, 'points' => $points, 'reason' => 'Achat ' . $number,
                        'reference_type' => 'sale', 'reference_id' => $saleId, 'user_id' => Auth::id()]);
                    $db->query('UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?', [$points, $customerId]);
                }
            }

            // Vente reprise depuis la file d'attente : le brouillon est supprimé
            $draftId = (int) ($input['draft_id'] ?? 0);
            if ($draftId > 0) {
                // Vente issue d'un devis : le devis est marqué « converti »
                $quotationId = (int) $db->fetchColumn("SELECT quotation_id FROM sales WHERE id = ? AND status = 'suspended'", [$draftId]);
                $db->query("DELETE FROM sales WHERE id = ? AND status = 'suspended'", [$draftId]);
                if ($quotationId > 0) {
                    $db->update('sales', ['quotation_id' => $quotationId], 'id = :id', ['id' => $saleId]);
                    QuotationService::markConverted($quotationId, 'sale', $saleId);
                }
            }

            $overrides = count(array_filter($calcLines, static fn($l) => $l['price_overridden']));
            AuditLog::log('sale', 'pos', 'sale', $saleId, sprintf('Vente %s : %s%s%s', $number, money($calc['total']),
                $due > 0.004 ? ' (crédit ' . money($due) . ')' : '', $overrides ? " — {$overrides} prix modifié(s)" : ''));
            return ['id' => $saleId, 'number' => $number];
        });

        if ($due > 0.004) {
            $limit = (float) $customer['credit_limit'];
            $balance = CustomerService::balance($customerId);
            if ($limit > 0 && $balance >= 0.9 * $limit) {
                NotificationService::create('customer_debt', 'Crédit client proche de la limite : ' . CustomerService::displayName($customer),
                    'Solde ' . money($balance) . ' / limite ' . money($limit) . '.', 'customers/' . $customerId, 'warning',
                    null, null, 'customer_debt:' . $customerId . ':' . date('Y-m-d'));
            }
        }
        return $result + ['total' => $total, 'change' => $change, 'due' => $due];
    }

    /**
     * Sorties de stock d'une vente : produits suivis, composants de packs suivis,
     * et matières de la nomenclature des travaux non stockés (si aucun ordre de
     * production n'est créé automatiquement — sinon la production s'en charge).
     */
    private static function deductStock(array $lines, int $saleId, string $number): void
    {
        if (!Settings::get('auto_deduct_sales', true)) {
            return;
        }
        $db = Database::instance();
        $bomAtSale = !Settings::get('auto_create_from_sale', false);
        foreach ($lines as $l) {
            if ($l['product_id'] && $l['track_stock']) {
                StockService::move('product', (int) $l['product_id'], -$l['quantity'], 'sale', null, 'sale', $saleId, 'Vente ' . $number);
            } elseif ($l['pack_id']) {
                foreach ($db->fetchAll('SELECT pi.product_id, pi.quantity FROM pack_items pi JOIN products p ON p.id = pi.product_id WHERE pi.pack_id = ? AND p.track_stock = 1', [$l['pack_id']]) as $c) {
                    StockService::move('product', (int) $c['product_id'], -round($l['quantity'] * (float) $c['quantity'], 3), 'sale', null, 'sale', $saleId, 'Vente ' . $number . ' (pack)');
                }
            } elseif ($l['product_id'] && $bomAtSale) {
                foreach ($db->fetchAll('SELECT material_id, quantity, waste_percent FROM product_materials WHERE product_id = ?', [$l['product_id']]) as $b) {
                    $qty = round($l['quantity'] * (float) $b['quantity'] * (1 + (float) $b['waste_percent'] / 100), 3);
                    if ($qty > 0) {
                        // La matière a été physiquement consommée : on ne bloque pas la vente si le stock théorique est faux
                        StockService::move('material', (int) $b['material_id'], -$qty, 'consumption', null, 'sale', $saleId, 'Vente ' . $number, null, true);
                    }
                }
            }
        }
    }

    /* ==================================================================
       Mise en attente (brouillons / ventes suspendues)
       ================================================================== */

    public static function suspend(array $input): int
    {
        $db = Database::instance();
        $customerId = (int) ($input['customer_id'] ?? 0) ?: (int) Settings::get('walk_in_customer_id', 1);
        $lines = self::buildLines((array) ($input['items'] ?? []), $customerId);
        $discountType = (string) ($input['discount_type'] ?? 'none');
        $discountType = in_array($discountType, ['none', 'percent', 'amount'], true) ? $discountType : 'none';
        $calc = SaleCalculator::compute($lines, $discountType, (float) ($input['discount_value'] ?? 0));
        $session = CashService::openSession();
        return $db->transaction(function (Database $db) use ($input, $customerId, $calc, $discountType, $session): int {
            $draftId = (int) ($input['draft_id'] ?? 0);
            $quotationId = null;
            if ($draftId > 0) {
                $quotationId = $db->fetchColumn("SELECT quotation_id FROM sales WHERE id = ? AND status = 'suspended'", [$draftId]) ?: null;
                $db->query("DELETE FROM sales WHERE id = ? AND status = 'suspended'", [$draftId]);
            }
            $id = $db->insert('sales', [
                'quotation_id' => $quotationId,
                'cash_session_id' => $session['id'] ?? null, 'customer_id' => $customerId, 'user_id' => Auth::id(),
                'status' => 'suspended', 'sale_date' => date('Y-m-d H:i:s'), 'subtotal' => $calc['subtotal'],
                'discount_type' => $discountType, 'discount_value' => (float) ($input['discount_value'] ?? 0),
                'discount_total' => $calc['discount_total'], 'tax_total' => $calc['tax_total'], 'total' => $calc['total'],
                'suspend_label' => mb_substr(trim((string) ($input['label'] ?? '')), 0, 100) ?: null,
                'notes' => mb_substr(trim((string) ($input['notes'] ?? '')), 0, 2000) ?: null,
            ]);
            foreach ($calc['lines'] as $i => $l) {
                $db->insert('sale_items', [
                    'sale_id' => $id, 'product_id' => $l['product_id'], 'pack_id' => $l['pack_id'], 'description' => mb_substr($l['description'], 0, 500),
                    'width' => $l['width'], 'height' => $l['height'], 'dimension_unit' => $l['dimension_unit'],
                    // pour un travail au m², on garde le nombre d'exemplaires afin de restaurer le panier à l'identique
                    'quantity' => $l['area_pieces'] ?? $l['quantity'], 'unit_id' => $l['unit_id'],
                    'original_price' => $l['original_price'], 'unit_price' => $l['unit_price'], 'discount_percent' => $l['discount_percent'],
                    'tax_rate' => $l['tax_rate'], 'line_subtotal' => $l['line_subtotal'], 'line_tax' => $l['line_tax'], 'line_total' => $l['line_total'],
                    'unit_cost' => round($l['unit_cost'], 4), 'notes' => $l['notes'], 'sort_order' => $i,
                ]);
            }
            AuditLog::log('suspend', 'pos', 'sale', $id, 'Vente mise en attente : ' . money($calc['total']));
            return $id;
        });
    }

    /* ==================================================================
       Annulation (pendant la session de caisse)
       ================================================================== */

    public static function cancel(int $saleId, string $reason): void
    {
        if (!Auth::can('pos.cancel')) {
            throw new \DomainException('Vous n\'êtes pas autorisé à annuler une vente.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw new \DomainException('Indiquez le motif de l\'annulation.');
        }
        $db = Database::instance();
        $db->transaction(function (Database $db) use ($saleId, $reason): void {
            $sale = $db->fetch('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$saleId]);
            if (!$sale || $sale['status'] !== 'completed') {
                throw new \DomainException('Seule une vente terminée, sans retour, peut être annulée.');
            }
            if ($sale['invoice_id']) {
                throw new \DomainException('Cette vente a été facturée : émettez un avoir sur la facture.');
            }
            $session = CashService::openSession();
            if (!$session || (int) $sale['cash_session_id'] !== (int) $session['id']) {
                throw new \DomainException('Annulation possible uniquement pendant la session de caisse de la vente. Utilisez un retour.');
            }
            foreach ($db->fetchAll("SELECT * FROM payments WHERE sale_id = ? AND status = 'valid' AND direction = 'in'", [$saleId]) as $p) {
                $db->update('payments', ['status' => 'cancelled', 'cancelled_by' => Auth::id(), 'cancelled_at' => date('Y-m-d H:i:s'),
                    'cancel_reason' => mb_substr('Annulation vente : ' . $reason, 0, 255)], 'id = :id', ['id' => $p['id']]);
                CashService::movement((int) $session['id'], 'refund', -(float) $p['amount'], (int) $p['payment_method_id'], 'sale', $saleId, 'Annulation ' . $sale['number']);
            }
            // Retour en stock de tout ce qui avait été déduit pour cette vente
            foreach ($db->fetchAll("SELECT product_id, material_id, SUM(quantity) AS qty FROM stock_movements WHERE reference_type = 'sale' AND reference_id = ? GROUP BY product_id, material_id", [$saleId]) as $m) {
                if ((float) $m['qty'] < 0) {
                    StockService::move($m['product_id'] ? 'product' : 'material', (int) ($m['product_id'] ?: $m['material_id']), -(float) $m['qty'], 'return',
                        null, 'sale_cancel', $saleId, 'Annulation ' . $sale['number'], null, true);
                }
            }
            $points = (int) $db->fetchColumn("SELECT COALESCE(SUM(points), 0) FROM loyalty_transactions WHERE reference_type = 'sale' AND reference_id = ?", [$saleId]);
            if ($points !== 0) {
                $db->insert('loyalty_transactions', ['customer_id' => $sale['customer_id'], 'points' => -$points, 'reason' => 'Annulation ' . $sale['number'],
                    'reference_type' => 'sale_cancel', 'reference_id' => $saleId, 'user_id' => Auth::id()]);
                $db->query('UPDATE customers SET loyalty_points = GREATEST(0, loyalty_points - ?) WHERE id = ?', [$points, $sale['customer_id']]);
            }
            $db->update('sales', ['status' => 'cancelled', 'cancelled_by' => Auth::id(), 'cancelled_at' => date('Y-m-d H:i:s'),
                'cancel_reason' => mb_substr($reason, 0, 255)], 'id = :id', ['id' => $saleId]);
            AuditLog::log('cancel', 'pos', 'sale', $saleId, 'Vente ' . $sale['number'] . ' annulée : ' . $reason);
        });
    }

    /* ==================================================================
       Retour / remboursement
       ================================================================== */

    /**
     * @param array<int, float> $quantities sale_item_id => quantité retournée
     * @param array<int, bool>  $restock    sale_item_id => remise en stock
     */
    public static function processReturn(int $saleId, array $quantities, array $restock, int $methodId, string $reason): array
    {
        if (!Auth::can('pos.refund')) {
            throw new \DomainException('Vous n\'êtes pas autorisé à effectuer des retours.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw new \DomainException('Indiquez le motif du retour.');
        }
        $db = Database::instance();
        return $db->transaction(function (Database $db) use ($saleId, $quantities, $restock, $methodId, $reason): array {
            $sale = $db->fetch('SELECT s.*, c.is_walk_in FROM sales s JOIN customers c ON c.id = s.customer_id WHERE s.id = ? FOR UPDATE', [$saleId]);
            if (!$sale || !in_array($sale['status'], ['completed', 'partially_refunded'], true)) {
                throw new \DomainException('Cette vente ne peut pas faire l\'objet d\'un retour.');
            }
            if ($sale['invoice_id']) {
                throw new \DomainException('Cette vente a été facturée : le retour se fait par un avoir sur la facture.');
            }
            $method = $db->fetch('SELECT * FROM payment_methods WHERE id = ? AND is_active = 1', [$methodId]);
            if (!$method) {
                throw new \DomainException('Mode de remboursement invalide.');
            }
            if ((int) $method['is_credit'] === 1 && (int) $sale['is_walk_in'] === 1) {
                throw new \DomainException('Le client comptoir ne peut pas recevoir d\'avoir en compte : remboursez-le directement.');
            }
            $session = CashService::openSession();
            if (!$session && (int) $method['affects_cash'] === 1) {
                throw new \DomainException('La caisse est fermée : ouvrez-la pour rembourser en espèces.');
            }
            $items = [];
            foreach ($db->fetchAll('SELECT * FROM sale_items WHERE sale_id = ?', [$saleId]) as $it) {
                $items[(int) $it['id']] = $it;
            }
            $lines = [];
            $total = 0.0;
            $subtotal = 0.0;
            $tax = 0.0;
            foreach ($quantities as $itemId => $qty) {
                $qty = round((float) $qty, 3);
                if ($qty <= 0) {
                    continue;
                }
                $it = $items[(int) $itemId] ?? null;
                if (!$it) {
                    throw new \DomainException('Ligne de vente invalide.');
                }
                $available = round((float) $it['quantity'] - (float) $it['returned_quantity'], 3);
                if ($qty > $available + 0.0005) {
                    throw new \DomainException('Quantité retournée supérieure à la quantité vendue restante pour « ' . $it['description'] . ' ».');
                }
                $ratio = $qty / (float) $it['quantity'];
                $amount = round((float) $it['line_total'] * $ratio, 2);
                $lines[] = ['item' => $it, 'qty' => $qty, 'amount' => $amount, 'restock' => !empty($restock[$itemId])];
                $total += $amount;
                $subtotal += round((float) $it['line_subtotal'] * $ratio, 2);
                $tax += round((float) $it['line_tax'] * $ratio, 2);
            }
            if (!$lines) {
                throw new \DomainException('Sélectionnez au moins un article à retourner.');
            }
            $total = round($total, 2);
            if ((int) $method['is_credit'] !== 1) {
                // On ne rembourse pas plus que ce que le client a réellement payé
                $paidOut = (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE sale_id = ? AND direction = 'refund' AND status = 'valid'", [$saleId]);
                $refundable = round((float) $sale['paid_amount'] - $paidOut, 2);
                if ($total > $refundable + 0.004) {
                    throw new \DomainException('Le client n\'a réglé que ' . money(max(0, $refundable)) . ' sur cette vente : remboursez ce montant au maximum, le reste en « Crédit client ».');
                }
            }
            $number = DocumentNumber::next('sale_return');
            $returnId = $db->insert('sale_returns', [
                'number' => $number, 'sale_id' => $saleId, 'cash_session_id' => $session['id'] ?? null, 'user_id' => Auth::id(),
                'return_date' => date('Y-m-d H:i:s'), 'reason' => mb_substr($reason, 0, 255),
                'subtotal' => round($subtotal, 2), 'tax_total' => round($tax, 2), 'total' => $total, 'refund_method_id' => $methodId,
            ]);
            $allReturned = true;
            foreach ($lines as $l) {
                $it = $l['item'];
                $db->insert('sale_return_items', ['sale_return_id' => $returnId, 'sale_item_id' => $it['id'], 'quantity' => $l['qty'], 'amount' => $l['amount'], 'restock' => $l['restock'] ? 1 : 0]);
                $db->query('UPDATE sale_items SET returned_quantity = returned_quantity + ? WHERE id = ?', [$l['qty'], $it['id']]);
                if ($l['restock']) {
                    if ($it['product_id'] && (int) $db->fetchColumn('SELECT track_stock FROM products WHERE id = ?', [$it['product_id']]) === 1) {
                        StockService::move('product', (int) $it['product_id'], $l['qty'], 'return', null, 'sale_return', $returnId, 'Retour ' . $number);
                    } elseif ($it['pack_id']) {
                        foreach ($db->fetchAll('SELECT pi.product_id, pi.quantity FROM pack_items pi JOIN products p ON p.id = pi.product_id WHERE pi.pack_id = ? AND p.track_stock = 1', [$it['pack_id']]) as $c) {
                            StockService::move('product', (int) $c['product_id'], round($l['qty'] * (float) $c['quantity'], 3), 'return', null, 'sale_return', $returnId, 'Retour ' . $number . ' (pack)');
                        }
                    }
                }
            }
            foreach ($db->fetchAll('SELECT quantity, returned_quantity FROM sale_items WHERE sale_id = ?', [$saleId]) as $it) {
                if ((float) $it['returned_quantity'] + 0.0005 < (float) $it['quantity']) {
                    $allReturned = false;
                }
            }
            $db->update('sales', [
                'refunded_amount' => round((float) $sale['refunded_amount'] + $total, 2),
                'status' => $allReturned ? 'refunded' : 'partially_refunded',
            ], 'id = :id', ['id' => $saleId]);

            // Remboursement : versement au client, sauf « crédit client » (le montant réduit sa dette / crée un avoir en compte)
            if ((int) $method['is_credit'] !== 1) {
                $db->insert('payments', [
                    'number' => DocumentNumber::next('receipt'), 'direction' => 'refund', 'customer_id' => $sale['customer_id'],
                    'payment_method_id' => $methodId, 'cash_session_id' => $session['id'] ?? null, 'sale_id' => $saleId, 'amount' => $total,
                    'payment_date' => date('Y-m-d H:i:s'), 'notes' => 'Retour ' . $number, 'user_id' => Auth::id(),
                ]);
                if ($session) {
                    CashService::movement((int) $session['id'], 'refund', -$total, $methodId, 'sale_return', $returnId, 'Retour ' . $number);
                }
            }
            AuditLog::log('return', 'pos', 'sale_return', $returnId, 'Retour ' . $number . ' sur ' . $sale['number'] . ' : ' . money($total) . ' (' . $method['name'] . ') — ' . $reason);
            return ['id' => $returnId, 'number' => $number, 'total' => $total];
        });
    }
}
