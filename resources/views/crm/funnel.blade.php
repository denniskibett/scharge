@extends('layouts.app')

@section('content')
@php
    $th = 'px-3 py-3 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $td = 'px-3 py-3 text-sm whitespace-nowrap text-gray-700 dark:text-gray-300';
    $card = 'overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $head = 'flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-3 dark:border-gray-800';
    $h3 = 'text-sm font-semibold text-gray-800 dark:text-white/90';
    $tz = config('app.schedule_timezone', 'Africa/Nairobi');
    $window = $recap->from->copy()->tz($tz)->format('j M Y').' – '.$recap->to->copy()->tz($tz)->format('j M Y');
    $top = max(1, $steps[0]['reached']);
    // Bar width on a log scale, so a handful of deals still shows next to 100,000 prospects.
    $bar = fn ($n) => $n ? max(2, round(log10($n + 1) / log10($top + 1) * 100)) : 0;
    $q = fn (array $extra = []) => array_filter(['period' => $period, 'person' => $seesTeam ? $person?->id : null] + $extra);
    $pipe = fn (array $extra = []) => route('crm.index', array_filter(['owner' => $person?->id] + $extra));
    $won = $now['won'] ?? 0;
    $open = collect(\App\Models\Company::OPEN_CRM_STAGES)->sum(fn ($s) => $now[$s] ?? 0);
    $engaged = collect(['qualified', 'demo_booked', 'proposal', 'negotiation'])->sum(fn ($s) => $now[$s] ?? 0);
    $em = $emails;
    $pct = fn ($n, $of) => $of ? round($n / $of * 100, 1).'%' : '-';
@endphp
<div>
    @include('crm.partials.nav', ['title' => 'Sales funnel', 'subtitle' => $seesTeam
        ? ($person ? $person->name.'\'s book: where each prospect stands, what moved, and how the cold emails did.' : 'The whole team: where every prospect stands, what moved, and how the cold emails did.')
        : 'Your book: where each of your prospects stands, what moved, and how your cold emails did.'])

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        @include('partials.table.tabs', ['tabs' => collect(\App\Modules\CRM\Controllers\Admin\CrmFunnelController::PERIODS)->map(fn ($l, $k) => ['label' => $l, 'url' => route('crm.funnel.index', array_filter(['period' => $k, 'person' => $seesTeam ? $person?->id : null])), 'active' => $period === $k])->values()->all()])
        @if ($seesTeam)
            <form method="GET" action="{{ route('crm.funnel.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="period" value="{{ $period }}">
                <label for="funnel-person" class="text-sm text-gray-500 dark:text-gray-400">Show</label>
                <select id="funnel-person" name="person" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">Whole team</option>
                    @foreach ($staff as $s)
                        <option value="{{ $s->id }}" @selected($person?->id === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @include('partials.card.pills', ['pills' => [
        ['label' => 'Prospects in the funnel', 'value' => number_format($steps[0]['reached'] + ($now['lost'] ?? 0) + ($now['nurture'] ?? 0)), 'url' => $pipe(), 'tone' => 'gray'],
        ['label' => 'Open deals past Contacted', 'value' => number_format($engaged), 'url' => $pipe(['stage' => 'open']), 'tone' => $engaged ? 'green' : 'gray'],
        ['label' => 'Won', 'value' => number_format($won), 'url' => $pipe(['stage' => 'won']), 'tone' => $won ? 'green' : 'gray'],
        ['label' => 'Contacted → Won', 'value' => $pct($won, $steps[1]['reached']), 'url' => null, 'tone' => 'gray'],
        ['label' => 'Moved in '.\Illuminate\Support\Str::lower(\App\Modules\CRM\Controllers\Admin\CrmFunnelController::PERIODS[$period]), 'value' => number_format($moves->sum()), 'url' => null, 'tone' => 'gray'],
    ]])

    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="{{ $card }}">
            <div class="{{ $head }}">
                <h3 class="{{ $h3 }}">Where they stand now</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">reached = at this stage or further</span>
            </div>
            <table class="w-full table-auto"><tbody>
                @foreach ($steps as $s)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-5 py-2.5 text-sm whitespace-nowrap"><a href="{{ $pipe(['stage' => $s['stage']]) }}" class="font-medium text-gray-700 hover:text-blue-600 dark:text-gray-300">{{ $s['label'] }}</a></td>
                        <td class="px-3 py-2.5 text-right text-sm font-semibold text-gray-800 dark:text-white/90" title="Reached this stage or further">{{ number_format($s['reached']) }}</td>
                        <td class="px-3 py-2.5 text-right text-xs whitespace-nowrap text-gray-500 dark:text-gray-400">
                            @if ($s['rate'] !== null)<span class="{{ $s['rate'] >= 20 ? 'text-green-700' : '' }}">{{ $s['rate'] }}% of previous</span>@endif
                        </td>
                        <td class="px-5 py-2.5" style="width:40%"><div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full {{ $s['stage'] === 'won' ? 'bg-green-500' : 'bg-brand-500' }}" style="width: {{ $bar($s['reached']) }}%"></div></div></td>
                        <td class="px-3 py-2.5 text-right text-xs whitespace-nowrap text-gray-500 dark:text-gray-400">{{ number_format($s['now']) }} here now</td>
                    </tr>
                @endforeach
                @foreach (['lost' => 'Lost', 'nurture' => 'Nurture (later)'] as $k => $label)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-5 py-2.5 text-sm whitespace-nowrap"><a href="{{ $pipe(['stage' => $k]) }}" class="text-gray-500 hover:text-blue-600 dark:text-gray-400">{{ $label }}</a></td>
                        <td class="px-3 py-2.5 text-right text-sm text-gray-600 dark:text-gray-400">{{ number_format($now[$k] ?? 0) }}</td>
                        <td></td>
                        <td class="px-5 py-2.5" style="width:40%"><div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full {{ $k === 'lost' ? 'bg-red-500' : 'bg-gray-400' }}" style="width: {{ $bar($now[$k] ?? 0) }}%"></div></div></td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody></table>
            <p class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">Bars use a log scale so a few deals still show next to thousands of new prospects. Click a stage to open those prospects on the Pipeline. Lost and Nurture leave the path.</p>
        </div>

        <div class="{{ $card }}">
            <div class="{{ $head }}">
                <h3 class="{{ $h3 }}">Moved into each stage</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $window }}</span>
            </div>
            <table class="w-full table-auto"><tbody>
                @php($maxMove = max(1, $moves->max() ?? 0))
                @foreach (\App\Models\Company::CRM_STAGES as $k => $label)
                    @continue($k === 'new')
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-5 py-2.5 text-sm text-gray-600 dark:text-gray-400">{{ $label }}</td>
                        <td class="px-3 py-2.5 text-right text-sm font-semibold {{ $k === 'won' && ($moves[$k] ?? 0) ? 'text-green-700' : ($k === 'lost' && ($moves[$k] ?? 0) ? 'text-red-600' : 'text-gray-800 dark:text-white/90') }}">{{ number_format($moves[$k] ?? 0) }}</td>
                        <td class="px-5 py-2.5" style="width:55%"><div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full {{ $k === 'won' ? 'bg-green-500' : ($k === 'lost' ? 'bg-red-500' : 'bg-brand-500') }}" style="width: {{ ($moves[$k] ?? 0) ? max(2, round($moves[$k] / $maxMove * 100)) : 0 }}%"></div></div></td>
                    </tr>
                @endforeach
            </tbody></table>
            <p class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">Every stage change in the period, by people and by the system (a campaign email moves New → Contacted).</p>
        </div>
    </div>

    <div class="mb-6 {{ $card }}">
        <div class="{{ $head }}">
            <h3 class="{{ $h3 }}">Cold emails sent {{ $window }}</h3>
            <a href="{{ route('crm.campaigns.index') }}" class="text-xs font-medium text-brand-500 hover:underline">Campaigns</a>
        </div>
        <div class="w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead><tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="{{ $th }} pl-5">Campaign</th><th class="{{ $th }}">Sent</th><th class="{{ $th }}">Delivered</th><th class="{{ $th }}">Opened</th><th class="{{ $th }}">Clicked</th><th class="{{ $th }}">Replied</th><th class="{{ $th }}">Booked a demo</th><th class="{{ $th }}">Bounced</th>
                </tr></thead>
                <tbody>
                    @forelse ($em['campaigns'] as $name => $c)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="{{ $td }} pl-5 font-medium text-gray-800 dark:text-white/90">{{ $name }}</td>
                            <td class="{{ $td }}">{{ number_format($c['sent']) }}</td>
                            @foreach (['delivered', 'opened', 'clicked', 'replied', 'booked'] as $k)
                                <td class="{{ $td }}">{{ number_format($c[$k]) }} <span class="text-xs text-gray-400">{{ $pct($c[$k], $c['sent']) }}</span></td>
                            @endforeach
                            <td class="{{ $td }} {{ $c['bounced'] ? 'text-red-600' : '' }}">{{ number_format($c['bounced']) }} <span class="text-xs text-gray-400">{{ $pct($c['bounced'], $c['sent']) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No cold emails sent in this period.</td></tr>
                    @endforelse
                    @if (count($em['campaigns']) > 1)
                        <tr class="bg-gray-50 dark:bg-white/[0.02]">
                            <td class="{{ $td }} pl-5 font-semibold text-gray-800 dark:text-white/90">All</td>
                            <td class="{{ $td }} font-semibold">{{ number_format($em['sent']) }}</td>
                            @foreach (['delivered', 'opened', 'clicked', 'replied', 'booked'] as $k)
                                <td class="{{ $td }} font-semibold">{{ number_format($em[$k]) }} <span class="text-xs font-normal text-gray-400">{{ $pct($em[$k], $em['sent']) }}</span></td>
                            @endforeach
                            <td class="{{ $td }} font-semibold {{ $em['bounced'] ? 'text-red-600' : '' }}">{{ number_format($em['bounced']) }} <span class="text-xs font-normal text-gray-400">{{ $pct($em['bounced'], $em['sent']) }}</span></td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <p class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">How far the emails sent in the period have got so far. Opens need images on, so the real number is higher.</p>
    </div>

    @if ($byPerson->isNotEmpty())
        <div class="mb-6 {{ $card }}">
            <div class="{{ $head }}"><h3 class="{{ $h3 }}">By person</h3><span class="text-xs text-gray-500 dark:text-gray-400">their book now, by stage</span></div>
            <div class="w-full overflow-x-auto">
                <table class="w-full table-auto">
                    <thead><tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="{{ $th }} pl-5">Person</th>
                        @foreach (\App\Models\Company::CRM_STAGES as $label)<th class="{{ $th }}">{{ $label }}</th>@endforeach
                        <th class="{{ $th }}">Contacted → Won</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($byPerson as $r)
                            @php($reachedContacted = collect(array_slice(\App\Modules\CRM\Controllers\Admin\CrmFunnelController::PATH, 1))->sum(fn ($s) => $r['stages'][$s] ?? 0))
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="{{ $td }} pl-5"><a href="{{ route('crm.funnel.index', ['period' => $period, 'person' => $r['user']->id]) }}" class="font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">{{ $r['user']->name }}</a></td>
                                @foreach (array_keys(\App\Models\Company::CRM_STAGES) as $k)
                                    <td class="{{ $td }}">@if ($r['stages'][$k] ?? 0)<a href="{{ route('crm.index', ['owner' => $r['user']->id, 'stage' => $k]) }}" class="hover:text-blue-600 {{ $k === 'won' ? 'font-semibold text-green-700' : '' }}">{{ number_format($r['stages'][$k]) }}</a>@else<span class="text-gray-300 dark:text-gray-600">0</span>@endif</td>
                                @endforeach
                                <td class="{{ $td }}">{{ $pct($r['stages']['won'] ?? 0, $reachedContacted) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
