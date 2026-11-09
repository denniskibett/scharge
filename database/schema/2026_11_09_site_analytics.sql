-- scharge: first-party website analytics (page views, clicks for the heatmap, conversions).
-- Hand-applied SQL (not a migration):
--   mysql scha_main < database/schema/2026_11_09_site_analytics.sql
-- Safe to re-run. Until it is applied the website keeps working and simply records nothing;
-- Sales CRM > Website analytics shows a reminder instead of the report.
--
-- Privacy: no cookies of our own and no IP addresses are stored. `visitor` is a hash of
-- IP + browser + date + APP_KEY, so it changes every day and cannot be traced back to a person.
-- Paths are stored without query strings, and token-like path segments are masked.
-- Visitors who send Do-Not-Track or Global Privacy Control are not recorded.

CREATE TABLE IF NOT EXISTS `site_page_views` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visitor` char(16) NOT NULL,
  `view_key` char(16) NOT NULL,                 -- random per page load, to attach time-on-page later
  `path` varchar(255) NOT NULL,
  `referrer_host` varchar(120) DEFAULT NULL,    -- external sites only
  `utm_source` varchar(80) DEFAULT NULL,
  `utm_medium` varchar(80) DEFAULT NULL,
  `utm_campaign` varchar(120) DEFAULT NULL,
  `country` char(2) DEFAULT NULL,               -- from Cloudflare's CF-IPCountry when present
  `device` varchar(10) NOT NULL,                -- mobile | tablet | desktop
  `browser` varchar(20) DEFAULT NULL,
  `viewport_w` smallint unsigned DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `scroll_pct` tinyint unsigned DEFAULT NULL,
  `is_staff` tinyint(1) NOT NULL DEFAULT 0,     -- signed-in users, hidden from the report by default
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_page_views_view_key_unique` (`view_key`),
  KEY `site_page_views_created_at_index` (`created_at`),
  KEY `site_page_views_path_index` (`path`),
  KEY `site_page_views_visitor_index` (`visitor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_clicks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(255) NOT NULL,
  `device` varchar(10) NOT NULL,
  `x_permille` smallint unsigned NOT NULL,      -- 0-1000 of page width
  `y` int unsigned NOT NULL,                    -- pixels from the top of the page
  `page_h` int unsigned NOT NULL,
  `target` varchar(120) DEFAULT NULL,           -- link/button text, for "most clicked"
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `site_clicks_path_device_created_index` (`path`, `device`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Key moments: form conversions (recorded by the server when a form is accepted) and
-- browser events such as opening a listing. Never names, phones or emails.
--   name: conversion | listing_view | outbound | ...
--   label: which form (demo, waitlist, api_keys, listing_request, listing_alert, host_register)
--          or which listing title
-- Attribution (source/medium/campaign/landing_path) is the visitor's first touch this session.
CREATE TABLE IF NOT EXISTS `site_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `visitor` char(16) DEFAULT NULL,
  `source` varchar(120) DEFAULT NULL,           -- utm_source, else referrer host, else 'direct'
  `medium` varchar(80) DEFAULT NULL,
  `campaign` varchar(120) DEFAULT NULL,
  `landing_path` varchar(255) DEFAULT NULL,
  `device` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `site_events_name_created_index` (`name`, `created_at`),
  KEY `site_events_campaign_index` (`campaign`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
