@extends('layouts.app')

@section('content')
@php
    use App\Models\Company;
    use App\Modules\CRM\Controllers\Admin\CrmSalesController as Sales;
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $kes = fn ($n) => $n ? 'KES '.number_format($n) : '–';
    $th = 'px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $td = 'px-3 py-2.5 text-sm text-gray-800 dark:text-white/90';
    $kpis = [
        ['Closed won', $totals['won'], 'text-green-600'],
        ['Won value / month', $kes($totals['won_value']), 'text-green-600'],
        ['Lost', $totals['lost'], 'text-gray-800 dark:text-white/90'],
        ['Open deals', $totals['open'], 'text-gray-800 dark:text-white/90'],
        ['Pipeline / month', $kes($totals['pipeline']), 'text-gray-800 dark:text-white/90'],
        ['Weighted forecast', $kes($totals['forecast']), 'text-gray-800 dark:text-white/90'],
        ['Expected to close', $totals['closing'], 'text-gray-800 dark:text-white/90'],
    ];
@endphp
<div class="mx-auto max-w-screen-2xl">
    @include('crm.partials.nav', ['title' => 'Sales tracker', 'subtitle' => 'Who is closing what: wins, pipeline and overdue work per account manager, and the deals nearest to closing.'])

    {{-- Period + owner --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <div class="flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-900">
            @foreach (Sales::PERIODS as $key => $name)
                <a href="{{ route('crm.sales.index', array_filter(['period' => $key, 'owner' => $seesAll ? $ownerFilter : null])) }}" @class([
                    'rounded-lg px-3 py-1.5 text-sm font-medium',
                    'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' => $period === $key,
                    'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $period !== $key,
                ])>{{ $name }}</a>
            @endforeach
        </div>
        @if ($seesAll)
            <input type="hidden" name="period" value="{{ $period }}">
            <select name="owner" class="{{ $field }}" aria-label="Account manager" onchange="this.form.submit()">
                <option value="">Whole team</option>
                @foreach ($staff as $s)<option value="{{ $s->id }}" @selected($ownerFilter == $s->id)>{{ $s->name }}</option>@endforeach
                <option value="none" @selected($ownerFilter === 'none')>Unassigned</option>
            </select>
        @endif
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $from->format('j M') }} – {{ $to->format('j M Y') }}</span>
    </form>

    {{-- KPIs --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
        @foreach ($kpis as [$name, $value, $colour])
            <div class="{{ $card }} p-4">
                <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ $name }}</span>
                <span class="mt-1 block text-xl font-semibold {{ $colour }}">{{ $value }}</span>
            </div>
        @endforeach
    </div>

    {{-- Scoreboard --}}
    <div class="{{ $card }} mb-6 overflow-x-auto">
        <div class="flex items-center justify-between px-4 pt-4">
            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $seesAll ? 'Account managers' : 'My numbers' }}</h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">Won/lost = deals moved to that stage in the period, credited to the current owner. Values are monthly (KES).</span>
        </div>
        <table class="mt-2 min-w-full divide-y divide-gray-100 dark:divide-gray-800">
            <thead><tr>
                <th class="{{ $th }}">Account manager</th><th class="{{ $th }}">Won</th><th class="{{ $th }}">Won value</th><th class="{{ $th }}">Lost</th><th class="{{ $th }}">Win rate</th>
                <th class="{{ $th }}">Open</th><th class="{{ $th }}">Proposal + negotiation</th><th class="{{ $th }}">Pipeline</th><th class="{{ $th }}">Calls / emails / meetings</th><th class="{{ $th }}">Overdue</th><th class="{{ $th }}">No next step</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($rows as $r)
                    @php($pipelineLink = route('crm.index', ['owner' => $r['id'] ?? 'none', 'stage' => 'open']))
                    <tr>
                        <td class="{{ $td }} font-medium">
                            @if ($seesAll)<a href="{{ route('crm.sales.index', ['period' => $period, 'owner' => $r['id'] ?? 'none']) }}" class="hover:text-brand-500">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif
                        </td>
                        <td class="{{ $td }} font-semibold text-green-600">{{ $r['won'] }}</td>
                        <td class="{{ $td }}">{{ $kes($r['won_value']) }}</td>
                        <td class="{{ $td }}">{{ $r['lost'] }}</td>
                        <td class="{{ $td }}">{{ $r['win_rate'] !== null ? $r['win_rate'].'%' : '–' }}</td>
                        <td class="{{ $td }}"><a href="{{ $pipelineLink }}" class="hover:text-brand-500">{{ $r['open'] }}</a></td>
                        <td class="{{ $td }}">{{ $r['late'] }}</td>
                        <td class="{{ $td }}">{{ $kes($r['pipeline']) }}</td>
                        <td class="{{ $td }}">{{ $r['activity'] ?? '–' }}</td>
                        <td class="{{ $td }} {{ $r['overdue'] ? 'font-semibold text-red-600' : '' }}">{{ $r['overdue'] }}</td>
                        <td class="{{ $td }} {{ $r['no_next_step'] ? 'text-amber-600' : '' }}">
                            @if ($r['no_next_step'])<a href="{{ route('crm.index', ['owner' => $r['id'] ?? 'none', 'attention' => 'unattended']) }}" class="hover:underline">{{ $r['no_next_step'] }}</a>@else 0 @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="{{ $td }} text-gray-500">No account managers yet. Give someone the account_manager role and assign them companies.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Closing board --}}
    <div class="mb-3 flex items-end justify-between">
        <div>
            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Closing board</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Deals from qualified onwards, soonest expected close first. Set deal value and expected close on the company page.</p>
        </div>
    </div>
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($board as $stage => $deals)
            @php($next = Sales::CLOSING_STAGES[array_search($stage, Sales::CLOSING_STAGES) + 1] ?? null)
            <section class="rounded-2xl bg-gray-50 p-3 dark:bg-white/[0.02]" aria-label="{{ Company::CRM_STAGES[$stage] }}">
                <div class="mb-2 flex items-center justify-between px-1">
                    @include('crm.partials.stage-badge', ['stage' => $stage])
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $deals->count() }} · {{ $kes($deals->sum(fn ($c) => Sales::value($c))) }}</span>
                </div>
                <div class="space-y-2">
                    @forelse ($deals as $c)
                        @php($close = Sales::date($c->profile('expected_close')))
                        <article class="{{ $card }} p-3" x-data="{ losing: false }">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('crm.companies.show', $c) }}" class="text-sm font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $c->name }}</a>
                                @include('crm.partials.tier-badge', ['tier' => $c->tier])
                            </div>
                            <div class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <div>{{ $c->owner?->name ?? 'Unassigned' }}@if (Sales::value($c)) · <span class="font-medium text-gray-700 dark:text-gray-300">{{ $kes(Sales::value($c)) }}/mo</span>@endif</div>
                                @if ($close)<div @class(['font-medium text-red-600' => $close->isPast()])>Close {{ $close->isPast() ? 'was due' : 'by' }} {{ $close->format('j M') }}</div>@endif
                                @if ($c->next_due_at)<div>Next step {{ \Illuminate\Support\Carbon::parse($c->next_due_at)->diffForHumans() }}</div>@else<div class="text-amber-600">No next step</div>@endif
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1" x-show="!losing">
                                @if ($next)
                                    <form method="POST" action="{{ route('crm.companies.update', $c) }}">@csrf @method('PUT')<input type="hidden" name="crm_stage" value="{{ $next }}">
                                        <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">→ {{ Company::CRM_STAGES[$next] }}</button></form>
                                @endif
                                <form method="POST" action="{{ route('crm.companies.update', $c) }}" onsubmit="return confirm('Mark this deal as won? The company becomes a customer.')">@csrf @method('PUT')<input type="hidden" name="crm_stage" value="won">
                                    <button class="rounded-lg bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700">Won</button></form>
                                <button type="button" @click="losing = true" class="rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">Lost</button>
                            </div>
                            <form method="POST" action="{{ route('crm.companies.update', $c) }}" x-show="losing" x-cloak class="mt-2 flex gap-1">@csrf @method('PUT')
                                <input type="hidden" name="crm_stage" value="lost">
                                <input name="lost_reason" required placeholder="Why lost?" class="h-8 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-2 text-xs dark:border-gray-700 dark:text-white/90">
                                <button class="rounded-lg bg-red-600 px-2 py-1 text-xs font-medium text-white">Save</button>
                                <button type="button" @click="losing = false" class="px-1 text-xs text-gray-500">Cancel</button>
                            </form>
                        </article>
                    @empty
                        <p class="px-1 py-4 text-center text-xs text-gray-400">Nothing here</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    {{-- Recent wins --}}
    @if ($recentWins->isNotEmpty())
        <div class="{{ $card }} p-4">
            <h3 class="mb-2 text-base font-semibold text-gray-800 dark:text-white/90">Closed won in this period</h3>
            <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                @foreach ($recentWins as $m)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('crm.companies.show', $m->company_id) }}" class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $m->company->name }}</a>
                        <span class="text-gray-500 dark:text-gray-400">{{ $kes(Sales::value($m->company)) }} · {{ $m->done_at->format('j M') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
