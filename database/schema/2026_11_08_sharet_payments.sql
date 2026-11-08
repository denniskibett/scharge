-- scharge: payments through Sharet Africa, Integrations page, API keys.
-- Hand-applied SQL (same approach as the CRM files beside this one); not a migration.
-- Apply to the scharge database:   mysql scha_main < database/schema/2026_11_08_sharet_payments.sql
-- Safe to re-run: every statement is CREATE TABLE IF NOT EXISTS / INSERT IGNORE.

-- Provider settings per company (company_id NULL = scharge platform default).
-- `credentials` is a JSON object encrypted with APP_KEY (Laravel `encrypted:array` cast).
CREATE TABLE IF NOT EXISTS `company_integrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(40) NOT NULL,
  `credentials` text,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_tested_at` timestamp NULL DEFAULT NULL,
  `last_test_ok` tinyint(1) DEFAULT NULL,
  `last_test_message` varchar(255) DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_integrations_lookup` (`company_id`, `provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- scharge's own API keys (for white-label clients and integrators). Only a SHA-256 hash is stored.
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `environment` varchar(10) NOT NULL DEFAULT 'test',
  `scopes` json DEFAULT NULL,
  `allowed_ips` json DEFAULT NULL,
  `prefix` varchar(16) NOT NULL,
  `last_four` varchar(4) NOT NULL,
  `hash` char(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_keys_hash_unique` (`hash`),
  KEY `api_keys_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per Sharet payment request (a tenant pay link for an invoice).
-- mode: 'rail' = paid into scharge's Sharet wallet, swept to the landlord later;
--       'connected' = created on the landlord's own connected Sharet account.
CREATE TABLE IF NOT EXISTS `sharet_payment_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sharet_id` varchar(80) NOT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `tenant_id` bigint unsigned DEFAULT NULL,
  `environment` varchar(10) NOT NULL DEFAULT 'test',
  `mode` varchar(12) NOT NULL DEFAULT 'rail',
  `sharet_account` varchar(40) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `pay_url` varchar(255) DEFAULT NULL,
  `reference` varchar(140) DEFAULT NULL,
  `paid_via` varchar(20) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `settled_payment_id` bigint unsigned DEFAULT NULL,
  `sweep_id` bigint unsigned DEFAULT NULL,
  `raw` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sharet_payment_requests_sharet_id_unique` (`sharet_id`),
  KEY `sharet_payment_requests_invoice_id_index` (`invoice_id`),
  KEY `sharet_payment_requests_sweep_lookup` (`company_id`, `mode`, `status`, `sweep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rail-mode payouts to landlords, run at 08:00 / 12:00 / 16:00 Africa/Nairobi.
CREATE TABLE IF NOT EXISTS `sharet_sweeps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `slot` varchar(20) NOT NULL,               -- e.g. 2026-11-09T08 (one sweep per company per slot)
  `requests_count` int unsigned NOT NULL DEFAULT 0,
  `gross` decimal(12,2) NOT NULL DEFAULT 0,
  `fee` decimal(12,2) NOT NULL DEFAULT 0,
  `net` decimal(12,2) NOT NULL DEFAULT 0,
  `destination` json DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending', -- pending, paying, paid, failed, skipped
  `sharet_payment_id` varchar(80) DEFAULT NULL,
  `failure` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sharet_sweeps_company_slot_unique` (`company_id`, `slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every Sharet webhook event received (dedupe on event id).
CREATE TABLE IF NOT EXISTS `sharet_webhook_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(80) NOT NULL,
  `type` varchar(60) NOT NULL,
  `payload` json DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sharet_webhook_events_event_id_unique` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff role for account managers (see docs/SYSTEM_AUDIT.md §12.3).
INSERT IGNORE INTO `roles` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'account_manager', 'web', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'account_manager' AND `guard_name` = 'web');

-- Payments that arrive through Sharet are recorded with payment_method = 'sharet'.
ALTER TABLE `payments` MODIFY `payment_method`
  enum('wallet','mpesa_stk','mpesa_paybill','bank_transfer','cash','manual_topup','message_paste','sharet') NOT NULL DEFAULT 'wallet';
