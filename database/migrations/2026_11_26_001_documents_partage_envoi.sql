-- Phase 5 : partage sécurisé des documents (lien public, QR code) et journal des envois e-mail
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS document_shares (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type      VARCHAR(30)  NOT NULL COMMENT 'quotation, invoice, credit_note, payment, sales_order',
  doc_id        INT UNSIGNED NOT NULL,
  token         CHAR(40)     NOT NULL,
  expires_at    DATETIME     NULL,
  views         INT UNSIGNED NOT NULL DEFAULT 0,
  last_viewed_at DATETIME    NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at    DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ds_token (token),
  KEY idx_ds_doc (doc_type, doc_id),
  CONSTRAINT fk_docshare_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_emails (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type    VARCHAR(30)  NOT NULL,
  doc_id      INT UNSIGNED NOT NULL,
  channel     ENUM('email','whatsapp') NOT NULL DEFAULT 'email',
  recipient   VARCHAR(190) NOT NULL,
  subject     VARCHAR(255) NULL,
  status      ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  error       VARCHAR(500) NULL,
  user_id     INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_de_doc (doc_type, doc_id),
  CONSTRAINT fk_docmail_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_group, setting_key, setting_value, value_type) VALUES
('documents', 'share_link_days', '60', 'int'),
('documents', 'default_deposit_percent', '50', 'decimal'),
('documents', 'quotation_intro', 'Suite à votre demande, nous avons le plaisir de vous adresser notre meilleure offre.', 'text'),
('documents', 'email_signature', 'Cordialement,', 'text');
