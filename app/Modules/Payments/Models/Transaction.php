<?php
// app/Models/Transaction.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Payment;
use App\Models\Tenancy;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'tenancy_id',
        'raw_message',
        'parsed_amount',
        'parsed_reference_number',     
        'parsed_payment_method',
        'parsed_payment_datetime',
        'parsed_payer_name',
        'parsed_paid_to',
        'parsed_payment_month',
        'status',
        'verification_notes',
        'verified_by',
        'verified_at',
        'remaining_amount',
    ];

    protected $casts = [
        'parsed_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'parsed_payment_datetime' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // One transaction can create multiple payments (for partial allocations)
    public function payments()
    {
        return $this->hasMany(Payment::class, 'transaction_id');
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

    public function scopeAllocated($query)
    {
        return $query->where('status', 'allocated');
    }
}