-- ---------------------------------------------------------------------------
-- scharge: CRM, website demo requests and waitlist
--
-- MySQL 8 / MariaDB 10.4+. Run once on the app database. Turn it into migrations
-- when you tidy up database/migrations.
--
--   companies         + lifecycle: prospect | customer | churned (existing rows stay customers)
--                     + CRM columns; sales-sheet details live in the crm_profile JSON
--   users             email may be empty (phone-only leads), + country_code, lead_source
--   crm_follow_ups    NEW: the one pivot (person <-> company). Every contact link, call,
--                     note, demo, API-key request and feedback request is a row with a
--                     type and a status. Renter requests from Open listings have no company.
--   waitlist_entries  NEW: pivot (person <-> country we are not in yet), incl. demo details,
--                     converted into follow-ups when we launch in that country.
--
-- Countries are ISO 3166 alpha-2 codes (KE, UG, ...) from the pragmarx/countries package.
-- ---------------------------------------------------------------------------

-- 1. Companies: prospects and customers in one table ----------------------
ALTER TABLE companies
  ADD COLUMN lifecycle    ENUM('prospect','customer','churned') NOT NULL DEFAULT 'customer' AFTER is_active,
  ADD COLUMN crm_stage    VARCHAR(30)      NULL COMMENT 'new, contacted, qualified, demo_booked, proposal, negotiation, won, lost, nurture; NULL = not in the CRM' AFTER lifecycle,
  ADD COLUMN tier         TINYINT UNSIGNED NULL COMMENT '1 = highest priority' AFTER crm_stage,
  ADD COLUMN owner_id     BIGINT UNSIGNED  NULL COMMENT 'scharge staff member who owns the account' AFTER tier,
  ADD COLUMN country_code CHAR(2)          NULL AFTER owner_id,
  ADD COLUMN domain       VARCHAR(255)     NULL COMMENT 'website / work-email domain, for duplicate matching' AFTER country_code,
  ADD COLUMN name_key     VARCHAR(255)     NULL COMMENT 'normalised name, for duplicate matching' AFTER domain,
  ADD COLUMN source       VARCHAR(255)     NULL AFTER name_key,
  ADD COLUMN crm_profile  JSON             NULL COMMENT 'website, phone_alt, business_type, portfolio, opportunity, target_role, ...' AFTER source,
  ADD KEY companies_lifecycle_index (lifecycle),
  ADD KEY companies_crm_stage_index (crm_stage),
  ADD KEY companies_domain_index (domain),
  ADD KEY companies_name_key_index (name_key),
  ADD CONSTRAINT companies_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL;

-- `php artisan crm:import` fills name_key for existing customers first, so a prospect with
-- the same name is matched to the customer instead of being added twice.

-- 2. Users: website leads can arrive with a phone number only -------------
ALTER TABLE users
  MODIFY email VARCHAR(255) NULL,
  ADD COLUMN country_code CHAR(2)     NULL AFTER country,
  ADD COLUMN lead_source  VARCHAR(50) NULL AFTER country_code,
  ADD KEY users_phone_index (phone);

-- 3. The CRM pivot: person <-> company, one row per thing that happens -----
CREATE TABLE crm_follow_ups (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  company_id  BIGINT UNSIGNED NULL COMMENT 'NULL for renter requests (listing viewings, listing alerts)',
  user_id     BIGINT UNSIGNED NULL COMMENT 'the contact person',
  assigned_to BIGINT UNSIGNED NULL COMMENT 'scharge staff member doing it',
  created_by  BIGINT UNSIGNED NULL,
  type        VARCHAR(20)  NOT NULL COMMENT 'contact, note, call, email, whatsapp, meeting, demo, api_keys, viewing, listing_alert, feedback, stage, import',
  status      VARCHAR(20)  NOT NULL DEFAULT 'open' COMMENT 'open, scheduled, done, missed, cancelled',
  title       VARCHAR(255) NOT NULL,
  notes       TEXT         NULL,
  due_at      DATETIME     NULL,
  done_at     DATETIME     NULL,
  outcome     VARCHAR(255) NULL,
  score       TINYINT UNSIGNED NULL COMMENT 'feedback rows: 0-10',
  token       CHAR(40)     NULL COMMENT 'feedback rows: private link',
  details     JSON         NULL COMMENT 'demo: project_type, units, message; contact: job_title; stage: from, to',
  created_at  TIMESTAMP NULL DEFAULT NULL,
  updated_at  TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY crm_follow_ups_token_unique (token),
  KEY crm_follow_ups_company_status_index (company_id, status),
  KEY crm_follow_ups_status_due_index (status, due_at),
  KEY crm_follow_ups_type_status_index (type, status),
  KEY crm_follow_ups_user_index (user_id),
  CONSTRAINT crm_follow_ups_company_foreign  FOREIGN KEY (company_id)  REFERENCES companies (id) ON DELETE CASCADE,
  CONSTRAINT crm_follow_ups_user_foreign     FOREIGN KEY (user_id)     REFERENCES users (id)     ON DELETE SET NULL,
  CONSTRAINT crm_follow_ups_assigned_foreign FOREIGN KEY (assigned_to) REFERENCES users (id)     ON DELETE SET NULL,
  CONSTRAINT crm_follow_ups_creator_foreign  FOREIGN KEY (created_by)  REFERENCES users (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Waitlist pivot: person <-> country we are not in yet (incl. demo details)
CREATE TABLE waitlist_entries (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  country_code CHAR(2)         NOT NULL,
  company_id   BIGINT UNSIGNED NULL,
  status       VARCHAR(20)     NOT NULL DEFAULT 'waiting' COMMENT 'waiting, demo_requested, invited, converted, unsubscribed',
  details      JSON            NULL COMMENT 'demo details (project_type, units, message), ip, user_agent',
  created_at   TIMESTAMP NULL DEFAULT NULL,
  updated_at   TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY waitlist_entries_user_country_unique (user_id, country_code),
  KEY waitlist_entries_country_status_index (country_code, status),
  CONSTRAINT waitlist_entries_user_foreign    FOREIGN KEY (user_id)    REFERENCES users (id)     ON DELETE CASCADE,
  CONSTRAINT waitlist_entries_company_foreign FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
