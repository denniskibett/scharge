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

## Fixed alongside

- **Generate all** used to bill every company's active tenancies. It now bills only the signed-in user's company.
- **Companies could see each other's invoices and tenancies.**
  - A `company` global scope on `Invoice` and `Tenancy` (`App\Support\CompanyAccess`) now limits every list, search and `{invoice}`/`{tenancy}` URL to the user's own company. It uses `company_id`, or the tenancy's unit or property for older rows.
  - Account managers without a company see the companies they own.
  - Sysadmins, the scheduler, queues, payment callbacks and tenants without a company are not filtered, so they behave as before.
  - `withoutGlobalScope('company')` opts out where a cross-company read is intended (the public bill page).
- **Public bill links could be guessed.**
  - `/public/invoice/{id}` and its pay and status calls now need `?t=` plus a secret code: an HMAC of the id with `APP_KEY` (`App\Support\InvoiceLink`).
  - Every link (SMS, email, PDF, calendar, API, pay-link button) is built with `InvoiceLink::url()`.
  - Links sent before this change show "This link is not valid", and the tenant asks for the bill again. Changing `APP_KEY` would also invalidate all links.

- **Payments, tenants and units** follow the same rule (10 Nov, same branch):
  - **payments:** through their invoice;
  - **tenants:** `company_id`, or one of the company's tenancies for older rows;
  - **units:** `company_id`, or their property's company, or a unit the company manages in `unit_managements` (a BnB host's unit in a managed building).
- **New tenants get the creator's company.** The forms never saved a company on new tenants, so they would have vanished from the list of the company that added them.
- **System work runs through `CompanyAccess::bypass()`,** so it sees everything whoever is signed in:
  - M-Pesa and Sharet payment settlement, so a payment is never missed or recorded twice;
  - BnB host units and property claims;
  - the public bill page;
  - the property register's unit count.
- **Properties are not filtered by this rule.** Their screens already check the company, and the shared property register must see every property to prevent duplicates.

## Still open

- **Old rows with no company:** the 16 local tenant records with no company and no tenancy are now visible only to sysadmins. On live, check them and assign a company or remove them.
- **Existing tests:** 23 still fail on `wallets` already existing (duplicate wallet migrations).
