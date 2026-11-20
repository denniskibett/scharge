# Currency and conversion

scharge stores every amount in the currency it was created in and never rewrites it. Changing a currency setting
only changes how figures are **shown**. Conversion uses one rate source: Sharet's FX rates.

## Settings

| Setting | Where | Effect |
|---|---|---|
| Platform default display currency | Sysadmin › System › Currency Settings (`system.currency`) | Dashboards, reports and billing totals for everyone who hasn't picked their own. Only currencies Sharet quotes can be chosen; any other stored value falls back to KES. |
| Company display currency | Settings › White label › Display currency (`companies.display_currency`, NULL = platform default) | The same, for one company. Company admins and sysadmins can set it. |
| Book currency | `config/currency.php` › `book` (KES) | The currency of every stored amount without its own `currency` column: rent, invoices, payments, wallets, the ledger. Never change it on a live system. |

`subscription_invoices` and `billing_payments` have their own `currency` column and are charged in it.

SQL (hand-applied, no migration): `database/schema/2026_11_20_display_currency.sql` adds `companies.display_currency`
and resets any leftover `system.currency` that Sharet doesn't quote (some installs hold USD or AUD from old defaults)
back to KES, so nothing changes on deploy. The app works before the SQL is applied: every company follows the platform default.

## Rate source

`App\Support\FxRates` reads Sharet's reference rates: Safaricom's M-PESA rate for the day, recorded by Sharet treasury
(`sharetafricanew`: `config/fx.php`, `App\Fx\Rates`, table `fx_rates`). These are reference rates with no margin,
because nothing is traded here: it's display only.

- URL: `SHARET_FX_URL`, default `{SHARET_BASE_URL}/fx/rates` (`https://sharet.africa/api/v1/fx/rates`). The platform's Sharet API key is sent if one is connected.
- Shapes read: `{"base":"KES","date":"2026-11-20","rates":{"USD":"0.0077","UGX":"28.65"}}`, or Sharet's pair form
  `{"date":"…","pairs":{"KES/UGX":"28.65"}}` (also wrapped in `data`). `X/KES` pairs are inverted; other pairs are ignored.
- Cached for `FX_CACHE_MINUTES` (360), refreshed hourly by `php artisan fx:rates`.
- The last good set is kept in `storage/app/fx-rates.json`. If Sharet can't be reached, those are used and the note says it's the last known rate. Sharet is asked again after 15 minutes, not on every page.
- With no rate at all, a figure stays in its own currency. scharge never shows a guessed number.

**Sharet doesn't serve `/api/v1/fx/rates` yet** (20 Nov 2026). Until it does, every figure shows in KES.

## In code

```php
use App\Support\Money;
use App\Support\Currency;

Money::format($amount, $invoice->currency);   // "KES 1,500.00": its own currency, never converted (what's charged)
Money::show($bookAmount);                     // in the viewer's display currency, converted at Sharet's rate
Money::show(25, 'USD', 'KES');                // from and to
Money::convert('1500', 'KES', 'USD');         // "11.63" (string, half-up to the currency's decimals) or null with no rate
Money::total([[1500, 'KES'], [10, 'USD']]);   // ['amount' => …, 'currency' => …, 'missing' => [...]]
Money::note();                                // "Shown in USD at Sharet's rate of 20 Nov 2026 (1 KES = 0.0077 USD)."
Currency::display();                          // the signed-in user's company's choice, else the platform default
```

Blade: `<x-fx-note />` prints that rate line next to converted figures (nothing when no conversion happens).
JS (charts): `window.scMoney.fmt(bookAmount)`, `scMoney.short(v)`, `scMoney.conv(v)`, `scMoney.code`, set in `layouts/app`.

Rounding is bcmath at 12 places, then half away from zero to the target's decimals (KES/USD 2, UGX/TZS/RWF 0).

## Converted so far

- Dashboards: stat cards for admin, property manager and accountant (`partials/card/card-dashboard`), the sysadmin dashboard (revenue, MRR, plan revenue), the analytics summary tiles, the revenue analytics table and every money chart (accountant, revenue chart), with the rate line at the top.
- Reports (`reports/index` and `resources/js/components/charts/reports.js`): headline figures, property and arrears tables, expense totals and charts.
- Billing (`billing/index`): bills, the wallet and the ledger stay in their own currency; the amount due and the wallet get an "about USD …" figure and the rate line.
- The scharge invoice page and PDF (`billing/invoice*.blade.php`): charged in the bill's currency, plus "About USD …. You pay KES …" and a line with the rate and its date.
- `SystemHelper::currency()` / `currencySymbol()` now label stored amounts with the book currency's symbol, not the editable platform symbol.

Deliberately not converted: anything a tenant sees or pays, amounts typed into forms, individual payment and transaction rows, and exports (CSV columns are labelled KES).

## Still hard-coding "KES" (for later)

Format these through `Money::format()` (stored amounts) or `Money::show()` (totals):

- Modals: units create/edit, tenancies create, invoice create, payments create, wallet payment/deposit/statement/schedule, subscriptions create, water reconciliation, readings.
- Pages: `tenancies/show`, `units/show`, `properties/show`, `water/show`, `rent/index`, `tenant/wallet`, `public/invoice-payment`, `home/listings`, `welcome`, `admin/companies/show`, SMS broadcast and campaigns.
- Cards and tables: `card-wallet-summary`, `card-subscription`, `card-invoices`, `table-properties`, `table-transactions`, `table-readings`, `partials/reports/water`.
- Accountant dashboard deposit confirm dialogs (stored amounts, so label only).
- PHP: `WalletController`, `WalletService`, `InvoiceController`, `PaymentController`, `BillPayments`, `PropertyOnboarding`, SMS `CampaignService`/controllers, `Guides`, `Seo`, `Playbook`, the API (`V1Controller`, mobile `Presents`), `emails/invoice`, `crm/pdf/partner-pack`.
- `BillingLock::status()` sums unpaid bills without looking at their currencies. That's fine while every bill is in KES; use `Money::total()` before any non-KES bill goes out.
- Mobile API: return `display_currency` and the rate so the apps can do the same.
