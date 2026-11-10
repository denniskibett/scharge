# Renter demand heatmaps

*Added 2026-11-11.*

**What it shows:** where people look for homes on Scharge's Open listings and at what budget, including **demand nobody serves**: searches where no listing matched.

**Who sees it:**
- **Free for every landlord:** Property › **Renter demand** (`/demand`). It shows market-wide, anonymous figures, marks the landlord's own areas with ●, and nudges them to list vacant units that have no rent amount.
- **Scharge staff:** the same page at `/admin/demand`.

## What is collected (`listing_searches`)

| Signal | When | Weight |
|---|---|---|
| Search | The listings page sends kind, area, maximum price and the number of matches 1.5 s after the visitor stops changing filters. The same search by the same visitor within 30 minutes counts once. | 1 |
| SMS alert | "SMS me new matches" | 3 |
| Viewing request | "Book a viewing" (area and price of that listing) | 5 |

- **Anonymous:** no IP or phone is stored. Each row has a daily-changing visitor code and the searcher's approximate city, from the offline IP database (`docs/SIGN_IN_LOCATIONS.md`).
- **Opt-outs respected:** Do-Not-Track and Global Privacy Control, and bots are skipped, as in Website analytics.

## The page

- **Totals:** searches, alerts, viewing requests, people, and the share of searches with no match.
- **Heatmap:** area against budget band (KES a month, or a night for short stays), with darker cells for more demand.
- **Looked for, nothing found:** areas with searches that matched no listing.
- **Demand against homes on offer:** the most demand per listing first, which is where a new listing gets most attention.
- **Where searchers are:** by city.

## Pieces

`App\Services\Listings\ListingDemand` (recording, bands, weights), `DemandReport`, `DemandController`, `resources/views/demand/index.blade.php`, the beacon in `public/assets/js/main.js` (`TrackController` type `search`).
