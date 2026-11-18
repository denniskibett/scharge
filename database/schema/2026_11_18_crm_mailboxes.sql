-- scharge CRM inbox: the sharet.africa work mailboxes staff read and answer from CRM → Inbox.
-- A mailbox with user_id belongs to that account manager; user_id NULL is shared with all CRM staff.
-- The password is typed in by the mailbox owner (or a sysadmin) on the Inbox page and stored encrypted (APP_KEY).
-- Hand-applied SQL (not a migration). Run ONCE:  mysql scha_main < database/schema/2026_11_18_crm_mailboxes.sql

CREATE TABLE IF NOT EXISTS `crm_mailboxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `address` varchar(191) NOT NULL,
  `label` varchar(120) DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `password` text DEFAULT NULL,
  `connected_at` timestamp NULL DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_mailboxes_address_unique` (`address`),
  KEY `crm_mailboxes_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The account managers' own work mailboxes (matched by their login email, so ids may differ per database).
INSERT IGNORE INTO `crm_mailboxes` (`address`, `label`, `user_id`, `created_at`, `updated_at`)
  SELECT 'wkamau@sharet.africa', 'Waithaka (work)', id, NOW(), NOW() FROM users WHERE email = 'waithakakamau44@gmail.com' LIMIT 1;
INSERT IGNORE INTO `crm_mailboxes` (`address`, `label`, `user_id`, `created_at`, `updated_at`)
  SELECT 'akenga@sharet.africa', 'Annete (work)', id, NOW(), NOW() FROM users WHERE email = 'sidiannete@gmail.com' LIMIT 1;
-- Shared team mailboxes.
INSERT IGNORE INTO `crm_mailboxes` (`address`, `label`, `user_id`, `created_at`, `updated_at`) VALUES
  ('sales@sharet.africa', 'Sales', NULL, NOW(), NOW()),
  ('onboarding@sharet.africa', 'Onboarding', NULL, NOW(), NOW()),
  ('support@sharet.africa', 'Support', NULL, NOW(), NOW());
