<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyAgent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The landlord's side: on the property page, give an agency (an agent business on scharge) access to market and let
 * this property, with an optional commission %, or take it away. Company admins and property managers only.
 */
class PropertyAgentController extends Controller
{
    public function store(Request $request, Property $property): RedirectResponse
    {
        $this->authorizeLandlord($property);
        $data = $request->validate([
            'agency' => ['required', 'string', 'max:191'],
            'commission_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'can_list' => ['nullable', 'boolean'],
        ]);

        $agency = self::findAgency($data['agency']);
        if (! $agency) {
            return back()->withErrors(['agency' => 'No agent or agency on scharge matches "'.$data['agency'].'". Ask them to sign up and pick "Agent / agency", then try their email, phone or agency name.'])->withInput();
        }
        if ((int) $agency->id === (int) $property->company_id) {
            return back()->withErrors(['agency' => 'That is your own business.'])->withInput();
        }

        $grant = PropertyAgent::active()->firstOrNew(['property_id' => $property->id, 'agency_id' => $agency->id]);
        $grant->fill([
            'can_list' => $request->boolean('can_list', true),
            'commission_pct' => $data['commission_pct'] ?? null,
            'status' => 'active',
            'granted_by' => Auth::id(),
        ])->save();

        return back()->with('success', $agency->name.' can now market and let '.$property->name.'.');
    }

    public function destroy(Property $property, PropertyAgent $grant): RedirectResponse
    {
        $this->authorizeLandlord($property);
        abort_unless((int) $grant->property_id === (int) $property->id, 404);
        $grant->update(['status' => 'revoked', 'revoked_at' => now()]);
        \App\Services\ListingCatalog::forget();

        return back()->with('success', ($grant->agency?->name ?? 'The agency').' no longer has access to '.$property->name.'.');
    }

    /** An agency by name, its email, or the email / phone of one of its people. */
    public static function findAgency(string $q): ?Company
    {
        $q = trim($q);
        $agencies = Company::withoutGlobalScope('customers')->where('kind', 'agency');
        if (filter_var($q, FILTER_VALIDATE_EMAIL)) {
            return (clone $agencies)->whereRaw('LOWER(email) = ?', [strtolower($q)])->first()
                ?? (clone $agencies)->whereKey(User::whereRaw('LOWER(email) = ?', [strtolower($q)])->value('company_id'))->first();
        }
        $digits = preg_replace('/\D/', '', $q);
        if (strlen($digits) >= 9 && strlen($digits) <= 12) {
            $tail = substr($digits, -9);
            $byUser = User::whereNotNull('company_id')->where('phone', 'like', '%'.$tail)->pluck('company_id');

            return (clone $agencies)->where(fn ($w) => $w->where('phone', 'like', '%'.$tail)->orWhereIn('id', $byUser))->first();
        }

        return (clone $agencies)->whereRaw('LOWER(name) = ?', [mb_strtolower($q)])->first();
    }

    private function authorizeLandlord(Property $property): void
    {
        $user = Auth::user();
        abort_unless($user->hasRole('sysadmin')
            || ((int) $user->company_id === (int) $property->company_id && $user->hasAnyRole(['admin', 'property_manager'])), 403);
    }
}
