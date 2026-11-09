<?php

namespace App\Modules\Security\Models;

use App\Models\Company;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * An entry code for one visit or stay at a unit (database/schema/2026_11_09_gate_passes.sql).
 * The guest gets the code by SMS/email, shows it at the gate, and can check out from their phone
 * through /pass/{token}. Every actual entry/exit is a SecurityLog row with gate_pass_id.
 */
class GatePass extends Model
{
    protected $table = 'gate_passes';

    public const KINDS = [
        'stay' => 'Guest stay (BnB / short stay)',
        'visitor' => 'Visitor',
        'delivery' => 'Delivery',
        'contractor' => 'Contractor / service',
    ];

    protected $fillable = [
        'code', 'token', 'visitor_id', 'unit_id', 'property_id', 'company_id', 'kind', 'status',
        'guest_name', 'guest_phone', 'guest_email', 'guests_count', 'vehicle_plate', 'vehicle_desc',
        'valid_from', 'valid_until', 'checked_in_at', 'checked_in_by', 'check_in_method',
        'checked_out_at', 'checked_out_by', 'check_out_method', 'is_inside', 'sms_sent_at', 'email_sent_at',
        'notes', 'created_by',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'is_inside' => 'boolean',
        'guests_count' => 'integer',
    ];

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(SecurityLog::class, 'gate_pass_id')->orderBy('access_time');
    }

    public function place()
    {
        return $this->hasOne(VisitorPlace::class, 'visitor_id', 'visitor_id')->where('unit_id', $this->unit_id);
    }

    /** Still usable at the gate: not cancelled/finished and not past its end. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['issued', 'checked_in'], true);
    }

    public function isExpired(): bool
    {
        return $this->status === 'issued' && $this->valid_until && $this->valid_until->isPast();
    }

    public function isEarly(): bool
    {
        return $this->status === 'issued' && $this->valid_from && $this->valid_from->gt(now()->addHours(2));
    }

    public function isOverstay(): bool
    {
        return $this->status === 'checked_in' && $this->valid_until && $this->valid_until->isPast();
    }

    /** What the guard / host reads: label + Tailwind colour. */
    public function stateLabel(): array
    {
        return match (true) {
            $this->status === 'cancelled' => ['Cancelled', 'gray'],
            $this->status === 'checked_out' => ['Checked out', 'blue'],
            $this->isOverstay() => ['Inside · past end', 'amber'],
            $this->status === 'checked_in' && $this->is_inside => ['Inside', 'green'],
            $this->status === 'checked_in' => ['Checked in · out now', 'teal'],
            $this->isExpired() => ['Expired', 'gray'],
            $this->isEarly() => ['Not yet valid', 'amber'],
            default => ['Expected', 'indigo'],
        };
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function publicUrl(): string
    {
        $base = rtrim(preg_replace('#^http://(?!localhost|127\.)#', 'https://', (string) config('app.url')), '/');

        return $base.'/pass/'.$this->token;
    }

    public function placeLabel(): string
    {
        return collect([$this->unit?->unit_number ? 'Unit '.$this->unit->unit_number : null, $this->property?->name ?? $this->unit?->property?->name])
            ->filter()->implode(', ');
    }

    /** "10 Nov 14:00 – 14 Nov 11:00" in Nairobi time. */
    public function windowLabel(): string
    {
        $f = $this->valid_from?->timezone('Africa/Nairobi');
        $u = $this->valid_until?->timezone('Africa/Nairobi');
        if (! $f && ! $u) {
            return 'Any time';
        }
        if ($f && $u && $f->isSameDay($u)) {
            return $f->format('j M, H:i').' – '.$u->format('H:i');
        }

        return trim(($f ? $f->format('j M H:i') : 'now').' – '.($u ? $u->format('j M H:i') : 'open'));
    }
}
