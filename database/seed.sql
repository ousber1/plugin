-- =====================================================================
-- HO CREATIVE — Données de référence initiales
-- Uniquement des référentiels (rôles, permissions, unités, taxes,
-- catégories…). Aucune donnée commerciale fictive.
-- Les paramètres de coûts sont créés à 0 : l'administrateur doit
-- saisir ses propres valeurs réelles.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Rôles
-- ---------------------------------------------------------------------
INSERT INTO roles (id, code, name, description, is_system) VALUES
(1, 'admin',      'Administrateur', 'Accès complet à toutes les fonctionnalités', 1),
(2, 'manager',    'Manager',        'Gestion commerciale, financière et production', 1),
(3, 'cashier',    'Caissier',       'Point de vente, caisse, encaissements', 1),
(4, 'production', 'Production',     'Ordres de production, machines, consommations', 1),
(5, 'designer',   'Designer',       'Commandes de design et fichiers', 1),
(6, 'stock',      'Stock',          'Gestion des stocks, achats et inventaires', 1);

-- ---------------------------------------------------------------------
-- Permissions (module.action)
-- ---------------------------------------------------------------------
INSERT INTO permissions (code, module, name) VALUES
('dashboard.view',          'dashboard',     'Voir le tableau de bord'),
('dashboard.financial',     'dashboard',     'Voir les indicateurs financiers'),
('pos.access',              'pos',           'Accéder au point de vente'),
('pos.discount',            'pos',           'Appliquer des remises'),
('pos.price_override',      'pos',           'Modifier le prix de vente'),
('pos.below_min_price',     'pos',           'Vendre sous le prix minimum'),
('pos.refund',              'pos',           'Effectuer des retours et remboursements'),
('pos.cancel',              'pos',           'Annuler une vente'),
('pos.credit_sale',         'pos',           'Vendre à crédit'),
('cash.view',               'cash',          'Voir la caisse'),
('cash.open',               'cash',          'Ouvrir la caisse'),
('cash.close',              'cash',          'Clôturer la caisse'),
('cash.movements',          'cash',          'Dépôts, retraits et transferts'),
('customers.view',          'customers',     'Voir les clients'),
('customers.create',        'customers',     'Créer des clients'),
('customers.edit',          'customers',     'Modifier des clients'),
('customers.delete',        'customers',     'Supprimer des clients'),
('customers.export',        'customers',     'Exporter les clients'),
('customers.import',        'customers',     'Importer des clients'),
('customers.credit_limit',  'customers',     'Modifier la limite de crédit'),
('suppliers.view',          'suppliers',     'Voir les fournisseurs'),
('suppliers.create',        'suppliers',     'Créer des fournisseurs'),
('suppliers.edit',          'suppliers',     'Modifier des fournisseurs'),
('suppliers.delete',        'suppliers',     'Supprimer des fournisseurs'),
('products.view',           'products',      'Voir le catalogue'),
('products.create',         'products',      'Créer des produits et services'),
('products.edit',           'products',      'Modifier des produits et services'),
('products.delete',         'products',      'Supprimer des produits et services'),
('products.import',         'products',      'Importer le catalogue'),
('products.export',         'products',      'Exporter le catalogue'),
('products.view_cost',      'products',      'Voir les prix d''achat et coûts'),
('packs.view',              'packs',         'Voir les packs'),
('packs.manage',            'packs',         'Gérer les packs'),
('stock.view',              'stock',         'Voir le stock'),
('stock.movements',         'stock',         'Entrées et sorties de stock'),
('stock.adjust',            'stock',         'Ajuster le stock'),
('stock.inventory',         'stock',         'Réaliser des inventaires'),
('stock.materials',         'stock',         'Gérer les matières premières'),
('purchases.view',          'purchases',     'Voir les achats'),
('purchases.manage',        'purchases',     'Gérer commandes et factures fournisseurs'),
('purchases.pay',           'purchases',     'Payer les fournisseurs'),
('quotations.view',         'quotations',    'Voir les devis'),
('quotations.create',       'quotations',    'Créer des devis'),
('quotations.edit',         'quotations',    'Modifier des devis'),
('quotations.delete',       'quotations',    'Supprimer des devis'),
('quotations.send',         'quotations',    'Envoyer des devis'),
('quotations.convert',      'quotations',    'Convertir des devis'),
('invoices.view',           'invoices',      'Voir les factures'),
('invoices.create',         'invoices',      'Créer des factures'),
('invoices.edit',           'invoices',      'Modifier des factures brouillon'),
('invoices.validate',       'invoices',      'Valider des factures'),
('invoices.cancel',         'invoices',      'Annuler des factures'),
('invoices.credit_note',    'invoices',      'Émettre des avoirs'),
('payments.view',           'payments',      'Voir les paiements'),
('payments.create',         'payments',      'Enregistrer des paiements'),
('payments.cancel',         'payments',      'Annuler des paiements'),
('payments.refund',         'payments',      'Rembourser'),
('production.view',         'production',    'Voir la production'),
('production.create',       'production',    'Créer des ordres de production'),
('production.edit',         'production',    'Modifier des ordres de production'),
('production.status',       'production',    'Changer le statut de production'),
('production.delete',       'production',    'Supprimer des ordres de production'),
('design.view',             'design',        'Voir les commandes de design'),
('design.manage',           'design',        'Gérer les commandes de design'),
('deliveries.view',         'deliveries',    'Voir les livraisons'),
('deliveries.manage',       'deliveries',    'Gérer les livraisons et installations'),
('machines.view',           'machines',      'Voir les machines'),
('machines.manage',         'machines',      'Gérer les machines'),
('machines.maintenance',    'machines',      'Enregistrer la maintenance'),
('calculator.use',          'calculator',    'Utiliser le calculateur de coûts'),
('calculator.settings',     'calculator',    'Modifier les paramètres de coûts'),
('expenses.view',           'expenses',      'Voir les dépenses'),
('expenses.create',         'expenses',      'Créer des dépenses'),
('expenses.edit',           'expenses',      'Modifier des dépenses'),
('expenses.delete',         'expenses',      'Supprimer des dépenses'),
('reports.view',            'reports',       'Voir les rapports'),
('reports.financial',       'reports',       'Voir les rapports financiers'),
('reports.export',          'reports',       'Exporter les rapports'),
('users.view',              'users',         'Voir les utilisateurs'),
('users.manage',            'users',         'Gérer les utilisateurs et rôles'),
('settings.manage',         'settings',      'Gérer les paramètres'),
('backups.manage',          'backups',       'Gérer les sauvegardes'),
('logs.view',               'logs',          'Consulter le journal d''activité'),
('notifications.view',      'notifications', 'Voir les notifications');

-- Administrateur : toutes les permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Manager : tout sauf utilisateurs, paramètres, sauvegardes
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE code NOT IN ('users.manage','settings.manage','backups.manage');

-- Caissier
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN (
 'dashboard.view','pos.access','pos.discount','pos.credit_sale',
 'cash.view','cash.open','cash.close','cash.movements',
 'customers.view','customers.create','customers.edit',
 'products.view','packs.view','stock.view',
 'quotations.view','quotations.create','quotations.edit','quotations.send','quotations.convert',
 'invoices.view','invoices.create','invoices.validate',
 'payments.view','payments.create',
 'production.view','deliveries.view','calculator.use','expenses.view','expenses.create',
 'notifications.view');

-- Production
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE code IN (
 'dashboard.view','customers.view','products.view','packs.view',
 'stock.view','stock.movements','stock.materials',
 'production.view','production.create','production.edit','production.status',
 'design.view','deliveries.view','deliveries.manage',
 'machines.view','machines.manage','machines.maintenance',
 'calculator.use','notifications.view');

-- Designer
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE code IN (
 'dashboard.view','customers.view','products.view',
 'production.view','production.status','design.view','design.manage',
 'notifications.view');

-- Stock
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE code IN (
 'dashboard.view','products.view','products.create','products.edit','products.view_cost','products.import','products.export',
 'packs.view','stock.view','stock.movements','stock.adjust','stock.inventory','stock.materials',
 'suppliers.view','suppliers.create','suppliers.edit',
 'purchases.view','purchases.manage','machines.view','notifications.view');

-- ---------------------------------------------------------------------
-- Unités
-- ---------------------------------------------------------------------
INSERT INTO units (code, name, symbol, allows_decimal, is_area, sort_order) VALUES
('piece',   'Pièce',          'pc',   0, 0, 1),
('unite',   'Unité',          'u',    0, 0, 2),
('page',    'Page',           'p',    0, 0, 3),
('feuille', 'Feuille',        'f',    0, 0, 4),
('m',       'Mètre',          'm',    1, 0, 5),
('ml',      'Mètre linéaire', 'ml',   1, 0, 6),
('m2',      'Mètre carré',    'm²',   1, 1, 7),
('kg',      'Kilogramme',     'kg',   1, 0, 8),
('g',       'Gramme',         'g',    1, 0, 9),
('l',       'Litre',          'L',    1, 0, 10),
('h',       'Heure',          'h',    1, 0, 11),
('min',     'Minute',         'min',  1, 0, 12),
('pack',    'Pack',           'pack', 0, 0, 13),
('lot',     'Lot',            'lot',  0, 0, 14),
('boite',   'Boîte',          'bte',  0, 0, 15),
('rouleau', 'Rouleau',        'rl',   1, 0, 16),
('projet',  'Projet',         'proj', 0, 0, 17),
('service', 'Service',        'serv', 0, 0, 18);

-- ---------------------------------------------------------------------
-- Taxes (taux de TVA en vigueur au Maroc)
-- ---------------------------------------------------------------------
INSERT INTO taxes (id, name, rate, is_default) VALUES
(1, 'TVA 20 %', 20.000, 1),
(2, 'TVA 14 %', 14.000, 0),
(3, 'TVA 10 %', 10.000, 0),
(4, 'TVA 7 %',   7.000, 0),
(5, 'Exonéré (0 %)', 0.000, 0);

-- ---------------------------------------------------------------------
-- Modes de paiement
-- ---------------------------------------------------------------------
INSERT INTO payment_methods (code, name, affects_cash, requires_reference, is_credit, sort_order) VALUES
('cash',     'Espèces',           1, 0, 0, 1),
('card',     'Carte bancaire',    0, 0, 0, 2),
('transfer', 'Virement bancaire', 0, 1, 0, 3),
('cheque',   'Chèque',            0, 1, 0, 4),
('mobile',   'Paiement mobile',   0, 1, 0, 5),
('credit',   'Crédit client',     0, 0, 1, 6);

-- ---------------------------------------------------------------------
-- Catégories du catalogue
-- ---------------------------------------------------------------------
INSERT INTO categories (name, slug, type, color, icon, sort_order) VALUES
('Impression papier',      'impression-papier',      'both',    '#2563eb', 'bi-printer',          1),
('Bureautique',            'bureautique',            'both',    '#0ea5e9', 'bi-file-earmark-text',2),
('Grand format',           'grand-format',           'both',    '#7c3aed', 'bi-aspect-ratio',     3),
('Enseignes',              'enseignes',              'both',    '#db2777', 'bi-signpost-split',   4),
('Signalétique',           'signaletique',           'both',    '#e11d48', 'bi-sign-turn-right',  5),
('DTF',                    'dtf',                    'both',    '#f97316', 'bi-layers',           6),
('Textile',                'textile',                'both',    '#f59e0b', 'bi-person-bounding-box', 7),
('Sublimation',            'sublimation',            'both',    '#eab308', 'bi-cup-hot',          8),
('Cadeaux personnalisés',  'cadeaux-personnalises',  'product', '#84cc16', 'bi-gift',             9),
('Laser',                  'laser',                  'both',    '#22c55e', 'bi-lightning-charge', 10),
('Gravure',                'gravure',                'both',    '#10b981', 'bi-pen',              11),
('Découpe',                'decoupe',                'both',    '#14b8a6', 'bi-scissors',         12),
('Stickers',               'stickers',               'both',    '#06b6d4', 'bi-sticky',           13),
('Étiquettes',             'etiquettes',             'both',    '#3b82f6', 'bi-tags',             14),
('Packaging',              'packaging',              'both',    '#6366f1', 'bi-box-seam',         15),
('Événementiel',           'evenementiel',           'both',    '#8b5cf6', 'bi-balloon',          16),
('Car branding',           'car-branding',           'service', '#a855f7', 'bi-car-front',        17),
('Marketing digital',      'marketing-digital',      'service', '#d946ef', 'bi-megaphone',        18),
('Design graphique',       'design-graphique',       'service', '#ec4899', 'bi-palette',          19),
('Installation',           'installation',           'service', '#f43f5e', 'bi-tools',            20),
('Pose',                   'pose',                   'service', '#ef4444', 'bi-hammer',           21),
('Fournitures',            'fournitures',            'product', '#64748b', 'bi-basket',           22),
('Packs commerciaux',      'packs-commerciaux',      'both',    '#111111', 'bi-boxes',            23);

-- ---------------------------------------------------------------------
-- Listes de prix et groupes clients
-- ---------------------------------------------------------------------
INSERT INTO price_lists (id, code, name, description, is_default) VALUES
(1, 'detail',  'Détail',        'Clients particuliers et comptoir', 1),
(2, 'pro',     'Professionnel', 'Entreprises, commerces, agences', 0),
(3, 'gros',    'Gros',          'Revendeurs et grandes quantités', 0),
(4, 'contrat', 'Contrat',       'Clients sous contrat annuel', 0);

INSERT INTO customer_groups (name, price_list_id, discount_percent, description) VALUES
('Particuliers',    1, 0.00, 'Clients particuliers'),
('Professionnels',  2, 0.00, 'Entreprises et commerces'),
('Revendeurs',      3, 0.00, 'Revendeurs'),
('Contrats',        4, 0.00, 'Clients sous contrat');

-- ---------------------------------------------------------------------
-- Client comptoir (obligatoire pour le POS)
-- ---------------------------------------------------------------------
INSERT INTO customers (id, code, type, full_name, is_walk_in, group_id, price_list_id, notes) VALUES
(1, 'COMPTOIR', 'particulier', 'Client comptoir', 1, 1, 1, 'Client par défaut du point de vente');

-- ---------------------------------------------------------------------
-- Dépenses : catégories
-- ---------------------------------------------------------------------
INSERT INTO expense_categories (code, name, color) VALUES
('rent',         'Loyer',                 '#6366f1'),
('electricity',  'Électricité',           '#f59e0b'),
('internet',     'Internet & téléphone',  '#0ea5e9'),
('transport',    'Transport',             '#14b8a6'),
('maintenance',  'Entretien',             '#64748b'),
('repairs',      'Réparation machines',   '#ef4444'),
('materials',    'Matières premières',    '#8b5cf6'),
('salaries',     'Salaires',              '#22c55e'),
('marketing',    'Marketing',             '#ec4899'),
('taxes',        'Impôts et taxes',       '#dc2626'),
('office',       'Fournitures de bureau', '#84cc16'),
('delivery',     'Livraison',             '#06b6d4'),
('installation', 'Installation',          '#f97316'),
('misc',         'Divers',                '#9ca3af');

-- ---------------------------------------------------------------------
-- Stock & caisse
-- ---------------------------------------------------------------------
INSERT INTO stock_locations (id, name, is_default) VALUES (1, 'Atelier principal', 1);
INSERT INTO cash_registers (id, name, location) VALUES (1, 'Caisse principale', 'Comptoir');

-- ---------------------------------------------------------------------
-- Parc machines (coûts à 0 : à renseigner par l'administrateur)
-- ---------------------------------------------------------------------
INSERT INTO machines (code, name, type, brand, model, status) VALUES
('MAC-001', 'Ecosolvent XP600',      'ecosolvent',      NULL,             'XP600', 'operational'),
('MAC-002', 'Imprimante DTF',        'dtf',             NULL,             NULL,    'operational'),
('MAC-003', 'Konica Minolta C458',   'laser_printer',   'Konica Minolta', 'bizhub C458', 'operational'),
('MAC-004', 'Laser CO2',             'co2_laser',       NULL,             NULL,    'operational'),
('MAC-005', 'Plotter de découpe vinyle', 'vinyl_cutter', NULL,            NULL,    'operational'),
('MAC-006', 'Imprimante sublimation','sublimation',     NULL,             NULL,    'operational'),
('MAC-007', 'Équipement sérigraphie','screen_printing', NULL,             NULL,    'operational');

-- ---------------------------------------------------------------------
-- Paramètres du calculateur de coûts (valeurs à saisir : 0 par défaut)
-- ---------------------------------------------------------------------
INSERT INTO cost_parameters (calculator, param_key, label, unit_label, sort_order) VALUES
('general', 'electricity_kwh_price', 'Prix du kWh électrique',             'DH/kWh', 1),
('general', 'labor_hourly_rate',     'Coût horaire main d''œuvre',         'DH/h',   2),
('general', 'design_hourly_rate',    'Coût horaire design',                'DH/h',   3),
('general', 'installation_hourly_rate','Coût horaire installation',        'DH/h',   4),
('general', 'transport_km_cost',     'Coût transport par km',              'DH/km',  5),
('general', 'default_waste_percent', 'Taux de gâche par défaut',           '%',      6),
('general', 'default_margin_percent','Marge bénéficiaire par défaut',      '%',      7),

('bache', 'banner_price_m2',        'Prix de la bâche',                     'DH/m²',  1),
('bache', 'ink_cost_m2',            'Coût encre par m²',                    'DH/m²',  2),
('bache', 'print_speed_m2_h',       'Vitesse d''impression',                'm²/h',   3),
('bache', 'hem_cost_ml',            'Coût ourlet / soudure',                'DH/ml',  4),
('bache', 'eyelet_unit_cost',       'Coût unitaire œillet',                 'DH/u',   5),
('bache', 'eyelet_spacing_cm',      'Espacement des œillets',               'cm',     6),
('bache', 'installation_cost_m2',   'Coût de pose par m²',                  'DH/m²',  7),
('bache', 'waste_percent',          'Gâche bâche',                          '%',      8),

('vinyle', 'vinyl_price_m2',        'Prix du vinyle',                       'DH/m²',  1),
('vinyle', 'ink_cost_m2',           'Coût encre par m²',                    'DH/m²',  2),
('vinyle', 'print_speed_m2_h',      'Vitesse d''impression',                'm²/h',   3),
('vinyle', 'lamination_price_m2',   'Prix lamination',                      'DH/m²',  4),
('vinyle', 'cutting_cost_m2',       'Coût découpe par m²',                  'DH/m²',  5),
('vinyle', 'weeding_cost_m2',       'Coût échenillage par m²',              'DH/m²',  6),
('vinyle', 'installation_cost_m2',  'Coût de pose par m²',                  'DH/m²',  7),
('vinyle', 'waste_percent',         'Gâche vinyle',                         '%',      8),

('dtf', 'film_price_ml',            'Prix film DTF par mètre linéaire',     'DH/ml',  1),
('dtf', 'film_width_cm',            'Laize du film',                        'cm',     2),
('dtf', 'ink_cost_ml',              'Coût encre par mètre linéaire',        'DH/ml',  3),
('dtf', 'powder_cost_ml',           'Coût poudre par mètre linéaire',       'DH/ml',  4),
('dtf', 'print_speed_ml_h',         'Vitesse d''impression',                'ml/h',   5),
('dtf', 'press_seconds_unit',       'Temps de pressage par pièce',          's',      6),
('dtf', 'press_power_kw',           'Puissance presse',                     'kW',     7),
('dtf', 'waste_percent',            'Gâche DTF',                            '%',      8),

('sublimation', 'paper_cost_sheet', 'Coût papier sublimation par feuille',  'DH/f',   1),
('sublimation', 'ink_cost_sheet',   'Coût encre par feuille',               'DH/f',   2),
('sublimation', 'press_minutes_unit','Temps de pressage par pièce',         'min',    3),
('sublimation', 'press_power_kw',   'Puissance presse',                     'kW',     4),
('sublimation', 'labor_minutes_unit','Main d''œuvre par pièce',             'min',    5),
('sublimation', 'waste_percent',    'Gâche sublimation',                    '%',      6),

('laser', 'machine_hourly_cost',    'Coût horaire laser (si machine non liée)', 'DH/h', 1),
('laser', 'power_kw',               'Puissance laser',                      'kW',     2),
('laser', 'waste_percent',          'Gâche matière',                        '%',      3),
('laser', 'setup_minutes',          'Temps de préparation',                 'min',    4),

('konica', 'paper_a4_sheet',        'Coût feuille A4',                      'DH/f',   1),
('konica', 'paper_a3_sheet',        'Coût feuille A3',                      'DH/f',   2),
('konica', 'click_cost_color',      'Coût clic / page couleur',             'DH/p',   3),
('konica', 'click_cost_bw',         'Coût clic / page noir et blanc',       'DH/p',   4),
('konica', 'toner_cost_color',      'Coût toner / page couleur',            'DH/p',   5),
('konica', 'toner_cost_bw',         'Coût toner / page noir et blanc',      'DH/p',   6),
('konica', 'lamination_sheet',      'Coût plastification par feuille',      'DH/f',   7),
('konica', 'binding_unit',          'Coût reliure par document',            'DH/u',   8),
('konica', 'finishing_unit',        'Coût finition (coupe, pliage)',        'DH/u',   9),
('konica', 'waste_percent',         'Gâche papier',                         '%',      10);

-- ---------------------------------------------------------------------
-- Numérotation des documents (préfixes modifiables à l'installation)
-- ---------------------------------------------------------------------
INSERT INTO document_sequences (doc_type, label, prefix, format, padding, reset_policy) VALUES
('quotation',        'Devis',                       'DEV', '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('invoice',          'Facture',                     'FAC', '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('credit_note',      'Avoir',                       'AV',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('receipt',          'Reçu de paiement',            'REC', '{PREFIX}-{YYYY}-{SEQ}', 5, 'yearly'),
('sale',             'Ticket de vente',             'TK',  '{PREFIX}-{YYYY}-{SEQ}', 5, 'yearly'),
('sale_return',      'Retour de vente',             'RET', '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('sales_order',      'Bon de commande',             'BC',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('delivery',         'Bon de livraison',            'BL',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('production',       'Bon de production',           'BP',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('design',           'Commande de design',          'DSG', '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('stock_exit',       'Bon de sortie',               'BS',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('inventory',        'Inventaire',                  'INV', '{PREFIX}-{YYYY}-{SEQ}', 3, 'yearly'),
('purchase_order',   'Commande fournisseur',        'ACH', '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('purchase_invoice', 'Facture fournisseur',         'FF',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('supplier_payment', 'Bon de paiement fournisseur', 'PF',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('supplier_return',  'Retour fournisseur',          'RF',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('expense',          'Dépense',                     'DEP', '{PREFIX}-{YYYY}-{SEQ}', 5, 'yearly'),
('cash_session',     'Session de caisse',           'CS',  '{PREFIX}-{YYYY}-{SEQ}', 4, 'yearly'),
('customer',         'Code client',                 'CLI', '{PREFIX}-{SEQ}',        5, 'never'),
('supplier',         'Code fournisseur',            'FRS', '{PREFIX}-{SEQ}',        4, 'never'),
('product',          'Code produit',                'PRD', '{PREFIX}-{SEQ}',        5, 'never'),
('material',         'Code matière',                'MAT', '{PREFIX}-{SEQ}',        4, 'never'),
('pack',             'Code pack',                   'PCK', '{PREFIX}-{SEQ}',        3, 'never');

-- ---------------------------------------------------------------------
-- Modèles de documents par défaut
-- ---------------------------------------------------------------------
INSERT INTO document_templates (doc_type, name, title, terms, is_default) VALUES
('quotation',    'Devis standard',            'DEVIS',              'Devis valable jusqu''à la date indiquée. Toute commande implique l''acceptation de nos conditions générales. Un acompte peut être demandé avant le lancement de la production.', 1),
('invoice',      'Facture standard',          'FACTURE',            'Paiement à réception sauf accord contraire. Tout retard de paiement peut entraîner des pénalités conformément à la loi n° 69-21.', 1),
('deposit',      'Facture d''acompte',        'FACTURE D''ACOMPTE', 'Acompte à déduire de la facture de solde.', 1),
('balance',      'Facture de solde',          'FACTURE DE SOLDE',   'Facture de solde après déduction des acomptes versés.', 1),
('credit_note',  'Avoir standard',            'AVOIR',              'Avoir à imputer sur une prochaine facture ou à rembourser.', 1),
('receipt',      'Reçu de paiement',          'REÇU DE PAIEMENT',   NULL, 1),
('sales_order',  'Bon de commande',           'BON DE COMMANDE',    NULL, 1),
('delivery',     'Bon de livraison',          'BON DE LIVRAISON',   'Marchandise reçue en bon état.', 1),
('production',   'Bon de production',         'BON DE PRODUCTION',  NULL, 1),
('stock_exit',   'Bon de sortie',             'BON DE SORTIE',      NULL, 1),
('supplier_payment','Bon de paiement',        'BON DE PAIEMENT',    NULL, 1),
('statement',    'Relevé de compte',          'RELEVÉ DE COMPTE',   NULL, 1);

-- ---------------------------------------------------------------------
-- Paramètres des notifications
-- ---------------------------------------------------------------------
INSERT INTO notification_settings (type, label, is_enabled, days_before, notify_role_id) VALUES
('low_stock',            'Stock faible',                        1, NULL, 6),
('quotation_new',        'Nouveau devis',                       1, NULL, 2),
('quotation_expiring',   'Expiration de devis',                 1, 3,    2),
('invoice_overdue',      'Facture en retard',                   1, 0,    2),
('customer_payment',     'Paiement client reçu',                1, NULL, 2),
('production_deadline',  'Échéance de production',              1, 1,    4),
('production_delayed',   'Production en retard',                1, 0,    4),
('maintenance_due',      'Maintenance machine à prévoir',       1, 7,    4),
('cash_closing',         'Clôture de caisse',                   1, NULL, 2),
('supplier_debt',        'Dette fournisseur échue',             1, 3,    2),
('customer_debt',        'Dette client (limite de crédit)',     1, NULL, 2);

-- ---------------------------------------------------------------------
-- Paramètres applicatifs par défaut
-- (les informations de l'entreprise sont saisies dans l'installateur)
-- ---------------------------------------------------------------------
INSERT INTO settings (setting_group, setting_key, setting_value, value_type) VALUES
('business', 'business_name',        'HO CREATIVE', 'string'),
('business', 'business_activity',    'Impression • Publicité • Design • Communication', 'string'),
('business', 'business_address',     '', 'string'),
('business', 'business_city',        '', 'string'),
('business', 'business_country',     'Maroc', 'string'),
('business', 'business_phone',       '', 'string'),
('business', 'business_whatsapp',    '', 'string'),
('business', 'business_email',       '', 'string'),
('business', 'business_website',     '', 'string'),
('business', 'business_ice',         '', 'string'),
('business', 'business_if',          '', 'string'),
('business', 'business_rc',          '', 'string'),
('business', 'business_patente',     '', 'string'),
('business', 'business_cnss',        '', 'string'),
('business', 'business_capital',     '', 'string'),
('business', 'business_logo',        '', 'string'),
('business', 'bank_name',            '', 'string'),
('business', 'bank_rib',             '', 'string'),
('business', 'bank_swift',           '', 'string'),

('currency', 'currency_code',        'MAD', 'string'),
('currency', 'currency_symbol',      'DH',  'string'),
('currency', 'currency_position',    'after', 'string'),
('currency', 'currency_decimals',    '2',   'int'),
('currency', 'decimal_separator',    ',',   'string'),
('currency', 'thousands_separator',  ' ',   'string'),

('tax',      'default_tax_id',       '1',   'int'),
('tax',      'prices_include_tax',   '0',   'bool'),
('tax',      'tax_enabled',          '1',   'bool'),

('documents','quotation_validity_days','30', 'int'),
('documents','invoice_due_days',     '30',  'int'),
('documents','default_payment_terms','Paiement à 30 jours à compter de la date de facture.', 'text'),
('documents','quotation_footer',     '',    'text'),
('documents','invoice_footer',       '',    'text'),
('documents','default_discount_percent','0', 'decimal'),
('documents','amount_in_words',      '1',   'bool'),

('pos',      'walk_in_customer_id',  '1',   'int'),
('pos',      'require_cash_session', '1',   'bool'),
('pos',      'default_cash_register_id','1','int'),
('pos',      'receipt_width',        '80',  'int'),
('pos',      'receipt_header',       '',    'text'),
('pos',      'receipt_footer',       'Merci pour votre confiance !', 'text'),
('pos',      'auto_print_receipt',   '0',   'bool'),
('pos',      'allow_negative_stock', '0',   'bool'),
('pos',      'max_discount_percent', '20',  'decimal'),

('stock',    'default_location_id',  '1',   'int'),
('stock',    'auto_deduct_sales',    '1',   'bool'),
('stock',    'auto_deduct_production','1',  'bool'),
('stock',    'low_stock_alerts',     '1',   'bool'),
('stock',    'valuation_method',     'weighted_average', 'string'),

('production','default_deadline_days','3',  'int'),
('production','auto_create_from_sale','0',  'bool'),

('pdf',      'paper_size',           'A4',  'string'),
('pdf',      'show_qr_code',         '1',   'bool'),
('pdf',      'show_signature',       '1',   'bool'),
('pdf',      'show_stamp',           '1',   'bool'),
('pdf',      'stamp_image',          '',    'string'),
('pdf',      'signature_image',      '',    'string'),

('appearance','default_theme',       'auto', 'string'),
('appearance','accent_color',        '#ff3d00', 'string'),
('appearance','items_per_page',      '25',  'int'),

('security', 'session_lifetime_minutes','120','int'),
('security', 'max_login_attempts',   '5',   'int'),
('security', 'lockout_minutes',      '15',  'int'),
('security', 'password_min_length',  '10',  'int'),

('loyalty',  'loyalty_enabled',      '0',   'bool'),
('loyalty',  'points_per_amount',    '0',   'decimal'),

('backup',   'backup_retention',     '10',  'int'),
('backup',   'auto_backup_enabled',  '0',   'bool'),
('backup',   'auto_backup_frequency','daily','string'),

('mail',     'mail_from_address',    '',    'string'),
('mail',     'mail_from_name',       'HO CREATIVE', 'string'),
('mail',     'smtp_host',            '',    'string'),
('mail',     'smtp_port',            '587', 'int'),
('mail',     'smtp_username',        '',    'string'),
('mail',     'smtp_password',        '',    'string'),
('mail',     'smtp_encryption',      'tls', 'string'),

('system',   'app_language',         'fr',  'string'),
('system',   'timezone',             'Africa/Casablanca', 'string'),
('system',   'date_format',          'd/m/Y', 'string');

INSERT INTO schema_migrations (version, description) VALUES ('1.0.0', 'Schéma initial HO CREATIVE POS & ERP');
