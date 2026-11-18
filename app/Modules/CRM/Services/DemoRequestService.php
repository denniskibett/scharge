<?php

namespace App\Modules\CRM\Services;

use App\Models\Company;
use App\Modules\CRM\Mail\DemoRequestCopy;
use App\Modules\CRM\Mail\DemoRequestNotice;
use App\Modules\CRM\Models\CrmFollowUp;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * The private demo form linked from cold emails (/demo/{ref}): who they are, what they want, their properties and
 * units, and when to contact them. Lands on the same company as a "demo" request for its account manager, with the
 * submission as a PDF on the company's thread, and an email to the account manager with the PDF attached.
 */
class DemoRequestService
{
    public const ROLES = [
        'agent' => 'Property agent / managing agent',
        'employee' => 'Employee of a property company',
        'bnb_host' => 'Airbnb / short-stay host',
        'owner' => 'Property owner / landlord',
        'developer' => 'Developer',
    ];

    public const INTERESTS = [
        'rent' => 'Rent & service-charge invoicing',
        'mpesa' => 'M-Pesa / bank payments matched automatically',
        'water' => 'Water billing from meter readings',
        'power' => 'Electricity sub-metering / tokens',
        'statements' => 'Landlord / owner statements & payouts',
        'tenant_app' => 'Tenant app: receipts, notices, maintenance',
        'gate' => 'Gate & visitor management',
        'shortstay' => 'Short-stay bookings & direct-booking website',
        'listings' => 'Listing vacant units',
        'api' => 'API / smart-meter integration',
        'migration' => 'Moving our data from Excel or another system',
    ];

    /** Property types, grouped into the class we sell to (each class has its own playbook pitch). */
    public const TYPE_GROUPS = [
        'Residential' => ['Apartments / flats', 'Bedsitters / single rooms', 'Gated estate / maisonettes', 'Standalone houses / villas', 'Student hostel'],
        'Short-stay' => ['Airbnb / serviced apartments', 'Hotel / guest house'],
        'Commercial' => ['Offices', 'Mall / shops / retail', 'Warehouse / industrial'],
        'Mixed use' => ['Mixed use (shops + homes)'],
        'Land & other' => ['Plots / land', 'Other'],
    ];

    public const PROPERTY_TYPES = [
        'Apartments / flats', 'Bedsitters / single rooms', 'Gated estate / maisonettes', 'Standalone houses / villas', 'Student hostel',
        'Airbnb / serviced apartments', 'Hotel / guest house', 'Offices', 'Mall / shops / retail', 'Warehouse / industrial',
        'Mixed use (shops + homes)', 'Plots / land', 'Other',
    ];

    /** Types used by submissions made before the grouped list (18 Nov 2026). */
    private const LEGACY_TYPES = [
        'Apartments' => 'Residential', 'Short-stay / serviced' => 'Short-stay', 'Commercial / offices' => 'Commercial',
        'Mall / retail' => 'Commercial', 'Mixed use' => 'Mixed use',
    ];

    public static function typeGroup(?string $type): ?string
    {
        if (! $type) {
            return null;
        }
        foreach (self::TYPE_GROUPS as $group => $types) {
            if (in_array($type, $types, true)) {
                return $group;
            }
        }

        return self::LEGACY_TYPES[$type] ?? 'Land & other';
    }

    /**
     * Units per property class, largest first, e.g. ['Residential' => 30, 'Commercial' => 6].
     * A typed row with no units still counts its class (0 units).
     *
     * @param  array<int,array<string,mixed>>  $properties
     * @return array<string,int>
     */
    public static function mix(array $properties): array
    {
        $mix = [];
        foreach ($properties as $p) {
            if ($group = self::typeGroup($p['type'] ?? null)) {
                $mix[$group] = ($mix[$group] ?? 0) + (int) ($p['units'] ?? 0);
            }
        }
        arsort($mix);

        return $mix;
    }

    /** The one class for the company: the class with most units, or "Mixed portfolio" when none has 70%. */
    public static function propertyClass(array $mix): ?string
    {
        if (! $mix) {
            return null;
        }
        $total = array_sum($mix);
        $top = array_key_first($mix);

        return count($mix) === 1 || ($total > 0 && $mix[$top] / $total >= 0.7) ? $top : 'Mixed portfolio';
    }

    /** "Residential 30 units · Commercial 6 units" */
    public static function mixLabel(array $mix): string
    {
        return collect($mix)->map(fn ($u, $g) => $g.($u ? ' '.number_format($u).' '.($u === 1 ? 'unit' : 'units') : ''))->join(' · ');
    }

    public const BILLING_NOW = ['Excel / by hand', 'Paybill only', 'Another system', 'New building, nothing yet'];

    public const WINDOWS = ['08:00–10:00', '10:00–12:00', '12:00–14:00', '14:00–16:00', '16:00–18:00'];

    public const CHANNELS = ['Phone call', 'WhatsApp', 'Email', 'Video call', 'Visit at our office'];

    public function __construct(private ContactService $contacts)
    {
    }

    public static function findByRef(string $ref): ?Company
    {
        return Company::withoutGlobalScope('customers')->where('crm_profile->demo_ref', strtoupper($ref))->first();
    }

    /** @param array<string,mixed> $data validated form data */
    public function submit(Company $company, array $data, string $ref): CrmFollowUp
    {
        $properties = collect($data['properties'] ?? [])
            ->map(fn ($p) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, $p))
            ->filter(fn ($p) => ($p['name'] ?? '') !== '' || (int) ($p['units'] ?? 0) > 0)
            ->values()->all();
        $totalUnits = array_sum(array_map(fn ($p) => (int) ($p['units'] ?? 0), $properties));
        $mix = self::mix($properties);
        $when = Carbon::parse($data['contact_date'].' '.substr($data['contact_window'], 0, 5), 'Africa/Nairobi');

        $followUp = DB::transaction(function () use ($company, $data, $properties, $totalUnits, $mix, $when, $ref) {
            [$person] = $this->contacts->upsertPerson([
                'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null, 'country_code' => 'KE',
            ], 'website_demo');
            $this->contacts->attachContact($company, $person, $data['job_title'] ?? null);

            if (in_array($company->crm_stage, ['new', 'contacted', 'qualified', 'nurture', 'lost', null], true)) {
                $this->contacts->setStage($company, 'demo_booked');
            }
            $profile = array_merge($company->crm_profile ?? [], array_filter([
                'estimated_units' => $totalUnits ?: ($company->profile('estimated_units') ?: null),
                'requester_role' => self::ROLES[$data['role']] ?? null,
                'property_class' => self::propertyClass($mix),
                'property_mix' => $mix ? self::mixLabel($mix) : null,
                'property_types' => implode(', ', array_values(array_unique(array_filter(array_column($properties, 'type'))))) ?: null,
                'needs_verification' => false,
            ], fn ($v) => $v !== null));
            $company->update(['crm_profile' => $profile]);

            $demo = $company->followUps()->create([
                'user_id' => $person->id,
                'assigned_to' => $company->owner_id,
                'type' => 'demo',
                'status' => 'open',
                'title' => 'Demo request ('.$ref.'): '.$person->name.', '.($totalUnits ? number_format($totalUnits).' units' : count($properties).' properties'),
                'notes' => $data['message'] ?? null,
                'due_at' => $when->copy()->timezone(config('app.timezone')),
                'details' => [
                    'ref' => $ref,
                    'role' => $data['role'],
                    'interests' => array_values(array_intersect(array_keys(self::INTERESTS), $data['interests'] ?? [])),
                    'properties' => $properties,
                    'units' => $totalUnits,
                    'mix' => $mix,
                    'property_class' => self::propertyClass($mix),
                    'billing_now' => $data['billing_now'] ?? null,
                    'contact' => ['date' => $data['contact_date'], 'window' => $data['contact_window'], 'channel' => $data['channel']],
                    'project_type' => self::ROLES[$data['role']] ?? null,
                    'consent_at' => now()->toDateTimeString(),
                ],
            ]);
            $this->addOpenProperties($company, $properties, $ref, $demo->id, $demo->details['interests'] ?? []);

            return $demo;
        });

        \App\Modules\CRM\Support\EmailTracking::mark(\App\Modules\CRM\Support\EmailTracking::latestFor($company), 'booked');
        $followUp->load('company', 'contact', 'assignee');
        $pdf = $this->pdf($followUp);
        $followUp->update(['details' => array_merge($followUp->details, ['pdf' => $pdf])]);
        $this->contacts->log($company, 'note', 'Demo form submitted ('.$ref.')', 'PDF saved with the demo request.', ['pdf' => $pdf, 'demo_id' => $followUp->id], $followUp->contact);

        $to = $followUp->assignee?->email;
        if ($to) {
            try {
                Mail::to($to)->send(new DemoRequestNotice($followUp->fresh(['company', 'contact', 'assignee'])));
            } catch (\Throwable $e) {
                Log::warning('Demo request notice failed', ['follow_up' => $followUp->id, 'error' => $e->getMessage()]);
            }
        }
        // The prospect's own copy, with the same PDF.
        $mine = $followUp->contact?->email;
        if ($mine && filter_var($mine, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($mine)->send(new DemoRequestCopy($followUp->fresh(['company', 'contact', 'assignee'])));
            } catch (\Throwable $e) {
                Log::warning('Demo request copy failed', ['follow_up' => $followUp->id, 'error' => $e->getMessage()]);
            }
        }

        return $followUp;
    }

    public const PROPERTY_STATUSES = ['open' => 'Open', 'demo_done' => 'Demo done', 'proposal' => 'Proposal sent', 'won' => 'Won', 'lost' => 'Lost'];

    /**
     * Each property they listed becomes an open property on the company (crm_profile.prospect_properties),
     * which account managers work on the Open properties page until it is won or lost.
     */
    private function addOpenProperties(Company $company, array $properties, string $ref, int $demoId, array $interests): void
    {
        $list = $company->fresh()->crm_profile['prospect_properties'] ?? [];
        foreach ($properties as $p) {
            $name = trim((string) ($p['name'] ?? '')) ?: 'Unnamed property';
            $key = strtolower($name).'|'.strtolower((string) ($p['location'] ?? ''));
            if (collect($list)->contains(fn ($x) => strtolower($x['name']).'|'.strtolower((string) ($x['location'] ?? '')) === $key)) {
                continue;
            }
            $list[] = [
                'id' => strtolower(\Illuminate\Support\Str::random(8)),
                'name' => $name, 'type' => $p['type'] ?? null, 'class' => self::typeGroup($p['type'] ?? null),
                'location' => $p['location'] ?? null, 'units' => (int) ($p['units'] ?? 0),
                'ref' => $ref, 'demo_id' => $demoId, 'status' => 'open', 'offerings' => array_values($interests),
                'added_at' => now()->toDateTimeString(),
            ];
        }
        $company->forceFill(['crm_profile' => array_merge($company->fresh()->crm_profile ?? [], ['prospect_properties' => $list])])->saveQuietly();
    }

    /** Render the submission as a PDF on the private disk; returns its storage path. */
    public function pdf(CrmFollowUp $followUp): string
    {
        $path = 'crm/demo-requests/'.$followUp->company_id.'/'.$followUp->detail('ref').'-'.$followUp->id.'.pdf';
        Storage::disk('local')->put($path, Pdf::loadView('crm.pdf.demo-request', ['f' => $followUp])->setPaper('a4')->output());

        return $path;
    }
}
