<?php

namespace App\Modules\CRM\Console;

use App\Modules\CRM\Controllers\Admin\CrmCompanyController;
use App\Modules\CRM\Mail\CrmDailyRecap;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\CrmRecap;
use App\Modules\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * 07:30 recap of the last working day (Monday covers Friday to Sunday):
 *   sysadmins get the whole team (funnel, pipeline, each account manager, hot leads);
 *   each account manager gets their own day plus today's list (replies waiting, due today, overdue, who to call first).
 */
class DailyRecap extends Command
{
    protected $signature = 'crm:daily-recap {--to= : Send only the team recap, to this address (test)} {--no-managers : Skip the account managers\' own recaps}';

    protected $description = 'Email the CRM recap of the last working day to sysadmins and each account manager';

    public function handle(): int
    {
        $tz = config('app.schedule_timezone', 'Africa/Nairobi');
        $today = now($tz)->startOfDay();
        $start = $today->isMonday() ? $today->copy()->subDays(3) : $today->copy()->subDay();
        $recap = new CrmRecap($start->copy()->utc(), $today->copy()->utc());
        $window = $start->format('l j F').($today->isMonday() ? ' to Sunday' : '');
        $staff = CrmCompanyController::staff()->pluck('id');
        $url = rtrim(config('app.url'), '/');

        $team = [
            'headline' => 'What happened in the CRM', 'window' => $window,
            'funnel' => $recap->funnel(), 'totals' => $recap->totals(),
            'people' => $recap->people(User::whereIn('id', $staff)->whereHas('roles', fn ($q) => $q->whereIn('name', ['account_manager', 'sales']))->pluck('id')),
            'hot' => $recap->hotLeads(null, 10), 'todo' => [],
        ];
        $admins = $this->option('to') ? [$this->option('to')]
            : User::whereHas('roles', fn ($q) => $q->where('name', 'sysadmin'))->pluck('email')->filter()->all();
        foreach ($admins as $to) {
            Mail::to($to)->send(new CrmDailyRecap('CRM recap: '.$team['funnel']['sent'].' sent, '.$team['funnel']['opened'].' opened, '.$team['totals']['replies'].' replies · '.$window, $team));
            $this->info('Team recap sent to '.$to);
        }
        if ($this->option('to') || $this->option('no-managers')) {
            return self::SUCCESS;
        }

        foreach (User::whereIn('id', $staff)->whereHas('roles', fn ($q) => $q->whereIn('name', ['account_manager', 'sales']))->get() as $am) {
            $ids = collect([$am->id]);
            $mine = $recap->people($ids)->first();
            $dueToday = CrmFollowUp::where('assigned_to', $am->id)->dueToday()->count();
            $hot = $recap->hotLeads($ids, 10);
            $data = [
                'headline' => 'Your day, '.explode(' ', $am->name)[0], 'window' => $window, 'me' => $am,
                'funnel' => $recap->funnel(), 'totals' => null, 'people' => [], 'hot' => $hot,
                'todo' => [
                    ['Replies waiting for your answer', $mine['waiting'], $url.'/crm?attention=replied'],
                    ['Follow-ups due today', $dueToday, $url.'/crm/follow-ups?view=today'],
                    ['Overdue follow-ups', $mine['overdue'], $url.'/crm/follow-ups?view=overdue'],
                    ['Opened your email, call them', $hot->count(), $url.'/crm?attention=opened&owner='.$am->id],
                ],
            ];
            Mail::to($am->mailboxes())->send(new CrmDailyRecap('Your CRM day: '.$mine['waiting'].' replies waiting, '.$hot->count().' warm calls', $data));
            $this->info('Own recap sent to '.$am->name);
        }

        return self::SUCCESS;
    }
}
