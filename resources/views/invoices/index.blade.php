@extends('layouts.app')

@section('content')
<div x-data="invoicePage()">
    @include('partials.card.card-invoices')
    @include('partials.table.table-invoices')
</div>

@endsection