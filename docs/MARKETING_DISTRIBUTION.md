# scharge: digital marketing & distribution plan (Kenya)

Who we sell to, where they are, what we say, and how every channel is measured in
**Sales CRM › Website analytics** (`/admin/website-analytics`). Read with the CRM
(`app/Modules/CRM`): every form on the website becomes a lead there, and the analytics
report shows which channel and campaign brought it.

---

## 1. Who we are selling to

| Segment | Pain we solve | Main offer | Landing page |
|---|---|---|---|
| Small landlords (5–60 units: bedsitters, 1–2 BR blocks in Nairobi, Kiambu, Machakos, Nakuru, Kisumu, Mombasa) | Chasing rent on WhatsApp, M-Pesa statements that don't match units, water bills by hand | Rent + water billing on M-Pesa, from KES 40/unit | `/` (`#pricing`) |
| Property managers & agencies (100–5,000 units) | Owner reports, service charge, caretakers, many Paybills | Multi-property, owner payouts, staff roles | `/` + book a demo |
| Developers & landowners (off-plan, plots on instalments) | Buyer instalments, handover, service charge set-up | Instalments + handover | `/developers` |
| Airbnb / short-stay hosts (Kilimani, Westlands, Kileleshwa, Diani, Nanyuki) | Double bookings, keys, cleaners, payouts | Flat fee per listing, no commission | `/airbnb`, `/host/register` |
| Tenants & guests looking for a house | Fake listings, viewing fees, hidden costs | Real vacancies, every cost shown | `/listings`, `/listings/{slug}` |

Tenants are not paying customers, but every vacancy they view is a reason for a landlord to
list with us, and listing pages are our cheapest SEO traffic.

---

## 2. UTM conventions (what the analytics report reads)

The report groups traffic by `utm_source`, `utm_medium` and `utm_campaign`. Anything
untagged shows the referring site (e.g. `facebook.com`, `google.com`) or `direct`.
A conversion (any accepted website form) is credited to the visitor's **first source in that
browser session**, and a tagged campaign link replaces an earlier untagged visit.

**Rules**

1. Lowercase, words joined with `_`. No spaces, no names, no phone numbers or emails
   (the tracker drops values that look like contact details).
2. `utm_source` = the place: `google`, `facebook`, `instagram`, `tiktok`, `linkedin`,
   `whatsapp`, `sms`, `email`, `buyrentkenya`, `property24`, `jiji`, `gbp` (Google Business
   Profile), `kpda`, `partner_<name>` (e.g. `partner_stima_sacco`), `event_<name>`.
3. `utm_medium` = the type: `cpc` (search ads), `paid_social`, `social` (organic posts),
   `group` (Facebook/WhatsApp groups), `broadcast` (WhatsApp/SMS broadcast), `email`,
   `sms`, `listing` (property portals), `referral` (partners), `print` (QR on flyers/signs),
   `profile` (Google Business Profile, bios).
4. `utm_campaign` = `<audience>_<offer or theme>_<yyyy><qN or mon>`, e.g.
   `landlords_nairobi_2026q4`, `hosts_diani_dec2026`, `developers_handover_2026q4`,
   `agents_demo_nov2026`, `tenants_kileleshwa_vacancies`.
5. Optional `utm_content` for the creative/variant (not reported yet, but keep it consistent:
   `video_rent_chase`, `carousel_pricing`).

**Examples**

```
https://scharge.com/?utm_source=facebook&utm_medium=paid_social&utm_campaign=landlords_nairobi_2026q4
https://scharge.com/airbnb?utm_source=whatsapp&utm_medium=group&utm_campaign=hosts_kilimani_nov2026
https://scharge.com/developers?utm_source=linkedin&utm_medium=social&utm_campaign=developers_handover_2026q4
https://scharge.com/?utm_source=sms&utm_medium=sms&utm_campaign=landlords_reactivation_nov2026
https://scharge.com/listings?utm_source=buyrentkenya&utm_medium=listing&utm_campaign=tenants_kileleshwa_vacancies
https://scharge.com/?utm_source=partner_stima_sacco&utm_medium=referral&utm_campaign=sacco_landlords_2026q4
https://scharge.com/?utm_source=gbp&utm_medium=profile&utm_campaign=always_on
```

Keep a shared sheet of every live link (channel, campaign, owner, start date, spend). Use a
short link (Bitly or our own domain) for SMS and print so the UTM tags survive, and a QR code
for flyers, yard signs and the back of receipts.

**What counts as a conversion** (Website analytics › Form conversions): Book a demo,
country waitlist, sandbox API keys, listing viewing/booking request, listing SMS alert,
BnB host sign-up. Phone, WhatsApp and email link clicks are counted separately.

---

## 3. Channels and where to distribute

### 3.1 Search (always on, highest intent)

**Google Business Profile** (`utm_source=gbp&utm_medium=profile`)
- Category "Software company", secondary "Property management company"; service area Nairobi
  + the counties we sell in. Products: "Rent collection on M-Pesa", "Water billing",
  "Airbnb host tools". Weekly post (a feature, a landlord tip, a vacancy round-up).
- Ask every onboarded landlord for a review in the welcome SMS (week 2) with the review link.

**SEO (built into the site)**: per-page titles/descriptions, canonical URLs, Open Graph cards
for WhatsApp/Facebook sharing, `sitemap.xml` (static pages + every live listing),
`robots.txt`, schema.org (Organization, SoftwareApplication, FAQPage, listing offers),
`llms.txt`. Next steps:
- Submit `https://scharge.com/sitemap.xml` in Google Search Console and Bing Webmaster Tools
  (verification tokens: `GOOGLE_SITE_VERIFICATION`, `BING_SITE_VERIFICATION` in `.env`).
- Content that ranks for Kenyan searches (one article or guide a week, see §5):
  "how to collect rent on M-Pesa Paybill", "water billing for landlords in Kenya",
  "service charge in Kenya explained", "tenancy agreement template Kenya",
  "Airbnb rules Kenya 2026 / KRA tax for Airbnb hosts", "how to evict a tenant legally in Kenya".
- Area pages for listings ("houses to rent in Kileleshwa", "bedsitters in Rongai") once each
  area has 5+ live listings.

**Google Ads** (`utm_source=google&utm_medium=cpc`). Start KES 1,500–3,000/day, Nairobi +
Kiambu + Mombasa, phrase/exact match, conversions = website forms (import GA4
`generate_lead` if GA4 is switched on, otherwise judge from the analytics report by campaign).

| Ad group | Keywords | Landing |
|---|---|---|
| Rent collection | "rent collection app kenya", "m-pesa rent collection", "rent collection software", "paybill for rent", "landlord app kenya" | `/` |
| Property management | "property management software kenya", "property management system nairobi", "tenant management system", "real estate management software kenya" | `/` |
| Water billing | "water billing software", "water meter billing kenya", "sub-meter water billing apartments" | `/` |
| Service charge | "service charge management software", "apartment service charge collection" | `/developers` |
| Airbnb hosts | "airbnb management kenya", "airbnb co-host nairobi", "short stay management software", "smart lock airbnb kenya" | `/airbnb` |
| Developers | "off plan instalment management", "real estate developer software kenya" | `/developers` |

Negative keywords: jobs, vacancy (job sense), course, free download, pdf, salary, internship,
"house for rent" (unless running the listings campaign).

### 3.2 Property portals and classifieds (`utm_medium=listing`)

- **BuyRentKenya**, **Property24 Kenya**, **Jiji.co.ke**, **PigiaMe**, **Kenya Property Centre**,
  Facebook Marketplace (Property rentals).
- Post our landlords' vacancies (with their OK) with the "Book a viewing on scharge" link
  pointing to the listing's own page (`/listings/{slug}`). Every viewing request is a CRM lead and
  shows the landlord the value of listing with us.
- Agency/landlord accounts on these portals are also our prospect list: a landlord with 10+
  live ads is a sales lead (add to CRM prospects, tag `source=portal`).

### 3.3 Social

| Network | Audience | What to post | Cadence |
|---|---|---|---|
| Facebook | Landlords 35–65, caretakers, tenants | Short videos ("chasing rent on WhatsApp vs scharge"), pricing carousel, vacancy round-ups by area; paid: lead ads → `/` (`paid_social`) | 3 posts/week + always-on ads |
| Facebook groups | "Landlords Association Kenya"-type groups, estate/area groups (Kileleshwa, Ruaka, Syokimau, Rongai residents), "Airbnb Hosts Kenya" | Helpful answers first, link only when asked; vacancy posts in area groups | Daily, by the account managers |
| WhatsApp | Landlords, caretakers, hosts | Channel "scharge for landlords" (weekly tip); status updates; broadcast lists from CRM (opt-in only, `broadcast`) | Weekly |
| TikTok / Instagram Reels | Younger landlords, Airbnb hosts, tenants | 20–40s skits: the rent chase, the water bill argument, the double booking; house tours of live vacancies | 3/week |
| LinkedIn | Developers, property managers, banks/SACCOs | Case studies, handover story, founder posts, product updates | 2/week |
| X (Twitter) | Tech/press, Nairobi renters | Product news, vacancy threads | As news happens |
| YouTube | Search for "how to" | Screen-recorded tutorials (set up Paybill, read water meters) — also embedded in help docs | 2/month |

### 3.4 Associations, events and partnerships (`utm_medium=referral`, `event_*`)

- **KPDA** (Kenya Property Developers Association): member offer, sponsor a breakfast,
  speak on handover and service charge.
- **Real-estate & landlord bodies**: Kenya Landlords Association, Estate Agents Registration
  Board (EARB) registered agents list, Institution of Surveyors of Kenya (ISK) property
  managers, residents' associations in managed estates.
- **Airbnb host communities**: Airbnb Hosts Kenya groups, Nairobi short-stay meetups; offer a
  "first listing free for 3 months" code.
- **Banks & SACCOs**: landlords borrow to build. Partner with SACCOs (Stima, Mwalimu, Kenya
  Police, Unaitas) and bank SME/mortgage desks (KCB, Co-op, Equity, Family): co-branded
  landing link `partner_<name>`, rent-collection data that supports a loan, revenue share.
- **Developers & contractors**: every completed block is a handover; pitch `/developers`
  before practical completion.
- **Hardware stores, plumbers, water-meter suppliers**: flyers with QR (`print`) for
  "water billing on M-Pesa".
- **Events**: Nairobi Real Estate Expo / Homes Expo, Kenya Homes Expo, Africa Property
  Investment events, county landlord forums — QR codes per event (`event_<name>`).

### 3.5 Owned channels

- Email newsletter (monthly) to waitlist, demo requests and customers.
- In-product: tenant receipts and SMS footers ("Powered by scharge — scharge.com"), listing
  pages shared by landlords on WhatsApp (Open Graph cards make these previews look right).
- Referral programme: landlord gets one free month per referred landlord who goes live
  (tag `utm_source=referral&utm_campaign=landlord_referral`).

---

## 4. Outreach with the CRM (cold email / SMS)

The CRM (`app/Modules/CRM`) holds prospect companies (Kenya-wide prospect lists), contacts,
follow-ups and the sales tracker with account-manager commission.

**Flow**

1. **Prospect lists** → companies with `lifecycle = prospect`, owned by an account manager
   (`owner_id`). Sources: portal advertisers, KPDA/EARB directories, Google Maps (property
   managers by area), events, partner referrals.
2. **Sequence** (account manager, logged as CRM follow-ups):
   - Day 0: SMS or email, personal, one line + one link with UTM
     (`utm_source=sms|email&utm_medium=sms|email&utm_campaign=outbound_<segment>_<month>`).
   - Day 2: call; Day 5: WhatsApp with a 40-second video; Day 10: last email with a case study.
3. **Website** → when the prospect books a demo, joins the waitlist or registers as a host,
   the lead lands in Sales CRM › Requests/Waitlist, and Website analytics shows the source
   and campaign of that conversion. Compare campaigns by **conversion rate** (conversions ÷
   visitors) in the UTM campaigns table.
4. **Close** → company moves to customer; commission is tracked in the sales tracker.

**Templates** (keep under 160 characters for SMS; always name the sender and give an opt-out)

> SMS: "Hi {first name}, {AM name} from scharge. We help Nairobi landlords collect rent and
> water on M-Pesa, matched to each unit. 2-min look: scharge.com/s/ll1 Reply STOP to opt out"

> Email subject: "Rent and water for {property name}, collected on M-Pesa"
> Body: 3 short lines (problem, what we do, price from KES 40/unit), one link with UTM,
> a reply-to that reaches the account manager, physical address, unsubscribe line.

**Compliance (Kenya Data Protection Act, 2019 and the Kenya Information and Communications
(Consumer Protection) Regulations)**
- Only contact business numbers/emails that are publicly listed for the business, or people
  who gave consent; record the source on the CRM contact.
- Every SMS/email: who we are + opt-out (STOP). Honour opt-outs within 24 hours in the CRM.
- SMS sending hours 8:00–19:00 EAT; no promotional SMS on Sundays.
- Never put a phone number, email or name in a UTM tag or URL.

---

## 5. Content calendar (first 12 weeks)

| Week | Theme | Blog / guide (SEO) | Social | Outreach / partner |
|---|---|---|---|---|
| 1 | Launch the rent-collection story | "How to collect rent on M-Pesa Paybill without chasing tenants" | Video: the rent chase | GBP set-up; Search Console + sitemap |
| 2 | Water billing | "Water billing for apartments in Kenya: meters, rates, disputes" | Carousel: a water bill done right | Plumbers / meter suppliers flyers (QR) |
| 3 | Pricing transparency | "What property management software costs in Kenya" | Pricing calculator reel | Google Ads: rent collection group live |
| 4 | Case study #1 | Landlord story (10–40 units) | Testimonial video | Outbound SMS: Nairobi small landlords |
| 5 | Airbnb hosts | "Airbnb in Kenya: KRA tax, county permits and house rules" | Double-booking skit | Airbnb host groups; host referral code |
| 6 | Tenants & vacancies | "Houses to rent in Kileleshwa: what you really pay" | Vacancy round-up by area | Post vacancies on BuyRentKenya / Jiji |
| 7 | Developers | "Handover checklist for developers in Kenya" | LinkedIn: handover story | KPDA introduction; developer outreach |
| 8 | Service charge | "Service charge in Kenya explained for owners and tenants" | Explainer reel | Residents' associations |
| 9 | Money safety | "Why deposits should never go to a personal number" | Scam-awareness post | SACCO partnership pitch |
| 10 | Case study #2 | Property manager (100+ units) | LinkedIn post + PDF | Agency outbound email |
| 11 | Year-end | "Landlord year-end checklist: KRA rental income tax (MRI)" | Tax reminder posts | Bank SME desk co-webinar |
| 12 | Review | Update top guides with new data | Best-of month | Review UTM report; cut losing channels |

Every piece of content links with UTM tags so its traffic and conversions appear in the
report. Review every Monday: top campaigns by conversions, top pages by exits (heatmap the
weak ones), and listing views versus viewing requests.

---

## 6. Measurement

- **Website analytics** (first-party, no cookies): visitors, page views, time and scroll,
  sources, UTM campaigns, conversions by form and by source, listing views, contact clicks,
  click heatmap. Visitors with Do-Not-Track / Global Privacy Control are not recorded;
  signed-in users are not counted.
- **Optional ad tags**: set `GA4_MEASUREMENT_ID` and/or `META_PIXEL_ID` in `.env` to measure
  paid campaigns inside Google Ads / Meta Ads. They stay off until set, load only after the
  visitor accepts the cookie notice, and receive `generate_lead`/`Lead` on form conversions and
  `view_item`/`ViewContent` when a listing is opened.
- **Weekly KPIs**: website visitors; conversion rate; demos booked; host sign-ups; listing
  viewing requests; cost per lead by channel (spend sheet ÷ conversions); demo → customer rate
  (CRM); units live.
- **Targets for the first quarter** (adjust after week 4): 30 demo requests/month, 15 new
  landlords live/month, 40 BnB host sign-ups, cost per lead under KES 1,500 on paid channels.
