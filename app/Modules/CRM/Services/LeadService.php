<?php

namespace App\Modules\CRM\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Models\WaitlistEntry;
use App\Modules\CRM\Support\Countries;
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
     * @param  array{name: string, email: ?string, phone: ?string, company: ?string, country_code: string, ip: ?string, user_agent: ?string}  $data
     * @return array{entry: WaitlistEntry, already: bool}
     */
    public function joinWaitlist(array $data): array
    {
        return DB::transaction(function () use ($data) {
            [$user] = $this->contacts->upsertPerson($data + ['country_is_home' => false], 'website_waitlist');
            $company = $this->companyFor($data, $user, 'Website waitlist', 'nurture');

            $entry = WaitlistEntry::firstOrNew(['user_id' => $user->id, 'country_code' => $data['country_code']]);
            $already = $entry->exists;
            $entry->company_id ??= $company?->id;
            $entry->details = array_merge($entry->details ?? [], array_filter(['ip' => $data['ip'] ?? null, 'user_agent' => Str::limit((string) ($data['user_agent'] ?? ''), 250, '')]));
            $entry->save();

            return ['entry' => $entry, 'already' => $already];
        });
    }

    /**
     * Live country: the demo goes straight to the CRM. Elsewhere it waits on the waitlist with its
     * details until we launch there.
     *
     * @param  array{name: string, company: string, email: ?string, phone: string, country_code: string, project_type: ?string, units: ?int, message: ?string, source_page: ?string, ip: ?string, user_agent: ?string}  $data
     * @return array{already: bool, waitlisted: bool}
     */
    public function bookDemo(array $data): array
    {
        return DB::transaction(function () use ($data) {
            [$user] = $this->contacts->upsertPerson($data, 'website_demo');
            $demo = array_filter([
                'project_type' => $data['project_type'] ?? null,
                'units' => $data['units'] ?? null,
                'message' => $data['message'] ?? null,
                'source_page' => $data['source_page'] ?? null,
            ]);

            if (! Countries::isLive($data['country_code'])) {
                $company = $this->companyFor($data, $user, 'Website demo request', 'nurture');
                $entry = WaitlistEntry::firstOrNew(['user_id' => $user->id, 'country_code' => $data['country_code']]);
                $already = $entry->exists && $entry->status === 'demo_requested';
                $entry->fill(['status' => 'demo_requested', 'company_id' => $entry->company_id ?? $company?->id]);
                $entry->details = array_merge($entry->details ?? [], $demo, ['requested_at' => now()->toDateTimeString()]);
                $entry->save();

                return ['already' => $already, 'waitlisted' => true];
            }

            $company = $this->companyFor($data, $user, 'Website demo request', 'demo_booked');

            // Same person asking again within a day: keep one request, refresh it.
            $recent = $company->followUps()->where('type', 'demo')->where('user_id', $user->id)
                ->whereIn('status', ['open', 'scheduled'])->where('created_at', '>=', now()->subDay())->first();
            if ($recent) {
                $recent->update(['details' => array_merge($recent->details ?? [], $demo)]);
                $this->contacts->log($company, 'note', "{$user->name} sent the demo form again", $data['message'] ?? null, [], $user);

                return ['already' => true, 'waitlisted' => false];
            }

            if (in_array($company->crm_stage, self::EARLY_STAGES, true)) {
                $this->contacts->setStage($company, 'demo_booked', null, null);
            }

            // One row is both the request and the call-back: the website promises a call within one working day.
            $company->followUps()->create([
                'user_id' => $user->id,
                'assigned_to' => $company->owner_id,
                'type' => 'demo',
                'status' => 'open',
                'title' => "Demo for {$user->name}: call to confirm a time",
                'notes' => $data['message'] ?? null,
                'due_at' => $this->nextWorkingDay(now()),
                'details' => $demo,
            ]);

            return ['already' => false, 'waitlisted' => false];
        });
    }

    /** We launched in a country: turn its waitlist into CRM follow-ups. Returns how many moved. */
    public function launchCountry(string $code, ?int $authorId): int
    {
        $moved = 0;
        WaitlistEntry::with('user')->where('country_code', $code)->whereIn('status', ['waiting', 'demo_requested', 'invited'])
            ->chunkById(100, function ($entries) use ($code, $authorId, &$moved) {
                foreach ($entries as $entry) {
                    DB::transaction(function () use ($entry, $code, $authorId) {
                        // No company given: the person is the prospect (an individual landlord).
                        $company = $entry->company ?? $this->contacts->upsertCompany([
                            'name' => $entry->user->name, 'country_code' => $code, 'crm_stage' => 'new', 'business_type' => 'Individual landlord',
                        ], 'Waitlist '.Countries::name($code))[0];
                        $this->contacts->attachContact($company, $entry->user);
                        $wantsDemo = $entry->status === 'demo_requested';
                        $company->followUps()->create([
                            'user_id' => $entry->user_id,
                            'assigned_to' => $company->owner_id,
                            'created_by' => $authorId,
                            'type' => $wantsDemo ? 'demo' : 'call',
                            'status' => 'open',
                            'title' => $wantsDemo
                                ? "Demo for {$entry->user->name}: call to confirm a time"
                                : "Tell {$entry->user->name} scharge is live in ".Countries::name($code),
                            'due_at' => $this->nextWorkingDay(now()),
                            'details' => array_intersect_key($entry->details ?? [], array_flip(['project_type', 'units', 'message'])) + ['from_waitlist' => $entry->id],
                        ]);
                        $entry->update(['status' => 'converted']);
                    });
                    $moved++;
                }
            });

        return $moved;
    }

    public function requestFeedback(Company $company, string $context, ?User $contact, ?int $requestedBy, ?int $demoId = null): CrmFollowUp
    {
        return $company->followUps()->create([
            'user_id' => $contact?->id,
            'created_by' => $requestedBy,
            'assigned_to' => $company->owner_id ?? $requestedBy,
            'type' => 'feedback',
            'status' => 'open',
            'title' => 'Feedback: '.(CrmFollowUp::FEEDBACK_CONTEXTS[$context] ?? $context),
            'token' => Str::random(40),
            'details' => array_filter(['context' => $context, 'demo_id' => $demoId]),
        ]);
    }

    /** Store a response. Low scores open a recovery call for the account owner. */
    public function submitFeedback(CrmFollowUp $feedback, int $score, ?string $comment): CrmFollowUp
    {
        return DB::transaction(function () use ($feedback, $score, $comment) {
            $feedback->update(['score' => $score, 'notes' => $comment, 'status' => 'done', 'done_at' => now(), 'outcome' => "{$score}/10"]);

            if ($score <= CrmFollowUp::LOW_SCORE && ! $feedback->detail('recovery_id')) {
                $recovery = $feedback->company->followUps()->create([
                    'user_id' => $feedback->user_id,
                    'assigned_to' => $feedback->company->owner_id ?? $feedback->assigned_to,
                    'type' => 'call',
                    'status' => 'open',
                    'title' => "Recover: feedback score {$score}/10",
                    'notes' => $comment,
                    'due_at' => $this->nextWorkingDay(now()),
                    'details' => ['feedback_id' => $feedback->id],
                ]);
                $feedback->update(['details' => array_merge($feedback->details ?? [], ['recovery_id' => $recovery->id])]);
            }

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

    private function companyFor(array $data, User $user, string $source, string $stage): ?Company
    {
        if (empty($data['company'])) {
            return null;
        }
        [$company] = $this->contacts->upsertCompany([
            'name' => $data['company'],
            'email' => $data['email'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'estimated_units' => $data['units'] ?? null,
            'crm_stage' => $stage,
        ], $source);
        $this->contacts->attachContact($company, $user);

        return $company;
    }
}
