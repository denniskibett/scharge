<?php

namespace App\Modules\CRM\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\CrmFeedback;
use App\Modules\CRM\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public feedback page sent to customers as a private link: /feedback/{token}. */
class SiteFeedbackController extends Controller
{
    public function show(string $token): View
    {
        $feedback = CrmFeedback::with('company', 'user')->where('token', $token)->firstOrFail();

        return view('home.feedback', compact('feedback'));
    }

    public function store(Request $request, string $token, LeadService $leads): RedirectResponse
    {
        $feedback = CrmFeedback::where('token', $token)->firstOrFail();

        if ($feedback->submitted_at) {
            return back()->with('site_alert', ['type' => 'success', 'title' => 'Thanks, we already have your answer.', 'message' => 'You can close this page.']);
        }

        $input = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], ['score.required' => 'Pick a score from 0 to 10.']);

        $leads->submitFeedback($feedback, (int) $input['score'], $input['comment'] ?? null);

        $message = $input['score'] <= CrmFeedback::LOW_SCORE
            ? 'Sorry we fell short. Someone from our team will call you within one working day to put it right.'
            : 'Thank you. Your answer goes straight to the team.';

        return back()->with('site_alert', ['type' => 'success', 'title' => 'Feedback received.', 'message' => $message]);
    }
}
