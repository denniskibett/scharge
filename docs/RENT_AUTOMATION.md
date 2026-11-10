# Rent automation: monthly bills and arrears reminders

*Added 2026-11-10 on `claude/rent-automation`.*

A landlord company switches it on under **Integrations › Rent automation**. It stays off until they do. After that, scharge:

1. **Bills every active tenancy each month.** It runs on the company's invoice day, or at the first run after that day if one was missed.
2. **Sends each tenant the bill by SMS with a pay link,** unless the company chose "No, only create the invoices".
3. **Reminds tenants who are late** at the company's reminder days (default 1, 7 and 14 days after the due day). Each tenant gets one message per stage, showing the total they owe.

## What goes on the monthly bill

| Line | From |
|---|---|
| Rent | `units.rent_amount` |
| Service charge, garbage, security | `units.service_charge`, `garbage_charge`, `security_charge` |
| Water, flat rate | `units.water_charge` when `water_billing_type = flat` |
| Water, metered | Last month's reading only (`water_readings` for the month before), × `custom_water_rate` or the property's `water_rate`. With no reading for that month, water is left off (add it from Water readings). An older reading is never billed again. |

Lines of KES 0 are left out, and a tenancy with nothing to bill is skipped.

**Who is billed:** tenancies with `status = active` that cover the month: moved in by its last day and not moved out before its first. A tenancy belongs to a company through `tenancies.company_id`, else the unit's, else the property's (older rows have no `company_id`).

**Never twice:**
- A tenancy that already has any `monthly` or `move_in` invoice dated in that month is skipped. So the move-in month isn't billed again, and neither is a month someone invoiced by hand.
- The tenancy row is locked while its invoice is created, so two runs at once can't both bill it.

The invoice gets `company_id`, `status = unpaid` and `invoice_type = monthly`.

## Due dates and reminders

- **Due date:**
  - monthly bills are due on the company's **due day** of the billing month (default the 5th);
  - move-in bills are due on the move-in date.
- **Late days** are counted from the **oldest** unpaid bill (status `unpaid` or `partial`, balance above 0) of an active tenancy.
- **Stage:** each run picks the largest reminder day that has passed. A stage is sent once.
  - Example: with 1, 7, 14, a tenant 30 days late gets the 14-day reminder once, and nothing more until a newer bill becomes late.
- **The message** gives the total owed across all the tenant's late bills, with a pay link for the oldest one. The other bills are recorded as reminded at that stage (`status = grouped`).
- **Channel:**
  - SMS through the company's sender (`KenyaSMS::forCompany`).
  - With "WhatsApp" chosen and the company's own WhatsApp number set up, reminders go by WhatsApp instead.
  - Foreign numbers fall back to WhatsApp as SMS already does.
- **Pay link:**
  - a Sharet pay link when the company has no Daraja app of its own and Sharet is set up;
  - otherwise the public invoice page.

Every bill and reminder is a row in `rent_messages`, unique per invoice, kind (`bill`/`reminder`) and stage. Its status is `sent`, `failed`, `no_phone` or `grouped`, and it keeps the text that was sent. The row is written **before** sending, so a second run never sends the same message again. A failed send is not retried automatically, which avoids double SMS.

## Pieces

| Piece | Where |
|---|---|
| Settings (switch, invoice day, due day, send bill, reminder days, channel) | `config/integrations.php` › `rent_automation`, read by `App\Services\Rent\RentSettings` |
| Monthly run | `App\Services\Rent\RentRun` |
| Messages | `App\Services\Rent\BillNotifier`, `App\Models\RentMessage` |
| Reminders | `App\Services\Rent\ArrearsReminders` |
| "Test" button: preview of the next run, sends nothing | `App\Services\Rent\RentPreview` |
| Commands | `rent:invoice [--company=] [--month=YYYY-MM] [--dry-run]`, `rent:remind [--company=] [--dry-run]` |
| Schedule | `routes/console.php`: `rent:invoice` daily 07:00, `rent:remind` daily 10:00 (Africa/Nairobi, outside SMS quiet hours) |
| Invoices › Generate all | `InvoiceController::generateAllInvoices` now uses `RentRun` for **the signed-in user's company only** |
| Table | `database/migrations/2026_11_10_120000_create_rent_messages_table.php` |
| Tests | `tests/Feature/RentAutomationTest.php` (builds its own tables; see below) |

## Deploying

The repo's migrations don't match production yet (SYSTEM_AUDIT.md §2), so **don't run a plain `php artisan migrate`**. Run only the new one:

```bash
php artisan migrate --path=database/migrations/2026_11_10_120000_create_rent_messages_table.php --force
```

The server cron must run `php artisan schedule:run` every minute (already needed for M-Pesa reconcile and the sweeps).

Before switching it on for a company, press **Test**. It shows how many invoices the next run would create and how many tenants are due a reminder, without sending anything. Or run `php artisan rent:invoice --company=ID --dry-run`.

## Found while building (not fixed here)

- **Generate all billed every company.** `generateAllInvoices` used to bill every company's active tenancies, not just the signed-in company's. Fixed here.
- **Invoices page lists every company's invoices.** `InvoiceController::index` loads `Invoice::with(...)->get()` with no company filter, so one company can see another's invoices. `App\Traits\CompanyScoped` exists but no model uses it.
- **Public invoice links can be guessed.** `/public/invoice/{id}` is reachable by numeric id with no signature, so anyone can step through ids and see other tenants' bills. Reminders send these links when Sharet isn't used. A signed or random-token link would close this.
- **The test suite can't build the database.** 23 of the existing tests fail on `wallets` already existing (duplicate wallet migrations).
