-- scharge: a property's vacant homes show on the public Open listings page only once its owner (company admin /
-- property manager with properties.manage) or scharge staff switch "Show on Open listings" on, on the property page.
-- Every property starts OFF. Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_19_property_public_listing.sql

ALTER TABLE `properties`
  ADD COLUMN `listed_publicly` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN `listed_at` timestamp NULL DEFAULT NULL,
  ADD COLUMN `listed_by` bigint unsigned DEFAULT NULL;
