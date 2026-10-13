<?php

namespace App\Modules\CRM\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public feedback page sent to customers as a private link: /feedback/{token}. */
class SiteFeedbackController extends Controller
{
    public function show(string $token): View
    {
        return view('home.feedback', ['feedback' => $this->find($token)->load('company', 'contact')]);
    }

    public function store(Request $request, string $token, LeadService $leads): RedirectResponse
    {
        $feedback = $this->find($token);

        if ($feedback->status === 'done') {
            return back()->with('site_alert', ['type' => 'success', 'title' => 'Thanks, we already have your answer.', 'message' => 'You can close this page.']);
        }

        $input = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], ['score.required' => 'Pick a score from 0 to 10.']);

        $leads->submitFeedback($feedback, (int) $input['score'], $input['comment'] ?? null);

        $message = $input['score'] <= CrmFollowUp::LOW_SCORE
            ? 'Sorry we fell short. Someone from our team will call you within one working day to put it right.'
            : 'Thank you. Your answer goes straight to the team.';

        return back()->with('site_alert', ['type' => 'success', 'title' => 'Feedback received.', 'message' => $message]);
    }

    private function find(string $token): CrmFollowUp
    {
        return CrmFollowUp::where('type', 'feedback')->where('token', $token)->firstOrFail();
    }
}
