{{-- Playbook modal body (fragment): loaded into crm.partials.playbook-modal. --}}
@php
    $field = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $area = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm leading-relaxed text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400';
    $btn = 'inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50';
    $ghost = 'inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
    $tab = fn ($t) => "tab === '{$t}'";
    $verify = (bool) $company->profile('needs_verification');
@endphp
<div x-data="{ tab: '{{ $doNotEmail ? 'approach' : 'email' }}' }" class="flex max-h-[85vh] flex-col">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-800">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-brand-500">Playbook · {{ $book['label'] }}</p>
            <h3 class="truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $company->name }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ collect([$company->profile('area'), $company->profile('city')])->filter()->join(', ') ?: 'Location not set' }}
                · Account manager: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $company->owner?->name ?? 'none yet ('.$manager->name.' if you send)' }}</span>
                @if ($company->profile('last_emailed_at')) · last emailed {{ \Illuminate\Support\Carbon::parse($company->profile('last_emailed_at'))->diffForHumans() }}@endif
            </p>
        </div>
        <button type="button" @click="$dispatch('close-playbook')" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5" aria-label="Close"><x-icon name="x-mark" class="h-5 w-5" /></button>
    </div>

    <nav class="flex gap-1 border-b border-gray-200 px-5 pt-2 dark:border-gray-800" aria-label="Playbook sections">
        @foreach (['email' => 'Cold email', 'approach' => 'How to approach', 'offers' => 'What to sell', 'verify' => 'Verify details'] as $key => $name)
            <button type="button" @click="tab = '{{ $key }}'" :class="{{ $tab($key) }} ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium">
                {{ $name }}@if ($key === 'verify' && $verify)<span class="ml-1 inline-block h-2 w-2 rounded-full bg-amber-500"></span>@endif
            </button>
        @endforeach
    </nav>

    <div class="overflow-y-auto p-5">
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
                <div><label class="{{ $label }}" for="pb-subject">Subject</label><input id="pb-subject" name="subject" required maxlength="200" value="{{ old('subject', $draft['subject']) }}" class="{{ $field }}"></div>
                <div><label class="{{ $label }}" for="pb-body">Message @if ($hasSavedDraft)<span class="font-normal text-gray-400">(saved draft for this company)</span>@else<span class="font-normal text-gray-400">(playbook template; edit freely)</span>@endif</label>
                    <textarea id="pb-body" name="body" rows="12" required class="{{ $area }}">{{ old('body', $draft['body']) }}</textarea></div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Sent from scharge as <strong>{{ $manager->name }} · scharge</strong>; replies go to {{ $manager->email }}. An opt-out line is added at the bottom. A call follow-up is booked for {{ $manager->name }} and the email is logged on the timeline.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="{{ $btn }}" :disabled="sending" @disabled($doNotEmail)><span x-text="sending ? 'Sending…' : 'Send email'"></span></button>
                    <button type="submit" formaction="{{ route('crm.companies.playbook.draft', $company) }}" formnovalidate name="_method" value="PUT" class="{{ $ghost }}">Save draft</button>
                    @if ($hasSavedDraft)<button type="submit" formaction="{{ route('crm.companies.playbook.draft', $company) }}" formnovalidate name="reset" value="1" class="text-sm text-gray-500 hover:text-gray-800" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=_method value=PUT>')">Reset to template</button>@endif
                    <a href="{{ route('crm.companies.playbook.preview', $company) }}" target="_blank" rel="noopener" class="ml-auto text-sm text-brand-500 hover:underline" title="Shows the saved draft; save first to preview your edits">Preview email</a>
                    <button type="button" class="text-sm text-brand-500 hover:underline" x-data="{ copied: false }" @click="navigator.clipboard.writeText(document.getElementById('pb-subject').value + '\n\n' + document.getElementById('pb-body').value); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy text'"></button>
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
            <div class="flex flex-wrap gap-2 text-sm">
                @if ($company->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $company->phone) }}" class="{{ $ghost }}">Call {{ $company->phone }}</a>
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $company->phone) }}" target="_blank" rel="noopener" class="{{ $ghost }}">WhatsApp</a>@endif
                <a href="{{ route('crm.companies.show', $company) }}" class="{{ $ghost }}">Open company page</a>
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
