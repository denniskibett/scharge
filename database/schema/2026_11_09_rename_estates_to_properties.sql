-- scharge: rename "estates" to "properties" everywhere in the schema.
-- Hand-applied SQL (not a migration). Run ONCE, together with the code that uses `properties`:
--   mysql scha_main < database/schema/2026_11_09_rename_estates_to_properties.sql
-- Works on MariaDB 10.3 (live) and MySQL 8/9 (no RENAME COLUMN / RENAME INDEX used).
-- The first statement fails if this was already applied, and the mysql client stops there.
-- Back up first: mysqldump scha_main | gzip > before-properties-rename.sql.gz

RENAME TABLE `estates` TO `properties`;

-- properties' own company index and foreign key get matching names
ALTER TABLE `properties` DROP FOREIGN KEY `estates_company_id_foreign`;
ALTER TABLE `properties` DROP INDEX `estates_company_id_index`, ADD INDEX `properties_company_id_index` (`company_id`);
ALTER TABLE `properties` ADD CONSTRAINT `properties_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- company_payment_methods
ALTER TABLE `company_payment_methods` DROP FOREIGN KEY `company_payment_methods_estate_id_foreign`;
ALTER TABLE `company_payment_methods` DROP INDEX `company_payment_methods_estate_id_index`,
  CHANGE `estate_id` `property_id` bigint unsigned DEFAULT NULL,
  ADD INDEX `company_payment_methods_property_id_index` (`property_id`);
ALTER TABLE `company_payment_methods` ADD CONSTRAINT `company_payment_methods_property_id_foreign`
  FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- expenses
ALTER TABLE `expenses` DROP FOREIGN KEY `expenses_estate_id_foreign`;
ALTER TABLE `expenses` DROP INDEX `expenses_estate_id_foreign`,
  CHANGE `estate_id` `property_id` bigint unsigned NOT NULL,
  ADD INDEX `expenses_property_id_foreign` (`property_id`);
ALTER TABLE `expenses` ADD CONSTRAINT `expenses_property_id_foreign`
  FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`);

-- maintenances
ALTER TABLE `maintenances` DROP FOREIGN KEY `maintenances_estate_id_foreign`;
ALTER TABLE `maintenances` DROP INDEX `maintenances_estate_id_index`,
  CHANGE `estate_id` `property_id` bigint unsigned DEFAULT NULL,
  ADD INDEX `maintenances_property_id_index` (`property_id`);
ALTER TABLE `maintenances` ADD CONSTRAINT `maintenances_property_id_foreign`
  FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

-- security
ALTER TABLE `security` DROP FOREIGN KEY `security_estate_id_foreign`;
ALTER TABLE `security` DROP INDEX `security_estate_id_index`,
  CHANGE `estate_id` `property_id` bigint unsigned DEFAULT NULL,
  ADD INDEX `security_property_id_index` (`property_id`);
ALTER TABLE `security` ADD CONSTRAINT `security_property_id_foreign`
  FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

-- tenancies
ALTER TABLE `tenancies` DROP FOREIGN KEY `tenancies_estate_id_foreign`;
ALTER TABLE `tenancies` DROP INDEX `tenancies_estate_id_index`,
  CHANGE `estate_id` `property_id` bigint unsigned DEFAULT NULL,
  ADD INDEX `tenancies_property_id_index` (`property_id`);
ALTER TABLE `tenancies` ADD CONSTRAINT `tenancies_property_id_foreign`
  FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

-- units (the FK uses the unique key as its index)
ALTER TABLE `units` DROP FOREIGN KEY `units_estate_id_foreign`;
ALTER TABLE `units` DROP INDEX `units_estate_id_unit_number_unique`,
  CHANGE `estate_id` `property_id` bigint unsigned NOT NULL,
  ADD UNIQUE INDEX `units_property_id_unit_number_unique` (`property_id`, `unit_number`);
ALTER TABLE `units` ADD CONSTRAINT `units_property_id_foreign`
  FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

-- visitors
ALTER TABLE `visitors` DROP FOREIGN KEY `visitors_estate_id_foreign`;
ALTER TABLE `visitors` DROP INDEX `visitors_estate_id_index`,
  CHANGE `estate_id` `property_id` bigint unsigned DEFAULT NULL,
  ADD INDEX `visitors_property_id_index` (`property_id`);
ALTER TABLE `visitors` ADD CONSTRAINT `visitors_property_id_foreign`
  FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

-- columns with no foreign key
ALTER TABLE `invoices` CHANGE `estate_id` `property_id` bigint unsigned DEFAULT NULL;
ALTER TABLE `tenants` CHANGE `estate_id` `property_id` bigint unsigned DEFAULT NULL;
ALTER TABLE `sms_campaign_history` DROP INDEX `estate_id`,
  CHANGE `estate_id` `property_id` int DEFAULT NULL,
  ADD INDEX `property_id` (`property_id`);

-- Saved SMS templates keep working: {{estate_name}} is still filled in by the code,
-- and new templates use {{property_name}}.
