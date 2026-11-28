<?php

declare(strict_types=1);

/*
 * Phase 7: secret links for customers (only SHA-256 hashes are stored) and invoice e-mail tracking.
 */
return [
    'up' => [
        "ALTER TABLE quotations ADD COLUMN access_token CHAR(64) NULL COMMENT 'sha256 of the link sent to the customer' AFTER notes,
                                ADD COLUMN response_ip VARCHAR(45) NULL AFTER rejected_at,
                                ADD KEY idx_quotations_validity (status, valid_until)",
        "ALTER TABLE invoices ADD COLUMN access_token CHAR(64) NULL COMMENT 'sha256 of the download link sent to the customer' AFTER pdf_path,
                              ADD COLUMN sent_at DATETIME NULL AFTER issued_by,
                              ADD KEY idx_invoices_order_type (order_id, invoice_type, status)",
    ],
    'down' => [
        'ALTER TABLE invoices DROP KEY idx_invoices_order_type, DROP COLUMN sent_at, DROP COLUMN access_token',
        'ALTER TABLE quotations DROP KEY idx_quotations_validity, DROP COLUMN response_ip, DROP COLUMN access_token',
    ],
];
