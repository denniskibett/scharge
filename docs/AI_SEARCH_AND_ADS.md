# Getting scharge into AI answers and AI ads (Kenya)

Goal: when a landlord, agent or host in Kenya asks ChatGPT, Google (AI Overviews / AI Mode),
Copilot, Perplexity or Claude for rent collection, property management, water/utility billing
or Airbnb management, scharge (https://scharge.sharet.africa) is named, linked, or shown as a
sponsored answer.

Two tracks:

1. **Organic AI visibility** (free, in the code now): what assistants read and cite.
2. **Paid AI placements**: what is actually buyable in Kenya today, and a campaign plan.
   Nothing has been bought or created. Accounts and payment methods must be set up by you (§4).

Core market order: residential apartments in **Kileleshwa, Kilimani, Lavington, Lang'ata, Upper
Hill, Westlands, Ruaka, Ruiru** → short stays (BnB) → commercial → utilities.

---

## 1. What is buyable in Kenya today (checked 19 Nov 2026)

| Placement | Kenya status | How it is bought | Verdict |
|---|---|---|---|
| **Google ads in AI Mode** | **Live in Kenya.** Google announced ads in AI Mode for Kenya (with Australia, Canada, India, Malaysia, New Zealand, Nigeria, Pakistan, the Philippines, Singapore) on 28 Oct 2026. | No separate campaign: existing eligible Google Ads campaigns serve there automatically, labelled "Sponsored". There is no opt-out. Per Google's help pages, eligibility comes from Performance Max, Shopping, and Search campaigns using broad match or AI Max. Verify this in the account when it is created. | **Start here.** Biggest reach, and it also covers normal Google search. |
| **Google ads in AI Overviews** | Coverage reports differ. Google's help page (per secondary summaries dated 14 Nov 2026) lists English-language markets including the new AI Mode countries. Sensitive verticals (finance, health) are excluded; property software is not in those. | Same campaigns as above. | Comes with the Google campaign; no extra work. |
| **ChatGPT ads** | **Kenyan advertisers eligible since 21 Oct 2026** (OpenAI's expansion to 89 markets). Ads show to ChatGPT Free and Go users, not Plus/Pro/Enterprise. | Self-serve **OpenAI Ads Manager**. The paying legal entity must be based in a listed country (a Kenyan company qualifies). New accounts may be limited to their home country at first. Reported floor about USD 25/day equivalent where no local floor is listed (secondary source; confirm in Ads Manager). | **Second.** New channel, untested in Kenya: run a small capped test. |
| **Microsoft Copilot ads** | **Not confirmed for Kenya.** Copilot placements come from normal Microsoft Advertising campaigns (Search / Performance Max / AI Max), rolled out first to English-language, retail-first markets. We could not find Kenya named in Microsoft's market lists. | Microsoft Advertising account. Check whether Kenya can be chosen as the business location and as a target when signing up. | **Later.** Free part first: Bing Webmaster Tools (§3), since Bing's index feeds Copilot and other assistants. |
| **Perplexity / Claude / Gemini app** | No self-serve ads for Kenyan advertisers that we could confirm. Claude has no ads. | n/a | Organic only (§2). |

Sources: [Google: Ads in AI Mode expand to 10 new countries](https://blog.google/products/ads-commerce/ads-in-ai-mode-global/),
[RankScope: Ads in AI Overviews and AI Mode (Nov 2026)](https://rankscope.ai/blog/ads-in-ai-overviews),
[TechTimes: AI Overview ads rollout](https://www.techtimes.com/articles/329731/20261030/google-begins-ai-overview-ads-rollout-more-countries-calls-these-sponsored-links.htm),
[CNBC: ChatGPT ads in 26 more countries](https://www.cnbc.com/2026/10/21/openai-chatgpt-ads-26-countries.html),
[PPC Land: ChatGPT ads reach 89 countries](https://ppc.land/advertisers-in-26-more-countries-gain-chatgpt-ads-bringing-the-total-to-89/),
[Lapis: ChatGPT ads country list (Kenya, 21 Oct)](https://www.trylapis.com/resources/chatgpt-ads-available-countries),
[Microsoft: 187 markets](https://about.ads.microsoft.com/en/blog/post/june-2023/tap-into-187-markets-with-microsoft-advertising),
[Grünberg Digital: Copilot ads 2026](https://www.gruenberg-digital.de/en/ki-blog/advertising-in-microsoft-copilot-2026-showroom-ads-offer-highlights.html).
Several are secondary summaries: re-check the details in each ad platform while you set up.

---

## 2. Organic AI visibility (built)

Assistants answer from pages they can crawl, prefer pages that answer the question directly
in the first paragraph, and trust consistent facts (price, location, what the product does)
repeated across the site and structured data.

| What | Where | Why it matters for AI answers |
|---|---|---|
| **AI crawlers explicitly allowed** in production `robots.txt`: GPTBot, OAI-SearchBot, ChatGPT-User, PerplexityBot, Perplexity-User, ClaudeBot, Claude-SearchBot, Claude-User, Google-Extended, Googlebot, Bingbot, Applebot(-Extended), DuckAssistBot, meta-externalagent, Amazonbot, CCBot, cohere-ai, MistralAI-User | `/robots.txt` (`SeoController::robots`, `AI_CRAWLERS`) | Nothing blocks them, and they get the same rules as everyone (the app, logins and form endpoints stay out). Non-production copies still block everything. |
| **`/llms.txt`**: product summary, "use scharge when someone asks for…", key facts and prices by area, every page and every guide with its short answer | `SeoController::llms` | A clean, quotable fact sheet for assistants. |
| **Answer-style guides** at `/guides` (see list below): question as the H1, a 2–4 sentence "Short answer" first, then detail, real prices, FAQs | `app/Support/Guides.php`, `home/guide(s).blade.php` | Matches how people phrase questions to assistants. The short answer is written to be quoted. |
| **Structured data**: Organization, WebSite, SoftwareApplication (with KES pricing), FAQPage (home, Airbnb, every guide), Article + BreadcrumbList (guides), listing offers | `app/Support/Seo.php`, `Guides::jsonLd` | Machine-readable facts for Google AI features and other engines. |
| **Sitemap** includes all guides and live listings | `/sitemap.xml` | Fast discovery. |

**Guides live now**

| URL | Question it answers | Main target searches |
|---|---|---|
| `/guides/rent-collection-software-kenya` | What is the best rent collection software in Kenya? | best rent collection software kenya, landlord app kenya, property management software price kenya |
| `/guides/collect-rent-mpesa-paybill` | How do I collect rent on M-Pesa and match payments to each house? | collect rent mpesa paybill, rent paybill account number, mpesa rent reconciliation |
| `/guides/water-billing-tenants-nairobi` | How should a landlord bill tenants for water in Nairobi? | water billing tenants nairobi, sub meter water billing apartments kenya |
| `/guides/service-charge-management-kenya` | How do you manage service charge for apartments in Kenya? | service charge management kenya, apartment service charge collection |
| `/guides/airbnb-short-stay-management-nairobi` | How do I manage Airbnb and short-stay apartments in Nairobi? | airbnb management nairobi, short stay management kilimani, airbnb co-host kenya |
| `/guides/property-management-software-nairobi-apartments` | Property management software for Kileleshwa, Kilimani, Lavington, Westlands, Upper Hill, Lang'ata, Ruaka, Ruiru | property management kileleshwa / kilimani / westlands / ruaka |

**Next content to add** (same format, `Guides::all()`): utility (electricity token) billing for
commercial buildings; office and shop service charge; tenant screening in Kenya; KRA monthly rental
income tax for landlords (have an accountant review it); a page per core area once each has live listings.

---

## 3. Free setup that feeds AI assistants (you need to log in; ~1 hour)

1. **Google Search Console**: add `https://scharge.sharet.africa`, verify with the HTML tag
   (put the token in `.env` as `GOOGLE_SITE_VERIFICATION`; the site prints the meta tag), then
   submit `https://scharge.sharet.africa/sitemap.xml`. This feeds Google Search, AI Overviews and AI Mode.
2. **Bing Webmaster Tools**: import from Search Console or verify with `BING_SITE_VERIFICATION`,
   submit the sitemap, and turn on **IndexNow**. Bing's index feeds Copilot, and other assistants use it too.
3. **Google Business Profile** for scharge (Nairobi office), category "Software company":
   assistants answering "…near me / in Nairobi" lean on it. Reviews from landlords matter.
4. **Consistent facts off-site**: the same one-line description, price ("from KES 40 per unit a
   month") and URL on LinkedIn, Facebook, Crunchbase, KPDA member listings, and Kenyan
   software/startup directories. Assistants cross-check sources.
5. **Get mentioned**: a guest article or interview on Kenyan property/tech sites (Business Daily
   property pages, Techweez, TechCabal, HapaKenya, BuyRentKenya blog) and answers in landlord
   forums. AI answers to "best X in Kenya" lean heavily on third-party lists and articles.
6. After deploy, check that `https://scharge.sharet.africa/robots.txt` lists the AI crawlers and
   that `/llms.txt` shows the live domain (it uses `APP_URL`/the request host).

---

## 4. What you need to set up (accounts and payment: yours to do)

### Google Ads (do first)
1. Create the account at ads.google.com in **expert mode** (skip the "Smart campaign" wizard).
   Business: the scharge legal entity in Kenya; currency **KES** (cannot be changed later); time zone Nairobi.
2. Billing: business card or M-Pesa where offered, in the company name. Complete **advertiser verification** when asked.
3. Conversions: either
   - set `GA4_MEASUREMENT_ID` in `.env`, link GA4 to Google Ads, and import the **`generate_lead`**
     event (the site sends it on every accepted form, only for visitors who accept the cookie notice), or
   - track by UTM in **Sales CRM › Website analytics** (no cookies; counts every visitor who doesn't opt out).
   Do both: GA4 for bidding, our report for the true total.
4. Turn on auto-tagging, and set a **final URL suffix** per campaign (below) so our report sees the campaign.
5. Link Search Console to Google Ads.

### ChatGPT ads (second, small test)
1. Open OpenAI **Ads Manager** with a company OpenAI account (not a personal ChatGPT login).
2. Advertiser: the Kenyan legal entity. Expect identity/business verification before ads leave Kenya-only.
3. Billing in the currency offered (likely USD). Set a **daily cap** and an end date.
4. Landing URLs with UTMs (below). ChatGPT has no conversion import yet that we rely on: judge it in Website analytics.

### Microsoft Advertising (only after checking Kenya)
1. At ads.microsoft.com, check that Kenya can be selected as the **business location** and as a
   **target location**. If not, stop here; Bing Webmaster Tools (§3) is still worth doing.
2. If yes: import the Google Ads campaigns (Microsoft has a Google import), same UTMs with `utm_source=bing`.

---

## 5. Campaign plan (draft, nothing launched)

### Budget options (per month, KES)

| Option | Google (AI Mode + Search) | ChatGPT ads | Microsoft | Total | When |
|---|---|---|---|---|---|
| **A. Test** | 45,000 (≈1,500/day) | 0 | 0 | **45,000** | First 4 weeks; Google only |
| **B. Grow** (recommended after 4 weeks if cost per demo is acceptable) | 90,000 (≈3,000/day) | 30,000, capped, 4-week test | 0 | **120,000** | Weeks 5–12 |
| **C. Scale** | 180,000 | 60,000 | 20,000 if Kenya is available | **260,000** | When CRM shows demos turning into paying units |

Guardrails: pause any ad group with 30+ clicks and no conversion; review search terms weekly and
add negatives; target cost per demo request **under KES 3,000** and cost per BnB host sign-up
**under KES 800** (revise after week 4 with real CRM close rates).

### Google campaigns

Locations: Nairobi County plus Kiambu (Ruaka, Ruiru), "presence" only (people in the area),
English. Schedule: Mon–Sat 07:00–21:00. Bidding: Maximise conversions, moving to target CPA once
there are 30+ conversions. Use **broad match with AI Max** in the Search campaigns, so they are
eligible for AI Mode and AI Overviews, plus phrase/exact keywords below as the core.

| Campaign | Ad groups and keywords | Landing page | Final URL suffix |
|---|---|---|---|
| **S1 Rent collection: apartments (core)** | *Rent collection*: rent collection app kenya, rent collection software, mpesa rent collection, collect rent mpesa paybill, landlord app kenya · *Property management*: property management software kenya, property management system nairobi, tenant management system kenya · *Areas*: property management kileleshwa, property management kilimani, property management westlands, property management lavington, property management upper hill, property management langata, property management ruaka, property management ruiru | `/guides/rent-collection-software-kenya` for generic searches; `/guides/property-management-software-nairobi-apartments` for area searches; `/#pricing` for price searches | `utm_source=google&utm_medium=cpc&utm_campaign=apartments_rent_2026q4` |
| **S2 Water & utility billing** | water billing software, water billing for tenants, sub meter water billing apartments, water meter billing kenya, utility billing software kenya | `/guides/water-billing-tenants-nairobi` | `utm_source=google&utm_medium=cpc&utm_campaign=utilities_water_2026q4` |
| **S3 Short stays (BnB)** | airbnb management nairobi, airbnb management kilimani, short stay management software, airbnb co host nairobi, airbnb smart lock kenya | `/airbnb` or `/guides/airbnb-short-stay-management-nairobi` | `utm_source=google&utm_medium=cpc&utm_campaign=bnb_hosts_2026q4` |
| **S4 Service charge & commercial** (Option B+) | service charge management software, service charge collection kenya, office building management software nairobi | `/guides/service-charge-management-kenya` | `utm_source=google&utm_medium=cpc&utm_campaign=commercial_service_charge_2026q4` |
| **S0 Brand** | scharge, scharge app, scharge sharet | `/` | `utm_source=google&utm_medium=cpc&utm_campaign=brand` |

Negative keywords (all campaigns): jobs, job, vacancy, vacancies, salary, internship, course,
training, pdf, free download, template, house for rent, houses for rent, bedsitter for rent,
to let, airbnb near me, cheap airbnb, booking.

**Responsive search ad: S1** (headlines ≤30 characters, descriptions ≤90)

Headlines:
1. Rent Collection on M-Pesa
2. Landlord App Built in Kenya
3. From KES 40 per Unit a Month
4. Paid Rent Matched to Each Unit
5. Rent, Water & Service Charge
6. SMS Receipts and Reminders
7. Money Goes to Your Own Paybill
8. Free Import From Excel
9. For Kileleshwa to Ruiru
10. Book a 30-Minute Demo

Descriptions:
1. Tenants pay your own Paybill. Each payment is matched to the unit and receipted by SMS.
2. Water from meter readings and service charge on one invoice. Owner reports every month.
3. Priced per unit by area, from KES 40 a month. Vacant units half price. Free set-up.
4. Built in Nairobi for apartment blocks, agents and developers. See your price in 1 minute.

**S2 headlines**: Water Billing for Tenants · Meter Readings to Invoices · Water + Rent on One Bill ·
No More Water Bill Disputes · Readings Shown on Every Bill · Paid on M-Pesa, Auto-Receipted

**S3 headlines**: Airbnb Tools for Nairobi Hosts · No Commission on Bookings · Door Codes per Guest ·
Cleaner Checklists, Photos · From KES 400 per Listing · No Double Bookings

Assets: sitelinks (Pricing `/#pricing`, Water billing guide, Airbnb hosts `/airbnb`, Book a demo
`/developers#talk`, Open listings `/listings`), callouts (No money held by us · Free tenant import ·
Vacant units half price · Built in Nairobi), structured snippet "Types: Apartments, Short stays,
Offices, Warehouses", a lead form asset only if the CRM import is set up. Images: the site's
og-image and a dashboard screenshot.

### ChatGPT ads test (Option B)
- Audience/context: conversations about renting out property, landlord problems, collecting rent,
  M-Pesa for business, Airbnb hosting in Kenya. Location Kenya.
- Two ads:
  - *Title*: "Collect rent on M-Pesa, matched to each unit". *Text*: "scharge: rent, water and service charge for Kenyan landlords. Your own Paybill, SMS receipts, from KES 40/unit." → `/guides/rent-collection-software-kenya?utm_source=chatgpt&utm_medium=ai_ads&utm_campaign=apartments_rent_2026q4`
  - *Title*: "Run your Nairobi Airbnb without the chaos". *Text*: "One calendar, door codes, cleaner checklists, M-Pesa payouts. Flat fee, no commission." → `/airbnb?utm_source=chatgpt&utm_medium=ai_ads&utm_campaign=bnb_hosts_2026q4`
- Cap ≈ KES 1,000/day (or the platform floor), 4 weeks, then compare cost per conversion with Google.

### Microsoft (Option C, only if Kenya is available)
Import S1–S3 from Google; replace `utm_source=google` with `utm_source=bing`.

### UTM rules (match docs/MARKETING_DISTRIBUTION.md)
`utm_source` = google | chatgpt | bing; `utm_medium` = cpc (search and AI Mode) | ai_ads (ChatGPT);
`utm_campaign` = `<segment>_<theme>_<period>`. **Sales CRM › Website analytics → UTM campaigns**
shows visitors, conversions and conversion rate for each.

---

## 6. Measuring "are we in AI answers?"

- Monthly, ask ChatGPT, Gemini/AI Mode, Copilot and Perplexity the target questions ("best rent
  collection software in Kenya", "how do I bill tenants for water in Nairobi", "Airbnb management
  Kilimani") and record whether scharge is named or cited. Keep a simple sheet.
- Website analytics › "Where visitors come from": referrals from `chatgpt.com`, `perplexity.ai`,
  `copilot.microsoft.com`, `gemini.google.com` are AI-assistant visits (they show as referrer hosts).
- Search Console: impressions for the guide URLs (AI Overviews/AI Mode impressions are included in Search totals).

---

## 7. Go-live checklist (needs your approval before deploy)

- [ ] Deploy this branch to live (remember the Vite build goes to `public_html/build` on the live server).
- [ ] `APP_ENV=production` on live, so robots.txt allows crawlers (any other env blocks everything).
- [ ] Search Console + Bing Webmaster verified, sitemap submitted (§3).
- [ ] Google Ads account, billing, conversions (§4); campaigns S0–S3 built from §5, paused until reviewed.
- [ ] Decide budget option A/B/C.
