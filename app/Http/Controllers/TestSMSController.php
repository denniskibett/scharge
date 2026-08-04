<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\SMS\Services\KenyaSMS;
use App\Services\CampaignService;

class TestSMSController extends Controller
{
    protected $kenyaSms;
    protected $campaignService;

    public function __construct(KenyaSMS $kenyaSms, CampaignService $campaignService)
    {
        $this->kenyaSms = $kenyaSms;
        $this->campaignService = $campaignService;
    }

    /**
     * Test sending a single SMS
     */
    public function testSendSms(Request $request)
    {
        $phone = $request->input('phone', '254712345678');
        $message = $request->input('message', 'Test SMS from SHARETENT - ' . now());

        $result = $this->kenyaSms->sendOne($phone, $message);

        return response()->json([
            'test' => 'Send SMS',
            'phone' => $phone,
            'message' => $message,
            'sandbox' => $this->kenyaSms->isSandbox(),
            'result' => $result
        ]);
    }

    /**
     * Test phone number formatting
     */
    public function testPhoneFormat(Request $request)
    {
        $phone = $request->input('phone', '0712345678');

        $formatted = $this->kenyaSms->formatPhoneNumber($phone);
        $valid = $this->kenyaSms->validatePhone($phone);

        return response()->json([
            'test' => 'Phone Format',
            'original' => $phone,
            'formatted' => $formatted,
            'valid' => $valid ? 'Yes (Safaricom)' : 'No',
            'sandbox' => $this->kenyaSms->isSandbox()
        ]);
    }

    /**
     * Test message parts calculation
     */
    public function testMessageParts(Request $request)
    {
        $message = $request->input('message', 'Hello from SHARETENT! This is a test message.');

        $parts = $this->kenyaSms->getMessageParts($message);
        $cost = $this->kenyaSms->getEstimatedCost($message);

        return response()->json([
            'test' => 'Message Parts',
            'message' => $message,
            'length' => strlen($message),
            'parts' => $parts,
            'estimated_cost' => $cost . ' KES',
            'sandbox' => $this->kenyaSms->isSandbox()
        ]);
    }

    /**
     * Test getting balance
     */
    public function testBalance()
    {
        $balance = $this->kenyaSms->getBalance();

        return response()->json([
            'test' => 'Balance Check',
            'balance' => $balance,
            'sandbox' => $this->kenyaSms->isSandbox()
        ]);
    }

    /**
     * Test quiet hours check
     */
    public function testQuietHours()
    {
        $isQuiet = $this->kenyaSms->isQuietHours();

        return response()->json([
            'test' => 'Quiet Hours Check',
            'is_quiet_hours' => $isQuiet,
            'current_time' => now()->setTimezone('EAT')->format('H:i'),
            'quiet_hours' => config('sms.kenyasms.quiet_hours'),
            'sandbox' => $this->kenyaSms->isSandbox()
        ]);
    }

    /**
     * Test campaign preview
     */
    public function testCampaignPreview(Request $request)
    {
        $templateId = $request->input('template_id', 1);
        $filters = $request->input('filters', []);

        $result = $this->campaignService->previewCampaign($templateId, $filters);

        return response()->json([
            'test' => 'Campaign Preview',
            'template_id' => $templateId,
            'filters' => $filters,
            'previews' => $result,
            'sandbox' => $this->kenyaSms->isSandbox()
        ]);
    }
}