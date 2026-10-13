<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Country;
use App\Modules\CRM\Models\WaitlistEntry;
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
            'byCountry' => WaitlistEntry::query()
                ->leftJoin('countries', 'countries.id', '=', 'waitlist_entries.country_id')
                ->selectRaw("COALESCE(countries.name, waitlist_entries.country_name, 'Another country') as label, waitlist_entries.country_id, COUNT(*) as total, SUM(waitlist_entries.status = 'waiting') as waiting")
                ->groupBy('label', 'waitlist_entries.country_id')
                ->orderByDesc('total')
                ->get(),
            'countries' => Country::active()->get(),
            'filters' => $request->only(['country', 'status', 'q']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $entries = $this->query($request)->get();

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Email', 'Country', 'Company', 'Status', 'Joined'], ',', '"', '');
            foreach ($entries as $e) {
                fputcsv($out, [$e->user?->name, $e->user?->phone, $e->user?->email, $e->country_label, $e->company?->name, $e->status, $e->created_at->toDateTimeString()], ',', '"', '');
            }
            fclose($out);
        }, 'scharge-waitlist-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function update(Request $request, WaitlistEntry $entry): RedirectResponse
    {
        $input = $request->validate(['status' => ['required', Rule::in(WaitlistEntry::STATUSES)]]);
        $entry->update($input + (in_array($input['status'], ['contacted', 'invited'], true) && ! $entry->notified_at ? ['notified_at' => now()] : []));

        return back()->with('success', 'Waitlist entry updated.');
    }

    private function query(Request $request): Builder
    {
        return WaitlistEntry::query()
            ->with(['user:id,name,phone,email', 'country:id,name', 'company:id,name'])
            ->when($request->input('country'), fn ($q, $c) => $c === 'other' ? $q->whereNull('country_id') : $q->where('country_id', $c))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('q'), fn ($q, $term) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->latest();
    }
}
