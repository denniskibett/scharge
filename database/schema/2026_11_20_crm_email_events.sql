-- scharge CRM: every open and click of a cold email, with when, roughly where and on what device
-- (App\Modules\CRM\Support\EmailEngagement). One row per open (tracking image) or click (demo-form link).
-- Privacy (Kenya Data Protection Act 2019): only a shortened IP (IPv4 /24, IPv6 /48) is kept, never the full address;
-- place is town-level at most; rows older than 12 months are deleted daily. Opens fetched by Gmail's or Apple's
-- image proxies are marked in `via` and carry no place or device (those would be Google's or Apple's, not the reader's).
-- Hand-applied SQL (not a migration). Safe to re-run:
--   mysql scha_main < database/schema/2026_11_20_crm_email_events.sql

CREATE TABLE IF NOT EXISTS `crm_email_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `follow_up_id` bigint unsigned NOT NULL COMMENT 'the "email" crm_follow_ups row',
  `company_id` bigint unsigned NULL DEFAULT NULL,
  `kind` varchar(8) NOT NULL COMMENT 'open | click',
  `occurred_at` timestamp NOT NULL,
  `via` varchar(10) NOT NULL DEFAULT 'direct' COMMENT 'direct | gmail | apple | yahoo (mail-app image proxies)',
  `ip_prefix` varchar(45) NULL DEFAULT NULL COMMENT 'shortened: IPv4 a.b.c.0, IPv6 first 48 bits',
  `country` char(2) NULL DEFAULT NULL,
  `region` varchar(80) NULL DEFAULT NULL,
  `city` varchar(80) NULL DEFAULT NULL,
  `device` varchar(10) NULL DEFAULT NULL COMMENT 'phone | tablet | computer',
  `os` varchar(20) NULL DEFAULT NULL,
  `client` varchar(40) NULL DEFAULT NULL COMMENT 'mail app or browser',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_email_events_follow_up` (`follow_up_id`, `occurred_at`),
  KEY `crm_email_events_company` (`company_id`, `occurred_at`),
  KEY `crm_email_events_when` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
