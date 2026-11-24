@extends('layouts.app')

@section('content')
@php
    use App\Modules\CRM\Services\MailboxClient;
    use App\Modules\CRM\Support\CrmAccess;
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $isAdmin = CrmAccess::seesAll(auth()->user());
    $here = fn (array $q = []) => route('crm.inbox.index', array_filter(array_merge(['box' => $box?->id, 'folder' => $role !== 'inbox' ? $role : null, 'q' => $search ?: null], $q), fn ($v) => $v !== null && $v !== ''));
    $msg = $message;
    $replyTo = $msg ? ($msg['reply_to']['email'] ?? $msg['from']['email']) : '';
    $replySubject = $msg ? (preg_match('/^re:/i', $msg['subject']) ? $msg['subject'] : 'Re: '.$msg['subject']) : '';
    $replyAllCc = $msg ? collect($msg['to'])->merge($msg['cc'])->pluck('email')->reject(fn ($e) => $e === $box?->address || $e === $replyTo)->unique()->join(', ') : '';
    $composeOpen = $errors->has('to') || $errors->has('subject') || $errors->has('body') || $errors->has('files.*');
@endphp
<div x-data="{ compose: {{ $composeOpen ? 'true' : 'false' }}, to: @js(old('to', '')), cc: @js(old('cc', '')), subject: @js(old('subject', '')), replyUid: @js(old('reply_uid', '')), adding: {{ $errors->has('address') ? 'true' : 'false' }},
              write(to, cc, subject, uid) { this.to = to; this.cc = cc; this.subject = subject; this.replyUid = uid; this.compose = true; this.$nextTick(() => this.$refs.body?.focus()); } }">
    @include('crm.partials.nav', ['title' => 'Inbox', 'subtitle' => 'Your sharet.africa work email inside scharge: read, reply and write to prospects. Emails to and from CRM companies are linked to them.'])

    @if (! $available)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">This copy of scharge has no PHP imap extension, so mailboxes can't be read here. They work on scharge.sharet.africa.</div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[260px_minmax(0,1fr)]">
        {{-- Mailboxes --}}
        <aside class="{{ $card }} h-fit p-3">
            <div class="mb-2 flex items-center justify-between px-2">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Mailboxes</h3>
                @if ($isAdmin)
                    <button type="button" @click="adding = !adding" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">+ Add</button>
                @endif
            </div>
            <nav class="space-y-1" aria-label="Mailboxes">
                @forelse ($mailboxes as $m)
                    <a href="{{ route('crm.inbox.index', ['box' => $m->id]) }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                        'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' => $box?->id === $m->id,
                        'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $box?->id !== $m->id,
                    ])>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $m->name }}</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $m->address }}{{ $m->user_id ? '' : ' · shared' }}</span>
                        </span>
                        @if (! $m->isConnected())
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-500 dark:bg-gray-800 dark:text-gray-400">Not connected</span>
                        @elseif (($unread[$m->id] ?? 0) > 0)
                            <span class="rounded-full bg-brand-500 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white">{{ $unread[$m->id] }}</span>
                        @endif
                    </a>
                @empty
                    <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No mailboxes yet.{{ $isAdmin ? ' Add one above.' : ' Ask a sysadmin to add your work email.' }}</p>
                @endforelse
            </nav>
            @if ($isAdmin)
                <form x-show="adding" x-cloak method="POST" action="{{ route('crm.inbox.mailboxes.store') }}" class="mt-3 space-y-3 border-t border-gray-200 px-2 pt-3 dark:border-gray-800">
                    @csrf
                    <p class="text-xs text-gray-500 dark:text-gray-400">Create the mailbox in CyberPanel first, then add it here.</p>
                    <div><label class="{{ $label }}" for="mb-address">Address</label><input id="mb-address" name="address" type="email" required value="{{ old('address') }}" placeholder="name@sharet.africa" class="{{ $field }}">
                        @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $label }}" for="mb-label">Name</label><input id="mb-label" name="label" value="{{ old('label') }}" placeholder="e.g. Waithaka (work)" class="{{ $field }}"></div>
                    <div><label class="{{ $label }}" for="mb-owner">Whose is it?</label>
                        <select id="mb-owner" name="user_id" class="{{ $field }}"><option value="">Shared with all CRM staff</option>@foreach ($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
                    <button class="h-10 w-full rounded-lg bg-brand-500 text-sm font-medium text-white hover:bg-brand-600">Add mailbox</button>
                </form>
            @endif
        </aside>

        <section class="min-w-0">
            @if (! $box)
                <div class="{{ $card }} p-10 text-center text-sm text-gray-500 dark:text-gray-400">No mailbox to show.</div>
            @elseif (! $box->isConnected())
                {{-- Connect --}}
                <div class="{{ $card }} mx-auto max-w-xl p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-500 dark:bg-brand-500/10"><x-icon name="envelope" class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Connect your work email</h3>
                            <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $box->address }}</p>
                        </div>
                    </div>
                    @if ($box->manageableBy(auth()->user()))
                        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Enter this mailbox's password once. Your prospects' emails and replies then show here, and you can answer them without leaving scharge.</p>
                        <form method="POST" action="{{ route('crm.inbox.connect', $box) }}" class="mt-5 space-y-4" x-data="{ show: false }">
                            @csrf
                            <div><label class="{{ $label }}" for="mb-password">Email password</label>
                                <div class="relative">
                                    <input id="mb-password" name="password" :type="show ? 'text' : 'password'" type="password" required autocomplete="off" class="{{ $field }} pr-16">
                                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-4 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400" x-text="show ? 'Hide' : 'Show'">Show</button>
                                </div>
                                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                            <button class="h-11 w-full rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">Connect inbox</button>
                            <p class="flex items-center gap-1.5 text-xs text-gray-400"><x-icon name="lock-closed" class="h-3.5 w-3.5" /> Kept encrypted. Only used to read and send from this inbox.</p>
                        </form>
                    @else
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A sysadmin connects this shared mailbox. Ask them to type its password on this page.</p>
                    @endif
                    @if ($isAdmin)
                        <form method="POST" action="{{ route('crm.inbox.mailboxes.update', $box) }}" class="mt-6 grid grid-cols-1 gap-3 border-t border-gray-200 pt-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end dark:border-gray-800">
                            @csrf @method('PUT')
                            <div><label class="{{ $label }}" for="mb-l2">Name</label><input id="mb-l2" name="label" value="{{ $box->label }}" class="{{ $field }}"></div>
                            <div><label class="{{ $label }}" for="mb-o2">Whose is it?</label>
                                <select id="mb-o2" name="user_id" class="{{ $field }}"><option value="">Shared</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($box->user_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
                            <button class="h-11 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Save</button>
                        </form>
                    @endif
                </div>
            @else
                {{-- Toolbar --}}
                <div class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <nav class="flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-900" aria-label="Folders">
                        @foreach (MailboxClient::ROLES as $r => $name)
                            @continue($r !== 'inbox' && ! isset($folders[$r]))
                            <a href="{{ route('crm.inbox.index', ['box' => $box->id, 'folder' => $r === 'inbox' ? null : $r]) }}" @class([
                                'rounded-lg px-3 py-1.5 text-sm font-medium',
                                'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' => $role === $r,
                                'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $role !== $r,
                            ])>{{ $name }}@if ($r === 'inbox' && ($unread[$box->id] ?? 0) > 0) <span class="ml-1 text-brand-600">{{ $unread[$box->id] }}</span>@endif</a>
                        @endforeach
                    </nav>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="GET" class="flex items-center gap-2">
                            <input type="hidden" name="box" value="{{ $box->id }}">
                            @if ($role !== 'inbox')<input type="hidden" name="folder" value="{{ $role }}">@endif
                            <input name="q" value="{{ $search }}" placeholder="Search this folder" aria-label="Search" class="h-10 w-56 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                        </form>
                        <button type="button" @click="write('', '', '', '')" class="inline-flex h-10 items-center gap-2 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Write</button>
                        @if ($box->manageableBy(auth()->user()))
                            <form method="POST" action="{{ route('crm.inbox.disconnect', $box) }}" onsubmit="return confirm('Disconnect {{ $box->address }}? scharge forgets its password; the emails stay on the mail server.')">
                                @csrf @method('DELETE')
                                <button class="h-10 rounded-lg border border-gray-300 px-3 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5">Disconnect</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($error)
                    <div class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                        {{ $error }}
                        @if ($box->manageableBy(auth()->user()) && str_contains($error, 'password')) If this mailbox's password changed, disconnect and connect again with the new one. @endif
                    </div>
                @endif

                <div @class(['grid grid-cols-1 gap-4', '2xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]' => $msg])>
                    {{-- List --}}
                    <div @class(['overflow-hidden', $card, 'hidden 2xl:block' => $msg])>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($list['items'] as $m)
                                @php
                                    $who = $role === 'sent' ? ($m['to_name'] ?: $m['to_email']) : ($m['from_name'] ?: $m['from_email']);
                                    $company = $companies[$role === 'sent' ? $m['to_email'] : $m['from_email']] ?? null;
                                @endphp
                                <li>
                                    <a href="{{ $here(['uid' => $m['uid'], 'page' => $page > 1 ? $page : null]) }}" @class([
                                        'block px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-white/5',
                                        'bg-brand-50/60 dark:bg-brand-500/10' => $msg && $msg['uid'] === $m['uid'],
                                    ])>
                                        <div class="flex items-center gap-2">
                                            @unless ($m['seen'])<span class="h-2 w-2 shrink-0 rounded-full bg-brand-500" title="Unread"></span>@endunless
                                            <span @class(['min-w-0 flex-1 truncate text-sm', 'font-semibold text-gray-900 dark:text-white' => ! $m['seen'], 'text-gray-700 dark:text-gray-300' => $m['seen']])>{{ $role === 'sent' ? 'To: ' : '' }}{{ $who }}</span>
                                            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $m['date'] ? ($m['date']->isToday() ? $m['date']->format('H:i') : $m['date']->format('j M')) : '' }}</span>
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-2">
                                            <span @class(['min-w-0 flex-1 truncate text-sm', 'text-gray-800 dark:text-white/90' => ! $m['seen'], 'text-gray-500 dark:text-gray-400' => $m['seen']])>{{ $m['answered'] ? '↩ ' : '' }}{{ $m['subject'] }}</span>
                                            @if ($company)<span class="shrink-0 truncate rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-300" style="max-width: 10rem">{{ $company->name }}</span>@endif
                                        </div>
                                    </a>
                                </li>
                            @empty
                                <li class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ $search !== '' ? 'Nothing matches "'.$search.'".' : 'No emails here yet.' }}</li>
                            @endforelse
                        </ul>
                        @php $pages = (int) ceil($list['total'] / MailboxClient::PER_PAGE); @endphp
                        @if ($pages > 1)
                            <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                                <span class="text-gray-500 dark:text-gray-400">Page {{ $page }} of {{ $pages }} · {{ number_format($list['total']) }} emails</span>
                                <span class="flex gap-2">
                                    @if ($page > 1)<a href="{{ $here(['page' => $page - 1]) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 dark:border-gray-700 dark:text-gray-300">Newer</a>@endif
                                    @if ($page < $pages)<a href="{{ $here(['page' => $page + 1]) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-gray-700 dark:border-gray-700 dark:text-gray-300">Older</a>@endif
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Message --}}
                    @if ($msg)
                        @php $msgCompany = $companies[$msg['from']['email']] ?? collect($msg['to'])->map(fn ($p) => $companies[$p['email']] ?? null)->filter()->first(); @endphp
                        <article class="{{ $card }} flex min-w-0 flex-col">
                            <header class="border-b border-gray-200 p-5 dark:border-gray-800">
                                <a href="{{ $here(['page' => $page > 1 ? $page : null]) }}" class="mb-3 inline-block text-sm text-brand-600 hover:underline 2xl:hidden dark:text-brand-400">← Back to {{ strtolower(MailboxClient::ROLES[$role]) }}</a>
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $msg['subject'] }}</h3>
                                <div class="mt-2 flex flex-wrap items-start justify-between gap-3 text-sm">
                                    <div class="min-w-0 text-gray-600 dark:text-gray-400">
                                        <div><span class="font-medium text-gray-800 dark:text-white/90">{{ $msg['from']['name'] ?: $msg['from']['email'] }}</span> &lt;{{ $msg['from']['email'] }}&gt;</div>
                                        <div class="truncate">To: {{ collect($msg['to'])->pluck('email')->join(', ') }}@if ($msg['cc']) · Cc: {{ collect($msg['cc'])->pluck('email')->join(', ') }}@endif</div>
                                        @if ($msgCompany)
                                            <a href="{{ route('crm.companies.show', $msgCompany->id) }}" class="mt-1 inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 hover:underline dark:bg-blue-500/15 dark:text-blue-300">CRM: {{ $msgCompany->name }}{{ $msgCompany->crm_stage ? ' · '.$msgCompany->crm_stage_label : '' }}</a>
                                        @endif
                                    </div>
                                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $msg['date']?->format('D j M Y, H:i') }}</span>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="button" @click="write(@js($replyTo), '', @js($replySubject), {{ $msg['uid'] }})" class="h-9 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Reply</button>
                                    @if ($replyAllCc !== '')
                                        <button type="button" @click="write(@js($replyTo), @js($replyAllCc), @js($replySubject), {{ $msg['uid'] }})" class="h-9 rounded-lg border border-gray-300 px-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Reply all</button>
                                    @endif
                                    <button type="button" @click="write('', '', @js(preg_match('/^fwd?:/i', $msg['subject']) ? $msg['subject'] : 'Fwd: '.$msg['subject']), {{ $msg['uid'] }})" class="h-9 rounded-lg border border-gray-300 px-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Forward</button>
                                    <form method="POST" action="{{ route('crm.inbox.unread', [$box, $msg['uid']]) }}">@csrf<input type="hidden" name="folder" value="{{ $role }}"><button class="h-9 rounded-lg border border-gray-300 px-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Mark unread</button></form>
                                    <form method="POST" action="{{ route('crm.inbox.trash', [$box, $msg['uid']]) }}" onsubmit="return confirm('{{ $role === 'trash' ? 'Delete this email for good?' : 'Move this email to Trash?' }}')">@csrf<input type="hidden" name="folder" value="{{ $role }}"><button class="h-9 rounded-lg border border-red-200 px-3 text-sm text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/40">{{ $role === 'trash' ? 'Delete' : 'Trash' }}</button></form>
                                </div>
                            </header>
                            <iframe sandbox="allow-popups allow-popups-to-escape-sandbox" srcdoc="{{ $msg['body'] }}" title="Email body" class="w-full flex-1 bg-white" style="min-height: 480px; border: 0"
                                    onload="try { this.style.height = Math.max(480, this.contentDocument.documentElement.scrollHeight + 24) + 'px' } catch (e) {}"></iframe>
                            @if ($msg['attachments'])
                                <footer class="flex flex-wrap gap-2 border-t border-gray-200 p-4 dark:border-gray-800">
                                    @foreach ($msg['attachments'] as $a)
                                        <a href="{{ route('crm.inbox.attachment', [$box, $msg['uid'], 'folder' => $role, 'part' => $a['part']]) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                                            📎 <span class="max-w-[14rem] truncate">{{ $a['name'] }}</span> <span class="text-xs text-gray-500">{{ $a['size'] ? number_format($a['size'] / 1024 * 0.75).' KB' : '' }}</span>
                                        </a>
                                    @endforeach
                                </footer>
                            @endif
                        </article>
                    @endif
                </div>
            @endif
        </section>
    </div>

    {{-- Write / reply: right-side slide-over --}}
    @if ($box && $box->isConnected())
    <div x-show="compose" x-cloak class="fixed inset-0 z-99999" role="dialog" aria-modal="true" aria-labelledby="compose-title" @keydown.escape.window="compose = false">
        <div x-show="compose" x-transition.opacity.duration.200ms class="absolute inset-0 modal-overlay" @click="compose = false"></div>
        <form method="POST" action="{{ route('crm.inbox.send', $box) }}" enctype="multipart/form-data" x-show="compose"
              x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
              x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
              class="absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl dark:bg-gray-900">
            @csrf
            <input type="hidden" name="reply_uid" :value="replyUid">
            <input type="hidden" name="folder" value="{{ $role }}">
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-800">
                <div>
                    <h3 id="compose-title" class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="replyUid ? (subject.match(/^fwd?:/i) ? 'Forward' : 'Reply') : 'New email'"></h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">From {{ auth()->user()->name }} &lt;{{ $box->address }}&gt;. A copy goes to Sent.</p>
                </div>
                <button type="button" @click="compose = false" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/5" aria-label="Close"><x-icon name="x-mark" class="h-5 w-5" /></button>
            </div>
            <div class="flex-1 space-y-4 overflow-y-auto p-5">
                @if ($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $errors->first() }}</div>
                @endif
                <div><label class="{{ $label }}" for="c-to">To</label><input id="c-to" name="to" x-model="to" required placeholder="name@company.co.ke (separate several with commas)" class="{{ $field }}"></div>
                <div><label class="{{ $label }}" for="c-cc">Cc</label><input id="c-cc" name="cc" x-model="cc" class="{{ $field }}"></div>
                <div><label class="{{ $label }}" for="c-subject">Subject</label><input id="c-subject" name="subject" x-model="subject" required class="{{ $field }}"></div>
                <div><label class="{{ $label }}" for="c-body">Message</label>
                    <textarea id="c-body" name="body" x-ref="body" required rows="12" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('body') }}</textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="replyUid">The original email is quoted below your message.</p></div>
                <div><label class="{{ $label }}" for="c-files">Attachments (up to 5, 10 MB each)</label>
                    <input id="c-files" name="files[]" type="file" multiple class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm dark:text-gray-400 dark:file:bg-gray-800 dark:file:text-gray-300"></div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-800">
                <button type="button" @click="compose = false" class="h-11 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
                <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">Send</button>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection
