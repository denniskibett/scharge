-- scharge: Claude assistant (Integrations › Claude assistant; docs/ASSISTANT.md). Every reply, follow-up and clean-up fix
-- Claude prepares waits here until a person sends it (or applies it). company_id NULL = scharge's own (CRM, billing).
-- Hand-applied SQL (not a migration). Run ONCE:
--   mysql scha_main < database/schema/2026_11_20_ai_drafts.sql

CREATE TABLE IF NOT EXISTS `ai_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NULL DEFAULT NULL,
  `kind` varchar(30) NOT NULL COMMENT 'prospect_reply | prospect_followup | tenant_email | arrears | billing | cleanup',
  `status` varchar(12) NOT NULL DEFAULT 'draft' COMMENT 'draft | sent | applied | discarded | failed',
  `channel` varchar(12) NOT NULL DEFAULT 'email' COMMENT 'email | sms | whatsapp | action',
  `source_ref` varchar(120) NOT NULL COMMENT 'what it answers, e.g. arrears:123:2026-W47 (one draft per thing)',
  `to_name` varchar(190) NULL DEFAULT NULL,
  `to_address` varchar(190) NULL DEFAULT NULL,
  `subject` varchar(255) NULL DEFAULT NULL,
  `body` text NULL,
  `note` varchar(500) NULL DEFAULT NULL COMMENT 'Claude''s note for the reviewer',
  `context` json NULL COMMENT 'facts Claude was given, target ids, clean-up action',
  `model` varchar(40) NULL DEFAULT NULL,
  `input_tokens` int unsigned NULL DEFAULT NULL,
  `output_tokens` int unsigned NULL DEFAULT NULL,
  `error` varchar(500) NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_drafts_once` (`source_ref`),
  KEY `ai_drafts_queue` (`company_id`, `status`, `kind`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
