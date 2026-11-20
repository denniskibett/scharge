<?php

namespace App\Modules\CRM\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\DemoRequestService as Demo;
use App\Modules\CRM\Support\EmailTracking;
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

    public function show(Request $request, string $ref): View
    {
        $company = Demo::findByRef($ref);
        abort_unless($company, 404);
        // Opened from a cold email (?t=…): they clicked.
        $email = EmailTracking::find($request->query('t'));
        EmailTracking::mark($email, 'clicked');
        \App\Modules\CRM\Support\EmailEngagement::record($email, 'click', $request);

        return view('home.demo-request', ['company' => $company, 'ref' => strtoupper($ref), 'manager' => $company->owner,
            'draft' => (array) $company->profile('demo_draft.data', []), 'draftStep' => (int) $company->profile('demo_draft.step', 1)]);
    }

    /**
     * Save the demo form step by step (one page at a time, nothing is lost if they stop half way). Kept on the
     * prospect as crm_profile.demo_draft until they book; the playbook shows how far they got.
     */
    public function draft(Request $request, string $ref): \Illuminate\Http\JsonResponse
    {
        $company = Demo::findByRef($ref);
        abort_unless($company, 404);
        if (filled($request->input('website_url'))) {   // honeypot
            return response()->json(['saved' => true]);
        }
        $str = fn ($v, int $n) => is_scalar($v) ? mb_substr(trim(strip_tags((string) $v)), 0, $n) : null;
        $in = $request->all();
        $data = array_filter([
            'role' => in_array($in['role'] ?? null, array_keys(Demo::ROLES), true) ? $in['role'] : null,
            'name' => $str($in['name'] ?? null, 120), 'job_title' => $str($in['job_title'] ?? null, 120),
            'phone' => $str($in['phone'] ?? null, 40), 'email' => $str($in['email'] ?? null, 190),
            'interests' => array_values(array_intersect((array) ($in['interests'] ?? []), array_keys(Demo::INTERESTS))) ?: null,
            'properties' => collect((array) ($in['properties'] ?? []))->take(50)->map(fn ($p) => [
                'name' => $str($p['name'] ?? null, 120), 'type' => in_array($p['type'] ?? null, Demo::PROPERTY_TYPES, true) ? $p['type'] : null,
                'location' => $str($p['location'] ?? null, 120), 'units' => is_numeric($p['units'] ?? null) ? max(0, min(100000, (int) $p['units'])) : null,
            ])->values()->all() ?: null,
            'billing_now' => in_array($in['billing_now'] ?? null, Demo::BILLING_NOW, true) ? $in['billing_now'] : null,
            'contact_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['contact_date'] ?? '')) ? $in['contact_date'] : null,
            'contact_window' => in_array($in['contact_window'] ?? null, Demo::WINDOWS, true) ? $in['contact_window'] : null,
            'channel' => in_array($in['channel'] ?? null, Demo::CHANNELS, true) ? $in['channel'] : null,
            'message' => $str($in['message'] ?? null, 2000),
        ], fn ($v) => $v !== null && $v !== '');
        $step = max(1, min(4, (int) $request->input('step', 1)));
        $profile = (array) $company->crm_profile;
        $profile['demo_draft'] = ['data' => $data, 'step' => $step, 'saved_at' => now()->toDateTimeString()];
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();

        return response()->json(['saved' => true, 'step' => $step]);
    }

    /** 1×1 image in cold emails: records that the email was opened. */
    public function opened(Request $request, string $token): \Illuminate\Http\Response
    {
        $email = EmailTracking::find($token);
        EmailTracking::mark($email, 'opened');
        \App\Modules\CRM\Support\EmailEngagement::record($email, 'open', $request);   // when, roughly where, which device

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            'Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache',
        ]);
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
        $fresh = $company->fresh();                            // booked: the half-filled draft is no longer needed
        $profile = (array) $fresh->crm_profile;
        unset($profile['demo_draft']);
        $fresh->forceFill(['crm_profile' => $profile])->saveQuietly();

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
