@extends('layouts.app')

@section('content')
<div x-data="invoicePage()">
    @include('partials.card.maintenance')
    @include('partials.table.table-maintenance')
</div>

@endsection