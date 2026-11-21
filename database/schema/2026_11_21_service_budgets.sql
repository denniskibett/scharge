-- Service-charge budgets (Budgets page): one budget per building per year, with its lines.
-- Hand SQL (no migrations). Safe to run twice.
CREATE TABLE IF NOT EXISTS service_budgets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  sinking_fund_percent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY service_budgets_property_year (property_id, year),
  KEY service_budgets_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_budget_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_budget_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  expense_category_id BIGINT UNSIGNED NULL,
  annual_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY service_budget_lines_budget (service_budget_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
