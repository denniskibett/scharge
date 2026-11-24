<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Controllers\Admin\CrmCompanyController;
use App\Modules\CRM\Controllers\Admin\CrmReplyController;
use App\Modules\CRM\Controllers\Admin\CrmSalesController;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Models\WaitlistEntry;
use App\Modules\CRM\Services\CommissionService;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\OutreachService;
use App\Modules\CRM\Services\ReplyService;
use App\Modules\CRM\Support\Countries;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\CRM\Support\Normalizer;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\CRM\Support\Playbook;
use App\Modules\CRM\Support\OpenPipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The CRM in the scharge app for scharge's sales people (sysadmin, account_manager, sales): the same book as the web
 * CRM (app/Modules/CRM). Every query goes through CrmAccess, so an account manager sees and changes only the prospects
 * they own plus unassigned leads; sysadmins and "crm.all" people see the whole pipeline. Sales and commission of other
 * people stay sysadmin-only (CrmAccess::seesAll), as on CRM › Sales tracker.
 * Routes: routes/api.php (MobileCan 'crm' + crm.view / crm.manage).
 */
class CrmController extends Controller
{
    use Presents;

    public function __construct(private ContactService $contacts)
    {
    }

    /** "My day": replies waiting, follow-ups due, new requests, and the book in numbers (CRM index stats). */
    public function summary(Request $request)
    {
        $user = $request->user();
        $mine = fn () => CrmAccess::companies(Company::crm(), $user);
        $fu = fn () => CrmAccess::followUps(CrmFollowUp::query(), $user);
        $todo = fn ($q) => $q->whereIn('status', ['open', 'scheduled'])->whereNotIn('type', CrmFollowUp::NOT_TODO);

        $replies = $mine()->where('companies.reply_pending', 1)->orderByDesc('crm_profile->reply->at')->limit(20)
            ->get(['companies.id', 'name', 'tier', 'crm_stage', 'owner_id', 'crm_profile']);
        $due = $fu()->todo()->whereNotNull('due_at')->where('due_at', '<=', now()->endOfDay())
            ->with(['company:id,name,crm_stage', 'contact:id,name,phone,email'])->orderBy('due_at')->limit(30)->get();

        return response()->json([
            'sees_all' => CrmAccess::seesPipeline($user),
            'stats' => [
                'open' => $mine()->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->count(),
                'replied' => $mine()->where('companies.reply_pending', 1)->count(),
                'overdue' => $fu()->overdue()->count(),
                'due_today' => $fu()->dueToday()->count(),
                'unattended' => $mine()->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->whereDoesntHave('followUps', $todo)->count(),
                'new_requests' => $fu()->whereIn('type', array_keys(CrmFollowUp::REQUEST_TYPES))->where('status', 'open')->count(),
                'waitlist' => WaitlistEntry::whereIn('status', ['waiting', 'demo_requested'])->count(),
            ],
            'replies' => $replies->map(fn ($c) => $this->prospectRow($c))->values(),
            'due' => $due->map(fn ($f) => $this->followUp($f))->values(),
            'stages' => self::stages(),
        ]);
    }

    /** The pipeline / prospect list, with the web list's main filters (q, stage, attention, segment, county). */
    public function prospects(Request $request)
    {
        $user = $request->user();
        $todo = fn ($q) => $q->whereIn('status', ['open', 'scheduled'])->whereNotIn('type', CrmFollowUp::NOT_TODO);
        $attention = $request->query('attention');
        $term = trim((string) $request->query('q'));

        // Phone-sized and fast: page on indexed columns only (replies first, then tier, then name); the next-due date
        // is looked up for the rows on this page, not grouped over every follow-up as the web list does for sorting.
        $page = CrmAccess::companies(Company::crm(), $user)
            ->select('companies.id')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->when($request->query('stage'), fn ($q, $stage) => $stage === 'open' ? $q->whereIn('crm_stage', Company::OPEN_CRM_STAGES) : $q->where('crm_stage', $stage))
            ->when($request->query('owner') === 'me', fn ($q) => $q->where('owner_id', $user->id))
            ->when($request->query('owner') === 'none', fn ($q) => $q->whereNull('owner_id'))
            ->when($attention === 'replied', fn ($q) => $q->where('companies.reply_pending', 1))
            ->when($attention === 'overdue', fn ($q) => $q->whereHas('followUps', fn ($f) => $f->overdue()))
            ->when($attention === 'unattended', fn ($q) => $q->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->whereDoesntHave('followUps', $todo))
            ->when($request->query('segment'), fn ($q, $v) => $q->where('crm_profile->segment', $v))
            ->when($request->query('county'), fn ($q, $v) => $q->where('crm_profile->county', $v))
            ->orderByDesc('companies.reply_pending')->orderBy('companies.tier')->orderBy('companies.name')->orderBy('companies.id')
            ->paginate(min(50, max(10, (int) $request->query('per_page', 25))));

        $ids = collect($page->items())->pluck('id');
        $models = Company::withoutGlobalScope('customers')->with('owner:id,name')->findMany($ids)->keyBy('id');
        $due = $todo(CrmFollowUp::query())->whereIn('company_id', $ids->all() ?: [0])->selectRaw('company_id, MIN(due_at) as next_due_at')
            ->groupBy('company_id')->pluck('next_due_at', 'company_id');
        $rows = $ids->map(fn ($id) => isset($models[$id]) ? $this->prospectData($models[$id], $due[$id] ?? null) : null)->filter()->values();

        return response()->json(['data' => $rows, 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'stages' => self::stages()]);
    }

    /** One prospect: details, contacts, to-dos and the latest history. */
    public function prospect(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $rows = $company->followUps()->with(['contact:id,name,phone,email', 'assignee:id,name'])->orderByDesc('created_at')->orderByDesc('id')->limit(80)->get();

        return response()->json($this->prospectRow($company) + [
            'contacts' => $rows->where('type', 'contact')->map(fn ($r) => ['name' => $r->contact?->name, 'phone' => $r->contact?->phone, 'email' => $r->contact?->email,
                'role' => $r->detail('role'), 'primary' => (bool) $r->detail('primary')])->values(),
            'todo' => $rows->filter(fn ($r) => $r->is_open && ! in_array($r->type, CrmFollowUp::NOT_TODO, true))->sortBy('due_at')->map(fn ($f) => $this->followUp($f))->values(),
            'history' => $rows->filter(fn ($r) => ! $r->is_open && $r->type !== 'contact')->take(30)->map(fn ($f) => $this->followUp($f))->values(),
            'stages' => self::stages(),
            'action_types' => array_intersect_key(CrmFollowUp::TYPES, array_flip(CrmFollowUp::ACTION_TYPES)),
        ]);
    }

    /** Move the stage (same as the web Status card: "won" turns a prospect into a customer). */
    public function stage(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $input = $request->validate([
            'stage' => ['required', Rule::in(array_keys(Company::CRM_STAGES))],
            'reason' => ['nullable', 'required_if:stage,lost', 'string', 'max:255'],
        ], ['reason.required_if' => 'Say why it was lost.']);
        $author = $request->user()->id;
        $this->contacts->setStage($company, $input['stage'], $author, $input['reason'] ?? null);
        if ($input['stage'] === 'won' && $company->lifecycle === 'prospect') {
            $company->update(['lifecycle' => 'customer', 'is_active' => true]);
            $this->contacts->log($company, 'note', 'Became a customer', 'Set up their subscription from Admin › Companies.', [], null, $author);
        }

        return response()->json($this->prospectRow($company->fresh()));
    }

    /** Log something that happened (mode=log) or schedule something to do (mode=schedule), as on the web. */
    public function addFollowUp(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $schedule = $request->input('mode') === 'schedule';
        $input = $request->validate([
            'mode' => ['required', Rule::in(['log', 'schedule'])],
            'type' => ['required', Rule::in(CrmFollowUp::ACTION_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_at' => [$schedule ? 'required' : 'nullable', 'date'],
        ]);
        $author = $request->user()->id;
        $row = $company->followUps()->create([
            'assigned_to' => $company->owner_id ?? $author, 'created_by' => $author, 'type' => $input['type'],
            'status' => $schedule ? 'open' : 'done', 'title' => $input['title'], 'notes' => $input['notes'] ?? null,
            'due_at' => $schedule ? $input['due_at'] : null, 'done_at' => $schedule ? null : now(),
        ]);
        if (! $schedule && $input['type'] !== 'note' && $company->crm_stage === 'new') {
            $this->contacts->setStage($company, 'contacted', $author);
        }

        return response()->json($this->followUp($row->load('company:id,name,crm_stage')), 201);
    }

    /** Clear a prospect's reply alert, optionally moving the stage (CRM › reply "Mark handled"). */
    public function replyHandled(Request $request, int $id, ReplyService $replies)
    {
        $company = $this->company($request, $id);
        $input = $request->validate([
            'stage' => ['nullable', Rule::in(array_keys(CrmReplyController::stages()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $replies->handled($company, $request->user(), $input['stage'] ?? null, $input['note'] ?? null);

        return response()->json($this->prospectRow($company->fresh()));
    }

    /** Answer the reply in the same e-mail thread (from the prospect's account manager's work address). */
    public function replyAnswer(Request $request, int $id, ReplyService $replies)
    {
        $company = $this->company($request, $id);
        $input = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'stage' => ['nullable', Rule::in(array_keys(CrmReplyController::stages()))],
        ]);
        try {
            $replies->answer($company, $request->user(), $input['body'], $input['stage'] ?? null);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'The answer could not be sent: '.$e->getMessage()], 422);
        }

        return response()->json($this->prospectRow($company->fresh()));
    }

    /** Playbook: how to approach them, what to sell, and the first email (saved draft or the segment template), as on the web. */
    public function playbook(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $manager = $company->owner ?? $request->user();
        $book = Playbook::for($company);
        $draft = Playbook::draft($company, $manager);
        $people = $company->followUps()->where('type', 'contact')->with('contact:id,name,email,phone')->get()->toBase();
        $phones = $people->map(fn ($r) => $r->contact?->phone ? ['name' => $r->contact->name, 'phone' => $r->contact->phone] : null)
            ->prepend($company->phone ? ['name' => $company->name, 'phone' => $company->phone] : null)->filter()->unique('phone')->values();
        $emails = $people
            ->map(fn ($r) => strtolower(trim((string) $r->contact?->email)))->prepend(strtolower(trim((string) $company->email)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values();

        return response()->json([
            'label' => $book['label'] ?? null,
            'approach' => $book['approach'] ?? [],
            'offers' => $book['offers'] ?? [],
            'objections' => collect($book['objections'] ?? [])->map(fn ($a, $q) => ['q' => $q, 'a' => $a])->values(),
            'subject' => $draft['subject'],
            'body' => EmailBody::toText($draft['body']),
            'saved_draft' => (bool) $company->profile('email_body'),
            'do_not_email' => (bool) $company->profile('do_not_email'),
            'to' => $emails->first(),
            'emails' => $emails,
            'phones' => $phones,
            'signed_by' => $manager->name,
            'signals' => Playbook::signals($company),
            'pains' => collect(Playbook::pains($company))->map(fn ($x, $k) => ['key' => $k, 'problem' => $x[0], 'fix' => $x[1]])->values(),
            'chosen_pains' => array_values(array_filter(explode(',', (string) $company->profile('pains')))),
            'research' => $company->profile('research'),
            // The other way in: their private demo form. Shared by WhatsApp or SMS from the phone when email is not the way.
            'form_url' => $url = Playbook::demoUrl($company),
            'form_message' => 'Hello '.preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name).', this is '.$manager->name.' from scharge. '
                .'This short form tells us how you run your properties so we can show you what fits: '.$url,
            'form_filled' => $company->followUps()->where('type', 'demo')->exists(),
            'follow_up_days' => [2 => '2 working days', 3 => '3 working days', 5 => '1 week', 10 => '2 weeks'],
        ]);
    }

    /** Save the email draft for this prospect (reset=true goes back to the playbook template). */
    public function savePlaybookDraft(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        if ($request->boolean('reset')) {
            $this->setProfile($company, ['email_subject' => null, 'email_body' => null]);
        } else {
            $input = $this->emailInput($request, ['subject' => ['required', 'string', 'max:200']]);
            $this->setProfile($company, ['email_subject' => $input['subject'], 'email_body' => $input['body']]);
        }

        return $this->playbook($request, $id);
    }

    /** Send the first email from the app: same as the web Playbook (logged, follow-up booked, Reply-To the manager). */
    public function sendPlaybook(Request $request, int $id, OutreachService $outreach)
    {
        $company = $this->company($request, $id);
        $input = $this->emailInput($request, [
            'to' => ['required', 'email:rfc', 'max:190'],
            'subject' => ['required', 'string', 'max:200'],
            'follow_up_days' => ['required', 'integer', 'between:1,30'],
            'cc' => ['nullable', 'array', 'max:2'],
            'cc.*' => ['email:rfc', 'max:190'],
        ]);
        abort_if((bool) $company->profile('do_not_email'), 422, $company->name.' asked not to be emailed.');
        try {
            ['manager' => $manager, 'due' => $due] = $outreach->send($company, $request->user(), $input['to'], $input['subject'], $input['body'], (int) $input['follow_up_days'], null, $input['cc'] ?? []);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'The email could not be sent: '.$e->getMessage()], 422);
        }

        return response()->json($this->prospectRow($company->fresh()) + [
            'message' => 'Email sent to '.$input['to'].'. '.$manager->name.' has a follow-up on '.$due->format('D j M').'.',
        ]);
    }

    /** Rewrite the email around the problems they chose and what the account manager found (web: Playbook › Research). */
    public function composePlaybook(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $input = $request->validate([
            'pains' => ['nullable', 'array'],
            'pains.*' => [Rule::in(array_keys(Playbook::pains($company)))],
            'research' => ['nullable', 'string', 'max:600'],
        ]);
        $draft = Playbook::compose($company, $company->owner ?? $request->user(), $input['pains'] ?? [], $input['research'] ?? null);
        $this->setProfile($company, ['research' => $input['research'] ?? null, 'pains' => implode(',', $input['pains'] ?? []),
            'email_subject' => $draft['subject'], 'email_body' => $draft['body']]);

        return $this->playbook($request, $id);
    }

    /** The demo form link was sent from the phone (WhatsApp, SMS or another way): log it and book the next step. */
    public function formShared(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $input = $request->validate(['via' => ['required', Rule::in(['whatsapp', 'sms', 'other'])], 'follow_up_days' => ['nullable', 'integer', 'between:1,30']]);
        $author = $request->user()->id;
        $via = ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'other' => 'a message'][$input['via']];
        $company->followUps()->create(['assigned_to' => $company->owner_id ?? $author, 'created_by' => $author, 'type' => $input['via'] === 'whatsapp' ? 'whatsapp' : 'note',
            'status' => 'done', 'done_at' => now(), 'title' => 'Sent the demo form by '.$via, 'notes' => Playbook::demoUrl($company)]);
        $days = (int) ($input['follow_up_days'] ?? 3);
        $company->followUps()->create(['assigned_to' => $company->owner_id ?? $author, 'created_by' => $author, 'type' => 'call', 'status' => 'open',
            'title' => 'Check they filled the demo form ('.$via.')', 'due_at' => now()->addWeekdays($days)->setTime(10, 0)]);
        if ($company->crm_stage === 'new') {
            $this->contacts->setStage($company, 'contacted', $author);
        }

        return response()->json($this->prospectRow($company->fresh()) + ['message' => 'Logged. A follow-up is booked in '.$days.' working days.']);
    }

    /** Add a prospect (web: CRM › Add prospect). An existing company is opened and filled in, never copied. */
    public function createProspect(Request $request)
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['nullable', 'integer', 'between:1,3'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:150'],
            'country_code' => ['nullable', Rule::in(array_keys(Countries::africa()))],
        ]);
        $input['tier'] ??= 2;
        $input['country_code'] ??= 'KE';
        $input['phone'] = Normalizer::phone($input['phone'] ?? null, Countries::dial($input['country_code']));
        $input['owner_id'] = $request->user()->id;
        [$company, $created] = $this->contacts->upsertCompany($input, 'Added by '.$request->user()->name.' (app)');
        if ($created) {
            $this->contacts->log($company, 'note', 'Prospect added', null, [], null, $request->user()->id);
        }
        CrmAccess::authorizeCompany($company, $request->user());

        return response()->json($this->prospectRow($company->fresh()) + ['created' => $created,
            'message' => $created ? 'Prospect added.' : 'That company is already in the system, so it was opened and its missing details filled in.'], $created ? 201 : 200);
    }

    /** Edit details (web: the company page's Details and Verify cards). Only the fields sent change. */
    public function updateProspect(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $fields = ['website', 'city', 'area', 'estimated_units', 'decision_maker', 'current_system', 'next_action', 'notes', 'business_type', 'phone_alt'];
        $input = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'tier' => ['sometimes', 'required', 'integer', 'between:1,3'],
            'do_not_email' => ['sometimes', 'boolean'],
            'needs_verification' => ['sometimes', 'boolean'],
            'profile' => ['sometimes', 'array'],
            'profile.*' => ['nullable', 'string', 'max:5000'],
        ]);
        $changes = array_intersect_key($input, array_flip(['tier', 'email']));
        if (array_key_exists('phone', $input)) {
            $changes['phone'] = Normalizer::phone($input['phone'], Countries::dial($company->country_code ?: 'KE'));
        }
        if (isset($input['name'])) {
            $changes['name'] = Normalizer::companyName($input['name']);
            $changes['name_key'] = Normalizer::companyKey($changes['name']);
        }
        $profile = $company->crm_profile ?? [];
        $profile = array_merge($profile, array_intersect_key($input['profile'] ?? [], array_flip($fields)));
        foreach (['do_not_email', 'needs_verification'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $profile[$flag] = $input[$flag] ? true : null;
            }
        }
        if (array_key_exists('website', $input['profile'] ?? [])) {
            $profile['website'] = Normalizer::website($input['profile']['website']);
            $changes['domain'] = Normalizer::domain($profile['website'], $changes['email'] ?? $company->email) ?? $company->domain;
        }
        $changes['crm_profile'] = array_filter($profile, fn ($v) => $v !== null && $v !== '');
        $company->update($changes);
        if (array_key_exists('do_not_email', $input)) {
            $this->contacts->log($company, 'note', $input['do_not_email'] ? 'Marked: do not email' : 'Email allowed again', null, [], null, $request->user()->id);
        }

        return $this->prospect($request, $id);
    }

    /** Add a person at the prospect (linked, never copied, if they are already in the system). */
    public function addContact(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'job_title' => ['nullable', 'string', 'max:150'],
        ]);
        [$user] = $this->contacts->upsertPerson($input + ['country_code' => $company->country_code], 'crm_manual');
        $this->contacts->attachContact($company, $user, $input['job_title'] ?? null, $request->user()->id);

        return $this->prospect($request, $id);
    }

    /** A feedback link to send them (after a demo, onboarding or support), as on the web. */
    public function requestFeedback(Request $request, int $id, \App\Modules\CRM\Services\LeadService $leads)
    {
        $company = $this->company($request, $id);
        $input = $request->validate(['context' => ['required', Rule::in(array_keys(CrmFollowUp::FEEDBACK_CONTEXTS))]]);
        $feedback = $leads->requestFeedback($company, $input['context'], null, $request->user()->id);

        return response()->json(['link' => route('site.feedback.show', $feedback->token), 'contexts' => CrmFollowUp::FEEDBACK_CONTEXTS], 201);
    }

    /** A win-back email drafted for approval (web: Follow-ups › draft): send it as written/edited, or skip it. */
    public function sendDraft(Request $request, int $id, OutreachService $outreach)
    {
        $f = $this->visible($request)->with('company')->findOrFail($id);
        abort_unless($f->detail('winback') && $f->is_open && $f->company, 404, 'No draft waiting here.');
        $input = $request->validate([
            'action' => ['required', Rule::in(['send', 'skip'])],
            'subject' => ['required_if:action,send', 'nullable', 'string', 'max:200'],
            'body' => ['required_if:action,send', 'nullable', 'string', 'max:20000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        if ($input['action'] === 'skip') {
            $f->update(['status' => 'cancelled', 'done_at' => now(), 'outcome' => $input['reason'] ?: 'Skipped']);

            return response()->json($this->followUp($f->fresh()) + ['message' => 'Skipped. No email was sent.']);
        }
        $row = $outreach->sendText($f->company, (string) $f->detail('to'), [], $input['subject'], $input['body'],
            'Win-back email approved by '.$request->user()->name.' (app).', ['winback' => true, 'reason' => $f->detail('reason')], $request->user()->id);
        abort_unless($row, 422, 'Not sent: they asked not to be emailed, or the address is not valid.');
        $f->update(['status' => 'done', 'done_at' => now(), 'outcome' => 'Sent', 'details' => array_merge($f->details ?? [], ['sent_id' => $row->id, 'subject' => $input['subject'], 'body' => $input['body']])]);
        $f->company->followUps()->create(['assigned_to' => $f->company->owner_id, 'created_by' => $request->user()->id, 'type' => 'call', 'status' => 'open',
            'title' => 'Call about the win-back email to '.$f->detail('to'), 'due_at' => now()->addWeekdays(3)->setTime(10, 0)]);

        return response()->json($this->followUp($f->fresh()) + ['message' => 'Sent to '.$f->detail('to').'. A follow-up call is booked in three working days.']);
    }

    /** Follow-ups: overdue, today, open (to do) or done. */
    public function followUps(Request $request)
    {
        $view = in_array($request->query('view'), ['overdue', 'today', 'open', 'done'], true) ? $request->query('view') : 'open';
        $q = $this->visible($request)->with(['company:id,name,tier,crm_stage', 'contact:id,name,phone,email', 'assignee:id,name'])
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($view === 'overdue', fn ($q) => $q->overdue())
            ->when($view === 'today', fn ($q) => $q->dueToday())
            ->when($view === 'open', fn ($q) => $q->todo())
            ->when($view === 'done', fn ($q) => $q->whereIn('type', array_merge(CrmFollowUp::ACTION_TYPES, ['demo']))->whereIn('status', ['done', 'missed', 'cancelled']))
            ->orderBy($view === 'done' ? 'done_at' : 'due_at', $view === 'done' ? 'desc' : 'asc');

        return response()->json($this->page($q, fn ($f) => $this->followUp($f), $request) + ['counts' => [
            'overdue' => $this->visible($request)->overdue()->count(),
            'today' => $this->visible($request)->dueToday()->count(),
            'open' => $this->visible($request)->todo()->count(),
        ]]);
    }

    /** Close, miss, cancel or reschedule a follow-up, and book the next step in one go (web: Follow-ups › Update). */
    public function updateFollowUp(Request $request, int $id)
    {
        $followUp = $this->visible($request)->find($id);
        abort_unless($followUp, 403, 'This follow-up belongs to another account manager.');
        $input = $request->validate([
            'status' => ['required', Rule::in(array_keys(CrmFollowUp::STATUSES))],
            'outcome' => ['nullable', 'required_if:status,done,missed,cancelled', 'string', 'max:255'],
            'due_at' => ['nullable', 'required_if:status,scheduled', 'date'],
            'next_title' => ['nullable', 'string', 'max:255'],
            'next_due_at' => ['nullable', 'required_with:next_title', 'date', 'after:now'],
        ], ['outcome.required_if' => 'Say what happened so the next person has context.', 'due_at.required_if' => 'Pick the date and time.']);
        $author = $request->user()->id;
        $closed = in_array($input['status'], ['done', 'missed', 'cancelled'], true);
        $followUp->update(array_filter([
            'status' => $input['status'], 'outcome' => $input['outcome'] ?? null,
            'due_at' => $input['status'] === 'scheduled' ? $input['due_at'] : null, 'done_at' => $closed ? now() : null,
        ], fn ($v) => $v !== null));
        if (! empty($input['next_title'])) {
            CrmFollowUp::create([
                'company_id' => $followUp->company_id, 'user_id' => $followUp->user_id, 'assigned_to' => $followUp->assigned_to ?? $author,
                'created_by' => $author, 'type' => in_array($followUp->type, CrmFollowUp::ACTION_TYPES, true) ? $followUp->type : 'call',
                'status' => 'open', 'title' => $input['next_title'], 'due_at' => $input['next_due_at'],
            ]);
        }
        $company = $followUp->company;
        $warning = $closed && empty($input['next_title']) && $company && in_array($company->crm_stage, Company::OPEN_CRM_STAGES, true) && ! $company->openFollowUps()->exists()
            ? "{$company->name} has no next step booked. Schedule one so they don't go cold." : null;

        return response()->json($this->followUp($followUp->fresh(['company:id,name,crm_stage', 'contact:id,name,phone,email'])) + ['warning' => $warning]);
    }

    /** Inbound website requests: demos, API keys, listing requests, listing alerts. */
    public function requests(Request $request)
    {
        $type = array_key_exists($request->query('type'), CrmFollowUp::REQUEST_TYPES) ? $request->query('type') : 'demo';
        $q = $this->visible($request)->where('type', $type)->with(['contact:id,name,phone,email', 'company:id,name,crm_stage', 'assignee:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest();

        return response()->json($this->page($q, fn ($f) => $this->followUp($f), $request) + [
            'types' => CrmFollowUp::REQUEST_TYPES,
            'open' => $this->visible($request)->whereIn('type', array_keys(CrmFollowUp::REQUEST_TYPES))->where('status', 'open')
                ->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type'),
        ]);
    }

    public function waitlist(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $q = WaitlistEntry::query()->with(['user:id,name,phone,email', 'company:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($term !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->latest();

        return response()->json($this->page($q, fn ($e) => ['id' => $e->id, 'name' => $e->user?->name, 'phone' => $e->user?->phone, 'email' => $e->user?->email,
            'country' => $e->country_name, 'company' => $e->company?->name, 'status' => $e->status, 'status_label' => WaitlistEntry::STATUSES[$e->status] ?? $e->status,
            'project' => $e->detail('project_type'), 'units' => $e->detail('units'), 'joined_at' => $e->created_at?->toIso8601String()], $request)
            + ['statuses' => WaitlistEntry::STATUSES]);
    }

    public function updateWaitlist(Request $request, int $id)
    {
        $entry = WaitlistEntry::findOrFail($id);
        $entry->update($request->validate(['status' => ['required', Rule::in(array_keys(WaitlistEntry::STATUSES))]]));

        return response()->json(['id' => $entry->id, 'status' => $entry->status, 'status_label' => WaitlistEntry::STATUSES[$entry->status] ?? $entry->status]);
    }

    /**
     * Sales tracker + funnel for this person (sysadmins: the whole team): open deals and the stage funnel of their
     * book, wins and losses in the period, settled platform fees and their commission, forecast. Same rules as
     * CRM › Sales tracker (CommissionService).
     */
    public function sales(Request $request, CommissionService $commission)
    {
        $user = $request->user();
        $seesAll = CrmAccess::seesAll($user);
        $period = array_key_exists($request->query('period'), CrmSalesController::PERIODS) ? $request->query('period') : 'month';
        [$from, $to] = CrmSalesController::range($period);
        $book = fn () => CrmAccess::companies(Company::crm(), $user)->when(! $seesAll, fn ($q) => $q->where('owner_id', $user->id));

        // Numbers from the database; only the late-stage deals are loaded (Support\OpenPipeline: there are ~180,000 open prospects).
        $settings = $commission->settings();
        $all = OpenPipeline::sum(OpenPipeline::groups($book(), $settings));
        $closing = OpenPipeline::closing($book(), 30);
        $moves = OpenPipeline::closedMoves($book()->select('companies.id'), $from, $to);
        $won = $moves->filter(fn ($m) => $m->detail('to') === 'won' && $m->company?->crm_stage === 'won')->unique('company_id');
        $lost = $moves->filter(fn ($m) => $m->detail('to') === 'lost' && $m->company?->crm_stage === 'lost')->unique('company_id');

        $lines = $commission->lines($from, $to, $seesAll ? null : [$user->id]);
        $rate = $seesAll ? null : $commission->rateFor($user->id, $settings);
        $managed = $seesAll ? null : ($commission->managed([$user->id])[$user->id] ?? null);
        $value = fn ($c) => CrmSalesController::value($c);
        $forecast = $all['forecast'];
        $counts = $book()->selectRaw('crm_stage, COUNT(*) as total')->groupBy('crm_stage')->pluck('total', 'crm_stage');

        return response()->json([
            'period' => $period, 'periods' => CrmSalesController::PERIODS, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'scope' => $seesAll ? 'team' : 'mine',
            'funnel' => collect(Company::CRM_STAGES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)])->values(),
            'totals' => [
                'open' => $all['open'], 'pipeline' => self::money($all['pipeline']), 'forecast' => self::money($forecast),
                'won' => $won->count(), 'won_value' => self::money($won->sum(fn ($m) => $value($m->company))), 'lost' => $lost->count(),
                'win_rate' => $won->count() + $lost->count() ? (int) round($won->count() / ($won->count() + $lost->count()) * 100) : null,
                'no_next_step' => $all['no_next_step'],
                'fees' => self::money($lines->sum('fee')), 'commission' => self::money($lines->sum('commission')),
                'rate' => $rate, 'forecast_commission' => $rate !== null ? self::money(round($forecast * $rate / 100)) : null,
                'units' => $managed ? (int) $managed->units : ($seesAll ? null : 0),
                'properties' => $managed ? (int) $managed->properties : ($seesAll ? null : 0),
            ],
            'closing' => $closing->map(fn ($c) => $this->prospectRow($c) + ['value' => self::money($value($c))])->values(),
            'wins' => $won->take(10)->map(fn ($m) => ['id' => $m->company_id, 'name' => $m->company?->name, 'at' => $m->done_at?->toIso8601String(), 'value' => self::money($value($m->company))])->values(),
            // Paid platform invoices of the companies this person owns (commission is earned once a bill is settled).
            'lines' => $seesAll ? [] : $lines->sortByDesc('paid_at')->take(40)->map(fn ($l) => ['company' => $l->company, 'property' => $l->property,
                'invoice' => $l->invoice_number ?? $l->invoice_id, 'period' => $l->period, 'paid_at' => $l->paid_at?->toIso8601String(), 'units' => $l->units,
                'fee' => self::money($l->fee), 'commission' => self::money($l->commission)])->values(),
        ]);
    }

    // ---------------------------------------------------------------------------------------------------------------

    /** The rest of the rules plus the body: written as plain text in the app, stored as the same safe HTML as the web editor. */
    private function emailInput(Request $request, array $rules): array
    {
        $input = $request->validate($rules + ['body' => ['required', 'string', 'max:30000']]);
        $input['body'] = EmailBody::toHtml($input['body']);
        if (trim(EmailBody::toText($input['body'])) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['body' => 'Write the email before saving or sending it.']);
        }

        return $input;
    }

    private function setProfile(Company $company, array $values): void
    {
        $profile = array_merge($company->crm_profile ?? [], $values);
        $company->update(['crm_profile' => array_filter($profile, fn ($v) => $v !== null && $v !== '')]);
    }

    private function company(Request $request, int $id): Company
    {
        $company = Company::crm()->find($id);
        abort_unless($company, 404, 'No such prospect.');
        CrmAccess::authorizeCompany($company, $request->user());

        return $company;
    }

    private function visible(Request $request): Builder
    {
        return CrmAccess::followUps(CrmFollowUp::query(), $request->user());
    }

    private function prospectRow(Company $c, $nextDue = null): array
    {
        return $this->prospectData($c->loadMissing('owner:id,name'), $nextDue);
    }

    private function prospectData(Company $c, $nextDue = null): array
    {
        $reply = $c->profile('reply');
        $pending = (bool) $c->profile('reply_pending', $c->reply_pending); // the column is a stored copy of this

        return [
            'id' => $c->id, 'name' => $c->name, 'tier' => $c->tier, 'stage' => $c->crm_stage, 'stage_label' => Company::CRM_STAGES[$c->crm_stage] ?? $c->crm_stage,
            'owner' => $c->relationLoaded('owner') ? $c->owner?->name : null, 'mine' => $c->owner_id !== null && (int) $c->owner_id === (int) request()->user()?->id, 'unassigned' => $c->owner_id === null,
            'phone' => $c->phone, 'email' => $c->email, 'website' => $c->profile('website'), 'segment' => $c->profile('segment'),
            'place' => collect([$c->profile('city') ?? $c->profile('location'), $c->profile('sub_county'), $c->profile('county')])->filter()->unique()->join(', ') ?: null,
            'units' => $c->profile('units') ?? $c->profile('estimated_units'), 'city' => $c->profile('city'), 'do_not_email' => (bool) $c->profile('do_not_email'), 'expected_close' => $c->profile('expected_close'), 'lost_reason' => $c->profile('lost_reason'),
            'next_due_at' => $nextDue ? \Illuminate\Support\Carbon::parse($nextDue)->toIso8601String() : null,
            'reply' => $pending && is_array($reply) ? ['from' => $reply['name'] ?? $reply['from'] ?? null, 'subject' => $reply['subject'] ?? null,
                'snippet' => $reply['snippet'] ?? null, 'at' => $reply['at'] ?? null, 'opt_out' => (bool) ($reply['opt_out'] ?? false)] : null,
        ];
    }

    private function followUp(CrmFollowUp $f): array
    {
        return [
            'id' => $f->id, 'type' => $f->type, 'type_label' => CrmFollowUp::TYPES[$f->type] ?? $f->type, 'status' => $f->status,
            'status_label' => (in_array($f->type, ['demo', 'viewing', 'api_keys'], true) ? CrmFollowUp::DEMO_STATUSES : CrmFollowUp::STATUSES)[$f->status] ?? $f->status,
            'title' => $f->title, 'notes' => $f->notes, 'outcome' => $f->outcome,
            'due_at' => $f->due_at?->toIso8601String(), 'done_at' => $f->done_at?->toIso8601String(), 'created_at' => $f->created_at?->toIso8601String(),
            'overdue' => (bool) $f->is_overdue, 'open' => (bool) $f->is_open,
            'company' => $f->company ? ['id' => $f->company->id, 'name' => $f->company->name, 'stage' => $f->company->crm_stage] : null,
            'contact' => $f->contact ? ['name' => $f->contact->name, 'phone' => $f->contact->phone, 'email' => $f->contact->email] : null,
            'assigned_to' => $f->relationLoaded('assignee') ? $f->assignee?->name : null,
            'draft' => $f->detail('winback') && $f->is_open ? ['to' => $f->detail('to'), 'subject' => $f->detail('subject'), 'body' => EmailBody::toText((string) $f->detail('body'))] : null,
        ];
    }

    private static function stages(): array
    {
        return collect(Company::CRM_STAGES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    private function page($query, callable $map, Request $request): array
    {
        $p = $query->paginate(min(50, max(10, (int) $request->query('per_page', 25))));

        return ['data' => collect($p->items())->map($map)->values(), 'page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()];
    }
}
