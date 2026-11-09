@extends('layouts.app')

@section('title', 'Security Logs')

@section('content')
@include('partials.card.security')
@php
  $tz = 'Africa/Nairobi';
  $tabUrl = fn ($key) => request()->fullUrlWithQuery(['status' => $key === 'all' ? null : $key, 'page' => null]);
  $tabs = [
    ['label' => 'All', 'url' => $tabUrl('all'), 'active' => $status === 'all', 'count' => $counts['all']],
    ['label' => 'Inside', 'url' => $tabUrl('inside'), 'active' => $status === 'inside', 'count' => $counts['inside'], 'tone' => 'green'],
    ['label' => 'Checked out', 'url' => $tabUrl('out'), 'active' => $status === 'out', 'count' => $counts['out']],
    ['label' => 'Pending', 'url' => $tabUrl('pending'), 'active' => $status === 'pending', 'count' => $counts['pending'], 'tone' => 'amber'],
  ];
  $initials = fn ($n) => collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
  $menuItem = 'text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300';
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
  <form method="GET" action="{{ route('security.logs.index') }}" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
    @if ($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
    @include('partials.table.tabs', ['tabs' => $tabs])

    <div class="flex flex-wrap items-center gap-3 lg:justify-end">
      @include('partials.table.select', ['name' => 'property_id', 'aria' => 'Property', 'selected' => request('property_id'),
        'options' => ['' => 'All Properties'] + $properties->pluck('name', 'id')->all()])
      <input type="date" name="date" value="{{ request('date') }}" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" aria-label="Date"
        class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
      @if (request()->hasAny(['date', 'property_id', 'q']) && (request('date') || request('property_id') || request('q')))
        <a href="{{ route('security.logs.index', $status !== 'all' ? ['status' => $status] : []) }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          Clear
        </a>
      @endif
      @include('partials.table.search', ['value' => request('q'), 'placeholder' => 'Search name, phone or plate…'])
    </div>
  </form>

  @if (isset($error))
    <div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $error }}</div>
  @endif

  <div class="w-full overflow-x-auto">
    <table class="data-table w-full table-auto">
      <thead>
        <tr class="border-b border-gray-200 dark:border-gray-800">
          @foreach (['Visitor', 'Unit', 'Vehicle', 'Check in', 'Check out', 'Status'] as $h)
            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $h }}</th>
          @endforeach
          <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
        @forelse ($logs as $log)
          @php
            $isIn = $log->status === 'approved' && ! $log->exit_time;
            $isOut = $log->status === 'completed' || $log->exit_time;
          @endphp
          <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
            <td class="p-4 whitespace-nowrap">
              <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                  <span class="text-sm font-medium text-purple-600">{{ $initials($log->visitor_name_snapshot ?? $log->visitor?->name) }}</span>
                </div>
                <div>
                  <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $log->visitor_name_snapshot ?? $log->visitor?->name ?? 'Unknown' }}</span>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ $log->visitor_phone_snapshot ?? '-' }}</p>
                </div>
              </div>
            </td>
            <td class="p-4 whitespace-nowrap">
              @if ($log->unit)
                <a href="{{ url('/units/'.$log->unit_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">{{ $log->unit->unit_number }}</a>
              @else
                <span class="text-sm text-gray-500">-</span>
              @endif
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ $log->property->name ?? '' }}</p>
            </td>
            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
              @if ($log->vehicle_registration_snapshot)
                <span class="font-medium text-gray-800 dark:text-white/90">{{ $log->vehicle_registration_snapshot }}</span>
                @if ($log->vehicle_type || $log->vehicle_model || $log->vehicle_color)<p class="text-xs">{{ trim($log->vehicle_color.' '.$log->vehicle_type.' '.$log->vehicle_model) }}</p>@endif
              @else
                -
              @endif
            </td>
            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $log->access_time?->timezone($tz)->format('M d, Y H:i') ?? '-' }}</td>
            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $log->exit_time?->timezone($tz)->format('M d, Y H:i') ?? '-' }}</td>
            <td class="p-4 whitespace-nowrap">
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $isIn ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($log->status === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300') }}">
                {{ $isIn ? 'Inside' : ($isOut ? 'Checked out' : ucfirst($log->status)) }}
              </span>
            </td>
            <td class="p-4 whitespace-nowrap text-right">
              <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                  <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                </button>
                <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                  @if ($log->gate_pass_id)
                    <a href="{{ route('gate.passes.show', $log->gate_pass_id) }}" class="{{ $menuItem }}">Open pass</a>
                  @endif
                  <button type="button" onclick="viewLog({{ $log->id }})" @click="open = false" class="{{ $menuItem }}">View details</button>
                  @if ($isIn)
                    <button type="button" onclick="checkOut({{ $log->id }})" @click="open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Check out</button>
                  @endif
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="p-4 text-center">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No gate entries found</h3>
              @if (request('date') || request('property_id') || request('q'))<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters or search.</p>@endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @include('partials.table.pager', ['paginator' => $logs])
</div>

<!-- View Log Modal -->
<div id="logModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="closeModal()"></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Log Details</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="logDetails" class="mt-4 space-y-3">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</div>

<script>
function checkOut(logId) {
    if (!confirm('Check out this visitor?')) return;
    
    fetch('/security/api/check-out', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ visitor_id: logId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Visitor checked out successfully!');
            (window.schargeRefresh || (() => location.reload()))();
        } else {
            alert('' + (data.message || 'Failed to check out visitor'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to check out visitor');
    });
}

function viewLog(logId) {
    fetch(`/security/logs/${logId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const log = data.log;
                const details = document.getElementById('logDetails');
                details.innerHTML = `
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-gray-500">Visitor</label>
                            <p class="text-sm font-medium text-gray-800">${log.person_name || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Phone</label>
                            <p class="text-sm text-gray-600">${log.visitor_phone || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Unit</label>
                            <p class="text-sm text-gray-600">${log.unit_number || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Purpose</label>
                            <p class="text-sm text-gray-600">${log.purpose || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Check In</label>
                            <p class="text-sm text-gray-600">${log.datetime_formatted || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Status</label>
                            <p class="text-sm font-medium ${log.status === 'approved' ? 'text-green-600' : 'text-gray-600'}">${log.status_label || log.status}</p>
                        </div>
                        ${log.vehicle ? `
                        <div class="col-span-2">
                            <label class="text-xs font-medium text-gray-500">Vehicle Registration</label>
                            <p class="text-sm text-gray-600">${log.vehicle}</p>
                        </div>
                        ` : ''}
                        ${log.vehicle_type ? `
                        <div>
                            <label class="text-xs font-medium text-gray-500">Vehicle Type</label>
                            <p class="text-sm text-gray-600">${log.vehicle_type}</p>
                        </div>
                        ` : ''}
                        ${log.vehicle_model ? `
                        <div>
                            <label class="text-xs font-medium text-gray-500">Vehicle Model</label>
                            <p class="text-sm text-gray-600">${log.vehicle_model}</p>
                        </div>
                        ` : ''}
                        ${log.vehicle_color ? `
                        <div>
                            <label class="text-xs font-medium text-gray-500">Vehicle Color</label>
                            <p class="text-sm text-gray-600">${log.vehicle_color}</p>
                        </div>
                        ` : ''}
                        ${log.notes ? `
                        <div class="col-span-2">
                            <label class="text-xs font-medium text-gray-500">Notes</label>
                            <p class="text-sm text-gray-600">${log.notes}</p>
                        </div>
                        ` : ''}
                    </div>
                `;
                document.getElementById('logModal').classList.remove('hidden');
            }
        })
        .catch(error => console.error('Error:', error));
}

function closeModal() {
    document.getElementById('logModal').classList.add('hidden');
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
@endsection