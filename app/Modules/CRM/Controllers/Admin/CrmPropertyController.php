<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Services\CommissionService;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\DemoRequestService as Demo;
use App\Modules\CRM\Support\CrmAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Open properties: every property a prospect listed on the demo form (crm_profile.prospect_properties), until it is
 * won or lost. Account managers attach the offerings to sell for each one, move its status and book follow-ups;
 * every change is logged on the company thread.
 */
class CrmPropertyController extends Controller
{
    public function __construct(private ContactService $contacts, private CommissionService $commission)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = array_key_exists($request->query('status'), Demo::PROPERTY_STATUSES + ['all' => 1]) ? $request->query('status') : 'active';
        $class = $request->query('class');
        $owner = CrmAccess::seesPipeline($user) ? $request->query('owner') : null;
        $price = (int) $this->commission->settings()['pipeline_price_per_unit'];

        $rows = CrmAccess::companies(Company::crm(), $user)
            ->whereNotNull('crm_profile->prospect_properties')
            ->when($owner, fn ($q, $o) => $o === 'none' ? $q->whereNull('owner_id') : $q->where('owner_id', $o))
            ->with('owner:id,name')->get(['id', 'name', 'owner_id', 'crm_stage', 'crm_profile'])
            ->flatMap(fn (Company $c) => collect($c->crm_profile['prospect_properties'] ?? [])->map(fn ($p) => $p + ['company' => $c]))
            ->filter(fn ($p) => match ($status) {
                'all' => true,
                'active' => ! in_array($p['status'] ?? 'open', ['won', 'lost'], true),
                default => ($p['status'] ?? 'open') === $status,
            })
            ->when($class, fn ($c) => $c->where('class', $class))
            ->sortByDesc('added_at')->values();

        return view('crm.properties', [
            'rows' => $rows,
            'status' => $status,
            'class' => $class,
            'owner' => $owner,
            'staff' => CrmAccess::seesPipeline($user) ? CrmCompanyController::staff() : collect(),
            'price' => $price,
            'units' => $rows->sum('units'),
            'value' => $rows->sum('units') * $price,
        ]);
    }

    public function update(Request $request, Company $crmCompany, string $property): RedirectResponse
    {
        CrmAccess::authorizeCompany($crmCompany, $request->user());
        $input = $request->validate([
            'status' => ['required', Rule::in(array_keys(Demo::PROPERTY_STATUSES))],
            'offerings' => ['nullable', 'array'],
            'offerings.*' => [Rule::in(array_keys(Demo::INTERESTS))],
            'units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'follow_up_at' => ['nullable', 'date', 'after:now'],
        ]);
        $list = $crmCompany->crm_profile['prospect_properties'] ?? [];
        $i = collect($list)->search(fn ($p) => ($p['id'] ?? null) === $property);
        abort_if($i === false, 404);
        $before = $list[$i];
        $list[$i] = array_merge($before, [
            'status' => $input['status'],
            'offerings' => array_values($input['offerings'] ?? []),
            'units' => isset($input['units']) ? (int) $input['units'] : ($before['units'] ?? 0),
            'updated_at' => now()->toDateTimeString(),
            'updated_by' => $request->user()->id,
        ]);
        $crmCompany->forceFill(['crm_profile' => array_merge($crmCompany->crm_profile ?? [], ['prospect_properties' => $list])])->saveQuietly();

        $changes = array_filter([
            ($before['status'] ?? 'open') !== $input['status'] ? 'status '.Demo::PROPERTY_STATUSES[$before['status'] ?? 'open'].' → '.Demo::PROPERTY_STATUSES[$input['status']] : null,
            $list[$i]['offerings'] !== ($before['offerings'] ?? []) ? 'offerings: '.(collect($list[$i]['offerings'])->map(fn ($k) => Demo::INTERESTS[$k] ?? $k)->join(', ') ?: 'none') : null,
            $list[$i]['units'] !== ($before['units'] ?? 0) ? 'units '.($before['units'] ?? 0).' → '.$list[$i]['units'] : null,
        ]);
        if ($changes || ! empty($input['note'])) {
            $this->contacts->log($crmCompany, 'note', 'Property '.$before['name'].($changes ? ': '.implode('; ', $changes) : ''), $input['note'] ?? null, ['property_id' => $property], null, $request->user()->id);
        }
        if (! empty($input['follow_up_at'])) {
            $crmCompany->followUps()->create([
                'assigned_to' => $crmCompany->owner_id ?? $request->user()->id, 'created_by' => $request->user()->id,
                'type' => 'call', 'status' => 'open', 'due_at' => Carbon::parse($input['follow_up_at']),
                'title' => 'Follow up on '.$before['name'].' ('.$crmCompany->name.')',
                'notes' => $input['note'] ?? null, 'details' => ['property_id' => $property],
            ]);
        }

        return back()->with('success', $before['name'].' updated'.(! empty($input['follow_up_at']) ? '; follow-up booked for '.Carbon::parse($input['follow_up_at'])->format('D j M, H:i') : '').'.');
    }
}
