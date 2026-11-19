@extends('layouts.app')

@section('content')
@php
    $th = 'px-3 py-3 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $td = 'px-3 py-3 text-sm whitespace-nowrap text-gray-700 dark:text-gray-300';
    $ago = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->diffForHumans(short: true) : 'never';
    $ts = fn ($epoch) => $epoch ? \Illuminate\Support\Carbon::createFromTimestamp($epoch)->diffForHumans(short: true) : '-';
    $icons = ['note' => 'pencil-square', 'call' => 'phone', 'email' => 'envelope', 'whatsapp' => 'chat-bubble-left-ellipsis', 'meeting' => 'user-group', 'demo' => 'calendar-days', 'stage' => 'arrow-right', 'feedback' => 'star'];
    $mailCells = function ($m) use ($ts) {
        if (! $m) return ['-', '-', '-', '-'];
        if (! array_key_exists('received', $m)) return ['<span class="text-gray-400">no data yet</span>', '', '', ''];
        return [
            number_format($m['received']).($m['received_today'] ? ' <span class="text-xs text-gray-400">('.$m['received_today'].' today)</span>' : ''),
            number_format($m['sent']).($m['sent_today'] ? ' <span class="text-xs text-gray-400">('.$m['sent_today'].' today)</span>' : ''),
            $m['unread'] ? '<span class="font-medium text-amber-600">'.number_format($m['unread']).'</span>' : '0',
            'in '.$ts($m['last_received'] ?? null).' · out '.$ts($m['last_sent'] ?? null),
        ];
    };
@endphp
<div>
    @include('crm.partials.nav', ['title' => 'Team', 'subtitle' => 'What each account manager is doing: CRM work, replies waiting on them, and their sharet.africa work mailbox.'])

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @include('partials.table.tabs', ['tabs' => collect(\App\Modules\CRM\Controllers\Admin\CrmTeamController::PERIODS)->map(fn ($l, $k) => ['label' => $l, 'url' => route('crm.team.index', ['period' => $k]), 'active' => $period === $k])->values()->all()])
    </div>

    @php
        $pct = fn ($n) => $funnel['sent'] ? ' <span class="text-xs font-normal text-gray-400">'.round($n / $funnel['sent'] * 100).'%</span>' : '';
        $steps = [['Sent', $funnel['sent'], ''], ['Delivered', $funnel['delivered'], $pct($funnel['delivered'])], ['Opened', $funnel['opened'], $pct($funnel['opened'])], ['Clicked', $funnel['clicked'], $pct($funnel['clicked'])], ['Replied', $funnel['replied'], $pct($funnel['replied'])], ['Booked a demo', $funnel['booked'], $pct($funnel['booked'])], ['Bounced', $funnel['bounced'], $pct($funnel['bounced'])]];
        $window = $recap->from->copy()->tz(config('app.schedule_timezone', 'Africa/Nairobi'))->format('D j M').($period === 'yesterday' ? '' : ' – '.$recap->to->copy()->tz(config('app.schedule_timezone', 'Africa/Nairobi'))->format('D j M'));
    @endphp
    @if ($totals)
        @include('partials.card.pills', ['pills' => [
            ['label' => 'New prospects', 'value' => number_format($totals['new_prospects']), 'url' => null, 'tone' => 'gray'],
            ['label' => 'Replies in', 'value' => $totals['replies'], 'url' => null, 'tone' => 'gray'],
            ['label' => 'Replies waiting now', 'value' => $totals['replies_waiting'], 'url' => route('crm.index', ['attention' => 'replied']), 'tone' => $totals['replies_waiting'] ? 'red' : 'gray'],
            ['label' => 'Demo requests', 'value' => $totals['demo_requests'], 'url' => route('crm.requests.index'), 'tone' => $totals['demo_requests'] ? 'green' : 'gray'],
            ['label' => 'Overdue follow-ups now', 'value' => $totals['overdue'], 'url' => route('crm.follow-ups.index', ['view' => 'overdue']), 'tone' => $totals['overdue'] ? 'amber' : 'gray'],
            ['label' => 'Ready to email', 'value' => collect($totals['ready'])->map(fn ($n, $t) => $t.' '.$n)->join(' · '), 'url' => route('crm.campaigns.index'), 'tone' => 'gray'],
        ]])
    @endif

    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Cold emails sent {{ $window }}</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ collect($funnel['by_campaign'])->map(fn ($n, $c) => $c.' '.$n)->join(' · ') ?: 'none sent' }}</span>
            </div>
            <table class="w-full table-auto"><tbody>
                @foreach ($steps as [$label, $n, $p])
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-5 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $label }}</td>
                        <td class="px-5 py-2 text-right text-sm font-semibold {{ $label === 'Bounced' && $n ? 'text-red-600' : 'text-gray-800 dark:text-white/90' }}">{{ number_format($n) }}{!! $p !!}</td>
                        <td class="px-5 py-2" style="width:50%"><div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full {{ $label === 'Bounced' ? 'bg-red-500' : 'bg-brand-500' }}" style="width: {{ $funnel['sent'] ? max(1, round($n / $funnel['sent'] * 100)) : 0 }}%"></div></div></td>
                    </tr>
                @endforeach
            </tbody></table>
            <p class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">How far those emails have got so far. In the same days, across all emails: {{ $funnel['events']['opened'] }} opens, {{ $funnel['events']['clicked'] }} clicks, {{ $funnel['events']['replied'] }} replies, {{ $funnel['events']['booked'] }} demos booked. Opens need images on, so the real number is higher; some mail apps open automatically.</p>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800"><h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Hot leads: opened, no answer yet. Call these first</h3></div>
            <ul class="max-h-96 overflow-y-auto">
                @forelse ($hot as $e)
                    <li class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-2.5 dark:border-gray-800">
                        <div class="min-w-0">
                            <a href="{{ route('crm.companies.show', $e->company_id) }}" class="block truncate text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">{{ $e->company->name }}</a>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $e->company->owner?->name ?? 'No owner' }}{{ $e->company->phone ? ' · '.$e->company->phone : ' · no phone' }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($e->detail('clicked_at'))<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">clicked</span>@endif
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ max(1, (int) $e->detail('opens')) }} open{{ (int) $e->detail('opens') > 1 ? 's' : '' }} · {{ $ago($e->detail('clicked_at') ?? $e->detail('opened_at')) }}</span>
                            @if ($e->company->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $e->company->phone) }}" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-brand-500 hover:bg-gray-100 dark:hover:bg-white/5" aria-label="Call {{ $e->company->name }}"><x-icon name="phone" class="h-4 w-4" /></a>@endif
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No opens waiting for a call.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800"><h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">CRM work · {{ \App\Modules\CRM\Controllers\Admin\CrmTeamController::PERIODS[$period] }}</h3></div>
        <div class="w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead><tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="{{ $th }}">Account manager</th><th class="{{ $th }}">Prospects (open)</th><th class="{{ $th }}">Cold emails</th><th class="{{ $th }}">Opened</th><th class="{{ $th }}">Replies in</th><th class="{{ $th }}">Waiting on them</th><th class="{{ $th }}">Answered</th><th class="{{ $th }}">Calls · WhatsApp · Meetings</th><th class="{{ $th }}">Stage moves</th><th class="{{ $th }}">Overdue</th><th class="{{ $th }}">Last own action</th>
                </tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="{{ $td }}"><span class="font-medium text-gray-800 dark:text-white/90">{{ $r['user']->name }}</span><div class="text-xs text-gray-400">{{ $r['user']->email }}</div></td>
                            <td class="{{ $td }}"><a href="{{ route('crm.index', ['owner' => $r['user']->id]) }}" class="hover:text-blue-600">{{ number_format($r['owned']) }}</a> <span class="text-xs text-gray-400">({{ number_format($r['open']) }})</span></td>
                            <td class="{{ $td }}">{{ number_format($r['cold']) }}</td>
                            <td class="{{ $td }}">{{ number_format($r['opened']) }}</td>
                            <td class="{{ $td }}">{{ number_format($r['replies']) }}</td>
                            <td class="{{ $td }}">@if ($r['waiting'])<a href="{{ route('crm.index', ['attention' => 'replied', 'owner' => $r['user']->id]) }}" class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 hover:bg-red-100 dark:text-red-200"><span class="h-2 w-2 rounded-full bg-red-500"></span>{{ $r['waiting'] }}</a>@else 0 @endif</td>
                            <td class="{{ $td }}">{{ number_format($r['answered']) }}</td>
                            <td class="{{ $td }}">{{ $r['calls'] }} · {{ $r['whatsapp'] }} · {{ $r['meetings'] }}</td>
                            <td class="{{ $td }}">{{ $r['stages'] }}</td>
                            <td class="{{ $td }} {{ $r['overdue'] ? 'font-medium text-red-600' : '' }}">{{ $r['overdue'] }}</td>
                            <td class="{{ $td }}">{{ $ago($r['last_active']) }}<div class="text-xs {{ $r['logged_in'] ? 'text-green-600' : 'text-gray-400' }}">signed in {{ $ago($r['last_login']) }}</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-3 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Mailboxes · last 7 days</h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">Counts only, refreshed every 5 minutes{{ $statsAt ? ' (last '.$statsAt->diffForHumans().')' : '' }}. <a href="{{ route('crm.inbox.index') }}" class="font-medium text-brand-500 hover:underline">Read the mail in Inbox</a></span>
        </div>
        <div class="w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead><tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="{{ $th }}">Mailbox</th><th class="{{ $th }}">Received</th><th class="{{ $th }}">Sent</th><th class="{{ $th }}">Unread</th><th class="{{ $th }}">Last mail</th>
                </tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        @php($c = $mailCells($r['mailbox']))
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="{{ $td }}"><span class="font-medium text-gray-800 dark:text-white/90">{{ $r['mailbox']['address'] ?? 'No work mailbox' }}</span><div class="text-xs text-gray-400">{{ $r['user']->name }}</div></td>
                            <td class="{{ $td }}">{!! $c[0] !!}</td><td class="{{ $td }}">{!! $c[1] !!}</td><td class="{{ $td }}">{!! $c[2] !!}</td><td class="{{ $td }} text-xs">{!! $c[3] !!}</td>
                        </tr>
                    @endforeach
                    @foreach ($shared as $m)
                        @php($c = $mailCells($m))
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="{{ $td }}"><span class="font-medium text-gray-800 dark:text-white/90">{{ $m['address'] }}</span><div class="text-xs text-gray-400">Shared</div></td>
                            <td class="{{ $td }}">{!! $c[0] !!}</td><td class="{{ $td }}">{!! $c[1] !!}</td><td class="{{ $td }}">{!! $c[2] !!}</td><td class="{{ $td }} text-xs">{!! $c[3] !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-200 px-5 py-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">Sent counts mail sent from the mailbox itself (webmail, phone or CRM › Inbox). Cold emails and CRM answers go out from sales@ and are counted in the table above. Personal Gmail addresses can't be seen here.</p>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800"><h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Replies waiting for an answer</h3></div>
            <ul>
                @forelse ($waitingList as $c)
                    <li class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('crm.companies.show', $c) }}#reply" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">{{ $c->name }}</a>
                            <span class="text-xs text-gray-400 whitespace-nowrap">{{ $ago($c->profile('reply.at')) }} · {{ $c->owner?->name ?? 'No owner' }}</span>
                        </div>
                        <p class="truncate text-xs text-red-700 dark:text-red-200">“{{ $c->profile('reply.snippet') }}”</p>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No replies waiting.</li>
                @endforelse
            </ul>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800"><h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Latest activity</h3></div>
            <ul>
                @forelse ($feed as $f)
                    <li class="flex items-start gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                        <x-icon name="{{ $icons[$f->type] ?? 'pencil-square' }}" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-gray-800 dark:text-white/90">{{ $f->title }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $f->author?->name }} · @if ($f->company)<a href="{{ route('crm.companies.show', $f->company_id) }}" class="hover:text-blue-600">{{ $f->company->name }}</a> · @endif{{ $ago($f->created_at) }}</p>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nothing logged in this period.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
