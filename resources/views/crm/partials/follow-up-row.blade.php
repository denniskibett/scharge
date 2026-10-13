{{-- One open follow-up with its "mark done" form. Expects $followUp, $showCompany. --}}
@php
    $field = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $overdue = $followUp->is_overdue;
    $contact = $followUp->contact;
@endphp
<div @class(['rounded-xl border p-4', 'border-red-200 bg-red-50/50 dark:border-red-500/30 dark:bg-red-500/5' => $overdue, 'border-gray-200 dark:border-gray-800' => ! $overdue]) x-data="{ done: false }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="text-sm font-medium text-gray-800 dark:text-white/90">{{ \App\Modules\CRM\Models\CrmFollowUp::TYPES[$followUp->type] ?? 'Task' }}: {{ $followUp->title }}</div>
            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                <span @class(['font-semibold text-red-600' => $overdue])>{{ $followUp->due_at->format('D j M, H:i') }}@if ($overdue) · overdue {{ $followUp->due_at->diffForHumans(null, true) }}@endif</span>
                @if ($showCompany && $followUp->company) · <a href="{{ route('crm.companies.show', $followUp->company) }}" class="text-brand-500">{{ $followUp->company->name }}</a>@endif
                · {{ $followUp->assignee?->name ?? 'Unassigned' }}
            </div>
            @if ($contact)
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                    {{ $contact->name }}
                    @if ($contact->phone) · <a href="tel:{{ preg_replace('/[^\d+]/', '', $contact->phone) }}" class="text-brand-500">{{ $contact->phone }}</a> · <a href="https://wa.me/{{ preg_replace('/\D/', '', $contact->phone) }}" target="_blank" rel="noopener" class="text-brand-500">WhatsApp</a>@endif
                    @if ($contact->email) · <a href="mailto:{{ $contact->email }}" class="text-brand-500">{{ $contact->email }}</a>@endif
                </div>
            @endif
            @if ($followUp->notes)<div class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $followUp->notes }}</div>@endif
        </div>
        <button type="button" @click="done = !done" class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" x-text="done ? 'Cancel' : 'Mark done'"></button>
    </div>
    <form x-show="done" x-cloak method="POST" action="{{ route('crm.follow-ups.complete', $followUp) }}" class="mt-3 space-y-2 border-t border-gray-200 pt-3 dark:border-gray-800">
        @csrf @method('PATCH')
        <input name="outcome" required placeholder="What happened? e.g. Spoke to Jane, sending pricing" class="{{ $field }}" aria-label="Outcome">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[2fr_1fr_auto]">
            <input name="next_title" placeholder="Next step (recommended)" class="{{ $field }}" aria-label="Next step">
            <input name="next_due_at" type="datetime-local" value="{{ now()->addWeekdays(2)->setTime(10, 0)->format('Y-m-d\TH:i') }}" class="{{ $field }}" aria-label="Next step date">
            <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Done</button>
        </div>
    </form>
</div>
