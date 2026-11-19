# Claude assistant

Claude (Anthropic, `claude-opus-5-5`, low effort, server-side fallback on) **drafts**. A person **sends**.
Nothing leaves scharge and no record changes until someone presses Send or Apply fix on **Assistant** (`/assistant`).

Install once:

```bash
mysql scha_main < database/schema/2026_11_20_ai_drafts.sql
```

## Switches (Integrations)

- **Claude assistant (scharge)** (platform card):
  - scharge's Anthropic key. When it's blank, the Document reading (KYC) key or `ANTHROPIC_API_KEY` is used.
  - A daily cap of drafts per company (default 60).
  - scharge CRM drafts on or off.
  - "What Claude should know about scharge": pricing and policy text used in every scharge draft.
- **Claude assistant** (each company):
  - Tone and sign-off.
  - Payment follow-ups: after 3, 7 or 14 days late, or off. Sent by SMS, WhatsApp or email.
  - Data clean-up: weekly or on demand.
  - House rules, used in every draft.
- **Email from your address › Let the Claude assistant read this inbox:** read-only IMAP. Emails are opened with FT_PEEK, so they are never marked read, moved or deleted. Only emails from the company's own tenants get a draft.

## What gets drafted (`App\Services\Assistant\Sources`, every 15 minutes, 07:00 to 19:00)

| Kind | For | Facts given to Claude | Send does |
|---|---|---|---|
| Prospect replies | scharge | the prospect's reply, the last 6 emails in the thread, their segment and pains, the demo link | `ReplyService::answer`: same thread, clears the reply alert |
| Prospect follow-ups | scharge | the CRM task due today, prospect facts, demo link | email logged on the prospect; follow-up marked done |
| Subscription reminders | scharge | invoice number, amount, due date, the pay-1-unlock-3 policy, `/billing` | email to the company (or its first admin) |
| Tenant emails | company | the tenant's email, unit, balance, pay link | reply from the company's own mailbox, in the thread |
| Payment follow-ups | company | each unpaid bill, total owed, last payment, pay link | SMS, WhatsApp or email in the company's name |
| Data clean-up | company | the problem, how many, up to 5 examples, the proposed fix | `Cleanup::apply`: re-checks each record, then fixes it |

Each thing is drafted once (`ai_drafts.source_ref`). For example, a late tenant gets one payment follow-up draft per week.

Claude is told to use only the facts given: never to invent amounts, dates, links, discounts or threats, and to answer in Swahili when the person wrote in Swahili. Anything it was unsure about goes in a **Check:** note for the reviewer.

## Data clean-up checks (`App\Services\Assistant\Cleanup`)

- **Units marked occupied with no active tenancy:** mark them vacant.
- **Units marked vacant that have an active tenancy:** mark them occupied.
- **Bill total not equal to the sum of its lines:** set the total to the sum of the lines.
- **"Paid" not matching the completed payments:** recalculate paid and the status.
- **Tenant phones not in +254 form:** rewrite them.
- **Tenancies still active more than 7 days after the move-out date:** end them.
- **Two tenants sharing one phone number:** review only. This is never fixed automatically.

## Code clean-up

Code clean-up is not part of the app. It runs as a Claude Code job on the repository, separately.

Tests: `tests/Feature/AssistantTest.php`. They fake Claude with `Claude::$fake`.
