# Hosted company website and data agreement

*Added 2026-11-11 on `claude/rent-automation`. Decisions from the user (via the mobile-app session):*
- *site pages come from a Scharge template plus the company's own pages;*
- *the company's staff and Scharge both edit, and Scharge approves before anything goes live;*
- *the company gets a full export, not only a disaster-recovery copy;*
- *a data-processing agreement says the data is theirs.*

A white-label company (e.g. Danaff) gets its **main website and its tenant/owner portal on its own address**. Scharge hosts both; the company keeps backups (full export plus nightly copy, `docs/WHITE_LABEL.md`).

## The website

- **Its own address:** on `portal.danaff.co.ke` (any address in `company_brands.domain`):
  - `/` is the company's home page once approved; until then, sign-in;
  - `/{page}` serves its other live pages;
  - `/login` is the portal.
  - Paths the portal uses (`/login`, `/dashboard`, `/rent`, …) can never be page addresses (`WebsiteController::RESERVED`).
- **Template** (Settings › Website › Start from the template):
  - **Home:** heading, text, and the first 3 vacant units;
  - **About us**;
  - **Homes to rent:** all the company's vacant units, kept up to date automatically, each with a "Book a viewing" form;
  - **Contact:** a form whose messages land in Enquiries, with an SMS to the company's admins.
  - Custom pages can be added.
- **Look:** the brand's logo, colour and contact, a "Tenant & owner login" button, and dark mode. No Scharge marketing; "Powered by scharge" only if the brand shows it.
- **Text:** written in Markdown (headings, bold, lists, links). Raw HTML is escaped, so nobody can put scripts on the site.

## Editing and approval

1. **Company admins edit a draft:** Settings › Website, plus a banner picture. **Preview** shows the draft in the real design.
2. **Send to scharge for approval:** the status becomes "Waiting for scharge", or "Live · changes waiting" for a page that's already live. Visitors keep seeing the live version.
3. **Scharge staff review** on **Website approvals** (`/admin/website-approvals`): preview, then **Approve** (the draft goes live) or **Send back** with a note, which the company sees on the page.
   - Sysadmins see every company; account managers see the companies they own.
4. **Scharge staff can also edit** any company's site (`?company=ID`) and **Publish now**.

## Data-processing agreement

- **The text:** Settings › White label & data › "Read and accept the data-processing agreement" (`/settings/data-agreement`). It lives in `resources/legal/dpa.md`, version `CompanyAgreement::DPA_VERSION`.
- **What it says:** the data is the company's (data controller under Kenya's Data Protection Act, 2019); Scharge only processes it on instructions. It also covers confidentiality and security, the named sub-processors (Anthropic only while document reading is on), people's rights, breach notice within 48 hours, transfers, export and deletion when leaving (30 days, backups within 90), audits, and Kenyan law.
- **Acceptance:** the company's admin types their name and role and ticks to accept. Scharge staff can read it but can't accept it for the company. The name, role, user, time, IP and version are stored in `company_agreements`.
- **It is a draft:** have a Kenyan advocate review it, and bump the version (and the text) when the terms change; companies are then asked to accept again.

Website pages and agreement records are included in the company's data export.

## Pieces

| Piece | Where |
|---|---|
| Tables | `company_pages`, `company_agreements` (`database/migrations/2026_11_11_000500_…`) |
| Public site | `SiteController` (home via `ApplyBrand`, pages via the fallback route, `POST /site/contact`), `resources/views/site/page.blade.php` |
| Editor | `WebsiteController`, `resources/views/website/{index,edit}.blade.php` |
| Approvals | `WebsiteReviewController`, `resources/views/website/review.blade.php` |
| Agreement | `AgreementController`, `resources/legal/dpa.md`, `resources/views/brand/dpa.blade.php` |
| Tests | `tests/Feature/CompanyWebsiteTest.php` |

**Main domain too:** to serve `danaff.co.ke` as well as `portal.danaff.co.ke`, the domain field holds one address today. Point the main domain at the portal address with a redirect at the DNS or hosting level, or extend `company_brands` to take several addresses.
