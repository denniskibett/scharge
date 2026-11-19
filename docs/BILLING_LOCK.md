# Billing lock ("money doesn't sleep")

When a company has a scharge bill that is unpaid and past its due date, the whole company is locked at once. A bill counts as unpaid when its `subscription_invoices` row is `pending` or `failed`. The lock lifts as soon as the bill is marked paid; nothing is cached for longer than 30 seconds.

The logic lives in `App\Support\BillingLock`. `Modules::deny()` runs it before the module checks, so it applies everywhere:

- web pages (the `CheckModule` middleware);
- the scharge app (`AuthenticateMobile`);
- API keys (`AuthenticateApiKey`).

## What a locked company sees

**Web pages** redirect to `/billing` (route `billing.index`). The sidebar shows only Billing and Profile.

**The scharge app and API keys** get a JSON 403 with `error: "billing_locked"` and the billing URL.

**On `/billing`:**

- The owner (admin role) and the accountant see the unpaid bills, the amount due, and recent payments. They can pay each bill with Sharet Pay or from the company's scharge wallet (`docs/BILLING_PAYMENTS.md`). Without online payment set up, they see the paybill instead (`SCHARGE_PAYBILL`).
- Everyone else sees "Your company's access is paused. Contact your admin to restore it." with the admins' names and contacts, and no amounts.

These stay open while the company is locked: billing, sign-in and sign-out, profile, password, and the email-code screen. The app's sign-in, crash reports and device registration stay open too.

## Who is never locked

- scharge staff: sysadmin, account_manager and sales.
- Tenants. They can still pay rent.
- People without a company.
- The public site, pay links and payment callbacks. These have no signed-in company user.

## Sysadmin

**Companies list:** an "Overdue" badge, which becomes "Locked · unpaid" once the lock is on.

**Admin › Companies › {company} › Modules tab:** shows the bill status. "Give grace" lets the company in until a chosen date; "End grace" removes that. These changes are logged in `company_module_logs` as `billing_grace`.

To see which companies are, or would be, locked, run this dry run:

```
php artisan billing:lock-report
```

## Settings

All settings go in `.env`, read through `config/billing.php`:

| Setting | Meaning |
|---|---|
| `BILLING_LOCK_ENABLED` | `false` by default. Switch it on only after checking the report. |
| `BILLING_LOCK_FROM` | Bills due before this date never lock. Use it for old test or migrated bills. |
| `SCHARGE_PAYBILL` | The paybill shown to payers. |
| `SCHARGE_PAYBILL_ACCOUNT_HINT` | The account to enter at the paybill. |
| `SCHARGE_BILLING_EMAIL` | Billing contact email. |
| `SCHARGE_BILLING_PHONE` | Billing contact phone. |

## "Pay 1 month, get 3"

This is how bills are generated, not how the lock works. The lock only reads due dates and paid status.
