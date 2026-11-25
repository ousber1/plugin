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
- **Coûts** : chaque ligne de document conserve `unit_cost` au moment de la vente → calcul du bénéfice réel, même si les coûts évoluent.
- **Machines** : coût horaire, électricité, maintenance par heure et amortissement linéaire estimé (`purchase_cost`, `salvage_value`, `useful_life_years`).
- **Calculateur** : aucune valeur de consommation n'est imposée ; les 51 paramètres sont créés à **0** et saisis par l'administrateur.

## Données initiales (seed)

6 rôles · 84 permissions · 18 unités · 5 taux de TVA marocains (20/14/10/7/0 %) · 6 modes de paiement · 23 catégories · 4 listes de prix · 4 groupes clients · client comptoir · 14 catégories de dépenses · 1 emplacement de stock · 1 caisse · 7 machines du parc (coûts à 0) · 51 paramètres de coûts · 23 séquences de numérotation · 12 modèles de documents · 11 types de notifications · ~80 paramètres applicatifs.

## Évolutions du schéma

Les modifications futures seront livrées sous forme de fichiers `database/migrations/AAAA_MM_JJ_description.sql`, appliqués par l'application et enregistrés dans `schema_migrations`.
