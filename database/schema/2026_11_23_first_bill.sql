-- First bill for new accounts (owner, 23 Nov 2026): scharge staff set each company's price per unit; once the company
-- has units and a price, App\Support\FirstBill issues one month's bill, due in 7 days, and paying it opens 3 months.
-- Hand SQL (no migrations). Safe to run twice. MariaDB 10.3 (live) and MySQL 8+.
ALTER TABLE companies
  ADD COLUMN IF NOT EXISTS price_per_unit DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS price_set_by BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS price_set_at TIMESTAMP NULL;
