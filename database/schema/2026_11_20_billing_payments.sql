-- scharge: companies pay their scharge bills (subscription_invoices) from Billing (/billing), with Sharet Pay or from
-- their scharge wallet (rail-mode rent collected through scharge and not yet paid out). App\Services\Sharet\BillPayments,
-- docs/BILLING_PAYMENTS.md. One row per attempt; a paid row marks the bill paid and is the company's ledger entry.
-- A wallet row is taken off the company's next payout (sweep_id = the sweep that took it).
-- The Billing page works without this table (no pay buttons).
-- Hand-applied SQL (not a migration). Safe to re-run:
--   mysql scha_main < database/schema/2026_11_20_billing_payments.sql

CREATE TABLE IF NOT EXISTS `billing_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `subscription_invoice_id` bigint unsigned NOT NULL,
  `method` varchar(12) NOT NULL COMMENT 'sharet_pay | wallet',
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KES',
  `status` varchar(12) NOT NULL DEFAULT 'open' COMMENT 'open | paid | expired | cancelled | failed',
  `environment` varchar(10) NOT NULL DEFAULT 'test',
  `sharet_id` varchar(80) NULL DEFAULT NULL COMMENT 'Sharet payment request (sharet_pay)',
  `sharet_transaction` varchar(80) NULL DEFAULT NULL,
  `pay_url` varchar(255) NULL DEFAULT NULL,
  `paid_via` varchar(20) NULL DEFAULT NULL,
  `sweep_id` bigint unsigned NULL DEFAULT NULL COMMENT 'wallet: the payout this was taken off',
  `user_id` bigint unsigned NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `raw` json NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_payments_sharet_id_unique` (`sharet_id`),
  KEY `billing_payments_invoice` (`subscription_invoice_id`, `status`),
  KEY `billing_payments_wallet` (`company_id`, `method`, `status`, `sweep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
