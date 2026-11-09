-- scharge: gate passes (entry codes sent to guests/visitors), the visitor <-> unit pivot, and cars.
-- Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_09_gate_passes.sql
--
-- gate_passes     a code for one visit or stay at a unit. Issued by the unit's business (BnB host, manager,
--                 tenant) or the guard; sent to the guest by SMS/email; checked in at the gate (or by the guest
--                 from their phone); checked out by the guard, the host or the guest's own link (no login).
-- visitor_places  pivot visitor <-> unit (with its property and business): visit count, last in/out, last car.
--                 Updated on every pass, so a returning guest is recognised and the form is prefilled.
-- security        each actual entry (and exit) at the gate stays a row here, linked to its pass.

CREATE TABLE IF NOT EXISTS `gate_passes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,                          -- 6 digits, shown to the guard
  `token` varchar(64) NOT NULL,                         -- the guest's private link /pass/{token}
  `visitor_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `property_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,            -- the business the guest is visiting (BnB host / manager)
  `kind` varchar(16) NOT NULL DEFAULT 'visitor',        -- stay | visitor | delivery | contractor
  `status` varchar(16) NOT NULL DEFAULT 'issued',       -- issued | checked_in | checked_out | cancelled
  `guest_name` varchar(160) NOT NULL,
  `guest_phone` varchar(32) DEFAULT NULL,
  `guest_email` varchar(191) DEFAULT NULL,
  `guests_count` smallint unsigned NOT NULL DEFAULT 1,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `vehicle_desc` varchar(80) DEFAULT NULL,              -- e.g. "White Toyota Axio"
  `valid_from` timestamp NULL DEFAULT NULL,
  `valid_until` timestamp NULL DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `checked_in_by` bigint unsigned DEFAULT NULL,         -- user; NULL + method 'self' = the guest's phone
  `check_in_method` varchar(10) DEFAULT NULL,           -- guard | host | self
  `checked_out_at` timestamp NULL DEFAULT NULL,
  `checked_out_by` bigint unsigned DEFAULT NULL,
  `check_out_method` varchar(10) DEFAULT NULL,          -- guard | host | self
  `is_inside` tinyint(1) NOT NULL DEFAULT 0,            -- inside the gate right now (stays step in and out)
  `sms_sent_at` timestamp NULL DEFAULT NULL,
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gate_passes_token_unique` (`token`),
  KEY `gate_passes_code_status_index` (`code`, `status`),
  KEY `gate_passes_unit_status_index` (`unit_id`, `status`),
  KEY `gate_passes_company_index` (`company_id`),
  KEY `gate_passes_visitor_index` (`visitor_id`),
  KEY `gate_passes_valid_from_index` (`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_places` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visitor_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `property_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `visit_count` int unsigned NOT NULL DEFAULT 0,
  `first_visit_at` timestamp NULL DEFAULT NULL,
  `last_check_in_at` timestamp NULL DEFAULT NULL,
  `last_check_out_at` timestamp NULL DEFAULT NULL,
  `last_pass_id` bigint unsigned DEFAULT NULL,
  `last_vehicle_plate` varchar(20) DEFAULT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `visitor_places_visitor_unit_unique` (`visitor_id`, `unit_id`),
  KEY `visitor_places_unit_index` (`unit_id`),
  KEY `visitor_places_property_index` (`property_id`),
  KEY `visitor_places_company_index` (`company_id`),
  CONSTRAINT `visitor_places_visitor_id_foreign` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each gate entry links to its pass. access_time no longer jumps to "now" whenever the row is updated
-- (it had ON UPDATE CURRENT_TIMESTAMP, so checking someone out overwrote their check-in time).
ALTER TABLE `security`
  ADD COLUMN `gate_pass_id` bigint unsigned DEFAULT NULL AFTER `visitor_id`,
  ADD INDEX `security_gate_pass_id_index` (`gate_pass_id`),
  MODIFY `access_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Short-stay guests are a visitor type of their own.
ALTER TABLE `visitors`
  MODIFY `visitor_type` enum('family','employee','contractor','regular_guest','delivery','maintenance','one_time','guest') NOT NULL DEFAULT 'one_time';
