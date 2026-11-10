<?php

namespace App\Http\Controllers;

use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Property › Enquiries: renters' viewing and booking requests for this landlord's listings (EnquiryRouter), worked
 * like a small CRM: new → contacted → viewing booked → let / not interested. Company users see only their own.
 */
class EnquiryController extends Controller
{
    public const STAGES = ['new' => 'New', 'contacted' => 'Contacted', 'viewing' => 'Viewing booked', 'let' => 'Let', 'lost' => 'Not interested'];

    public function index(Request $request): View
    {
        $companyId = $this->companyId($request);
        $stage = array_key_exists((string) $request->query('stage'), self::STAGES) ? $request->query('stage') : null;
        $base = CrmFollowUp::with('contact')->where('type', 'viewing')->where('company_id', $companyId);

        return view('enquiries.index', [
            'enquiries' => (clone $base)->when($stage, fn ($q) => $q->where('details->stage', $stage), fn ($q) => $q->orderByRaw("status = 'open' desc"))
                ->latest('id')->paginate(25)->withQueryString(),
            'stage' => $stage,
            'counts' => collect(self::STAGES)->map(fn ($l, $k) => (clone $base)->get()->filter(fn ($e) => ($e->details['stage'] ?? 'new') === $k)->count()),
        ]);
    }

    public function update(Request $request, CrmFollowUp $enquiry): RedirectResponse
    {
        abort_unless($enquiry->type === 'viewing' && (int) $enquiry->company_id === $this->companyId($request), 404);
        $data = $request->validate(['stage' => ['required', Rule::in(array_keys(self::STAGES))], 'note' => ['nullable', 'string', 'max:500']]);
        $done = in_array($data['stage'], ['let', 'lost'], true);
        $enquiry->update([
            'details' => array_merge($enquiry->details ?? [], ['stage' => $data['stage']]),
            'status' => $done ? 'done' : ($data['stage'] === 'viewing' ? 'scheduled' : 'open'),
            'outcome' => $done ? self::STAGES[$data['stage']] : $enquiry->outcome,
            'done_at' => $done ? now() : null,
            'notes' => filled($data['note'] ?? null) ? trim(($enquiry->notes ? $enquiry->notes."\n" : '').now('Africa/Nairobi')->format('j M').': '.$data['note']) : $enquiry->notes,
        ]);

        return back()->with('success', 'Updated: '.self::STAGES[$data['stage']].'.');
    }

    private function companyId(Request $request): int
    {
        $user = $request->user();
        abort_if($user->hasRole('tenant') && ! $user->hasAnyRole(['admin', 'property_manager', 'sysadmin']), 403);
        abort_unless($user->company_id, 403, 'Enquiries belong to a landlord company.');

        return (int) $user->company_id;
    }
}
