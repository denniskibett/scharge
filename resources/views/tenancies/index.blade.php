@extends('layouts.app')

@section('content')
@include('partials.card.tenancies')

    @include('partials.table.table-tenancy', ['tenancies' => $tenanciesData])
@endsection