<?php
// app/Services/MpesaService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $this->passkey = env('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
        
        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
        
        Log::info('🔐 M-Pesa Service Initialized', [
            'environment' => $this->environment,
            'base_url' => $this->baseUrl,
            'shortcode' => $this->shortcode,
            'passkey_set' => !empty($this->passkey),
            'consumer_key_set' => !empty($this->consumerKey),
            'consumer_secret_set' => !empty($this->consumerSecret),
        ]);
    }

    /**
     * Get OAuth Access Token with SSL disabled for development
     */
    public function getAccessToken()
    {
        $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        
        Log::info('🔐 M-Pesa Auth Request', [
            'url' => $url,
            'consumer_key' => $this->consumerKey ? substr($this->consumerKey, 0, 20) . '...' : 'MISSING',
            'consumer_secret' => $this->consumerSecret ? substr($this->consumerSecret, 0, 20) . '...' : 'MISSING',
        ]);
        
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->withOptions([
                    'verify' => false,
                    'timeout' => 30,
                ])
                ->get($url);

            Log::info('📤 M-Pesa Auth Response', [
                'status' => $response->status(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('✅ M-Pesa Access token generated successfully');
                return $data['access_token'] ?? null;
            }

            Log::error('❌ M-Pesa: Failed to get access token', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('❌ M-Pesa: Access token error', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get Access Token and Return Full Response (For Debugging)
     */
    public function getAccessTokenWithDebug()
    {
        $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->withOptions([
                    'verify' => false,
                    'timeout' => 30,
                ])
                ->get($url);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json(),
                'headers' => $response->headers(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Send STK Push to customer's phone
     */
    public function stkPush($phone, $amount, $accountReference, $transactionDesc = 'Payment')
    {
        Log::info('📱 Starting STK Push', [
            'phone' => $phone,
            'amount' => $amount,
            'account_reference' => $accountReference
        ]);

        $token = $this->getAccessToken();
        
        if (!$token) {
            Log::error('❌ STK Push Failed: No access token');
            return [
                'success' => false,
                'message' => 'Failed to get access token. Please check M-Pesa credentials.'
            ];
        }

        Log::info('✅ Access token obtained');

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

        Log::info('📤 M-Pesa STK Push Request', [
            'phone' => $phone,
            'amount' => $amount,
            'callback_url' => env('MPESA_CALLBACK_URL'),
        ]);

        try {
            $response = Http::withToken($token)
                ->withOptions([
                    'verify' => false,
                    'timeout' => 60,
                ])
                ->post($url, $payload);

            $responseData = $response->json();

            Log::info('📥 M-Pesa STK Push Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            if ($response->successful() && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0') {
                Log::info('✅ STK Push successful!', [
                    'checkout_request_id' => $responseData['CheckoutRequestID'] ?? null,
                ]);
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => $responseData['CustomerMessage'] ?? 'STK Push sent successfully',
                    'checkout_request_id' => $responseData['CheckoutRequestID'] ?? null,
                    'merchant_request_id' => $responseData['MerchantRequestID'] ?? null
                ];
            }

            $errorCode = $responseData['ResponseCode'] ?? 'Unknown';
            $errorDescription = $responseData['ResponseDescription'] ?? $responseData['errorMessage'] ?? 'STK Push failed';
            
            Log::error('❌ STK Push failed', [
                'response_code' => $errorCode,
                'response_description' => $errorDescription,
            ]);

            return [
                'success' => false,
                'message' => $errorDescription,
                'data' => $responseData,
                'error_code' => $errorCode
            ];

        } catch (\Exception $e) {
            Log::error('❌ M-Pesa STK Push Exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'System error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Query STK Push Status
     */
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
                ->withOptions(['verify' => false])
                ->post($url, $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0') {
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => $responseData['ResultDesc'] ?? 'Query successful',
                    'status' => $responseData['ResultCode'] ?? null
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['ResponseDescription'] ?? 'Query failed',
                'data' => $responseData
            ];

        } catch (\Exception $e) {
            Log::error('M-Pesa Query Status Error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Format phone number to 254XXXXXXXXX format
     */
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

    /**
     * B2B Payment (Business PayBill)
     */
    public function businessPayBill(array $data)
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to get access token'
            ];
        }

        $url = $this->baseUrl . '/mpesa/b2b/v1/paymentrequest';
        
        $payload = [
            'Initiator' => $data['initiator'] ?? $this->initiator,
            'SecurityCredential' => $data['security_credential'] ?? $this->securityCredential,
            'CommandID' => 'BusinessPayBill',
            'SenderIdentifierType' => $data['sender_type'] ?? '4',
            'ReceiverIdentifierType' => $data['receiver_type'] ?? '4',
            'Amount' => $data['amount'],
            'PartyA' => $data['party_a'] ?? $this->shortcode,
            'PartyB' => $data['party_b'],
            'AccountReference' => $data['account_reference'],
            'Requester' => $data['requester'] ?? null,
            'Remarks' => $data['remarks'] ?? 'OK',
            'QueueTimeOutURL' => $data['queue_timeout_url'] ?? env('MPESA_QUEUE_URL'),
            'ResultURL' => $data['result_url'] ?? env('MPESA_B2B_RESULT_URL'),
        ];

        $payload = array_filter($payload, function ($value) {
            return !is_null($value);
        });

        try {
            $response = Http::withToken($token)
                ->withOptions(['verify' => false])
                ->post($url, $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0') {
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => $responseData['ResponseDescription'] ?? 'Transaction initiated successfully'
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['ResponseDescription'] ?? 'Transaction failed',
                'data' => $responseData
            ];

        } catch (\Exception $e) {
            Log::error('M-Pesa B2B Error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Pay a bill (simplified wrapper for B2B)
     */
    public function payBill($amount, $partyB, $accountReference, $requester = null)
    {
        return $this->businessPayBill([
            'amount' => $amount,
            'party_b' => $partyB,
            'account_reference' => $accountReference,
            'requester' => $requester,
            'remarks' => 'Bill Payment',
        ]);
    }
}