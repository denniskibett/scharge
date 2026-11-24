<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitChannel;
use App\Services\Bookings\BookingCalendar;
use App\Services\Bookings\ChannelSync;
use App\Support\CompanyAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bookings for short-stay units (BnB, serviced apartments): one calendar per unit, no double bookings, and channel
 * sync with Airbnb / Booking.com once it is switched on (ChannelSync). docs/BOOKINGS.md.
 * Units come through the company scope: a company's own, or ones it manages (a BnB host's units).
 */
class BookingController extends Controller
{
    private const STAY_TYPES = ['short_stay', 'bnb', 'mixed'];

    public function index(Request $request, BookingCalendar $calendar): View
    {
        $units = Unit::with('property')->whereIn('stay_type', self::STAY_TYPES)->orderBy('property_id')->orderBy('unit_number')->get();
        $unitId = $units->firstWhere('id', (int) $request->query('unit'))?->id;
        $bookings = Booking::with('unit.property')->whereIn('unit_id', $units->pluck('id')->all() ?: [0])
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->where('status', 'confirmed')->whereDate('check_out', '>=', now()->toDateString())->orderBy('check_in')->paginate(30)->withQueryString();

        // Availability: four weeks from the chosen day (today unless moved), one row per unit, free and taken nights.
        $today = now('Africa/Nairobi')->startOfDay();
        try {
            $start = $request->query('from') ? \Illuminate\Support\Carbon::parse((string) $request->query('from'), 'Africa/Nairobi')->startOfDay() : $today->copy();
        } catch (\Throwable) {
            $start = $today->copy();
        }
        $shown = $unitId ? $units->where('id', $unitId)->values() : $units;

        return view('bookings.index', [
            'calendarStart' => $start,
            'calendarDays' => collect(range(0, 27))->map(fn ($i) => $start->copy()->addDays($i)),
            'calendarUnits' => $shown,
            'nights' => $calendar->nights($shown->pluck('id')->all(), $start, $start->copy()->addDays(28)),
            'units' => $units,
            'unitId' => $unitId,
            'bookings' => $bookings,
            'channels' => UnitChannel::whereIn('unit_id', $units->pluck('id')->all() ?: [0])->get()->groupBy('unit_id'),
            'syncOn' => ChannelSync::enabled(),
            'tonight' => Booking::whereIn('unit_id', $units->pluck('id')->all() ?: [0])->where('status', 'confirmed')
                ->whereDate('check_in', '<=', now()->toDateString())->whereDate('check_out', '>', now()->toDateString())->count(),
        ]);
    }

    public function store(Request $request, BookingCalendar $calendar): RedirectResponse
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['check_out.after' => 'Check-out must be after check-in.']);
        $unit = $this->unit($data['unit_id']);
        $booking = $calendar->book($unit, collect($data)->except('unit_id')->all(), $request->user()->id);

        return redirect()->route('bookings.index', ['unit' => $unit->id])->with('success', sprintf('Booked %s, %s to %s (%d %s).',
            $unit->unit_number, $booking->check_in->format('j M'), $booking->check_out->format('j M'), $booking->nights, $booking->nights === 1 ? 'night' : 'nights'));
    }

    public function cancel(Request $request, Booking $booking, BookingCalendar $calendar): RedirectResponse
    {
        $this->unit($booking->unit_id);
        abort_unless($booking->source === 'direct', 422, 'Cancel channel bookings on the channel; the next sync removes them here.');
        $calendar->cancel($booking);

        return back()->with('success', 'Booking cancelled. The nights are free again.');
    }

    public function saveChannel(Request $request, Unit $unit, ChannelSync $sync): RedirectResponse
    {
        abort_unless(ChannelSync::enabled(), 422, 'Channel sync is coming soon.');
        $this->unit($unit->id);
        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys(UnitChannel::CHANNELS))],
            'import_url' => ['required', 'string', 'max:2000', function ($attr, $value, $fail) use ($request) {
                if (! ChannelSync::allowedUrl((string) $request->input('channel'), (string) $value)) {
                    $fail('Paste the calendar link from '.(UnitChannel::CHANNELS[$request->input('channel')] ?? 'the channel').' (it starts with https://).');
                }
            }],
        ]);
        $channel = UnitChannel::updateOrCreate(['unit_id' => $unit->id, 'channel' => $data['channel']],
            ['import_url' => $data['import_url'], 'company_id' => $unit->company_id ?? $unit->property?->company_id]);
        $r = $sync->sync($channel);

        return back()->with($channel->fresh()->last_status === 'ok' ? 'success' : 'error', $channel->fresh()->last_status === 'ok'
            ? sprintf('%s connected: %d %s imported.', UnitChannel::CHANNELS[$data['channel']], $r['imported'], $r['imported'] === 1 ? 'stay' : 'stays')
            : 'Saved, but the calendar could not be read: '.$channel->fresh()->last_error);
    }

    public function removeChannel(Request $request, UnitChannel $channel): RedirectResponse
    {
        $this->unit($channel->unit_id);
        $channel->delete();

        return back()->with('success', 'Channel disconnected. Its stays stay on the calendar until you cancel them.');
    }

    /** Public calendar link for a channel to import (private token; dates only). */
    public function calendar(Request $request, int $unit, string $token, ChannelSync $sync): Response
    {
        abort_unless(hash_equals(ChannelSync::exportToken($unit), preg_replace('/\.ics$/', '', $token)), 404);
        $model = CompanyAccess::bypass(fn () => Unit::findOrFail($unit));
        $for = array_key_exists((string) $request->query('for'), UnitChannel::CHANNELS) ? $request->query('for') : null;

        return response(CompanyAccess::bypass(fn () => $sync->export($model, $for)), 200, ['Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="unit-'.$unit.'.ics"', 'Cache-Control' => 'no-store']);
    }

    /** A short-stay unit the user may manage (company scope: their own or one they manage). */
    private function unit(int $id): Unit
    {
        return Unit::with('property')->whereIn('stay_type', self::STAY_TYPES)->findOrFail($id);
    }
}
