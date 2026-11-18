<?php

namespace App\Modules\CRM\Services;

use App\Models\Company;
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

    public const PROPERTY_TYPES = ['Apartments', 'Gated estate / maisonettes', 'Commercial / offices', 'Mall / retail', 'Short-stay / serviced', 'Student hostel', 'Warehouse / industrial', 'Mixed use', 'Plots / land', 'Other'];

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
        $when = Carbon::parse($data['contact_date'].' '.substr($data['contact_window'], 0, 5), 'Africa/Nairobi');

        $followUp = DB::transaction(function () use ($company, $data, $properties, $totalUnits, $when, $ref) {
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
                'needs_verification' => false,
            ], fn ($v) => $v !== null));
            $company->update(['crm_profile' => $profile]);

            return $company->followUps()->create([
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
                    'billing_now' => $data['billing_now'] ?? null,
                    'contact' => ['date' => $data['contact_date'], 'window' => $data['contact_window'], 'channel' => $data['channel']],
                    'project_type' => self::ROLES[$data['role']] ?? null,
                    'consent_at' => now()->toDateTimeString(),
                ],
            ]);
        });

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

        return $followUp;
    }

    /** Render the submission as a PDF on the private disk; returns its storage path. */
    public function pdf(CrmFollowUp $followUp): string
    {
        $path = 'crm/demo-requests/'.$followUp->company_id.'/'.$followUp->detail('ref').'-'.$followUp->id.'.pdf';
        Storage::disk('local')->put($path, Pdf::loadView('crm.pdf.demo-request', ['f' => $followUp])->setPaper('a4')->output());

        return $path;
    }
}
