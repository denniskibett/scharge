<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\UnitPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Photos on a unit's page. One upload can be for just this house, for every house of the
 * same type in the property, or for the whole property (shared, not copied).
 */
class UnitPhotoController extends Controller
{
    public function index(Request $request, Unit $unit)
    {
        $this->authorizeUnit($request, $unit);
        if (! \Illuminate\Support\Facades\Schema::hasTable('unit_photos')) {
            return response()->json(['photos' => [], 'similar' => 0, 'message' => 'Apply database/schema/2026_11_09_unit_photos.sql to enable photos.']);
        }

        return response()->json([
            'photos' => UnitPhoto::forUnit($unit)->get()->map(fn ($p) => $this->present($p, $unit))->values(),
            'similar' => Unit::where('property_id', $unit->property_id)->where('unit_type', $unit->unit_type)->count(),
            'property_units' => Unit::where('property_id', $unit->property_id)->count(),
        ]);
    }

    public function store(Request $request, Unit $unit)
    {
        $this->authorizeUnit($request, $unit);
        $data = $request->validate([
            'photos' => 'required|array|min:1|max:12',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:6144',
            'applies_to' => ['required', Rule::in(UnitPhoto::APPLIES_TO)],
            'caption' => 'nullable|string|max:140',
        ]);
        $unit->loadMissing('property:id,company_id');
        $next = (int) UnitPhoto::where('property_id', $unit->property_id)->max('sort');

        $saved = collect($request->file('photos'))->map(function ($file) use ($unit, $data, $request, &$next) {
            return UnitPhoto::create([
                'company_id' => $unit->property->company_id ?? $unit->company_id,
                'property_id' => $unit->property_id,
                'unit_id' => $unit->id,
                'unit_type' => $data['applies_to'] === 'property' ? null : $unit->unit_type,
                'applies_to' => $data['applies_to'],
                'path' => $file->store('unit-photos/'.$unit->property_id, 'public'),
                'caption' => $data['caption'] ?? null,
                'sort' => ++$next,
                'uploaded_by' => $request->user()->id,
            ]);
        });

        \App\Services\ListingCatalog::forget();
        $similar = Unit::where('property_id', $unit->property_id)->where('unit_type', $unit->unit_type)->count();
        $where = match ($data['applies_to']) {
            'unit' => 'this house only',
            'type' => "all {$similar} {$unit->unit_type} houses in this property",
            'property' => 'every house in this property',
        };

        return response()->json([
            'message' => $saved->count().' photo'.($saved->count() > 1 ? 's' : '')." added for {$where}.",
            'photos' => UnitPhoto::forUnit($unit)->get()->map(fn ($p) => $this->present($p, $unit))->values(),
        ], 201);
    }

    public function destroy(Request $request, Unit $unit, UnitPhoto $photo)
    {
        $this->authorizeUnit($request, $unit);
        abort_unless((int) $photo->property_id === (int) $unit->property_id, 404);
        Storage::disk('public')->delete($photo->path);
        $photo->delete();
        \App\Services\ListingCatalog::forget();

        return response()->json(['message' => 'Photo removed.', 'photos' => UnitPhoto::forUnit($unit)->get()->map(fn ($p) => $this->present($p, $unit))->values()]);
    }

    private function present(UnitPhoto $p, Unit $unit): array
    {
        return [
            'id' => $p->id, 'url' => $p->url(), 'caption' => $p->caption, 'applies_to' => $p->applies_to,
            'scope_label' => match ($p->applies_to) {
                'unit' => 'This house', 'type' => 'All '.($p->unit_type ?? 'similar').' houses', default => 'Whole property',
            },
            'from_other_unit' => $p->unit_id !== null && (int) $p->unit_id !== (int) $unit->id,
        ];
    }

    private function authorizeUnit(Request $request, Unit $unit): void
    {
        $user = $request->user();
        $companyId = $unit->property?->company_id ?? $unit->company_id;
        abort_unless($user->hasRole('sysadmin') || ((int) $user->company_id === (int) $companyId && $user->hasAnyRole(['admin', 'property_manager'])), 403);
    }
}
