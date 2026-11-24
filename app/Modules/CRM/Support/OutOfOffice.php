<?php

namespace App\Modules\CRM\Support;

use Illuminate\Support\Carbon;

/**
 * Reads an automatic reply to one of our emails: is it a bounce, when is the person back, and which other addresses
 * does it offer ("for urgent matters contact …", "our alternative email is …"). ReplyService acts on the result:
 * the email goes again on the morning they are back, and straight away to the addresses offered.
 */
class OutOfOffice
{
    private const MONTHS = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

    private const DAYS = 'monday|tuesday|wednesday|thursday|friday|saturday|sunday';

    /** Never written to: system senders and our own domain. */
    private const SKIP_LOCAL = '/^(no-?reply|do-?not-?reply|donotreply|mailer-daemon|postmaster|bounces?|notifications?|alerts?)$/i';

    /**
     * @param  array<int,string>  $known  addresses we already wrote to for this company (left out of the alternates)
     * @return array{bounce: bool, away: bool, return_on: ?Carbon, alternates: array<int,string>}
     */
    public static function read(InboundEmail $mail, ?Carbon $received = null, array $known = []): array
    {
        $received = ($received ?? now())->copy()->timezone(self::tz());
        $subject = (string) $mail->subject;
        $from = strtolower(trim((string) $mail->from));
        $text = preg_replace('/[ \t]+/', ' ', str_replace("\r", '', $mail->fresh()));

        if (self::isBounce($subject, $from)) {
            return ['bounce' => true, 'away' => false, 'return_on' => null, 'alternates' => []];
        }
        $returnOn = self::returnDate($subject."\n".$text, $received); // "(returning 11/30/2026)" is often only in the subject
        $away = $returnOn !== null || (bool) preg_match('/\b(out of (the )?office|away from (the |my )?(office|desk)|on (annual |medical |maternity |paternity |study )?leave|on (holiday|vacation)|limited access to (my )?e-?mail|currently away|travell?ing)\b/i', $subject.' '.$text);

        return ['bounce' => false, 'away' => $away, 'return_on' => $returnOn, 'alternates' => self::alternates($text, $from, $known)];
    }

    public static function isBounce(string $subject, string $from): bool
    {
        return (bool) preg_match('/(undeliver|delivery (status|failure|has failed)|returned mail|mail delivery|failure notice|not (been )?delivered|delayed \d+ hours|message blocked)/i', $subject)
            || (bool) preg_match('/^(mailer-daemon|postmaster)@/i', $from);
    }

    /** Other addresses the reply offers, in the order written. */
    public static function alternates(string $text, string $from, array $known = []): array
    {
        $known = array_map(fn ($e) => strtolower(trim((string) $e)), array_merge($known, [$from]));
        [$fromLocal, $fromDomain] = array_pad(explode('@', $from, 2), 2, '');
        preg_match_all('/(?<![\w.+-])[a-z0-9][a-z0-9._%+-]*@[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}(?![\w-])/i', $text, $m);
        $out = [];
        foreach ($m[0] as $email) {
            $email = strtolower(rtrim($email, '.'));
            [$local, $domain] = explode('@', $email, 2);
            if (in_array($email, $known, true) || in_array($email, $out, true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                || str_ends_with($domain, 'sharet.africa') || preg_match(self::SKIP_LOCAL, $local)
                || preg_match('/\.(png|jpe?g|gif)$/', $local)) {
                continue;
            }
            // A signature that breaks the sender's own address ("t ilisiviews@…") is the sender, not someone else.
            if ($domain === $fromDomain && $local !== $fromLocal && (str_ends_with($fromLocal, $local) || str_starts_with($fromLocal, $local))) {
                continue;
            }
            $out[] = $email;
        }

        return array_slice($out, 0, 5);
    }

    /** The morning they are back, when the reply says so: today or later, at most six months ahead. */
    public static function returnDate(string $text, Carbon $received): ?Carbon
    {
        $date = '(?:(?:'.self::DAYS.'),?\s+)?(?:\d{1,2}(?:st|nd|rd|th)?\s+(?:of\s+)?(?:'.self::MONTHS.')\.?,?(?:\s+\d{4})?|(?:'.self::MONTHS.')\.?\s+\d{1,2}(?:st|nd|rd|th)?,?(?:\s+\d{4})?|\d{1,2}[\/.-]\d{1,2}(?:[\/.-]\d{2,4})?|(?:'.self::DAYS.')|tomorrow)';
        $on = '(?:back|return(?:ing)?|resum(?:e|ing)|report(?:ing)? back|in the office|available)\s+(?:to (?:the |my )?(?:office|work|desk)\s+)?(?:on|from|by)?\s*';
        $until = '(?:until|till|through|thru|up to|to)\s+(?:and including\s+)?';
        foreach ([[$on, 0], [$until, 1]] as [$lead, $nextDay]) {
            if (preg_match('/\b'.$lead.'('.$date.')\b/i', $text, $mm)) {
                $d = self::parseDate($mm[1], $received);
                if ($d && $nextDay) {
                    $d = $d->copy()->addWeekday();
                }
                if ($d && $d->gte($received->copy()->startOfDay()) && $d->lte($received->copy()->addMonths(6))) {
                    return $d->setTime(8, 0);
                }
            }
        }

        return null;
    }

    private static function parseDate(string $raw, Carbon $received): ?Carbon
    {
        $s = strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
        $s = preg_replace('/^(?:'.self::DAYS.'),?\s+(?=\d|'.self::MONTHS.')/i', '', $s);
        if ($s === 'tomorrow') {
            return $received->copy()->addDay()->startOfDay();
        }
        if (preg_match('/^('.self::DAYS.')$/', $s)) {
            return $received->copy()->startOfDay()->next(ucfirst($s));
        }
        try {
            if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})(?:[\/.-](\d{2,4}))?$/', $s, $p)) {
                $year = isset($p[3]) ? (int) (strlen($p[3]) === 2 ? '20'.$p[3] : $p[3]) : $received->year;
                [$day, $month] = [(int) $p[1], (int) $p[2]];       // Kenya writes the day first…
                if ($month > 12 && $day <= 12) {
                    [$day, $month] = [$month, $day];                // …US mail systems write 11/30/2026
                }
                if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || ! checkdate($month, $day, $year)) {
                    return null;
                }
                $d = Carbon::create($year, $month, $day, 0, 0, 0, self::tz());
                $explicit = isset($p[3]);
            } else {
                $clean = preg_replace('/(\d)(st|nd|rd|th)\b/', '$1', str_replace([',', ' of '], [' ', ' '], $s));
                $explicit = (bool) preg_match('/\b\d{4}\b/', $clean);
                $d = Carbon::parse($clean.($explicit ? '' : ' '.$received->year), self::tz())->startOfDay();
            }
        } catch (\Throwable) {
            return null;
        }
        if (! $explicit && $d->lt($received->copy()->startOfDay())) {
            $d->addYear(); // "back on 5 January", written in December
        }

        return $d;
    }

    private static function tz(): string
    {
        return config('app.schedule_timezone', 'Africa/Nairobi');
    }
}
