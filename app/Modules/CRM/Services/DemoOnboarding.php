<?php

namespace App\Modules\CRM\Services;

use App\Models\Company;
use App\Models\Property;
use App\Modules\CRM\Mail\AccountReadyEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\CRM\Support\Playbook;
use App\Modules\CRM\Support\SetupMode;
use App\Models\User;
use App\Services\Properties\PropertyRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Done-for-you onboarding after a demo. From what the client shared on the demo form, scharge:
 *   1. turns the prospect into a customer account (same record and history; nothing is billed until the deal is Won,
 *      because the first bill only goes out once staff set a price per unit),
 *   2. gives the person who booked the demo an admin login (set-password link valid 14 days),
 *   3. creates their properties through the building registry (one record per building, never copied),
 *   4. puts the account in setup mode: they add and edit units, scharge does the rest (SetupMode),
 *   5. books the account manager's setup tasks, and
 *   6. emails them an onboarding to-do PDF with screenshots, page by page.
 * Progress shows on the CRM page (Onboarding section) and ticks itself from their data (OnboardingGuide).
 */
class DemoOnboarding
{
    /** Screenshots used in the PDF (resources/pdf/onboarding). */
    public const SHOTS = ['guide' => 'guide.jpg', 'units' => 'units.jpg', 'import' => 'import.jpg', 'profile' => 'profile.jpg'];

    public function __construct(private ContactService $contacts)
    {
    }

    /**
     * @return array{company: Company, user: ?User, properties: array<int, array{name: string, status: string}>, pdf: ?string, sent: bool, problems: array<string>}
     */
    public function setUp(Company $company, ?User $by = null, bool $send = true): array
    {
        $problems = [];
        $by ??= $company->owner_id ? User::find($company->owner_id) : null;
        $manager = $company->owner_id ? User::find($company->owner_id) : $by;

        // 1. Customer account, same record. No price is set, so no first bill until Won.
        $details = (array) ($company->profile('company_details') ?? []);
        $company->forceFill(array_filter([
            'lifecycle' => 'customer',
            'is_active' => true,
            'registration_number' => $company->registration_number ?: ($details['registration_number'] ?? null),
            'tax_id' => $company->tax_id ?: ($details['kra_pin'] ?? null),
            'address' => $company->address ?: ($details['address'] ?? null),
        ], fn ($v) => $v !== null && $v !== ''))->saveQuietly();
        SetupMode::start($company->fresh(), $by?->id);
        $company = $company->fresh();

        // 2. The person who booked the demo becomes the account's admin.
        $user = $this->login($company, $problems);

        // 3. Properties from the demo form, through the registry.
        $props = $this->properties($company, $problems);

        // 5. The account manager's setup tasks: scharge does everything except the units.
        $this->tasks($company, $manager);

        // 6. The onboarding to-do PDF.
        $pdf = $this->pdf($company, $user, $manager, $props);
        $profile = $company->fresh()->crm_profile ?? [];
        $profile['setup'] = array_merge($profile['setup'] ?? [], ['pdf' => $pdf, 'user_id' => $user?->id, 'expected_units' => (int) ($company->profile('estimated_units') ?? 0)]);
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();

        $this->contacts->log($company, 'note', 'Account set up from the demo', 'Customer account created (no bill until Won). '
            .($user ? 'Admin login for '.$user->name.' <'.$user->email.'>. ' : 'No login: '.implode(' ', $problems).' ')
            .count($props).' propert'.(count($props) === 1 ? 'y' : 'ies').'. Setup mode: they add units, scharge does the rest.', [], null, $by?->id);

        $sent = false;
        if ($send && $user && $manager) {
            $sent = $this->send($company->fresh(), $user, $manager, $pdf);
        }

        return ['company' => $company->fresh(), 'user' => $user, 'properties' => $props, 'pdf' => $pdf, 'sent' => $sent, 'problems' => $problems];
    }

    /** The signed welcome link (14 days): opening it issues a fresh set-password link, until they have signed in once. */
    public static function welcomeUrl(User $user): string
    {
        return URL::temporarySignedRoute('setup.welcome', now()->addDays(14), ['user' => $user->id]);
    }

    private function login(Company $company, array &$problems): ?User
    {
        $row = CrmFollowUp::where('company_id', $company->id)->where('type', 'contact')->whereNotNull('user_id')->orderByDesc('id')->get()
            ->sortBy(fn ($r) => $r->detail('primary') ? 0 : 1)->first();
        $user = $row ? User::find($row->user_id) : null;
        if ($user && $user->company_id && (int) $user->company_id !== (int) $company->id) {
            $problems[] = $user->name.' already belongs to another scharge account.';
            $user = null;
        }
        $email = $user?->email ?: $company->email;
        if (! $email) {
            $problems[] = 'No email for the contact or the company.';

            return null;
        }
        if (! $user) {
            $user = User::where('email', Str::lower($email))->first();
            if ($user && $user->company_id && (int) $user->company_id !== (int) $company->id) {
                $problems[] = $email.' already has a login on another scharge account.';

                return null;
            }
            $user ??= User::forceCreate(['name' => $company->name, 'email' => Str::lower($email), 'phone' => $company->phone, 'password' => bcrypt(Str::password(32))]);
        }
        $user->forceFill(['company_id' => $company->id, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->syncRoles([$company->kind === 'bnb_host' ? 'bnb_host' : 'admin']);

        return $user->fresh();
    }

    /** @return array<int, array{name: string, status: string, units: int, property_id: ?int}> */
    private function properties(Company $company, array &$problems): array
    {
        $out = [];
        $prospect = (array) ($company->profile('prospect_properties') ?? []);
        $registry = app(PropertyRegistry::class);
        foreach ($prospect as $i => $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $units = (int) ($p['units'] ?? 0);
            if (! empty($p['property_id']) && Property::find($p['property_id'])) {
                $out[] = ['name' => $name, 'status' => 'ready', 'units' => $units, 'property_id' => (int) $p['property_id']];

                continue;
            }
            $match = $registry->matches($name, $p['location'] ?? null)->first(fn ($m) => $m['score'] >= 95);
            $property = $match['property'] ?? null;
            if ($property && $property->company_id && (int) $property->company_id !== (int) $company->id) {
                $problems[] = $name.' is already run by another scharge account: claim it from Properties.';
                $out[] = ['name' => $name, 'status' => 'claim', 'units' => $units, 'property_id' => null];

                continue;
            }
            if ($property) {
                $property->forceFill(['company_id' => $company->id])->save();
            } else {
                $property = Property::create(['name' => $name, 'location' => $p['location'] ?? null, 'company_id' => $company->id,
                    'water_rate' => 0, 'service_charge' => 0, 'garbage_charge' => 0, 'security_charge' => 0]);
            }
            PropertyRegistry::index($property);
            $prospect[$i]['property_id'] = $property->id;
            $out[] = ['name' => $name, 'status' => $match ? 'linked' : 'created', 'units' => $units, 'property_id' => $property->id];
        }
        $profile = $company->fresh()->crm_profile ?? [];
        $profile['prospect_properties'] = $prospect;
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();

        return $out;
    }

    private function tasks(Company $company, ?User $manager): void
    {
        if (! $manager) {
            return;
        }
        $due = now()->addWeekday()->setTime(10, 0);
        foreach ([
            ['Import their tenants and balances', 'Ask for their tenant list (Excel or a photo of the book) and load it with Properties › Import, or from the file they send on their Guide page.'],
            ['Connect their M-Pesa Paybill or Till and switch on rent automation', 'Integrations: their own Paybill/Till, then Rent automation with their invoice and due days.'],
            ['Company details and KYC', 'Registered name, KRA PIN, certificate of registration and a director\'s ID on Settings › Verify your business.'],
            ['Open full access when setup is done', 'CRM page › Onboarding › Open full access. Set the price per unit on Admin › Companies once the deal is Won (that starts their first bill).'],
        ] as $n => [$title, $notes]) {
            $exists = CrmFollowUp::where('company_id', $company->id)->where('title', $title)->whereIn('status', ['open', 'scheduled'])->exists();
            if (! $exists) {
                $company->followUps()->create(['assigned_to' => $manager->id, 'type' => 'call', 'status' => 'open', 'title' => $title,
                    'due_at' => $due->copy()->addWeekdays($n === 3 ? 4 : 0), 'notes' => $notes, 'details' => ['onboarding' => 'setup']]);
            }
        }
    }

    public function pdf(Company $company, ?User $user, ?User $manager, array $props): ?string
    {
        $shots = [];
        foreach (self::SHOTS as $key => $file) {
            $path = resource_path('pdf/onboarding/'.$file);
            $shots[$key] = is_file($path) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path)) : null;
        }
        $html = view('crm.pdf.onboarding-guide', [
            'company' => $company, 'user' => $user, 'manager' => $manager, 'properties' => $props, 'shots' => $shots,
            'expected' => (int) ($company->profile('estimated_units') ?? array_sum(array_column($props, 'units'))),
            'managerEmail' => $manager ? (collect($manager->mailboxes())->first(fn ($e) => str_ends_with($e, '@sharet.africa')) ?? $manager->email) : null,
            'loginUrl' => rtrim((string) config('app.url'), '/').'/login',
        ])->render();
        $path = 'crm/onboarding/'.$company->id.'/scharge-onboarding-'.Str::slug(preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name)).'-'.now()->format('Ymd-His').'.pdf';
        Storage::disk('local')->put($path, Pdf::loadHTML($html)->setPaper('a4')->output());

        return $path;
    }

    private function send(Company $company, User $user, User $manager, ?string $pdf): bool
    {
        $first = explode(' ', trim((string) $user->name))[0] ?: 'there';
        $props = collect((array) ($company->profile('prospect_properties') ?? []))->pluck('name')->filter()->join(', ', ' and ');
        $body = '<p>Dear '.e($first).',</p>'
            .'<p>Thank you for your time on the demo. Your scharge account for <strong>'.e($company->name).'</strong> is ready, and we have already set up '.e($props ?: 'your properties').' from what you shared.</p>'
            .'<p><strong>1. Set your password:</strong> <a href="'.self::welcomeUrl($user).'">open your scharge account</a> (this link works for 14 days). Your login is '.e($user->email).'.</p>'
            .'<p><strong>2. Add your units:</strong> on <em>Units</em>, press <em>Add Unit</em> for each house, with its number, type and rent. You can also send us your list in Excel and we load it for you.</p>'
            .'<p><strong>3. Leave the rest to us:</strong> we import your tenants and balances, connect your M-Pesa Paybill or Till, switch on automatic invoices and reminders, and verify your company details. Your checklist on the dashboard ticks itself as each step is done.</p>'
            .'<p>The attached PDF walks you through it page by page, with screenshots. Nothing is billed while we set you up.</p>'
            .'<p>Anything you need, call or WhatsApp me on '.e($manager->phone).'.</p>'
            .'<p>Kind regards,<br>'.e($manager->name).'<br>Account Manager, scharge<br>'.e(collect($manager->mailboxes())->first(fn ($e) => str_ends_with($e, '@sharet.africa')) ?? $manager->email).'</p>';
        $subject = 'Your scharge account is ready: '.preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name);
        $track = Str::random(24);
        try {
            $sent = Mail::to($user->email)->bcc($manager->mailboxes())->send(new AccountReadyEmail($subject, EmailBody::toHtml($body), $manager, $company, $track, [], $pdf));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
        $company->followUps()->create(['assigned_to' => $manager->id, 'type' => 'email', 'status' => 'done', 'done_at' => now(),
            'title' => 'Email: '.$subject, 'notes' => EmailBody::toText($body),
            'details' => ['direction' => 'out', 'to' => $user->email, 'from' => Playbook::fromFor($company), 'subject' => $subject, 'track' => $track,
                'message_id' => $sent?->getMessageId(), 'onboarding' => 'account_ready', 'pdf' => $pdf]]);
        $profile = $company->fresh()->crm_profile ?? [];
        $profile['setup']['sent_at'] = now()->toDateTimeString();
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();
        $this->sms($company->fresh(), $user, $manager);

        return true;
    }

    /**
     * The welcome link by SMS too (KenyaSMS, scharge's SHARETENT sender), to the number they gave on the demo form
     * (else the company's). Logged on the CRM thread. Returns whether it went.
     */
    public function sms(Company $company, User $user, User $manager): bool
    {
        $phone = $user->phone ?: $company->phone;
        if (! $phone) {
            return false;
        }
        $first = explode(' ', trim((string) $user->name))[0] ?: 'there';
        $text = 'Hi '.$first.', your scharge account for '.preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name).' is ready. Set your password here (14 days): '
            .self::welcomeUrl($user).' . Then add your units; we do the rest. '.$manager->name.', scharge '.$manager->phone;
        try {
            $r = app(\App\Services\SmsService::class)->forCompany(null)->send($phone, $text);
        } catch (\Throwable $e) {
            report($e);
            $r = ['success' => false, 'message' => $e->getMessage()];
        }
        $ok = (bool) ($r['success'] ?? false);
        $company->followUps()->create(['assigned_to' => $manager->id, 'type' => 'note', 'status' => 'done', 'done_at' => now(),
            'title' => ($ok ? 'SMS sent: ' : 'SMS failed: ').'welcome link to '.$phone, 'notes' => $text,
            'details' => ['onboarding' => 'welcome_sms', 'to' => $phone, 'result' => array_intersect_key((array) $r, array_flip(['success', 'status', 'message', 'channel']))]]);
        if ($ok) {
            $profile = $company->fresh()->crm_profile ?? [];
            $profile['setup']['sms_at'] = now()->toDateTimeString();
            $company->forceFill(['crm_profile' => $profile])->saveQuietly();
        }

        return $ok;
    }

    /**
     * Where their onboarding stands, for the CRM page: our setup steps plus the live checklist they see.
     *
     * @return array{started_at: ?string, sent_at: ?string, finished_at: ?string, signed_in: bool, units: int, expected: int, steps: array<int, array{label: string, done: bool, detail: string}>, percent: int}
     */
    public static function progress(Company $company): array
    {
        $setup = (array) ($company->profile('setup') ?? []);
        $userId = $setup['user_id'] ?? null;
        $signedIn = $userId && \Illuminate\Support\Facades\Schema::hasTable('login_events')
            && DB::table('login_events')->where('user_id', $userId)->where('success', true)->exists();
        $guide = new \App\Support\OnboardingGuide($company);
        $chapters = collect($guide->chapters())->keyBy('key');
        $propertyIds = DB::table('properties')->where('company_id', $company->id)->pluck('id');
        $units = DB::table('units')->where(fn ($q) => $q->where('company_id', $company->id)->orWhereIn('property_id', $propertyIds))->count();
        $expected = (int) ($setup['expected_units'] ?? $company->profile('estimated_units') ?? 0);
        $steps = [
            ['label' => 'Account set up from the demo', 'done' => ! empty($setup['started_at']), 'detail' => $setup['started_at'] ?? ''],
            ['label' => 'Welcome email and to-do PDF sent', 'done' => ! empty($setup['sent_at']), 'detail' => $setup['sent_at'] ?? ''],
            ['label' => 'They signed in', 'done' => (bool) $signedIn, 'detail' => ''],
            ['label' => 'Units added', 'done' => $expected ? $units >= $expected : $units > 0, 'detail' => $units.($expected ? ' of '.$expected : '').' units'],
        ];
        foreach (['tenants' => 'Tenants imported (scharge)', 'payments' => 'M-Pesa connected (scharge)'] as $key => $label) {
            if ($chapters->has($key)) {
                $steps[] = ['label' => $label, 'done' => (bool) ($chapters[$key]['done'] ?? false), 'detail' => (string) ($chapters[$key]['progress'] ?? '')];
            }
        }
        $kyc = \Illuminate\Support\Facades\Schema::hasTable('company_kyc') ? DB::table('company_kyc')->where('company_id', $company->id)->value('status') : null;
        $steps[] = ['label' => 'Company details and KYC (scharge)', 'done' => $kyc === 'verified', 'detail' => (string) ($kyc ?? 'not started')];
        $steps[] = ['label' => 'Full access opened', 'done' => ! empty($setup['finished_at']), 'detail' => $setup['finished_at'] ?? ''];
        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return ['started_at' => $setup['started_at'] ?? null, 'sent_at' => $setup['sent_at'] ?? null, 'finished_at' => $setup['finished_at'] ?? null,
            'signed_in' => (bool) $signedIn, 'units' => $units, 'expected' => $expected, 'steps' => $steps, 'percent' => (int) round($done / max(1, count($steps)) * 100)];
    }
}
