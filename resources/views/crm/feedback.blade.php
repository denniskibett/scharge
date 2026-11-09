@extends('layouts.app')

@section('content')
@include('partials.card.crm-feedback')
@php
    use App\Modules\CRM\Models\CrmFollowUp;
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $bands = ['' => 'All', 'pending' => 'Waiting for an answer', 'detractor' => 'Unhappy (0–6)', 'passive' => 'Neutral (7–8)', 'promoter' => 'Happy (9–10)'];
@endphp
<div>
    @include('crm.partials.nav', ['title' => 'Customer feedback', 'subtitle' => 'Ask after demos, onboarding and support. Low scores automatically open a recovery call for the account owner.'])

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ([['NPS', $summary['nps'] ?? '–'], ['Happy (9–10)', $summary['promoters']], ['Neutral (7–8)', $summary['passives']], ['Unhappy (0–6)', $summary['detractors']], ['Waiting', $summary['pending']]] as [$name, $value])
            <div class="{{ $card }} p-4"><span class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ $name }}</span><span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $value }}</span></div>
        @endforeach
    </div>

    @php
        $bandTabs = [];
        $bandCounts = ['pending' => [$summary['pending'], 'amber'], 'detractor' => [$summary['detractors'], 'red'], 'passive' => [$summary['passives'], 'gray'], 'promoter' => [$summary['promoters'], 'green']];
        foreach ($bands as $key => $bandName) {
            $bandTabs[] = ['label' => $key === '' ? 'All' : $bandName, 'url' => route('crm.feedback.index', ['band' => $key ?: null]), 'active' => ($band ?? '') === $key]
                + (isset($bandCounts[$key]) ? ['count' => $bandCounts[$key][0], 'tone' => $bandCounts[$key][1]] : []);
        }
        $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            @include('partials.table.tabs', ['tabs' => $bandTabs])
        </div>
        <div class="w-full overflow-x-auto">
        <table class="data-table w-full table-auto">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Customer</th><th class="{{ $th }}">About</th><th class="{{ $th }}">Score</th><th class="{{ $th }}">Comment</th><th class="{{ $th }}">Recovery call</th></tr>
            </thead>
            <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($feedback as $fb)
                    <tr class="align-top transition hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="p-4 whitespace-nowrap">@if ($fb->company)<a href="{{ route('crm.companies.show', $fb->company) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">{{ $fb->company->name }}</a>@endif<p class="text-xs text-gray-500 dark:text-gray-400">{{ $fb->contact?->name }}</p></td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ CrmFollowUp::FEEDBACK_CONTEXTS[$fb->detail('context')] ?? 'General' }}<p class="text-xs text-gray-500 dark:text-gray-400">{{ $fb->status === 'done' ? 'Answered '.$fb->done_at->format('j M Y') : 'Sent '.$fb->created_at->format('j M Y') }}</p></td>
                        <td class="p-4 whitespace-nowrap">
                            @if ($fb->score !== null)
                                <span @class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $fb->score >= 9, 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' => $fb->score >= 7 && $fb->score <= 8, 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => $fb->score <= 6])>{{ $fb->score }}/10</span>
                            @else
                                <button type="button" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400" onclick="navigator.clipboard.writeText('{{ route('site.feedback.show', $fb->token) }}'); this.textContent='Link copied'">Copy link</button>
                            @endif
                        </td>
                        <td class="max-w-md p-4 text-sm text-gray-500 dark:text-gray-400">{{ $fb->status === 'done' ? ($fb->notes ?? '—') : '—' }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $fb->detail('recovery_id') ? 'Opened' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-4 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No feedback yet</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ask from a company page, or mark a demo completed.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.table.pager', ['paginator' => $feedback])
    </div>
</div>
@endsection
