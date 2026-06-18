{{-- resources/views/subscriptions/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Subscription Plans')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Subscription Plans</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage region-based subscription plans and pricing</p>
        </div>
    </div>

    @include('partials.table.table-subscriptions')
</div>
@endsection