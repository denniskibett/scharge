<?php

namespace App\Services\Gate;

use App\Mail\GatePassEmail;
use App\Modules\Security\Models\GatePass;
use App\Services\EmailOtp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Messages of the gate journey, all sent after the response (no queue worker on live):
 *   guest  SMS + email with the code and the private link (show at the gate, check out from the phone)
 *   host   email to whoever issued the pass when the guest checks in / out (confirmed emails only)
 */
class GateNotify
{
    /** The SMS a guest gets. Kept to about one SMS (160 chars) before the link. */
    public static function smsText(GatePass $pass): string
    {
        $where = $pass->unit?->unit_number ? $pass->unit->unit_number.', '.($pass->property?->name ?? '') : ($pass->property?->name ?? '');
        $from = $pass->valid_from?->timezone('Africa/Nairobi');

        return 'Gate code '.$pass->code.' for '.trim($where, ', ')
            .($from && $from->isFuture() ? ' from '.$from->format('j M H:i') : '')
            .'. Show it to the guard. Pass & check-out: '.$pass->publicUrl();
    }

    /** @return array{sms: ?string, email: ?string} what was queued, for the flash message */
    public static function sendToGuest(GatePass $pass, bool $sms = true, bool $email = true): array
    {
        $out = ['sms' => null, 'email' => null];
        $id = $pass->id;
        if ($sms && filled($pass->guest_phone)) {
            $out['sms'] = $pass->guest_phone;
            dispatch(function () use ($id) {
                $pass = GatePass::with('unit', 'property')->find($id);
                if (! $pass) {
                    return;
                }
                try {
                    $res = app(\App\Modules\SMS\Services\KenyaSMS::class)->forCompany($pass->company_id)
                        ->sendOne($pass->guest_phone, self::smsText($pass), 'transactional');
                    if (! empty($res['success'])) {
                        $pass->forceFill(['sms_sent_at' => now()])->save();
                    } else {
                        Log::info('Gate pass SMS not sent', ['pass' => $id, 'result' => $res]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Gate pass SMS failed', ['pass' => $id, 'error' => $e->getMessage()]);
                }
            })->afterResponse();
        }
        if ($email && self::validEmail($pass->guest_email)) {
            $out['email'] = $pass->guest_email;
            dispatch(function () use ($id) {
                $pass = GatePass::with('unit.property', 'property', 'company')->find($id);
                if (! $pass || ! EmailOtp::mailWorks()) {
                    return;
                }
                try {
                    Mail::to($pass->guest_email)->send(new GatePassEmail($pass, 'pass'));
                    $pass->forceFill(['email_sent_at' => now()])->save();
                } catch (\Throwable $e) {
                    Log::warning('Gate pass email failed', ['pass' => $id, 'error' => $e->getMessage()]);
                }
            })->afterResponse();
        }

        return $out;
    }

    /** Tell the host (who issued the pass) that their guest arrived / left. */
    public static function hostEvent(GatePass $pass, string $event): void
    {
        $id = $pass->id;
        dispatch(function () use ($id, $event) {
            $pass = GatePass::with('unit.property', 'property', 'company', 'creator')->find($id);
            $user = $pass?->creator;
            if (! $user || $user->hasRole('security') || ! self::validEmail($user->email) || ! EmailOtp::mailWorks() || EmailOtp::needs($user)) {
                return; // no host email, the guard issued it, mail off, or the email isn't confirmed
            }
            try {
                Mail::to($user->email)->send(new GatePassEmail($pass, $event === 'in' ? 'arrived' : 'left'));
            } catch (\Throwable $e) {
                Log::warning('Gate host email failed', ['pass' => $id, 'error' => $e->getMessage()]);
            }
        })->afterResponse();
    }

    private static function validEmail(?string $email): bool
    {
        return filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! preg_match('/@(example\.(com|org|net)|test\.com|mailinator\.com)$|\.(test|invalid|local)$/i', $email);
    }
}
