{{-- resources/views/subscriptions/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Subscription Plans')

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            @section('page_title')
Subscription Plans
@endsection
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage region-based subscription plans and pricing</p>
        </div>
    </div>

    @include('partials.table.table-subscriptions')
</div>
@endsection