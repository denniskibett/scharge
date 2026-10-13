<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CRM\Models\CrmCompany;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmFollowUpController extends Controller
{
    public function __construct(private ContactService $contacts)
    {
    }

    public function index(Request $request): View
    {
        $view = $request->input('view', 'open');
        $mine = $request->boolean('mine');

        $followUps = CrmFollowUp::query()
            ->with(['company:id,name,tier,stage', 'contact:id,name,phone,email', 'assignee:id,name'])
            ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($view === 'overdue', fn ($q) => $q->overdue())
            ->when($view === 'today', fn ($q) => $q->open()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]))
            ->when($view === 'open', fn ($q) => $q->open())
            ->when($view === 'done', fn ($q) => $q->whereNotNull('completed_at'))
            ->orderBy($view === 'done' ? 'completed_at' : 'due_at', $view === 'done' ? 'desc' : 'asc')
            ->paginate(30)
            ->withQueryString();

        return view('crm.follow-ups', [
            'followUps' => $followUps,
            'view' => $view,
            'mine' => $mine,
            'counts' => [
                'overdue' => CrmFollowUp::overdue()->count(),
                'today' => CrmFollowUp::open()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
                'open' => CrmFollowUp::open()->count(),
            ],
        ]);
    }

    public function store(Request $request, CrmCompany $company): RedirectResponse
    {
        $input = $request->validate([
            'type' => ['required', Rule::in(array_keys(CrmFollowUp::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', Rule::in(User::role('sysadmin')->pluck('users.id'))],
            'user_id' => ['nullable', Rule::in($company->contacts()->pluck('users.id'))],
        ]);

        $followUp = $company->followUps()->create($input + [
            'assigned_to' => $input['assigned_to'] ?? $company->owner_id ?? $request->user()->id,
            'created_by' => $request->user()->id,
        ]);
        $company->update(['next_action' => $followUp->title, 'next_action_at' => $followUp->due_at]);
        $this->contacts->log($company, 'follow_up', "Follow-up scheduled: {$followUp->title} (".$followUp->due_at->format('D j M, H:i').')', null, ['follow_up_id' => $followUp->id], null, $request->user()->id);

        return back()->with('success', 'Follow-up scheduled.');
    }

    /** Mark done, record what happened, and optionally book the next step in the same go. */
    public function complete(Request $request, CrmFollowUp $followUp): RedirectResponse
    {
        $input = $request->validate([
            'outcome' => ['required', 'string', 'max:255'],
            'next_title' => ['nullable', 'string', 'max:255'],
            'next_due_at' => ['nullable', 'required_with:next_title', 'date', 'after:now'],
        ], ['outcome.required' => 'Say what happened so the next person has context.']);

        $company = $followUp->company;
        $followUp->update(['completed_at' => now(), 'outcome' => $input['outcome']]);
        $this->contacts->log($company, 'follow_up', "Done: {$followUp->title}", $input['outcome'], ['follow_up_id' => $followUp->id], $followUp->contact, $request->user()->id);
        $company->update(['last_contacted_at' => now()]);

        if (! empty($input['next_title'])) {
            $next = $company->followUps()->create([
                'user_id' => $followUp->user_id,
                'assigned_to' => $followUp->assigned_to ?? $request->user()->id,
                'created_by' => $request->user()->id,
                'type' => $followUp->type,
                'title' => $input['next_title'],
                'due_at' => $input['next_due_at'],
            ]);
            $company->update(['next_action' => $next->title, 'next_action_at' => $next->due_at]);
            $this->contacts->log($company, 'follow_up', "Follow-up scheduled: {$next->title} (".$next->due_at->format('D j M, H:i').')', null, ['follow_up_id' => $next->id], null, $request->user()->id);
        } else {
            $upcoming = $company->openFollowUps()->first();
            $company->update(['next_action' => $upcoming?->title, 'next_action_at' => $upcoming?->due_at]);
        }

        $message = 'Follow-up completed.';
        if (empty($input['next_title']) && in_array($company->stage, CrmCompany::OPEN_STAGES, true) && ! $company->openFollowUps()->exists()) {
            $message .= " {$company->name} has no next step booked. Schedule one so they don't go cold.";
        }

        return back()->with('success', $message);
    }
}
