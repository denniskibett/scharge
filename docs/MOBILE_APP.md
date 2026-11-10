# scharge app (Android now, iOS later)

One app for every role. The app lives in its own repo, `scharge-app` (Flutter), next to this one. This repo holds the
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
4. Staff without a phone: `POST /auth/password` (email + password).

Each phone is its own row in `mobile_sessions` (token stored as a SHA-256 hash). Sign out (`POST /auth/logout`) ends
only that phone. Sessions unused for 90 days stop working. A switched-off user (`users.status = 1`) is refused.

## Screens per role

Default grants from `Access::ROLES`. The Roles page can add or remove any of them.

| Role | Screens (tabs, in order) | Can do |
|---|---|---|
| Tenant | Home (balance, bills), Receipts, Repairs, Visitors | Pay a bill (opens the secret bill link: M-Pesa prompt or Sharet), report a repair, send / cancel a gate pass for their unit |
| Admin (company) | Rent, Unpaid bills, Payments, Readings, Repairs, Visitors, Tenants, Units | Match paybill payments to tenants, remind on WhatsApp with the bill link, record readings, update repairs, issue passes, let guests in/out |
| Property manager | Rent, Unpaid bills, Payments, Readings, Repairs, Visitors, Tenants, Units | As admin, except matching paybill payments (needs `payments.manage`) and changing the gate (`security.manage`) |
| Accountant | Rent, Unpaid bills, Payments, Readings, Tenants, Units | Match paybill payments; readings are view only |
| Meter reader | Readings, Units | Record water and electricity readings with a photo (safe to retry) |
| Maintenance | Repairs, Units | Start, "waiting for parts", done with notes and cost |
| Cleaning staff | Repairs, Units | View only |
| Security | Gate, Units | Type a guest's code, let in, let out, walk-in visitors |
| BnB host | Visitors, Bookings, Units | Guest passes for their units, see stays (units they manage only) |
| Account manager (scharge) | Rent, Payments, Companies | For the companies they own only |
| sysadmin (scharge) | Everything, all companies | |

With more than 5 screens the app shows 4 tabs and "More".

## Endpoints (`/api/v1/app`, "Authorization: Bearer mob_…")

| Method | Path | Who / permission |
|---|---|---|
| POST | `/auth/code`, `/auth/verify`, `/auth/password` | public (throttled) |
| GET | `/me` | signed in |
| POST | `/auth/pin`, `/auth/unlock`, `/auth/logout` | signed in |
| GET | `/tenant/summary`, `/tenant/bills`, `/tenant/bills/{id}` | tenant · `invoices.view` |
| GET | `/tenant/payments` | tenant · `payments.view` |
| GET / POST | `/tenant/repairs` | tenant · `maintenance.view` / `maintenance.manage` |
| GET | `/rent`, `/payments`, `/paybill/unmatched` | staff · `payments.view` |
| POST | `/paybill/{id}/assign` | staff · `payments.manage` (money moves through `PaybillPayments`) |
| GET | `/bills`, `/bills/{id}` | staff · `invoices.view` |
| GET | `/tenants` | staff · `tenants.view` |
| GET | `/units`, `/bookings` | staff · `properties.view` |
| GET | `/companies` | staff · `companies.view` |
| GET | `/meters`, `/meters/{id}/readings` | staff · `water.view` |
| POST | `/meters/{id}/readings` | staff · `water.manage` (multipart, `photo`, `client_ref`) |
| GET / POST | `/repairs`, `/repairs/{id}` | staff · `maintenance.view` / `maintenance.manage` |
| GET / POST | `/gate/passes`, `/gate/verify`, `/gate/passes/{id}` | Gate service rules (guards, managers, hosts, tenants) |

Tests: `tests/Feature/MobileAppTest.php` (sign-in, PIN lockout, screens per role, Roles-page changes, and
cross-company / cross-tenant checks for bills, rent, tenants, units, meters, repairs, gate and bookings).

## Deploying

- Migration: `2026_11_11_000600_create_mobile_sessions.php` (listed in `scripts/deploy-migrations.txt`).
- The APK for the website: build it in `scharge-app` (see its README) and copy it to `public/downloads/scharge-app.apk`
  here before `scripts/push.sh`. It is not kept in git. Without the file the pages show "coming soon" instead of the link.

## Not built yet

- Paying inside the app with a native STK screen (Pay opens the bill link page, which sends the prompt).
- An offline queue for readings in basements without signal (readings retry safely with `client_ref`).
- Push notifications, Play Store and App Store listings, iOS build (needs a Mac signing setup and Apple account).
- Self sign-up from the app: an SMS code only opens an account that a landlord, company or host signup created.
