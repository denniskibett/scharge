-- scharge: photos per house, shared with similar houses, shown on the public Open listings page.
-- Hand-applied SQL (not a migration). Apply AFTER 2026_11_09_rename_estates_to_properties.sql:
--   mysql scha_main < database/schema/2026_11_09_unit_photos.sql
-- Safe to re-run.
--
-- Who a photo applies to (`applies_to`):
--   unit     = only this house (unit_id)
--   type     = every house of the same unit_type in the property (e.g. all Two Bedrooms)
--   property = every house in the property (compound, gate, parking, amenities)
-- So similar houses share photos without copying files, and new units of that type get them too.

CREATE TABLE IF NOT EXISTS `unit_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `property_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned DEFAULT NULL,       -- the house it was taken in
  `unit_type` varchar(60) DEFAULT NULL,         -- copied from the unit for applies_to = type
  `applies_to` varchar(10) NOT NULL DEFAULT 'type',
  `path` varchar(255) NOT NULL,                 -- on the public disk
  `caption` varchar(140) DEFAULT NULL,
  `sort` int NOT NULL DEFAULT 0,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_photos_property_type_index` (`property_id`, `unit_type`),
  KEY `unit_photos_unit_id_index` (`unit_id`),
  CONSTRAINT `unit_photos_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `unit_photos_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
