-- scharge: more Integrations (docs/INTEGRATIONS.md): webhooks out, KRA eTIMS, accounting sync (QuickBooks, Xero,
-- Zoho Books), bank statement import and Equity Jenga. Settings themselves stay in company_integrations (encrypted).
-- The app works without these tables: the new cards say "not installed yet" on Test until this file is applied.
-- Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_20_integrations_more.sql

-- One row per event sent (or to send) to a company's webhook URL. Retried by `integrations:retry` with back-off.
CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `event` varchar(60) NOT NULL,
  `event_id` varchar(40) NOT NULL COMMENT 'evt_… sent as X-Scharge-Event-Id; receivers dedupe on it',
  `payload` json NOT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending' COMMENT 'pending | sent | failed',
  `attempts` tinyint unsigned NOT NULL DEFAULT 0,
  `response_code` smallint unsigned NULL DEFAULT NULL,
  `response_body` varchar(255) NULL DEFAULT NULL,
  `next_attempt_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_deliveries_event_id` (`event_id`),
  KEY `webhook_deliveries_due` (`status`, `next_attempt_at`),
  KEY `webhook_deliveries_company` (`company_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A scharge record copied to an outside system: an invoice signed by KRA eTIMS, or a tenant / invoice / payment pushed
-- to QuickBooks, Xero or Zoho Books. One row per (company, provider, record); retried by `integrations:retry`.
CREATE TABLE IF NOT EXISTS `integration_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `provider` varchar(30) NOT NULL COMMENT 'etims | quickbooks | xero | zoho_books',
  `object_type` varchar(20) NOT NULL COMMENT 'invoice | payment | customer',
  `object_id` bigint unsigned NOT NULL,
  `remote_id` varchar(120) NULL DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending' COMMENT 'pending | synced | failed',
  `attempts` tinyint unsigned NOT NULL DEFAULT 0,
  `error` varchar(500) NULL DEFAULT NULL,
  `data` json NULL DEFAULT NULL COMMENT 'eTIMS: receipt number, signature, QR link',
  `next_attempt_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `integration_links_object` (`company_id`, `provider`, `object_type`, `object_id`),
  KEY `integration_links_due` (`status`, `next_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Money-in lines from a bank statement (uploaded CSV/XLSX, or pulled from Equity Jenga). Each is matched to a tenant's
-- bill by reference / unit / amount; a person confirms, and only then is a payment recorded.
CREATE TABLE IF NOT EXISTS `bank_statement_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `source` varchar(12) NOT NULL DEFAULT 'upload' COMMENT 'upload | jenga',
  `bank` varchar(40) NULL DEFAULT NULL,
  `file_name` varchar(190) NULL DEFAULT NULL,
  `line_hash` char(40) NOT NULL COMMENT 'sha1 of date+amount+reference+description: the same line uploaded twice is kept once',
  `posted_on` date NOT NULL,
  `description` varchar(500) NULL DEFAULT NULL,
  `reference` varchar(120) NULL DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `invoice_id` bigint unsigned NULL DEFAULT NULL COMMENT 'suggested or confirmed bill',
  `match_reason` varchar(120) NULL DEFAULT NULL,
  `payment_id` bigint unsigned NULL DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'new' COMMENT 'new | suggested | posted | ignored',
  `posted_by` bigint unsigned NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_statement_lines_once` (`company_id`, `line_hash`),
  KEY `bank_statement_lines_status` (`company_id`, `status`, `posted_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
