-- scharge: paid modules per company (App\Support\Modules). Each company gets the modules of its active plan
-- (subscription_plans.features -> "modules"; a plan without that list includes every module, so nobody loses anything
-- on deploy). A sysadmin can switch a module on or off for one company from Admin › Companies › {company} › Modules,
-- optionally until a date; after that date the plan default applies again. Every change is logged.
-- The app works without these tables (every module stays on).
-- Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_20_company_modules.sql

CREATE TABLE IF NOT EXISTS `company_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `module` varchar(40) NOT NULL,
  `enabled` tinyint(1) NOT NULL COMMENT '1 = on, 0 = off, whatever the plan says',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'after this the plan default applies again',
  `note` varchar(255) NULL DEFAULT NULL,
  `changed_by` bigint unsigned NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_modules_company_module` (`company_id`, `module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_module_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `module` varchar(40) NOT NULL COMMENT 'module key, or "plan:<id>" for a plan default change',
  `from_state` varchar(20) NULL DEFAULT NULL COMMENT 'plan | on | off',
  `to_state` varchar(20) NOT NULL COMMENT 'plan | on | off',
  `expires_at` timestamp NULL DEFAULT NULL,
  `note` varchar(255) NULL DEFAULT NULL,
  `user_id` bigint unsigned NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_module_logs_company` (`company_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
