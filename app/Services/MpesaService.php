<?php
// app/Services/MpesaService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\MpesaStk;

class MpesaService
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $baseUrl;
    protected $environment;
    protected $shortcode;
    protected $initiator;
    protected $securityCredential;
    protected $passkey;

    public function __construct()
    {
        $this->consumerKey = env('MPESA_CONSUMER_KEY');
        $this->consumerSecret = env('MPESA_CONSUMER_SECRET');
        $this->environment = env('MPESA_ENVIRONMENT', 'sandbox');
        $this->shortcode = env('MPESA_SHORTCODE', '174379');
        $this->initiator = env('MPESA_INITIATOR', 'sharet_api');
        $this->securityCredential = env('MPESA_SECURITY_CREDENTIAL', '');
        $this->passkey = env('MPESA_PASSKEY', '');
        
        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public function getAccessToken()
    {
        $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(30)
                ->withoutVerifying()
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error('Failed to get access token', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Access token error: ' . $e->getMessage());
            return null;
        }
    }

    public function getAccessTokenWithDebug()
    {
        $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(30)
                ->withoutVerifying()
                ->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'response' => $response->json(),
                    'status' => $response->status()
                ];
            }

            return [
                'success' => false,
                'response' => $response->body(),
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function stkPush($phone, $amount, $accountReference, $transactionDesc = 'Payment', $userId = null, $invoiceId = null, $invoiceItemId = null)
    {
        Log::info('STK Push Initiated', ['phone' => $phone, 'amount' => $amount]);

        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to get access token'
            ];
        }

        $url = $this->baseUrl . '/mpesa/stkpush/v1/processrequest';
        
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $phone = $this->formatPhoneNumber($phone);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) $amount,
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => env('MPESA_CALLBACK_URL'),
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc,
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->withoutVerifying()
                ->post($url, $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0') {
                try {
                    $mpesaStk = MpesaStk::create([
                        'user_id' => $userId,
                        'invoice_id' => $invoiceId,
                        'invoice_item_id' => $invoiceItemId,
                        'merchant_request_id' => $responseData['MerchantRequestID'] ?? null,
                        'checkout_request_id' => $responseData['CheckoutRequestID'] ?? null,
                        'response_code' => 0,
                        'response_description' => $responseData['ResponseDescription'] ?? 'STK Push initiated',
                        'customer_message' => $responseData['CustomerMessage'] ?? 'Please check your phone',
                        'amount' => $amount,
                        'phone_number' => $phone,
                        'status' => 'pending',
                        'metadata' => [
                            'account_reference' => $accountReference,
                            'initiated_by' => $userId,
                            'initiated_at' => now()->toISOString(),
                        ],
                    ]);

                    return [
                        'success' => true,
                        'message' => $responseData['CustomerMessage'] ?? 'STK Push sent successfully',
                        'checkout_request_id' => $responseData['CheckoutRequestID'] ?? null,
                        'merchant_request_id' => $responseData['MerchantRequestID'] ?? null,
                        'stk_id' => $mpesaStk->id,
                    ];

                } catch (\Exception $e) {
                    Log::error('Failed to save STK: ' . $e->getMessage());
                }
            }

            return [
                'success' => false,
                'message' => $responseData['ResponseDescription'] ?? 'STK Push failed',
            ];

        } catch (\Exception $e) {
            Log::error('STK Push error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function queryStatus($checkoutRequestId)
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to get access token'
            ];
        }

        $url = $this->baseUrl . '/mpesa/stkpushquery/v1/query';
        
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->withoutVerifying()
                ->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => $responseData['ResultDesc'] ?? 'Query successful',
                    'status' => $responseData['ResultCode'] ?? null
                ];
            }

            return [
                'success' => false,
                'message' => 'Query failed'
            ];

        } catch (\Exception $e) {
            Log::error('Query Status error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function formatPhoneNumber($phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }
        
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        
        if (str_starts_with($phone, '7')) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}