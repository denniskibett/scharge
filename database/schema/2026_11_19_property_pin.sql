-- scharge: a property's GPS pin (inside Kenya), set from the Add / Edit property form (use my location, or drag the pin
-- on the map) or from the CSV onboarding `gps` column. Used to tell apart buildings with the same name and to match
-- the same building typed two ways. The app works without these columns (it just does not save a pin).
-- Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_19_property_pin.sql

ALTER TABLE `properties`
  ADD COLUMN `lat` decimal(10,7) NULL DEFAULT NULL,
  ADD COLUMN `lng` decimal(10,7) NULL DEFAULT NULL,
  ADD COLUMN `pin_source` varchar(12) NULL DEFAULT NULL COMMENT 'device | map | typed | csv',
  ADD COLUMN `pinned_at` timestamp NULL DEFAULT NULL,
  ADD KEY `properties_lat_lng` (`lat`, `lng`);
