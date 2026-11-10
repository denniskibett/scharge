# White label (hosted)

*Added 2026-11-11 on `claude/rent-automation`. First client: Danaff Kenya Co. Limited (company 1).*

A company gets its own brand and its own web address on Scharge's hosting, and full ownership of its data. It stays in the shared database, kept private by the company scopes (`App\Support\CompanyAccess`), so every Scharge update reaches it automatically.

## What the company sees

- **Brand:** its name, logo, dark-background logo, icon and colour everywhere: the dashboard (title, sidebar and header logos, buttons and links), the sign-in page, tenant bill pages, PDF bills and receipts, and bill emails.
  - Bill emails show the logo, the brand colour in the header and footer, and the brand as the sender name, with no "via scharge".
  - "Powered by scharge" is shown or hidden per company, and only Scharge staff can change it.
- **Its own address** (e.g. `portal.danaff.co.ke`):
  - `/` goes to sign-in, Register isn't offered, and the page title is the company's name (not indexed by search engines);
  - only the company's own people (and sysadmins) can stay signed in there; anyone else is signed out with a message;
  - bill links in SMS, emails and PDFs use that address.
- **Settings › White label & data** (company admins; sysadmins use `?company=ID`):
  - edit the brand;
  - see row counts;
  - **Download all data**.

## The company's data

- **Download all data / `php artisan company:export {id}`:** one zip covering the whole history. It holds one CSV per kind of record, `manifest.json` (row counts) and `README.txt` (how the files link).
  - **Included:** properties, units, people, tenants, tenancies, bills and bill lines, payments, tenancy charges, leases, checklists, water readings, M-Pesa prompts, tenant wallets and their transactions, expenses, payees, repairs, meters, gate, SMS templates, payment methods, rent messages, paybill payments, bookings and unit management.
  - **Ownership** follows the app's rules: `company_id`, or the tenancy, unit or property for older rows.
  - **Never included:** passwords, tokens, key hashes, integration credentials, calendar links, and Scharge staff accounts.
- **Nightly copy:** Integrations › Nightly data copy, run at 02:30 by `company:backup-offsite`.
  - It sends the same zip to the company's own SFTP server or any S3-compatible bucket (Amazon S3, Google Cloud Storage with HMAC keys, Wasabi, Backblaze, DigitalOcean Spaces).
  - Copies older than "keep days" are deleted there.
  - **Test** writes and deletes a small file.
  - The last result shows on the White label & data page.
- **Read API** (Settings › API keys, `read` scope): `GET /api/v1/{collection}` for properties, units, people, tenants, tenancies, invoices, invoice_items, payments and water_readings.
  - **Paging:** `?after={id}&limit=1..500`, and `?updated_since=2026-11-01` for incremental sync. The response has `has_more` and `next_after`.
  - **Scoping:** each key sees only its company's records, with the same rules and secret filtering as the export.

The company keeps its own copy; Scharge keeps the master database, plus the deploy backups and nightly server backups.

## Setting up a company's own address (Scharge staff)

1. **The company's DNS:** a `CNAME` from `portal.theircompany.co.ke` to `scharge.sharet.africa`.
2. **On the server (CyberPanel):**
   - add the address to the scharge.sharet.africa website as an alias;
   - issue its SSL certificate (Let's Encrypt);
   - check `https://portal.theircompany.co.ke/up` returns 200.
3. **Session cookies:** `SESSION_DOMAIN` in `.env` must be empty or `null`, so sign-in cookies work on every address.
4. **Save the address:** Settings › White label & data `?company=ID` → enter the address, choose "Powered by scharge", save.
5. **Their paybill:** if the company has its own Daraja paybill, press **Test** on Integrations › M-Pesa Daraja once, so paybill payments are registered.

SMS go out under the company's own sender ID (Integrations › SMS sender) and WhatsApp from its own number, if set up. Both existed before white label.

## Pieces

| Piece | Where |
|---|---|
| Brand data | `company_brands` (`database/migrations/2026_11_11_000300_…`), `App\Models\CompanyBrand` |
| Which brand a page wears | `App\Support\Brand` (address first, then the signed-in user's company), `App\Http\Middleware\ApplyBrand` |
| Colour | `Brand::css()` recolours the `brand-*` utility classes (Tailwind v3 compiles them to fixed colours) |
| Settings page | `BrandController`, `resources/views/brand/edit.blade.php` |
| Export / nightly copy | `App\Services\Data\CompanyExport`, `App\Services\Data\CompanyBackup`, `company:export`, `company:backup-offsite` |
| Read API | `Api\V1Controller::collection` |
| Tests | `tests/Feature/WhiteLabelTest.php` |

**Packages:** `league/flysystem-sftp-v3` and `league/flysystem-aws-s3-v3` were added. `composer.json` pins platform PHP 8.3, because the live server runs 8.3.
