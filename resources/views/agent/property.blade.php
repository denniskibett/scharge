@extends('layouts.app')

@section('content')
@include('partials.modal.success-modal')
@include('partials.modal.error-modal')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $th = 'px-4 py-3 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $listed = (bool) $property->listed_publicly;
    $vacant = $units->where('status', 'vacant');
@endphp
<div>
    @include('partials.page-header', ['title' => $property->name, 'crumbs' => ['Agency properties' => route('agent.dashboard')]])
    <div class="{{ $card }} mb-6 p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                @section('page_title')
{{ $property->name }}
@endsection
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $property->location ?: 'No location yet' }} · Landlord: {{ $property->company?->name }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $vacant->count() }} vacant of {{ $units->count() }} homes
                    @if ($grant->commission_pct !== null) · Your commission: {{ rtrim(rtrim(number_format((float) $grant->commission_pct, 2), '0'), '.') }}% @endif
                </p>
            </div>
            @if ($grant->can_list)
                <form method="POST" action="{{ route('agent.properties.listing', $property) }}" class="shrink-0">
                    @csrf @method('PATCH')
                    <input type="hidden" name="listed_publicly" value="{{ $listed ? 0 : 1 }}">
                    <button @class(['inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium',
                        'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700' => $listed,
                        'bg-brand-500 text-white hover:bg-brand-600' => ! $listed])>{{ $listed ? 'Take off Open listings' : 'Show on Open listings' }}</button>
                </form>
            @endif
        </div>
        <p @class(['mt-4 rounded-lg px-3 py-2 text-sm',
            'bg-green-50 text-green-800 dark:bg-green-950/30 dark:text-green-300' => $listed,
            'bg-gray-50 text-gray-600 dark:bg-gray-900 dark:text-gray-400' => ! $listed])>
            Open listings: <span class="font-semibold">{{ $listed ? 'On' : 'Off' }}</span>.
            {{ $listed ? 'Vacant homes with a rent show on scharge Open listings.' : ($grant->can_list ? 'Switch on to advertise the vacant homes.' : 'Only the landlord can switch this on.') }}
            @if ($listed)<a href="{{ route('home.listings') }}" target="_blank" class="font-medium underline">See Open listings</a>@endif
        </p>
    </div>

    <div class="{{ $card }} mb-6 overflow-hidden">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Homes</h3></div>
        <div class="max-h-[28rem] overflow-auto">
            <table class="min-w-full">
                <thead class="sticky top-0 bg-white dark:bg-gray-900"><tr class="border-b border-gray-100 dark:border-gray-800">
                    <th class="{{ $th }}">Unit</th><th class="{{ $th }}">Type</th><th class="{{ $th }}">Status</th><th class="{{ $th }}">Rent</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($units as $u)
                        <tr>
                            <td class="px-4 py-2.5 text-sm font-medium text-gray-800 dark:text-white/90">{{ $u->unit_number }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400">{{ $u->unit_type }}{{ $u->furnishing_status ? ' · '.ucfirst($u->furnishing_status) : '' }}</td>
                            <td class="px-4 py-2.5 text-sm"><span @class(['rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-green-50 text-green-700 dark:bg-green-500/15 dark:text-green-400' => $u->status === 'vacant',
                                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => $u->status !== 'vacant'])>{{ ucfirst($u->status) }}</span></td>
                            <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">
                                @if ($u->stay_type === 'bnb' && (float) $u->bnb_nightly_rate > 0) KES {{ number_format((float) $u->bnb_nightly_rate) }} / night
                                @elseif ((float) $u->rent_amount > 0) KES {{ number_format((float) $u->rent_amount) }} / month
                                @else <span class="text-xs text-amber-600">No rent set: not advertised</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($photoUnits->isNotEmpty())
        <div class="mb-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Listing photos</h3>
            <p class="-mt-2 text-sm text-gray-500 dark:text-gray-400">Photos for a home type show on all its listings. Without photos, listings show the scharge logo.</p>
            @foreach ($photoUnits as $unit)
                <div class="{{ $card }} p-5">
                    <p class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $unit->unit_type ?: 'Homes' }}</p>
                    @include('units.partials.photos', ['unit' => $unit])
                </div>
            @endforeach
        </div>
    @endif

    @include('agent.partials.enquiries', ['enquiries' => $enquiries, 'title' => 'Renter enquiries for '.$property->name])
</div>
<script>
document.addEventListener('alpine:initialized', () => {
    @if (session('success')) window.successModal?.simple('Done', @json(session('success'))); @endif
});
</script>
@endsection
