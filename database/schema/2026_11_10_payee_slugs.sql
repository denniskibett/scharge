-- scharge: payees are unique and normalized. Each shared payee (company_id NULL) has a stable slug (kplc, kra,
-- cleaning-company…, see App\Services\Expenses\BusinessBooks::SHARED_PAYEES) so it matches the same row locally, on
-- live and in other projects. A business's own payees keep slug NULL. Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_10_payee_slugs.sql
-- then: php artisan db:seed --class=PayeesAndExpenseCategoriesSeeder --force  (fills the slugs, merges duplicates)

ALTER TABLE `payees`
  ADD COLUMN `slug` varchar(64) DEFAULT NULL AFTER `company_id`,
  ADD UNIQUE KEY `payees_slug_unique` (`slug`);
