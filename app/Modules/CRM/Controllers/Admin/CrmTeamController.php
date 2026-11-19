<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * What each account manager is doing: CRM work (emails, replies waiting and answered, calls, WhatsApp,
 * stage moves, overdue to-dos, last sign-in) next to their sharet.africa work mailbox and the shared
 * sales@ / onboarding@ / support@ (message counts only, from storage/app/mailbox-stats.json, written by
 * the root cron /usr/local/bin/scharge-mailreplies). Reading the mail itself is CRM › Inbox.
 * Sysadmins see the whole team; an account manager sees only their own row.
 */
class CrmTeamController extends Controller
{
    public const PERIODS = ['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 30 days'];

    public const SHARED = ['sales', 'onboarding', 'support'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $seesAll = CrmAccess::seesAll($user);
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'week';
        $from = match ($period) { 'today' => now()->startOfDay(), 'month' => now()->subDays(30), default => now()->subDays(7) };

        $ids = CrmCompanyController::staff()->pluck('id')->when(! $seesAll, fn ($c) => $c->filter(fn ($id) => $id === $user->id))->values();
        $people = User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email']);
        $companies = fn () => Company::withoutGlobalScope('customers');
        $fu = fn () => CrmFollowUp::query();

        $count = fn ($q, string $col) => $q->selectRaw($col.' as uid, COUNT(*) as n')->groupBy('uid')->pluck('n', 'uid');
        $owned = $count($companies()->whereIn('owner_id', $ids), 'owner_id');
        $open = $count($companies()->whereIn('owner_id', $ids)->whereIn('crm_stage', Company::OPEN_CRM_STAGES), 'owner_id');
        $waiting = $count($companies()->whereIn('owner_id', $ids)->where('crm_profile->reply_pending', true), 'owner_id');
        $cold = $count($fu()->where('type', 'email')->whereNotNull('details->track')->where('created_at', '>=', $from)->whereIn('assigned_to', $ids), 'assigned_to');
        $replies = $fu()->where('crm_follow_ups.type', 'email')->where('crm_follow_ups.details->direction', 'in')->whereNull('crm_follow_ups.details->auto')
            ->where('crm_follow_ups.created_at', '>=', $from)->join('companies', 'companies.id', '=', 'crm_follow_ups.company_id')->whereIn('companies.owner_id', $ids)
            ->selectRaw('companies.owner_id as uid, COUNT(*) as n')->groupBy('uid')->pluck('n', 'uid');
        $answered = $count($fu()->where('type', 'email')->where('details->direction', 'out')->where('created_at', '>=', $from)->whereIn('created_by', $ids), 'created_by');
        $done = $fu()->where('status', 'done')->where('done_at', '>=', $from)->whereIn('created_by', $ids)->whereIn('type', ['call', 'whatsapp', 'meeting', 'note', 'stage', 'demo'])
            ->selectRaw('created_by as uid, type, COUNT(*) as n')->groupBy('uid', 'type')->get()->groupBy('uid')->map(fn ($r) => $r->pluck('n', 'type'));
        $overdue = $count($fu()->overdue()->whereIn('assigned_to', $ids), 'assigned_to');
        $lastActive = $fu()->whereIn('created_by', $ids)->selectRaw('created_by as uid, MAX(created_at) as at')->groupBy('uid')->pluck('at', 'uid');
        $lastLogin = Schema::hasTable('login_events')
            ? DB::table('login_events')->whereIn('user_id', $ids)->where('success', true)->selectRaw('user_id as uid, MAX(created_at) as at')->groupBy('uid')->pluck('at', 'uid')
            : collect();

        $stats = $this->mailboxStats();
        $mailboxOf = fn (User $p) => collect($p->mailboxes())->first(fn ($e) => str_ends_with($e, '@sharet.africa'));

        $rows = $people->map(fn (User $p) => [
            'user' => $p,
            'owned' => (int) ($owned[$p->id] ?? 0), 'open' => (int) ($open[$p->id] ?? 0),
            'cold' => (int) ($cold[$p->id] ?? 0), 'replies' => (int) ($replies[$p->id] ?? 0),
            'waiting' => (int) ($waiting[$p->id] ?? 0), 'answered' => (int) ($answered[$p->id] ?? 0),
            'calls' => (int) ($done[$p->id]['call'] ?? 0), 'whatsapp' => (int) ($done[$p->id]['whatsapp'] ?? 0),
            'meetings' => (int) (($done[$p->id]['meeting'] ?? 0) + ($done[$p->id]['demo'] ?? 0)),
            'notes' => (int) ($done[$p->id]['note'] ?? 0), 'stages' => (int) ($done[$p->id]['stage'] ?? 0),
            'overdue' => (int) ($overdue[$p->id] ?? 0),
            'last_active' => isset($lastActive[$p->id]) ? Carbon::parse($lastActive[$p->id]) : null,
            'last_login' => isset($lastLogin[$p->id]) ? Carbon::parse($lastLogin[$p->id]) : null,
            'mailbox' => ($m = $mailboxOf($p)) ? ['address' => $m] + ($stats['mailboxes'][explode('@', $m)[0]] ?? []) : null,
        ]);

        $shared = $seesAll ? collect(self::SHARED)->map(fn ($m) => ['address' => $m.'@sharet.africa'] + ($stats['mailboxes'][$m] ?? [])) : collect();

        $feed = $fu()->whereIn('created_by', $ids)->where('created_at', '>=', $from)->whereNotIn('type', ['contact', 'import'])
            ->with(['company' => fn ($q) => $q->withoutGlobalScope('customers')->select('id', 'name'), 'author:id,name'])
            ->latest('id')->limit(40)->get();
        $waitingList = $companies()->whereIn('owner_id', $ids)->where('crm_profile->reply_pending', true)->with('owner:id,name')
            ->orderByDesc('crm_profile->reply->at')->limit(30)->get(['id', 'name', 'owner_id', 'crm_profile', 'crm_stage']);

        return view('crm.team', [
            'rows' => $rows, 'shared' => $shared, 'feed' => $feed, 'waitingList' => $waitingList,
            'period' => $period, 'seesAll' => $seesAll, 'statsAt' => isset($stats['generated_at']) ? Carbon::createFromTimestamp($stats['generated_at']) : null,
        ]);
    }

    private function mailboxStats(): array
    {
        $path = storage_path('app/mailbox-stats.json');
        $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
