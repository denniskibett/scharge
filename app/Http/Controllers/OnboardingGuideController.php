<?php

namespace App\Http\Controllers;

use App\Support\OnboardingGuide;
use Illuminate\Http\Request;

/** The company manual (App\Support\OnboardingGuide): the /guide page, and marking chapters or hiding it from the dashboard. */
class OnboardingGuideController extends Controller
{
    public function show(Request $request)
    {
        $guide = $this->guide($request);

        return view('guide.index', ['guide' => $guide]);
    }

    /** "Not for us" / "Got it" on a chapter, or undo. */
    public function mark(Request $request)
    {
        $guide = $this->guide($request);
        $keys = array_column($guide->chapters(), 'key');
        $data = $request->validate(['chapter' => 'required|in:'.implode(',', $keys), 'on' => 'required|boolean']);
        $guide->mark($data['chapter'], (bool) $data['on']);

        return $request->wantsJson() ? response()->json(['progress' => (new OnboardingGuide($guide->company(), $request->user()))->progress()]) : back();
    }

    /** Hide from the dashboard (the manual stays at /guide), or put it back. */
    public function visibility(Request $request)
    {
        $guide = $this->guide($request);
        $show = $request->boolean('show');
        $guide->save(['hidden_at' => $show ? null : now()->toIso8601String()]);

        return $show ? redirect()->route('dashboard') : back()->with('success', 'The guide is hidden from your dashboard. Open it any time from Guide in your profile menu.');
    }

    private function guide(Request $request): OnboardingGuide
    {
        $guide = OnboardingGuide::for($request->user());
        abort_unless($guide, 403, 'The guide is for company accounts.');

        return $guide;
    }
}
