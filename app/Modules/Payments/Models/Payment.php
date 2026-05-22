<?php
// app/Models/Payment.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenancy_id',
        'invoice_id',
        'transaction_id',
        'reference_number',
        'amount',
        'payment_method',
        'transaction_message',
        'paid_to',
        'payer_name',
        'payment_datetime',
        'payment_month',
        'status',
        'verification_notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'payment_datetime' => 'datetime',
        'verified_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    
    // Links to the transactions table (the source transaction record)
    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
    
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
    
    // Invoice items paid by this payment
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'payment_id');
    }
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
    
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}