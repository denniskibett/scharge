# Bookings and channel sync

*Added 2026-11-11 on `claude/rent-automation`.*

**Property › Bookings** (`/bookings`) is one calendar per short-stay unit. A short-stay unit is one whose stay type is BnB, short stay or mixed.

- **No double bookings.** A booking covers the nights from check-in up to the night before check-out, so a guest can arrive the morning another leaves. A booking on a night that's already taken is refused, with the clashing stay named. The unit row is locked while booking, so two people can't take the same night at once.
- **Who sees which units:** the company scope. A company sees its own units and the units it manages (`unit_managements`, e.g. a BnB host's units). BnB hosts get the page in their menu.
- **Cancelling:** direct bookings are cancelled here. Channel bookings are cancelled on the channel, and the next sync cancels them here.

## Channel sync (built, switched off)

Sync goes both ways through the calendar (iCal) links that Airbnb, Booking.com and Vrbo give every listing:

- **Import:** each unit can have a channel's *export calendar* link. `bookings:sync` (every 30 minutes) reads it:
  - stays become bookings with `source` set to the channel;
  - stays the channel no longer lists are cancelled here (future ones only);
  - a channel stay on nights already booked here is kept and flagged as a clash on the page.
- **Export:** each unit has a private link, `/calendar/units/{id}/{code}?for=airbnb`, to paste into the channel's *import calendar*.
  - It lists every booking except that channel's own, so the channel blocks those nights.
  - It carries dates only, never guest names or amounts.
  - The code is an HMAC of the unit id with `APP_KEY`.
- **Safety:** only `https` links on the channels' own domains are fetched (`ChannelSync::HOSTS`), and redirects aren't followed.

**Switch:** **Integrations › Channel sync** (platform, sysadmin only), off by default. While it's off:
- the Bookings page shows **Coming soon**;
- connecting a channel is refused;
- `bookings:sync` does nothing.

Switching it on starts sync for every unit that has a link. `php artisan bookings:sync --force` runs it once while it's off, for testing.

**What iCal can't do:** iCal links carry only dates, so guest names, prices and messages from Airbnb or Booking.com need their partner APIs (Airbnb's API is invite-only; Booking.com's Connectivity API needs certification). That would be a later step; the bookings table already has room for the data.

## Pieces

| Piece | Where |
|---|---|
| Tables | `bookings`, `unit_channels` (`database/migrations/2026_11_11_000200_…`) |
| Calendar rules | `App\Services\Bookings\BookingCalendar` |
| iCal read/write | `App\Services\Bookings\Ical` |
| Sync | `App\Services\Bookings\ChannelSync`, `php artisan bookings:sync` (scheduled every 30 minutes) |
| Page | `BookingController`, `resources/views/bookings/index.blade.php` |
| Tests | `tests/Feature/BookingsTest.php` |
