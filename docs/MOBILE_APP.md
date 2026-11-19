# scharge app (Android)

One app for every role. The app lives in its own folder, `../mobile/scharge` (Flutter). This repo holds the
API it talks to (`routes/api.php`, prefix `/api/v1/app`) and this document.

Designs: the "7 · Mobile app" page of the scharge brand canvas (Tenant, Property manager & landlord, Water meter
reader, BnB host, Sign in, Security guard).

## How access works

- **The server lists the screens.** After sign-in, `GET /api/v1/app/me` returns `screens` (keys, titles and the
  buttons allowed on each) from `App\Support\MobileAccess::screens()`. The app draws only those and never works out
  access itself.
- **One source of truth.** Every screen and every endpoint needs a permission from `App\Support\Access`
  (`invoices.view`, `water.manage`, ...), checked through Spatie. Changing a role on the Roles page changes the app on
  the next unlock. Before `php artisan access:sync` has run, the role defaults in `Access::ROLES` apply (fail closed).
- **Every call checks again** (`App\Http\Middleware\MobileCan`): `staff` routes need a company user or scharge staff,
  `tenant` routes need the tenant role and the person's own tenant records.
- **Company scope.** The token signs the person in for the request (`AuthenticateMobile`), so the same `company`
  global scopes as the dashboard apply (`CompanyAccess`, `RentRun::ownedBy` for rows without `company_id`). Tenants
  are filtered by their own tenant ids only. Hosts see units through `unit_managements`. The gate uses
  `App\Services\Gate\Gate`'s own rules.
- **White label.** `brand` in `/me` comes from `company_brands` (`App\Support\Brand::forCompany`): staff get their
  company's label, tenants get their landlord's, scharge staff get scharge's.

## Sign-in (the JAZZA pattern)

1. New phone: phone number -> `POST /auth/code` (6-digit SMS, 10 minutes, 5 tries, 3 codes per 10 minutes per number;
   the same answer whether or not the number has an account) -> `POST /auth/verify` -> token.
   One number with several accounts: verify answers `choose_account`, the app asks, then verifies with `account_id`.
2. Create a 4-digit PIN (`POST /auth/pin`) and turn on fingerprint / face / phone lock.
3. Every day: fingerprint, face or the phone's own lock opens the app (checked on the phone; the token sits in the
   phone's keystore). Or the PIN (`POST /auth/unlock`, checked on the server). 5 wrong PINs delete that phone's session.
4. Password sign-in: `POST /auth/password` with the phone number or the email, the same password.
5. Create my account (`POST /auth/register`): for tenants their landlord has already added by phone. With the SMS
   code they set their name, ID number, email and password, then the PIN. A number nobody added gets "Ask your
   landlord to add you" (404 `not_added`), never a new empty account.

Each phone is its own row in `mobile_sessions` (token stored as a SHA-256 hash). Sign out (`POST /auth/logout`) ends
only that phone. Sessions unused for 90 days stop working. A switched-off user (`users.status = 1`) is refused.

## Screens per role

Default grants from `Access::ROLES`. The Roles page can add or remove any of them.

| Role | Screens (tabs, in order) | Can do |
|---|---|---|
| Tenant | Home (balance, bills), Receipts, Repairs, Visitors (+ Suggestion box from Home and Account) | Pay a bill (opens the secret bill link: M-Pesa prompt or Sharet), share invoices and receipts, report a repair, gate passes for their home, suggest passes for other units they hold and approve ones suggested for their home, send anonymous suggestions |
| Admin (company) | Rent, Unpaid bills, Payments, Readings, Repairs, Visitors, Tenants, Suggestions, Units | Match paybill payments to tenants, remind on WhatsApp with the bill link, record readings, update repairs, issue passes, let guests in/out |
| Property manager | Rent, Unpaid bills, Payments, Readings, Repairs, Visitors, Tenants, Suggestions, Units | As admin, except matching paybill payments (needs `payments.manage`) and changing the gate (`security.manage`) |
| Accountant | Rent, Unpaid bills, Payments, Readings, Tenants, Units | Match paybill payments; readings are view only |
| Meter reader | Readings, Units | Record water and electricity readings with a photo (safe to retry) |
| Maintenance | Repairs, Units | Start, "waiting for parts", done with notes and cost |
| Cleaning staff | Repairs, Units | View only |
| Security | Gate, Units | Type a guest's code, let in, let out, walk-in visitors |
| BnB host | Visitors, Bookings, Units | Guest passes for their units, see stays (units they manage only) |
| Agent (agency) | Units (+ Bookings when short-stay units are in scope) | Units of properties landlords gave their agency (`property_agents`); booking amounts on those stay the landlord's (hidden) |
| Account manager (scharge) | My day, Pipeline, Follow-ups, Sales, then Requests, Waitlist, Rent, Payments, Companies | CRM on their own book plus unassigned leads (`CrmAccess`): answer or clear prospect replies, move stages (won → customer, lost needs a reason), log and book follow-ups, close follow-ups with the next step, work website requests and the waitlist; Sales tracker shows their funnel, wins, settled platform fees and commission (`CommissionService`); Rent, Payments and Companies for the companies they own (`owner_id`) only |
| Sales (scharge) | My day, Pipeline, Follow-ups, Sales, Requests, Waitlist | Same CRM as an account manager; no company data (no Companies, Rent, Bills…) |
| sysadmin (scharge) | The CRM screens (whole pipeline, whole-team sales), then everything, all companies | |

A person whose role has no app screens sees "Nothing to show for your role yet" with Refresh (before 1.1.0+3 the
app crashed with "Invalid argument(s): 0").

With more than 5 screens the app shows 4 tabs and "More".

Colours follow the brand canvas: red leads (logo, main buttons, prices) and each audience gets one secondary for
summary cards and the selected tab: tenants red only, owners and landlord staff Lake Teal, field staff Savanna Gold
(ink text), BnB hosts Jacaranda, scharge staff ink. A white label replaces the red with the company's colour.

## Gate passes

- A tenant's pass goes straight to the guest only for the flat they live in (their active tenancy with the latest
  move-in, `MobileAccess::homeUnitId`). For any other unit they hold, the pass is saved as `suggested`: the guard
  cannot use it, and only the person living there (or a gate manager) can approve it. The one who suggested it
  cannot approve it.
- Guests: email and phone are both kept. The code goes by email by default, by SMS when asked, and always by SMS
  when there is no email. The app also shares the pass as a QR "ticket" image or on WhatsApp; the QR opens
  `/gate/verify?code=…` on the guard's phone.

## Paying

- Every payment in the app is confirmed first: fingerprint / face / phone lock when switched on, otherwise the PIN
  (4 or 6 digits, the same PIN that opens the app; wrong PINs count towards the 5-try sign-out).
- Methods per landlord: M-Pesa (the bill link page sends the prompt), credit (money paid ahead), Sharet Pay (scharge's
  Sharet account), the landlord's own paybill (unit as the account), bank standing order (Integrations ›
  "Bank standing order (for tenants)": bank, account name and number shown with the unit as the reference).
- Paying ahead: paying more than the bill by M-Pesa, Sharet or paybill keeps the extra as wallet credit
  (MpesaSettlementService, PaybillPayments); the next bill can be paid from it.
- Password sign-in takes the phone number or the email with the same password.

## The bell and the suggestion box

- `GET /notifications` works out what needs the person now (bills to pay, passes to approve, repairs done,
  unmatched paybill, flagged readings, open repairs, new suggestions), only for screens they have. The bell shows
  only when the count is above zero.
- Suggestion box: tenants send anonymous suggestions (`tenant_suggestions` keeps only the company, topic and text,
  never the tenant, unit or phone). People with `tenants.manage` in that company read them; opening the list marks
  them read.

## Endpoints (`/api/v1/app`, "Authorization: Bearer mob_…")

| Method | Path | Who / permission |
|---|---|---|
| POST | `/auth/code`, `/auth/verify`, `/auth/password`, `/auth/register` | public (throttled) |
| GET | `/me` | signed in |
| POST | `/auth/pin`, `/auth/unlock`, `/auth/logout` | signed in |
| GET | `/tenant/summary`, `/tenant/bills`, `/tenant/bills/{id}` | tenant · `invoices.view` |
| GET | `/tenant/payments` | tenant · `payments.view` |
| GET | `/tenant/bills/{id}/pay` | tenant · `invoices.view`: the landlord's pay methods (M-Pesa, credit, Sharet Pay, paybill, bank standing order) |
| POST | `/tenant/bills/{id}/sharet` | tenant · `invoices.view`: a Sharet Pay page (SharetCollections) |
| POST | `/tenant/bills/{id}/pay-credit` | tenant · `invoices.view`: pay from credit paid ahead (WalletService), confirmed with the PIN or the phone's lock |
| GET | `/tenant/wallet` | tenant · `payments.view`: credit paid ahead, its history, how to add money |
| GET / POST | `/tenant/water`, `/tenant/water/query` | tenant: their home unit's water; a reading dispute becomes a repair ticket |
| GET / POST | `/tenant/repairs` | tenant · `maintenance.view` / `maintenance.manage` |
| GET | `/rent`, `/payments`, `/paybill/unmatched` | staff · `payments.view` |
| POST | `/paybill/{id}/assign` | staff · `payments.manage` (money moves through `PaybillPayments`) |
| GET | `/bills`, `/bills/{id}` | staff · `invoices.view` |
| GET | `/tenants` | staff · `tenants.view` |
| GET | `/units`, `/bookings` | staff · `properties.view` |
| GET | `/companies` | sysadmin / account_manager · `companies.view` (as Admin › Companies on the web) |
| GET | `/crm/summary`, `/crm/prospects`, `/crm/prospects/{id}`, `/crm/follow-ups`, `/crm/requests`, `/crm/waitlist`, `/crm/sales` | crm (sysadmin, account_manager, sales) · `crm.view`; rows through `CrmAccess` |
| POST | `/crm/prospects/{id}/stage`, `/crm/prospects/{id}/follow-ups`, `/crm/prospects/{id}/reply`, `/crm/prospects/{id}/reply/handled`, `/crm/follow-ups/{id}`, `/crm/waitlist/{id}` | crm · `crm.manage`; another account manager's prospect or follow-up is 403 |
| GET | `/meters`, `/meters/{id}/readings` | staff · `water.view` |
| POST | `/meters/{id}/readings` | staff · `water.manage` (multipart, `photo`, `client_ref`) |
| GET / POST | `/repairs`, `/repairs/{id}` | staff · `maintenance.view` / `maintenance.manage` |
| GET | `/notifications` | signed in (the bell) |
| POST | `/tenant/suggestions` | tenant · `tenants.view` (anonymous) |
| GET | `/suggestions` | staff · `tenants.manage` |
| GET / POST | `/gate/passes`, `/gate/verify`, `/gate/passes/{id}` | Gate service rules (guards, managers, hosts, tenants) |

Tests: `tests/Feature/MobileAppTest.php` (sign-in, PIN lockout, screens per role, Roles-page changes, and
cross-company / cross-tenant checks for bills, rent, tenants, units, meters, repairs, gate and bookings; the CRM
book per account manager, sales and sysadmin; repair photos). App side: `test/rbac_test.dart` in the app project.

## Deploying

- Migrations: `2026_11_11_000600_create_mobile_sessions.php` and `2026_11_11_001000_create_tenant_suggestions.php`
  (both listed in `scripts/deploy-migrations.txt`).
- The APKs for the website: build them in `../mobile/scharge` (see its README) and copy them to `public/downloads/` here
  before `scripts/push.sh`: `scharge-app.apk` (any phone), `scharge-app-arm64.apk` (newer phones),
  `scharge-app-arm32.apk` (older phones). They are not kept in git. Without `scharge-app.apk` the pages show
  "coming soon" instead of the link.

## Not built yet

- Paying inside the app with a native STK screen (Pay opens the bill link page, which sends the prompt).
- An offline queue for readings in basements without signal (readings retry safely with `client_ref`).
- Push notifications and a Play Store listing. The app is Android only for now (no iOS project).
- Self sign-up from the app: an SMS code only opens an account that a landlord, company or host signup created.
