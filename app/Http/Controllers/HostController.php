<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Unit;
use App\Models\UnitManagement;
use App\Models\User;
use App\Services\Properties\HostUnits;
use App\Services\Properties\PropertyRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * BnB hosts: sign up with their unit (website "Add my listing"), then manage their units.
 * The building is found or created once (PropertyRegistry); the host manages only their own units.
 */
class HostController extends Controller
{
    public function create()
    {
        return view('host.register');
    }

    /** Public: similar buildings already on scharge, so the host picks the right one. */
    public function searchProperties(Request $request)
    {
        $request->validate(['name' => 'required|string|min:3|max:120', 'location' => 'nullable|string|max:160']);
        $matches = app(PropertyRegistry::class)->matches($request->query('name'), $request->query('location'))->take(5);

        // Only what a host needs to recognise the building: name and area.
        return response()->json(['matches' => $matches->map(fn ($m) => [
            'id' => $m['property']->id, 'name' => $m['property']->name, 'location' => $m['property']->location,
        ])->values()]);
    }

    public function store(Request $request, HostUnits $hosts)
    {
        if (filled($request->input('website'))) { // honeypot: bots fill the hidden field
            return redirect()->route('host.register')->with('success', 'Thank you. Check your email to finish signing up.');
        }
        $guard = \App\Services\KycLog::verdict(email: (string) $request->input('email'));
        if (! $guard['allow']) {
            \App\Services\KycLog::record('signup.blocked', null, ['email' => (string) $request->input('email'), 'reasons' => $guard['reasons'], 'form' => 'host']);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Too many failed attempts from your network. Please try again in an hour or contact support.']);
        }
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'required|string|max:30',
            'password' => 'required|string|min:8|confirmed',
            'property_id' => 'nullable|integer|exists:properties,id',
            'property_name' => 'required_without:property_id|nullable|string|max:160',
            'location' => 'required_without:property_id|nullable|string|max:160',
            'unit_number' => 'required|string|max:30',
            'unit_type' => 'required|in:Studio,One Bedroom,Two Bedroom,Three Bedroom,Four Bedroom,Villa,Cottage',
            'nightly_rate' => 'required|numeric|min:500|max:500000',
            'terms' => 'accepted',
        ]);

        [$user, $unit] = DB::transaction(function () use ($data, $hosts) {
            $company = Company::forceCreate(['name' => $data['name'].' (BnB host)', 'kind' => 'bnb_host', 'email' => $data['email'],
                'phone' => $data['phone'], 'is_active' => 1, 'lifecycle' => 'customer']);
            $user = User::forceCreate(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                'password' => Hash::make($data['password']), 'company_id' => $company->id, 'status' => 0]);
            $user->assignRole('bnb_host');
            $unit = $hosts->registerUnit($company, $user, $data);

            return [$user, $unit];
        });

        $ref = $this->recordSignup($user, $unit, $data);
        \App\Services\KycLog::record('signup.ok', $user, ['form' => 'host']);
        \App\Support\SiteAnalytics::conversion($request, 'host_register');
        Auth::login($user);
        $user->sendEmailVerificationNotification();

        return redirect()->route('host.dashboard')->with('success', "Welcome! {$unit->unit_number} at {$unit->property->name} is set up. Add photos so guests can find it.".($ref ? " Your reference is {$ref}." : ''));
    }

    public function dashboard(Request $request)
    {
        $units = $this->myUnits($request)->with('property:id,name,location,claim_status')->get();

        return view('host.dashboard', ['units' => $units]);
    }

    public function addUnit(Request $request, HostUnits $hosts)
    {
        $data = $request->validate([
            'property_id' => 'nullable|integer|exists:properties,id',
            'property_name' => 'required_without:property_id|nullable|string|max:160',
            'location' => 'required_without:property_id|nullable|string|max:160',
            'unit_number' => 'required|string|max:30',
            'unit_type' => 'required|string|max:40',
            'nightly_rate' => 'required|numeric|min:500|max:500000',
        ]);
        $unit = $hosts->registerUnit(Company::findOrFail($request->user()->company_id), $request->user(), $data);

        return back()->with('success', "{$unit->unit_number} at {$unit->property->name} added.");
    }

    /**
     * The sign-up on the CRM: a welcome call on the host's company for its account manager (or the default one), with
     * the submission as a PDF and an email (FormSubmissionService). Never blocks the sign-up itself.
     */
    private function recordSignup(User $user, Unit $unit, array $data): ?string
    {
        try {
            $company = Company::withoutGlobalScope('customers')->find($user->company_id);
            if (! $company->crm_stage) {
                $company->update(['crm_stage' => 'won', 'source' => $company->source ?: 'website_host']);
            }
            $followUp = $company->followUps()->create([
                'user_id' => $user->id,
                'assigned_to' => $company->owner_id,
                'type' => 'call',
                'status' => 'open',
                'title' => "Welcome call: new BnB host {$user->name} ({$unit->unit_number}, {$unit->property->name})",
                'due_at' => app(\App\Modules\CRM\Services\LeadService::class)->nextWorkingDay(now()),
                'details' => ['unit_id' => $unit->id, 'property_id' => $unit->property_id],
            ]);

            return app(\App\Modules\CRM\Services\FormSubmissionService::class)->record($followUp, 'host_register', [
                'Building' => $unit->property->name.($unit->property->location ? ', '.$unit->property->location : ''),
                'Existing building' => ! empty($data['property_id']) ? 'Yes, joined a building already on scharge' : 'No, added by the host',
                'Unit' => $unit->unit_number.' · '.$data['unit_type'],
                'Price per night' => 'KES '.number_format((float) $data['nightly_rate']),
            ])->detail('ref');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Units this host's company manages as a BnB host. */
    private function myUnits(Request $request)
    {
        return Unit::whereIn('id', UnitManagement::where('company_id', $request->user()->company_id)
            ->where('role', 'bnb_host')->where('status', 'active')->pluck('unit_id'));
    }
}
