# Sign-in locations

*Added 2026-11-11.*

**Admin › Sign-in locations** (`/admin/sign-ins`), for Scharge staff, shows where people sign in from. Sysadmins see everyone; account managers see the companies they own.

## What it shows

Filter by period, channel and company:
- **Totals:** sign-ins, people, countries, failed attempts, and sign-ins with no known location.
- **Top lists:** countries, cities (with region), companies (sign-ins, people, and how many cities their people sign in from), and failed attempts by country (number of attempts and distinct IP addresses).
- **The latest 50:** who, role and company, city and IP, channel and method, device and browser, and whether it worked.

## What is recorded

`login_events`, one row per attempt, kept for 180 days (pruned daily at 04:30):

| Channel | When |
|---|---|
| **Website** | Laravel's `Login` and `Failed` events (password, remember-me) |
| **Mobile app** | `Api\Mobile\AuthController::signIn` (SMS code or password), plus wrong passwords |
| **Reader app** | `Api\MetersController::token`, plus wrong passwords |

Each row stores the user, company, role, success, IP, country, region, city, device and browser. Recording never blocks a sign-in.

## Where the location comes from

- **Offline lookup:** `App\Services\Analytics\GeoIp` reads DB-IP's free **IP to City Lite** database (CC BY 4.0; the attribution is on the page) from `storage/app/geo/dbip-city-lite.mmdb`. IP addresses never leave the server.
- **Refresh:** `php artisan geo:update` downloads the current month's file, validates it and swaps it in.
  - It runs monthly on the 3rd at 04:00.
  - `scripts/deploy.sh` runs it once if the file is missing.
  - If a download fails, the old file is kept.
- **Unknown locations:** private and local addresses have none. Without the file, only a CDN country header (Cloudflare's `CF-IPCountry`) is used, if present.
- **Accuracy:** city-level and approximate. Mobile networks often show the operator's city (often Nairobi), not the person's.

## Privacy

IP addresses and locations are personal data. They're kept for 180 days for security and service analytics, are visible only to Scharge staff, and aren't part of the company export. Mention sign-in logging in the privacy notice.
