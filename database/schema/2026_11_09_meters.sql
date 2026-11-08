-- scharge: water & electricity meters, manual and smart, for the meter readings API.
-- Hand-applied SQL (not a migration). Apply AFTER 2026_11_09_rename_estates_to_properties.sql:
--   mysql scha_main < database/schema/2026_11_09_meters.sql
-- Safe to re-run (CREATE TABLE IF NOT EXISTS). Works on MariaDB 10.3 and MySQL 8/9.

CREATE TABLE IF NOT EXISTS `meters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `property_id` bigint unsigned DEFAULT NULL,
  `unit_id` bigint unsigned DEFAULT NULL,           -- NULL = a bulk/common-area meter
  `utility` varchar(20) NOT NULL,                   -- water | electricity
  `kind` varchar(10) NOT NULL DEFAULT 'manual',     -- manual (read by a person) | smart (reports itself)
  `billing` varchar(10) NOT NULL DEFAULT 'postpaid',-- postpaid | prepaid (electricity tokens)
  `serial_number` varchar(64) NOT NULL,
  `make` varchar(80) DEFAULT NULL,
  `model` varchar(80) DEFAULT NULL,
  `unit_of_measure` varchar(10) NOT NULL DEFAULT 'm3', -- m3 | kWh
  `multiplier` decimal(10,4) NOT NULL DEFAULT 1.0000,  -- CT ratio etc.
  `rate_per_unit` decimal(12,2) DEFAULT NULL,       -- overrides the unit/property rate
  `initial_reading` decimal(14,3) NOT NULL DEFAULT 0.000,
  `last_reading` decimal(14,3) DEFAULT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `device_token_prefix` varchar(16) DEFAULT NULL,
  `device_token_hash` char(64) DEFAULT NULL,         -- smart meters: SHA-256 of mtr_… token
  `status` varchar(12) NOT NULL DEFAULT 'active',   -- active | inactive | replaced
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meters_company_utility_serial_unique` (`company_id`, `utility`, `serial_number`),
  UNIQUE KEY `meters_device_token_hash_unique` (`device_token_hash`),
  KEY `meters_unit_id_index` (`unit_id`),
  KEY `meters_property_id_index` (`property_id`),
  CONSTRAINT `meters_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `meters_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meter_readings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `meter_id` bigint unsigned NOT NULL,
  `company_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned DEFAULT NULL,
  `utility` varchar(20) NOT NULL,
  `reading` decimal(14,3) NOT NULL,
  `previous_reading` decimal(14,3) DEFAULT NULL,
  `consumption` decimal(14,3) DEFAULT NULL,         -- after the meter multiplier
  `rate_applied` decimal(12,2) DEFAULT NULL,
  `charge` decimal(12,2) DEFAULT NULL,
  `read_at` timestamp NOT NULL,
  `source` varchar(12) NOT NULL,                    -- reader_app | smart | api | web
  `recorded_by` bigint unsigned DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'accepted', -- accepted | flagged | rejected
  `flag_reason` varchar(120) DEFAULT NULL,
  `client_ref` varchar(80) DEFAULT NULL,            -- offline-sync / device message id (idempotency)
  `water_reading_id` bigint unsigned DEFAULT NULL,  -- the monthly billing row it produced
  `note` varchar(255) DEFAULT NULL,
  `raw` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meter_readings_meter_client_ref_unique` (`meter_id`, `client_ref`),
  KEY `meter_readings_meter_read_at_index` (`meter_id`, `read_at`),
  KEY `meter_readings_company_status_index` (`company_id`, `status`),
  CONSTRAINT `meter_readings_meter_id_foreign` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
