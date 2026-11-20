-- scharge: display currency (docs/CURRENCY.md, App\Support\Currency).
-- Amounts are never rewritten: this only stores which currency a company wants its dashboards, reports and billing
-- totals shown in. NULL = follow the platform default (System settings › Currency).
-- The app works without this column (every company follows the platform default).
-- Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_20_display_currency.sql

ALTER TABLE `companies`
  ADD COLUMN `display_currency` char(3) NULL DEFAULT NULL COMMENT 'ISO 4217; NULL = platform default' AFTER `country_code`;

-- `system.currency` was never used for display before, so some installs hold a leftover value (USD, AUD...).
-- Reset it to the book currency so nothing changes on deploy; a sysadmin then picks the default on System settings.
UPDATE `system` SET `currency` = 'KES', `currency_symbol` = 'KSh'
 WHERE `currency` IS NULL OR `currency` NOT IN ('KES','UGX','TZS','RWF','USD','EUR','GBP','CNY','AED','INR','ZAR');
