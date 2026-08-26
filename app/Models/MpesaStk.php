<?php
// app/Models/MpesaStk.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Payments\Models\Invoice;

class MpesaStk extends Model
{
    protected $table = 'mpesa_stks';
    
    protected $fillable = [
        'user_id',
        'invoice_id',
        'invoice_item_id',
        'payment_id',           // ✅ New field
        'merchant_request_id',
        'checkout_request_id',
        'response_code',        // ✅ Now integer: 0 = success, 1+ = failure
        'result_code',          // ✅ New field: ResultCode from callback
        'response_description',
        'customer_message',
        'amount',
        'phone_number',
        'mpesa_receipt_number',
        'transaction_date',
        'status',               // ✅ New field: pending, completed, failed, cancelled
        'metadata',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'transaction_date' => 'datetime',
        'response_code' => 'integer',    // ✅ Cast to integer
        'result_code' => 'integer',      // ✅ Cast to integer
    ];
    
    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
    
    public function scopeSuccess($query)
    {
        return $query->where('response_code', 0);
    }
    
    public function scopeFailedResponse($query)
    {
        return $query->where('response_code', '>', 0);
    }
    
    // Helper methods
    public function isSuccess(): bool
    {
        return $this->response_code === 0;
    }
    
    public function isFailed(): bool
    {
        return $this->response_code !== null && $this->response_code > 0;
    }
    
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}