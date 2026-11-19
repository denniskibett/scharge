<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitChannel;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Channel sync with Airbnb, Booking.com and Vrbo through their calendar (iCal) links, both ways:
 *  - import: each unit's channel calendar is read every 30 minutes; its stays become bookings here (source = channel),
 *    and stays removed on the channel are cancelled here;
 *  - export: each unit has a private calendar link (exportUrl) to paste into the channel, listing every booking made
 *    anywhere else, so the channel blocks those nights. Together: no double bookings.
 *
 * OFF until scharge switches it on (Integrations › Channel sync, platform). Until then the Bookings page shows
 * "Coming soon" and nothing is fetched. iCal links only carry dates (no guest names or prices): that is what the
 * channels share without a partner API.
 */
class ChannelSync
{
    /** Only these hosts are fetched (a calendar link is a URL the user pastes). */
    public const HOSTS = ['airbnb' => ['airbnb.com', 'airbnb.co.ke', 'airbnb.co.uk'], 'booking_com' => ['booking.com'], 'vrbo' => ['vrbo.com', 'homeaway.com']];

    public static function enabled(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('company_integrations')
            && \App\Models\CompanyIntegration::withoutGlobalScope('company')->where('provider', 'channel_sync')->whereNull('company_id')->where('enabled', true)->exists();
    }

    public static function exportToken(int $unitId): string
    {
        return substr(hash_hmac('sha256', 'unit-calendar:'.$unitId, (string) config('app.key')), 0, 32);
    }

    /** The link to paste into a channel ("import calendar"): every booking except that channel's own. */
    public static function exportUrl(int $unitId, ?string $forChannel = null): string
    {
        return route('bookings.calendar', array_filter(['unit' => $unitId, 'token' => self::exportToken($unitId), 'for' => $forChannel]));
    }

    /** The unit's calendar for a channel: every confirmed booking that did not come from that channel. */
    public function export(Unit $unit, ?string $forChannel = null): string
    {
        $bookings = Booking::where('unit_id', $unit->id)->where('status', 'confirmed')
            ->when($forChannel, fn ($q) => $q->where('source', '!=', $forChannel))
            ->whereDate('check_out', '>=', now()->subDays(30)->toDateString())->orderBy('check_in')->get();

        return Ical::build('scharge · '.$unit->unit_number, $bookings->map(fn (Booking $b) => [
            'uid' => 'booking-'.$b->id.'@scharge', 'start' => $b->check_in->toDateString(), 'end' => $b->check_out->toDateString(),
            'summary' => 'Booked', // never guest details: the link may be seen by the channel
        ]));
    }

    public static function allowedUrl(string $channel, string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);

        return collect(self::HOSTS[$channel] ?? [])->contains(fn ($h) => $host === $h || str_ends_with($host, '.'.$h));
    }

    /** @return array{imported: int, cancelled: int, clashes: int} */
    public function sync(UnitChannel $channel): array
    {
        return CompanyAccess::bypass(function () use ($channel) {
            $url = (string) $channel->import_url;
            if (! self::allowedUrl($channel->channel, $url)) {
                $channel->update(['last_status' => 'failed', 'last_error' => 'Not a '.(UnitChannel::CHANNELS[$channel->channel] ?? 'channel').' calendar link.', 'last_synced_at' => now()]);

                return ['imported' => 0, 'cancelled' => 0, 'clashes' => 0];
            }
            try {
                $res = Http::timeout(20)->withOptions(['allow_redirects' => false])->get($url);
                if (! $res->successful() || ! str_contains($res->body(), 'BEGIN:VCALENDAR')) {
                    throw new \RuntimeException('The channel did not return a calendar (HTTP '.$res->status().').');
                }
            } catch (\Throwable $e) {
                $channel->update(['last_status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 255), 'last_synced_at' => now()]);

                return ['imported' => 0, 'cancelled' => 0, 'clashes' => 0];
            }

            return $this->apply($channel, Ical::parse($res->body()));
        });
    }

    /** @param array<int, array{uid: string, start: string, end: string, summary: string, cancelled: bool}> $events */
    public function apply(UnitChannel $channel, array $events): array
    {
        $today = now()->toDateString();
        $seen = [];
        $counts = ['imported' => 0, 'cancelled' => 0, 'clashes' => 0];
        $unit = Unit::withoutGlobalScope('company')->with('property')->findOrFail($channel->unit_id);
        $calendar = app(BookingCalendar::class);

        DB::transaction(function () use ($channel, $events, $today, &$seen, &$counts, $unit, $calendar) {
            foreach ($events as $ev) {
                if ($ev['end'] < $today || $ev['cancelled']) {
                    continue;
                }
                $seen[] = $ev['uid'];
                $booking = Booking::firstOrNew(['unit_id' => $unit->id, 'source' => $channel->channel, 'external_uid' => $ev['uid']]);
                $isNew = ! $booking->exists;
                $booking->fill(['company_id' => $unit->company_id ?? $unit->property?->company_id, 'check_in' => $ev['start'], 'check_out' => $ev['end'],
                    'status' => 'confirmed', 'guest_name' => $booking->guest_name ?: self::guestFrom($ev['summary'])]);
                if ($booking->isDirty() || $isNew) {
                    if ($calendar->clashes($unit->id, $ev['start'], $ev['end'], $booking->id)->isNotEmpty()) {
                        $counts['clashes']++; // the channel took nights already booked here: kept, and flagged on the page
                    }
                    $booking->save();
                    $counts['imported'] += $isNew ? 1 : 0;
                }
            }
            // Stays the channel no longer lists (cancelled there): cancel here, future ones only.
            $counts['cancelled'] = Booking::where('unit_id', $unit->id)->where('source', $channel->channel)->where('status', 'confirmed')
                ->whereDate('check_out', '>=', $today)->whereNotIn('external_uid', $seen ?: [''])->update(['status' => 'cancelled']);
        });

        $channel->update(['last_status' => 'ok', 'last_synced_at' => now(),
            'last_error' => $counts['clashes'] ? $counts['clashes'].' '.($counts['clashes'] === 1 ? 'stay overlaps' : 'stays overlap').' a booking made elsewhere: check the calendar.' : null]);

        return $counts;
    }

    /** Airbnb puts "Reserved" / "Airbnb (Not available)"; Booking.com "CLOSED - Not available". No real names: keep it blank. */
    private static function guestFrom(string $summary): ?string
    {
        return preg_match('/not available|reserved|closed|blocked|airbnb|booking/i', $summary) ? null : (mb_substr($summary, 0, 120) ?: null);
    }
}
