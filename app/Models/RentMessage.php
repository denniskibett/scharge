<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A bill or arrears reminder sent to a tenant by rent automation (App\Services\Rent\BillNotifier). */
class RentMessage extends Model
{
    use \App\Traits\CompanyScoped;

    protected $fillable = ['company_id', 'tenancy_id', 'invoice_id', 'kind', 'stage', 'channel', 'phone', 'status', 'error', 'balance', 'message'];

    protected $casts = ['balance' => 'decimal:2', 'stage' => 'integer'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }
}
