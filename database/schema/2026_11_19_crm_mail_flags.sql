-- scharge CRM: the e-mail flags campaigns and the Team / Campaigns pages filter on, as stored indexed columns (they used to
-- read crm_profile JSON for every prospect; crm:campaign runs these every 10 minutes). Same meaning as before:
--   email_dne     crm_profile.do_not_email is true
--   email_blocked do_not_email, email_no_mx or email_shared is true
--   email_sent    crm_profile.last_emailed_at is set
--   email_problem crm_profile.email_bounced or email_no_mx is true
-- Hand-applied SQL (not a migration); MariaDB 10.3+ and MySQL 8+. Run ONCE (after 2026_11_19_crm_class.sql):
--   mysql scha_main < database/schema/2026_11_19_crm_mail_flags.sql

ALTER TABLE `companies`
  ADD COLUMN `email_dne` tinyint(1) AS (IF(JSON_VALUE(`crm_profile`, '$.do_not_email') IN ('true', '1'), 1, 0)) STORED,
  ADD COLUMN `email_blocked` tinyint(1) AS (IF(JSON_VALUE(`crm_profile`, '$.do_not_email') IN ('true', '1')
      OR JSON_VALUE(`crm_profile`, '$.email_no_mx') IN ('true', '1') OR JSON_VALUE(`crm_profile`, '$.email_shared') IN ('true', '1'), 1, 0)) STORED,
  ADD COLUMN `email_sent` tinyint(1) AS (IF(COALESCE(JSON_VALUE(`crm_profile`, '$.last_emailed_at'), 'null') NOT IN ('null', ''), 1, 0)) STORED,
  ADD COLUMN `email_problem` tinyint(1) AS (IF(JSON_VALUE(`crm_profile`, '$.email_bounced') IN ('true', '1')
      OR JSON_VALUE(`crm_profile`, '$.email_no_mx') IN ('true', '1'), 1, 0)) STORED,
  ADD INDEX `companies_class_lifecycle_stage_index` (`crm_class`, `lifecycle`, `crm_stage`, `email_blocked`, `email_sent`);
