-- scharge: BnB hosts inside shared properties, one record per building, claims.
-- Hand-applied SQL (not a migration). Apply AFTER 2026_11_09_rename_estates_to_properties.sql. Run ONCE
-- (the first ALTER fails if it was already applied, and the mysql client stops there):
--   mysql scha_main < database/schema/2026_11_09_bnb_hosts.sql
--
-- Model:
--   properties.company_id   = the building's management company (NULL while unclaimed)
--   units.company_id        = who manages that unit day to day (a BnB host's company for host units)
--   unit_managements        = who manages each unit, in what role, from when (history kept)
--   property_claims         = a management company asking to take over a building already in scharge,
--                             approved by its account manager / a sysadmin

ALTER TABLE `properties`
  ADD COLUMN `name_key` varchar(191) DEFAULT NULL AFTER `name`,
  ADD COLUMN `location_key` varchar(191) DEFAULT NULL AFTER `location`,
  ADD COLUMN `claim_status` varchar(12) NOT NULL DEFAULT 'managed' AFTER `company_id`,   -- managed | unclaimed
  ADD COLUMN `created_by_company_id` bigint unsigned DEFAULT NULL AFTER `claim_status`,
  ADD INDEX `properties_name_key_index` (`name_key`);

ALTER TABLE `companies`
  ADD COLUMN `kind` varchar(20) NOT NULL DEFAULT 'manager' AFTER `name`;              -- manager | bnb_host | developer

CREATE TABLE IF NOT EXISTS `unit_managements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint unsigned NOT NULL,
  `company_id` bigint unsigned NOT NULL,
  `role` varchar(12) NOT NULL,                      -- manager | bnb_host | owner
  `status` varchar(10) NOT NULL DEFAULT 'active',   -- active | ended
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_managements_unit_status_index` (`unit_id`, `status`),
  KEY `unit_managements_company_role_index` (`company_id`, `role`),
  CONSTRAINT `unit_managements_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `property_claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `company_id` bigint unsigned NOT NULL,            -- the company asking to manage the building
  `status` varchar(10) NOT NULL DEFAULT 'pending',  -- pending | approved | rejected
  `requested_by` bigint unsigned DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `message` varchar(500) DEFAULT NULL,              -- what the claimant said (e.g. "we manage this block since 2024")
  `review_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_claims_status_index` (`status`),
  CONSTRAINT `property_claims_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every existing unit is managed by its own company, as the manager.
INSERT INTO `unit_managements` (`unit_id`, `company_id`, `role`, `status`, `starts_at`, `notes`, `created_at`, `updated_at`)
SELECT `id`, `company_id`, 'manager', 'active', COALESCE(`created_at`, NOW()), 'Backfilled', NOW(), NOW()
FROM `units` WHERE `company_id` IS NOT NULL;

-- Role for BnB hosts.
INSERT INTO `roles` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'bnb_host', 'web', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'bnb_host' AND `guard_name` = 'web');

-- Fill properties.name_key / location_key afterwards with:  php artisan properties:index-keys
