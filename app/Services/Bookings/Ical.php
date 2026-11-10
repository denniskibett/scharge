<?php

namespace App\Services\Bookings;

use Carbon\CarbonImmutable;

/** Minimal iCalendar (RFC 5545) reading and writing for channel calendars: all-day VEVENTs with UID, DTSTART, DTEND. */
class Ical
{
    /** @return array<int, array{uid: string, start: string, end: string, summary: string, cancelled: bool}> dates as Y-m-d */
    public static function parse(string $ics): array
    {
        // Unfold lines (a line starting with a space or tab continues the previous one).
        $lines = preg_split('/\r\n|\n|\r/', preg_replace("/\r?\n[ \t]/", '', $ics));
        $events = [];
        $e = null;
        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $e = [];

                continue;
            }
            if ($line === 'END:VEVENT') {
                if ($e !== null && isset($e['DTSTART'], $e['DTEND'])) {
                    $start = self::date($e['DTSTART']);
                    $end = self::date($e['DTEND']);
                    if ($start && $end && $end > $start) {
                        $events[] = ['uid' => mb_substr($e['UID'] ?? sha1($start.$end.($e['SUMMARY'] ?? '')), 0, 191), 'start' => $start, 'end' => $end,
                            'summary' => mb_substr(self::unescape($e['SUMMARY'] ?? ''), 0, 120), 'cancelled' => strtoupper($e['STATUS'] ?? '') === 'CANCELLED'];
                    }
                }
                $e = null;

                continue;
            }
            if ($e !== null && str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $e[strtoupper(explode(';', $name)[0])] = trim($value);
            }
        }

        return $events;
    }

    /** @param iterable<array{uid: string, start: string, end: string, summary: string}> $events */
    public static function build(string $name, iterable $events): string
    {
        $out = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//scharge//Bookings//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:'.self::escape($name)];
        $stamp = gmdate('Ymd\THis\Z');
        foreach ($events as $ev) {
            $out[] = 'BEGIN:VEVENT';
            $out[] = 'UID:'.$ev['uid'];
            $out[] = 'DTSTAMP:'.$stamp;
            $out[] = 'DTSTART;VALUE=DATE:'.str_replace('-', '', $ev['start']);
            $out[] = 'DTEND;VALUE=DATE:'.str_replace('-', '', $ev['end']);
            $out[] = 'SUMMARY:'.self::escape($ev['summary']);
            $out[] = 'END:VEVENT';
        }
        $out[] = 'END:VCALENDAR';

        return implode("\r\n", $out)."\r\n";
    }

    private static function date(string $v): ?string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $v, $m)) {
            // Date-times count by their calendar day (stays are whole nights).
            return CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3])->toDateString();
        }

        return null;
    }

    private static function escape(string $s): string
    {
        return str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $s);
    }

    private static function unescape(string $s): string
    {
        return str_replace(['\n', '\N', '\,', '\;', '\\\\'], [' ', ' ', ',', ';', '\\'], $s);
    }
}
