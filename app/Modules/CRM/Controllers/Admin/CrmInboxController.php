<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Models\CrmMailbox;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\MailboxClient;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRM → Inbox: staff read and answer their sharet.africa work mailboxes inside scharge. An account manager sees their
 * own mailbox (e.g. wkamau@) and the shared ones (sales@, onboarding@, support@); a sysadmin sees all and adds more.
 * Each mailbox is connected once with its password, typed in by its owner or a sysadmin and stored encrypted.
 */
class CrmInboxController extends Controller
{
    private const FREE_MAIL = ['gmail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'aol.com', 'ymail.com', 'protonmail.com', 'proton.me', 'msn.com'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $mailboxes = CrmMailbox::visibleTo($user)->with('user')->orderByRaw('user_id IS NULL')->orderBy('address')->get();
        $box = $mailboxes->firstWhere('id', (int) $request->query('box')) ?? $mailboxes->first(fn ($m) => $m->user_id === $user->id) ?? $mailboxes->first();
        $role = array_key_exists($request->query('folder'), MailboxClient::ROLES) ? $request->query('folder') : 'inbox';
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('q', ''));

        $data = ['mailboxes' => $mailboxes, 'box' => $box, 'role' => $role, 'page' => $page, 'search' => $search,
            'folders' => [], 'list' => ['total' => 0, 'items' => []], 'message' => null, 'error' => null, 'companies' => [],
            'unread' => [], 'available' => MailboxClient::available(),
            'owners' => CrmAccess::seesAll($user) ? User::role(['sysadmin', 'account_manager'])->orderBy('name')->get(['id', 'name', 'email']) : collect()];

        if ($box && $box->isConnected() && $data['available']) {
            try {
                $client = new MailboxClient($box);
                $data['folders'] = $client->folders();
                $folder = $data['folders'][$role] ?? 'INBOX';
                $data['list'] = $client->messages($folder, $page, $search);
                if ($uid = (int) $request->query('uid')) {
                    $data['message'] = $client->message($folder, $uid);
                    $data['message']['body'] = $this->safeHtml($data['message']);
                }
                $data['unread'][$box->id] = $client->unread();
                $emails = collect($data['list']['items'])->map(fn ($m) => $role === 'sent' ? $m['to_email'] : $m['from_email']);
                if ($data['message']) {
                    $emails->push($data['message']['from']['email'], ...collect($data['message']['to'])->pluck('email'));
                }
                $data['companies'] = $this->companiesFor($emails->filter()->unique()->values()->all(), $user);
                if ($box->last_error) {
                    $box->update(['last_error' => null]);
                }
            } catch (\Throwable $e) {
                $data['error'] = $e->getMessage();
                $box->update(['last_error' => Str::limit($e->getMessage(), 490)]);
            }
        }

        return view('crm.inbox', $data);
    }

    /** The owner (or a sysadmin) types the mailbox password once; it is checked against the server before saving. */
    public function connect(Request $request, CrmMailbox $mailbox): RedirectResponse
    {
        $this->authorizeManage($request, $mailbox);
        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);
        try {
            (new MailboxClient($mailbox, $data['password']))->open()->folders();
        } catch (\Throwable $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }
        $mailbox->update(['password' => $data['password'], 'connected_at' => now(), 'last_error' => null]);

        return redirect()->route('crm.inbox.index', ['box' => $mailbox->id])->with('success', $mailbox->address.' is connected.');
    }

    public function disconnect(Request $request, CrmMailbox $mailbox): RedirectResponse
    {
        $this->authorizeManage($request, $mailbox);
        $mailbox->update(['password' => null, 'connected_at' => null]);

        return redirect()->route('crm.inbox.index', ['box' => $mailbox->id])->with('success', $mailbox->address.' is disconnected. Its emails stay on the mail server.');
    }

    /** Sysadmin: add another work mailbox (it must already exist on the mail server) and say whose it is. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(CrmAccess::seesAll($request->user()), 403);
        $data = $request->validate([
            'address' => ['required', 'email', 'max:191', 'regex:/@sharet\.africa$/i', Rule::unique('crm_mailboxes', 'address')],
            'label' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], ['address.regex' => 'Use a @sharet.africa work address.']);
        $mailbox = CrmMailbox::create(['address' => strtolower($data['address']), 'label' => $data['label'] ?? null, 'user_id' => $data['user_id'] ?? null]);

        return redirect()->route('crm.inbox.index', ['box' => $mailbox->id])->with('success', $mailbox->address.' added. Connect it with its password.');
    }

    public function update(Request $request, CrmMailbox $mailbox): RedirectResponse
    {
        abort_unless(CrmAccess::seesAll($request->user()), 403);
        $data = $request->validate(['label' => ['nullable', 'string', 'max:120'], 'user_id' => ['nullable', 'integer', 'exists:users,id']]);
        $mailbox->update(['label' => $data['label'] ?? null, 'user_id' => $data['user_id'] ?? null]);

        return redirect()->route('crm.inbox.index', ['box' => $mailbox->id])->with('success', $mailbox->address.' updated.');
    }

    /** New email or a reply, sent from the mailbox itself (so it shows in Sent on webmail and phones too). */
    public function send(Request $request, CrmMailbox $mailbox, ContactService $contacts): RedirectResponse
    {
        $this->authorizeRead($request, $mailbox);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:2000'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'subject' => ['required', 'string', 'max:250'],
            'body' => ['required', 'string', 'max:100000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:10240'],
            'reply_uid' => ['nullable', 'integer'],
            'folder' => ['nullable', Rule::in(array_keys(MailboxClient::ROLES))],
        ]);
        $split = fn (?string $list) => collect(preg_split('/[\s,;]+/', (string) $list))->map(fn ($e) => strtolower(trim($e)))->filter()->unique()->values();
        $to = $split($data['to']);
        $cc = $split($data['cc'] ?? '');
        $bad = $to->merge($cc)->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($bad->isNotEmpty()) {
            return back()->withInput()->withErrors(['to' => 'Not an email address: '.$bad->join(', ')]);
        }

        $client = new MailboxClient($mailbox);
        $html = EmailBody::toHtml($data['body']);
        $thread = [];
        if (! empty($data['reply_uid'])) {
            try {
                $role = $data['folder'] ?? 'inbox';
                $folder = $client->folders()[$role] ?? 'INBOX';
                $original = $client->message($folder, (int) $data['reply_uid']);
                $thread = ['message_id' => $original['message_id'], 'references' => $original['references'], 'answered_uid' => $original['uid'], 'folder' => $folder];
                $quoted = trim($original['text']) !== '' ? e($original['text']) : e(trim(html_entity_decode(strip_tags($original['html']))));
                $html .= '<p style="color:#667085">On '.e($original['date']?->format('j M Y, H:i')).', '.e($original['from']['name'] ?: $original['from']['email']).' wrote:</p>'
                    .'<blockquote style="margin:0 0 0 8px;padding-left:12px;border-left:3px solid #d0d5dd;color:#475467">'.nl2br(Str::limit($quoted, 20000)).'</blockquote>';
            } catch (\Throwable) {
                // The original is gone: send it as a new email.
            }
        }
        $text = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>', '</blockquote>'], "\n", $html))));

        try {
            $client->send($request->user()->name, $to->all(), $cc->all(), $data['subject'], $html, $text, $request->file('files', []), $thread);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['to' => 'The email was not sent: '.Str::limit($e->getMessage(), 200)]);
        }

        // Log it on each CRM company it went to.
        foreach ($this->companiesFor($to->merge($cc)->all(), $request->user()) as $email => $company) {
            $contacts->log($company, 'email', 'Email: '.$data['subject'], Str::limit($text, 1000), ['from' => $mailbox->address, 'to' => $to->all(), 'cc' => $cc->all(), 'via' => 'inbox'], null, $request->user()->id);
        }

        return redirect()->route('crm.inbox.index', array_filter(['box' => $mailbox->id, 'folder' => $data['folder'] ?? null, 'uid' => $data['reply_uid'] ?? null]))
            ->with('success', 'Sent to '.$to->join(', ').'.');
    }

    public function trash(Request $request, CrmMailbox $mailbox, int $uid): RedirectResponse
    {
        $this->authorizeRead($request, $mailbox);
        $role = $request->input('folder', 'inbox');
        $client = new MailboxClient($mailbox);
        $client->trash($client->folders()[$role] ?? 'INBOX', $uid);

        return redirect()->route('crm.inbox.index', ['box' => $mailbox->id, 'folder' => $role])->with('success', 'Moved to Trash.');
    }

    public function unread(Request $request, CrmMailbox $mailbox, int $uid): RedirectResponse
    {
        $this->authorizeRead($request, $mailbox);
        $role = $request->input('folder', 'inbox');
        $client = new MailboxClient($mailbox);
        $client->markUnread($client->folders()[$role] ?? 'INBOX', $uid);

        return redirect()->route('crm.inbox.index', ['box' => $mailbox->id, 'folder' => $role]);
    }

    public function attachment(Request $request, CrmMailbox $mailbox, int $uid): Response
    {
        $this->authorizeRead($request, $mailbox);
        $role = (string) $request->query('folder', 'inbox');
        $client = new MailboxClient($mailbox);
        $file = $client->attachment($client->folders()[$role] ?? 'INBOX', $uid, (string) $request->query('part'));
        $name = str_replace(['"', "\r", "\n", '/', '\\'], '', $file['name']);

        return response($file['data'], 200, [
            'Content-Type' => 'application/octet-stream',   // always a download, never rendered on scharge's origin
            'Content-Disposition' => 'attachment; filename="'.Str::ascii($name).'"; filename*=UTF-8\'\''.rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Unread counts for the mailbox list (one quick IMAP STATUS each), for the Inbox tab and the switcher. */
    public function counts(Request $request)
    {
        $counts = [];
        foreach (CrmMailbox::visibleTo($request->user())->get() as $box) {
            if ($box->isConnected() && MailboxClient::available()) {
                try {
                    $counts[$box->id] = (new MailboxClient($box))->unread();
                } catch (\Throwable) {
                    $counts[$box->id] = null;
                }
            }
        }

        return response()->json($counts);
    }

    private function authorizeRead(Request $request, CrmMailbox $mailbox): void
    {
        abort_unless(CrmMailbox::visibleTo($request->user())->whereKey($mailbox->id)->exists(), 403, 'This mailbox belongs to someone else.');
        abort_unless($mailbox->isConnected(), 409, 'Connect this mailbox first.');
    }

    private function authorizeManage(Request $request, CrmMailbox $mailbox): void
    {
        abort_unless($mailbox->manageableBy($request->user()), 403, 'Only the mailbox owner or a sysadmin can do that.');
    }

    /** Email HTML made safe (no scripts, forms or tracking tricks); shown inside a sandboxed frame. */
    private function safeHtml(array $message): string
    {
        if (trim($message['html']) === '') {
            return '<div style="white-space:pre-wrap;font:14px/1.6 system-ui,sans-serif;color:#1d2939">'.e($message['text']).'</div>';
        }
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', storage_path('framework/cache'));
        $config->set('HTML.ForbiddenElements', ['form', 'input', 'button', 'iframe', 'object', 'embed']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true, 'data' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('CSS.AllowTricky', false);

        return '<base target="_blank"><style>body{font:14px/1.6 system-ui,sans-serif;color:#1d2939;margin:0;word-wrap:break-word}img{max-width:100%;height:auto}</style>'
            .(new \HTMLPurifier($config))->purify($message['html']);
    }

    /**
     * CRM companies this person can see, matched by an email address: the company's own email, a contact's email,
     * or (for work addresses, not Gmail and the like) the company's domain.
     *
     * @return array<string, Company> email => company
     */
    private function companiesFor(array $emails, ?User $user): array
    {
        $emails = array_values(array_filter(array_map('strtolower', $emails), fn ($e) => str_contains($e, '@') && ! str_ends_with($e, '@sharet.africa')));
        if (! $emails) {
            return [];
        }
        $domains = collect($emails)->map(fn ($e) => Str::after($e, '@'))->reject(fn ($d) => in_array($d, self::FREE_MAIL, true))->unique()->values()->all();
        $companies = CrmAccess::companies(Company::withoutGlobalScope('customers'), $user)
            ->where(fn ($q) => $q->whereIn(\DB::raw('LOWER(companies.email)'), $emails)
                ->when($domains, fn ($w) => $w->orWhereIn('companies.domain', $domains))
                ->orWhereIn('companies.id', User::whereIn(\DB::raw('LOWER(email)'), $emails)->whereNotNull('company_id')->select('company_id')))
            ->get(['companies.id', 'companies.name', 'companies.email', 'companies.domain', 'companies.crm_stage', 'companies.lifecycle']);
        $contacts = User::whereIn(\DB::raw('LOWER(email)'), $emails)->whereNotNull('company_id')->pluck('company_id', \DB::raw('LOWER(email)'));

        $out = [];
        foreach ($emails as $email) {
            $match = $companies->first(fn ($c) => strtolower((string) $c->email) === $email)
                ?? (isset($contacts[$email]) ? $companies->firstWhere('id', $contacts[$email]) : null)
                ?? $companies->first(fn ($c) => $c->domain && strtolower($c->domain) === Str::after($email, '@') && ! in_array(Str::after($email, '@'), self::FREE_MAIL, true));
            if ($match) {
                $out[$email] = $match;
            }
        }

        return $out;
    }
}
