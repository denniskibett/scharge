<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One calendar per short-stay unit: every booking (direct or from a channel) in one place, and never two stays on the
 * same night. A booking covers the nights from check-in up to the day before check-out, so one guest can leave the
 * morning the next arrives.
 */
class BookingCalendar
{
    /** Confirmed bookings that share a night with check_in..check_out (excluding one booking, when editing). */
    public function clashes(int $unitId, string $checkIn, string $checkOut, ?int $exceptId = null)
    {
        return Booking::where('unit_id', $unitId)->where('status', 'confirmed')
            ->whereDate('check_in', '<', $checkOut)->whereDate('check_out', '>', $checkIn)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->orderBy('check_in')->get();
    }

    /**
     * The nights taken in [from, to) for each unit, with the booking that takes them: the availability calendar on
     * the Bookings page and (as dates only) on the public listing.
     *
     * @param  array<int>  $unitIds
     * @return array<int, array<string, Booking>>  [unit id => ['2026-11-26' => Booking]]
     */
    public function nights(array $unitIds, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $out = [];
        if (! $unitIds || ! \Illuminate\Support\Facades\Schema::hasTable('bookings')) {
            return $out;
        }
        $bookings = Booking::withoutGlobalScopes()->whereIn('unit_id', $unitIds)->where('status', 'confirmed')
            ->whereDate('check_in', '<', $to->toDateString())->whereDate('check_out', '>', $from->toDateString())->orderBy('check_in')->get();
        foreach ($bookings as $b) {
            for ($d = $b->check_in->copy()->max($from->copy()->startOfDay()); $d->lt($b->check_out) && $d->lt($to); $d->addDay()) {
                $out[$b->unit_id][$d->toDateString()] = $b;
            }
        }

        return $out;
    }

    /** A direct booking. Refused when the nights are taken. */
    public function book(Unit $unit, array $data, ?int $userId): Booking
    {
        return DB::transaction(function () use ($unit, $data, $userId) {
            Unit::whereKey($unit->id)->lockForUpdate()->first(); // one booking at a time per unit
            $clash = $this->clashes($unit->id, $data['check_in'], $data['check_out'])->first();
            if ($clash) {
                throw ValidationException::withMessages(['check_in' => sprintf('Already booked %s to %s (%s%s).',
                    $clash->check_in->format('j M'), $clash->check_out->format('j M'), Booking::SOURCES[$clash->source] ?? $clash->source,
                    $clash->guest_name ? ', '.$clash->guest_name : '')]);
            }

            return Booking::create($data + ['unit_id' => $unit->id, 'company_id' => $unit->company_id ?? $unit->property?->company_id,
                'source' => 'direct', 'status' => 'confirmed', 'created_by' => $userId]);
        });
    }

    public function cancel(Booking $booking): void
    {
        $booking->update(['status' => 'cancelled']);
    }
}
