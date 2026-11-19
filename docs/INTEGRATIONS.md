# Integrations (Settings › Integrations)

Each card is one `company_integrations` row per company (credentials encrypted with APP_KEY), defined in
`config/integrations.php`. Every card has Save, **Test** and an on/off switch. Secret fields are write-only.
Install once: `mysql scha_main < database/schema/2026_11_20_integrations_more.sql` (webhook_deliveries,
integration_links, bank_statement_lines). Code: `app/Services/Connectors/`.

Events come from model events in `AppServiceProvider` → `Connectors\Hub` (after the transaction commits, after the
web response). Failed sends are retried every 5 minutes by the `integrations-retry` schedule.

| Card | What it does | What the company supplies | Test |
|---|---|---|---|
| Webhooks | Signed JSON POST on `payment.received`, `invoice.issued`, `invoice.paid`, `tenancy.started`, `tenancy.ended`, `booking.created`. Retried 1 m, 5 m, 30 m, 2 h, 12 h. | https URL (public only), signing secret, optional event list | Signed `ping` |
| Email from your address | Bills, receipts and gate passes are sent from the company's own mailbox (`CompanyMail::send`). | Google / Microsoft / Zoho (address + app password), Mailgun (domain + key), or any SMTP | Signs in and emails the signed-in user |
| Google Sheets & Calendar feeds | Private CSV links (payments, bills, arrears, tenants) for `=IMPORTDATA()`, plus an iCal link (stays, move-ins, move-outs). Token in the path; "Make new links" rotates it. | Nothing | — |
| Bank statement import | `/bank-import`: upload any bank's CSV/XLSX; money-in lines are matched by bill number (INV-000123), unit + exact amount, tenant phone, or name + exact amount. A person confirms (or sure matches post at once). | Bank, auto-post choice | — |
| Equity Bank (Jenga) | Reads the mini statement every morning (06:30) into the same matching. | Merchant code, API key, consumer secret, account number, RSA private key | Signs in, reads balance |
| KRA eTIMS (OSCU) | Each bill is sent to KRA once (`saveTrnsSalesOsdc`) when paid or when issued; receipt no., signature and QR link are kept in `integration_links.data` (`Etims::receipt($invoiceId)`). Gap-free invoice numbers per branch. | KRA PIN, branch, OSCU device serial, VAT type | Registers the device (`selectInitOsdcInfo`) and the Rent item |
| QuickBooks Online / Xero / Zoho Books | Tenant → customer, bill → invoice, payment → payment, as they happen (from the connect date or "Copy records from"). | Click **Connect** and approve; optional item / account codes | Shows the connected company |
| Smart meters & IoT | Vendor cloud posts `POST /api/v1/meter-readings` `{serial, reading, read_at, client_ref}` with `Bearer mgw_<company>_…` (key shown once on Save). Per-meter device tokens still work at `/api/v1/smart/readings`. | Vendor name | Counts meters reporting in 24 h |
| SMS sender ID (paid by scharge) | No staff step: the company types the name, downloads the pre-filled authorisation letter, signs and uploads it; `SenderIds::request` emails letter + KYC documents to the desk set on the platform KenyaSMS card ("Send companies' sender ID requests to"), admins copied. `sender-ids-sync` (hourly) reads scharge's KenyaSMS `GET /account/sender-ids` and switches the name on when it is active, emailing the company's admins. SMS always use scharge's account (the company's plan pays). | Sender name, signed letter (KYC documents reused) | Checks approval, then KenyaSMS |
| WhatsApp (managed) | "scharge sets up my number": staff create the number in scharge's WABA, fill Phone number ID, set status Live; messages then use scharge's token with the company's number. | Display name, number | Graph API check once live |

**Go live checklist** (`Connectors\GoLive`, top of a company's Integrations page): verify business, get paid, SMS name, email, brand, automatic bills, WhatsApp (optional).

Platform cards (scharge only): **QuickBooks app**, **Xero app**, **Zoho Books app** hold scharge's OAuth client id/secret.
Redirect URIs to register with each provider:
`https://scharge.sharet.africa/integrations/oauth/{quickbooks|xero|zoho_books}/callback`.

Tests: `tests/Feature/IntegrationsMoreTest.php`.
