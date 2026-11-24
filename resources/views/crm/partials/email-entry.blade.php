{{--
    One email on a lead's activity, in the TailAdmin inbox style: a compact inbox row (sender, subject + preview, time)
    that opens into the email-details layout (subject, from / to, date, body, engagement, files, reply).
    Expects $row (CrmFollowUp, type email) and $company. Shares its look with CRM › Inbox.
--}}
@php
    $in = $row->detail('direction') === 'in';
    $auto = (bool) $row->detail('auto');
    $subject = (string) ($row->detail('subject') ?: preg_replace('/^(Cold email|Email sent again|Answered [^:]+):\s*/', '', (string) $row->title));
    $fromAddr = $in ? (string) $row->detail('from') : (string) ($row->detail('from') ?: \App\Modules\CRM\Support\Playbook::fromFor($company));
    $fromName = $in ? ((string) $row->detail('from_name') ?: $fromAddr) : ($row->author?->name ?? $row->assignee?->name ?? 'scharge');
    $to = $in ? (string) ($row->detail('mailbox') ?: 'us') : (string) $row->detail('to');
    $cc = collect((array) $row->detail('cc'))->filter()->join(', ');
    $when = $row->done_at ?? $row->created_at;
    $initials = \Illuminate\Support\Str::of($in ? $fromName : ($to ?: $fromName))->replaceMatches('/[^\p{L}\s]/u', ' ')->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: '@';
    $preview = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $row->notes), 140);
    $tracked = ! $in && $row->detail('track');
    $status = $tracked ? \App\Modules\CRM\Support\EmailTracking::status($row) : null;
    $pending = $in && $company->profile('reply_pending') && (int) $company->profile('reply.follow_up_id') === $row->id;
    $tag = match (true) {
        $in && $auto => ['Automatic reply', 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300'],
        $in => ['Reply', 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
        (bool) $row->detail('resend') => ['Sent again', 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'],
        str_starts_with((string) $row->title, 'Cold email') => ['Cold email', 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'],
        default => ['Sent', 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'],
    };
@endphp
<div x-data="{ open: {{ $pending ? 'true' : 'false' }} }" class="rounded-xl border border-gray-200 dark:border-gray-800" :class="open && 'shadow-theme-xs'">
    {{-- Inbox row --}}
    <button type="button" @click="open = !open" :aria-expanded="open" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/[0.03]">
        <span aria-hidden="true" @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold', 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $in, 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' => ! $in])>{{ $initials }}</span>
        <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2">
                <span @class(['truncate text-sm text-gray-800 dark:text-white/90', 'font-semibold' => $pending, 'font-medium' => ! $pending])>{{ $in ? $fromName : 'To '.($to ?: 'them') }}</span>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $tag[1] }}">{{ $tag[0] }}</span>
                @if ($status)<span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ \App\Modules\CRM\Support\EmailTracking::TONES[$status] }}" title="{{ $row->detail('delivery_reply') }}">{{ \App\Modules\CRM\Support\EmailTracking::LABELS[$status] }}</span>@endif
            </span>
            <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400"><span class="text-gray-700 dark:text-gray-300">{{ $subject ?: '(no subject)' }}</span>@if ($preview) — {{ $preview }}@endif</span>
        </span>
        <span class="shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">{{ $when->isToday() ? $when->format('H:i') : $when->format('j M') }}</span>
    </button>

    {{-- Email details --}}
    <article x-show="open" x-cloak class="border-t border-gray-200 dark:border-gray-800">
        <header class="p-5">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $subject ?: '(no subject)' }}</h3>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-3 text-sm">
                <div class="flex min-w-0 items-start gap-3">
                    <span aria-hidden="true" @class(['flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold', 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $in, 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' => ! $in])>{{ $in ? $initials : \Illuminate\Support\Str::of($fromName)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
                    <div class="min-w-0 text-gray-600 dark:text-gray-400">
                        <div><span class="font-medium text-gray-800 dark:text-white/90">{{ $fromName }}</span>@if ($fromAddr && $fromAddr !== $fromName) &lt;{{ $fromAddr }}&gt;@endif</div>
                        <div class="truncate">To: {{ $to ?: '—' }}@if ($cc) · Cc: {{ $cc }}@endif</div>
                    </div>
                </div>
                <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $when->format('D j M Y, H:i') }}</span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($pending)
                    <a href="#reply" class="inline-flex h-9 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Reply</a>
                @elseif ($in && $fromAddr)
                    <a href="mailto:{{ $fromAddr }}?subject={{ rawurlencode(preg_match('/^re:/i', $subject) ? $subject : 'Re: '.$subject) }}" class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Reply by email</a>
                @endif
                <a href="{{ route('crm.inbox.index') }}" class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Open Inbox</a>
            </div>
        </header>
        <div class="border-t border-gray-200 bg-white px-5 py-4 text-sm leading-6 whitespace-pre-line text-gray-800 dark:border-gray-800 dark:bg-transparent dark:text-white/90">{{ trim((string) $row->notes) ?: 'No text was saved for this email.' }}</div>
        @if ($tracked || $row->detail('pdf') || $row->detail('file'))
            <footer class="flex flex-wrap items-center gap-2 border-t border-gray-200 px-5 py-3 dark:border-gray-800">
                @if ($tracked)
                    @include('crm.partials.engagement', ['e' => \App\Modules\CRM\Support\EmailEngagement::summary($row->id), 'events' => \App\Modules\CRM\Support\EmailEngagement::events($row->id)])
                @endif
                @if ($row->detail('file'))<a href="{{ route('crm.follow-ups.file', $row) }}" data-no-spa class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"><x-icon name="document-text" class="h-3.5 w-3.5" /> {{ $row->detail('file_name') ?: 'Attachment' }}</a>@endif
                @if ($row->detail('pdf'))<a href="{{ route('crm.follow-ups.pdf', $row) }}" target="_blank" rel="noopener" data-no-spa x-data @click="if ($event.metaKey || $event.ctrlKey || $event.shiftKey) return; $event.preventDefault(); $dispatch('open-pdf', { url: $el.href, title: $el.textContent.trim() })" class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"><x-icon name="document-text" class="h-3.5 w-3.5" /> PDF</a>@endif
            </footer>
        @endif
    </article>
</div>
