<?php

namespace App\Services\Listings;

use App\Models\Company;
use App\Models\User;
use App\Modules\SMS\Services\KenyaSMS;
use App\Services\ListingCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Where a renter's viewing / booking request from Open listings goes (docs/ONBOARDING.md):
 *  - the listing's building belongs to a scharge customer: the request is that landlord's (Property › Enquiries), and
 *    their admins and property managers get an SMS at once;
 *  - otherwise (building not managed on scharge yet, or a prospect company): it is an onboarding lead for scharge's
 *    sales team (CRM › Requests), with the building, so sales can call the owner with real demand in hand.
 */
class EnquiryRouter
{
    /** @return array{company_id: ?int, property_id: ?int, route: string} */
    public function route(?string $slug): array
    {
        $item = $slug ? app(ListingCatalog::class)->find($slug) : null;
        $propertyId = $item['property_id'] ?? null;
        $companyId = $propertyId ? DB::table('properties')->where('id', $propertyId)->value('company_id') : null;
        $customer = $companyId ? Company::withoutGlobalScopes()->whereKey($companyId)->where(fn ($q) => $q->whereNull('lifecycle')->orWhere('lifecycle', '!=', 'prospect'))->exists() : false;

        return ['company_id' => $customer ? (int) $companyId : null, 'property_id' => $propertyId ? (int) $propertyId : null,
            'route' => $customer ? 'landlord' : 'onboarding'];
    }

    /** SMS the landlord's admins and property managers about a new request. Returns how many were told. */
    public function notifyLandlord(int $companyId, string $name, string $phone, string $listing, ?string $when): int
    {
        $staff = User::where('company_id', $companyId)->whereNotNull('phone')
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'property_manager']))->limit(5)->get();
        $text = mb_substr("New viewing request: {$name} ({$phone}) for {$listing}".($when ? ", {$when}" : '').'. Reply on WhatsApp, then mark it in scharge › Enquiries.', 0, 300);
        $sent = 0;
        foreach ($staff as $user) {
            try {
                $res = app(KenyaSMS::class)->forCompany($companyId)->sendOne($user->phone, $text);
                $sent += ($res['success'] ?? false) ? 1 : 0;
            } catch (\Throwable $e) {
                Log::warning('Enquiry SMS to landlord failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }
}
