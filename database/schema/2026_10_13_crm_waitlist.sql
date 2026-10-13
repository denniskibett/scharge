-- ---------------------------------------------------------------------------
-- scharge: website waitlist, demo requests and CRM
--
-- MySQL 8 / MariaDB 10.4+. Run once, in this order, on the app database.
-- Turn it into migrations when you tidy up database/migrations; the models in
-- app/Modules/CRM/Models expect exactly these tables and columns.
--
-- Changes to existing tables: users (email nullable, country_id, lead_source).
-- New tables: countries, waitlist_entries, crm_companies, crm_company_user,
--             crm_demo_requests, crm_follow_ups, crm_activities, crm_feedback.
-- ---------------------------------------------------------------------------

-- 1. Countries -------------------------------------------------------------
CREATE TABLE countries (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100)    NOT NULL,
  iso2         CHAR(2)         NOT NULL,
  dial_code    VARCHAR(6)      NOT NULL,
  status       ENUM('live','next','waitlist') NOT NULL DEFAULT 'waitlist',
  sort_order   INT             NOT NULL DEFAULT 0,
  is_active    TINYINT(1)      NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NULL DEFAULT NULL,
  updated_at   TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY countries_iso2_unique (iso2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO countries (name, iso2, dial_code, status, sort_order, created_at, updated_at) VALUES
  ('Kenya',        'KE', '254', 'live',     1,  NOW(), NOW()),
  ('Uganda',       'UG', '256', 'next',     2,  NOW(), NOW()),
  ('Tanzania',     'TZ', '255', 'next',     3,  NOW(), NOW()),
  ('Rwanda',       'RW', '250', 'next',     4,  NOW(), NOW()),
  ('Ethiopia',     'ET', '251', 'waitlist', 5,  NOW(), NOW()),
  ('Ghana',        'GH', '233', 'waitlist', 6,  NOW(), NOW()),
  ('Nigeria',      'NG', '234', 'waitlist', 7,  NOW(), NOW()),
  ('South Africa', 'ZA', '27',  'waitlist', 8,  NOW(), NOW()),
  ('Zambia',       'ZM', '260', 'waitlist', 9,  NOW(), NOW());

-- 2. Users: leads from the website can arrive with a phone number only ------
ALTER TABLE users
  MODIFY email VARCHAR(255) NULL,
  ADD COLUMN country_id  BIGINT UNSIGNED NULL AFTER country,
  ADD COLUMN lead_source VARCHAR(50)     NULL AFTER country_id,
  ADD KEY users_country_id_index (country_id),
  ADD KEY users_phone_index (phone),
  ADD CONSTRAINT users_country_id_foreign FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE SET NULL;

-- 3. Prospect companies (kept apart from `companies`, which are paying customers)
CREATE TABLE crm_companies (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(255)    NOT NULL,
  name_key           VARCHAR(255)    NOT NULL COMMENT 'lowercase letters/digits only, legal suffixes removed; used to match duplicates',
  domain             VARCHAR(255)    NULL     COMMENT 'website or work-email domain; used to match duplicates',
  tier               TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '1 = highest priority',
  business_type      VARCHAR(150)    NULL,
  portfolio          TEXT            NULL,
  scale_signal       TEXT            NULL,
  opportunity        TEXT            NULL     COMMENT 'scharge sales angle',
  target_role        VARCHAR(255)    NULL,
  route              VARCHAR(100)    NULL     COMMENT 'direct sale, channel partnership, ...',
  phone              VARCHAR(30)     NULL,
  phone_alt          VARCHAR(30)     NULL,
  email              VARCHAR(255)    NULL,
  website            VARCHAR(255)    NULL,
  linkedin           VARCHAR(255)    NULL,
  decision_maker     VARCHAR(255)    NULL,
  current_system     VARCHAR(255)    NULL,
  estimated_units    INT UNSIGNED    NULL,
  country_id         BIGINT UNSIGNED NULL,
  stage              VARCHAR(30)     NOT NULL DEFAULT 'new' COMMENT 'new, contacted, qualified, demo_booked, proposal, negotiation, won, lost, nurture',
  owner_id           BIGINT UNSIGNED NULL,
  next_action        VARCHAR(255)    NULL,
  next_action_at     DATETIME        NULL,
  last_contacted_at  DATETIME        NULL,
  lost_reason        VARCHAR(255)    NULL,
  company_id         BIGINT UNSIGNED NULL     COMMENT 'set when the prospect becomes a paying customer',
  source             VARCHAR(255)    NULL,
  notes              TEXT            NULL,
  needs_verification TINYINT(1)      NOT NULL DEFAULT 0,
  created_at         TIMESTAMP NULL DEFAULT NULL,
  updated_at         TIMESTAMP NULL DEFAULT NULL,
  deleted_at         TIMESTAMP NULL DEFAULT NULL,
  KEY crm_companies_name_key_index (name_key),
  KEY crm_companies_domain_index (domain),
  KEY crm_companies_stage_index (stage),
  KEY crm_companies_next_action_at_index (next_action_at),
  CONSTRAINT crm_companies_country_id_foreign FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE SET NULL,
  CONSTRAINT crm_companies_owner_id_foreign   FOREIGN KEY (owner_id)   REFERENCES users (id)     ON DELETE SET NULL,
  CONSTRAINT crm_companies_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Pivot: people <-> the companies they represent ----------------------
CREATE TABLE crm_company_user (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  crm_company_id BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,
  job_title      VARCHAR(150)    NULL,
  is_primary     TINYINT(1)      NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NULL DEFAULT NULL,
  updated_at     TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY crm_company_user_unique (crm_company_id, user_id),
  CONSTRAINT crm_company_user_company_foreign FOREIGN KEY (crm_company_id) REFERENCES crm_companies (id) ON DELETE CASCADE,
  CONSTRAINT crm_company_user_user_foreign    FOREIGN KEY (user_id)        REFERENCES users (id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Pivot: people <-> the countries they want scharge in (the waitlist) -----
CREATE TABLE waitlist_entries (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id        BIGINT UNSIGNED NOT NULL,
  country_id     BIGINT UNSIGNED NULL,
  country_name   VARCHAR(100)    NULL     COMMENT 'only when "Another country" was chosen',
  crm_company_id BIGINT UNSIGNED NULL,
  status         ENUM('waiting','contacted','invited','converted','unsubscribed') NOT NULL DEFAULT 'waiting',
  source         VARCHAR(50)     NOT NULL DEFAULT 'website',
  notified_at    TIMESTAMP NULL DEFAULT NULL,
  ip_address     VARCHAR(45)     NULL,
  user_agent     VARCHAR(255)    NULL,
  created_at     TIMESTAMP NULL DEFAULT NULL,
  updated_at     TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY waitlist_entries_user_country_unique (user_id, country_id),
  KEY waitlist_entries_status_index (status),
  CONSTRAINT waitlist_entries_user_foreign    FOREIGN KEY (user_id)        REFERENCES users (id)         ON DELETE CASCADE,
  CONSTRAINT waitlist_entries_country_foreign FOREIGN KEY (country_id)     REFERENCES countries (id)     ON DELETE SET NULL,
  CONSTRAINT waitlist_entries_company_foreign FOREIGN KEY (crm_company_id) REFERENCES crm_companies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. "Book a demo" requests ----------------------------------------------
CREATE TABLE crm_demo_requests (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id        BIGINT UNSIGNED NULL,
  crm_company_id BIGINT UNSIGNED NULL,
  country_id     BIGINT UNSIGNED NULL,
  project_type   VARCHAR(100)    NULL,
  units          INT UNSIGNED    NULL,
  message        TEXT            NULL,
  status         VARCHAR(20)     NOT NULL DEFAULT 'new' COMMENT 'new, scheduled, completed, no_show, cancelled',
  scheduled_at   DATETIME        NULL,
  source_page    VARCHAR(255)    NULL,
  ip_address     VARCHAR(45)     NULL,
  user_agent     VARCHAR(255)    NULL,
  created_at     TIMESTAMP NULL DEFAULT NULL,
  updated_at     TIMESTAMP NULL DEFAULT NULL,
  KEY crm_demo_requests_status_index (status),
  CONSTRAINT crm_demo_requests_user_foreign    FOREIGN KEY (user_id)        REFERENCES users (id)         ON DELETE SET NULL,
  CONSTRAINT crm_demo_requests_company_foreign FOREIGN KEY (crm_company_id) REFERENCES crm_companies (id) ON DELETE SET NULL,
  CONSTRAINT crm_demo_requests_country_foreign FOREIGN KEY (country_id)     REFERENCES countries (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Follow-ups (the "don't lose them" list) ------------------------------
CREATE TABLE crm_follow_ups (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  crm_company_id  BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NULL     COMMENT 'contact person',
  assigned_to     BIGINT UNSIGNED NULL,
  created_by      BIGINT UNSIGNED NULL,
  demo_request_id BIGINT UNSIGNED NULL,
  type            VARCHAR(20)     NOT NULL DEFAULT 'call' COMMENT 'call, email, whatsapp, meeting, task',
  title           VARCHAR(255)    NOT NULL,
  notes           TEXT            NULL,
  due_at          DATETIME        NOT NULL,
  completed_at    DATETIME        NULL,
  outcome         VARCHAR(255)    NULL,
  created_at      TIMESTAMP NULL DEFAULT NULL,
  updated_at      TIMESTAMP NULL DEFAULT NULL,
  KEY crm_follow_ups_due_at_index (due_at),
  KEY crm_follow_ups_completed_at_index (completed_at),
  CONSTRAINT crm_follow_ups_company_foreign  FOREIGN KEY (crm_company_id)  REFERENCES crm_companies (id)     ON DELETE CASCADE,
  CONSTRAINT crm_follow_ups_user_foreign     FOREIGN KEY (user_id)         REFERENCES users (id)             ON DELETE SET NULL,
  CONSTRAINT crm_follow_ups_assigned_foreign FOREIGN KEY (assigned_to)     REFERENCES users (id)             ON DELETE SET NULL,
  CONSTRAINT crm_follow_ups_creator_foreign  FOREIGN KEY (created_by)      REFERENCES users (id)             ON DELETE SET NULL,
  CONSTRAINT crm_follow_ups_demo_foreign     FOREIGN KEY (demo_request_id) REFERENCES crm_demo_requests (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Activity timeline ----------------------------------------------------
CREATE TABLE crm_activities (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  crm_company_id BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NULL COMMENT 'contact person',
  created_by     BIGINT UNSIGNED NULL,
  type           VARCHAR(30)     NOT NULL COMMENT 'note, call, email, whatsapp, meeting, demo_request, waitlist, stage_change, follow_up, feedback, import',
  summary        VARCHAR(255)    NOT NULL,
  body           TEXT            NULL,
  meta           JSON            NULL,
  occurred_at    DATETIME        NOT NULL,
  created_at     TIMESTAMP NULL DEFAULT NULL,
  updated_at     TIMESTAMP NULL DEFAULT NULL,
  KEY crm_activities_company_occurred_index (crm_company_id, occurred_at),
  CONSTRAINT crm_activities_company_foreign FOREIGN KEY (crm_company_id) REFERENCES crm_companies (id) ON DELETE CASCADE,
  CONSTRAINT crm_activities_user_foreign    FOREIGN KEY (user_id)        REFERENCES users (id)         ON DELETE SET NULL,
  CONSTRAINT crm_activities_creator_foreign FOREIGN KEY (created_by)     REFERENCES users (id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Feedback loop (0-10 score; low scores open a recovery follow-up) --------
CREATE TABLE crm_feedback (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  crm_company_id  BIGINT UNSIGNED NULL,
  user_id         BIGINT UNSIGNED NULL,
  demo_request_id BIGINT UNSIGNED NULL,
  follow_up_id    BIGINT UNSIGNED NULL COMMENT 'recovery follow-up opened for a low score',
  requested_by    BIGINT UNSIGNED NULL,
  token           CHAR(40)        NOT NULL,
  context         VARCHAR(30)     NOT NULL DEFAULT 'general' COMMENT 'demo, onboarding, support, general',
  score           TINYINT UNSIGNED NULL COMMENT '0-10: how likely to recommend scharge',
  comment         TEXT            NULL,
  requested_at    DATETIME        NOT NULL,
  submitted_at    DATETIME        NULL,
  created_at      TIMESTAMP NULL DEFAULT NULL,
  updated_at      TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY crm_feedback_token_unique (token),
  CONSTRAINT crm_feedback_company_foreign   FOREIGN KEY (crm_company_id)  REFERENCES crm_companies (id)     ON DELETE SET NULL,
  CONSTRAINT crm_feedback_user_foreign      FOREIGN KEY (user_id)         REFERENCES users (id)             ON DELETE SET NULL,
  CONSTRAINT crm_feedback_demo_foreign      FOREIGN KEY (demo_request_id) REFERENCES crm_demo_requests (id) ON DELETE SET NULL,
  CONSTRAINT crm_feedback_follow_up_foreign FOREIGN KEY (follow_up_id)    REFERENCES crm_follow_ups (id)    ON DELETE SET NULL,
  CONSTRAINT crm_feedback_requester_foreign FOREIGN KEY (requested_by)    REFERENCES users (id)             ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
