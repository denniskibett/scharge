<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\WaitlistEntry;
use App\Modules\CRM\Services\LeadService;
use App\Modules\CRM\Support\Countries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmWaitlistController extends Controller
{
    public function index(Request $request): View
    {
        return view('crm.waitlist', [
            'entries' => $this->query($request)->paginate(50)->withQueryString(),
            'byCountry' => WaitlistEntry::selectRaw("country_code, COUNT(*) as total, SUM(status = 'demo_requested') as demos, SUM(status IN ('waiting','demo_requested','invited')) as open")
                ->groupBy('country_code')->orderByDesc('total')->get(),
            'filters' => $request->only(['country', 'status', 'q']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $entries = $this->query($request)->get();

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Email', 'Country', 'Company', 'Status', 'Project', 'Units', 'Joined'], ',', '"', '');
            foreach ($entries as $e) {
                fputcsv($out, [$e->user?->name, $e->user?->phone, $e->user?->email, $e->country_name, $e->company?->name,
                    WaitlistEntry::STATUSES[$e->status] ?? $e->status, $e->detail('project_type'), $e->detail('units'), $e->created_at->toDateTimeString()], ',', '"', '');
            }
            fclose($out);
        }, 'scharge-waitlist-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function update(Request $request, WaitlistEntry $entry): RedirectResponse
    {
        $entry->update($request->validate(['status' => ['required', Rule::in(array_keys(WaitlistEntry::STATUSES))]]));

        return back()->with('success', 'Waitlist entry updated.');
    }

    /** "We're live in X": move that country's waitlist into the CRM as follow-ups. */
    public function launch(Request $request, LeadService $leads): RedirectResponse
    {
        $code = $request->validate(['country_code' => ['required', Rule::in(array_keys(Countries::africa()))]])['country_code'];
        $moved = $leads->launchCountry($code, $request->user()->id);

        $note = Countries::isLive($code) ? '' : ' Add '.$code.' to Countries::LIVE so new demo requests from there go straight to the CRM.';

        return back()->with('success', "{$moved} ".str('person')->plural($moved).' from '.Countries::name($code).' moved to CRM follow-ups.'.$note);
    }

    private function query(Request $request): Builder
    {
        return WaitlistEntry::query()
            ->with(['user:id,name,phone,email', 'company:id,name'])
            ->when($request->input('country'), fn ($q, $c) => $q->where('country_code', $c))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('q'), fn ($q, $term) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->latest();
    }
}
