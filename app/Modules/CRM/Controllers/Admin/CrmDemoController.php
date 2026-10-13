<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\CrmDemoRequest;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmDemoController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status');

        $demos = CrmDemoRequest::query()
            ->with(['user:id,name,phone,email', 'company:id,name,stage', 'country:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('crm.demos', [
            'demos' => $demos,
            'status' => $status,
            'counts' => CrmDemoRequest::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function update(Request $request, CrmDemoRequest $demo, ContactService $contacts, LeadService $leads): RedirectResponse
    {
        $input = $request->validate([
            'status' => ['required', Rule::in(array_keys(CrmDemoRequest::STATUSES))],
            'scheduled_at' => ['nullable', 'required_if:status,scheduled', 'date'],
        ], ['scheduled_at.required_if' => 'Pick the date and time of the demo.']);

        $demo->update($input);
        $company = $demo->company;
        $message = 'Demo updated.';

        if ($company) {
            $label = CrmDemoRequest::STATUSES[$input['status']];
            $when = $demo->scheduled_at ? ' for '.$demo->scheduled_at->format('D j M, H:i') : '';
            $contacts->log($company, 'demo_request', "Demo {$label}{$when}", null, ['demo_request_id' => $demo->id], $demo->user, $request->user()->id);

            if ($input['status'] === 'scheduled') {
                // Confirmation calls for this demo are done once it's on the calendar.
                $demo->followUps()->whereNull('completed_at')->update(['completed_at' => now(), 'outcome' => "Demo scheduled{$when}"]);
                $company->update(['next_action' => 'Run demo', 'next_action_at' => $demo->scheduled_at]);
            }
            if (in_array($input['status'], ['completed', 'no_show', 'cancelled'], true)) {
                $upcoming = $company->openFollowUps()->first();
                $company->update(['next_action' => $upcoming?->title, 'next_action_at' => $upcoming?->due_at]);
            }
            // Close the loop: after a demo, ask how it went.
            if ($input['status'] === 'completed' && ! $company->feedback()->where('demo_request_id', $demo->id)->exists()) {
                $feedback = $leads->requestFeedback($company, 'demo', $demo->user, $request->user()->id, $demo->id);
                $message .= ' Feedback link: '.route('site.feedback.show', $feedback->token);
            }
        }

        return back()->with('success', $message);
    }
}
