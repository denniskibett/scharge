-- scharge CRM: each company's property type (App\\Modules\\CRM\\Support\\PropertyClass::of) as a stored, indexed column, so the
-- type filter, campaign audiences and the Team page stop reading crm_profile JSON row by row (131k companies).
-- Generated from PropertyClass::SEGMENTS: when SEGMENTS change, regenerate this (drop + add the column).
-- Hand-applied SQL (not a migration); MariaDB 10.3+ and MySQL 8+. Run ONCE:
--   mysql scha_main < database/schema/2026_11_19_crm_class.sql

ALTER TABLE `companies`
  ADD COLUMN `crm_class` varchar(32) AS (COALESCE(NULLIF(JSON_VALUE(`crm_profile`, '$.property_class'), ''), CASE WHEN JSON_VALUE(`crm_profile`, '$.segment') IN ('Property Manager / Agency', 'Residential Property (named building/estate)', 'Residential Building (site)', 'Developer', 'Affordable Housing', 'High-end Residential', 'Gated Community', 'Upmarket Agency') THEN 'Residential' WHEN JSON_VALUE(`crm_profile`, '$.segment') IN ('Short-stay / Airbnb Manager', 'Short-stay / Airbnb', 'Hotel / Serviced Accommodation', 'Serviced Apartments') THEN 'Short-stay' WHEN JSON_VALUE(`crm_profile`, '$.segment') IN ('Commercial Property / Mall', 'Office / Commercial', 'Retail / Mall', 'Industrial / Warehouse Park', 'Industrial / Factory (utility & metering)', 'Industrial / Logistics') THEN 'Commercial' WHEN JSON_VALUE(`crm_profile`, '$.segment') IN ('Water Company', 'Private Water Supplier', 'Electricity / KPLC & Metering') THEN 'Utilities' WHEN JSON_VALUE(`crm_profile`, '$.segment') IN ('Real Estate Agency') THEN 'Agency' END, CASE `kind` WHEN 'bnb_host' THEN 'Short-stay' WHEN 'agency' THEN 'Agency' END)) STORED,
  ADD INDEX `companies_crm_class_index` (`crm_class`);
