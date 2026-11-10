# Onboarding: enquiries, matching and KYC

*Added 2026-11-11 on `claude/rent-automation`.*

## Listing enquiries reach the right people

A renter's "Book a viewing" or "Book a stay" request on Open listings now carries the listing's address (`listing_slug`). `App\Services\Listings\EnquiryRouter` decides where it goes:

- **The building belongs to a Scharge customer:**
  - the request is that landlord's: a CRM follow-up with their `company_id`;
  - their admins and property managers get an SMS at once (name, phone, listing, preferred day);
  - the landlord works it on **Property › Enquiries** (`/enquiries`): New → Contacted → Viewing booked → Let / Not interested, with notes and a one-tap WhatsApp link.
- **The building isn't managed on Scharge yet** (a host-registered or unclaimed building, or a prospect company):
  - the request becomes an **onboarding lead** for Scharge sales (CRM › Requests, titled "Onboarding lead · …");
  - sales calls the owner with real renter demand in hand, which is the fastest way to onboard them.

## Match alerts

"SMS me new matches" saved a search but nothing ever matched it. `php artisan listings:match` now runs hourly from 09:00 to 19:00:
- each renter gets new vacant units that fit their kind, area and maximum rent;
- at most 3 listings per SMS and one SMS a day;
- never the same listing twice;
- alerts close after 60 days.

**Not built:** the "Reply STOP" message has no inbound handler yet. Close an alert in the CRM.

## KYC for businesses and property owners

**Settings › Verify your business** (`/settings/verify`): company admins, or Scharge staff with `?company=ID` while sitting with a new landlord.

1. **Upload** photos (the phone camera opens directly) or PDFs: the owner's or a director's national ID or passport, the KRA PIN certificate, the business registration or CR12, and a title deed per property.
2. **Reading:** each document is read automatically (below), and the empty fields fill themselves: owner name, ID number, KRA PIN, business name, registration number.
3. **Automatic checks** (`App\Services\Kyc\KycChecks`):
   - missing ID or KRA certificate;
   - KRA PIN format (`A012345678Z`);
   - typed PIN or ID number not matching the certificate or card;
   - the same person on the ID and the KRA certificate (word match, order and case ignored);
   - the business name against its certificate;
   - title deeds in someone else's name ("ask for an authority to manage it");
   - expired passports, and unreadable or odd-looking documents.

   Red items block sending; amber items are for the reviewer.
4. **Send for review:** Scharge staff see **KYC reviews** (`/admin/kyc`): each document with what was read, plus the checks, side by side. They choose **Verify** or **Send back** with a note.
   - Sysadmins see every company; account managers see the companies they own.
5. **Verified:** the landlord's listings get the **Verified** badge on Open listings.

Documents are stored on the private disk (`storage/app/private/kyc/{company}`). They're served only to that company's admins and Scharge staff, are never public, and are left out of the data export.

## Document reading (OCR)

`App\Services\Kyc\DocumentReader` sends the photo or PDF to **Claude Opus 5.5** through the official Anthropic PHP SDK (`anthropic-ai/sdk`):
- **Output:** structured JSON with one schema per kind of document, plus `legible`, `looks_like` and `notes`. Fields not on the document stay blank and are never guessed.
- **Fallback:** the server-side fallback is on (`fallbacks: "default"`), so a declined request is retried on Anthropic's fallback model.
- **Timing:** reading runs right after the upload's response, so the page returns at once and shows "Reading…".

**Switch:** **Integrations › Document reading (KYC)** (platform, sysadmin only), with an Anthropic API key. `ANTHROPIC_API_KEY` in `.env` also works.
- **While it's off:** uploads are kept, and owners type the details.
- **Test:** sends a one-line request to check the key.

**Data protection:** while it's on, uploaded KYC documents are sent to Anthropic's API to be read. Mention this in the privacy notice under Kenya's Data Protection Act.

## Pieces

| Piece | Where |
|---|---|
| Enquiry routing, landlord SMS | `App\Services\Listings\EnquiryRouter`, `LeadService::requestListing` |
| Landlord enquiries page | `EnquiryController`, `resources/views/enquiries/index.blade.php` |
| Match alerts | `App\Services\Listings\ListingMatcher`, `php artisan listings:match` |
| KYC | `company_kyc`, `kyc_documents` (`database/migrations/2026_11_11_000400_…`), `KycService`, `KycChecks`, `DocumentReader`, `KycController`, `KycReviewController`, `resources/views/kyc/*` |
| Tests | `tests/Feature/OnboardingTest.php` (document reading is stood in; the live call was checked to reach Anthropic) |
