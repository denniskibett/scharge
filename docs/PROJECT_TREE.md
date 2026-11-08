# scharge — project tree

Generated 2026-11-08 from branch `claude/crm` @ `7494111`. Excludes vendor, node_modules, storage, build output and the 11 MB `scha_main.sql` dump.

```
app/
|-- Console/
|   |-- Commands/
|   |   `-- CreateTenantWallets.php
|   `-- Kernel.php
|-- Events/
|   `-- WalletUpdated.php
|-- Helpers/
|   |-- CompanyHelper.php
|   |-- CountryHelper.php
|   |-- PhoneHelper.php
|   |-- SubscriptionHelper.php
|   |-- SystemHelper.php
|   `-- helpers.php
|-- Http/
|   |-- Controllers/
|   |   |-- Admin/
|   |   |   |-- AccountManagerController.php
|   |   |   |-- CompanyController.php
|   |   |   |-- RoleController.php
|   |   |   |-- StaffController.php
|   |   |   |-- SystemController.php
|   |   |   `-- UserController.php
|   |   |-- Auth/
|   |   |   |-- AuthenticatedSessionController.php
|   |   |   |-- ConfirmablePasswordController.php
|   |   |   |-- EmailVerificationNotificationController.php
|   |   |   |-- EmailVerificationPromptController.php
|   |   |   |-- GoogleController.php
|   |   |   |-- NewPasswordController.php
|   |   |   |-- PasswordController.php
|   |   |   |-- PasswordResetLinkController.php
|   |   |   |-- RegisteredUserController.php
|   |   |   |-- VerificationController.php
|   |   |   `-- VerifyEmailController.php
|   |   |-- CleaningController.php
|   |   |-- Controller.php
|   |   |-- DashboardController.php
|   |   |-- EstateController.php
|   |   |-- ExpenseCategoryController.php
|   |   |-- ExpenseController.php
|   |   |-- HomeController.php
|   |   |-- InvoiceController.php
|   |   |-- MaintenanceController.php
|   |   |-- MpesaController.php
|   |   |-- PayeeController.php
|   |   |-- PaymentController.php
|   |   |-- ProfileController.php
|   |   |-- PublicInvoiceController.php
|   |   |-- ReportController.php
|   |   |-- SecurityController.php
|   |   |-- TenancyController.php
|   |   |-- TenantController.php
|   |   |-- UnitController.php
|   |   `-- WaterReadingController.php
|   |-- Middleware/
|   |   |-- CheckMaintenanceMode.php
|   |   |-- RoleMiddleware.php
|   |   |-- SetCompanyContext.php
|   |   `-- VerifyCsrfToken.php
|   |-- Requests/
|   |   |-- Auth/
|   |   |   `-- LoginRequest.php
|   |   `-- ProfileUpdateRequest.php
|   `-- Kernel.php
|-- Models/
|   |-- AccountManager.php
|   |-- Company.php
|   |-- County.php
|   |-- Estate.php
|   |-- Expense.php
|   |-- Invoice.php
|   |-- InvoiceItem.php
|   |-- Maintenance.php
|   |-- MpesaStk.php
|   |-- NetworkPrefix.php
|   |-- Payment.php
|   |-- RegisteredVisitor.php
|   |-- SecurityLog.php
|   |-- SmsLog.php
|   |-- SmsTemplate.php
|   |-- SubCounty.php
|   |-- System.php
|   |-- Tenancy.php
|   |-- Tenant.php
|   |-- Transaction.php
|   |-- Unit.php
|   |-- User.php
|   |-- Visitor.php
|   |-- Wallet.php
|   |-- WalletTransaction.php
|   `-- WaterReading.php
|-- Modules/
|   |-- CRM/
|   |   |-- Console/
|   |   |   `-- ImportProspects.php
|   |   |-- Controllers/
|   |   |   |-- Admin/
|   |   |   |   |-- CrmCompanyController.php
|   |   |   |   |-- CrmFollowUpController.php
|   |   |   |   `-- CrmWaitlistController.php
|   |   |   `-- Site/
|   |   |       |-- SiteFeedbackController.php
|   |   |       `-- SiteFormController.php
|   |   |-- Models/
|   |   |   |-- CrmFollowUp.php
|   |   |   `-- WaitlistEntry.php
|   |   |-- Providers/
|   |   |   `-- CRMServiceProvider.php
|   |   |-- Services/
|   |   |   |-- ContactService.php
|   |   |   |-- CrmImporter.php
|   |   |   `-- LeadService.php
|   |   |-- Support/
|   |   |   |-- Countries.php
|   |   |   `-- Normalizer.php
|   |   `-- routes.php
|   |-- Cleaning/
|   |   `-- routes.php
|   |-- Companies/
|   |   |-- routes/
|   |   `-- routes.php
|   |-- Expenses/
|   |   |-- Models/
|   |   |   |-- Expense.php
|   |   |   |-- ExpenseCategory.php
|   |   |   |-- ExpensePayment.php
|   |   |   `-- Payee.php
|   |   `-- routes.php
|   |-- Maintenance/
|   |   |-- Models/
|   |   |   `-- Maintenance.php
|   |   `-- routes.php
|   |-- Payments/
|   |   |-- Controllers/
|   |   |   `-- WalletController.php
|   |   |-- Models/
|   |   |   |-- CompanyPaymentMethod.php
|   |   |   |-- Invoice.php
|   |   |   |-- InvoiceItem.php
|   |   |   |-- Payment.php
|   |   |   |-- Transaction.php
|   |   |   `-- Transfer.php
|   |   |-- Services/
|   |   |   `-- WalletService.php
|   |   `-- routes.php
|   |-- Properties/
|   |   |-- Models/
|   |   |   |-- Estate.php
|   |   |   |-- HouseChecklist.php
|   |   |   |-- LeaseAgreement.php
|   |   |   |-- Tenancy.php
|   |   |   |-- TenancyCharge.php
|   |   |   `-- Unit.php
|   |   `-- routes.php
|   |-- Reports/
|   |   `-- routes.php
|   |-- SMS/
|   |   |-- Controllers/
|   |   |   |-- CampaignController.php
|   |   |   |-- SmsController.php
|   |   |   |-- SmsTemplateController.php
|   |   |   `-- WebhookController.php
|   |   |-- Enums/
|   |   |   |-- CampaignStatus.php
|   |   |   `-- CampaignType.php
|   |   |-- Helpers/
|   |   |   `-- PhoneHelper.php
|   |   |-- Jobs/
|   |   |   `-- SendCampaignJob.php
|   |   |-- Models/
|   |   |   |-- Campaign.php
|   |   |   |-- CampaignLog.php
|   |   |   |-- CampaignRecipient.php
|   |   |   |-- SmsCampaign.php
|   |   |   |-- SmsLog.php
|   |   |   `-- SmsTemplate.php
|   |   |-- Providers/
|   |   |   `-- SMSServiceProvider.php
|   |   |-- Services/
|   |   |   |-- CampaignService.php
|   |   |   |-- KenyaSMS.php
|   |   |   `-- KenyaSMSService.php
|   |   `-- routes.php
|   |-- Security/
|   |   |-- Models/
|   |   |   |-- SecurityLog.php
|   |   |   `-- Visitor.php
|   |   `-- routes.php
|   |-- Subscriptions/
|   |   |-- Config/
|   |   |   `-- subscriptions.php
|   |   |-- Controllers/
|   |   |   |-- Admin/
|   |   |   |   `-- PlanController.php
|   |   |   |-- PaymentController.php
|   |   |   |-- PlanController.php
|   |   |   `-- SubscriptionController.php
|   |   |-- Events/
|   |   |   |-- SubscriptionCreated.php
|   |   |   |-- SubscriptionPaymentFailed.php
|   |   |   `-- SubscriptionRenewed.php
|   |   |-- Listeners/
|   |   |   |-- SendExpiryReminder.php
|   |   |   `-- sendPaymentFailedNotification.php
|   |   |-- Models/
|   |   |   |-- AccountManager.php
|   |   |   |-- CompanyPaymentMethod.php
|   |   |   |-- CompanySubscription.php
|   |   |   |-- Region.php
|   |   |   |-- SubscriptionInvoice.php
|   |   |   `-- SubscriptionPlan.php
|   |   |-- Providers/
|   |   |   `-- SubscriptionsServiceProvider.php
|   |   |-- Services/
|   |   |   |-- InvoiceService.php
|   |   |   |-- PaymentGatewayService.php
|   |   |   `-- SubscriptionService.php
|   |   `-- routes.php
|   |-- System/
|   |   |-- Models/
|   |   |   `-- System.php
|   |   `-- routes.php
|   |-- Tenants/
|   |   |-- Models/
|   |   |   |-- RegisteredVisitor.php
|   |   |   `-- Tenant.php
|   |   `-- routes.php
|   |-- Users/
|   |   |-- Models/
|   |   |   `-- User.php
|   |   `-- routes.php
|   `-- Water/
|       |-- Models/
|       |   `-- WaterReading.php
|       `-- routes.php
|-- Providers/
|   |-- AppServiceProvider.php
|   `-- SystemServiceProvider.php
|-- Rules/
|   `-- CustomerCompany.php
|-- Services/
|   |-- MpesaService.php
|   |-- SmsService.php
|   `-- SubscriptionService.php
|-- Traits/
|   `-- CompanyScoped.php
`-- View/
    `-- Components/
        |-- AppLayout.php
        `-- GuestLayout.php
bootstrap/
|-- cache/
|   |-- packages.php*
|   `-- services.php*
|-- app.php
`-- providers.php
config/
|-- app.php
|-- auth.php
|-- backup.php
|-- cache.php
|-- database.php
|-- filesystems.php
|-- logging.php
|-- mail.php
|-- mpesa.php
|-- permission.php
|-- queue.php
|-- services.php
|-- session.php
|-- sms.php
`-- wallet.php
database/
|-- data/
|   `-- crm/
|       |-- scharge_nairobi_property_prospects.csv
|       `-- scharge_nairobi_property_sales_list.csv
|-- factories/
|   `-- UserFactory.php
|-- migrations/
|   |-- 0001_01_01_000000_create_users_table.php
|   |-- 0001_01_01_000001_create_cache_table.php
|   |-- 0001_01_01_000002_create_jobs_table.php
|   |-- 0001_01_01_000003_create_system_table.php
|   |-- 2026_01_09_093556_create_roles_table.php
|   |-- 2026_01_09_093821_create_estates_table.php
|   |-- 2026_01_09_093922_create_units_table.php
|   |-- 2026_01_09_094125_create_tenancies_table.php
|   |-- 2026_01_09_094351_create_invoices_table.php
|   |-- 2026_01_09_094425_create_invoice_items_table.php
|   |-- 2026_01_09_094445_create_payments_table.php
|   |-- 2026_01_09_100436_create_payees_table.php
|   |-- 2026_01_09_100648_create_expense_categories_table.php
|   |-- 2026_01_09_100811_create_expenses_table.php
|   |-- 2026_01_09_100924_create_expense_payments_table.php
|   |-- 2026_01_10_125651_create_tenants_table.php
|   |-- 2026_01_17_125651_add_role_id_to_users_and_drop_role_user.php
|   |-- 2026_04_17_000001_create_maintenance_requests_table.php
|   |-- 2026_04_17_000002_create_security_table.php
|   |-- 2026_04_19_163956_add_unit_classification_fields_to_units_table.php
|   |-- 2026_04_28_create_water_readings_table.php
|   |-- 2026_05_22_152405_create_sms_logs_table.php
|   |-- 2026_05_27_132019_create_wallets_table.php
|   |-- 2026_05_27_132020_create_wallet_transactions_table.php
|   |-- 2026_05_27_171627_create_sms_templates_table.php
|   |-- 2026_05_27_171800_create_sms_templates_table.php
|   |-- 2026_05_27_171957_create_sms_templates_table.php
|   |-- 2026_05_27_192633_create_sms_templates_table.php
|   |-- 2026_05_27_193117_create_sms_templates_table.php
|   |-- 2026_05_27_193250_create_sms_templates_table.php
|   |-- 2026_06_02_124736_create_sms_campaigns_table.php
|   |-- 2026_06_02_125754_add_campaign_id_to_sms_logs_table.php
|   |-- 2026_06_10_124230_create_wallets_table.php
|   |-- 2026_06_10_182923_create_transactions_table.php
|   |-- 2026_06_10_192923_create_transfers_table.php
|   |-- 2026_07_01_213505_create_pending_mpesa_payments_table.php
|   `-- 2026_07_01_215223_add_columns_to_pending_mpesa_payments_table.php
|-- schema/
|-- seeders/
|   |-- CreateWalletsForExistingTenants.php
|   |-- DatabaseSeeder.php
|   |-- RegionSeeder.php
|   |-- RoleSeeder.php
|   |-- RolesAndPermissionsSeeder.php
|   |-- SubscriptionPlanSeeder.php
|   |-- UnitTenantMigrationSeeder.php
|   `-- UserSeeder.php
`-- database.sqlite
routes/
|-- auth.php
|-- console.php
|-- mpesa.php
`-- web.php
resources/
|-- css/
|   `-- app.css
|-- js/
|   |-- components/
|   |   |-- charts/
|   |   |   |-- accountant-charts.js
|   |   |   |-- chart-01.js
|   |   |   |-- chart-02.js
|   |   |   `-- chart-03.js
|   |   |-- calendar-init.js
|   |   |-- image-resize.js
|   |   `-- map-01.js
|   |-- app.js
|   |-- bootstrap.js
|   `-- index.js
`-- views/
    |-- admin/
    |   |-- account-managers/
    |   |   |-- create.blade.php
    |   |   |-- edit.blade.php
    |   |   |-- index.blade.php
    |   |   `-- show.blade.php
    |   |-- companies/
    |   |   |-- edit.blade.php
    |   |   |-- index.blade.php
    |   |   `-- show.blade.php
    |   |-- staff/
    |   |   |-- create.blade.php
    |   |   |-- index.blade.php
    |   |   `-- show.blade.php
    |   |-- system/
    |   |   |-- index.blade.php
    |   |   `-- simple.blade.php
    |   `-- users/
    |       |-- edit.blade.php
    |       |-- index.blade.php
    |       `-- show.blade.php
    |-- auth/
    |   |-- confirm-password.blade.php
    |   |-- forgot-password.blade.php
    |   |-- login.blade.php
    |   |-- register.blade.php
    |   |-- reset-password.blade.php
    |   `-- verify-email.blade.php
    |-- components/
    |   |-- application-logo.blade.php
    |   |-- auth-session-status.blade.php
    |   |-- danger-button.blade.php
    |   |-- dropdown-link.blade.php
    |   |-- dropdown.blade.php
    |   |-- input-error.blade.php
    |   |-- input-label.blade.php
    |   |-- modal.blade.php
    |   |-- nav-link.blade.php
    |   |-- primary-button.blade.php
    |   |-- responsive-nav-link.blade.php
    |   |-- secondary-button.blade.php
    |   `-- text-input.blade.php
    |-- crm/
    |   |-- partials/
    |   |   |-- follow-up-row.blade.php
    |   |   |-- nav.blade.php
    |   |   |-- stage-badge.blade.php
    |   |   `-- tier-badge.blade.php
    |   |-- demos.blade.php
    |   |-- feedback.blade.php
    |   |-- follow-ups.blade.php
    |   |-- index.blade.php
    |   |-- show.blade.php
    |   `-- waitlist.blade.php
    |-- estates/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- expenses/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- home/
    |   |-- partials/
    |   |   |-- auth-side.blade.php
    |   |   |-- footer.blade.php
    |   |   |-- head.blade.php
    |   |   |-- header.blade.php
    |   |   |-- scripts.blade.php
    |   |   `-- site-alert.blade.php
    |   |-- airbnb.blade.php
    |   |-- api.blade.php
    |   |-- auth.blade.php
    |   |-- credits.blade.php
    |   |-- developers.blade.php
    |   |-- feedback.blade.php
    |   |-- layout.blade.php
    |   `-- listings.blade.php
    |-- invoices/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- layouts/
    |   |-- app.blade.php
    |   |-- guest.blade.php
    |   `-- navigation.blade.php
    |-- maintenance/
    |   `-- index.blade.php
    |-- partials/
    |   |-- alert/
    |   |   |-- alert-error.blade.php
    |   |   |-- alert-info.blade.php
    |   |   |-- alert-success.blade.php
    |   |   `-- alert-warning.blade.php
    |   |-- avatar/
    |   |   |-- avatar-01.blade.php
    |   |   |-- avatar-02.blade.php
    |   |   |-- avatar-03.blade.php
    |   |   `-- avatar-04.blade.php
    |   |-- badge/
    |   |   |-- badge-01.blade.php
    |   |   |-- badge-02.blade.php
    |   |   |-- badge-03.blade.php
    |   |   |-- badge-04.blade.php
    |   |   |-- badge-05.blade.php
    |   |   `-- badge-06.blade.php
    |   |-- buttons/
    |   |   |-- button-01.blade.php
    |   |   |-- button-02.blade.php
    |   |   |-- button-03.blade.php
    |   |   |-- button-04.blade.php
    |   |   |-- button-05.blade.php
    |   |   `-- button-06.blade.php
    |   |-- card/
    |   |   |-- card-companies.blade.php
    |   |   |-- card-dashboard.blade.php
    |   |   |-- card-invoices.blade.php
    |   |   |-- card-subscription.blade.php
    |   |   |-- card-wallet-summary.blade.php
    |   |   `-- card-water-stats.blade.php
    |   |-- chart/
    |   |   |-- accountant-charts.blade.php
    |   |   |-- chart-01.blade.php
    |   |   |-- chart-02.blade.php
    |   |   |-- chart-03.blade.php
    |   |   |-- payment-methods.blade.php
    |   |   `-- revenue-chart.blade.php
    |   |-- dashboard/
    |   |   |-- accountant.blade.php
    |   |   |-- admin.blade.php
    |   |   |-- cleaning-staff.blade.php
    |   |   |-- guest.blade.php
    |   |   |-- maintenance.blade.php
    |   |   |-- meter-reader.blade.php
    |   |   |-- pending.blade.php
    |   |   |-- property-manager.blade.php
    |   |   |-- security.blade.php
    |   |   |-- sys-admin.blade.php
    |   |   `-- tenant.blade.php
    |   |-- grid-image/
    |   |   |-- image-01.blade.php
    |   |   |-- image-02.blade.php
    |   |   `-- image-03.blade.php
    |   |-- metric-group/
    |   |   `-- metric-group-01.blade.php
    |   |-- modal/
    |   |   |-- alert-modal.blade.php
    |   |   |-- categories-create-modal.blade.php
    |   |   |-- companies-create-modal.blade.php
    |   |   |-- companies-users-modal.blade.php
    |   |   |-- error-modal.blade.php
    |   |   |-- expenses-create-modal.blade.php
    |   |   |-- expenses-delete-modal.blade.php
    |   |   |-- expenses-edit-modal.blade.php
    |   |   |-- expenses-show-modal.blade.php
    |   |   |-- invoice-bulk-modal.blade.php
    |   |   |-- invoice-create-modal.blade.php
    |   |   |-- invoice-delete-modal.blade.php
    |   |   |-- maintenance-create-modal.blade.php
    |   |   |-- maintenance-view-modal.blade.php
    |   |   |-- modal-create-reading.blade.php
    |   |   |-- payees-create-modal.blade.php
    |   |   |-- payees-delete-modal.blade.php
    |   |   |-- payees-edit-modal.blade.php
    |   |   |-- payees-show-modal.blade.php
    |   |   |-- payment-create-modal.blade.php
    |   |   |-- payments-create-modal.blade.php
    |   |   |-- payments-delete-modal.blade.php
    |   |   |-- payments-edit-modal.blade.php
    |   |   |-- role-create-modal.blade.php
    |   |   |-- role-delete-modal.blade.php
    |   |   |-- security-create-modal.blade.php
    |   |   |-- security-crud-modal.blade.php
    |   |   |-- security-quick-entry-modal.blade.php
    |   |   |-- subscriptions-companies-create.blade.php
    |   |   |-- subscriptions-company-create-modal.blade.php
    |   |   |-- subscriptions-create-modal.blade.php
    |   |   |-- subscriptions-features-create.blade.php
    |   |   |-- subscriptions-invoices-create.blade.php
    |   |   |-- subscriptions-managers-create.blade.php
    |   |   |-- subscriptions-show-modal.blade.php
    |   |   |-- success-modal.blade.php
    |   |   |-- tenancies-create-modal.blade.php
    |   |   |-- tenancies-delete-modal.blade.php
    |   |   |-- tenancies-edit-modal.blade.php
    |   |   |-- tenancies-show-modal.blade.php
    |   |   |-- tenants-create-modal.blade.php
    |   |   |-- tenants-delete-modal.blade.php
    |   |   |-- tenants-edit-modal.blade.php
    |   |   |-- tenants-show-modal.blade.php
    |   |   |-- units-create-modal.blade.php
    |   |   |-- units-delete-modal.blade.php
    |   |   |-- units-edit-modal.blade.php
    |   |   |-- units-show-modal.blade.php
    |   |   |-- units-water-reading-modal.blade.php
    |   |   |-- user-assign-modal.blade.php
    |   |   |-- user-create-modal.blade.php
    |   |   |-- wallet-add-card-modal.blade.php
    |   |   |-- wallet-deposit-modal.blade.php
    |   |   |-- wallet-payment-modal.blade.php
    |   |   |-- wallet-schedule-modal.blade.php
    |   |   |-- wallet-statement-modal.blade.php
    |   |   `-- water-reconciliation-modal.blade.php
    |   |-- profile/
    |   |   |-- profile-address-modal.blade.php
    |   |   `-- profile-info-modal.blade.php
    |   |-- table/
    |   |   |-- table-cleaning.blade.php
    |   |   |-- table-companies.blade.php
    |   |   |-- table-company-staff.blade.php
    |   |   |-- table-estates.blade.php
    |   |   |-- table-expenses.blade.php
    |   |   |-- table-invoices.blade.php
    |   |   |-- table-maintenance.blade.php
    |   |   |-- table-payments.blade.php
    |   |   |-- table-readings.blade.php
    |   |   |-- table-revenue-analytics.blade.php
    |   |   |-- table-security.blade.php
    |   |   |-- table-subscriptions-companies.blade.php
    |   |   |-- table-subscriptions-company.blade.php
    |   |   |-- table-subscriptions-features.blade.php
    |   |   |-- table-subscriptions-invoices.blade.php
    |   |   |-- table-subscriptions-managers.blade.php
    |   |   |-- table-subscriptions.blade.php
    |   |   |-- table-tasks.blade.php
    |   |   |-- table-tenancy.blade.php
    |   |   |-- table-tenant-management.blade.php
    |   |   |-- table-tenants.blade.php
    |   |   |-- table-transactions.blade.php
    |   |   |-- table-units.blade.php
    |   |   `-- table-users.blade.php
    |   |-- video/
    |   |   |-- video-01.blade.php
    |   |   |-- video-02.blade.php
    |   |   |-- video-03.blade.php
    |   |   `-- video-04.blade.php
    |   |-- breadcrumb.blade.php
    |   |-- calendar-event-modal.blade.php
    |   |-- common-grid-shape.blade.php
    |   |-- common-social-links.blade.php
    |   |-- datepicker.blade.php
    |   |-- header.blade.php
    |   |-- map-01.blade.php
    |   |-- media-card.blade.php
    |   |-- overlay.blade.php
    |   |-- preloader.blade.php
    |   |-- sidebar.blade.php
    |   |-- top-card-group.blade.php
    |   |-- upcoming-schedule.blade.php
    |   `-- watchlist.blade.php
    |-- payees/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- payments/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- profile/
    |   |-- partials/
    |   |   |-- delete-user-form.blade.php
    |   |   |-- update-password-form.blade.php
    |   |   `-- update-profile-information-form.blade.php
    |   |-- edit.blade.php
    |   `-- show.blade.php
    |-- public/
    |   |-- invoice-paid.blade.php
    |   `-- invoice-payment.blade.php
    |-- roles/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- security/
    |   |-- reports/
    |   |   |-- daily.blade.php
    |   |   `-- trends.blade.php
    |   |-- full-entry.blade.php
    |   |-- index.blade.php
    |   |-- logs.blade.php
    |   `-- quick-entry.blade.php
    |-- sms/
    |   |-- campaigns/
    |   |   |-- partials/
    |   |   |   |-- filters.blade.php
    |   |   |   |-- preview.blade.php
    |   |   |   |-- recipients.blade.php
    |   |   |   `-- stats.blade.php
    |   |   |-- create.blade.php
    |   |   |-- edit.blade.php
    |   |   |-- history.blade.php
    |   |   |-- index.blade.php
    |   |   `-- show.blade.php
    |   |-- templates/
    |   |   |-- create.blade.php
    |   |   |-- edit.blade.php
    |   |   `-- index.blade.php
    |   |-- broadcast.blade.php
    |   |-- index.blade.php
    |   |-- logs.blade.php
    |   `-- settings.blade.php
    |-- subscriptions/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- tenancies/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- tenant/
    |   `-- wallet.blade.php
    |-- tenants/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- units/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- users/
    |-- water/
    |   |-- index.blade.php
    |   `-- show.blade.php
    |-- 404.blade.php
    |-- alerts.blade.php
    |-- avatars.blade.php
    |-- badge.blade.php
    |-- bar-chart.blade.php
    |-- basic-tables.blade.php
    |-- blank.blade.php
    |-- buttons.blade.php
    |-- calendar.blade.php
    |-- dashboard.blade.php
    |-- form-elements.blade.php
    |-- images.blade.php
    |-- index.blade.php
    |-- invoice.blade.php
    |-- line-chart.blade.php
    |-- maintenance.blade.php
    |-- mtickets.blade.php
    |-- mtickets.html
    |-- signin.blade.php
    |-- signup.blade.php
    |-- videos.blade.php
    `-- welcome.blade.php
public/assets/
|-- css/
|   `-- styles.css
`-- js/
    |-- main.js
    `-- site-forms.js
public/js/
|-- components/
|   |-- charts/
|   |   |-- chart-01.js
|   |   |-- chart-02.js
|   |   `-- chart-03.js
|   |-- calendar-init.js
|   |-- image-resize.js
|   `-- map-01.js
`-- index.js
tests/
|-- Feature/
|   |-- Auth/
|   |   |-- AuthenticationTest.php
|   |   |-- EmailVerificationTest.php
|   |   |-- PasswordConfirmationTest.php
|   |   |-- PasswordResetTest.php
|   |   |-- PasswordUpdateTest.php
|   |   `-- RegistrationTest.php
|   |-- ExampleTest.php
|   `-- ProfileTest.php
|-- Unit/
|   `-- ExampleTest.php
`-- TestCase.php
```
