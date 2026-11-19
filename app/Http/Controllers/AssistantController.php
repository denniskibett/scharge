<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Assistant\Claude;
use App\Services\Assistant\Cleanup;
use App\Services\Assistant\Sender;
use App\Services\Assistant\Sources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Assistant (docs/ASSISTANT.md): drafts written by Claude wait here. A person edits, sends, discards or asks for a
 * redraft; clean-up fixes are applied only from here. Company staff see their company's drafts; scharge staff
 * (sysadmin, account managers) see scharge's own (CRM replies, follow-ups, subscription reminders) or pick a company.
 */
class AssistantController extends Controller
{
    public const KINDS = ['prospect_reply' => 'Prospect replies', 'prospect_followup' => 'Prospect follow-ups', 'billing' => 'Subscription reminders',
        'tenant_email' => 'Tenant emails', 'arrears' => 'Payment follow-ups', 'cleanup' => 'Data clean-up'];

    public function index(Request $request)
    {
        [$companyId, $company] = $this->scope($request);
        $installed = Claude::installed();
        $status = in_array($request->query('status'), ['sent', 'failed', 'discarded', 'applied'], true) ? $request->query('status') : 'draft';
        $kind = array_key_exists((string) $request->query('kind'), self::KINDS) ? $request->query('kind') : null;
        $base = fn () => DB::table('ai_drafts')->where(fn ($q) => $companyId ? $q->where('company_id', $companyId) : $q->whereNull('company_id'));
        $drafts = $installed ? $base()->where('status', $status)->when($kind, fn ($q) => $q->where('kind', $kind))
            ->orderByDesc('id')->paginate(20)->withQueryString() : collect();
        $counts = $installed ? $base()->where('status', 'draft')->selectRaw('kind, count(*) n')->groupBy('kind')->pluck('n', 'kind') : collect();
        $sentWeek = $installed ? $base()->whereIn('status', ['sent', 'applied'])->where('sent_at', '>=', now()->subDays(7))->count() : 0;
        $isStaff = $request->user()->hasRole(['sysadmin', 'account_manager']);

        return view('assistant.index', [
            'drafts' => $drafts, 'counts' => $counts, 'sentWeek' => $sentWeek, 'status' => $status, 'kind' => $kind, 'installed' => $installed,
            'company' => $company, 'isStaff' => $isStaff, 'enabled' => Claude::enabledFor($companyId), 'hasKey' => (bool) Claude::apiKey(),
            'companies' => $request->user()->hasRole('sysadmin') ? Company::where(fn ($q) => $q->whereNull('lifecycle')->orWhere('lifecycle', '!=', 'prospect'))->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function send(Request $request, int $id)
    {
        $draft = $this->draft($request, $id);
        $data = $request->validate(['subject' => ['nullable', 'string', 'max:255'], 'body' => ['nullable', 'string', 'max:10000']]);
        abort_unless(in_array($draft->status, ['draft', 'failed'], true), 422, 'This one was already handled.');
        [$ok, $message] = Sender::send($draft, $request->user(), (string) ($data['subject'] ?? $draft->subject), (string) ($data['body'] ?? $draft->body));

        return back()->with($ok ? 'status' : 'error', $message);
    }

    public function discard(Request $request, int $id)
    {
        $draft = $this->draft($request, $id);
        DB::table('ai_drafts')->where('id', $draft->id)->update(['status' => $request->boolean('undo') ? 'draft' : 'discarded', 'reviewed_by' => $request->user()->id, 'updated_at' => now()]);

        return back()->with('status', $request->boolean('undo') ? 'Back in the list.' : 'Discarded.');
    }

    public function redraft(Request $request, int $id)
    {
        $draft = $this->draft($request, $id);
        $data = $request->validate(['instruction' => ['required', 'string', 'max:1000']]);
        [$out, $error] = Claude::redraft($draft, $data['instruction']);
        if ($error) {
            return back()->with('error', $error);
        }
        DB::table('ai_drafts')->where('id', $draft->id)->update(array_filter(['subject' => $draft->channel === 'email' ? ($out['subject'] ?: $draft->subject) : null,
            'body' => $out['body'], 'note' => mb_substr((string) $out['note'], 0, 500) ?: null, 'status' => 'draft', 'error' => null, 'updated_at' => now()], fn ($v) => $v !== null));

        return back()->with('status', 'Redrafted.');
    }

    /** "Scan now" (company: data clean-up) or "Check now" (scharge: CRM replies, follow-ups, subscription reminders). */
    public function run(Request $request)
    {
        [$companyId] = $this->scope($request);
        abort_unless(Claude::enabledFor($companyId), 422, 'The Claude assistant is off. Switch it on in Integrations.');
        if ($companyId) {
            $n = Cleanup::scan($companyId, true) + Sources::arrears($companyId) + rescue(fn () => \App\Services\Assistant\Inbox::poll($companyId), 0);
        } else {
            $n = Sources::prospectReplies() + Sources::prospectFollowUps() + Sources::billing();
        }

        return back()->with('status', $n ? "{$n} new drafts." : 'Nothing new to draft.');
    }

    /** @return array{0: ?int, 1: ?Company} */
    private function scope(Request $request): array
    {
        $user = $request->user();
        if ($user->hasRole('sysadmin') && $request->filled('company')) {
            $company = Company::findOrFail((int) $request->input('company'));

            return [$company->id, $company];
        }
        if ($user->hasRole(['sysadmin', 'account_manager'])) {
            return [null, null];
        }
        abort_unless($user->company_id, 403, 'Your account is not linked to a company yet.');

        return [(int) $user->company_id, Company::find($user->company_id)];
    }

    private function draft(Request $request, int $id): object
    {
        abort_unless(Claude::installed(), 404);
        [$companyId] = $this->scope($request);
        $draft = DB::table('ai_drafts')->where('id', $id)->first();
        abort_unless($draft && ((int) $draft->company_id === (int) $companyId || ($draft->company_id === null && $companyId === null)), 404);

        return $draft;
    }
}
