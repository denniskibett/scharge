-- scharge: fill in company_id on bills (and their tenancies and lines) that only reach their company through the
-- tenancy -> unit -> property chain. Until now Invoice::create() dropped company_id (it is not fillable), so most
-- bills, including every unpaid one, have it empty, and each company-scoped query falls back to that chain.
-- New bills now get it on create (Invoice / InvoiceItem `creating`); this fixes the old rows.
--
-- Hand-applied SQL (not a migration). NOT RUN. Review the counts first, take a backup, then run ONCE
-- (MariaDB 10.3 (live) and MySQL 8+):
--   mysqldump scha_main invoices tenancies invoice_items > /root/scha-backups/company-ids-$(date +%F).sql
--   mysql scha_main < database/schema/2026_11_20_backfill_company_ids.sql
-- Safe to run again: it only touches rows whose company_id is still NULL, and never changes a set one.
--
-- Local copy of scha_main on 2026-11-19: 1,570 bills; 1,269 with company_id NULL (all 284 unpaid ones), of which
-- 1,144 resolve through the tenancy and 125 through the unit, 0 through the property only, 0 unresolvable,
-- 0 set to a different company than their chain. 52 tenancies and 1,287 bill lines have company_id NULL.
-- payments has no company_id column: payments follow their bill (1,019 of 1,338 sit on bills with company_id NULL).

-- 1. Preview: what each step will change. Run these first; rows in the last query are left alone (no owner found).
SELECT COUNT(*) AS tenancies_to_fill FROM tenancies t
  JOIN units u ON u.id = t.unit_id LEFT JOIN properties p ON p.id = u.property_id
  WHERE t.company_id IS NULL AND COALESCE(u.company_id, p.company_id) IS NOT NULL;
SELECT COUNT(*) AS invoices_to_fill FROM invoices i
  JOIN tenancies t ON t.id = i.tenancy_id LEFT JOIN units u ON u.id = t.unit_id LEFT JOIN properties p ON p.id = u.property_id
  WHERE i.company_id IS NULL AND COALESCE(t.company_id, u.company_id, p.company_id) IS NOT NULL;
SELECT i.id, i.tenancy_id, i.status FROM invoices i
  LEFT JOIN tenancies t ON t.id = i.tenancy_id LEFT JOIN units u ON u.id = t.unit_id LEFT JOIN properties p ON p.id = u.property_id
  WHERE i.company_id IS NULL AND COALESCE(t.company_id, u.company_id, p.company_id) IS NULL;

START TRANSACTION;

-- 2. Tenancies: from the unit, else the unit's building (the same order as CompanyAccess::tenancyFilter).
UPDATE tenancies t
  JOIN units u ON u.id = t.unit_id
  LEFT JOIN properties p ON p.id = u.property_id
SET t.company_id = COALESCE(u.company_id, p.company_id)
WHERE t.company_id IS NULL AND COALESCE(u.company_id, p.company_id) IS NOT NULL;

-- 3. Bills: from the tenancy (now filled where it could be), else its unit, else the building.
UPDATE invoices i
  JOIN tenancies t ON t.id = i.tenancy_id
  LEFT JOIN units u ON u.id = t.unit_id
  LEFT JOIN properties p ON p.id = u.property_id
SET i.company_id = COALESCE(t.company_id, u.company_id, p.company_id)
WHERE i.company_id IS NULL AND COALESCE(t.company_id, u.company_id, p.company_id) IS NOT NULL;

-- 4. Bill lines: from their bill.
UPDATE invoice_items it
  JOIN invoices i ON i.id = it.invoice_id
SET it.company_id = i.company_id
WHERE it.company_id IS NULL AND i.company_id IS NOT NULL;

-- 5. Check, then COMMIT (or ROLLBACK if anything looks wrong). Expect 0 / 0 / 0 on the local copy.
SELECT
  (SELECT COUNT(*) FROM invoices WHERE company_id IS NULL) AS invoices_still_null,
  (SELECT COUNT(*) FROM invoice_items WHERE company_id IS NULL) AS lines_still_null,
  (SELECT COUNT(*) FROM tenancies WHERE company_id IS NULL) AS tenancies_still_null;

COMMIT;
