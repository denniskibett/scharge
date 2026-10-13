-- Only for databases where 2026_10_13_crm_waitlist.sql was applied before this change.
-- Lets renter requests from Open listings (viewings, SMS alerts) be stored without a company.
ALTER TABLE crm_follow_ups MODIFY company_id BIGINT UNSIGNED NULL COMMENT 'NULL for renter requests (listing viewings, listing alerts)';
