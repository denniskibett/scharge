<?php

namespace App\Http\Controllers;

use App\Modules\CRM\Mail\TenantListUploaded;
use App\Modules\CRM\Support\Onboarding;
use App\Support\OnboardingGuide;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    /**
     * "Upload your tenant list, we load it for you": any sheet, PDF or photo of the rent book. It is kept privately,
     * and their account manager gets it by email with a task to load it (App\Modules\CRM\Support\Onboarding).
     */
    public function tenantList(Request $request)
    {
        $guide = $this->guide($request);
        $data = $request->validate([
            'list' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt,pdf,jpg,jpeg,png,heic,doc,docx,ods'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['list.max' => 'The file is larger than 10 MB. Send a smaller file or split it.', 'list.mimes' => 'Send an Excel or CSV sheet, a PDF, a Word file or a photo.']);

        $company = $guide->company();
        $file = $data['list'];
        $original = Str::limit(preg_replace('/[^\w .()-]+/u', '', $file->getClientOriginalName()) ?: 'tenant-list', 120, '');
        $path = $file->storeAs('onboarding/'.$company->id, 'tenants-'.now()->format('Ymd-His').'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension()), 'local');

        $user = $request->user();
        $task = Onboarding::task($company, 'tenant_list', 'Load the tenant list '.$company->name.' uploaded',
            $user->name.' uploaded "'.$original.'" from the dashboard and is waiting for us to load it.'.(filled($data['note'] ?? null) ? "\nTheir note: ".$data['note'] : '')
            ."\nLoad it with Properties > Import (put it into the template first if it is not a sheet), check the preview, then call them to confirm.",
            ['file' => $path, 'file_name' => $original, 'uploaded_by' => $user->id], 'call', $user->id);
        $guide->save(['tenant_list' => ['at' => now()->toIso8601String(), 'name' => $original, 'task' => $task->id]]);

        $manager = $task->assignee;
        try {
            if ($manager && ($to = $manager->mailboxes())) {
                Mail::to($to)->send(new TenantListUploaded($task, $user));
            }
        } catch (\Throwable $e) {
            report($e);   // the task and the file are saved either way
        }

        return back()->with('success', 'We have your list.'.($manager ? ' '.$manager->name.', your account manager, will load it and call you.' : ' Our team will load it and call you.'));
    }

    private function guide(Request $request): OnboardingGuide
    {
        $guide = OnboardingGuide::for($request->user());
        abort_unless($guide, 403, 'The guide is for company accounts.');

        return $guide;
    }
}
