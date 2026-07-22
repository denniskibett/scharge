<?php

namespace App\Modules\SMS\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\SMS\Models\SmsLog;

class WebhookController extends Controller
{
    /**
     * Handle Delivery Report (DLR) from KenyaSMS
     */
    public function handleDLR(Request $request)
    {
        // Log the incoming webhook data
        \Log::info('SMS DLR Webhook received', $request->all());

        try {
            // Extract data from the webhook
            $messageId = $request->input('message_id');
            $status = $request->input('status');
            $phone = $request->input('phone');
            $error = $request->input('error');

            // Find the SMS log by provider_message_id
            if ($messageId) {
                $log = SmsLog::where('provider_message_id', $messageId)->first();
                
                if ($log) {
                    // Update the log status
                    $log->status = $status ?? $log->status;
                    $log->failure_reason = $error ?? $log->failure_reason;
                    $log->save();

                    \Log::info('SMS DLR updated for message: ' . $messageId);
                } else {
                    \Log::warning('SMS DLR: No log found for message_id: ' . $messageId);
                }
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('SMS DLR webhook error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}