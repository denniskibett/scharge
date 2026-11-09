<?php

namespace App\Services\Properties;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyClaim;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitManagement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BnB hosts inside shared buildings.
 *  - A host registers their unit in a building: the existing property if scharge already has it,
 *    otherwise a new "unclaimed" one. The host manages the unit through unit_managements (bnb_host).
 *  - units.company_id is the building's management company once the building is claimed (so its
 *    water, invoices and tenancies include the host unit and can bill the host service charge and
 *    water), and the host's own company while nobody manages the building yet.
 *  - A management company claims a building; staff approve; host units stay with their hosts.
 */
class HostUnits
{
    /** @param array{property_id?: ?int, property_name?: ?string, location?: ?string, unit_number: string, unit_type?: ?string, nightly_rate?: ?float} $d */
    public function registerUnit(Company $host, User $by, array $d): Unit
    {
        return DB::transaction(function () use ($host, $by, $d) {
            $property = ! empty($d['property_id'])
                ? Property::lockForUpdate()->findOrFail($d['property_id'])
                : $this->findOrCreateUnclaimed($host, (string) $d['property_name'], $d['location'] ?? null);

            $number = trim($d['unit_number']);
            if (Unit::where('property_id', $property->id)->where('unit_number', $number)->exists()) {
                throw ValidationException::withMessages(['unit_number' => "Unit {$number} at {$property->name} is already on scharge. If it is yours, contact support and we will move it to your account."]);
            }

            $managed = $property->company_id && ($property->claim_status ?? 'managed') === 'managed';
            $unit = (new Unit)->forceFill([
                'company_id' => $managed ? $property->company_id : $host->id,
                'property_id' => $property->id,
                'unit_number' => $number,
                'unit_type' => $d['unit_type'] ?? 'Studio',
                'rent_amount' => 0,
                'status' => 'vacant',
                'stay_type' => 'bnb',
                'bnb_nightly_rate' => $d['nightly_rate'] ?? null,
                'is_active' => 1,
            ]);
            $unit->save();

            UnitManagement::create(['unit_id' => $unit->id, 'company_id' => $host->id, 'role' => 'bnb_host', 'status' => 'active',
                'starts_at' => now(), 'created_by' => $by->id, 'notes' => $managed ? 'Host unit in a managed building' : 'Host registered before the building was managed']);
            if ($managed) {
                UnitManagement::create(['unit_id' => $unit->id, 'company_id' => $property->company_id, 'role' => 'manager', 'status' => 'active', 'starts_at' => now(), 'created_by' => $by->id]);
            }

            return $unit;
        });
    }

    private function findOrCreateUnclaimed(Company $host, string $name, ?string $location): Property
    {
        $match = app(PropertyRegistry::class)->matches($name, $location)->first();
        if ($match && $match['score'] >= 95) {
            return $match['property'];   // the same building: never create it twice
        }

        $property = Property::forceCreate(['name' => $name, 'location' => $location, 'water_rate' => 0, 'service_charge' => 0, 'garbage_charge' => 0,
            'security_charge' => 0, 'company_id' => null, 'claim_status' => 'unclaimed', 'created_by_company_id' => $host->id]);
        PropertyRegistry::index($property);

        return $property;
    }

    /** Staff approve a claim: the company now manages the building; host units stay with their hosts. */
    public function approve(PropertyClaim $claim, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($claim, $reviewer, $note) {
            $claim = PropertyClaim::whereKey($claim->id)->lockForUpdate()->firstOrFail();
            abort_unless($claim->status === 'pending', 422, 'This claim was already reviewed.');
            $property = Property::whereKey($claim->property_id)->lockForUpdate()->firstOrFail();
            $manager = $claim->company_id;

            $property->forceFill(['company_id' => $manager, 'claim_status' => 'managed'])->save();

            foreach (Unit::where('property_id', $property->id)->get() as $unit) {
                $host = UnitManagement::hostOf($unit->id);
                // The management company now runs the building's books for every unit…
                UnitManagement::where('unit_id', $unit->id)->where('role', 'manager')->where('status', 'active')
                    ->where('company_id', '!=', $manager)->update(['status' => 'ended', 'ends_at' => now()]);
                UnitManagement::firstOrCreate(['unit_id' => $unit->id, 'company_id' => $manager, 'role' => 'manager', 'status' => 'active'],
                    ['starts_at' => now(), 'created_by' => $reviewer->id, 'notes' => 'Building claim approved']);
                $unit->forceFill(['company_id' => $manager])->save();

                // …and bills each host for service charge and water, as an owner-occupier tenancy.
                if ($host) {
                    $this->ownerTenancy($unit, $host, $manager);
                }
            }

            // Other pending claims on the same building lose.
            PropertyClaim::where('property_id', $property->id)->where('status', 'pending')->whereKeyNot($claim->id)
                ->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => 'Another company\'s claim was approved']);
            $claim->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
        });
    }

    public function reject(PropertyClaim $claim, User $reviewer, ?string $note = null): void
    {
        abort_unless($claim->status === 'pending', 422, 'This claim was already reviewed.');
        $claim->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
    }

    /** A tenancy so the management company can invoice the host service charge and water (rent 0). */
    private function ownerTenancy(Unit $unit, UnitManagement $host, int $managerCompanyId): void
    {
        $hostUser = User::where('company_id', $host->company_id)->orderBy('id')->first();
        if (! $hostUser || Tenancy::where('unit_id', $unit->id)->where('status', 'active')->exists()) {
            return;
        }
        $tenant = Tenant::where('user_id', $hostUser->id)->where('company_id', $managerCompanyId)->first()
            ?? Tenant::forceCreate(['user_id' => $hostUser->id, 'company_id' => $managerCompanyId, 'property_id' => $unit->property_id, 'notes' => 'BnB host (unit owner)']);
        Tenancy::forceCreate([
            'company_id' => $managerCompanyId, 'property_id' => $unit->property_id, 'unit_id' => $unit->id, 'tenant_id' => $tenant->id,
            'move_in_date' => now()->toDateString(), 'status' => 'active',
            'notes' => 'Owner-managed BnB: billed service charge and water only; bookings stay with the host.',
        ]);
    }
}
