# HO CREATIVE — Schéma de base de données

**87 tables InnoDB, 232 clés étrangères**, `utf8mb4_unicode_ci`.
Sources : [`database/schema.sql`](../database/schema.sql) (structure) et [`database/seed.sql`](../database/seed.sql) (référentiels).

## Domaines et tables

| Domaine | Tables |
|---|---|
| **Sécurité** | `roles`, `permissions`, `role_permissions`, `users`, `user_permissions`, `password_resets`, `login_attempts`, `activity_logs` |
| **Configuration** | `settings`, `document_sequences`, `document_templates`, `notifications`, `notification_reads`, `notification_settings`, `backups`, `schema_migrations` |
| **Référentiels** | `units`, `taxes`, `payment_methods`, `categories`, `price_lists`, `customer_groups` |
| **CRM** | `customers`, `customer_files`, `customer_prices`, `loyalty_transactions` |
| **Fournisseurs & achats** | `suppliers`, `supplier_material_prices`, `purchase_orders`, `purchase_order_items`, `purchase_invoices`, `purchase_invoice_items`, `supplier_payments`, `supplier_returns`, `supplier_return_items` |
| **Catalogue** | `products`, `product_prices`, `product_materials` (nomenclature), `materials`, `packs`, `pack_items` |
| **Machines** | `machines`, `machine_maintenance`, `machine_downtimes`, `machine_usage`, `machine_consumables` |
| **Calculateur** | `cost_parameters` (valeurs saisies par l'administrateur), `cost_estimates` (calculs enregistrés, entrées + détail JSON) |
| **Devis & commandes** | `quotations`, `quotation_items`, `quotation_history`, `sales_orders`, `sales_order_items` |
| **POS & caisse** | `sales`, `sale_items`, `sale_returns`, `sale_return_items`, `cash_registers`, `cash_sessions`, `cash_movements` |
| **Facturation** | `invoices` (facture / acompte / solde), `invoice_items`, `invoice_deposits`, `credit_notes`, `credit_note_items`, `credit_note_allocations` |
| **Paiements** | `payments`, `payment_allocations`, `payment_reminders` |
| **Dépenses** | `expense_categories`, `expenses`, `recurring_expenses` |
| **Stock** | `stock_locations`, `stock_levels`, `stock_movements`, `inventory_counts`, `inventory_count_items` |
| **Production & design** | `production_orders`, `production_order_items`, `production_status_history`, `production_files`, `material_consumption`, `design_orders`, `design_revisions` |
| **Livraison** | `deliveries`, `delivery_items`, `delivery_status_history` |

## Règles de conception

- **Numérotation** : `document_sequences` (préfixe, format à jetons `{PREFIX} {YYYY} {YY} {MM} {SEQ}`, remise à zéro annuelle/mensuelle/jamais). Chaque table de document a une contrainte `UNIQUE` sur `number`. Les brouillons de factures/avoirs/ventes n'ont **pas** de numéro (colonne `NULL`) : le numéro est attribué à la validation pour garantir une suite continue.
- **Documents figés** : `customer_snapshot` / `business_snapshot` (JSON) conservent les coordonnées au moment de la validation ; `is_locked` interdit toute modification.
- **Facture d'acompte / de solde** : `invoices.invoice_type` + `invoice_deposits` (acomptes déduits d'une facture de solde).
- **Chiffre d'affaires** (défini une seule fois dans `App\Services\Analytics`) : tickets POS terminés non facturés + factures validées − avoirs validés, en HT. Un ticket converti en facture n'est compté qu'une fois. Une facture de solde porte des lignes négatives de déduction des acomptes, son total est donc net. Bénéfice estimé = CA HT − coût de revient des lignes vendues − dépenses HT (les achats de stock passent par les factures fournisseurs, pas par les dépenses).
- **Avoirs** : table dédiée, imputables sur facture (`credit_note_allocations`) ou remboursables (`payments.direction = 'refund'`).
- **Paiement mixte** : plusieurs lignes `payments` partageant le même `payment_group` ; ventilation sur factures via `payment_allocations` ; avance non affectée dans `unallocated_amount`.
- **Caisse** : une seule session ouverte par caisse, garantie par l'index unique `(cash_register_id, open_flag)` (open_flag = 1 si ouverte, NULL si clôturée). Montants de `cash_movements` signés.
- **Stock** : un article est un produit **ou** une matière (`product_id` / `material_id`). Quantités signées dans `stock_movements` (+ entrée / − sortie), `balance_after` pour l'historique, `unit_cost` pour la valorisation (coût moyen pondéré).
- **Stock (règles d'écriture)** : toute modification passe par `App\Services\StockService::move()` (verrou de ligne, refus du stock négatif, CMUP, alerte). Un inventaire applique l'écart *compté − théorique à l'ouverture*, pas une valeur absolue.
- **Compte client** (`App\Services\CustomerService`) : débit = factures validées + tickets non facturés + remboursements ; crédit = paiements + avoirs ; solde = débit − crédit.
- **Ventes POS** (`App\Services\SaleService`) : les montants de ligne enregistrés (`line_subtotal`, `line_tax`, `line_total`) intègrent la remise globale répartie au prorata ; `sales.subtotal` est le total HT avant remise globale. `paid_amount` = montant affecté à la vente (hors monnaie rendue, stockée dans `change_amount`) ; reste dû = `total − paid_amount`. Une vente en attente (`suspended`) n'a ni numéro, ni paiement, ni mouvement de stock.
- **Caisse** : `cash_movements` reçoit tous les encaissements de la session avec leur mode ; seuls les modes `affects_cash = 1` et les mouvements sans mode (dépôts, retraits) composent le solde théorique du tiroir.
- **Coûts** : chaque ligne de document conserve `unit_cost` au moment de la vente → calcul du bénéfice réel, même si les coûts évoluent.
- **Machines** : coût horaire, électricité, maintenance par heure et amortissement linéaire estimé (`purchase_cost`, `salvage_value`, `useful_life_years`).
- **Calculateur** : aucune valeur de consommation n'est imposée ; les 51 paramètres sont créés à **0** et saisis par l'administrateur.

## Données initiales (seed)

6 rôles · 84 permissions · 18 unités · 5 taux de TVA marocains (20/14/10/7/0 %) · 6 modes de paiement · 23 catégories · 4 listes de prix · 4 groupes clients · client comptoir · 14 catégories de dépenses · 1 emplacement de stock · 1 caisse · 7 machines du parc (coûts à 0) · 51 paramètres de coûts · 23 séquences de numérotation · 12 modèles de documents · 11 types de notifications · ~80 paramètres applicatifs.

## Évolutions du schéma

Les évolutions sont livrées sous forme de fichiers `database/migrations/AAAA_MM_JJ_NNN_description.sql`.
`App\Core\Migrator` les applique **automatiquement** à la première requête qui suit la mise à jour des fichiers
(verrou MySQL `GET_LOCK` contre les exécutions simultanées), puis les enregistre dans `schema_migrations` ;
l'empreinte `storage/cache/schema.version` évite toute requête supplémentaire ensuite. L'assistant d'installation
applique les mêmes migrations sur une nouvelle base. Les scripts sont rejouables (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`).

| Migration | Contenu |
|---|---|
| `2026_11_26_001_documents_partage_envoi` | `document_shares` (liens de consultation sécurisés : jeton, expiration, révocation, compteur de vues), `document_emails` (journal des envois e-mail / WhatsApp), paramètres `share_link_days`, `default_deposit_percent`, `quotation_intro`, `email_signature` |

## Règles comptables des documents (Phase 5)

- Une facture **brouillon** n'a pas de numéro et n'entre ni dans le chiffre d'affaires ni dans le solde client.
- La **validation** attribue le numéro (séquence sans trou, sous verrou), fige les coordonnées client et entreprise (`*_snapshot`) et verrouille la facture (`is_locked`).
- Une facture validée ne se modifie ni ne se supprime : elle se corrige par un **avoir**. L'annulation émet un avoir total ; la facture annulée et son avoir restent dans le chiffre d'affaires et le solde client, où ils se compensent.
- `paid_amount`, `credited_amount`, `balance_due` et le statut (payée, partielle, impayée, en retard) sont recalculés à partir de `payment_allocations` et `credit_note_allocations`.
- Facture de solde = lignes du devis + une ligne négative par taux de TVA et par facture d'acompte (`invoice_deposits`) ; la remise globale ne s'applique jamais aux lignes négatives.
- Un règlement non imputé reste une avance (`payments.unallocated_amount`) imputable plus tard ; un ticket facturé transmet ses règlements à la facture.
