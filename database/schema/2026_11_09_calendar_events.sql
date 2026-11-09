-- scharge: internal meetings and reminders on /calendar.
-- Hand-applied SQL (not a migration). Safe to re-run:
--   mysql scha_main < database/schema/2026_11_09_calendar_events.sql
--
-- visibility: company = everyone in the company sees it; staff = scharge staff (company_id NULL);
--             attendees = only the creator and the people invited.

CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,       -- NULL = scharge's own team
  `title` varchar(160) NOT NULL,
  `description` text,
  `location` varchar(255) DEFAULT NULL,            -- room, address or a meeting link
  `kind` varchar(12) NOT NULL DEFAULT 'meeting',   -- meeting | reminder
  `visibility` varchar(12) NOT NULL DEFAULT 'attendees',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calendar_events_company_starts_index` (`company_id`, `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendar_event_attendees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calendar_event_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'invited', -- invited | accepted | declined
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `calendar_event_attendees_unique` (`calendar_event_id`, `user_id`),
  KEY `calendar_event_attendees_user_index` (`user_id`),
  CONSTRAINT `calendar_event_attendees_event_foreign` FOREIGN KEY (`calendar_event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
