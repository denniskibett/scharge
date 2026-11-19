<?php

namespace App\Modules\CRM\Support;

/**
 * A received email (raw RFC 822 file from a Maildir), parsed just enough for the CRM:
 * who sent it, the subject, which of our emails it answers, and the new text (quoted history removed).
 */
class InboundEmail
{
    public string $messageId = '';

    public string $from = '';

    public string $fromName = '';

    public string $subject = '';

    /** @var string[] message-ids it answers (In-Reply-To first, then References), without <> */
    public array $replyTo = [];

    public ?string $date = null;

    public string $text = '';

    public bool $auto = false;

    /** @var array<string, string> lower-case header name => value (last wins) */
    public array $headers = [];

    public static function parse(string $raw): self
    {
        $m = new self;
        [$head, $body] = self::split($raw);
        $m->headers = self::headers($head);
        $h = fn ($k) => $m->headers[$k] ?? '';

        $m->messageId = trim($h('message-id'), " <>\t");
        $m->subject = trim(self::decodeWords($h('subject')));
        [$m->fromName, $m->from] = self::address(self::decodeWords($h('from')));
        preg_match_all('/<([^<>\s]+)>/', $h('in-reply-to').' '.$h('references'), $ids);
        $m->replyTo = array_values(array_unique($ids[1]));
        $m->date = $h('date') ?: null;

        $auto = strtolower($h('auto-submitted'));
        $m->auto = ($auto !== '' && $auto !== 'no')
            || $h('x-autoreply') !== '' || $h('x-autorespond') !== ''
            || preg_match('/^(auto_reply|bulk|junk)$/i', trim($h('precedence')))
            || preg_match('/^(auto(matic)?[ -]?reply|out of (the )?office|autoreply|undeliverable|delivery status|returned mail|mail delivery)/i', $m->subject)
            || preg_match('/^(mailer-daemon|postmaster)@/i', $m->from);

        $m->text = self::bodyText($m->headers, $body);

        return $m;
    }

    /** The new part of a reply: quoted lines and everything from "On … wrote:" / "From:" / a signature line down are dropped. */
    public function fresh(): string
    {
        $out = [];
        foreach (preg_split('/\R/', $this->text) as $line) {
            $t = trim($line);
            if (preg_match('/^(On .{4,200}wrote:?|-{2,}\s*Original Message\s*-{2,}|_{5,}|From:\s.+|Sent from my .+|Get Outlook for .+)$/i', $t)
                || preg_match('/^(Le|Am|El) .{4,200}(écrit|schrieb|escribió)\s*:?$/iu', $t)) {
                break;
            }
            if (str_starts_with($t, '>')) {
                continue;
            }
            $out[] = rtrim($line);
        }
        $text = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));

        return $text !== '' ? $text : trim($this->text);
    }

    /** Asked us to stop emailing them. */
    public function optOut(): bool
    {
        return (bool) preg_match('/\b(unsubscribe|remove me|take me off|stop (emailing|sending|contacting)|do not (email|contact)|don\'t (email|contact))\b/i', $this->subject.' '.mb_substr($this->fresh(), 0, 600));
    }

    private static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");

        return $pos === false ? [$raw, ''] : [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    private static function headers(string $head): array
    {
        $out = [];
        $head = preg_replace("/\n[ \t]+/", ' ', $head); // unfold
        foreach (explode("\n", $head) as $line) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $x)) {
                $out[strtolower($x[1])] = trim($x[2]);
            }
        }

        return $out;
    }

    public static function decodeWords(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return $value;
        }
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded !== false ? $decoded : $value;
    }

    /** @return array{0: string, 1: string} [name, lower-case email] */
    public static function address(string $value): array
    {
        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $value, $x)) {
            return [trim($x[1]), strtolower(trim($x[2]))];
        }
        if (preg_match('/[^\s<>"]+@[^\s<>"]+/', $value, $x)) {
            return ['', strtolower(trim($x[0], '.,;'))];
        }

        return ['', ''];
    }

    /** text/plain if there is one, else text/html turned into text. */
    private static function bodyText(array $headers, string $body): string
    {
        $parts = [];
        self::walk($headers, $body, $parts, 0);
        $plain = collect($parts)->firstWhere('type', 'text/plain');
        if ($plain) {
            return trim($plain['text']);
        }
        $html = collect($parts)->firstWhere('type', 'text/html');
        if ($html) {
            $t = preg_replace('#<(br|/p|/div|/li|/tr|/h\d)[^>]*>#i', "\n", $html['text']);
            $t = preg_replace('#<(style|script|head)[^>]*>.*?</\1>#is', '', $t);
            $t = preg_replace('#<blockquote.*?</blockquote>#is', '', $t);

            return trim(preg_replace("/[ \t]+\n/", "\n", html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        return '';
    }

    private static function walk(array $headers, string $body, array &$parts, int $depth): void
    {
        $ctype = $headers['content-type'] ?? 'text/plain';
        $type = strtolower(trim(explode(';', $ctype)[0]));
        if ($depth < 6 && str_starts_with($type, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $ctype, $b)) {
            foreach (array_slice(explode('--'.$b[1], $body), 1) as $chunk) {
                if (str_starts_with($chunk, '--')) {
                    break;
                }
                [$h, $content] = self::split(ltrim($chunk, "\n"));
                self::walk(self::headers($h), $content, $parts, $depth + 1);
            }

            return;
        }
        if (! in_array($type, ['text/plain', 'text/html'], true) || str_contains(strtolower($headers['content-disposition'] ?? ''), 'attachment')) {
            return;
        }
        $enc = strtolower(trim($headers['content-transfer-encoding'] ?? ''));
        $text = match ($enc) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $charset = preg_match('/charset="?([^";\s]+)"?/i', $ctype, $c) ? strtoupper($c[1]) : 'UTF-8';
        if ($charset !== 'UTF-8' && $charset !== 'US-ASCII') {
            $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
            $text = $converted !== false ? $converted : $text;
        }
        $parts[] = ['type' => $type, 'text' => mb_scrub($text, 'UTF-8')];
    }
}
