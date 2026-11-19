<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\CRM\Support\CrmRecap;
use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    public const PERIODS = ['yesterday' => 'Yesterday', 'today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 30 days'];

    public const SHARED = ['sales', 'onboarding', 'support', 'noreply'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $seesAll = CrmAccess::seesAll($user);
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'yesterday';
        $tz = config('app.schedule_timezone', 'Africa/Nairobi');
        $recap = match ($period) {
            'yesterday' => CrmRecap::yesterday(),
            'today' => new CrmRecap(now($tz)->startOfDay()->utc(), now()),
            'month' => new CrmRecap(now()->subDays(30), now()),
            default => new CrmRecap(now()->subDays(7), now()),
        };
        $from = $recap->from;

        $ids = CrmCompanyController::staff()->pluck('id')->when(! $seesAll, fn ($c) => $c->filter(fn ($id) => $id === $user->id))->values();
        $companies = fn () => Company::withoutGlobalScope('customers');
        $fu = fn () => CrmFollowUp::query();
        $people = $recap->people($ids);

        $stats = $this->mailboxStats();
        $mailboxOf = fn (User $p) => collect($p->mailboxes())->first(fn ($e) => str_ends_with($e, '@sharet.africa'));

        $rows = $people->map(fn ($r) => $r + [
            'mailbox' => ($m = $mailboxOf($r['user'])) ? ['address' => $m] + ($stats['mailboxes'][explode('@', $m)[0]] ?? []) : null,
        ]);

        $shared = $seesAll ? collect(self::SHARED)->map(fn ($m) => ['address' => $m.'@sharet.africa'] + ($stats['mailboxes'][$m] ?? [])) : collect();

        $feed = $fu()->whereIn('created_by', $ids)->whereBetween('created_at', [$recap->from, $recap->to])->whereNotIn('type', ['contact', 'import'])->where('title', 'not like', 'Owner:%')
            ->with(['company' => fn ($q) => $q->withoutGlobalScope('customers')->select('id', 'name'), 'author:id,name'])
            ->latest('id')->limit(40)->get();
        $waitingList = $companies()->whereIn('owner_id', $ids)->where('companies.reply_pending', 1)->with('owner:id,name')
            ->orderByDesc('crm_profile->reply->at')->limit(30)->get(['id', 'name', 'owner_id', 'crm_profile', 'crm_stage']);

        return view('crm.team', [
            'rows' => $rows, 'shared' => $shared, 'feed' => $feed, 'waitingList' => $waitingList,
            'funnel' => $recap->funnel(), 'totals' => $seesAll ? $recap->totals() : null, 'hot' => $recap->hotLeads($seesAll ? null : $ids, 15), 'recap' => $recap,
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
