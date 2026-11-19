{{-- Renter viewing / booking requests from Open listings. $enquiries (CrmFollowUp type viewing), $title --}}
@php $th = 'px-4 py-3 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400'; @endphp
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Renters who asked to view or book on Open listings. You and the landlord both get an SMS.</p>
    </div>
    @if ($enquiries->isEmpty())
        <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">No enquiries yet. They come in once a property is on Open listings.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead><tr class="border-b border-gray-100 dark:border-gray-800">
                    <th class="{{ $th }}">When</th><th class="{{ $th }}">Renter</th><th class="{{ $th }}">Wants</th><th class="{{ $th }}">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($enquiries as $e)
                        @php $phone = $e->contact?->phone; $wa = $phone ? 'https://wa.me/'.preg_replace('/\D/', '', $phone) : null; @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $e->created_at?->format('j M, H:i') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $e->contact?->name ?? 'Renter' }}</span>
                                @if ($phone)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400"><a href="tel:{{ $phone }}" class="hover:underline">{{ $phone }}</a> · <a href="{{ $wa }}" target="_blank" rel="noopener" class="text-green-700 hover:underline dark:text-green-400">WhatsApp</a></span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $e->title }}@if ($e->detail('when'))<span class="block text-xs text-gray-500">Preferred: {{ $e->detail('when') }}</span>@endif</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ ucfirst($e->detail('stage') ?? $e->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
