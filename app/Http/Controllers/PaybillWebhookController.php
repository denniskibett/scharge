<?php

namespace App\Http\Controllers;

use App\Services\Rent\PaybillPayments;
use App\Services\Rent\PaybillRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Safaricom C2B: payments made straight to a paybill. Safaricom calls these URLs (registered by PaybillRegistration
 * when Integrations › M-Pesa Daraja is tested). The secret in the path stops strangers posting fake payments.
 */
class PaybillWebhookController extends Controller
{
    private const ACCEPT = ['ResultCode' => '0', 'ResultDesc' => 'Accepted'];

    /** Validation: always accept; an unknown account number is matched by hand later, never bounced back to the tenant. */
    public function validation(Request $request, string $token): JsonResponse
    {
        abort_unless(hash_equals(PaybillRegistration::token(), $token), 404);

        return response()->json(self::ACCEPT);
    }

    public function confirmation(Request $request, string $token, PaybillPayments $paybill): JsonResponse
    {
        abort_unless(hash_equals(PaybillRegistration::token(), $token), 404);

        try {
            $row = $paybill->receive($request->all());
            Log::info('Paybill payment', ['trans_id' => $row->trans_id, 'status' => $row->status, 'company_id' => $row->company_id]);
        } catch (\Throwable $e) {
            Log::error('Paybill payment not recorded', ['error' => $e->getMessage(), 'payload' => $request->all()]);
        }

        return response()->json(self::ACCEPT);
    }
}
