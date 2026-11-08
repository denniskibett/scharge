<?php

namespace App\Http\Controllers;

use App\Models\CompanyIntegration;
use App\Models\SharetSweep;
use App\Services\Integrations;
use App\Services\Sharet\SharetClient;
use App\Services\Sharet\SharetCollections;
use App\Services\Sharet\SharetPayouts;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST /webhooks/sharet: events from Sharet Africa, signed with the secret saved on
 * Integrations › Sharet Africa. Each event id is processed once.
 */
class SharetWebhookController extends Controller
{
    public function __invoke(Request $request, SharetCollections $collections, SharetPayouts $payouts)
    {
        $platform = Integrations::sharet();
        $raw = $request->getContent();

        if (! $platform || ! SharetClient::verifySignature($request->header('Sharet-Signature'), $raw, $platform->get('webhook_secret'))) {
            Log::warning('Sharet webhook rejected: bad or missing signature', ['ip' => $request->ip()]);

            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $event = json_decode($raw, true) ?: [];
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        $data = (array) ($event['data'] ?? []);
        if ($id === '' || $type === '') {
            return response()->json(['error' => 'invalid_event'], 400);
        }

        try {
            DB::table('sharet_webhook_events')->insert(['event_id' => $id, 'type' => $type, 'payload' => json_encode($event), 'created_at' => now(), 'updated_at' => now()]);
        } catch (QueryException) {
            return response()->json(['received' => true, 'duplicate' => true]);   // already have this event
        }

        match (true) {
            str_starts_with($type, 'payment_request.') => $collections->apply($data),
            str_starts_with($type, 'payment.') => $this->payout($payouts, $data),
            $type === 'account.connected' => $this->connected($data, true),
            $type === 'account.disconnected' => $this->connected($data, false),
            default => null,
        };

        DB::table('sharet_webhook_events')->where('event_id', $id)->update(['processed_at' => now(), 'updated_at' => now()]);

        return response()->json(['received' => true]);
    }

    private function payout(SharetPayouts $payouts, array $payment): void
    {
        $sweep = SharetSweep::where('sharet_payment_id', $payment['id'] ?? '')->first();
        if ($sweep) {
            $payouts->record($sweep, $payment);
        }
    }

    /** A landlord approved (or removed) the connect link we sent them. */
    private function connected(array $data, bool $active): void
    {
        $connectionId = $data['id'] ?? null;
        $sharetId = $data['account']['sharet_id'] ?? $data['sharet_id'] ?? null;

        CompanyIntegration::where('provider', 'sharet_payouts')->get()
            ->filter(fn ($row) => $row->get('connection_id') && $row->get('connection_id') === $connectionId)
            ->each(function (CompanyIntegration $row) use ($active, $sharetId) {
                $row->mergeCredentials($active
                    ? ['sharet_account' => $sharetId ?? $row->get('sharet_account'), 'connection_status' => 'active']
                    : ['sharet_account' => null, 'connection_status' => 'disconnected', 'mode' => 'rail']);
                $row->save();
            });
    }
}
