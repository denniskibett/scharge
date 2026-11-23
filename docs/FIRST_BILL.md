# First bill for new accounts

*Added 2026-11-23 (owner: "pay one month, get three months").*

A new company account is open from the day it is created. Nothing is billed until scharge staff set its price.

1. A sysadmin opens **Admin › Companies › {company} › Modules** and sets **Price per unit** (KES a unit a month). The change is logged under Changes.
2. Once the company has units and a price, scharge issues **one month's bill**: units × price, due in **7 days** (`INV-FIRST-…`, marked `first_bill` in `invoice_json`).
   - `billing:first-bills` runs hourly. It waits until the last unit was added over an hour ago, so a bulk import is counted whole.
   - Saving a price issues the bill straight away when the units are already in.
3. Paying the bill (Sharet Pay, wallet, or marked paid by any route) opens **three months** from the day it was paid: the subscription turns `active` with `ends_at` = paid + 3 months, and so does the company.
4. Unpaid after the due date, the billing lock applies as for any bill (`docs/BILLING_LOCK.md`).

Only companies created on or after `BILLING_FIRST_BILL_FROM` (default 2026-11-23) get it, and only if they have no scharge bill yet. Older companies are billed by hand. No price, no bill: an amount is never guessed.

| Setting | Default |
|---|---|
| `BILLING_FIRST_BILL_FROM` | 2026-11-23 |
| `BILLING_FIRST_BILL_DUE_DAYS` | 7 |
| `BILLING_FIRST_BILL_MONTHS` | 3 |

Pieces: `app/Support/FirstBill.php`, `app/Console/Commands/FirstBills.php`, `CompanyModuleController::price` (`PUT /admin/companies/{id}/billing-price`, sysadmin), the Price per unit box in `resources/views/admin/companies/partials/modules.blade.php`, `BillPayments::settle`, `tests/Feature/FirstBillTest.php`.

Going live: `mysql scha_main < database/schema/2026_11_23_first_bill.sql`, then deploy.
