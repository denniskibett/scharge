@extends('layouts.app')

@section('content')

    @include('partials.table.table-tenancy', ['tenancies' => $tenanciesData])
@endsection