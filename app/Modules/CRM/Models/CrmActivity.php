<?php

namespace App\Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    /** Types staff can log by hand; the rest are written by the system. */
    public const MANUAL_TYPES = ['note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'meeting' => 'Meeting'];

    protected $fillable = ['crm_company_id', 'user_id', 'created_by', 'type', 'summary', 'body', 'meta', 'occurred_at'];

    protected $casts = ['meta' => 'array', 'occurred_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
