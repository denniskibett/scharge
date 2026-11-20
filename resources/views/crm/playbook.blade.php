{{-- Playbook slide-over body (fragment): loaded into crm.partials.playbook-modal. Pills switch sections; ⋯ menus hold the secondary actions. --}}
@php
    $field = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $area = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm leading-relaxed text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400';
    $btn = 'inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50';
    $ghost = 'inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
    $tab = fn ($t) => "tab === '{$t}'";
    $verify = (bool) $company->profile('needs_verification');
    $menu = 'absolute right-0 z-10 mt-1 w-64 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-800 dark:bg-gray-900';
    $item = 'flex w-full items-center gap-2 px-3 py-2 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5';
    $dots = 'inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/5';
@endphp
<style>
.pb-editor { min-height: 16rem; }
.pb-editor-wrap:focus-within { border-color: var(--color-brand-300, #a4bcfd); }
.pb-editor p { margin: 0 0 .85em; }
.pb-editor ul { list-style: disc; padding-left: 1.4em; margin: 0 0 .85em; }
.pb-editor ol { list-style: decimal; padding-left: 1.4em; margin: 0 0 .85em; }
.pb-editor li { margin: 0 0 .3em; }
.pb-editor h3 { font-size: 1rem; font-weight: 600; margin: .4em 0 .6em; }
.pb-editor a { color: #DC2626; text-decoration: underline; }
.pb-editor blockquote { border-left: 3px solid #e5e7eb; padding-left: .8em; color: #6b7280; }
</style>
<div x-data="{ tab: '{{ $doNotEmail ? 'approach' : ($hasSavedDraft ? 'email' : 'research') }}', copied: '' }" class="flex h-full flex-col overflow-hidden">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-800">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-brand-500">Playbook · {{ $book['label'] }}</p>
            <h3 class="truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $company->name }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ collect([$company->profile('area'), $company->profile('city')])->filter()->join(', ') ?: 'Location not set' }}
                · Account manager: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $company->owner?->name ?? 'none yet ('.$manager->name.' if you send)' }}</span>
                @if ($company->profile('property_class')) · {{ $company->profile('property_class') }}@endif
                @if ($company->profile('last_emailed_at')) · last emailed {{ \Illuminate\Support\Carbon::parse($company->profile('last_emailed_at'))->diffForHumans() }}@endif
            </p>
            @include('crm.partials.engagement', ['e' => \App\Modules\CRM\Support\EmailEngagement::summary(null, $company->id), 'events' => \App\Modules\CRM\Support\EmailEngagement::events(null, $company->id, 20)])
        </div>
        <div class="flex shrink-0 items-center gap-1">
            <div class="relative" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape.stop="menu = false">
                <button type="button" @click="menu = !menu" class="{{ $dots }}" aria-label="More actions" :aria-expanded="menu"><x-icon name="ellipsis-horizontal" class="h-5 w-5" /></button>
                <div x-show="menu" x-cloak x-transition.origin.top.right class="{{ $menu }}" @click="menu = false">
                    <a href="{{ route('crm.companies.show', $company) }}" class="{{ $item }}">Open company page</a>
                    @if ($company->phone)
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $company->phone) }}" class="{{ $item }}">Call {{ $company->phone }}</a>
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $company->phone) }}" target="_blank" rel="noopener" class="{{ $item }}">WhatsApp</a>
                    @endif
                    @if ($company->email)<a href="mailto:{{ $company->email }}" class="{{ $item }}">Email from my mailbox</a>@endif
                    <div class="mt-1 mb-1 border-t border-gray-100 dark:border-gray-800"></div>
                    <a href="{{ $demoUrl }}" target="_blank" rel="noopener" class="{{ $item }}">Open their demo form</a>
                    <button type="button" class="{{ $item }}" @click="navigator.clipboard.writeText(@js($demoUrl)); copied = 'Demo link copied'; setTimeout(() => copied = '', 1500)">Copy demo link</button>
                    <a href="{{ route('crm.companies.playbook.preview', $company) }}" target="_blank" rel="noopener" class="{{ $item }}">Preview saved email</a>
                    <button type="button" class="{{ $item }}" @click="tab = 'verify'">Verify details</button>
                </div>
            </div>
            <button type="button" @click="$dispatch('close-playbook')" class="{{ $dots }}" aria-label="Close"><x-icon name="x-mark" class="h-5 w-5" /></button>
        </div>
    </div>

    <nav class="flex gap-2 overflow-x-auto border-b border-gray-200 px-5 py-3 dark:border-gray-800" aria-label="Playbook sections">
        @foreach (['research' => '1 · Research', 'email' => '2 · Cold email', 'approach' => 'How to approach', 'offers' => 'What to sell', 'verify' => 'Verify details'] as $key => $name)
            <button type="button" @click="tab = '{{ $key }}'" :aria-current="{{ $tab($key) }} ? 'page' : null"
                :class="{{ $tab($key) }} ? 'bg-brand-500 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-800 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10'"
                class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-1.5 text-sm font-medium transition">
                {{ $name }}@if ($key === 'verify' && $verify)<span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>@endif
            </button>
        @endforeach
    </nav>
    <div x-show="copied" x-cloak x-text="copied" class="mx-5 mt-3 rounded-lg bg-green-50 px-3 py-2 text-xs font-medium text-green-700 dark:bg-green-500/10 dark:text-green-300"></div>

    <div class="flex-1 overflow-y-auto p-5">
        {{-- Research: their problems, what we know, what you found --}}
        <div x-show="{{ $tab('research') }}" x-cloak>
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">Emails that open with <em>their</em> problem get far more replies than a product pitch. Tick what's likely true for {{ $company->name }}, add one thing you found (their website, listings, reviews), and we'll write the email around it.</p>
            <form method="POST" action="{{ route('crm.companies.playbook.compose', $company) }}" class="space-y-4">
                @csrf
                <fieldset>
                    <legend class="{{ $label }}">Likely problems (pick 2 or 3)</legend>
                    <div class="space-y-2">
                        @foreach ($pains as $key => [$problem, $fix])
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-3 text-sm hover:bg-gray-50 has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50/50 dark:border-gray-800 dark:hover:bg-white/[0.03] dark:has-[:checked]:bg-brand-500/10">
                                <input type="checkbox" name="pains[]" value="{{ $key }}" class="mt-0.5" @checked(in_array($key, $chosenPains ?: array_slice(array_keys($pains), 0, 2), true))>
                                <span><span class="font-medium text-gray-800 dark:text-white/90">{{ $problem }}</span><span class="block text-gray-500 dark:text-gray-400">→ {{ $fix }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                @if ($signals)
                    <div class="rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/[0.03]">
                        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">What we already know</p>
                        <ul class="list-disc space-y-0.5 pl-5 text-gray-700 dark:text-gray-300">@foreach ($signals as $s)<li>{{ $s }}</li>@endforeach</ul>
                    </div>
                @endif
                <div>
                    <label class="{{ $label }}" for="pb-research">What you found about them <span class="font-normal text-gray-400">(one or two sentences; opens the email)</span></label>
                    <textarea id="pb-research" name="research" rows="3" maxlength="600" class="{{ $area }}" placeholder="e.g. I saw you list 23 rentals in Kilimani and Westlands on Property24, and several tenants mention water billing in your reviews.">{{ $company->profile('research') }}</textarea>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="{{ $btn }}">Write my email</button>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Replaces the current draft. You can still edit it before sending.</span>
                </div>
            </form>
        </div>

        {{-- Cold email --}}
        <div x-show="{{ $tab('email') }}">
            @if ($doNotEmail)
                <p class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $company->name }} asked not to be emailed. Sending is blocked (see Verify details).</p>
            @elseif (! $company->email)
                <p class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">No business email on file. Find one on their website or a directory and enter it below (only a published business address, like info@).</p>
            @endif
            <form method="POST" action="{{ route('crm.companies.playbook.send', $company) }}" class="space-y-3" x-data="{ sending: false }" @submit="sending = true">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]">
                    <div><label class="{{ $label }}" for="pb-to">To</label><input id="pb-to" name="to" type="email" required value="{{ old('to', $company->email) }}" placeholder="info@company.co.ke" class="{{ $field }}"></div>
                    <div><label class="{{ $label }}" for="pb-days">Follow up in</label>
                        <select id="pb-days" name="follow_up_days" class="{{ $field }}">@foreach ([2 => '2 working days', 3 => '3 working days', 5 => '1 week', 10 => '2 weeks'] as $d => $t)<option value="{{ $d }}" @selected($d === 3)>{{ $t }}</option>@endforeach</select></div>
                </div>
                @php($ccOptions = \App\Modules\CRM\Support\EmailFinder::ccFor($company))
                @if ($ccOptions)
                    <fieldset><legend class="{{ $label }}">Copy (CC) their other published addresses</legend>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-700 dark:text-gray-300">
                            @foreach ($ccOptions as $cc)
                                <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="cc[]" value="{{ $cc }}" @checked(in_array($cc, old('cc', $ccOptions), true)) class="rounded border-gray-300 dark:border-gray-600"> {{ $cc }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <div><label class="{{ $label }}" for="pb-subject">Subject</label><input id="pb-subject" name="subject" required maxlength="200" value="{{ old('subject', $draft['subject']) }}" class="{{ $field }}"></div>
                <div x-data="{
                        sync() { this.$refs.body.value = this.$refs.ed.innerHTML },
                        cmd(c, v = null) { this.$refs.ed.focus(); document.execCommand(c, false, v); this.sync() },
                        link() { const u = prompt('Link address', 'https://'); if (u && u !== 'https://') this.cmd('createLink', u) },
                        paste(e) { document.execCommand('insertText', false, e.clipboardData.getData('text/plain')); this.sync() },
                    }" x-init="$refs.ed.innerHTML = $refs.body.value; document.execCommand('defaultParagraphSeparator', false, 'p')">
                    <span id="pb-body-label" class="{{ $label }}">Message @if ($hasSavedDraft)<span class="font-normal text-gray-400">(saved draft for this company)</span>@else<span class="font-normal text-gray-400">(playbook template; edit freely)</span>@endif</span>
                    <div class="overflow-hidden rounded-lg border border-gray-300 pb-editor-wrap dark:border-gray-700">
                        <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 px-1.5 py-1 dark:border-gray-800 dark:bg-white/[0.03]" role="toolbar" aria-label="Formatting">
                            <button type="button" @mousedown.prevent="cmd('bold')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10 font-bold" title="Bold (Ctrl+B)" aria-label="Bold">B</button>
                            <button type="button" @mousedown.prevent="cmd('italic')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10 italic" title="Italic (Ctrl+I)" aria-label="Italic">I</button>
                            <button type="button" @mousedown.prevent="cmd('underline')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10 underline" title="Underline (Ctrl+U)" aria-label="Underline">U</button>
                            <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                            <button type="button" @mousedown.prevent="cmd('formatBlock', 'h3')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10 font-semibold" title="Heading" aria-label="Heading">H</button>
                            <button type="button" @mousedown.prevent="cmd('formatBlock', 'p')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10" title="Normal text" aria-label="Normal text">¶</button>
                            <button type="button" @mousedown.prevent="cmd('insertUnorderedList')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10" title="Bulleted list" aria-label="Bulleted list">• List</button>
                            <button type="button" @mousedown.prevent="cmd('insertOrderedList')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10" title="Numbered list" aria-label="Numbered list">1. List</button>
                            <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                            <button type="button" @mousedown.prevent="link()" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10" title="Add a link" aria-label="Add a link">Link</button>
                            <button type="button" @mousedown.prevent="cmd('unlink')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10" title="Remove link" aria-label="Remove link">Unlink</button>
                            <button type="button" @mousedown.prevent="cmd('removeFormat')" class="inline-flex h-8 items-center justify-center rounded-md px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10" title="Clear formatting" aria-label="Clear formatting">Clear</button>
                        </div>
                        <div x-ref="ed" id="pb-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="pb-body-label"
                             @input="sync()" @blur="sync()" @paste.prevent="paste($event)"
                             class="pb-editor max-h-96 overflow-y-auto px-3 py-2 text-sm leading-relaxed text-gray-800 focus:outline-none dark:text-white/90"></div>
                    </div>
                    <textarea x-ref="body" name="body" class="hidden" aria-hidden="true">{{ \App\Modules\CRM\Support\EmailBody::toHtml(old('body', $draft['body'])) }}</textarea>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Their demo form: <a href="{{ $demoUrl }}" target="_blank" rel="noopener" class="text-brand-500 hover:underline">{{ $demoUrl }}</a> (the email's button goes there; what they fill in comes back to this company as a PDF).</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Sent from scharge as <strong>{{ $manager->name }} · scharge</strong>; replies go to {{ $manager->email }}. An opt-out line is added at the bottom. A call follow-up is booked for {{ $manager->name }} and the email is logged on the timeline.</p>
                <div class="sticky bottom-0 flex items-center gap-2 border-t border-gray-200 bg-white py-3 dark:border-gray-800 dark:bg-gray-900">
                    <button class="{{ $btn }}" :disabled="sending" @disabled($doNotEmail)><span x-text="sending ? 'Sending…' : 'Send email'"></span></button>
                    <button type="submit" formaction="{{ route('crm.companies.playbook.draft', $company) }}" formnovalidate name="_method" value="PUT" class="{{ $ghost }}">Save draft</button>
                    <div class="relative ml-auto" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape.stop="menu = false">
                        <button type="button" @click="menu = !menu" class="{{ $dots }}" aria-label="More email actions" :aria-expanded="menu"><x-icon name="ellipsis-horizontal" class="h-5 w-5" /></button>
                        <div x-show="menu" x-cloak x-transition.origin.bottom.right class="{{ $menu }} mt-0" style="bottom:2.75rem" @click="menu = false">
                            <a href="{{ route('crm.companies.playbook.preview', $company) }}" target="_blank" rel="noopener" class="{{ $item }}" title="Shows the saved draft; save first to preview your edits">Preview email</a>
                            <button type="button" class="{{ $item }}" @click="navigator.clipboard.writeText(document.getElementById('pb-subject').value + '\n\n' + document.getElementById('pb-editor').innerText); copied = 'Email text copied'; setTimeout(() => copied = '', 1500)">Copy subject and text</button>
                            <button type="button" class="{{ $item }}" @click="navigator.clipboard.writeText(@js($demoUrl)); copied = 'Demo link copied'; setTimeout(() => copied = '', 1500)">Copy demo link</button>
                            <button type="button" class="{{ $item }}" @click="tab = 'research'">Rewrite from research</button>
                            @if ($hasSavedDraft)
                                <button type="submit" formaction="{{ route('crm.companies.playbook.draft', $company) }}" formnovalidate name="reset" value="1" class="{{ $item }} text-red-600 dark:text-red-400" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=_method value=PUT>')">Reset to template</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- How to approach --}}
        <div x-show="{{ $tab('approach') }}" x-cloak class="space-y-5">
            <ol class="list-decimal space-y-2 pl-5 text-sm text-gray-700 dark:text-gray-300">
                @foreach ($book['approach'] as $step)<li>{{ $step }}</li>@endforeach
            </ol>
            @if ($company->profile('target_role') || $company->profile('opportunity'))
                <div class="rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/[0.03]">
                    @if ($company->profile('target_role'))<p><span class="text-gray-500">Ask for:</span> {{ $company->profile('target_role') }}</p>@endif
                    @if ($company->profile('opportunity'))<p class="mt-1"><span class="text-gray-500">Angle from the sheet:</span> {{ $company->profile('opportunity') }}</p>@endif
                </div>
            @endif
            <div>
                <h4 class="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">Objections</h4>
                <dl class="space-y-2 text-sm">
                    @foreach ($book['objections'] as $q => $a)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800"><dt class="font-medium text-gray-800 dark:text-white/90">“{{ $q }}”</dt><dd class="mt-0.5 text-gray-600 dark:text-gray-400">{{ $a }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- What to sell --}}
        <div x-show="{{ $tab('offers') }}" x-cloak>
            <ul class="space-y-2">
                @foreach ($book['offers'] as $offer)
                    <li class="flex gap-2 text-sm text-gray-700 dark:text-gray-300"><x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-600" />{{ $offer }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Pricing is per unit per month whatever the occupancy. Set the expected monthly fee on the company page so it counts in the sales tracker.</p>
        </div>

        {{-- Verify details --}}
        <div x-show="{{ $tab('verify') }}" x-cloak>
            @if ($verify)<p class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Some details came from a register or directory and haven't been checked. Confirm them against the company's own website or a call, then untick "Still needs verifying".</p>@endif
            <form method="POST" action="{{ route('crm.companies.update', $company) }}" class="space-y-3">
                @csrf @method('PUT')
                <div><label class="{{ $label }}" for="pv-name">Business name</label><input id="pv-name" name="name" value="{{ $company->name }}" required class="{{ $field }}"></div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div><label class="{{ $label }}" for="pv-phone">Business phone</label><input id="pv-phone" name="phone" value="{{ $company->phone }}" class="{{ $field }}"></div>
                    <div><label class="{{ $label }}" for="pv-phone2">Other phone</label><input id="pv-phone2" name="profile[phone_alt]" value="{{ $company->profile('phone_alt') }}" class="{{ $field }}"></div>
                    <div><label class="{{ $label }}" for="pv-email">Business email</label><input id="pv-email" name="email" type="email" value="{{ $company->email }}" class="{{ $field }}"></div>
                    <div><label class="{{ $label }}" for="pv-web">Website</label><input id="pv-web" name="profile[website]" value="{{ $company->profile('website') }}" class="{{ $field }}"></div>
                    <div><label class="{{ $label }}" for="pv-city">Town</label><input id="pv-city" name="profile[city]" value="{{ $company->profile('city') }}" class="{{ $field }}"></div>
                    <div><label class="{{ $label }}" for="pv-area">Area</label><input id="pv-area" name="profile[area]" value="{{ $company->profile('area') }}" class="{{ $field }}"></div>
                </div>
                <div><label class="{{ $label }}" for="pv-notes">Notes</label><textarea id="pv-notes" name="profile[notes]" rows="3" class="{{ $area }}">{{ $company->profile('notes') }}</textarea></div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Business contacts only (office lines, info@ addresses), as the company publishes them. Don't store anyone's personal number or email here.</p>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="profile[needs_verification]" value="1" @checked($verify)> Still needs verifying</label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="hidden" name="profile[do_not_email]" value="0"><input type="checkbox" name="profile[do_not_email]" value="1" @checked($doNotEmail)> Do not email (they asked us to stop)</label>
                <button class="{{ $btn }}">Save details</button>
            </form>
        </div>
    </div>
</div>
