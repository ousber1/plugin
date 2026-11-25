-- =====================================================================
-- HO CREATIVE — POS & ERP
-- Schéma de base de données MySQL 5.7+ / MariaDB 10.3+
-- Moteur InnoDB, jeu de caractères utf8mb4
-- Ce fichier est exécuté par l'installateur (install/).
-- Chaque instruction se termine par un point-virgule en fin de ligne.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. SÉCURITÉ : rôles, permissions, utilisateurs
-- ---------------------------------------------------------------------

CREATE TABLE roles (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(50)  NOT NULL,
  name          VARCHAR(100) NOT NULL,
  description   VARCHAR(255) NULL,
  is_system     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(100) NOT NULL,
  module        VARCHAR(50)  NOT NULL,
  name          VARCHAR(150) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_code (code),
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id        INT UNSIGNED NOT NULL,
  permission_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_rp_permission (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id              INT UNSIGNED NOT NULL,
  full_name            VARCHAR(150) NOT NULL,
  username             VARCHAR(60)  NOT NULL,
  email                VARCHAR(190) NOT NULL,
  phone                VARCHAR(30)  NULL,
  password_hash        VARCHAR(255) NOT NULL,
  avatar               VARCHAR(255) NULL,
  theme                ENUM('light','dark','auto') NOT NULL DEFAULT 'auto',
  is_active            TINYINT(1)   NOT NULL DEFAULT 1,
  failed_attempts      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until         DATETIME     NULL,
  last_login_at        DATETIME     NULL,
  last_login_ip        VARCHAR(45)  NULL,
  password_changed_at  DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at           DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Surcharges de permissions par utilisateur (autoriser / refuser)
CREATE TABLE user_permissions (
  user_id        INT UNSIGNED NOT NULL,
  permission_id  INT UNSIGNED NOT NULL,
  allowed        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, permission_id),
  KEY idx_up_permission (permission_id),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_up_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pr_token (token_hash),
  KEY idx_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifier  VARCHAR(190) NOT NULL,
  ip_address  VARCHAR(45)  NOT NULL,
  success     TINYINT(1)   NOT NULL DEFAULT 0,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_identifier (identifier, created_at),
  KEY idx_la_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NULL,
  action       VARCHAR(50)  NOT NULL,
  module       VARCHAR(50)  NOT NULL,
  entity_type  VARCHAR(50)  NULL,
  entity_id    BIGINT UNSIGNED NULL,
  description  VARCHAR(500) NULL,
  old_values   JSON NULL,
  new_values   JSON NULL,
  ip_address   VARCHAR(45)  NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_al_user (user_id),
  KEY idx_al_module (module, created_at),
  KEY idx_al_entity (entity_type, entity_id),
  CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. CONFIGURATION : paramètres, numérotation, modèles, notifications
-- ---------------------------------------------------------------------

CREATE TABLE settings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_group  VARCHAR(50)  NOT NULL,
  setting_key    VARCHAR(100) NOT NULL,
  setting_value  TEXT NULL,
  value_type     ENUM('string','int','decimal','bool','json','text') NOT NULL DEFAULT 'string',
  updated_at     DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (setting_key),
  KEY idx_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Numérotation unique et configurable des documents
-- format : jetons {PREFIX} {YYYY} {YY} {MM} {SEQ}  ex. {PREFIX}-{YYYY}-{SEQ} => FAC-2026-0001
CREATE TABLE document_sequences (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type        VARCHAR(40)  NOT NULL,
  label           VARCHAR(100) NOT NULL,
  prefix          VARCHAR(20)  NOT NULL,
  format          VARCHAR(60)  NOT NULL DEFAULT '{PREFIX}-{YYYY}-{SEQ}',
  padding         TINYINT UNSIGNED NOT NULL DEFAULT 4,
  next_number     INT UNSIGNED NOT NULL DEFAULT 1,
  reset_policy    ENUM('never','yearly','monthly') NOT NULL DEFAULT 'yearly',
  current_period  VARCHAR(7)   NULL,
  updated_at      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ds_type (doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_templates (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type     VARCHAR(40)  NOT NULL,
  name         VARCHAR(100) NOT NULL,
  title        VARCHAR(100) NOT NULL,
  header_text  TEXT NULL,
  footer_text  TEXT NULL,
  terms        TEXT NULL,
  accent_color VARCHAR(7)   NOT NULL DEFAULT '#111111',
  show_logo    TINYINT(1)   NOT NULL DEFAULT 1,
  show_qr      TINYINT(1)   NOT NULL DEFAULT 1,
  show_signature TINYINT(1) NOT NULL DEFAULT 1,
  show_stamp   TINYINT(1)   NOT NULL DEFAULT 1,
  is_default   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dt_type (doc_type, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NULL COMMENT 'NULL = diffusée selon role_id ou à tous',
  role_id      INT UNSIGNED NULL,
  type         VARCHAR(50)  NOT NULL,
  severity     ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  title        VARCHAR(190) NOT NULL,
  message      VARCHAR(500) NULL,
  link         VARCHAR(255) NULL,
  entity_type  VARCHAR(50)  NULL,
  entity_id    BIGINT UNSIGNED NULL,
  dedup_key    VARCHAR(190) NULL COMMENT 'évite les doublons (ex. low_stock:material:12)',
  read_at      DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, read_at),
  KEY idx_notif_role (role_id, read_at),
  KEY idx_notif_dedup (dedup_key),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lecture individuelle des notifications diffusées
CREATE TABLE notification_reads (
  notification_id BIGINT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  read_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id, user_id),
  KEY idx_nr_user (user_id),
  CONSTRAINT fk_nr_notif FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE,
  CONSTRAINT fk_nr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_settings (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type         VARCHAR(50)  NOT NULL,
  label        VARCHAR(150) NOT NULL,
  is_enabled   TINYINT(1)   NOT NULL DEFAULT 1,
  days_before  SMALLINT UNSIGNED NULL COMMENT 'délai d''anticipation pour échéances',
  notify_role_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ns_type (type),
  CONSTRAINT fk_ns_role FOREIGN KEY (notify_role_id) REFERENCES roles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backups (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename    VARCHAR(190) NOT NULL,
  file_size   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  checksum    CHAR(64)     NULL,
  type        ENUM('manual','automatic','pre_restore') NOT NULL DEFAULT 'manual',
  status      ENUM('success','failed','restored') NOT NULL DEFAULT 'success',
  notes       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_backups_file (filename),
  CONSTRAINT fk_backups_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. RÉFÉRENTIELS : unités, taxes, modes de paiement, catégories, tarifs
-- ---------------------------------------------------------------------

CREATE TABLE units (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,
  name       VARCHAR(60)  NOT NULL,
  symbol     VARCHAR(15)  NOT NULL,
  allows_decimal TINYINT(1) NOT NULL DEFAULT 0,
  is_area    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'unité calculée largeur x hauteur',
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_units_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE taxes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(60)  NOT NULL,
  rate        DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_methods (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(30)  NOT NULL,
  name          VARCHAR(60)  NOT NULL,
  affects_cash  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = mouvement de caisse physique',
  requires_reference TINYINT(1) NOT NULL DEFAULT 0,
  is_credit     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'vente à crédit client',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order    SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pm_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id   INT UNSIGNED NULL,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(120) NOT NULL,
  type        ENUM('product','service','both') NOT NULL DEFAULT 'both',
  color       VARCHAR(7)   NOT NULL DEFAULT '#6c757d',
  icon        VARCHAR(50)  NULL,
  sort_order  SMALLINT     NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_parent (parent_id),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_lists (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(30)  NOT NULL,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pl_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_groups (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(100) NOT NULL,
  price_list_id    INT UNSIGNED NULL,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  description      VARCHAR(255) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cg_name (name),
  CONSTRAINT fk_cg_pricelist FOREIGN KEY (price_list_id) REFERENCES price_lists (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. CRM : clients
-- ---------------------------------------------------------------------

CREATE TABLE customers (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code            VARCHAR(30)  NOT NULL,
  type            ENUM('particulier','entreprise','association','administration','restaurant','commerce','agence','ecole') NOT NULL DEFAULT 'particulier',
  full_name       VARCHAR(150) NOT NULL,
  company_name    VARCHAR(190) NULL,
  phone           VARCHAR(30)  NULL,
  whatsapp        VARCHAR(30)  NULL,
  email           VARCHAR(190) NULL,
  address         VARCHAR(255) NULL,
  city            VARCHAR(100) NULL,
  ice             VARCHAR(20)  NULL COMMENT 'Identifiant Commun de l''Entreprise (15 chiffres)',
  if_number       VARCHAR(20)  NULL COMMENT 'Identifiant Fiscal',
  rc              VARCHAR(30)  NULL COMMENT 'Registre de Commerce',
  cin             VARCHAR(20)  NULL,
  group_id        INT UNSIGNED NULL,
  price_list_id   INT UNSIGNED NULL,
  credit_limit    DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '0 = aucun crédit autorisé',
  loyalty_points  INT NOT NULL DEFAULT 0,
  notes           TEXT NULL,
  is_walk_in      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'client comptoir',
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_code (code),
  KEY idx_customers_name (full_name),
  KEY idx_customers_company (company_name),
  KEY idx_customers_phone (phone),
  KEY idx_customers_ice (ice),
  KEY idx_customers_group (group_id),
  CONSTRAINT fk_customers_group FOREIGN KEY (group_id) REFERENCES customer_groups (id) ON DELETE SET NULL,
  CONSTRAINT fk_customers_pricelist FOREIGN KEY (price_list_id) REFERENCES price_lists (id) ON DELETE SET NULL,
  CONSTRAINT fk_customers_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_files (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id    INT UNSIGNED NOT NULL,
  original_name  VARCHAR(255) NOT NULL,
  stored_name    VARCHAR(255) NOT NULL,
  mime_type      VARCHAR(100) NOT NULL,
  file_size      INT UNSIGNED NOT NULL DEFAULT 0,
  description    VARCHAR(255) NULL,
  uploaded_by    INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cf_customer (customer_id),
  CONSTRAINT fk_cf_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_cf_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loyalty_transactions (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id     INT UNSIGNED NOT NULL,
  points          INT NOT NULL COMMENT 'positif = gagné, négatif = utilisé',
  reason          VARCHAR(190) NOT NULL,
  reference_type  VARCHAR(40)  NULL,
  reference_id    BIGINT UNSIGNED NULL,
  user_id         INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lt_customer (customer_id),
  CONSTRAINT fk_lt_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_lt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. FOURNISSEURS
-- ---------------------------------------------------------------------

CREATE TABLE suppliers (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(30)  NOT NULL,
  name         VARCHAR(150) NOT NULL,
  company      VARCHAR(190) NULL,
  phone        VARCHAR(30)  NULL,
  whatsapp     VARCHAR(30)  NULL,
  email        VARCHAR(190) NULL,
  address      VARCHAR(255) NULL,
  city         VARCHAR(100) NULL,
  ice          VARCHAR(20)  NULL,
  if_number    VARCHAR(20)  NULL,
  rc           VARCHAR(30)  NULL,
  payment_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  notes        TEXT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_suppliers_code (code),
  KEY idx_suppliers_name (name),
  CONSTRAINT fk_suppliers_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. MACHINES
-- ---------------------------------------------------------------------

CREATE TABLE machines (
  id                         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code                       VARCHAR(30)  NOT NULL,
  name                       VARCHAR(150) NOT NULL,
  type                       ENUM('ecosolvent','dtf','laser_printer','co2_laser','vinyl_cutter','sublimation','heat_press','screen_printing','finishing','other') NOT NULL DEFAULT 'other',
  brand                      VARCHAR(100) NULL,
  model                      VARCHAR(100) NULL,
  serial_number              VARCHAR(100) NULL,
  purchase_date              DATE NULL,
  purchase_cost              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  salvage_value              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  useful_life_years          DECIMAL(5,2)  NOT NULL DEFAULT 0.00 COMMENT 'pour amortissement linéaire estimé',
  supplier_id                INT UNSIGNED NULL,
  hourly_cost                DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'coût horaire machine (hors électricité)',
  power_kw                   DECIMAL(8,3)  NOT NULL DEFAULT 0.000 COMMENT 'puissance moyenne en kW',
  electricity_cost_per_hour  DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'calculé ou saisi',
  maintenance_cost_per_hour  DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  maintenance_frequency_days SMALLINT UNSIGNED NULL,
  last_maintenance_at        DATE NULL,
  next_maintenance_at        DATE NULL,
  status                     ENUM('operational','maintenance','down','retired') NOT NULL DEFAULT 'operational',
  maintenance_notes          TEXT NULL,
  notes                      TEXT NULL,
  is_active                  TINYINT(1) NOT NULL DEFAULT 1,
  created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                 DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at                 DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_machines_code (code),
  KEY idx_machines_status (status),
  CONSTRAINT fk_machines_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE machine_maintenance (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  machine_id      INT UNSIGNED NOT NULL,
  type            ENUM('preventive','corrective','cleaning','calibration','part_replacement') NOT NULL DEFAULT 'preventive',
  maintenance_date DATE NOT NULL,
  description     TEXT NOT NULL,
  cost            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  downtime_hours  DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  performed_by    VARCHAR(150)  NULL,
  supplier_id     INT UNSIGNED  NULL,
  expense_id      INT UNSIGNED  NULL,
  next_due_date   DATE NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mm_machine (machine_id, maintenance_date),
  CONSTRAINT fk_mm_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE CASCADE,
  CONSTRAINT fk_mm_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
  CONSTRAINT fk_mm_expense FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE SET NULL,
  CONSTRAINT fk_mm_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE machine_downtimes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  machine_id   INT UNSIGNED NOT NULL,
  started_at   DATETIME NOT NULL,
  ended_at     DATETIME NULL,
  reason       VARCHAR(255) NOT NULL,
  maintenance_id INT UNSIGNED NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_md_machine (machine_id, started_at),
  CONSTRAINT fk_md_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE CASCADE,
  CONSTRAINT fk_md_maint FOREIGN KEY (maintenance_id) REFERENCES machine_maintenance (id) ON DELETE SET NULL,
  CONSTRAINT fk_md_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. CATALOGUE : matières, produits, services, tarifs, packs
-- ---------------------------------------------------------------------

CREATE TABLE materials (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code            VARCHAR(40)  NOT NULL,
  name            VARCHAR(190) NOT NULL,
  material_type   ENUM('paper','vinyl','banner','ink','toner','dtf_film','dtf_powder','mdf','acrylic','sublimation_blank','textile','packaging','office_supply','spare_part','lamination','other') NOT NULL DEFAULT 'other',
  unit_id         INT UNSIGNED NOT NULL,
  unit_cost       DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'coût moyen pondéré par unité',
  last_purchase_cost DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  width_cm        DECIMAL(10,2) NULL COMMENT 'laize des rouleaux',
  length_m        DECIMAL(10,2) NULL COMMENT 'longueur des rouleaux',
  stock_quantity  DECIMAL(15,3) NOT NULL DEFAULT 0.000 COMMENT 'total toutes localisations (cache maintenu par StockService)',
  min_stock       DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  supplier_id     INT UNSIGNED NULL,
  barcode         VARCHAR(60)  NULL,
  notes           TEXT NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_materials_code (code),
  KEY idx_materials_type (material_type),
  KEY idx_materials_barcode (barcode),
  CONSTRAINT fk_materials_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE RESTRICT,
  CONSTRAINT fk_materials_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_material_prices (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id  INT UNSIGNED NOT NULL,
  material_id  INT UNSIGNED NOT NULL,
  price        DECIMAL(15,4) NOT NULL,
  min_quantity DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  lead_time_days SMALLINT UNSIGNED NULL,
  valid_from   DATE NOT NULL,
  notes        VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_smp_material (material_id, valid_from),
  KEY idx_smp_supplier (supplier_id),
  CONSTRAINT fk_smp_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE,
  CONSTRAINT fk_smp_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE machine_consumables (
  machine_id   INT UNSIGNED NOT NULL,
  material_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (machine_id, material_id),
  KEY idx_mc_material (material_id),
  CONSTRAINT fk_mc_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE CASCADE,
  CONSTRAINT fk_mc_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code               VARCHAR(40)  NOT NULL,
  barcode            VARCHAR(60)  NULL,
  name               VARCHAR(190) NOT NULL,
  name_fr            VARCHAR(190) NULL,
  product_type       ENUM('product','service','print_job','design','installation','digital','finished_good') NOT NULL DEFAULT 'product',
  category_id        INT UNSIGNED NULL,
  description        TEXT NULL,
  unit_id            INT UNSIGNED NOT NULL,
  purchase_price     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  selling_price      DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'prix de vente détail HT',
  min_selling_price  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  estimated_cost     DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'coût de production estimé',
  margin_percent     DECIMAL(7,2)  NOT NULL DEFAULT 0.00,
  tax_id             INT UNSIGNED NULL,
  track_stock        TINYINT(1)   NOT NULL DEFAULT 0,
  stock_quantity     DECIMAL(15,3) NOT NULL DEFAULT 0.000 COMMENT 'cache maintenu par StockService',
  min_stock          DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  image              VARCHAR(255) NULL,
  machine_id         INT UNSIGNED NULL,
  material_id        INT UNSIGNED NULL COMMENT 'matière principale',
  supplier_id        INT UNSIGNED NULL,
  allow_price_override TINYINT(1) NOT NULL DEFAULT 1,
  is_quick           TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'bouton rapide POS',
  is_active          TINYINT(1)   NOT NULL DEFAULT 1,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_code (code),
  KEY idx_products_barcode (barcode),
  KEY idx_products_name (name),
  KEY idx_products_category (category_id),
  KEY idx_products_type (product_type, is_active),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE RESTRICT,
  CONSTRAINT fk_products_tax FOREIGN KEY (tax_id) REFERENCES taxes (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
  CONSTRAINT fk_products_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prix par liste tarifaire (Détail, Professionnel, Gros, Contrat)
CREATE TABLE product_prices (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id     INT UNSIGNED NOT NULL,
  price_list_id  INT UNSIGNED NOT NULL,
  price          DECIMAL(15,2) NOT NULL,
  min_quantity   DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pp (product_id, price_list_id, min_quantity),
  KEY idx_pp_list (price_list_id),
  CONSTRAINT fk_pp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_list FOREIGN KEY (price_list_id) REFERENCES price_lists (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prix spécifiques négociés par client
CREATE TABLE customer_prices (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id  INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  price        DECIMAL(15,2) NOT NULL,
  valid_until  DATE NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cp (customer_id, product_id),
  KEY idx_cp_product (product_id),
  CONSTRAINT fk_cp_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nomenclature : matières consommées par unité de produit vendu
CREATE TABLE product_materials (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id     INT UNSIGNED NOT NULL,
  material_id    INT UNSIGNED NOT NULL,
  quantity       DECIMAL(15,4) NOT NULL COMMENT 'quantité de matière par unité de produit',
  waste_percent  DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pm (product_id, material_id),
  KEY idx_pmat_material (material_id),
  CONSTRAINT fk_pmat_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pmat_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packs (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code             VARCHAR(40)  NOT NULL,
  name             VARCHAR(190) NOT NULL,
  description      TEXT NULL,
  price            DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'prix du pack HT',
  discount_percent DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  tax_id           INT UNSIGNED NULL,
  image            VARCHAR(255) NULL,
  available_from   DATE NULL,
  available_until  DATE NULL,
  is_custom        TINYINT(1)   NOT NULL DEFAULT 0,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at       DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_packs_code (code),
  CONSTRAINT fk_packs_tax FOREIGN KEY (tax_id) REFERENCES taxes (id) ON DELETE SET NULL,
  CONSTRAINT fk_packs_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pack_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pack_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  quantity    DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  description VARCHAR(255) NULL,
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pi_pack (pack_id),
  KEY idx_pi_product (product_id),
  CONSTRAINT fk_pi_pack FOREIGN KEY (pack_id) REFERENCES packs (id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. CALCULATEUR DE COÛTS D'IMPRESSION
-- ---------------------------------------------------------------------

-- Paramètres modifiables par l'administrateur (aucune valeur de consommation imposée)
CREATE TABLE cost_parameters (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  calculator   ENUM('general','bache','vinyle','dtf','sublimation','laser','konica') NOT NULL,
  param_key    VARCHAR(60)  NOT NULL,
  label        VARCHAR(150) NOT NULL,
  value        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  unit_label   VARCHAR(40)  NULL,
  material_id  INT UNSIGNED NULL COMMENT 'si lié, le coût peut être repris du stock',
  machine_id   INT UNSIGNED NULL,
  description  VARCHAR(255) NULL,
  sort_order   SMALLINT NOT NULL DEFAULT 0,
  updated_at   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cost_param (calculator, param_key),
  CONSTRAINT fk_cpar_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE SET NULL,
  CONSTRAINT fk_cpar_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cost_estimates (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  calculator      VARCHAR(30)  NOT NULL,
  title           VARCHAR(190) NOT NULL,
  customer_id     INT UNSIGNED NULL,
  product_id      INT UNSIGNED NULL,
  machine_id      INT UNSIGNED NULL,
  quantity        DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  inputs          JSON NOT NULL,
  breakdown       JSON NOT NULL,
  total_cost      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  unit_cost       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  margin_percent  DECIMAL(7,2)  NOT NULL DEFAULT 0.00,
  selling_price   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  production_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  quotation_id    INT UNSIGNED NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ce_calc (calculator, created_at),
  KEY idx_ce_customer (customer_id),
  CONSTRAINT fk_ce_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
  CONSTRAINT fk_ce_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_ce_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL,
  CONSTRAINT fk_ce_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE SET NULL,
  CONSTRAINT fk_ce_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. DEVIS
-- ---------------------------------------------------------------------

CREATE TABLE quotations (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number             VARCHAR(40)  NOT NULL,
  customer_id        INT UNSIGNED NOT NULL,
  user_id            INT UNSIGNED NULL,
  status             ENUM('draft','sent','pending','accepted','refused','expired','converted') NOT NULL DEFAULT 'draft',
  title              VARCHAR(190) NULL,
  issue_date         DATE NOT NULL,
  valid_until        DATE NULL,
  delivery_date      DATE NULL,
  production_days    SMALLINT UNSIGNED NULL,
  subtotal           DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'total lignes HT après remises lignes',
  discount_type      ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
  discount_value     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_total     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  design_fees        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  installation_fees  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  transport_fees     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total              DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'TTC',
  cost_total         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  payment_terms      TEXT NULL,
  notes              TEXT NULL,
  internal_notes     TEXT NULL,
  customer_snapshot  JSON NULL,
  sent_at            DATETIME NULL,
  accepted_at        DATETIME NULL,
  refused_reason     VARCHAR(255) NULL,
  converted_type     ENUM('sale','invoice','sales_order','production_order') NULL,
  converted_id       INT UNSIGNED NULL,
  duplicated_from_id INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quotations_number (number),
  KEY idx_quotations_customer (customer_id),
  KEY idx_quotations_status (status, issue_date),
  CONSTRAINT fk_quotations_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_quotations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_quotations_dup FOREIGN KEY (duplicated_from_id) REFERENCES quotations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotation_items (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quotation_id      INT UNSIGNED NOT NULL,
  product_id        INT UNSIGNED NULL,
  pack_id           INT UNSIGNED NULL,
  description       TEXT NOT NULL,
  width             DECIMAL(10,2) NULL,
  height            DECIMAL(10,2) NULL,
  dimension_unit    ENUM('mm','cm','m') NULL,
  quantity          DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  unit_id           INT UNSIGNED NULL,
  unit_price        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  discount_percent  DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  tax_rate          DECIMAL(6,3)  NOT NULL DEFAULT 0.000,
  line_subtotal     DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'HT après remise',
  line_tax          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_total        DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'TTC',
  unit_cost         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  cost_estimate_id  INT UNSIGNED NULL,
  sort_order        SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_qi_quotation (quotation_id),
  KEY idx_qi_product (product_id),
  CONSTRAINT fk_qi_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE CASCADE,
  CONSTRAINT fk_qi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_qi_pack FOREIGN KEY (pack_id) REFERENCES packs (id) ON DELETE SET NULL,
  CONSTRAINT fk_qi_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL,
  CONSTRAINT fk_qi_estimate FOREIGN KEY (cost_estimate_id) REFERENCES cost_estimates (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotation_history (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quotation_id  INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NULL,
  action        VARCHAR(50)  NOT NULL,
  from_status   VARCHAR(20)  NULL,
  to_status     VARCHAR(20)  NULL,
  note          VARCHAR(500) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_qh_quotation (quotation_id),
  CONSTRAINT fk_qh_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE CASCADE,
  CONSTRAINT fk_qh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. BONS DE COMMANDE CLIENT
-- ---------------------------------------------------------------------

CREATE TABLE sales_orders (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number          VARCHAR(40)  NOT NULL,
  customer_id     INT UNSIGNED NOT NULL,
  quotation_id    INT UNSIGNED NULL,
  user_id         INT UNSIGNED NULL,
  status          ENUM('pending','confirmed','in_production','ready','delivered','completed','cancelled') NOT NULL DEFAULT 'pending',
  order_date      DATE NOT NULL,
  due_date        DATE NULL,
  subtotal        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_total  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  fees_total      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  deposit_required DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  notes           TEXT NULL,
  customer_snapshot JSON NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_so_number (number),
  KEY idx_so_customer (customer_id),
  KEY idx_so_status (status),
  CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_so_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE SET NULL,
  CONSTRAINT fk_so_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_order_items (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sales_order_id    INT UNSIGNED NOT NULL,
  product_id        INT UNSIGNED NULL,
  pack_id           INT UNSIGNED NULL,
  description       TEXT NOT NULL,
  width             DECIMAL(10,2) NULL,
  height            DECIMAL(10,2) NULL,
  dimension_unit    ENUM('mm','cm','m') NULL,
  quantity          DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  unit_id           INT UNSIGNED NULL,
  unit_price        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  discount_percent  DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  tax_rate          DECIMAL(6,3)  NOT NULL DEFAULT 0.000,
  line_subtotal     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_tax          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_total        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  unit_cost         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  sort_order        SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_soi_order (sales_order_id),
  CONSTRAINT fk_soi_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_soi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_soi_pack FOREIGN KEY (pack_id) REFERENCES packs (id) ON DELETE SET NULL,
  CONSTRAINT fk_soi_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. CAISSE
-- ---------------------------------------------------------------------

CREATE TABLE cash_registers (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  location    VARCHAR(150) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cash_sessions (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cash_register_id  INT UNSIGNED NOT NULL,
  number            VARCHAR(40)  NOT NULL,
  status            ENUM('open','closed') NOT NULL DEFAULT 'open',
  opened_by         INT UNSIGNED NULL,
  opened_at         DATETIME NOT NULL,
  opening_balance   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  closed_by         INT UNSIGNED NULL,
  closed_at         DATETIME NULL,
  expected_balance  DECIMAL(15,2) NULL,
  counted_balance   DECIMAL(15,2) NULL,
  difference        DECIMAL(15,2) NULL,
  denominations     JSON NULL COMMENT 'comptage des billets et pièces',
  opening_notes     VARCHAR(500) NULL,
  closing_notes     VARCHAR(500) NULL,
  -- 1 ligne max "ouverte" par caisse : open_flag = 1 si ouverte, NULL sinon
  open_flag         TINYINT(1) NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cs_number (number),
  UNIQUE KEY uq_cs_one_open (cash_register_id, open_flag),
  KEY idx_cs_status (status, opened_at),
  CONSTRAINT fk_cs_register FOREIGN KEY (cash_register_id) REFERENCES cash_registers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cs_opened FOREIGN KEY (opened_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_cs_closed FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Montant signé : positif = entrée en caisse, négatif = sortie
CREATE TABLE cash_movements (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cash_session_id    INT UNSIGNED NOT NULL,
  type               ENUM('sale','payment','refund','expense','supplier_payment','deposit','withdrawal','transfer_in','transfer_out','adjustment') NOT NULL,
  payment_method_id  INT UNSIGNED NULL,
  amount             DECIMAL(15,2) NOT NULL,
  reference_type     VARCHAR(40)  NULL,
  reference_id       BIGINT UNSIGNED NULL,
  description        VARCHAR(255) NULL,
  related_session_id INT UNSIGNED NULL COMMENT 'pour les transferts entre caisses',
  user_id            INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cm_session (cash_session_id, type),
  KEY idx_cm_ref (reference_type, reference_id),
  CONSTRAINT fk_cm_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cm_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
  CONSTRAINT fk_cm_related FOREIGN KEY (related_session_id) REFERENCES cash_sessions (id) ON DELETE SET NULL,
  CONSTRAINT fk_cm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. VENTES POS (tickets)
-- ---------------------------------------------------------------------

CREATE TABLE sales (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number           VARCHAR(40)  NULL COMMENT 'attribué à la validation (les brouillons n''ont pas de numéro)',
  cash_session_id  INT UNSIGNED NULL,
  customer_id      INT UNSIGNED NOT NULL,
  user_id          INT UNSIGNED NULL,
  status           ENUM('draft','suspended','completed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'draft',
  sale_date        DATETIME NOT NULL,
  subtotal         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_type    ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
  discount_value   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_total   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  paid_amount      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  change_amount    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  refunded_amount  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  cost_total       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  quotation_id     INT UNSIGNED NULL,
  invoice_id       INT UNSIGNED NULL,
  suspend_label    VARCHAR(100) NULL,
  notes            TEXT NULL,
  cancelled_by     INT UNSIGNED NULL,
  cancelled_at     DATETIME NULL,
  cancel_reason    VARCHAR(255) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_number (number),
  KEY idx_sales_date (sale_date, status),
  KEY idx_sales_customer (customer_id),
  KEY idx_sales_session (cash_session_id),
  KEY idx_sales_user (user_id),
  CONSTRAINT fk_sales_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_cancelled FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id           INT UNSIGNED NOT NULL,
  product_id        INT UNSIGNED NULL,
  pack_id           INT UNSIGNED NULL,
  description       VARCHAR(500) NOT NULL,
  width             DECIMAL(10,2) NULL,
  height            DECIMAL(10,2) NULL,
  dimension_unit    ENUM('mm','cm','m') NULL,
  quantity          DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  returned_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  unit_id           INT UNSIGNED NULL,
  original_price    DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'prix catalogue avant surcharge',
  unit_price        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  price_overridden_by INT UNSIGNED NULL,
  discount_percent  DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  tax_rate          DECIMAL(6,3)  NOT NULL DEFAULT 0.000,
  line_subtotal     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_tax          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_total        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  unit_cost         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  notes             VARCHAR(255) NULL,
  sort_order        SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_si_sale (sale_id),
  KEY idx_si_product (product_id),
  CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
  CONSTRAINT fk_si_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_si_pack FOREIGN KEY (pack_id) REFERENCES packs (id) ON DELETE SET NULL,
  CONSTRAINT fk_si_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL,
  CONSTRAINT fk_si_override FOREIGN KEY (price_overridden_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_returns (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number             VARCHAR(40)  NOT NULL,
  sale_id            INT UNSIGNED NOT NULL,
  cash_session_id    INT UNSIGNED NULL,
  user_id            INT UNSIGNED NULL,
  return_date        DATETIME NOT NULL,
  reason             VARCHAR(255) NOT NULL,
  subtotal           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  refund_method_id   INT UNSIGNED NULL,
  credit_note_id     INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sr_number (number),
  KEY idx_sr_sale (sale_id),
  CONSTRAINT fk_sr_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sr_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE SET NULL,
  CONSTRAINT fk_sr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_sr_method FOREIGN KEY (refund_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
  CONSTRAINT fk_sr_credit FOREIGN KEY (credit_note_id) REFERENCES credit_notes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_return_items (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_return_id  INT UNSIGNED NOT NULL,
  sale_item_id    INT UNSIGNED NOT NULL,
  quantity        DECIMAL(15,3) NOT NULL,
  amount          DECIMAL(15,2) NOT NULL,
  restock         TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_sri_return (sale_return_id),
  KEY idx_sri_item (sale_item_id),
  CONSTRAINT fk_sri_return FOREIGN KEY (sale_return_id) REFERENCES sale_returns (id) ON DELETE CASCADE,
  CONSTRAINT fk_sri_item FOREIGN KEY (sale_item_id) REFERENCES sale_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. FACTURES, AVOIRS, PAIEMENTS
-- ---------------------------------------------------------------------

CREATE TABLE invoices (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number             VARCHAR(40)  NULL COMMENT 'attribué à la validation ; un brouillon n''a pas de numéro',
  invoice_type       ENUM('standard','deposit','balance') NOT NULL DEFAULT 'standard' COMMENT 'facture / acompte / solde',
  customer_id        INT UNSIGNED NOT NULL,
  user_id            INT UNSIGNED NULL,
  sale_id            INT UNSIGNED NULL,
  quotation_id       INT UNSIGNED NULL,
  sales_order_id     INT UNSIGNED NULL,
  parent_invoice_id  INT UNSIGNED NULL COMMENT 'facture de solde -> factures d''acompte',
  status             ENUM('draft','validated','paid','partial','unpaid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  issue_date         DATE NOT NULL,
  due_date           DATE NULL,
  subtotal           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_type      ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
  discount_value     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_total     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  design_fees        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  installation_fees  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  transport_fees     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  deposits_deducted  DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'acomptes déjà facturés (facture de solde)',
  paid_amount        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  credited_amount    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  balance_due        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  cost_total         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  payment_terms      TEXT NULL,
  notes              TEXT NULL,
  internal_notes     TEXT NULL,
  customer_snapshot  JSON NULL COMMENT 'coordonnées figées à la validation',
  business_snapshot  JSON NULL,
  is_locked          TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = document finalisé, non modifiable',
  validated_at       DATETIME NULL,
  validated_by       INT UNSIGNED NULL,
  cancelled_at       DATETIME NULL,
  cancelled_by       INT UNSIGNED NULL,
  cancel_reason      VARCHAR(255) NULL,
  last_reminder_at   DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoices_number (number),
  KEY idx_invoices_customer (customer_id),
  KEY idx_invoices_status (status, due_date),
  KEY idx_invoices_date (issue_date),
  CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_parent FOREIGN KEY (parent_invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_validated FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_cancelled FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id        INT UNSIGNED NOT NULL,
  product_id        INT UNSIGNED NULL,
  pack_id           INT UNSIGNED NULL,
  description       TEXT NOT NULL,
  width             DECIMAL(10,2) NULL,
  height            DECIMAL(10,2) NULL,
  dimension_unit    ENUM('mm','cm','m') NULL,
  quantity          DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  unit_id           INT UNSIGNED NULL,
  unit_price        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  discount_percent  DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  tax_rate          DECIMAL(6,3)  NOT NULL DEFAULT 0.000,
  line_subtotal     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_tax          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_total        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  unit_cost         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  sort_order        SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_ii_invoice (invoice_id),
  KEY idx_ii_product (product_id),
  CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
  CONSTRAINT fk_ii_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_ii_pack FOREIGN KEY (pack_id) REFERENCES packs (id) ON DELETE SET NULL,
  CONSTRAINT fk_ii_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien facture de solde <-> factures d'acompte déduites
CREATE TABLE invoice_deposits (
  balance_invoice_id  INT UNSIGNED NOT NULL,
  deposit_invoice_id  INT UNSIGNED NOT NULL,
  amount              DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (balance_invoice_id, deposit_invoice_id),
  KEY idx_id_deposit (deposit_invoice_id),
  CONSTRAINT fk_id_balance FOREIGN KEY (balance_invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
  CONSTRAINT fk_id_deposit FOREIGN KEY (deposit_invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE credit_notes (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number            VARCHAR(40)  NULL,
  invoice_id        INT UNSIGNED NULL,
  customer_id       INT UNSIGNED NOT NULL,
  user_id           INT UNSIGNED NULL,
  status            ENUM('draft','validated','applied','refunded','cancelled') NOT NULL DEFAULT 'draft',
  issue_date        DATE NOT NULL,
  reason            VARCHAR(255) NOT NULL,
  subtotal          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  applied_amount    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  refunded_amount   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  restock           TINYINT(1)   NOT NULL DEFAULT 0,
  notes             TEXT NULL,
  customer_snapshot JSON NULL,
  is_locked         TINYINT(1) NOT NULL DEFAULT 0,
  validated_at      DATETIME NULL,
  validated_by      INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cn_number (number),
  KEY idx_cn_invoice (invoice_id),
  KEY idx_cn_customer (customer_id),
  CONSTRAINT fk_cn_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cn_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cn_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_cn_validated FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE credit_note_items (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  credit_note_id    INT UNSIGNED NOT NULL,
  invoice_item_id   INT UNSIGNED NULL,
  product_id        INT UNSIGNED NULL,
  description       TEXT NOT NULL,
  quantity          DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  unit_id           INT UNSIGNED NULL,
  unit_price        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  tax_rate          DECIMAL(6,3)  NOT NULL DEFAULT 0.000,
  line_subtotal     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_tax          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  line_total        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  sort_order        SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_cni_note (credit_note_id),
  CONSTRAINT fk_cni_note FOREIGN KEY (credit_note_id) REFERENCES credit_notes (id) ON DELETE CASCADE,
  CONSTRAINT fk_cni_item FOREIGN KEY (invoice_item_id) REFERENCES invoice_items (id) ON DELETE SET NULL,
  CONSTRAINT fk_cni_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_cni_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Encaissements clients (un paiement mixte = plusieurs lignes partageant payment_group)
CREATE TABLE payments (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number             VARCHAR(40)  NOT NULL COMMENT 'numéro du reçu',
  payment_group      CHAR(16)     NULL,
  direction          ENUM('in','refund') NOT NULL DEFAULT 'in',
  customer_id        INT UNSIGNED NOT NULL,
  payment_method_id  INT UNSIGNED NOT NULL,
  cash_session_id    INT UNSIGNED NULL,
  sale_id            INT UNSIGNED NULL,
  credit_note_id     INT UNSIGNED NULL COMMENT 'remboursement d''avoir',
  amount             DECIMAL(15,2) NOT NULL,
  unallocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'avance client non affectée',
  payment_date       DATETIME NOT NULL,
  reference          VARCHAR(100) NULL COMMENT 'n° chèque, n° virement, n° transaction',
  bank_name          VARCHAR(100) NULL,
  notes              VARCHAR(500) NULL,
  status             ENUM('valid','cancelled') NOT NULL DEFAULT 'valid',
  cancelled_by       INT UNSIGNED NULL,
  cancelled_at       DATETIME NULL,
  cancel_reason      VARCHAR(255) NULL,
  user_id            INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_number (number),
  KEY idx_payments_customer (customer_id, payment_date),
  KEY idx_payments_group (payment_group),
  KEY idx_payments_sale (sale_id),
  KEY idx_payments_date (payment_date),
  CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_cn FOREIGN KEY (credit_note_id) REFERENCES credit_notes (id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_cancelled FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_allocations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id  INT UNSIGNED NOT NULL,
  invoice_id  INT UNSIGNED NOT NULL,
  amount      DECIMAL(15,2) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pa (payment_id, invoice_id),
  KEY idx_pa_invoice (invoice_id),
  CONSTRAINT fk_pa_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE,
  CONSTRAINT fk_pa_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Imputation d'un avoir sur une facture
CREATE TABLE credit_note_allocations (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  credit_note_id  INT UNSIGNED NOT NULL,
  invoice_id      INT UNSIGNED NOT NULL,
  amount          DECIMAL(15,2) NOT NULL,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cna_note (credit_note_id),
  KEY idx_cna_invoice (invoice_id),
  CONSTRAINT fk_cna_note FOREIGN KEY (credit_note_id) REFERENCES credit_notes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cna_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cna_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_reminders (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id   INT UNSIGNED NOT NULL,
  customer_id  INT UNSIGNED NOT NULL,
  channel      ENUM('email','whatsapp','phone','sms','letter') NOT NULL,
  message      TEXT NULL,
  user_id      INT UNSIGNED NULL,
  sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_prm_invoice (invoice_id),
  CONSTRAINT fk_prm_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
  CONSTRAINT fk_prm_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_prm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. DÉPENSES
-- ---------------------------------------------------------------------

CREATE TABLE expense_categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(30)  NOT NULL,
  name       VARCHAR(100) NOT NULL,
  color      VARCHAR(7)   NOT NULL DEFAULT '#6c757d',
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ec_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recurring_expenses (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id        INT UNSIGNED NOT NULL,
  supplier_id        INT UNSIGNED NULL,
  description        VARCHAR(255) NOT NULL,
  amount             DECIMAL(15,2) NOT NULL,
  frequency          ENUM('weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  next_date          DATE NOT NULL,
  end_date           DATE NULL,
  payment_method_id  INT UNSIGNED NULL,
  auto_create        TINYINT(1) NOT NULL DEFAULT 0,
  is_active          TINYINT(1) NOT NULL DEFAULT 1,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_re_next (is_active, next_date),
  CONSTRAINT fk_re_category FOREIGN KEY (category_id) REFERENCES expense_categories (id) ON DELETE RESTRICT,
  CONSTRAINT fk_re_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
  CONSTRAINT fk_re_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
  CONSTRAINT fk_re_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number                VARCHAR(40)  NOT NULL,
  category_id           INT UNSIGNED NOT NULL,
  supplier_id           INT UNSIGNED NULL,
  machine_id            INT UNSIGNED NULL,
  expense_date          DATE NOT NULL,
  amount                DECIMAL(15,2) NOT NULL COMMENT 'TTC',
  tax_amount            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  payment_method_id     INT UNSIGNED NULL,
  cash_session_id       INT UNSIGNED NULL,
  reference             VARCHAR(100) NULL,
  description           VARCHAR(500) NOT NULL,
  attachment            VARCHAR(255) NULL,
  recurring_expense_id  INT UNSIGNED NULL,
  user_id               INT UNSIGNED NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at            DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_expenses_number (number),
  KEY idx_expenses_date (expense_date),
  KEY idx_expenses_category (category_id),
  CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories (id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
  CONSTRAINT fk_expenses_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL,
  CONSTRAINT fk_expenses_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL,
  CONSTRAINT fk_expenses_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE SET NULL,
  CONSTRAINT fk_expenses_recurring FOREIGN KEY (recurring_expense_id) REFERENCES recurring_expenses (id) ON DELETE SET NULL,
  CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. ACHATS FOURNISSEURS
-- ---------------------------------------------------------------------

CREATE TABLE purchase_orders (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number         VARCHAR(40)  NOT NULL,
  supplier_id    INT UNSIGNED NOT NULL,
  user_id        INT UNSIGNED NULL,
  status         ENUM('draft','sent','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
  order_date     DATE NOT NULL,
  expected_date  DATE NULL,
  subtotal       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  notes          TEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_po_number (number),
  KEY idx_po_supplier (supplier_id),
  KEY idx_po_status (status),
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_po_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_order_id  INT UNSIGNED NOT NULL,
  material_id        INT UNSIGNED NULL,
  product_id         INT UNSIGNED NULL,
  description        VARCHAR(255) NOT NULL,
  quantity           DECIMAL(15,3) NOT NULL,
  received_quantity  DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  unit_cost          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  tax_rate           DECIMAL(6,3)  NOT NULL DEFAULT 0.000,
  line_total         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_poi_order (purchase_order_id),
  CONSTRAINT fk_poi_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT,
  CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_invoices (
  id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number                   VARCHAR(40)  NOT NULL COMMENT 'référence interne',
  supplier_invoice_number  VARCHAR(60)  NULL,
  supplier_id              INT UNSIGNED NOT NULL,
  purchase_order_id        INT UNSIGNED NULL,
  invoice_date             DATE NOT NULL,
  due_date                 DATE NULL,
  subtotal                 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_total                DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total                    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  paid_amount              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status                   ENUM('unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  attachment               VARCHAR(255) NULL,
  notes                    TEXT NULL,
  user_id                  INT UNSIGNED NULL,
  created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pinv_number (number),
  KEY idx_pinv_supplier (supplier_id, status),
  CONSTRAINT fk_pinv_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pinv_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_pinv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_invoice_items (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_invoice_id  INT UNSIGNED NOT NULL,
  material_id          INT UNSIGNED NULL,
  product_id           INT UNSIGNED NULL,
  description          VARCHAR(255) NOT NULL,
  quantity             DECIMAL(15,3) NOT NULL,
  unit_cost            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  tax_rate             DECIMAL(6,3)  NOT NULL DEFAULT 0.000,
  line_total           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_pii_invoice (purchase_invoice_id),
  CONSTRAINT fk_pii_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices (id) ON DELETE CASCADE,
  CONSTRAINT fk_pii_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pii_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_payments (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number               VARCHAR(40)  NOT NULL,
  supplier_id          INT UNSIGNED NOT NULL,
  purchase_invoice_id  INT UNSIGNED NULL,
  payment_method_id    INT UNSIGNED NOT NULL,
  cash_session_id      INT UNSIGNED NULL,
  amount               DECIMAL(15,2) NOT NULL,
  payment_date         DATE NOT NULL,
  reference            VARCHAR(100) NULL,
  notes                VARCHAR(500) NULL,
  status               ENUM('valid','cancelled') NOT NULL DEFAULT 'valid',
  user_id              INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sp_number (number),
  KEY idx_sp_supplier (supplier_id, payment_date),
  CONSTRAINT fk_sp_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sp_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_sp_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sp_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE SET NULL,
  CONSTRAINT fk_sp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_returns (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number               VARCHAR(40)  NOT NULL,
  supplier_id          INT UNSIGNED NOT NULL,
  purchase_invoice_id  INT UNSIGNED NULL,
  return_date          DATE NOT NULL,
  reason               VARCHAR(255) NOT NULL,
  total                DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status               ENUM('draft','validated','refunded','cancelled') NOT NULL DEFAULT 'draft',
  user_id              INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sret_number (number),
  KEY idx_sret_supplier (supplier_id),
  CONSTRAINT fk_sret_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sret_invoice FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_sret_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_return_items (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_return_id  INT UNSIGNED NOT NULL,
  material_id         INT UNSIGNED NULL,
  product_id          INT UNSIGNED NULL,
  quantity            DECIMAL(15,3) NOT NULL,
  unit_cost           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  line_total          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_sreti_return (supplier_return_id),
  CONSTRAINT fk_sreti_return FOREIGN KEY (supplier_return_id) REFERENCES supplier_returns (id) ON DELETE CASCADE,
  CONSTRAINT fk_sreti_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sreti_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. STOCK
-- ---------------------------------------------------------------------

CREATE TABLE stock_locations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  address     VARCHAR(255) NULL,
  is_default  TINYINT(1)   NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Niveau de stock par localisation (source de vérité) ; item = produit OU matière
CREATE TABLE stock_levels (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_id  INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NULL,
  material_id  INT UNSIGNED NULL,
  quantity     DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  updated_at   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sl_product (location_id, product_id),
  UNIQUE KEY uq_sl_material (location_id, material_id),
  KEY idx_sl_product (product_id),
  KEY idx_sl_material (material_id),
  CONSTRAINT fk_sl_location FOREIGN KEY (location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sl_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_sl_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal immuable des mouvements (quantité signée : + entrée / - sortie)
CREATE TABLE stock_movements (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_id     INT UNSIGNED NOT NULL,
  product_id      INT UNSIGNED NULL,
  material_id     INT UNSIGNED NULL,
  movement_type   ENUM('purchase','sale','production','adjustment','return','loss','consumption','inventory','transfer','supplier_return','damaged') NOT NULL,
  quantity        DECIMAL(15,3) NOT NULL,
  unit_cost       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  balance_after   DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  reference_type  VARCHAR(40)  NULL,
  reference_id    BIGINT UNSIGNED NULL,
  reason          VARCHAR(255) NULL,
  user_id         INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sm_product (product_id, created_at),
  KEY idx_sm_material (material_id, created_at),
  KEY idx_sm_type (movement_type, created_at),
  KEY idx_sm_ref (reference_type, reference_id),
  CONSTRAINT fk_sm_location FOREIGN KEY (location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_counts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number        VARCHAR(40)  NOT NULL,
  location_id   INT UNSIGNED NOT NULL,
  status        ENUM('draft','validated','cancelled') NOT NULL DEFAULT 'draft',
  count_date    DATE NOT NULL,
  notes         VARCHAR(500) NULL,
  user_id       INT UNSIGNED NULL,
  validated_by  INT UNSIGNED NULL,
  validated_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ic_number (number),
  CONSTRAINT fk_ic_location FOREIGN KEY (location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ic_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_ic_validated FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_count_items (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventory_count_id  INT UNSIGNED NOT NULL,
  product_id          INT UNSIGNED NULL,
  material_id         INT UNSIGNED NULL,
  system_quantity     DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  counted_quantity    DECIMAL(15,3) NULL,
  difference          DECIMAL(15,3) NULL,
  unit_cost           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (id),
  KEY idx_ici_count (inventory_count_id),
  CONSTRAINT fk_ici_count FOREIGN KEY (inventory_count_id) REFERENCES inventory_counts (id) ON DELETE CASCADE,
  CONSTRAINT fk_ici_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_ici_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 17. PRODUCTION
-- ---------------------------------------------------------------------

CREATE TABLE production_orders (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number          VARCHAR(40)  NOT NULL,
  customer_id     INT UNSIGNED NOT NULL,
  quotation_id    INT UNSIGNED NULL,
  invoice_id      INT UNSIGNED NULL,
  sales_order_id  INT UNSIGNED NULL,
  sale_id         INT UNSIGNED NULL,
  title           VARCHAR(190) NOT NULL,
  status          ENUM('order','design','design_validation','production','finishing','quality_control','ready','delivery','completed','cancelled') NOT NULL DEFAULT 'order',
  priority        ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  assigned_to     INT UNSIGNED NULL,
  machine_id      INT UNSIGNED NULL,
  deadline        DATETIME NULL,
  started_at      DATETIME NULL,
  completed_at    DATETIME NULL,
  instructions    TEXT NULL,
  notes           TEXT NULL,
  estimated_minutes INT UNSIGNED NULL,
  actual_cost     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  created_by      INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prod_number (number),
  KEY idx_prod_status (status, deadline),
  KEY idx_prod_customer (customer_id),
  KEY idx_prod_assigned (assigned_to),
  CONSTRAINT fk_prod_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_prod_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL,
  CONSTRAINT fk_prod_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_order_items (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  production_order_id  INT UNSIGNED NOT NULL,
  product_id           INT UNSIGNED NULL,
  description          TEXT NOT NULL,
  width                DECIMAL(10,2) NULL,
  height               DECIMAL(10,2) NULL,
  dimension_unit       ENUM('mm','cm','m') NULL,
  quantity             DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  unit_id              INT UNSIGNED NULL,
  material_id          INT UNSIGNED NULL,
  machine_id           INT UNSIGNED NULL,
  finishing            VARCHAR(255) NULL,
  is_done              TINYINT(1) NOT NULL DEFAULT 0,
  notes                VARCHAR(500) NULL,
  sort_order           SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_poi2_order (production_order_id),
  CONSTRAINT fk_proi_order FOREIGN KEY (production_order_id) REFERENCES production_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_proi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_proi_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL,
  CONSTRAINT fk_proi_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE SET NULL,
  CONSTRAINT fk_proi_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE design_orders (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number               VARCHAR(40)  NOT NULL,
  customer_id          INT UNSIGNED NOT NULL,
  production_order_id  INT UNSIGNED NULL,
  quotation_id         INT UNSIGNED NULL,
  product_id           INT UNSIGNED NULL COMMENT 'service de design facturé',
  designer_id          INT UNSIGNED NULL,
  title                VARCHAR(190) NOT NULL,
  design_type          ENUM('logo','brand_identity','business_card','flyer','poster','menu','catalog','social_media','packaging','vehicle','signage','photo_editing','video_editing','campaign','other') NOT NULL DEFAULT 'other',
  brief                TEXT NULL,
  deadline             DATETIME NULL,
  status               ENUM('new','in_progress','sent_for_validation','revision','validated','delivered','cancelled') NOT NULL DEFAULT 'new',
  revisions_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_revisions        SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  fee                  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  validated_at         DATETIME NULL,
  delivered_at         DATETIME NULL,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at           DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_design_number (number),
  KEY idx_design_status (status, deadline),
  KEY idx_design_designer (designer_id),
  CONSTRAINT fk_design_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_design_prod FOREIGN KEY (production_order_id) REFERENCES production_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_design_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE SET NULL,
  CONSTRAINT fk_design_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_design_designer FOREIGN KEY (designer_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_design_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fichiers de production et de design (stockés hors racine web : storage/uploads)
CREATE TABLE production_files (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  production_order_id  INT UNSIGNED NULL,
  design_order_id      INT UNSIGNED NULL,
  file_type            ENUM('artwork','preview','proof','attachment','delivery_proof') NOT NULL DEFAULT 'attachment',
  original_name        VARCHAR(255) NOT NULL,
  stored_name          VARCHAR(255) NOT NULL,
  mime_type            VARCHAR(100) NOT NULL,
  file_size            INT UNSIGNED NOT NULL DEFAULT 0,
  description          VARCHAR(255) NULL,
  uploaded_by          INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pf_prod (production_order_id),
  KEY idx_pf_design (design_order_id),
  CONSTRAINT fk_pf_prod FOREIGN KEY (production_order_id) REFERENCES production_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_pf_design FOREIGN KEY (design_order_id) REFERENCES design_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_pf_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE design_revisions (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  design_order_id    INT UNSIGNED NOT NULL,
  revision_number    SMALLINT UNSIGNED NOT NULL,
  designer_comment   TEXT NULL,
  customer_feedback  TEXT NULL,
  file_id            INT UNSIGNED NULL,
  user_id            INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dr (design_order_id, revision_number),
  CONSTRAINT fk_dr_design FOREIGN KEY (design_order_id) REFERENCES design_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_dr_file FOREIGN KEY (file_id) REFERENCES production_files (id) ON DELETE SET NULL,
  CONSTRAINT fk_dr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_status_history (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  production_order_id  INT UNSIGNED NOT NULL,
  from_status          VARCHAR(30) NULL,
  to_status            VARCHAR(30) NOT NULL,
  note                 VARCHAR(500) NULL,
  user_id              INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_psh_order (production_order_id, created_at),
  CONSTRAINT fk_psh_order FOREIGN KEY (production_order_id) REFERENCES production_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_psh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE material_consumption (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  production_order_id  INT UNSIGNED NULL,
  material_id          INT UNSIGNED NOT NULL,
  machine_id           INT UNSIGNED NULL,
  quantity             DECIMAL(15,3) NOT NULL,
  unit_cost            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  total_cost           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  stock_movement_id    BIGINT UNSIGNED NULL,
  notes                VARCHAR(255) NULL,
  user_id              INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mcons_order (production_order_id),
  KEY idx_mcons_material (material_id, created_at),
  CONSTRAINT fk_mcons_order FOREIGN KEY (production_order_id) REFERENCES production_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_mcons_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE RESTRICT,
  CONSTRAINT fk_mcons_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE SET NULL,
  CONSTRAINT fk_mcons_movement FOREIGN KEY (stock_movement_id) REFERENCES stock_movements (id) ON DELETE SET NULL,
  CONSTRAINT fk_mcons_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE machine_usage (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  machine_id           INT UNSIGNED NOT NULL,
  production_order_id  INT UNSIGNED NULL,
  usage_date           DATE NOT NULL,
  hours                DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  units_produced       DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  output_unit          VARCHAR(20)   NULL COMMENT 'm², pages, pièces…',
  cost                 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  notes                VARCHAR(255) NULL,
  user_id              INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mu_machine (machine_id, usage_date),
  CONSTRAINT fk_mu_machine FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE CASCADE,
  CONSTRAINT fk_mu_order FOREIGN KEY (production_order_id) REFERENCES production_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_mu_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 18. LIVRAISON & INSTALLATION
-- ---------------------------------------------------------------------

CREATE TABLE deliveries (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number               VARCHAR(40)  NOT NULL,
  customer_id          INT UNSIGNED NOT NULL,
  production_order_id  INT UNSIGNED NULL,
  invoice_id           INT UNSIGNED NULL,
  sales_order_id       INT UNSIGNED NULL,
  delivery_date        DATETIME NULL,
  address              VARCHAR(255) NULL,
  city                 VARCHAR(100) NULL,
  contact_name         VARCHAR(150) NULL,
  contact_phone        VARCHAR(30)  NULL,
  driver_id            INT UNSIGNED NULL,
  driver_name          VARCHAR(150) NULL,
  delivery_fee         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  installation_fee     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  requires_installation TINYINT(1) NOT NULL DEFAULT 0,
  installation_date    DATETIME NULL,
  status               ENUM('to_prepare','ready','in_delivery','delivered','installation_planned','installed','cancelled') NOT NULL DEFAULT 'to_prepare',
  recipient_name       VARCHAR(150) NULL,
  delivered_at         DATETIME NULL,
  installed_at         DATETIME NULL,
  proof_file_id        INT UNSIGNED NULL,
  notes                TEXT NULL,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_deliveries_number (number),
  KEY idx_deliveries_status (status, delivery_date),
  KEY idx_deliveries_customer (customer_id),
  CONSTRAINT fk_del_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_del_prod FOREIGN KEY (production_order_id) REFERENCES production_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_del_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_del_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_del_driver FOREIGN KEY (driver_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_del_proof FOREIGN KEY (proof_file_id) REFERENCES production_files (id) ON DELETE SET NULL,
  CONSTRAINT fk_del_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE delivery_items (
  id                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  delivery_id               INT UNSIGNED NOT NULL,
  production_order_item_id  INT UNSIGNED NULL,
  description               VARCHAR(500) NOT NULL,
  quantity                  DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  PRIMARY KEY (id),
  KEY idx_di_delivery (delivery_id),
  CONSTRAINT fk_di_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries (id) ON DELETE CASCADE,
  CONSTRAINT fk_di_item FOREIGN KEY (production_order_item_id) REFERENCES production_order_items (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE delivery_status_history (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  delivery_id  INT UNSIGNED NOT NULL,
  from_status  VARCHAR(30) NULL,
  to_status    VARCHAR(30) NOT NULL,
  note         VARCHAR(500) NULL,
  user_id      INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dsh_delivery (delivery_id),
  CONSTRAINT fk_dsh_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries (id) ON DELETE CASCADE,
  CONSTRAINT fk_dsh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 19. VERSION DU SCHÉMA (migrations futures)
-- ---------------------------------------------------------------------

CREATE TABLE schema_migrations (
  version     VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
