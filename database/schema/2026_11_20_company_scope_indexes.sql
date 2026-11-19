-- scharge: indexes for company-scoped pages. Every company user's query now carries the company scope
-- (App\Traits\CompanyScoped, App\Support\CompanyAccess): "company_id IN (...)", or for older rows without company_id,
-- the tenancy -> unit -> property chain. These indexes make that filter, and the usual status / date filters, an index
-- lookup instead of a scan, so company pages stay fast as one shared database grows.
--
-- Hand-applied SQL (not a migration). Read it first, then run ONCE (works on MariaDB 10.3 (live) and MySQL 8+):
--   mysql --force scha_main < database/schema/2026_11_20_company_scope_indexes.sql
-- One statement per index: with --force an index that already exists only skips that line ("Duplicate key name").
-- Adding an index on tables this size (thousands of rows) takes well under a second each; InnoDB adds them online.
--
-- Measured on a copy of the local scha_main (Danaff, company 1, ~1.6k bills): rows read per query, before and
-- after, and the EXPLAIN plans of the two heaviest company dashboard queries, are at the end of this file.

-- invoices: no company_id or status index at all. The scope, the dashboard's open bills / arrears and the per-tenancy
-- totals all filter on these.
ALTER TABLE `invoices` ADD INDEX `invoices_company_status_index` (`company_id`, `status`);
ALTER TABLE `invoices` ADD INDEX `invoices_company_created_index` (`company_id`, `created_at`);
ALTER TABLE `invoices` ADD INDEX `invoices_tenancy_status_index` (`tenancy_id`, `status`);
ALTER TABLE `invoices` ADD INDEX `invoices_status_created_index` (`status`, `created_at`);

-- invoice_items: scoped by company_id, else through the invoice.
ALTER TABLE `invoice_items` ADD INDEX `invoice_items_company_id_index` (`company_id`);

-- payments: scoped through the invoice; money pages add status (and the month).
ALTER TABLE `payments` ADD INDEX `payments_invoice_status_index` (`invoice_id`, `status`);

-- tenancies: the scope's first test is company_id; lists filter by status; the tenants page asks per tenant.
-- tenant_id had no index at all (every tenant row scanned all tenancies).
ALTER TABLE `tenancies` ADD INDEX `tenancies_company_status_index` (`company_id`, `status`);
ALTER TABLE `tenancies` ADD INDEX `tenancies_tenant_status_index` (`tenant_id`, `status`);
ALTER TABLE `tenancies` ADD INDEX `tenancies_unit_status_index` (`unit_id`, `status`);

-- tenants: scoped by company_id (no index before).
ALTER TABLE `tenants` ADD INDEX `tenants_company_id_index` (`company_id`);

-- units: the property scope asks "buildings where this company has units" (company_id -> property_id), and the
-- dashboard counts units by status.
ALTER TABLE `units` ADD INDEX `units_company_property_index` (`company_id`, `property_id`);
ALTER TABLE `units` ADD INDEX `units_company_status_index` (`company_id`, `status`);

-- unit_managements: units and buildings a BnB host company manages (company_id + status -> unit_id), in every
-- unit and property scope.
ALTER TABLE `unit_managements` ADD INDEX `unit_managements_company_status_unit_index` (`company_id`, `status`, `unit_id`);

-- water_readings: scoped by company_id (no index before), listed newest first.
ALTER TABLE `water_readings` ADD INDEX `water_readings_company_date_index` (`company_id`, `reading_date`);

-- meter_readings: per unit history.
ALTER TABLE `meter_readings` ADD INDEX `meter_readings_unit_read_at_index` (`unit_id`, `read_at`);

-- maintenances: open repairs per company.
ALTER TABLE `maintenances` ADD INDEX `maintenances_company_status_index` (`company_id`, `status`);

-- expenses: per company per month (reports).
ALTER TABLE `expenses` ADD INDEX `expenses_company_date_index` (`company_id`, `expense_date`);

-- rent_messages: Rent > Messages, per company newest first.
ALTER TABLE `rent_messages` ADD INDEX `rent_messages_company_created_index` (`company_id`, `created_at`);

-- sms_logs (no company_id column, only campaign_id): SMS history and delivery checks by date, status and phone.
ALTER TABLE `sms_logs` ADD INDEX `sms_logs_created_at_index` (`created_at`);
ALTER TABLE `sms_logs` ADD INDEX `sms_logs_status_created_index` (`status`, `created_at`);
ALTER TABLE `sms_logs` ADD INDEX `sms_logs_recipient_phone_index` (`recipient_phone`);

-- crm_follow_ups (CRM leads' tasks): "My day" / Follow-ups per account manager by status and due date. The company
-- side (company_id, status, due_at) is already indexed (2026_11_19_crm_speed.sql); CRM leads themselves are
-- companies rows, already indexed by lifecycle / crm_stage / owner_id.
ALTER TABLE `crm_follow_ups` ADD INDEX `crm_follow_ups_assigned_status_due_index` (`assigned_to`, `status`, `due_at`);

-- To undo one: ALTER TABLE `<table>` DROP INDEX `<name>`;

-- ---------------------------------------------------------------------------------------------------------------
-- Measurements (InnoDB rows read per query, Handler_read_*; deterministic, unlike timings on a busy laptop).
-- Queries are what the code builds for a Danaff admin (company 1) with the company scope on.
-- before = today's indexes | after = this file | + backfill = this file and 2026_11_20_backfill_company_ids.sql
-- before |  after | + fill | query
--   2259 |    603 |    287 | Q1 open bills, newest first (dashboard / Invoices)
--      1 |      1 |      1 | Q2 rent collected this month (dashboard)
--   2259 |    603 |    287 | Q3 arrears total (dashboard)
--    393 |      1 |      1 | Q4 a tenant's active tenancies (tenants list, per row)
--  17736 |    853 |    453 | Q5 tenants page
--   2550 |   2550 |   2550 | Q6 vacant units (dashboard)
--     41 |     41 |     41 | Q7 latest water readings
--   1488 |   1119 |   1119 | Q8 properties list
--      4 |      4 |      2 | Q9 bills of one tenancy (tenancy page, per row)
--
-- Q6 (vacant units) stays a scan of units: the unit scope is four OR'd rules (own, own buildings, hosted, agency),
-- which no single index serves. Fine at 1.3k units; past ~50k units precompute the company's unit ids per request.
-- Q8 (properties) was 7 rows before only because it had no company scope and listed every company's buildings.
--
-- EXPLAIN, before:
--   Q1 open bills, newest first (dashboard / Invoices)
--     rows read: 2259
--     invoices     type=ALL    key=NULL                                     rows=1570
--     tenancies    type=eq_ref key=PRIMARY                                  rows=1
--     units        type=ref_or_null key=units_company_id_index                   rows=369
--     properties   type=ref    key=properties_company_id_index              rows=2
--   Q5 tenants page
--     rows read: 17736
--     tenants      type=ALL    key=NULL                                     rows=404
--     tenancies    type=ref_or_null key=tenancies_company_id_index               rows=390
--     units        type=ref_or_null key=units_company_id_index                   rows=369
--     properties   type=ref    key=properties_company_id_index              rows=2
--
-- EXPLAIN, after:
--   Q1 open bills, newest first (dashboard / Invoices)
--     rows read: 603
--     invoices     type=range  key=invoices_status_created_index            rows=286
--     tenancies    type=eq_ref key=PRIMARY                                  rows=1
--     units        type=unique_subquery key=PRIMARY                                  rows=1
--     properties   type=ref    key=properties_company_id_index              rows=2
--   Q5 tenants page
--     rows read: 853
--     tenants      type=ALL    key=NULL                                     rows=404
--     tenancies    type=ref    key=tenancies_tenant_status_index            rows=1
--     units        type=ref_or_null key=units_company_property_index             rows=369
--     properties   type=ref    key=properties_company_id_index              rows=2

