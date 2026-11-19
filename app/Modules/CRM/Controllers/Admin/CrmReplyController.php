<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Services\ReplyService;
use App\Modules\CRM\Support\CrmAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The reply alert on a prospect: answer it in the same email thread, or mark it handled, and move the stage. */
class CrmReplyController extends Controller
{
    public function __construct(private ReplyService $replies)
    {
    }

    /** Stages the alert can move to ("Won" goes through the Status card, which also turns them into a customer). */
    public static function stages(): array
    {
        return array_diff_key(Company::CRM_STAGES, ['won' => 1]);
    }

    public function answer(Request $request, Company $crmCompany): RedirectResponse
    {
        CrmAccess::authorizeCompany($crmCompany, $request->user());
        $input = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'stage' => ['nullable', Rule::in(array_keys(self::stages()))],
        ]);
        try {
            $this->replies->answer($crmCompany, $request->user(), $input['body'], $input['stage'] ?? null);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'The answer could not be sent: '.$e->getMessage());
        }

        return back()->with('success', 'Answer sent from '.\App\Modules\CRM\Support\Playbook::fromFor($crmCompany).' and logged on the thread.');
    }

    public function handled(Request $request, Company $crmCompany): RedirectResponse
    {
        CrmAccess::authorizeCompany($crmCompany, $request->user());
        $input = $request->validate([
            'stage' => ['nullable', Rule::in(array_keys(self::stages()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->replies->handled($crmCompany, $request->user(), $input['stage'] ?? null, $input['note'] ?? null);

        return back()->with('success', 'Reply marked as handled.');
    }
}
