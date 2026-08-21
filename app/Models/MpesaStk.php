<?php

namespace App\Models;

use App\Modules\Payments\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaStk extends Model
{
    protected $table = 'mpesa_stks';

    protected $fillable = [
        'user_id',
        'invoice_id',
        'invoice_item_id',
        'merchant_request_id',
        'checkout_request_id',
        'response_code',
        'response_description',
        'customer_message',
        'amount',
        'phone_number',
        'mpesa_receipt_number',
        'transaction_date',
        'metadata',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'transaction_reference', 'checkout_request_id');
    }

    // Status helpers
    public function isSuccessful()
    {
        return $this->response_code === '0';
    }

    public function isPending()
    {
        return $this->response_code === '1' || is_null($this->response_code);
    }

    public function isFailed()
    {
        return $this->response_code === '2' || $this->response_code === '1032';
    }

    // Get status label
    public function getStatusLabelAttribute()
    {
        if ($this->isSuccessful()) {
            return 'Success';
        } elseif ($this->isPending()) {
            return 'Pending';
        } else {
            return 'Failed';
        }
    }

    // Get status color class
    public function getStatusColorAttribute()
    {
        if ($this->isSuccessful()) {
            return 'green';
        } elseif ($this->isPending()) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('response_code', '0');
    }

    public function scopePending($query)
    {
        return $query->whereNull('response_code')->orWhere('response_code', '1');
    }

    public function scopeFailed($query)
    {
        return $query->where('response_code', '2');
    }

    public function scopeByCheckoutId($query, $checkoutId)
    {
        return $query->where('checkout_request_id', $checkoutId);
    }

    // Get formatted amount
    public function getFormattedAmountAttribute()
    {
        return 'KES ' . number_format($this->amount ?? 0, 2);
    }

    /**
     * Create from STK Push request
     */
    public static function createFromRequest($checkoutRequestId, $merchantRequestId, $phone, $amount, $invoiceId = null, $userId = null)
    {
        return self::create([
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'phone_number' => $phone,
            'amount' => $amount,
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'response_code' => '1', // Pending
            'response_description' => 'Payment initiated',
            'customer_message' => 'Please check your phone to complete payment',
        ]);
    }

    /**
     * Update from callback data
     */
    public function updateFromCallback($callbackData)
    {
        $updateData = [
            'response_code' => (string) ($callbackData['ResultCode'] ?? '1'),
            'response_description' => $callbackData['ResultDesc'] ?? 'Unknown',
            'metadata' => array_merge($callbackData, ['processed_at' => now()->toDateTimeString()]),
        ];

        // Check if successful
        if (isset($callbackData['ResultCode']) && $callbackData['ResultCode'] == 0) {
            // Look for CallbackMetadata
            if (isset($callbackData['CallbackMetadata']) && isset($callbackData['CallbackMetadata']['Item'])) {
                $metadata = $callbackData['CallbackMetadata']['Item'];
                
                foreach ($metadata as $item) {
                    $name = $item['Name'] ?? '';
                    $value = $item['Value'] ?? null;
                    
                    switch ($name) {
                        case 'MpesaReceiptNumber':
                            $updateData['mpesa_receipt_number'] = $value;
                            break;
                        case 'TransactionDate':
                            $updateData['transaction_date'] = date('Y-m-d H:i:s', strtotime($value));
                            break;
                        case 'Amount':
                            $updateData['amount'] = $value;
                            break;
                        case 'PhoneNumber':
                            $updateData['phone_number'] = (string) $value;
                            break;
                    }
                }
            }
            
            // If we didn't get metadata but have receipt, try to extract from response
            if (empty($updateData['mpesa_receipt_number']) && isset($callbackData['mpesa_receipt_number'])) {
                $updateData['mpesa_receipt_number'] = $callbackData['mpesa_receipt_number'];
            }
        }

        $this->update($updateData);
        return $this;
    }

    /**
     * Process successful payment and create Payment record
     */
    public function processSuccessfulPayment()
    {
        if (!$this->isSuccessful()) {
            return ['success' => false, 'message' => 'Payment not successful'];
        }

        if ($this->payment) {
            return ['success' => false, 'message' => 'Payment already processed'];
        }

        if (!$this->invoice_id) {
            return ['success' => false, 'message' => 'No invoice associated with this payment'];
        }

        $invoice = Invoice::find($this->invoice_id);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }

        // Check if invoice is already paid
        if ($invoice->status === 'paid') {
            return ['success' => false, 'message' => 'Invoice already paid'];
        }

        DB::beginTransaction();

        try {
            // Get tenant
            $tenant = $invoice->tenancy?->tenant;
            
            if (!$tenant) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Tenant not found for invoice'];
            }

            $amount = (float) $this->amount;
            
            // Get current wallet balance
            $balanceBefore = 0;
            try {
                if (method_exists($tenant, 'balance')) {
                    $balanceBefore = (float) $tenant->balance;
                }
            } catch (\Exception $e) {
                Log::warning('Could not get wallet balance', ['error' => $e->getMessage()]);
            }

            // Create payment record
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'payment_method' => 'mpesa_stk',
                'source' => 'public_link',
                'amount' => $amount,
                'wallet_balance_before' => $balanceBefore,
                'wallet_balance_after' => $balanceBefore + $amount,
                'transaction_reference' => $this->checkout_request_id,
                'external_reference' => $this->mpesa_receipt_number,
                'status' => 'completed',
                'is_reconciled' => false,
                'meta' => [
                    'mpesa_stk_id' => $this->id,
                    'mpesa_receipt' => $this->mpesa_receipt_number,
                    'transaction_date' => $this->transaction_date,
                    'phone_number' => $this->phone_number,
                    'payment_source' => 'public_link',
                    'checkout_request_id' => $this->checkout_request_id,
                    'merchant_request_id' => $this->merchant_request_id,
                    'paid_at' => now()->toISOString(),
                ]
            ]);

            // Update invoice total paid
            $invoice->total_paid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
            
            // Update invoice status
            if ($invoice->total_paid >= $invoice->total_amount) {
                $invoice->status = 'paid';
            } elseif ($invoice->total_paid > 0) {
                $invoice->status = 'partial';
            }
            $invoice->save();

            DB::commit();

            Log::info('Payment processed successfully from M-Pesa callback', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'receipt' => $this->mpesa_receipt_number
            ]);

            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment' => $payment,
                'invoice' => $invoice
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process payment from M-Pesa callback: ' . $e->getMessage(), [
                'stk_id' => $this->id,
                'trace' => $e->getTraceAsString()
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}