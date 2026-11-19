{{-- Reply alert: the prospect answered our email. Answer it in the same thread, or mark it handled; both can move the stage. --}}
@php
    $reply = $company->profile('reply') ?? [];
    $row = ! empty($reply['follow_up_id']) ? \App\Modules\CRM\Models\CrmFollowUp::find($reply['follow_up_id']) : null;
    $who = ($reply['name'] ?? '') ?: ($reply['from'] ?? 'They');
    $first = trim(explode(' ', (string) ($reply['name'] ?? ''))[0] ?? '');
    $stages = \App\Modules\CRM\Controllers\Admin\CrmReplyController::stages();
    $field = 'h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400';
    $stageSelect = function ($id) use ($stages, $company, $field) {
        $html = '<select id="'.$id.'" name="stage" class="'.$field.'"><option value="">Keep stage ('.e(\App\Models\Company::CRM_STAGES[$company->crm_stage] ?? 'none').')</option>';
        foreach ($stages as $k => $l) {
            if ($k !== $company->crm_stage) {
                $html .= '<option value="'.$k.'">Move to '.e($l).'</option>';
            }
        }

        return $html.'</select>';
    };
@endphp
<div id="reply" x-data="{ tab: 'answer' }" class="mb-6 scroll-mt-24 rounded-2xl border border-red-200 border-l-4 border-l-red-500 bg-white p-5 dark:border-red-900 dark:bg-white/[0.03]">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="flex items-center gap-2 text-sm font-semibold text-red-700 dark:text-red-200"><span class="h-2 w-2 animate-pulse rounded-full bg-red-500"></span> {{ $who }} replied {{ ! empty($reply['at']) ? \Illuminate\Support\Carbon::parse($reply['at'])->diffForHumans() : '' }}: waiting for an answer</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $reply['from'] ?? '' }} · “{{ $reply['subject'] ?? '(no subject)' }}”{{ ! empty($reply['mailbox']) ? ' · received at '.$reply['mailbox'] : '' }}</p>
            @if (! empty($reply['opt_out']))<p class="mt-1 text-xs font-medium text-red-700 dark:text-red-200">They asked us to stop emailing, so campaigns now skip them. Answer only to confirm, or mark it handled.</p>@endif
        </div>
        <div class="flex gap-1 rounded-lg bg-gray-100 p-1 text-xs dark:bg-gray-900">
            <button type="button" @click="tab = 'answer'" :class="tab === 'answer' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500'" class="rounded-md px-3 py-1.5 font-medium">Answer</button>
            <button type="button" @click="tab = 'handled'" :class="tab === 'handled' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500'" class="rounded-md px-3 py-1.5 font-medium">Mark handled</button>
        </div>
    </div>

    <div class="mt-3 max-h-60 overflow-y-auto whitespace-pre-line rounded-xl bg-gray-50 p-3 text-sm text-gray-800 dark:bg-white/[0.03] dark:text-white/90">{{ $row?->notes ?? ($reply['snippet'] ?? '') }}</div>

    <form x-show="tab === 'answer'" method="POST" action="{{ route('crm.companies.reply.answer', $company) }}" class="mt-4 space-y-3">
        @csrf
        <div><label class="{{ $label }}" for="reply-body">Your answer (goes from {{ \App\Modules\CRM\Support\Playbook::fromFor($company) }} in the same thread, signed {{ $company->owner?->name ?? auth()->user()->name }}; their replies come back to you)</label>
            <textarea id="reply-body" name="body" rows="6" required class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('body', 'Hi '.($first ?: 'there').",\n\n") }}</textarea></div>
        <div class="flex flex-wrap items-end gap-3">
            <div style="min-width:220px"><label class="{{ $label }}" for="reply-stage">Stage</label>{!! $stageSelect('reply-stage') !!}</div>
            <button class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600"><x-icon name="paper-airplane" class="h-4 w-4" /> Send answer</button>
        </div>
    </form>

    <form x-show="tab === 'handled'" x-cloak method="POST" action="{{ route('crm.companies.reply.handled', $company) }}" class="mt-4 space-y-3">
        @csrf
        <p class="text-xs text-gray-500 dark:text-gray-400">Use this when you answered by phone, WhatsApp or from your own mailbox.</p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div><label class="{{ $label }}" for="handled-stage">Stage</label>{!! $stageSelect('handled-stage') !!}</div>
            <div><label class="{{ $label }}" for="handled-note">Note for the thread (optional)</label><input id="handled-note" name="note" maxlength="1000" placeholder="e.g. Called back, demo on Tuesday" class="{{ $field }}"></div>
        </div>
        <button class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Mark handled</button>
    </form>
</div>
