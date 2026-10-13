{{-- One open CRM row (to-do or demo) with its status form. Expects $followUp, $showCompany. --}}
@php
    $field = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $overdue = $followUp->is_overdue;
    $contact = $followUp->contact;
    $isDemo = $followUp->type === 'demo';
    $statuses = $isDemo ? \App\Modules\CRM\Models\CrmFollowUp::DEMO_STATUSES : ['done' => 'Done', 'missed' => 'Missed', 'cancelled' => 'Cancelled'];
@endphp
<div @class(['rounded-xl border p-4', 'border-red-200 bg-red-50/50 dark:border-red-500/30 dark:bg-red-500/5' => $overdue, 'border-gray-200 dark:border-gray-800' => ! $overdue]) x-data="{ open: false, status: '{{ $isDemo ? ($followUp->status === 'open' ? 'scheduled' : 'done') : 'done' }}' }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="text-sm font-medium text-gray-800 dark:text-white/90">
                {{ \App\Modules\CRM\Models\CrmFollowUp::TYPES[$followUp->type] ?? 'Task' }}: {{ $followUp->title }}
                @if ($isDemo)<span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ \App\Modules\CRM\Models\CrmFollowUp::DEMO_STATUSES[$followUp->status] }}</span>@endif
            </div>
            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                @if ($followUp->due_at)<span @class(['font-semibold text-red-600' => $overdue])>{{ $followUp->due_at->format('D j M, H:i') }}@if ($overdue) · overdue {{ $followUp->due_at->diffForHumans(null, true) }}@endif</span>@endif
                @if ($showCompany && $followUp->company) · <a href="{{ route('crm.companies.show', $followUp->company) }}" class="text-brand-500">{{ $followUp->company->name }}</a>@elseif ($showCompany) · Open listings @endif
                · {{ $followUp->assignee?->name ?? 'Unassigned' }}
            </div>
            @php
                $facts = match ($followUp->type) {
                    'demo' => [$followUp->detail('project_type'), $followUp->detail('units') ? number_format($followUp->detail('units')).' units' : null],
                    'api_keys' => [$followUp->detail('use') ? 'Wants: '.$followUp->detail('use') : null],
                    'viewing' => [$followUp->detail('kind'), $followUp->detail('owner') ? 'Owner: '.$followUp->detail('owner') : null, $followUp->detail('when') ? 'When: '.$followUp->detail('when') : null],
                    default => [],
                };
            @endphp
            @if (array_filter($facts))<div class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ collect($facts)->filter()->join(' · ') }}</div>@endif
            @if ($contact)
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                    {{ $contact->name }}
                    @if ($contact->phone) · <a href="tel:{{ preg_replace('/[^\d+]/', '', $contact->phone) }}" class="text-brand-500">{{ $contact->phone }}</a> · <a href="https://wa.me/{{ preg_replace('/\D/', '', $contact->phone) }}" target="_blank" rel="noopener" class="text-brand-500">WhatsApp</a>@endif
                    @if ($contact->email) · <a href="mailto:{{ $contact->email }}" class="text-brand-500">{{ $contact->email }}</a>@endif
                </div>
            @endif
            @if ($followUp->notes)<div class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $followUp->notes }}</div>@endif
        </div>
        <button type="button" @click="open = !open" class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" x-text="open ? 'Cancel' : '{{ $isDemo ? 'Update' : 'Mark done' }}'"></button>
    </div>
    <form x-show="open" x-cloak method="POST" action="{{ route('crm.follow-ups.update', $followUp) }}" class="mt-3 space-y-2 border-t border-gray-200 pt-3 dark:border-gray-800">
        @csrf @method('PATCH')
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_2fr]">
            <select name="status" x-model="status" class="{{ $field }}" aria-label="Status">
                @foreach ($statuses as $key => $name)@continue($isDemo && $key === 'open')<option value="{{ $key }}">{{ $name }}</option>@endforeach
            </select>
            <input name="due_at" type="datetime-local" x-show="status === 'scheduled'" value="{{ $followUp->due_at?->format('Y-m-d\TH:i') }}" class="{{ $field }}" aria-label="Date and time">
            <input name="outcome" x-show="status !== 'scheduled'" placeholder="What happened? e.g. Spoke to Jane, sending pricing" class="{{ $field }}" aria-label="Outcome">
        </div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[2fr_1fr_auto]" x-show="status !== 'scheduled'">
            <input name="next_title" placeholder="Next step (recommended)" class="{{ $field }}" aria-label="Next step">
            <input name="next_due_at" type="datetime-local" value="{{ now()->addWeekdays(2)->setTime(10, 0)->format('Y-m-d\TH:i') }}" class="{{ $field }}" aria-label="Next step date">
            <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Save</button>
        </div>
        <button x-show="status === 'scheduled'" class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Save</button>
    </form>
</div>
