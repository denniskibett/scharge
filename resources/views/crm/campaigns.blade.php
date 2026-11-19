@extends('layouts.app')

@section('content')
@php
    use App\Modules\CRM\Support\EmailTracking as T;
    $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $num = 'p-4 text-sm text-right tabular-nums whitespace-nowrap';
    $cols = ['ready' => 'Ready to email', 'unsent' => 'Unsent (no email)', 'sent' => 'Sent', 'waiting' => 'Awaiting delivery', 'delivered' => 'Delivered', 'opened' => 'Opened', 'clicked' => 'Clicked', 'replied' => 'Replied', 'booked' => 'Booked a demo', 'bounced' => 'Bounced'];
@endphp
<div>
    @include('crm.partials.nav', ['title' => 'Campaigns', 'subtitle' => 'Cold emails per property type from sales@sharet.africa: who we can email, who still has no email, and what happened to every email sent.'])

    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead><tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="{{ $th }}">Property type</th>
                    @foreach ($cols as $label)<th class="{{ $th }} text-right">{{ $label }}</th>@endforeach
                </tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($rows as $type => $r)
                        <tr>
                            <td class="p-4 whitespace-nowrap"><span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $r['prefix'] }}</span><div class="text-xs text-gray-500 dark:text-gray-400">{{ $type }}</div></td>
                            @foreach ($cols as $key => $label)
                                <td class="{{ $num }} {{ $key === 'bounced' && $r[$key] ? 'text-red-600' : 'text-gray-700 dark:text-gray-300' }}">
                                    @if ($key === 'unsent' && $r[$key])
                                        <a href="{{ route('crm.index', ['attention' => 'unsent', 'ptype' => $type]) }}" class="font-medium text-brand-500 hover:underline">{{ number_format($r[$key]) }}</a>
                                    @else
                                        {{ number_format($r[$key]) }}
                                        @if (in_array($key, ['delivered', 'opened', 'clicked', 'replied', 'booked'], true) && $r['sent'])<span class="text-xs text-gray-400">{{ round($r[$key] / $r['sent'] * 100) }}%</span>@endif
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-200 px-5 py-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
            Delivered = the prospect's mail server accepted it (checked from our mail log every 10 minutes). Opened needs images on, so the real number is higher.
            Unsent = no email on file, or their email bounced: call or WhatsApp them, or find their business email under Playbook › Verify details.
        </p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            @include('partials.table.tabs', ['tabs' => collect(['' => 'All emails'] + T::LABELS)->map(fn ($l, $k) => ['label' => $l, 'url' => route('crm.campaigns.index', array_filter(['status' => $k])), 'active' => (string) $filter === (string) $k, 'count' => (string) $filter === (string) $k ? $list->count() : null])->values()->all()])
        </div>
        <div class="w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead><tr class="border-b border-gray-200 dark:border-gray-800">
                    <th class="{{ $th }}">Company</th><th class="{{ $th }}">Sent to</th><th class="{{ $th }}">Account manager</th><th class="{{ $th }}">Sent</th><th class="{{ $th }}">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($list as $e)
                        @php($st = T::status($e))
                        <tr>
                            <td class="p-4"><a href="{{ route('crm.companies.show', $e->company_id) }}" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">{{ $e->company?->name }}</a><div class="text-xs text-gray-500 dark:text-gray-400">{{ $e->detail('ref') }}{{ $e->detail('campaign') ? ' · '.$e->detail('campaign') : ' · Playbook' }}</div></td>
                            <td class="p-4 text-sm text-gray-700 dark:text-gray-300">{{ $e->detail('to') }}</td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $e->assignee?->name ?? '-' }}</td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ ($e->done_at ?? $e->created_at)->format('D j M, H:i') }}</td>
                            <td class="p-4">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap {{ T::TONES[$st] }}">{{ T::LABELS[$st] }}</span>
                                @if ($e->detail('opens') > 1)<span class="ml-1 text-xs text-gray-500">{{ $e->detail('opens') }} opens</span>@endif
                                @if (in_array($st, ['bounced', 'deferred'], true) && $e->detail('delivery_reply'))<div class="mt-1 max-w-md text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($e->detail('delivery_reply'), 140) }}</div>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">No tracked emails yet. They appear here as soon as a campaign or Playbook email goes out.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
