<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\CRM\Support\EmailTracking;
use App\Modules\CRM\Support\Playbook;
use App\Modules\CRM\Support\PropertyClass;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cold-email funnel per property type: who is ready to email, who has no email yet (Unsent), and what happened
 * to every email we sent (delivered, opened, clicked, replied, booked, bounced).
 */
class CrmCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $types = ['Short-stay', 'Residential', 'Commercial', 'Utilities'];
        $base = fn () => CrmAccess::companies(Company::withoutGlobalScope('customers'), $user);

        $emails = CrmAccess::followUps(CrmFollowUp::where('type', 'email')->whereNotNull('details->track'), $user)
            ->with(['company:id,name,crm_profile,kind,owner_id', 'assignee:id,name'])->latest('id')->get();
        $steps = array_keys(EmailTracking::LABELS);
        $rows = [];
        foreach ($types as $type) {
            $mine = $emails->filter(fn ($e) => ($e->detail('type') ?? ($e->company ? PropertyClass::of($e->company) : null)) === $type);
            $counts = array_fill_keys($steps, 0);
            foreach ($mine as $e) {
                $counts[EmailTracking::status($e)]++;
            }
            $rows[$type] = [
                'prefix' => Playbook::REF_PREFIX[$type],
                'ready' => EmailTracking::ready(PropertyClass::scope($base(), $type))->count(),
                'unsent' => EmailTracking::unsent(PropertyClass::scope($base(), $type))->count(),
                'sent' => $mine->count(),
                // each step counts everyone who got at least that far
                'delivered' => $counts['delivered'] + $counts['opened'] + $counts['clicked'] + $counts['replied'] + $counts['booked'],
                'opened' => $counts['opened'] + $counts['clicked'] + $counts['booked'],
                'clicked' => $counts['clicked'] + $counts['booked'],
                'replied' => $mine->filter(fn ($e) => $e->detail('replied_at'))->count(),
                'booked' => $counts['booked'],
                'bounced' => $counts['bounced'],
                'waiting' => $counts['sent'] + $counts['deferred'],
            ];
        }

        $filter = $request->query('status');
        $list = $emails->when(array_key_exists($filter, EmailTracking::LABELS), fn ($c) => $c->filter(fn ($e) => EmailTracking::status($e) === $filter))->take(200);

        return view('crm.campaigns', ['rows' => $rows, 'list' => $list, 'filter' => $filter, 'total' => $emails->count()]);
    }
}
