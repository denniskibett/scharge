-- scharge: Growth hub (Sales CRM › Growth). Hand-applied SQL (not a migration). Needs 2026_11_09_site_analytics.sql first.
--   mysql scha_main < database/schema/2026_11_10_growth_hub.sql
-- Run ONCE: the three ALTERs fail with "Duplicate column name 'site'" if they were already applied
-- (release-growth-scharge.sh checks information_schema first). The CREATEs are safe to re-run.
--
-- 1. `site` on the analytics tables: which Sharet-family site a row came from (sharet, breve, aiyotickets,
--    eserviz, scharge, loans). Existing rows are scharge's own, hence the default.
-- 2. growth_seo_checks: the latest SEO audit per site (php artisan growth:seo-audit, daily). Replaced on every run.
-- 3. growth_todos: the owner's per-site checklist (Search Console, sitemap submitted, Business Profile, directories).

ALTER TABLE `site_page_views`
  ADD COLUMN `site` varchar(20) NOT NULL DEFAULT 'scharge' AFTER `id`,
  ADD KEY `site_page_views_site_created_index` (`site`, `created_at`);

ALTER TABLE `site_events`
  ADD COLUMN `site` varchar(20) NOT NULL DEFAULT 'scharge' AFTER `id`,
  ADD KEY `site_events_site_name_created_index` (`site`, `name`, `created_at`);

ALTER TABLE `site_clicks`
  ADD COLUMN `site` varchar(20) NOT NULL DEFAULT 'scharge' AFTER `id`,
  ADD KEY `site_clicks_site_created_index` (`site`, `created_at`);

CREATE TABLE IF NOT EXISTS `growth_seo_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site` varchar(20) NOT NULL,
  `kind` varchar(10) NOT NULL,                  -- home | robots | sitemap | page
  `url` varchar(500) NOT NULL,
  `status` smallint unsigned DEFAULT NULL,      -- HTTP status, NULL = no answer
  `response_ms` int unsigned DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description_length` smallint unsigned DEFAULT NULL,
  `canonical` varchar(500) DEFAULT NULL,
  `og_tags` varchar(255) DEFAULT NULL,          -- og:/twitter: properties found, comma separated
  `jsonld_types` varchar(255) DEFAULT NULL,     -- schema.org @type values, comma separated
  `h1_count` smallint unsigned DEFAULT NULL,
  `noindex` tinyint(1) NOT NULL DEFAULT 0,
  `sitemap_urls` int unsigned DEFAULT NULL,     -- sitemap rows only
  `findings` text,                              -- JSON [{"level":"red|amber|green","text":"…"}]
  `checked_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `growth_seo_checks_site_index` (`site`, `kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `growth_todos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site` varchar(20) NOT NULL,
  `item` varchar(40) NOT NULL,                  -- key from App\Support\Growth\SeoTodos::ITEMS
  `done` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `growth_todos_site_item_unique` (`site`, `item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
