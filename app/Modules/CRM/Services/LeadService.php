<?php

namespace App\Modules\CRM\Services;

use App\Models\User;
use App\Modules\CRM\Models\Country;
use App\Modules\CRM\Models\CrmCompany;
use App\Modules\CRM\Models\CrmDemoRequest;
use App\Modules\CRM\Models\CrmFeedback;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Models\WaitlistEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** What happens when someone joins the waitlist, books a demo, or leaves feedback. */
class LeadService
{
    /** Stages a new demo request moves a prospect out of. Later stages are left alone. */
    private const EARLY_STAGES = ['new', 'contacted', 'qualified', 'nurture', 'lost'];

    public function __construct(private ContactService $contacts)
    {
    }

    /**
     * @param  array{name: string, email: ?string, phone: ?string, company: ?string, country: ?Country, country_name: ?string, ip: ?string, user_agent: ?string}  $data
     * @return array{entry: WaitlistEntry, already: bool, user: User}
     */
    public function joinWaitlist(array $data): array
    {
        return DB::transaction(function () use ($data) {
            [$user] = $this->contacts->upsertPerson($data + ['country_is_home' => false], 'website_waitlist');

            $company = null;
            if (! empty($data['company'])) {
                [$company, $created] = $this->contacts->upsertCompany([
                    'name' => $data['company'],
                    'email' => $data['email'] ?? null,
                    'country_id' => $data['country']?->id,
                    'stage' => 'nurture',
                ], 'Website waitlist');
                $this->contacts->attachContact($company, $user);
                $this->contacts->log($company, 'waitlist',
                    "{$user->name} joined the waitlist for ".($data['country']?->name ?? $data['country_name'] ?? 'another country'),
                    null, ['created_company' => $created], $user);
            }

            $entry = WaitlistEntry::query()
                ->where('user_id', $user->id)
                ->when($data['country'], fn ($q) => $q->where('country_id', $data['country']->id),
                    fn ($q) => $q->whereNull('country_id')->where('country_name', $data['country_name']))
                ->first();
            $already = (bool) $entry;

            $entry ??= WaitlistEntry::create([
                'user_id' => $user->id,
                'country_id' => $data['country']?->id,
                'country_name' => $data['country'] ? null : $data['country_name'],
                'source' => 'website',
                'ip_address' => $data['ip'] ?? null,
                'user_agent' => Str::limit((string) ($data['user_agent'] ?? ''), 250, ''),
            ]);
            if ($company && ! $entry->crm_company_id) {
                $entry->update(['crm_company_id' => $company->id]);
            }

            return ['entry' => $entry, 'already' => $already, 'user' => $user];
        });
    }

    /**
     * @param  array{name: string, company: string, email: ?string, phone: string, country: ?Country, project_type: ?string, units: ?int, message: ?string, source_page: ?string, ip: ?string, user_agent: ?string}  $data
     * @return array{request: CrmDemoRequest, already: bool, company: CrmCompany}
     */
    public function bookDemo(array $data): array
    {
        return DB::transaction(function () use ($data) {
            [$user] = $this->contacts->upsertPerson($data, 'website_demo');
            [$company, $created] = $this->contacts->upsertCompany([
                'name' => $data['company'],
                'email' => $data['email'] ?? null,
                'country_id' => $data['country']?->id,
                'estimated_units' => $data['units'] ?? null,
                'stage' => 'demo_booked',
            ], 'Website demo request');
            $this->contacts->attachContact($company, $user);

            // Same person asking again within a day: keep one request, refresh it.
            $recent = CrmDemoRequest::where('user_id', $user->id)
                ->where('crm_company_id', $company->id)
                ->whereIn('status', ['new', 'scheduled'])
                ->where('created_at', '>=', now()->subDay())
                ->first();
            if ($recent) {
                $recent->update(array_filter([
                    'project_type' => $data['project_type'] ?? null,
                    'units' => $data['units'] ?? null,
                    'message' => $data['message'] ?? null,
                ]));
                $this->contacts->log($company, 'demo_request', "{$user->name} sent the demo form again", $data['message'] ?? null, [], $user);

                return ['request' => $recent, 'already' => true, 'company' => $company];
            }

            $request = CrmDemoRequest::create([
                'user_id' => $user->id,
                'crm_company_id' => $company->id,
                'country_id' => $data['country']?->id,
                'project_type' => $data['project_type'] ?? null,
                'units' => $data['units'] ?? null,
                'message' => $data['message'] ?? null,
                'source_page' => $data['source_page'] ?? null,
                'ip_address' => $data['ip'] ?? null,
                'user_agent' => Str::limit((string) ($data['user_agent'] ?? ''), 250, ''),
            ]);

            if (! $created && in_array($company->stage, self::EARLY_STAGES, true)) {
                $this->contacts->log($company, 'stage_change', "Stage: {$company->stage_label} → Demo booked (website demo request)", null, ['from' => $company->stage, 'to' => 'demo_booked']);
                $company->stage = 'demo_booked';
            }

            // The website promises a call within one working day.
            $due = $this->nextWorkingDay(now());
            CrmFollowUp::create([
                'crm_company_id' => $company->id,
                'user_id' => $user->id,
                'assigned_to' => $company->owner_id,
                'demo_request_id' => $request->id,
                'type' => 'call',
                'title' => "Call {$user->name} to confirm the demo time",
                'notes' => trim(($data['project_type'] ?? '').(! empty($data['units']) ? " · {$data['units']} units" : '')),
                'due_at' => $due,
            ]);
            $company->fill(['next_action' => 'Confirm demo time', 'next_action_at' => $due])->save();

            $this->contacts->log($company, 'demo_request',
                "{$user->name} booked a demo".($data['project_type'] ? " ({$data['project_type']})" : ''),
                $data['message'] ?? null,
                ['demo_request_id' => $request->id, 'units' => $data['units'] ?? null, 'created_company' => $created],
                $user);

            return ['request' => $request, 'already' => false, 'company' => $company];
        });
    }

    public function requestFeedback(CrmCompany $company, string $context, ?User $contact, ?int $requestedBy, ?int $demoRequestId = null): CrmFeedback
    {
        $feedback = CrmFeedback::create([
            'crm_company_id' => $company->id,
            'user_id' => $contact?->id,
            'demo_request_id' => $demoRequestId,
            'requested_by' => $requestedBy,
            'token' => Str::random(40),
            'context' => $context,
            'requested_at' => now(),
        ]);
        $this->contacts->log($company, 'feedback', 'Feedback requested ('.(CrmFeedback::CONTEXTS[$context] ?? $context).')', null, ['feedback_id' => $feedback->id], $contact, $requestedBy);

        return $feedback;
    }

    /** Store a response. Low scores open a recovery follow-up for the account owner. */
    public function submitFeedback(CrmFeedback $feedback, int $score, ?string $comment): CrmFeedback
    {
        return DB::transaction(function () use ($feedback, $score, $comment) {
            $feedback->fill(['score' => $score, 'comment' => $comment, 'submitted_at' => now()]);

            if ($company = $feedback->company) {
                if ($score <= CrmFeedback::LOW_SCORE && ! $feedback->follow_up_id) {
                    $followUp = CrmFollowUp::create([
                        'crm_company_id' => $company->id,
                        'user_id' => $feedback->user_id,
                        'assigned_to' => $company->owner_id,
                        'type' => 'call',
                        'title' => "Recover: feedback score {$score}/10",
                        'notes' => $comment,
                        'due_at' => $this->nextWorkingDay(now()),
                    ]);
                    $feedback->follow_up_id = $followUp->id;
                }
                $this->contacts->log($company, 'feedback', "Feedback received: {$score}/10", $comment, ['feedback_id' => $feedback->id, 'score' => $score], $feedback->user);
            }

            $feedback->save();

            return $feedback;
        });
    }

    /** Next weekday at 10:00 (Mon–Fri). */
    public function nextWorkingDay(Carbon $from): Carbon
    {
        $due = $from->copy()->addDay()->setTime(10, 0);
        while ($due->isWeekend()) {
            $due->addDay();
        }

        return $due;
    }
}
