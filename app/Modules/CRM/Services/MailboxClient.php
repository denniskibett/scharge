<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\CrmMailbox;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Reads and sends for one CRM mailbox on the sharet.africa mail server: IMAP (Dovecot, port 993) to read,
 * SMTP submission (Postfix, port 587) to send, and a copy of every sent email appended to the Sent folder so
 * webmail and phones see it too. Needs PHP's imap extension (live has it).
 */
class MailboxClient
{
    public const PER_PAGE = 25;

    /** Folder roles shown as tabs, matched against the server's folder names. */
    public const ROLES = ['inbox' => 'Inbox', 'sent' => 'Sent', 'drafts' => 'Drafts', 'junk' => 'Junk', 'trash' => 'Trash'];

    /** @var resource|\IMAP\Connection|null */
    private $stream = null;

    private ?string $open = null;

    private ?array $folders = null;

    public function __construct(private CrmMailbox $mailbox, private ?string $password = null)
    {
        $this->password ??= $mailbox->password;
    }

    public static function available(): bool
    {
        return function_exists('imap_open');
    }

    public function __destruct()
    {
        if ($this->stream) {
            @imap_close($this->stream);
            imap_errors();
            imap_alerts();
        }
    }

    private function ref(string $folder = ''): string
    {
        return '{'.config('crm.mail.imap_host').':'.config('crm.mail.imap_port').'/imap/ssl/novalidate-cert}'.$folder;
    }

    /** Opens the mailbox (or switches folder). Throws with the server's reason when the login fails. */
    public function open(string $folder = 'INBOX'): self
    {
        if (! self::available()) {
            throw new RuntimeException('This server has no PHP imap extension, so the inbox cannot be read here.');
        }
        if ($this->stream && $this->open === $folder) {
            return $this;
        }
        if ($this->stream) {
            if (! @imap_reopen($this->stream, $this->ref($folder))) {
                throw new RuntimeException('Could not open the folder '.$folder.'.');
            }
        } else {
            $stream = @imap_open($this->ref($folder), $this->mailbox->address, (string) $this->password, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
            $errors = imap_errors() ?: [];
            imap_alerts();
            if (! $stream) {
                $reason = collect($errors)->first(fn ($e) => stripos($e, 'auth') !== false) ?? (end($errors) ?: 'the server did not answer');
                throw new RuntimeException(stripos($reason, 'auth') !== false ? 'Wrong password for '.$this->mailbox->address.'.' : 'Could not open '.$this->mailbox->address.': '.$reason);
            }
            $this->stream = $stream;
        }
        $this->open = $folder;

        return $this;
    }

    /** role => the server's folder name, e.g. ['inbox' => 'INBOX', 'sent' => 'Sent', ...]. */
    public function folders(): array
    {
        if ($this->folders !== null) {
            return $this->folders;
        }
        $this->open();
        $names = collect(@imap_list($this->stream, $this->ref(), '*') ?: [])
            ->map(fn ($f) => mb_convert_encoding(substr($f, strlen($this->ref())), 'UTF-8', 'UTF7-IMAP'))->values();
        $find = fn (array $words) => $names->first(fn ($n) => in_array(strtolower(preg_replace('/^INBOX[.\/]/i', '', $n)), $words, true));
        $this->folders = array_filter([
            'inbox' => 'INBOX',
            'sent' => $find(['sent', 'sent items', 'sent messages', 'sent mail']),
            'drafts' => $find(['drafts', 'draft']),
            'junk' => $find(['junk', 'spam', 'junk e-mail']),
            'trash' => $find(['trash', 'deleted', 'deleted items', 'deleted messages']),
        ]);

        return $this->folders;
    }

    /** The server's name for a role, creating Sent / Trash when the mailbox has none yet. */
    public function folder(string $role, bool $create = false): ?string
    {
        $name = $this->folders()[$role] ?? null;
        if (! $name && $create && in_array($role, ['sent', 'trash', 'drafts'], true)) {
            $name = ucfirst($role);
            @imap_createmailbox($this->stream, imap_utf7_encode($this->ref($name)));
            @imap_subscribe($this->stream, imap_utf7_encode($this->ref($name)));
            imap_errors();
            $this->folders[$role] = $name;
        }

        return $name;
    }

    /** Unread count of the inbox, without opening it as the current folder. */
    public function unread(): int
    {
        $this->open();
        $status = @imap_status($this->stream, $this->ref('INBOX'), SA_UNSEEN);

        return (int) ($status->unseen ?? 0);
    }

    /**
     * A page of messages, newest first.
     *
     * @return array{total: int, items: array<int, array>}
     */
    public function messages(string $folder, int $page = 1, ?string $search = null): array
    {
        $this->open($folder);
        if ($search !== null && trim($search) !== '') {
            $term = str_replace(['\\', '"'], '', trim($search));
            $uids = @imap_search($this->stream, 'TEXT "'.$term.'"', SE_UID, 'UTF-8') ?: [];
            rsort($uids);
        } else {
            $uids = @imap_sort($this->stream, SORTARRIVAL, true, SE_UID) ?: [];
        }
        imap_errors();
        $total = count($uids);
        $slice = array_slice($uids, max(0, ($page - 1) * self::PER_PAGE), self::PER_PAGE);
        if (! $slice) {
            return ['total' => $total, 'items' => []];
        }
        $order = array_flip($slice);
        $items = collect(@imap_fetch_overview($this->stream, implode(',', $slice), FT_UID) ?: [])
            ->map(function ($o) {
                [$fromName, $fromEmail] = self::person($o->from ?? '');
                [$toName, $toEmail] = self::person($o->to ?? '');

                return [
                    'uid' => (int) $o->uid,
                    'subject' => self::decode($o->subject ?? '') ?: '(no subject)',
                    'from_name' => $fromName, 'from_email' => $fromEmail,
                    'to_name' => $toName, 'to_email' => $toEmail,
                    'date' => isset($o->udate) ? \Illuminate\Support\Carbon::createFromTimestamp($o->udate)->timezone(config('app.timezone')) : null,
                    'seen' => (bool) ($o->seen ?? false),
                    'flagged' => (bool) ($o->flagged ?? false),
                    'answered' => (bool) ($o->answered ?? false),
                ];
            })
            ->sortBy(fn ($m) => $order[$m['uid']] ?? 0)->values()->all();

        return ['total' => $total, 'items' => $items];
    }

    /** One message: headers, the HTML and/or text body, and its attachments. Opening it marks it read. */
    public function message(string $folder, int $uid): array
    {
        $this->open($folder);
        $msgno = @imap_msgno($this->stream, $uid);
        if (! $msgno) {
            throw new RuntimeException('That email is no longer in this folder.');
        }
        $h = @imap_headerinfo($this->stream, $msgno);
        $structure = @imap_fetchstructure($this->stream, $uid, FT_UID);
        $out = ['html' => '', 'text' => '', 'attachments' => []];
        if ($structure) {
            $this->walk($uid, $structure, '', $out);
        }
        @imap_setflag_full($this->stream, (string) $uid, '\\Seen', ST_UID);
        imap_errors();

        $list = fn ($people) => collect($people ?? [])->map(fn ($p) => [
            'name' => self::decode($p->personal ?? ''),
            'email' => strtolower(($p->mailbox ?? '').(isset($p->host) ? '@'.$p->host : '')),
        ])->filter(fn ($p) => str_contains($p['email'], '@'))->values()->all();
        $from = $list($h->from ?? [])[0] ?? ['name' => '', 'email' => ''];

        return [
            'uid' => $uid,
            'subject' => self::decode($h->subject ?? '') ?: '(no subject)',
            'from' => $from,
            'reply_to' => $list($h->reply_to ?? [])[0] ?? $from,
            'to' => $list($h->to ?? []),
            'cc' => $list($h->cc ?? []),
            'date' => isset($h->udate) ? \Illuminate\Support\Carbon::createFromTimestamp($h->udate)->timezone(config('app.timezone')) : null,
            'message_id' => trim($h->message_id ?? ''),
            'references' => trim($h->references ?? ''),
            'html' => $out['html'],
            'text' => $out['text'],
            'attachments' => $out['attachments'],
        ];
    }

    private function walk(int $uid, object $part, string $number, array &$out): void
    {
        if ((int) $part->type === TYPEMULTIPART) {
            foreach ($part->parts ?? [] as $i => $child) {
                $this->walk($uid, $child, $number === '' ? (string) ($i + 1) : $number.'.'.($i + 1), $out);
            }

            return;
        }
        $no = $number === '' ? '1' : $number;
        $params = [];
        foreach (array_merge($part->ifparameters ? $part->parameters : [], $part->ifdparameters ? $part->dparameters : []) as $p) {
            $params[strtolower($p->attribute)] = $p->value;
        }
        $name = self::decode($params['filename'] ?? $params['name'] ?? '');
        $attachment = $name !== '' || ($part->ifdisposition && strtolower($part->disposition) === 'attachment') || (int) $part->type === TYPEMESSAGE;
        if ($attachment) {
            $out['attachments'][] = ['part' => $no, 'name' => $name ?: 'attachment-'.$no, 'size' => (int) ($part->bytes ?? 0), 'mime' => self::mime($part)];

            return;
        }
        if ((int) $part->type !== TYPETEXT) {
            return;
        }
        $body = self::decodeBody((string) @imap_fetchbody($this->stream, $uid, $no, FT_UID | FT_PEEK), (int) $part->encoding);
        $charset = $params['charset'] ?? 'UTF-8';
        if ($charset && strtoupper($charset) !== 'UTF-8') {
            $body = @mb_convert_encoding($body, 'UTF-8', $charset) ?: $body;
        }
        if (strtolower($part->subtype ?? '') === 'html') {
            $out['html'] .= $body;
        } else {
            $out['text'] .= ($out['text'] === '' ? '' : "\n\n").$body;
        }
    }

    /** @return array{name: string, mime: string, data: string} */
    public function attachment(string $folder, int $uid, string $part): array
    {
        $message = $this->message($folder, $uid);
        $file = collect($message['attachments'])->firstWhere('part', $part);
        if (! $file) {
            throw new RuntimeException('That attachment is no longer there.');
        }
        $structure = @imap_bodystruct($this->stream, imap_msgno($this->stream, $uid), $part);

        return [
            'name' => $file['name'],
            'mime' => $file['mime'],
            'data' => self::decodeBody((string) @imap_fetchbody($this->stream, $uid, $part, FT_UID | FT_PEEK), (int) ($structure->encoding ?? 0)),
        ];
    }

    /** Moves a message to Trash (or deletes it for good when it is already there). */
    public function trash(string $folder, int $uid): void
    {
        $this->open($folder);
        $trash = $this->folder('trash', true);
        if ($trash && $trash !== $folder) {
            @imap_mail_move($this->stream, (string) $uid, imap_utf7_encode($trash), CP_UID);
        } else {
            @imap_delete($this->stream, (string) $uid, FT_UID);
        }
        @imap_expunge($this->stream);
        imap_errors();
    }

    public function markUnread(string $folder, int $uid): void
    {
        $this->open($folder);
        @imap_clearflag_full($this->stream, (string) $uid, '\\Seen', ST_UID);
        imap_errors();
    }

    /**
     * Sends from this mailbox through the mail server and files a copy in Sent.
     *
     * @param  string[]  $to
     * @param  string[]  $cc
     * @param  UploadedFile[]  $files
     * @param  array{message_id?: string, references?: string, answered_uid?: int, folder?: string}  $thread
     */
    public function send(string $fromName, array $to, array $cc, string $subject, string $html, string $text, array $files = [], array $thread = []): string
    {
        $email = (new Email)
            ->from(new Address($this->mailbox->address, $fromName))
            ->to(...$to)
            ->subject($subject)
            ->html($html)
            ->text($text);
        if ($cc) {
            $email->cc(...$cc);
        }
        foreach ($files as $file) {
            $email->attachFromPath($file->getRealPath(), $file->getClientOriginalName(), $file->getMimeType() ?: 'application/octet-stream');
        }
        if (! empty($thread['message_id'])) {
            $email->getHeaders()->addTextHeader('In-Reply-To', $thread['message_id']);
            $email->getHeaders()->addTextHeader('References', trim(($thread['references'] ?? '').' '.$thread['message_id']));
        }

        $dsn = sprintf('smtp://%s:%s@%s:%d?verify_peer=0', rawurlencode($this->mailbox->address), rawurlencode((string) $this->password),
            config('crm.mail.smtp_host'), config('crm.mail.smtp_port'));
        $sent = Transport::fromDsn($dsn)->send($email);
        $raw = $sent?->toString() ?? $email->toString();

        // Best effort: the email has gone either way.
        try {
            if (self::available()) {
                $this->open();
                $folder = $this->folder('sent', true);
                if ($folder) {
                    @imap_append($this->stream, imap_utf7_encode($this->ref($folder)), str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw)), '\\Seen');
                }
                if (! empty($thread['answered_uid']) && ! empty($thread['folder'])) {
                    $this->open($thread['folder']);
                    @imap_setflag_full($this->stream, (string) $thread['answered_uid'], '\\Answered', ST_UID);
                }
                imap_errors();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $sent?->getMessageId() ?? '';
    }

    public static function decode(?string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        $decoded = function_exists('iconv_mime_decode') ? @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : false;

        return trim($decoded !== false ? $decoded : mb_decode_mimeheader($value));
    }

    /** "Jane Doe <jane@x.com>" → ['Jane Doe', 'jane@x.com']. */
    public static function person(string $raw): array
    {
        $raw = self::decode($raw);
        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $raw, $m)) {
            return [trim($m[1]), strtolower(trim($m[2]))];
        }

        return ['', strtolower(trim($raw))];
    }

    private static function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => (string) base64_decode($body),
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
    }

    private static function mime(object $part): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'model', 'application'];

        return ($types[(int) $part->type] ?? 'application').'/'.strtolower($part->subtype ?? 'octet-stream');
    }
}
