<?php

namespace App\Modules\CRM\Models;

use App\Modules\CRM\Support\CrmAccess;
use App\Modules\Users\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sharet.africa work mailbox read and answered from CRM → Inbox (IMAP/SMTP on the mail server, see MailboxClient).
 * With user_id it is that account manager's own; without, it is shared with all CRM staff (sales@, onboarding@, support@).
 */
class CrmMailbox extends Model
{
    protected $table = 'crm_mailboxes';

    protected $fillable = ['address', 'label', 'user_id', 'password', 'connected_at', 'last_error'];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'encrypted', 'connected_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The mailboxes this person can read: their own and the shared ones (a sysadmin sees every one). */
    public function scopeVisibleTo(Builder $query, ?Authenticatable $user): Builder
    {
        if (CrmAccess::seesAll($user)) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('user_id', $user?->id)->orWhereNull('user_id'));
    }

    /** Only the owner (or a sysadmin, for shared mailboxes or anyone's) types in or changes the password. */
    public function manageableBy(?Authenticatable $user): bool
    {
        return CrmAccess::seesAll($user) || ($this->user_id && $this->user_id === $user?->id);
    }

    public function isConnected(): bool
    {
        return filled($this->getRawOriginal('password')) && $this->connected_at !== null;
    }

    public function getNameAttribute(): string
    {
        return $this->label ?: $this->address;
    }
}
