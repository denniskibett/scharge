# scharge — system audit and current state

*Audit date: 2026-11-08 · branch `claude/crm` @ `7494111` · Laravel 12, PHP 8.2*
*Reference design: the "Scharge Logo Concepts" design canvas (pages 7 · Mobile app and 8 · Website).*
*Companion file: [PROJECT_TREE.md](PROJECT_TREE.md).*

This document records what the codebase does today against what the website and app designs promise. Every finding cites `file:line` so it can be checked. Status labels:

| Label | Meaning |
|---|---|
| ✅ Working | Implemented end-to-end and wired up |
| 🟡 Partial | Some of it exists; important pieces are missing or broken |
| 🔴 Broken | Code exists but cannot work as written |
| ⚪ Missing | Nothing in the backend; at most marketing copy |

---

## 1. Scorecard

| Area | Status | One-line verdict |
|---|---|---|
| bavix wallet | 🟡 Partial | On `User` and `Tenant` only; M-Pesa callback double-counts; no company/landlord wallet |
| Spatie roles & permissions | 🟡 Partial | Tables exist in the live DB but not in migrations; role names inconsistent; admin routes unguarded |
| KYC | ⚪ Missing | Free-text `id_number` only; no uploads, verification status or screening |
| OCR | ⚪ Missing | No OCR library; water readings have no photo column |
| Staff vs non-staff dashboards | 🔴 Broken split | Everyone gets the admin (TailAdmin, blue) layout; website design not used after login |
| Website forms → backend | 🟡 Partial | 5 forms save to CRM; none notify anyone; most CTAs link nowhere |
| CRM for staff | 🟡 Partial | Good pipeline UI, but `sysadmin`-only; schema is hand-run SQL |
| Cold email / marketing | ⚪ Missing | `MAIL_MAILER=log`, no Mailables, no unsubscribe/consent |
| White label (monthly) | ⚪ Missing | Marketing copy only ("quoted per portfolio"); no plan, no per-company branding |
| Subscription invoice via M-Pesa | ⚪ Missing | No STK for subscription invoices; renewal uses a fake gateway that always succeeds |
| Client's own Daraja | ⚪ Missing | One global credential set; nowhere to store a client's keys |
| scharge as payment rail | ⚪ Missing | No company ledger, fees, B2C/B2B payouts or statements |
| Expenses paid from portal | 🟡 Record-keeping | Expenses are logged; no money moves, no approvals |
| Sweeping 08:00 / 12:00 / 16:00 | ⚪ Missing | No sweep job, and **the scheduler doesn't run at all** |
| M-Pesa STK (tenant rent) | 🔴 Broken | Callback is blocked by CSRF (419); env names mismatched |

---

## 2. Architecture at a glance

- **Framework:** Laravel 12 using `Application::configure` (`bootstrap/app.php`). There is a leftover Laravel 10 `app/Console/Kernel.php` and `app/Http/Kernel.php`; **Laravel 12 ignores both**.
- **Modules:** `app/Modules/*` (CRM, Payments, Subscriptions, SMS, Security, Properties, Tenants, Users, Water, Expenses, Maintenance, …). Only SMS, CRM, Security and Users route files are `require`d from `routes/web.php:662-672`; Companies, System, Expenses, Cleaning, etc. route files are **never loaded**. Only the CRM service provider is registered.
- **Packages:** `bavix/laravel-wallet ^11.5`, `spatie/laravel-permission ^8.3`, `spatie/laravel-backup`, `moneyphp/money`, country/county data packages.
- **Database drift:** the live schema is the `scha_main.sql` dump (52 tables, including Spatie tables, companies, subscriptions, `mpesa_stks`). The repo migrations **don't match**:
  - no Spatie `create_permission_tables` migration;
  - no migrations for `companies`, `company_subscriptions`, `subscription_invoices`, `company_payment_methods`, `mpesa_stks`, `house_checklists`, `lease_agreements`;
  - CRM tables exist only as hand-run SQL in `database/schema/2026_10_13_*.sql`, and the dump has **no** CRM tables;
  - a custom `2026_05_27_132019_create_wallets_table.php` would collide with bavix's `wallets` table;
  - six duplicate `create_sms_templates_table` migrations.

  ➜ A fresh `php artisan migrate` cannot rebuild production. **Fix this first.**
- **Two model trees:** `app/Models/*` and `app/Modules/*/Models/*` duplicate Tenant, User, Invoice, Payment, etc. 17 files import `App\Models\Tenant` and 9 import the module class. With no morph map, bavix `holder_type` differs and wallets split.
- **Timezone:** `config/app.php:71` falls back to `UTC`, so Nairobi times are wrong by 3 hours.

---

## 3. Core platform

### 3.1 bavix wallet: 🟡 Partial

**Exists**
- `HasWallet` on `App\Modules\Users\Models\User` (User.php:17-19) and `App\Modules\Tenants\Models\Tenant` (Tenant.php:16-18).
- `config/wallet.php` and the published bavix migrations (`2026_06_10_*`).
- `WalletService::processDirectPayment` → `payInvoiceFromWallet` does deposit-then-withdraw correctly (WalletService.php:693, 863).

**Problems**
1. **Double counting on M-Pesa.** `MpesaController.php:268-326` (and `PublicInvoiceController.php:308`) marks the invoice paid **and** deposits the same amount into the tenant wallet without withdrawing it. A failed deposit is swallowed (`:316`).
2. **Approval double entry.** `WalletController.php:343-366` builds an unconfirmed transaction by hand. Approval (≈506-546) then runs a *second* `deposit()` and sets the original `confirmed=true`, which leaves two credits.
3. **No Company/landlord wallet.** Without one, there's nothing to sweep and no rail ledger (see §5).
4. **Dead legacy code:**
   - `app/Models/Wallet.php` and `WalletTransaction.php` (hand-rolled ledger);
   - seeder `CreateWalletsForExistingTenants.php`;
   - `CreateTenantWallets.php:8`, which imports a non-existent `App\Modules\Payments\Models\Wallet`.
5. `method_exists($tenant,'balance')` is always false, so `wallet_balance_before` is always 0.

### 3.2 Spatie roles & permissions: 🟡 Partial

- `HasRoles` on User; `config/permission.php` has `teams=false`. Company scoping is manual (`company_id` plus `SetCompanyContext`).
- **Two competing seeders:**
  - `RolesAndPermissionsSeeder` creates 5 roles (admin, property_manager, accountant, tenant, meter_reader) and granular permissions.
  - `RoleSeeder` creates `sys_admin`, `super_admin` and others through a non-existent `App\Models\Role`.
- **Name mismatch:** the code checks `sysadmin` (DashboardController.php:42,76; CRM `routes.php:32`; web.php:454), but no seeder creates `sysadmin`. A real sys admin can land on the guest dashboard.
- **Guards:**
  - Only `role:` middleware is used, and only in 4 places.
  - There is no `permission:` or `can:` middleware, no Policies or Gates, and no `@can`. Seeded permissions are therefore never enforced.
  - **Security gap:** these routes are protected only by `auth`, so any tenant who is logged in can reach them:
    - `roles.*` (including create/delete);
    - `admin/companies`, `admin/wallets`, `admin/subscriptions`, `admin/account-managers`;
    - `system`, `users`, `staff`, `reports/*`

    (web.php ≈225-653).
  - `public/invoice/{invoice}/*` uses sequential IDs with no signed URL (web.php:200-206).
- `User::isStaff()` (User.php:404-416) counts property managers, accountants and meter readers as "staff". That conflicts with the business meaning of staff (scharge's own team). There is no account-manager or sales role.

### 3.3 KYC: ⚪ Missing

- `tenants.id_number` is free text (`nullable|string|max:50`, TenantController.php:165).
- Visitor ID capture exists in Security (gate log, not KYC).
- `Payee.kra_pin` is fillable but **has no column**, so saves will error. `users.tax_id` is unused.
- Missing:
  - document uploads (ID front/back, KRA PIN certificate, company CR12, title deed);
  - verification status and reviewer workflow;
  - tenant screening;
  - format validation;
  - encryption of ID numbers (required under the Kenya Data Protection Act).

### 3.4 OCR: ⚪ Missing

- There is no OCR dependency (tesseract, Google Vision, AWS Textract).
- `water_readings` has **no photo column**. The model's fillable fields (`recorded_by`, `is_initial`, `rate_applied`, `billing_type`) don't exist in the migration, which has `rate_per_unit`, so meter-reading inserts can fail.
- The design ("Read from photo · 12.5 m³", anomaly flagging at 3.4× average, photo proof shown to the tenant) needs:
  - photo upload on readings;
  - an OCR service, run as a queued job;
  - an anomaly rule.

  OCR on ID documents would feed KYC.

---

## 4. Dashboards: staff vs non-staff

**Requirement:** scharge staff use an admin dashboard. Customers (landlords/owners, managers, tenants, BnB hosts, caretakers/meter readers, fundis) get dashboards that carry the **website design** (red `#DC2626`, ink `#1A1214`, white; Unbounded + DM Sans; `public/assets/css/styles.css`).

**Current state: 🔴 not split.**

| Layout | Used by | Design |
|---|---|---|
| `layouts/app.blade.php` | **Every** page after login: all dashboards, tenant wallet, estates, units, payments, CRM, admin | TailAdmin, Outfit, blue `#3A57E8` / `#465FFF` |
| `home/layout.blade.php` + `home/auth.blade.php` | Public site, login, register | Website design ✅ |
| `layouts/guest.blade.php` | reset / confirm / verify-email | Plain Breeze (matches neither) |
| `layouts.admin` | `vendor/subscriptions/index.blade.php` | **Does not exist** |

- Everyone is sent to `route('dashboard')`. `DashboardController::index` (l.30-61) picks a view from `partials/dashboard/<role>` using the user's *first* role.
- Dashboards that exist: sys-admin, admin, property-manager, accountant, tenant, meter-reader, maintenance, security, cleaning-staff, guest (an empty section) and pending.
- **Missing dashboards:** landlord/owner (payouts, statements, tax set-aside), BnB host, caretaker, fundi, and scharge account manager/sales.
- Self-registered users get **no role**, so they land on the empty guest dashboard.
- Menus are filtered client-side only (`partials/sidebar.blade.php:167-233`). The TailAdmin demo pages (`/alerts`, `/buttons`, `/signin`, …, web.php:267-286) are reachable by any logged-in user.
- `tailwind.config.js` defines `brand` twice (l.61 and l.101); the later blue one wins.

**Target shape**
- A `users.is_staff` flag, or a `staff` role family (`sysadmin`, `account_manager`, `support`). Staff routes go under `/staff/*` with `role:` middleware and keep the admin layout.
- A new `layouts/portal.blade.php` that reuses `home/partials/{head,header,footer}` and `styles.css`. Every customer dashboard and customer page moves onto it.
- The login redirect branches on staff vs customer.

---

## 5. Payments and money movement

### 5.1 M-Pesa STK for tenant invoices: 🔴 Broken

- **CSRF blocks Safaricom.** `routes/mpesa.php:8` registers `POST /pay` inside the web stack and calls `withoutMiddleware(App\Http\Middleware\VerifyCsrfToken)`. Laravel 12 runs `ValidateCsrfToken`, which is a different class. `bootstrap/app.php` has no `validateCsrfTokens(except: …)`. The callback therefore gets **419**.
- **Env names don't match:**
  - `MpesaService.php:22-30` reads `MPESA_SHORTCODE` and `MPESA_PASSKEY` (default `''`) through raw `env()`. Raw `env()` returns null once config is cached.
  - `config/mpesa.php` uses `MPESA_BUSINESS_SHORTCODE` and `SAFARICOM_PASSKEY`.
  - Result: the STK password is likely wrong.
- **Routes to methods that don't exist:**
  - `PaymentController::initiateMpesaStkPush` and `tenantPayment`;
  - `MpesaController::stkPush` and `showPaymentForm` (web.php:643-652);
  - `MpesaController::stkCallback`, `queryStatus` and `b2bPayment` (SMS `routes.php`).
- **No callback authentication:**
  - no Safaricom IP allow-list;
  - unknown CheckoutRequestIDs create orphan rows (MpesaController.php:94);
  - every exception is answered `ResultCode 0` (`:216`).
- **No idempotency:** no unique index on `mpesa_receipt_number` and no row lock.
- **No reconciliation:** `pending_mpesa_payments` is unused, and there's no C2B register/confirm/validate.
- **Logs leak data:** the consumer-key prefix (MpesaService.php:55), the full B2B payload including `SecurityCredential` (`:332`), and raw callbacks with headers (`MpesaController.php:30-49`).

### 5.2 Subscription invoice payment via M-Pesa: ⚪ Missing

- No STK for `SubscriptionInvoice`, and the callback only knows tenant `Invoice` (MpesaController.php:202,237).
- `PaymentGatewayService::charge` is a **stub that always returns `successful` in USD** (`PaymentGatewayService.php:30-48`). Auto-renewal "succeeds" without money moving.
- The saved payment method is a dummy card `4242`.
- `InvoiceService.php` is empty.
- Admin "mark paid" (`SubscriptionController.php:885`) doesn't extend the subscription.
- `Config/subscriptions.php` says Stripe/USD, but pricing is in KES.

**Needed**
- An STK push from the subscription invoice page, using scharge's own shortcode with account ref `SUB-<invoice>`.
- A dedicated callback, `/mpesa/subscriptions/callback`, that:
  - verifies, then marks `SubscriptionInvoice` paid;
  - extends `CompanySubscription`;
  - is idempotent on the receipt number.
- C2B paybill confirmation for manual paybill payments.
- A reconciliation job, plus receipts by SMS/email.

### 5.3 Client's own Daraja: ⚪ Missing

- `MpesaService` is a singleton credential set with one token cache key and one callback URL.
- Two conflicting `CompanyPaymentMethod` models on one table: the Payments one is display-only paybill/till/bank; the Subscriptions one is card fields.
- **Needed:** a `company_mpesa_credentials` table with:
  - consumer key/secret, passkey, shortcode, type (paybill/till), initiator and security credential;
  - all secrets stored with Laravel `encrypted` casts.

  Also needed:
  - `MpesaService::forCompany($company)` with a per-company token cache;
  - a per-company callback URL `/mpesa/callback/{company:uuid}`;
  - a sandbox "test connection" button.

  Payments go **straight to the client's paybill**. scharge only records them and bills its subscription.

### 5.4 scharge as the payment rail: ⚪ Missing

For clients without Daraja, tenants pay scharge's shortcode with an account ref that identifies unit and company (e.g. `B04MWANGAZA`, as in the tenant app design).

- **Today:** all money is attributed only to the tenant wallet. There's no Company wallet, fee/commission logic, payout, B2C/B2B, or landlord statement. The unused `MpesaService::businessPayBill` exists.
- **Copy conflict:** the website says scharge "never holds your money" (`welcome.blade.php:129`). The rail model contradicts this, and the developer page's placeholders (`[LICENSED PARTNER]`, `[ESCROW / TRUST ACCOUNT]`) are still unfilled. A CBK-licensed partner or trust-account arrangement is a **business/legal prerequisite** for this.
- **Needed:**
  - a `Company` wallet (bavix `HasWallets`, with slugs `collections`, `fees`, `reserve`);
  - a fee rule per plan;
  - a ledger entry per collection;
  - landlord statements;
  - payouts by B2B (paybill/till) or bank;
  - the sweep job (§5.6).

### 5.5 Expenses from the portal: 🟡 Record-keeping only

- `ExpenseController` stores expenses with `status pending|paid`.
- `ExpensePayment` is never written, and `Payee` has no paybill/till/bank fields.
- No approval, no wallet debit and no disbursement.
- **Needed:**
  - payee payment details;
  - an approval threshold (the design shows an auto-approve limit of KES 5,000);
  - paying from the company `collections` wallet;
  - B2C/B2B disbursement with a result callback;
  - expenses deducted on the landlord statement.

  For Daraja clients, record the expense and optionally disburse via their own B2C credentials.

### 5.6 Sweeping (08:00, 12:00, 16:00 Africa/Nairobi): ⚪ Missing

- **No scheduler runs.**
  - `app/Console/Kernel.php::schedule()` is ignored by Laravel 12.
  - `routes/console.php` has only `inspire`.
  - `bootstrap/app.php` has no `withSchedule`.
  - The commands it references don't exist.
  - `SubscriptionsServiceProvider` isn't in `bootstrap/providers.php`.
- **Needed:**
  - `APP_TIMEZONE=Africa/Nairobi`, or `->timezone('Africa/Nairobi')` on the schedule.
  - In `routes/console.php`:
    ```php
    Schedule::command('payments:sweep')
        ->cron('0 8,12,16 * * *')
        ->timezone('Africa/Nairobi')
        ->withoutOverlapping()
        ->onOneServer();
    ```
  - A `payments:sweep` command that, for each rail company:
    1. sums the cleared, unswept `collections` balance;
    2. deducts the fees, then any approved expenses;
    3. initiates a B2B/bank payout above a minimum;
    4. writes a sweep record (`sweeps` table: company, period, gross, fees, expenses, net, status, M-Pesa conversation IDs);
    5. marks those ledger rows swept, so a re-run is idempotent;
    6. notifies the landlord.
  - A cron entry `* * * * * php artisan schedule:run` on the server.
  - Move subscription renewals and expiry reminders to `routes/console.php` too.

  "Every 4 hours from 8am" gives exactly three runs: 08:00, 12:00 and 16:00. Collections after 16:00 roll into the next day's 08:00 sweep.

---

## 6. Website offerings vs backend

All public forms live in `app/Modules/CRM/Controllers/Site/SiteFormController.php` and are routed at CRM `routes.php:19-27`. Each form has CSRF protection, `throttle:6,1`, a honeypot and validation. **None sends a confirmation to the lead or an alert to staff.**

| Offering (canvas page) | Backend | Status |
|---|---|---|
| Waitlist (landing) | `waitlist_entries` + nurture company | ✅ (manual "launch country" step) |
| Book a meeting (developers) | `crm_follow_ups` type=demo, due next working day | ✅ in CRM, no notification |
| Get sandbox keys (API) | follow-up type=api_keys; **no keys generated** | 🟡 saves only |
| Viewing/application request (listings) | follow-up type=viewing, unassigned, no company | 🟡 saves only; nothing reaches the owner |
| SMS me new matches (listings) | one listing_alert per user (overwrites) | 🟡 saves only; no matching, no SMS, no STOP |
| Feedback link | NPS; a score ≤6 opens a recovery call | ✅ |
| Pricing calculator (landing) | JS only (`main.js:117-131`); the minimum is applied per account, not per property | ⚪ Frontend only |
| "Send me this quote" | Link to `developers#talk`; the quote is lost | ⚪ |
| Airbnb cost calculator | JS only | ⚪ |
| "Add my listing", "List my vacancy", "Embed on website" | Links loop back to pricing/API | ⚪ Goes nowhere |
| Open listings browse | Hard-coded JSON in `listings.blade.php:67`; no listings table | ⚪ |
| Channel sync, direct booking, smart locks, host earnings | Only `units.stay_type=bnb` and `bnb_nightly_rate` | ⚪ |
| Cleaning/turnover | Routes → empty `CleaningController` (would error) | 🔴 |
| Off-plan instalments, snag lists, handover, plots | — | ⚪ |
| API endpoints and webhooks | No `routes/api.php`, Sanctum or webhook dispatch | ⚪ |
| Tenant pay via M-Pesa | Public invoice STK works up to the callback (419) | 🔴 see §5.1 |
| Receipts | Receipt number stored; no SMS/email receipt | 🟡 |
| Metered water + photo proof | Readings exist; no photo, OCR or anomaly | 🟡 |
| Repairs / work orders / fundi | Store/update exist; images validated then saved as `[]`; `assignments()` and `destroy()` missing | 🟡 |
| Notices, arrears reminders | Manual SMS campaigns only | 🟡 |
| Payment plans, owner payouts, MRI tax set-aside, screening, e-lease, gate passes, offline reader | Stubs or nothing (`LeaseAgreement` and `HouseChecklist` models have no tables) | ⚪ |

---

## 7. CRM, marketing and cold email

**CRM (🟡)**
- The pipeline lives on `companies`:
  - `lifecycle` (prospect/customer/churned);
  - `crm_stage`: new, contacted, qualified, demo_booked, proposal, negotiation, won, lost, nurture;
  - `tier` 1-3, plus `owner_id`.
- Every activity is a typed row in `crm_follow_ups`. Waitlist entries are in `waitlist_entries`.
- Screens: pipeline with filters, company page, follow-ups ("mine"), requests tabs, feedback/NPS, and waitlist with CSV export.
- `php artisan crm:import` loads `database/data/crm/*.csv` (82 + 25 prospects).
- **Gaps for staff use:**
  - `role:sysadmin` only (CRM `routes.php:32`; sidebar l.171-179);
  - the owner picker only lists sysadmins (`CrmCompanyController.php:198`);
  - no account-manager/sales role;
  - `account_managers` (region-based) isn't linked to the CRM;
  - no CRM widgets on the staff dashboard (due today, overdue);
  - no bulk actions, kanban, UI import or delete;
  - the schema isn't in migrations.

**Cold email (⚪)**
- `MAIL_MAILER=log` and `MAIL_FROM_ADDRESS=hello@example.com`.
- No `app/Mail`, and no Notifications. The two listeners reference Mailables that don't exist and would crash.
- No templates, sequences, tracking, bounce handling, unsubscribe link, `List-Unsubscribe` header or consent record.
- **Needed:**
  - **Sending:** a transactional/bulk provider (e.g. Postmark/SES/Mailgun) on a separate outreach subdomain (e.g. `mail.scharge.com`) with SPF, DKIM and DMARC, plus warm-up.
  - **Sequences:** a `crm_sequences` / `crm_sequence_steps` / `crm_enrollments` model; queued, throttled sends; and a per-staff sender identity.
  - **Compliance:** an unsubscribe link and `List-Unsubscribe` header, a suppression list, and consent/lawful-basis fields (Kenya Data Protection Act 2019, B2B legitimate interest).
  - **Tracking:** open/click tracking and bounce/complaint webhooks that write back to `crm_follow_ups`.
  - **SMS:** let SMS campaigns target CRM leads, and honour `do_not_call_list`, a table that currently doesn't exist.

---

## 8. White label (monthly)

- **Today:** `SubscriptionPlanSeeder` only seeds per-unit bands (Residential KES 40-1,000; Commercial 350-1,000), with a 14-day trial and 10% off yearly. There's no white-label plan, feature flag or flat monthly fee.
- Branding (`SystemHelper::primaryColor/logoUrl/customCss`) is a single **platform-wide** `system` row.
- There are no per-company logo, colours, domain/subdomain, SMS sender ID or email-from fields.
- The API page sells white label as "quoted per portfolio" with a setup fee and about 4 weeks to launch (`home/api.blade.php:58,96-100`).
- **Needed:**
  - **Plan:** a `white_label` plan type: a monthly flat fee plus a per-unit fee and a one-off setup fee, gated by a `features.white_label` flag.
  - **Branding:** a `company_brandings` table (logo, colours, favicon, custom domain/subdomain, SMS sender ID, email from-name and domain, hide-scharge flag).
  - **Rendering:** a middleware that resolves the company from the host and injects its branding into `layouts/portal`.
  - **API access:** API keys (Sanctum) so white-label clients get API access.
  - **Sales:** a white-label enquiry form in place of the current `#keys` link.

---

## 9. Design canvas coverage

The "Scharge Logo Concepts" canvas now has a local working copy in this session, so it can be edited and republished. Its app and website pages map to code like this:

| Canvas artboard | Code counterpart | Gap |
|---|---|---|
| Website · Landing | `welcome.blade.php` | Calculator and quote have no backend |
| Website · Developers & landowners | `home/developers.blade.php` | Instalments, snags and handover missing |
| Website · Airbnb hosts | `home/airbnb.blade.php` | Sync, booking widget and locks missing |
| Website · Open listings | `home/listings.blade.php` | No listings table; hard-coded data |
| Website · API & white label | `home/api.blade.php` | No API, keys or webhooks |
| App · Tenant | `partials/dashboard/tenant.blade.php`, `tenant/wallet` | Admin layout; gate pass and water photo missing |
| App · Property manager & landlord | `property-manager.blade.php`, `admin.blade.php` | No landlord payouts, statements or MRI |
| App · BnB host | — | Missing |
| App · Water meter reader | `meter-reader.blade.php` | No photo/OCR, offline mode or anomaly |
| App · Roles & feature map | Spatie roles | Landlord, host, fundi, guard and caretaker roles missing |

---

## 10. Prioritised plan

**P0: make what exists safe and working (about 1-2 weeks)** (progress in §12)
1. **Schema baseline** *(owner: Dennis; migrations are being cleaned up by hand, see §12.3)*. Generate migrations from the live schema (or `php artisan schema:dump`). Add the Spatie, subscription, company, `mpesa_stks` and CRM tables. Delete the colliding custom `wallets` migration and the duplicate SMS-template migrations.
2. **Roles.** Settle on one role list (`sysadmin`, `account_manager` for staff, where account managers own a book of companies, their leads and revenue, and are paid commission; `landlord`, `property_manager`, `accountant`, `tenant`, `meter_reader`, `caretaker`, `bnb_host`, `fundi`, `security` for customers). Merge the seeders. Put `role:` / `permission:` middleware on every admin route. Remove the demo routes.
3. **M-Pesa.** Exclude callbacks from CSRF in `bootstrap/app.php`. Read credentials from `config/mpesa.php` only. Make callbacks idempotent with a unique receipt number. Fix the wallet double-count. Stop logging secrets.
4. **Scheduler.** Move it to `routes/console.php` and set `APP_TIMEZONE=Africa/Nairobi`.
5. **Morph map and models.** Pick one model per entity and add `Relation::enforceMorphMap`.

**P1: money (about 2-3 weeks)**
6. Subscription invoice STK + callback + C2B + reconciliation, replacing the fake gateway.
7. Per-company Daraja credentials (encrypted) and per-company callbacks.
8. Rail mode: company wallets, fees, ledger, landlord statements, B2B/bank payouts. This depends on the licensed-partner/escrow decision.
9. Expenses: payee payment details, approvals, payment from the collections wallet.
10. The `payments:sweep` job at 08:00/12:00/16:00 Nairobi, with a `sweeps` table and notifications.

**P2: growth (about 2-3 weeks, in parallel with P1)**
11. Open the CRM to staff: an `account_manager` role, a `/staff` area, CRM widgets on the staff dashboard, bulk actions and kanban.
12. Email: provider, domain authentication, Mailables, lead acknowledgements and staff alerts from every website form.
13. Cold email sequences with unsubscribe, suppression, consent and tracking.
14. A white-label plan and per-company branding, with domain resolution.

**P3: product depth**
15. A customer portal layout in the website design; landlord and BnB host dashboards.
16. KYC (uploads, statuses, review queue, encrypted IDs) and OCR for meter photos and IDs, as queued jobs.
17. Listings table + public browse + viewing hand-off to owners; quote capture; the API (Sanctum) + outgoing webhooks.

---

## 11. Open decisions for the business

1. **Rail licensing.** Which CBK-licensed partner or trust account will hold rail collections? The website currently says scharge never holds money.
2. **Sweep minimums and fees.** The minimum payout amount, who pays the B2B charges, and the fee per plan.
3. **White-label pricing.** The monthly fee, per-unit fee and setup fee.
4. **Outreach provider and sending domain.**
5. **OCR provider.** Google Vision, AWS Textract or self-hosted Tesseract, weighing cost against accuracy on meter dials.

---

## 12. P0 progress (2026-11-08, branch `claude/crm`, uncommitted)

### 12.1 Done in code

**M-Pesa**
- **CSRF:** callbacks are exempted in `bootstrap/app.php` via `validateCsrfTokens(except: ['pay', 'sms/mpesa/callback', 'sms/webhook/*'])`. Checked on a local server: `POST /pay` now returns 200, malformed bodies return 400, and ordinary forms still return 419 without a token.
- **Credentials from config:** `MpesaService` reads `config/mpesa.php`, which now uses the `.env` names (`MPESA_SHORTCODE`, `MPESA_PASSKEY`, `MPESA_INITIATOR`, `MPESA_SECURITY_CREDENTIAL`) with the old names as fallbacks. The hard-coded passkey default is gone.
- **Settlement:** a new `App\Services\MpesaSettlementService` is shared by the callback and the public-invoice status poll.
  - It locks the STK row and invoice, and skips if a Payment already exists for the checkout ID or receipt.
  - It deposits the full amount to the tenant wallet, then withdraws only the part applied to the invoice. Overpayment stays as wallet credit, which fixes the double count.
  - `invoice.total_paid` is capped at the invoice total.
  - On failure it flags `mpesa_stks.metadata.needs_reconciliation`.
- **Callback handling:**
  - Unknown CheckoutRequestIDs no longer create orphan rows.
  - A replayed callback can't overwrite a receipt.
  - An optional IP allow-list is available (`MPESA_CALLBACK_IPS`).
  - Raw callback files and request headers are no longer logged, and neither is the consumer-key prefix. `SecurityCredential` is stripped from the B2B log line.
- **Dead routes:** removed routes pointing at methods that never existed (`payments/mpesa/stk-push`, `payments/mpesa/pay`, `make-payment`, `sms/mpesa/*`, `mpesa/b2b/*`). `/sms/mpesa/callback` now uses `handleCallback`. The KenyaSMS delivery-report webhook moved out of the `auth` group.

**Scheduler**
- `routes/console.php` is now the schedule. The dead `app/Console/Kernel.php` was deleted.
- `config('app.schedule_timezone')` defaults to `Africa/Nairobi`. The app timezone stays UTC so stored timestamps keep their meaning.
- Jobs:
  - `mpesa:reconcile` (new) runs every 15 minutes and settles confirmed STKs that have no Payment.
  - `tenants:create-wallets` runs daily at 03:00. It was rewritten to go through bavix; the dangerous `--force` that deleted wallets was removed.
  - The 08:00/12:00/16:00 sweep slot is stubbed, waiting for `payments:sweep`.
  - Subscription billing is deliberately **not** scheduled while it uses the fake gateway.
- **Server cron still needed:** there is none on the live server (§13).

**Roles and access**
- `role:` guards added on:
  - `roles`, `system` and the demo pages: `sysadmin`;
  - `admin/account-managers`: `sysadmin`;
  - `admin/companies` and `admin/subscriptions`: `sysadmin|account_manager`;
  - `admin/wallets`, `users`, `staff` and `admin/roles/list`: `sysadmin|admin|accountant`;
  - `api/users`: the above plus `property_manager`;
  - `reports`: management roles.
- **CRM:** opened to `account_manager`, scoped by `App\Modules\CRM\Support\CrmAccess`. An account manager sees companies they own (`owner_id`) plus unassigned leads, and can't open another manager's account (403). The owner picker lists sysadmins and account managers.
- **Account manager login:** they pass the "has a company" check, land on the CRM, and get their own sidebar menu.
- **Seeder:** `RolesAndPermissionsSeeder` now lists every live role plus `account_manager`. The broken `RoleSeeder` (non-existent model, `sys_admin` naming) was deleted.

**Wallets**
- A morph map in `AppServiceProvider` makes `App\Models\Tenant`/`User` and their module classes resolve to the same morph name already stored in the data. Wallets and roles now resolve from either class: 86 tenants genuinely have no wallet, down from a false 394.

**Ported from live**
- `StaffController` now uses the existing `admin.staff.*` views.

### 12.2 Not done yet in P0
- The tenant/module model duplication itself: the morph map hides it, but the twin classes remain.
- A commission model for account managers: a rate per manager plus a ledger off paid subscription invoices.
- `MpesaService::getBalance()` still references undefined properties. It's dead code, but it would error if called.

### 12.3 Schema changes for you to make (described, not written)
Per your rule that migrations are yours, these are left for you:
1. **The `account_manager` role row.** It is **not** in the live or local `roles` table yet. Add it with:
   ```php
   Spatie\Permission\Models\Role::firstOrCreate(['name' => 'account_manager', 'guard_name' => 'web']);
   ```
   or by running `RolesAndPermissionsSeeder`. Note that the seeder also re-syncs permissions for admin, property_manager and so on.
2. **A unique index** on `mpesa_stks.mpesa_receipt_number`, with name e.g. `mpesa_stks_receipt_unique`. No duplicates exist today. The code is already idempotent; this is a second safety net.
3. **Collations differ** between `mpesa_stks` (`utf8mb4_0900_ai_ci` locally) and `payments` (`utf8mb4_unicode_ci`), so SQL joins/subqueries between them fail. Align them.
4. **Bavix ships its own migrations,** which show as Pending (`2018_11_06…` and others) because the published copies were renamed. When you clean up, either delete the published copies or call `WalletConfigure::ignoreMigrations()`.

---

## 13. Live server (scharge.sharet.africa) findings, 2026-11-08

The live server was checked read-only. Nothing on the server was changed.

- **App:** in `/home/scharge.sharet.africa/laravel`, with `public_html` beside it.
- **Code:** last copied up on 13 Oct, file-by-file. The server's git is at "Initial commit" with large uncommitted changes, so git there means nothing.
  - 31 of 37 differing files are just older versions from repo history.
  - Six files were edited on the server only: `bootstrap/app.php`, `routes/mpesa.php`, `VerifyCsrfToken.php`, `composer.json`, `RoleSeeder.php` and `StaffController.php`. Only the StaffController view fix was worth porting.
  - Live also has leftovers that the repo deleted: `routes/api.php`, `routes/test.php`, old models (`Loan`, `Payee`, …), `bootstrap/app.php.bak`.
  - Stray files in the app root (`-d`, `-H`, `n't work, try:`, …) came from a mistyped shell command.
- **🔴 `POST https://scharge.sharet.africa/pay` returns 419.** Safaricom STK callbacks are being rejected in production today.
- **🔴 `APP_DEBUG=true` and `APP_ENV=local` in production.** Errors show stack traces and config to the public. Set `APP_ENV=production`, `APP_DEBUG=false`.
- **No crontab for the site user `schar5452`,** so no scheduled task can run. Add:
  ```
  * * * * * cd /home/scharge.sharet.africa/laravel && php artisan schedule:run >> /dev/null 2>&1
  ```
- **M-Pesa on live** is still `MPESA_ENVIRONMENT=sandbox`, and `MAIL_MAILER=log`.
- **Database:** MariaDB `scha_main`, 54 tables, about 9.5 MB, 427 users.

### Live settings fixed (2026-11-08 21:06 UTC)
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`. The backup is `.env.before-prod-settings-20261108-210606`, and both files are now mode 600.
- Cron added for `schar5452`:
  ```
  * * * * * cd /home/scharge.sharet.africa/laravel && /usr/local/lsws/lsphp83/bin/php artisan schedule:run
  ```
  Live's `routes/console.php` has no tasks yet, so the jobs only start once the `claude/crm` code is deployed.
- **Still open on live:** the 419 on `/pay` stays until the `claude/crm` code is deployed. The stray files in the app root are also still there.

### Local database refreshed from live
- **Backup:** local `scha_main` was first backed up to `../scharge-db-backups/local-scha_main-before-live-sync-20261108-225503.sql.gz`. The local-only table `enquiries` is in that backup.
- **Import:** the live dump (`../scharge-db-backups/live-scha_main-20261108-225503.sql.gz`) was imported as the new local `scha_main`.
- **MariaDB → MySQL 9:** the generated column `units.total_monthly_charges` can't be inserted into on MySQL. It was imported as a plain column, then converted back to the same `STORED` generated expression.


---

## 14. Estate → Property rename (2026-11-09)

- **Code:** models, controllers, views, routes, relations, variables and JS now say Property everywhere: `App\Models\Property`, `PropertyController`, `/properties`, `property_id`.
- **Old links:** old `/estates/...` URLs 301-redirect to `/properties/...`.
- **SMS:** saved templates that use `{{estate_name}}` keep working, because the code fills it in alongside `{{property_name}}`.
- **Schema:** `database/schema/2026_11_09_rename_estates_to_properties.sql` (hand-applied, run once):
  - renames `estates` to `properties`;
  - renames `estate_id` to `property_id` in 10 tables;
  - recreates the 7 foreign keys and their indexes;
  - uses MariaDB 10.3-compatible syntax.

  **The code needs this SQL.** Apply it to each database when the code is deployed, after a backup.
- **Verified** on a copy of live data with both SQL files applied:
  - the page sweep as sysadmin, company admin, property manager and tenant matches the pre-rename baseline exactly;
  - the remaining 500s already existed: `/tenants` and `/tenancies` (missing `App\Models\Role`, `User::role()` called without a role), `/sms/campaigns` (missing `getStatusBadge()`), `/reports/financial` (missing method);
  - the Sharet payment checks pass, 31 of 31.

---

## 15. Listings, photos and calendar (2026-11-09)

- **Calendar.** `/calendar` is real. One role-scoped feed shows:
  - rent due, collections, the meter-reading window and the readings done or flagged;
  - repairs, move-ins and move-outs;
  - Sharet payouts, CRM follow-ups and subscription renewals.

  Tenants see only their own bills and payments.
- **Public Open listings.** The page now shows real vacant units instead of sample homes. There is one card per property, house type and rent (e.g. "Studio · Bloomfield Apartments · 10 available"), so 934 vacant units become 9 cards. Short stays use the nightly rate.
- **House photos** (`database/schema/2026_11_09_unit_photos.sql`, hand-applied). On a unit's page, upload photos for:
  - this house only;
  - all houses of the same type in the property (the default);
  - the whole property.

  Similar houses share the photos without copies, and the public card shows them. Cards without photos get a neutral "photos coming soon" picture, never a stock photo.
- **Roles list fix.** `/admin/roles/list` no longer reads `roles.description`, which doesn't exist in the live Spatie table, so the "No roles available" error is gone.

---

## 16. BnB hosts inside shared buildings (2026-11-09)

Schema: `database/schema/2026_11_09_bnb_hosts.sql` (hand-applied, run once), then `php artisan properties:index-keys`.

**Ownership model**
- `properties.company_id`: the building's management company. It is NULL while the building is unclaimed.
- `units.company_id`: who keeps the books for the unit.
- `unit_managements`: who manages each unit and in what role (`manager`, `bnb_host` or `owner`), with history.

**One record per building**
- Every new property is matched first on a normalized name and area ("Kitengela Gardens" matches "Kitengela Gardens Apartments, Kitengela").
- A host is offered the existing building. A management company gets a 409 with the matches and chooses either **Claim this building** or **a different building**.

**Host flow**
- A host signs up at `/host/register` (the website's "Add my listing" button). This creates their company (kind `bnb_host`), their user (role `bnb_host`) and their unit.
- The unit goes into the existing building, or into a new unclaimed one.
- Hosts manage their units at `/host`.

**Claims**
- Claims go to `/admin/property-claims`, where the company's account manager or a sysadmin approves them.
- On approval the company manages the building, and host units' books move to it.
- An owner tenancy (rent 0) is created so management can bill each host service charge and water.
- The host keeps the unit:
  - only the host can change listing fields (nightly rate, type, status);
  - only management can change billing fields;
  - management cannot delete a host's unit.
- A host who joins an already-managed building has their unit's books with the manager from day one.

**Verified:** 21 of 21 checks on a scratch copy of live data.

---

## 17. Roles that work, Team & access, no full reloads (2026-11-09)

**Permissions**
- `App\Support\Access` is the catalogue: 15 areas × view, manage and delete (45 permissions), plus default grants for each role.
- `php artisan access:sync` writes the catalogue into Spatie and removes the 60 old permissions, which nothing ever checked. **Run it on every database when deploying.** Until it has run, the access checks let everything through, so nobody gets locked out.
- Routes check the right permission per area through `access:<area>` (opening a page needs view, saving needs manage, deleting needs delete).
- The sidebar is built from the user's real permissions. Tenants keep their personal menu.
- A page sweep under each role shows every role opening its areas and getting 403 elsewhere, with no 500s.

**Roles page (`/roles`, Sharet design)**
- Permissions are grouped by area, with switches and "turn all on".
- Built-in roles can't be renamed or deleted. Custom roles can be added.

**Team & access (`/team`, Brevemaster design)**
- A company admin invites people with an email link (plus an SMS when a phone is given) to set their own password.
- They can change roles and switch logins off; the last admin is protected.
- A table shows what each role can do in each area.

**Fixed along the way**
- `App\Models\Role` was missing, which broke `/tenancies`.
- Legacy `whereHas('role')` calls broke `/tenants`.

**Navigation**
- The sidebar is flat (no dropdowns): six sections, every page one click away, with Team & access and Roles under Setup.
- The header user menu has **Website**.

**No full reloads (`resources/js/spa.js`)**
- Dashboard links swap only the page content in the background; back and forward work.
- Saves in modals call `schargeRefresh()` (79 former `location.reload()` calls), which refreshes the content in the background.
- A page whose scripts can't run twice in one window falls back to one normal load.
- Verified in the browser: Properties, Payments, Water, Calendar and Team load with no reloads and every component started. Invoices falls back to a normal load once and then works fully.
