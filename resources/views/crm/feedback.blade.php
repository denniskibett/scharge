@extends('layouts.app')

@section('content')
@php
    use App\Modules\CRM\Models\CrmFollowUp;
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $bands = ['' => 'All', 'pending' => 'Waiting for an answer', 'detractor' => 'Unhappy (0–6)', 'passive' => 'Neutral (7–8)', 'promoter' => 'Happy (9–10)'];
@endphp
<div class="mx-auto max-w-screen-2xl">
    @include('crm.partials.nav', ['title' => 'Customer feedback', 'subtitle' => 'Ask after demos, onboarding and support. Low scores automatically open a recovery call for the account owner.'])

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ([['NPS', $summary['nps'] ?? '–'], ['Happy (9–10)', $summary['promoters']], ['Neutral (7–8)', $summary['passives']], ['Unhappy (0–6)', $summary['detractors']], ['Waiting', $summary['pending']]] as [$name, $value])
            <div class="{{ $card }} p-4"><span class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ $name }}</span><span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $value }}</span></div>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($bands as $key => $name)
            <a href="{{ route('crm.feedback.index', ['band' => $key ?: null]) }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => ($band ?? '') === $key, 'border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-300' => ($band ?? '') !== $key])>{{ $name }}</a>
        @endforeach
    </div>

    <div class="{{ $card }} overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <tr><th class="px-4 py-3 font-medium">Customer</th><th class="px-4 py-3 font-medium">About</th><th class="px-4 py-3 font-medium">Score</th><th class="px-4 py-3 font-medium">Comment</th><th class="px-4 py-3 font-medium">Recovery call</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($feedback as $fb)
                    <tr class="align-top">
                        <td class="px-4 py-3">@if ($fb->company)<a href="{{ route('crm.companies.show', $fb->company) }}" class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $fb->company->name }}</a>@endif<div class="text-xs text-gray-500">{{ $fb->contact?->name }}</div></td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ CrmFollowUp::FEEDBACK_CONTEXTS[$fb->detail('context')] ?? 'General' }}<div class="text-xs text-gray-500">{{ $fb->status === 'done' ? 'Answered '.$fb->done_at->format('j M Y') : 'Sent '.$fb->created_at->format('j M Y') }}</div></td>
                        <td class="px-4 py-3">
                            @if ($fb->score !== null)
                                <span @class(['rounded-lg px-2 py-1 font-semibold', 'bg-green-50 text-green-700' => $fb->score >= 9, 'bg-amber-50 text-amber-700' => $fb->score >= 7 && $fb->score <= 8, 'bg-red-50 text-red-700' => $fb->score <= 6])>{{ $fb->score }}/10</span>
                            @else
                                <button type="button" class="text-xs font-medium text-brand-500" onclick="navigator.clipboard.writeText('{{ route('site.feedback.show', $fb->token) }}'); this.textContent='Link copied'">Copy link</button>
                            @endif
                        </td>
                        <td class="max-w-md px-4 py-3 text-gray-600 dark:text-gray-300">{{ $fb->status === 'done' ? ($fb->notes ?? '—') : '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ $fb->detail('recovery_id') ? 'Opened' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No feedback yet. Ask from a company page, or mark a demo completed.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($feedback->hasPages())<div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">{{ $feedback->links() }}</div>@endif
    </div>
</div>
@endsection
