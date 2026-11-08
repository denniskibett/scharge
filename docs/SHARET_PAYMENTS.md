# Payments through Sharet Africa, Integrations and API keys

*Added 2026-11-08 on `claude/crm`.*

## How a tenant payment is routed

For each invoice, the landlord company is resolved from `invoice.company_id`, falling back to `tenancy.company_id`, then the estate's `company_id`. All unpaid invoices in live data have a NULL `company_id`.

1. **The company has its own M-Pesa Daraja app switched on** (Integrations › M-Pesa Daraja). The tenant gets an STK prompt using the company's credentials, and the money goes to the company's own paybill or till. scharge only records it.
2. **Otherwise, if scharge's Sharet account is connected** (Integrations › Sharet Africa, sysadmin only), the tenant is sent to a Sharet pay page. There are two modes:
   - **Rail (default).** The money goes to scharge's Sharet wallet. `payments:sweep` pays the landlord at **08:00, 12:00 and 16:00 Africa/Nairobi**, minus `fee_percent`, to the M-Pesa number, paybill, till or bank set in Integrations › Rent collection & payouts.
   - **Connected.** The landlord connected their own Sharet account, so the pay link is created on it (`Sharet-Account` header) and the money is theirs at once.
3. **Otherwise**, scharge's platform Daraja app is used (the old behaviour).

Sharet's API cannot send a phone prompt directly: tenants open the pay link. A "send prompt" endpoint in Sharet is a separate task.

## Pieces

| Piece | Where |
|---|---|
| Schema (you apply; not a migration) | `database/schema/2026_11_08_sharet_payments.sql` |
| API client and webhook signature check | `app/Services/Sharet/SharetClient.php` |
| Pay links, applying payments, polling | `app/Services/Sharet/SharetCollections.php` |
| Sweeps | `app/Services/Sharet/SharetPayouts.php`, `php artisan payments:sweep [--dry-run] [--slot=2026-11-09T08]` |
| Webhook | `POST /webhooks/sharet` (`SharetWebhookController`), signed `Sharet-Signature`, deduped on event id |
| Poll for missed webhooks and connected-mode requests | `php artisan sharet:sync` (every 5 min) |
| Shared invoice application, also used by M-Pesa | `app/Services/InvoicePaymentService.php` |
| Integrations page | `/integrations` (`IntegrationController`, `config/integrations.php`) |
| API keys page | `/api-keys` (`ApiKeyController`) |
| scharge API v1 | `routes/api.php`: `GET /api/v1/me`, `GET /api/v1/invoices/{id}`, `POST /api/v1/invoices/{id}/pay-link` (scope `collect`) |
| Staff "send pay link" | `POST /invoices/{invoice}/pay-link` (texts the tenant) |

## Going live, in order

1. **Apply the schema:** `mysql scha_main < database/schema/2026_11_08_sharet_payments.sql`. It is safe to re-run, and it also adds the `account_manager` role.
2. **Create a Sharet key** in Sharet › Developers › API keys with scopes collect, payout and connect. Start with `sk_test_`. For live payouts, add the scharge server's IP to the key.
3. **Connect scharge to Sharet:** open scharge › Payments & API › Integrations › scharge platform › Sharet Africa, paste the key, set the fee and minimum, then click **Test connection**. This also registers the webhook (it needs the public HTTPS URL) and stores the `whsec_` secret.
4. **Set up each landlord company:** on Integrations › (company) › Rent collection & payouts, choose rail plus a payout destination, or connect their Sharet account. A company with its own Daraja app fills in M-Pesa Daraja instead.
5. **Run the cron** (already on the live server): `schedule:run` every minute runs `sharet:sync` and `payments:sweep`.

## Verified (2026-11-08, scratch copy of live data, Sharet faked with real signatures)

31 of 31 checks passed:

- **Routing:** the public pay page redirects to Sharet; a company with its own Daraja uses its own app, and a till uses Buy Goods. A connected landlord's pay link carries the `Sharet-Account` header.
- **Pay links:** they carry an Idempotency-Key, and an open link is reused for the same amount.
- **Webhook:** a bad signature gets 400. A signed event marks the invoice paid exactly once; a duplicate event or a later poll does not pay it twice.
- **Sweep:** it pays the net after the 2.5% fee to the chosen destination, and running the same slot again doesn't pay twice.
- **API:** `/me` 200; a bad key 401; another company's invoice 404; pay-link without the collect scope 403.
- **Pages:** Integrations and API keys load for sysadmins and company admins; tenants get 403; secrets never reach the browser.
