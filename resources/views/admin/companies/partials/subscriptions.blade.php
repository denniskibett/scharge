{{-- This company's own subscriptions (never the whole plan catalogue) and the monthly cost per property under the active
     plan: units x price per unit, less the plan discount. $subscriptions (CompanySubscription with plan), $properties. --}}
@php
    $card = 'overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $th = 'px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $td = 'px-5 py-3 text-sm text-gray-700 dark:text-gray-300';
    $active = $subscriptions->firstWhere('status', 'active');
    $plan = $active?->plan;
    $price = (float) ($plan->price_per_unit ?? 0);
    $discount = (float) ($plan->discount_percentage ?? 0);
    $perUnit = $price * (1 - $discount / 100);
    $kes = fn ($v) => 'KES '.number_format((float) $v, $v == (int) $v ? 0 : 2);
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('j M Y') : '—';
@endphp
<div class="space-y-6">
    <div class="{{ $card }}">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $company->name }}'s subscriptions</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Only the plans this company is or was subscribed to.</p>
        </div>
        @if ($subscriptions->isEmpty())
            <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">No subscription yet. Choose a plan with <span class="font-medium">Edit Company</span> or when the company onboards.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead><tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="{{ $th }}">Plan</th><th class="{{ $th }}">Price / unit</th><th class="{{ $th }}">Discount</th><th class="{{ $th }}">Billing</th>
                        <th class="{{ $th }}">Started</th><th class="{{ $th }}">Renews / ends</th><th class="{{ $th }}">Trial ends</th><th class="{{ $th }}">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($subscriptions as $s)
                            <tr>
                                <td class="{{ $td }} font-medium text-gray-800 dark:text-white/90">{{ $s->plan->name ?? 'Plan #'.$s->plan_id }}</td>
                                <td class="{{ $td }}">{{ $s->plan ? $kes($s->plan->price_per_unit).' / unit / month' : '—' }}</td>
                                <td class="{{ $td }}">{{ $s->plan && (float) $s->plan->discount_percentage > 0 ? rtrim(rtrim(number_format((float) $s->plan->discount_percentage, 2), '0'), '.').'%' : '—' }}</td>
                                <td class="{{ $td }}">{{ ucfirst((string) $s->billing_cycle) ?: '—' }}{{ $s->auto_renew ? ' · auto-renew' : '' }}</td>
                                <td class="{{ $td }}">{{ $date($s->starts_at) }}</td>
                                <td class="{{ $td }}">{{ $date($s->ends_at) }}</td>
                                <td class="{{ $td }}">{{ $date($s->trial_ends_at) }}</td>
                                <td class="{{ $td }}"><span @class(['rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-green-50 text-green-700 dark:bg-green-500/15 dark:text-green-400' => $s->status === 'active',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' => in_array($s->status, ['trial', 'trialing', 'pending', 'past_due'], true),
                                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => ! in_array($s->status, ['active', 'trial', 'trialing', 'pending', 'past_due'], true)])>{{ ucfirst(str_replace('_', ' ', (string) $s->status)) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="{{ $card }}">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Per property</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if ($plan)
                    Under {{ $plan->name }}: {{ $kes($price) }} per unit a month{{ $discount > 0 ? ', less '.rtrim(rtrim(number_format($discount, 2), '0'), '.').'%' : '' }}.
                @else
                    No active plan, so nothing is billed yet.
                @endif
            </p>
        </div>
        @if ($properties->isEmpty())
            <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">This company has no properties yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead><tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="{{ $th }}">Property</th><th class="{{ $th }}">Units</th><th class="{{ $th }}">Occupied</th><th class="{{ $th }} text-right">Per month</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($properties as $p)
                            <tr>
                                <td class="{{ $td }}"><span class="font-medium text-gray-800 dark:text-white/90">{{ $p->name }}</span>@if ($p->location)<span class="block text-xs text-gray-500 dark:text-gray-400">{{ $p->location }}</span>@endif
                                    @if (! empty($p->hosted))<span class="block text-xs font-medium text-brand-500">Hosts {{ str_replace(',', ', ', $p->hosted) }} here (not the building's owner)</span>@endif</td>
                                <td class="{{ $td }}">{{ number_format((int) $p->units) }}</td>
                                <td class="{{ $td }}">{{ number_format((int) $p->occupied) }}</td>
                                <td class="{{ $td }} text-right font-medium">{{ $plan ? $kes((int) $p->units * $perUnit) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($plan)
                        <tfoot><tr class="border-t border-gray-200 dark:border-gray-800">
                            <td class="{{ $td }} font-semibold text-gray-800 dark:text-white/90">Total</td>
                            <td class="{{ $td }} font-semibold">{{ number_format((int) $properties->sum('units')) }}</td>
                            <td class="{{ $td }}">{{ number_format((int) $properties->sum('occupied')) }}</td>
                            <td class="{{ $td }} text-right font-semibold text-gray-800 dark:text-white/90">{{ $kes((int) $properties->sum('units') * $perUnit) }}</td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        @endif
    </div>
</div>
