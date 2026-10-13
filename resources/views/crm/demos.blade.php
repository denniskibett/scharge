@extends('layouts.app')

@section('content')
@php
    use App\Modules\CRM\Models\CrmFollowUp;
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $pill = fn ($on) => $on ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-300';
    $statuses = match ($type) {
        'demo' => CrmFollowUp::DEMO_STATUSES,
        'listing_alert' => ['open' => 'Active', 'cancelled' => 'Stopped'],
        default => CrmFollowUp::STATUSES,
    };
    $intro = [
        'demo' => 'From the "Book a meeting" form (Kenya). Each one is due for a call-back within one working day. Requests from countries we are not in yet wait on the waitlist.',
        'api_keys' => 'From "Get sandbox keys" on the API page. The company is in the pipeline; send the keys and follow up.',
        'viewing' => 'Viewing, booking and site-visit requests from Open listings. Pass each one to the owner or caretaker the same day.',
        'listing_alert' => 'Renters who asked for an SMS when a new match is listed. Each person keeps one active alert with their latest search.',
    ][$type];
@endphp
<div class="mx-auto max-w-screen-xl">
    @include('crm.partials.nav', ['title' => 'Requests', 'subtitle' => $intro])

    <div class="mb-3 flex flex-wrap gap-2">
        @foreach (CrmFollowUp::REQUEST_TYPES as $key => $name)
            <a href="{{ route('crm.requests.index', ['type' => $key]) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ $pill($type === $key) }}">
                {{ $name }}@if ($openByType[$key] ?? 0)<span class="rounded-full bg-red-600 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white">{{ $openByType[$key] }}</span>@endif
            </a>
        @endforeach
    </div>
    <div class="mb-4 flex flex-wrap gap-2 text-xs">
        <a href="{{ route('crm.requests.index', ['type' => $type]) }}" class="rounded-md px-2 py-1 {{ $pill(! $status) }}">All ({{ $counts->sum() }})</a>
        @foreach ($statuses as $key => $name)
            <a href="{{ route('crm.requests.index', ['type' => $type, 'status' => $key]) }}" class="rounded-md px-2 py-1 {{ $pill($status === $key) }}">{{ $name }} ({{ $counts[$key] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="{{ $card }} space-y-3 p-5">
        @forelse ($demos as $row)
            @if ($row->is_open && $type !== 'listing_alert')
                @include('crm.partials.follow-up-row', ['followUp' => $row, 'showCompany' => true])
            @else
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                    <div>
                        <div class="text-gray-800 dark:text-white/90">{{ $row->title }}</div>
                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $row->contact?->name }}
                            @if ($row->contact?->phone) · <a href="tel:{{ $row->contact->phone }}" class="text-brand-500">{{ $row->contact->phone }}</a>@endif
                            @if ($row->company) · <a href="{{ route('crm.companies.show', $row->company) }}" class="text-brand-500">{{ $row->company->name }}</a>@endif
                            · {{ $row->created_at->format('j M Y') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $statuses[$row->status] ?? ucfirst($row->status) }}@if ($row->outcome) · {{ $row->outcome }}@endif</span>
                        @if ($type === 'listing_alert' && $row->status === 'open')
                            <form method="POST" action="{{ route('crm.follow-ups.update', $row) }}">@csrf @method('PATCH')
                                <input type="hidden" name="status" value="cancelled"><input type="hidden" name="outcome" value="Alert stopped">
                                <button class="rounded-md border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Stop alert</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <p class="py-8 text-center text-gray-500 dark:text-gray-400">Nothing here yet.</p>
        @endforelse
        @if ($demos->hasPages())<div class="pt-2">{{ $demos->links() }}</div>@endif
    </div>
    @if ($type === 'demo')<p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Marking a demo <b>Completed</b> creates a feedback link for the customer automatically.</p>@endif
</div>
@endsection
