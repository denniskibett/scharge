<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** One signed-in phone of one person (scharge app). Plain token shown once; only its hash is kept. */
class MobileSession extends Model
{
    /** Sessions not used for this many days stop working (the person signs in with an SMS code again). */
    public const IDLE_DAYS = 90;

    public const MAX_PIN_FAILURES = 5;

    protected $fillable = ['user_id', 'device_name', 'platform', 'signed_in_with'];

    protected $hidden = ['token_hash', 'pin_hash'];

    protected $casts = ['last_used_at' => 'datetime', 'pin_failures' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Creates the secret and returns the plain token (mob_…) once. */
    public function issue(): string
    {
        $plain = 'mob_'.Str::random(48);
        $this->token_hash = hash('sha256', $plain);

        return $plain;
    }

    public static function findByPlain(?string $plain): ?self
    {
        if (! $plain || ! str_starts_with($plain, 'mob_')) {
            return null;
        }
        $session = static::where('token_hash', hash('sha256', $plain))->first();
        if ($session && $session->last_used_at && $session->last_used_at->lt(now()->subDays(self::IDLE_DAYS))) {
            $session->delete();

            return null;
        }

        return $session;
    }
}
