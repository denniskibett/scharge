<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CRM\Models\CrmCompany;
use App\Modules\CRM\Models\CrmFeedback;
use App\Modules\CRM\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $band = $request->input('band');

        $feedback = CrmFeedback::query()
            ->with(['company:id,name', 'user:id,name', 'followUp:id,completed_at,title'])
            ->when($band === 'pending', fn ($q) => $q->whereNull('submitted_at'))
            ->when($band === 'detractor', fn ($q) => $q->whereNotNull('score')->where('score', '<=', CrmFeedback::LOW_SCORE))
            ->when($band === 'passive', fn ($q) => $q->whereBetween('score', [7, 8]))
            ->when($band === 'promoter', fn ($q) => $q->where('score', '>=', 9))
            ->latest('requested_at')
            ->paginate(30)
            ->withQueryString();

        $scores = CrmFeedback::whereNotNull('score')->pluck('score');
        $summary = [
            'responses' => $scores->count(),
            'pending' => CrmFeedback::whereNull('submitted_at')->count(),
            'promoters' => $scores->filter(fn ($s) => $s >= 9)->count(),
            'passives' => $scores->filter(fn ($s) => $s >= 7 && $s <= 8)->count(),
            'detractors' => $scores->filter(fn ($s) => $s <= CrmFeedback::LOW_SCORE)->count(),
        ];
        $summary['nps'] = $summary['responses'] ? (int) round(($summary['promoters'] - $summary['detractors']) / $summary['responses'] * 100) : null;

        return view('crm.feedback', compact('feedback', 'summary', 'band'));
    }

    public function store(Request $request, CrmCompany $company, LeadService $leads): RedirectResponse
    {
        $input = $request->validate([
            'context' => ['required', Rule::in(array_keys(CrmFeedback::CONTEXTS))],
            'user_id' => ['nullable', Rule::in($company->contacts()->pluck('users.id'))],
        ]);

        $feedback = $leads->requestFeedback($company, $input['context'], isset($input['user_id']) ? User::find($input['user_id']) : $company->primary_contact, $request->user()->id);

        return back()->with('success', 'Feedback link created: '.route('site.feedback.show', $feedback->token))
            ->with('feedback_link', route('site.feedback.show', $feedback->token));
    }
}
