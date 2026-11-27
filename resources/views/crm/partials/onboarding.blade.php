{{-- Done-for-you onboarding (DemoOnboarding): set the account up from the demo, then track it. Included on crm/show. --}}
@php
    $setup = (array) ($company->profile('setup') ?? []);
    $canSetUp = empty($setup['started_at']) && ! $company->deleted_at
        && (in_array($company->crm_stage, ['demo_booked', 'qualified', 'proposal', 'negotiation', 'won'], true) || $company->profile('prospect_properties'));
    $progress = ! empty($setup['started_at']) ? \App\Modules\CRM\Services\DemoOnboarding::progress($company) : null;
@endphp
@if ($canSetUp)
    <section class="{{ $card }} space-y-3 border-brand-200 dark:border-brand-500/30" aria-labelledby="onboard-h">
        <h2 id="onboard-h" class="{{ $h }}">Onboard them now</h2>
        <p class="text-sm text-gray-600 dark:text-gray-300">Set their account up from what they shared on the demo form: a login for the person who booked, their {{ count((array) $company->profile('prospect_properties', [])) ?: '' }} propert{{ count((array) $company->profile('prospect_properties', [])) === 1 ? 'y' : 'ies' }}, and your setup tasks. They get a welcome email with an onboarding to-do PDF (screenshots, page by page). They can only add and edit units until you open full access; you do the rest. Nothing is billed until the deal is Won.</p>
        <form method="POST" action="{{ route('crm.companies.setup', $company) }}" class="flex flex-wrap items-center gap-3" onsubmit="return confirm('Set up their account and email them now?')">
            @csrf
            <button class="{{ $btn }}">Set up their account</button>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" name="no_send" value="1" class="rounded"> Don't email them yet</label>
        </form>
    </section>
@elseif ($progress)
    <section class="{{ $card }} space-y-3" aria-labelledby="onboard-h">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="onboard-h" class="{{ $h }}">Onboarding <span class="text-sm font-normal text-gray-500">· {{ $progress['percent'] }}%</span></h2>
            <div class="flex items-center gap-3 text-sm">
                @if (! empty($setup['pdf']))<a href="{{ route('crm.companies.setup.pdf', $company) }}" class="font-medium text-brand-500">To-do PDF</a>@endif
                @if (! $progress['finished_at'])
                    <form method="POST" action="{{ route('crm.companies.setup.finish', $company) }}" onsubmit="return confirm('Open every page for their team?')">@csrf<button class="font-medium text-brand-500">Open full access</button></form>
                @endif
            </div>
        </div>
        <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full bg-brand-500" style="width: {{ $progress['percent'] }}%"></div></div>
        <ul class="space-y-1.5 text-sm">
            @foreach ($progress['steps'] as $step)
                <li class="flex items-start gap-2">
                    <span @class(['mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px]', 'bg-green-500 text-white' => $step['done'], 'border border-gray-300 dark:border-gray-600' => ! $step['done']])>{!! $step['done'] ? '&#10003;' : '' !!}</span>
                    <span class="text-gray-700 dark:text-gray-200">{{ $step['label'] }}@if ($step['detail'] !== '')<span class="text-gray-500 dark:text-gray-400"> · {{ $step['detail'] }}</span>@endif</span>
                </li>
            @endforeach
        </ul>
        @if (! $progress['finished_at'])<p class="text-xs text-gray-500 dark:text-gray-400">Setup mode: their logins can add and edit units only. Open full access when tenants, payments and KYC are done.</p>@endif
    </section>
@endif
