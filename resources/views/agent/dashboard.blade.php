@extends('layouts.app')

@section('content')
@include('partials.modal.success-modal')
@include('partials.modal.error-modal')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $th = 'px-4 py-3 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $vacant = $grants->sum(fn ($g) => (int) ($units[$g->property_id]->vacant ?? 0));
    $listed = $grants->filter(fn ($g) => $g->property->listed_publicly)->count();
@endphp
<div>
    <div class="mb-6">
        @section('page_title')
{{ $agency?->name ?? 'My agency' }}
@endsection
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The properties landlords gave your agency to market and let. Put vacant homes on Open listings, add photos and answer renters.</p>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['Properties', $grants->count()], ['Vacant homes', $vacant], ['On Open listings', $listed], ['Open enquiries', $openEnquiries]] as [$label, $value])
            <div class="{{ $card }} p-4">
                <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($value) }}</span>
            </div>
        @endforeach
    </div>

    <div class="{{ $card }} mb-6 overflow-hidden">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Properties</h3>
        </div>
        @if ($grants->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No landlord has given your agency a property yet. Ask them to open the property in scharge and add
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $agency?->name }}</span> under <span class="font-medium">Agents</span>
                (they can also use your email or phone).
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead><tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="{{ $th }}">Property</th><th class="{{ $th }}">Landlord</th><th class="{{ $th }}">Vacant</th>
                        <th class="{{ $th }}">Commission</th><th class="{{ $th }}">Open listings</th><th class="{{ $th }}"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($grants as $g)
                            @php $u = $units[$g->property_id] ?? null; @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('agent.properties.show', $g->property) }}" class="font-medium text-gray-800 hover:underline dark:text-white/90">{{ $g->property->name }}</a>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $g->property->location }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $g->property->company?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ (int) ($u->vacant ?? 0) }} of {{ (int) ($u->total ?? 0) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $g->commission_pct !== null ? rtrim(rtrim(number_format((float) $g->commission_pct, 2), '0'), '.').'%' : '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($g->can_list)
                                        <form method="POST" action="{{ route('agent.properties.listing', $g->property) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="listed_publicly" value="{{ $g->property->listed_publicly ? 0 : 1 }}">
                                            <button @class(['rounded-full px-3 py-1 text-xs font-medium',
                                                'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-500/15 dark:text-green-400' => $g->property->listed_publicly,
                                                'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400' => ! $g->property->listed_publicly])
                                                title="{{ $g->property->listed_publicly ? 'Click to take it off' : 'Click to show its vacant homes' }}">{{ $g->property->listed_publicly ? 'On' : 'Off' }}</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Landlord only</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('agent.properties.show', $g->property) }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('agent.partials.enquiries', ['enquiries' => $enquiries, 'title' => 'Latest renter enquiries'])

    @if ($isAdmin)
        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">Add your agents in <a href="{{ route('team.index') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Team</a> with the <span class="font-medium">Agent</span> role. They see the same properties.</p>
    @endif
</div>
<script>
document.addEventListener('alpine:initialized', () => {
    @if (session('success')) window.successModal?.simple('Done', @json(session('success'))); @endif
    @if ($errors->any()) window.errorModal?.show('Not done', '', @json($errors->all())); @endif
});
</script>
@endsection
