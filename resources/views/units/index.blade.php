@extends('layouts.app')

@section('content')
<!-- Include all modal partials -->
@include('partials.modal.units-create-modal')
@include('partials.modal.units-edit-modal')
@include('partials.modal.units-show-modal')
@include('partials.modal.units-delete-modal')
@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

@include('partials.card.units')

<!-- Include the units table partial -->
@include('partials.table.table-units', [
    'unitsData' => $unitsData ?? [],
    'totalUnits' => $totalUnits,
    'occupiedCount' => $occupiedCount,
    'vacantCount' => $vacantCount,
    'properties' => $properties ?? [],
    'currentProperty' => $property ?? null,
    'showPropertyFilter' => true,
    'showPropertyColumn' => true,
    'showUtilityColumns' => true,
    'showTotalColumn' => true,
    'hidePropertyFilter' => false
])

<!-- Hidden data for Alpine.js (kept for backward compatibility) -->
<script type="application/json" id="units-data">
@json($unitsData ?? [])
</script>
@endsection