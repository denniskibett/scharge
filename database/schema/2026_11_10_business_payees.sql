-- scharge: payees and expense categories belong to a business (company), like its expenses.
-- Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_10_business_payees.sql
--
-- company_id NULL = a shared default template (seeded list). Each business gets its own copies of the defaults
-- that fit its kind (manager / bnb_host / developer) the first time it opens Expenses
-- (App\Services\Expenses\BusinessBooks::ensureDefaults), and manages them from then on.

ALTER TABLE `payees`
  ADD COLUMN `company_id` bigint unsigned DEFAULT NULL AFTER `id`,
  ADD INDEX `payees_company_id_index` (`company_id`);

ALTER TABLE `expense_categories`
  ADD COLUMN `company_id` bigint unsigned DEFAULT NULL AFTER `id`,
  ADD INDEX `expense_categories_company_id_index` (`company_id`);

-- Expenses recorded without a company take their property's company.
UPDATE `expenses` e JOIN `properties` p ON p.id = e.property_id
SET e.company_id = p.company_id
WHERE e.company_id IS NULL AND p.company_id IS NOT NULL;
