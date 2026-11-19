-- White label preview address: scharge.sharet.africa/{slug} shows a company's site / branded portal before (or
-- instead of) its own domain, e.g. /danaff. Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_19_company_brand_slug.sql
ALTER TABLE `company_brands`
  ADD COLUMN `slug` varchar(60) DEFAULT NULL AFTER `domain`,
  ADD UNIQUE KEY `company_brands_slug_unique` (`slug`);
