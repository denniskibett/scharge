<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Everything on the CRM pivot: to-dos, logged activity, demos and feedback. */
class CrmFollowUpController extends Controller
{
    public function __construct(private ContactService $contacts, private LeadService $leads)
    {
    }

    public function index(Request $request): View
    {
        $view = $request->input('view', 'open');
        $mine = $request->boolean('mine');

        $followUps = CrmFollowUp::query()
            ->with(['company:id,name,tier,crm_stage', 'contact:id,name,phone,email', 'assignee:id,name'])
            ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($view === 'overdue', fn ($q) => $q->overdue())
            ->when($view === 'today', fn ($q) => $q->dueToday())
            ->when($view === 'open', fn ($q) => $q->todo())
            ->when($view === 'done', fn ($q) => $q->whereIn('type', array_merge(CrmFollowUp::ACTION_TYPES, ['demo']))->whereIn('status', ['done', 'missed', 'cancelled']))
            ->orderBy($view === 'done' ? 'done_at' : 'due_at', $view === 'done' ? 'desc' : 'asc')
            ->paginate(30)
            ->withQueryString();

        return view('crm.follow-ups', [
            'followUps' => $followUps,
            'view' => $view,
            'mine' => $mine,
            'counts' => ['overdue' => CrmFollowUp::overdue()->count(), 'today' => CrmFollowUp::dueToday()->count(), 'open' => CrmFollowUp::todo()->count()],
        ]);
    }

    /** Add a row: log something that happened (mode=log) or schedule something to do (mode=schedule). */
    public function store(Request $request, Company $crmCompany): RedirectResponse
    {
        $company = $crmCompany;
        $schedule = $request->input('mode') === 'schedule';
        $input = $request->validate([
            'mode' => ['required', Rule::in(['log', 'schedule'])],
            'type' => ['required', Rule::in(CrmFollowUp::ACTION_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_at' => [$schedule ? 'required' : 'nullable', 'date'],
            'assigned_to' => ['nullable', Rule::in(CrmCompanyController::staff()->pluck('id'))],
            'user_id' => ['nullable', Rule::in($company->contacts()->pluck('users.id'))],
        ]);
        $author = $request->user()->id;

        $company->followUps()->create([
            'user_id' => $input['user_id'] ?? null,
            'assigned_to' => $input['assigned_to'] ?? $company->owner_id ?? $author,
            'created_by' => $author,
            'type' => $input['type'],
            'status' => $schedule ? 'open' : 'done',
            'title' => $input['title'],
            'notes' => $input['notes'] ?? null,
            'due_at' => $schedule ? $input['due_at'] : null,
            'done_at' => $schedule ? null : now(),
        ]);

        // A real conversation moves a brand-new prospect to "contacted".
        if (! $schedule && $input['type'] !== 'note' && $company->crm_stage === 'new') {
            $this->contacts->setStage($company, 'contacted', $author);
        }

        return back()->with('success', $schedule ? 'Follow-up scheduled.' : 'Logged.');
    }

    /**
     * Change a row's status. Done/missed/cancelled need an outcome and can book the next step in one go.
     * Demos: scheduled needs a date; done asks the customer for feedback.
     */
    public function update(Request $request, CrmFollowUp $followUp): RedirectResponse
    {
        $input = $request->validate([
            'status' => ['required', Rule::in(array_keys(CrmFollowUp::STATUSES))],
            'outcome' => ['nullable', 'required_if:status,done,missed,cancelled', 'string', 'max:255'],
            'due_at' => ['nullable', 'required_if:status,scheduled', 'date'],
            'next_title' => ['nullable', 'string', 'max:255'],
            'next_due_at' => ['nullable', 'required_with:next_title', 'date', 'after:now'],
        ], [
            'outcome.required_if' => 'Say what happened so the next person has context.',
            'due_at.required_if' => 'Pick the date and time.',
        ]);
        $author = $request->user()->id;
        $company = $followUp->company;
        $closed = in_array($input['status'], ['done', 'missed', 'cancelled'], true);

        $followUp->update(array_filter([
            'status' => $input['status'],
            'outcome' => $input['outcome'] ?? null,
            'due_at' => $input['status'] === 'scheduled' ? $input['due_at'] : null,
            'done_at' => $closed ? now() : null,
        ], fn ($v) => $v !== null));

        $message = $closed ? 'Marked '.strtolower(CrmFollowUp::STATUSES[$input['status']]).'.' : 'Scheduled for '.$followUp->due_at->format('D j M, H:i').'.';

        if ($followUp->type === 'demo' && $input['status'] === 'done' && ! $company->followUps()->where('type', 'feedback')->where('details->demo_id', $followUp->id)->exists()) {
            $feedback = $this->leads->requestFeedback($company, 'demo', $followUp->contact, $author, $followUp->id);
            $message .= ' Feedback link for the customer: '.route('site.feedback.show', $feedback->token);
        }

        if (! empty($input['next_title'])) {
            $company->followUps()->create([
                'user_id' => $followUp->user_id,
                'assigned_to' => $followUp->assigned_to ?? $author,
                'created_by' => $author,
                'type' => $followUp->type === 'demo' ? 'call' : $followUp->type,
                'status' => 'open',
                'title' => $input['next_title'],
                'due_at' => $input['next_due_at'],
            ]);
        } elseif ($closed && in_array($company->crm_stage, Company::OPEN_CRM_STAGES, true) && ! $company->openFollowUps()->exists()) {
            $message .= " {$company->name} has no next step booked. Schedule one so they don't go cold.";
        }

        return back()->with('success', $message);
    }

    public function demos(Request $request): View
    {
        $status = $request->input('status');

        return view('crm.demos', [
            'demos' => CrmFollowUp::where('type', 'demo')
                ->with(['contact:id,name,phone,email', 'company:id,name,crm_stage,country_code'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => CrmFollowUp::where('type', 'demo')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function feedback(Request $request): View
    {
        $band = $request->input('band');
        $scores = CrmFollowUp::where('type', 'feedback')->whereNotNull('score')->pluck('score');

        return view('crm.feedback', [
            'feedback' => CrmFollowUp::where('type', 'feedback')
                ->with(['company:id,name', 'contact:id,name'])
                ->when($band === 'pending', fn ($q) => $q->where('status', 'open'))
                ->when($band === 'detractor', fn ($q) => $q->whereNotNull('score')->where('score', '<=', CrmFollowUp::LOW_SCORE))
                ->when($band === 'passive', fn ($q) => $q->whereBetween('score', [7, 8]))
                ->when($band === 'promoter', fn ($q) => $q->where('score', '>=', 9))
                ->latest()->paginate(30)->withQueryString(),
            'band' => $band,
            'summary' => [
                'nps' => CrmCompanyController::nps($scores),
                'promoters' => $scores->filter(fn ($s) => $s >= 9)->count(),
                'passives' => $scores->filter(fn ($s) => $s >= 7 && $s <= 8)->count(),
                'detractors' => $scores->filter(fn ($s) => $s <= CrmFollowUp::LOW_SCORE)->count(),
                'pending' => CrmFollowUp::where('type', 'feedback')->where('status', 'open')->count(),
            ],
        ]);
    }

    public function requestFeedback(Request $request, Company $crmCompany): RedirectResponse
    {
        $input = $request->validate([
            'context' => ['required', Rule::in(array_keys(CrmFollowUp::FEEDBACK_CONTEXTS))],
            'user_id' => ['nullable', Rule::in($crmCompany->contacts()->pluck('users.id'))],
        ]);
        $contact = isset($input['user_id']) ? User::find($input['user_id']) : null;
        $feedback = $this->leads->requestFeedback($crmCompany, $input['context'], $contact, $request->user()->id);
        $link = route('site.feedback.show', $feedback->token);

        return back()->with('success', 'Feedback link created: '.$link)->with('feedback_link', $link);
    }
}
