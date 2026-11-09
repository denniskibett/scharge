-- scharge: confirm each person's email with a one-time code (KYC). Hand-applied SQL (not a migration).
--   mysql scha_main < database/schema/2026_11_09_email_otp.sql
-- Safe to re-run. Works on MariaDB 10.3 (live) and MySQL 8/9 (local), which lacks ADD COLUMN IF NOT EXISTS.
--
-- email_confirmed_at: when the person typed the code we emailed them (App\Services\EmailOtp).
-- Separate from email_verified_at, which scharge uses as "approved by an admin".
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_confirmed_at');
SET @sql := IF(@has = 0,
  'ALTER TABLE `users` ADD COLUMN `email_confirmed_at` TIMESTAMP NULL DEFAULT NULL AFTER `email_verified_at`',
  'SELECT ''email_confirmed_at already there'' AS note');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
