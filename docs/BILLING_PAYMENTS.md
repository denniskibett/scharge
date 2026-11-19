# Paying scharge bills from Billing

*Added 2026-11-19 on `claude/rent-automation`.*

The owner (admin role) and the accountant can pay an unpaid scharge bill (`subscription_invoices`) from `/billing`, even while the company is locked (see `docs/BILLING_LOCK.md`). On the site and in emails this is always **Sharet Pay**.

## Two ways to pay

1. **Pay with Sharet Pay** is the default. scharge creates a Sharet payment request on **scharge's own Sharet account**: no `Sharet-Account` header, and never the company's own Daraja app or connected account, because this money is scharge's. The payer is sent to Sharet's hosted page, where they pay by M-Pesa (a prompt on their phone) or from a Sharet balance. They can enter another phone number first. An open link for the same amount is reused.
   - Sharet's API has no "send STK prompt" call. The prompt comes from the hosted page.
   - When Sharet reports the payment (`payment_request.paid` webhook, or `sharet:sync` every 5 minutes), the bill turns `paid`, with `paid_at`, `payment_method = sharet_pay` and `payment_id` set to Sharet's transaction ID.
2. **Pay from wallet.** The company's **scharge wallet** holds rent collected through scharge (rail mode) that has not been paid out yet, less scharge's fee (`fee_percent` on Integrations › Sharet Africa), less bills already paid from it. Amounts are in whole shillings, like the payout. The button shows only when the wallet covers the whole bill.
   - No money moves and nothing extra is charged. The bill turns `paid` at once (`payment_method = wallet`), and the amount comes off the company's next payout.
   - `payments:sweep` pays out what is left (gross − fee − bills paid from the wallet) at 08:00, 12:00 and 16:00, to the M-Pesa number, paybill, till or bank set in Integrations › Rent collection & payouts. Those payout options are unchanged.
   - If a payout fails, the collections and the wallet payments go back into the next sweep together.
   - A wallet payment and a sweep lock the same rows, so the same rent can never be both paid out and spent on a bill.
   - Companies in **connected** mode have no scharge wallet, because their rent goes straight to their own Sharet account. They pay with Sharet Pay.

Either way, `BillingLock` sees the paid bill and the company unlocks within a minute. `CommissionService` then counts the bill as settled for the account manager.

## Ledger

Each payment is a `billing_payments` row (`database/schema/2026_11_20_billing_payments.sql`, hand-applied). The Billing page shows the company's wallet ledger. It is built from the records themselves, not copied (`App\Support\CompanyLedger`):

- rent collected (`sharet_payment_requests`, rail, paid);
- scharge's fee and each payout (`sharet_sweeps`);
- bills paid from the wallet, which reduce the balance;
- bills paid with Sharet Pay. These are listed in grey because they came from outside the wallet.

If a bill is paid twice (for example a Sharet Pay link is paid after a wallet payment), both payments stay on the ledger and a warning is logged so scharge can refund or credit it.

## Pieces

| Piece | Where |
|---|---|
| Schema | `database/schema/2026_11_20_billing_payments.sql` |
| Pay links, wallet, settling, polling | `app/Services/Sharet/BillPayments.php` |
| Ledger | `app/Support/CompanyLedger.php` |
| Routes | `POST /billing/{invoice}/pay/sharet`, `POST /billing/{invoice}/pay/wallet` (admin and accountant, throttled 10/min) |
| Webhook | `SharetWebhookController`: rent pay links first, then bill pay links |
| Payout | `SharetPayouts::sweep` subtracts unswept wallet payments |
| Tests | `tests/Feature/BillPaymentsTest.php` |

## Going live

1. `mysql scha_main < database/schema/2026_11_20_billing_payments.sql`. Until this is applied, the page shows the old "how to pay" text and no buttons.
2. scharge's Sharet key must be saved and switched on (Integrations › Sharet Africa). Use `sk_test_` first.
