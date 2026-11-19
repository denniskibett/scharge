-- scharge agents: a landlord (property's company) lets an agency market and let a property. Every agent in that agency
-- (role "agent", or the agency's admin) then sees it on their Agent dashboard: vacant homes, Open listings switch,
-- photos and renter enquiries. No tenant or money data. History kept (status revoked + revoked_at).
-- Hand-applied SQL (not a migration). Run ONCE, then `php artisan access:sync --keep-custom` (creates the agent role):
--   mysql scha_main < database/schema/2026_11_19_property_agents.sql

CREATE TABLE IF NOT EXISTS `property_agents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `agency_id` bigint unsigned NOT NULL,
  `can_list` tinyint(1) NOT NULL DEFAULT 1,
  `commission_pct` decimal(5,2) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `granted_by` bigint unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_agents_property_status` (`property_id`, `status`),
  KEY `property_agents_agency_status` (`agency_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
