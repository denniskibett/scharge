<?php

namespace App\Modules\CRM\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\DemoRequestService as Demo;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Public demo form behind the private link in each cold email (/demo/{ref}), plus the staff PDF download. */
class DemoRequestController extends Controller
{
    public function __construct(private Demo $demos)
    {
    }

    public function show(string $ref): View
    {
        $company = Demo::findByRef($ref);
        abort_unless($company, 404);

        return view('home.demo-request', ['company' => $company, 'ref' => strtoupper($ref), 'manager' => $company->owner]);
    }

    public function store(Request $request, string $ref): RedirectResponse
    {
        $company = Demo::findByRef($ref);
        abort_unless($company, 404);
        if (filled($request->input('website_url'))) { // honeypot
            return redirect()->route('site.demo-request.thanks', $ref);
        }
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(Demo::ROLES))],
            'name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'interests' => ['nullable', 'array'],
            'interests.*' => [Rule::in(array_keys(Demo::INTERESTS))],
            'properties' => ['required', 'array', 'min:1', 'max:50'],
            'properties.*.name' => ['nullable', 'string', 'max:120'],
            'properties.*.type' => ['nullable', 'required_with:properties.*.name,properties.*.units', Rule::in(Demo::PROPERTY_TYPES)],
            'properties.*.location' => ['nullable', 'string', 'max:120'],
            'properties.*.units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'billing_now' => ['nullable', Rule::in(Demo::BILLING_NOW)],
            'contact_date' => ['required', 'date', 'after_or_equal:today', 'before:+60 days'],
            'contact_window' => ['required', Rule::in(Demo::WINDOWS)],
            'channel' => ['required', Rule::in(Demo::CHANNELS)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please agree to be contacted about the demo.', 'properties.*.type.required_with' => 'Pick a property type for each property (apartments, offices, Airbnb…).'], ['properties.*.units' => 'units', 'properties.*.type' => 'property type']);
        if (! Normalizer::phone($data['phone'], '254')) {
            throw ValidationException::withMessages(['phone' => 'Enter a phone number we can call, e.g. 0712 345 678.']);
        }
        if (! collect($data['properties'])->contains(fn ($p) => filled($p['name'] ?? null) || (int) ($p['units'] ?? 0) > 0)) {
            throw ValidationException::withMessages(['properties' => 'Add at least one property (a name or a number of units).']);
        }

        $this->demos->submit($company, $data, strtoupper($ref));

        return redirect()->route('site.demo-request.thanks', $ref);
    }

    public function thanks(string $ref): View
    {
        $company = Demo::findByRef($ref);
        abort_unless($company, 404);
        $demo = $company->followUps()->where('type', 'demo')->where('details->ref', strtoupper($ref))->latest('id')->first();

        return view('home.demo-request', ['company' => $company, 'ref' => strtoupper($ref), 'manager' => $company->owner, 'done' => $demo]);
    }

    /** Staff: the PDF of a website-form submission (demo form, FormSubmissionService), from the thread. */
    public function pdf(Request $request, CrmFollowUp $followUp): StreamedResponse
    {
        // Website forms without a company yet (viewings, alerts, waitlist): whoever may see the row may download it.
        abort_unless(CrmAccess::followUps(CrmFollowUp::whereKey($followUp->id), $request->user())->exists(), 403);
        if ($followUp->company) {
            CrmAccess::authorizeCompany($followUp->company, $request->user());
        }
        $path = $followUp->detail('pdf');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), ['Content-Type' => 'application/pdf']);
    }
}
