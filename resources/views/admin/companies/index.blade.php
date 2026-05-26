{{-- resources/views/admin/companies/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Companies Management')

@section('content')
<div x-data="companiesPage()" x-init="init()">
    @include('partials.card.card-companies')
    @include('partials.table.table-companies')
</div>
@endsection