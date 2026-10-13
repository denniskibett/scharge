@extends('layouts.app')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $views = ['overdue' => 'Overdue', 'today' => 'Due today', 'open' => 'All open', 'done' => 'Done'];
@endphp
<div class="mx-auto max-w-screen-xl">
    @include('crm.partials.nav', ['title' => 'Follow-ups', 'subtitle' => 'Work top to bottom. When you mark one done, book the next step so no prospect goes quiet.'])

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($views as $key => $name)
            <a href="{{ route('crm.follow-ups.index', ['view' => $key, 'mine' => $mine ?: null]) }}" @class([
                'rounded-lg px-3 py-2 text-sm font-medium',
                'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $view === $key,
                'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300' => $view !== $key,
            ])>{{ $name }}@isset($counts[$key]) ({{ $counts[$key] }})@endisset</a>
        @endforeach
        <a href="{{ route('crm.follow-ups.index', ['view' => $view, 'mine' => $mine ? null : 1]) }}" class="ml-auto inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <span @class(['inline-block h-4 w-4 rounded border', 'border-brand-500 bg-brand-500' => $mine, 'border-gray-400' => ! $mine])></span> Only mine
        </a>
    </div>

    <div class="{{ $card }} space-y-3">
        @forelse ($followUps as $followUp)
            @if (! $followUp->is_open)
                <div class="flex flex-wrap justify-between gap-2 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                    <span class="text-gray-800 dark:text-white/90">{{ $followUp->status === 'done' ? '✅' : '✖️' }} {{ $followUp->title }} · @if ($followUp->company)<a href="{{ route('crm.companies.show', $followUp->company) }}" class="text-brand-500">{{ $followUp->company->name }}</a>@else Open listings @endif</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ $followUp->outcome }} · {{ $followUp->done_at?->format('D j M, H:i') }}</span>
                </div>
            @else
                @include('crm.partials.follow-up-row', ['followUp' => $followUp, 'showCompany' => true])
            @endif
        @empty
            <p class="py-8 text-center text-gray-500 dark:text-gray-400">{{ $view === 'overdue' ? 'Nothing overdue. Nice.' : 'Nothing here.' }}</p>
        @endforelse
        @if ($followUps->hasPages())<div class="pt-2">{{ $followUps->links() }}</div>@endif
    </div>
</div>
@endsection
