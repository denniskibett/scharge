@extends('layouts.app')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-10 rounded-lg border border-gray-300 bg-transparent px-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp
<div class="mx-auto max-w-screen-2xl">
    @include('crm.partials.nav', ['title' => 'Demo requests', 'subtitle' => 'From the website "Book a meeting" form. Each one opens a call-back follow-up due within one working day.'])

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('crm.demos.index') }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => ! $status, 'border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-300' => $status])>All ({{ $counts->sum() }})</a>
        @foreach (\App\Modules\CRM\Models\CrmDemoRequest::STATUSES as $key => $name)
            <a href="{{ route('crm.demos.index', ['status' => $key]) }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $status === $key, 'border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-300' => $status !== $key])>{{ $name }} ({{ $counts[$key] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="{{ $card }} overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <tr><th class="px-4 py-3 font-medium">Requested</th><th class="px-4 py-3 font-medium">Person</th><th class="px-4 py-3 font-medium">Company</th><th class="px-4 py-3 font-medium">Project</th><th class="px-4 py-3 font-medium">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($demos as $demo)
                    <tr class="align-top">
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $demo->created_at->format('D j M, H:i') }}<div class="text-xs text-gray-400">{{ $demo->created_at->diffForHumans() }}</div></td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800 dark:text-white/90">{{ $demo->user?->name ?? '—' }}</div>
                            @if ($demo->user?->phone)<a href="tel:{{ $demo->user->phone }}" class="block text-xs text-brand-500">{{ $demo->user->phone }}</a>@endif
                            @if ($demo->user?->email)<a href="mailto:{{ $demo->user->email }}" class="block text-xs text-brand-500">{{ $demo->user->email }}</a>@endif
                        </td>
                        <td class="px-4 py-3">@if ($demo->company)<a href="{{ route('crm.companies.show', $demo->company) }}" class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $demo->company->name }}</a><div class="mt-1">@include('crm.partials.stage-badge', ['stage' => $demo->company->stage])</div>@else — @endif</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $demo->project_type ?? '—' }}<div class="text-xs text-gray-500">{{ $demo->units ? number_format($demo->units).' units · ' : '' }}{{ $demo->country?->name }}</div>@if ($demo->message)<div class="mt-1 max-w-xs text-xs italic">“{{ \Illuminate\Support\Str::limit($demo->message, 140) }}”</div>@endif</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('crm.demos.update', $demo) }}" class="flex flex-wrap items-center gap-2" x-data="{ status: '{{ $demo->status }}' }">
                                @csrf @method('PATCH')
                                <select name="status" x-model="status" class="{{ $field }}" aria-label="Status">@foreach (\App\Modules\CRM\Models\CrmDemoRequest::STATUSES as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select>
                                <input type="datetime-local" name="scheduled_at" x-show="status === 'scheduled'" value="{{ $demo->scheduled_at?->format('Y-m-d\TH:i') }}" class="{{ $field }}" aria-label="Demo date and time">
                                <button class="h-10 rounded-lg bg-brand-500 px-3 text-sm font-medium text-white hover:bg-brand-600">Save</button>
                            </form>
                            @if ($demo->scheduled_at)<div class="mt-1 text-xs text-gray-500">Demo: {{ $demo->scheduled_at->format('D j M, H:i') }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No demo requests yet. They arrive from the "Book a meeting" form on the Developers page.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($demos->hasPages())<div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">{{ $demos->links() }}</div>@endif
    </div>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Marking a demo <b>Completed</b> creates a feedback link for the customer automatically.</p>
</div>
@endsection
