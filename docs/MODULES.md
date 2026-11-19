# Paid modules per company

Each company only uses the modules it pays for. Sysadmins lock or unlock modules per company. The company's plan sets the defaults.

## The answer, in one place

`App\Support\Modules` decides whether module X is on for a company:

1. **Core modules are always on.** They are Properties & units, Tenants & tenancies, Invoices & payments, and Team & access.
2. **A sysadmin override wins while it lasts.** It is a row in `company_modules`: on or off, with an optional expiry date. After the expiry date, the plan applies again.
3. **Otherwise the plan decides.** The company's active or trial subscription points to `subscription_plans.features["modules"]`, a list of module keys.
   - A plan without that list includes every module.
   - A company without an active plan also gets every module.
   - This rule is why existing customers lost nothing when this shipped.
4. **scharge staff are never limited.** That covers sysadmin, account_manager and sales, and anyone without a company.

## Modules

| Key | Module | What it locks |
|---|---|---|
| rent_automation | Rent collection automation | /rent |
| water | Water & meters | /water, /meter-readings, app meter screens, API meter calls |
| maintenance | Repairs & maintenance | /maintenance, /cleaning, app repair screens |
| gate | Gate & visitors | /gate, /security, app gate passes |
| sms | Tenant SMS | /sms, /api/sms |
| bookings | Bookings & short stay | /bookings, app bookings |
| listings | Open listings & enquiries | /enquiries, /demand |
| website | Website & white label | /settings/website, /settings/white-label |
| integrations | Integrations | /integrations, /bank-import |
| api_keys | API keys | /api-keys, and every call made with a key |
| expenses | Expenses & payees | /expenses, /expense-categories, /payees |
| reports | Reports | /reports |
| agents | Agents | /agent, /properties/{id}/agents |
| mobile_app | scharge app | /api/v1/app/*, except sign-in, sign-out and crash reports |

## How it is enforced

- **Web pages.** The `CheckModule` middleware (web group) shows `modules/locked`: "X isn't on your plan", a button to ask the account manager or sales@sharet.africa, and a link back to the dashboard. It is never the generic 403 page. Fetch/JSON calls get a 403 with `{"error": "module_locked", "module", "message", "contact"}`.
- **scharge app and API.** `AuthenticateMobile` and `AuthenticateApiKey` run the same `Modules::deny()` and return the same JSON 403.
- **Sidebar.** `Modules::lockedMenu()` lists the sidebar keys to hide, whatever the role allows.

## Who changes it

- **One company.** On Admin › Companies › {company}, the **Modules** tab (`?tab=modules`) lets a sysadmin set each module to Plan, On or Off.
  - On and Off take an optional "until" date and a note.
  - Each row shows its source (plan or override), the expiry, and who changed it and when.
  - Every change is written to `company_module_logs`.
  - Account managers can see the tab but cannot change it.
- **A plan.** On Subscription plans › {plan}, the **Modules** tab lets a sysadmin tick the modules the plan includes, or choose "Every module". Plan changes are logged with `company_id = 0`.

## Schema

The tables are created by hand-applied SQL in `database/schema/2026_11_20_company_modules.sql`. Until it has run, every module stays on.

## Not covered yet

- Dashboard widgets of a locked module still show.
- The public website and listings of a company whose website or listings module is off still show.
- The scharge app hides screens only after the endpoint says `module_locked`. `/me` does not list locked modules yet.
