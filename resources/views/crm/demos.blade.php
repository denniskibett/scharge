@extends('layouts.app')

@section('content')
@php
    use App\Modules\CRM\Models\CrmFollowUp;
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $pill = fn ($on) => $on ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-300';
@endphp
<div class="mx-auto max-w-screen-xl">
    @include('crm.partials.nav', ['title' => 'Demo requests', 'subtitle' => 'From the website "Book a meeting" form (Kenya). Each one is due for a call-back within one working day. Requests from countries we are not in yet wait on the waitlist.'])

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('crm.demos.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ $pill(! $status) }}">All ({{ $counts->sum() }})</a>
        @foreach (CrmFollowUp::DEMO_STATUSES as $key => $name)
            <a href="{{ route('crm.demos.index', ['status' => $key]) }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ $pill($status === $key) }}">{{ $name }} ({{ $counts[$key] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="{{ $card }} space-y-3 p-5">
        @forelse ($demos as $demo)
            @if ($demo->is_open)
                @include('crm.partials.follow-up-row', ['followUp' => $demo, 'showCompany' => true])
            @else
                <div class="flex flex-wrap justify-between gap-2 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                    <span class="text-gray-800 dark:text-white/90">{{ $demo->contact?->name ?? 'Unknown' }} · <a href="{{ route('crm.companies.show', $demo->company) }}" class="text-brand-500">{{ $demo->company?->name }}</a> <span class="text-gray-500">· {{ CrmFollowUp::DEMO_STATUSES[$demo->status] }}</span></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ $demo->outcome }} · {{ $demo->done_at?->format('D j M, H:i') }}</span>
                </div>
            @endif
        @empty
            <p class="py-8 text-center text-gray-500 dark:text-gray-400">No demo requests yet. They arrive from the "Book a meeting" form on the Developers page.</p>
        @endforelse
        @if ($demos->hasPages())<div class="pt-2">{{ $demos->links() }}</div>@endif
    </div>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Marking a demo <b>Completed</b> creates a feedback link for the customer automatically.</p>
</div>
@endsection
