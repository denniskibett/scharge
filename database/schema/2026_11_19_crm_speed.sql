-- scharge CRM speed: 131k companies made every CRM page slow (Pipeline ~27 s, Sales funnel ~14 s), mostly from reading
-- crm_profile JSON row by row. Hand-applied SQL (not a migration); works on MariaDB 10.3 (live) and MySQL 8+. Run ONCE:
--   mysql scha_main < database/schema/2026_11_19_crm_speed.sql

-- "This prospect replied and is waiting for us": a stored copy of crm_profile.reply_pending that can be indexed.
ALTER TABLE `companies`
  ADD COLUMN `reply_pending` tinyint(1) AS (IF(JSON_VALUE(`crm_profile`, '$.reply_pending') IN ('true', '1'), 1, 0)) STORED,
  ADD INDEX `companies_reply_pending_index` (`reply_pending`),
  ADD INDEX `companies_lifecycle_stage_index` (`lifecycle`, `crm_stage`);

-- Next due / last contact per company for the Pipeline list.
ALTER TABLE `crm_follow_ups`
  ADD INDEX `crm_follow_ups_company_status_due_index` (`company_id`, `status`, `due_at`);
