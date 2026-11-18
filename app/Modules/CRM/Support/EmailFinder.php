<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Published work email addresses for a prospect, from its own website (home, contact, about pages and footer).
 *
 * Rules (no guessing, no probe emails, nothing behind a login):
 *   - only addresses printed on the page or in a mailto: link;
 *   - the domain must be the website's own domain, or a free-mail box the business publishes on its own site;
 *   - the syntax must be valid, the domain must have MX records, and it must not be a disposable or placeholder address.
 *
 * Up to 3 are kept per company: the best goes in companies.email, the others in crm_profile.emails
 * ([{email, source, found_at, verified}]), which campaigns copy (CC) on the same email.
 */
class EmailFinder
{
    public const MAX = 3;

    /** Business role addresses, best first; anything else on the business's own domain ranks after these. */
    private const ROLES = ['sales', 'lettings', 'letting', 'rentals', 'leasing', 'propertymanagement', 'management', 'manager', 'info', 'enquiries', 'enquiry',
        'inquiries', 'inquiry', 'office', 'admin', 'contact', 'contactus', 'hello', 'reservations', 'bookings', 'booking', 'customercare', 'customerservice', 'support'];

    /** Addresses a campaign must never get: system, job, legal and web-agency mailboxes. */
    private const SKIP_LOCAL = ['noreply', 'no-reply', 'donotreply', 'do-not-reply', 'mailer-daemon', 'postmaster', 'hostmaster', 'webmaster', 'abuse',
        'privacy', 'dpo', 'careers', 'career', 'jobs', 'recruitment', 'hr', 'cv', 'tenders', 'procurement', 'legal', 'compliance', 'unsubscribe', 'example', 'email', 'name', 'user', 'yourname', 'your'];

    private const PLACEHOLDER_DOMAINS = ['example.com', 'example.org', 'example.net', 'domain.com', 'yourdomain.com', 'email.com', 'mail.com', 'company.com',
        'yourcompany.com', 'website.com', 'test.com', 'sentry.io', 'sentry.wixpress.com', 'wixpress.com', 'sentry-next.wixpress.com', 'godaddy.com', 'wordpress.com',
        'w3.org', 'schema.org', 'gravatar.com', 'squarespace.com', 'mysite.com', 'site.com', 'emailaddress.com'];

    private const DISPOSABLE = ['mailinator.com', 'guerrillamail.com', 'guerrillamail.net', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'yopmail.com',
        'trashmail.com', 'getnada.com', 'sharklasers.com', 'dispostable.com', 'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mintemail.com', 'mohmal.com',
        'emailondeck.com', 'tempmailo.com', 'tempr.email', 'discard.email', 'mailnesia.com', 'mytemp.email', 'spamgourmet.com', 'moakt.com', 'burnermail.io'];

    private const FREE_MAIL = ['gmail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'aol.com', 'ymail.com', 'protonmail.com', 'proton.me', 'zoho.com', 'gmx.com'];

    /** Paths worth reading after the home page, when the site does not link them. */
    private const PAGES = ['contact', 'contact-us', 'contacts', 'about', 'about-us'];

    private array $mx = [];

    /** Syntax, not a placeholder or disposable, and the domain receives mail. Returns why not, or null when fine. */
    public function problem(string $email): ?string
    {
        $email = Str::lower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! preg_match('/^[a-z0-9._%+\'-]+@[a-z0-9.-]+\.[a-z]{2,}$/', $email)) {
            return 'invalid syntax';
        }
        [$local, $domain] = explode('@', $email, 2);
        if (in_array($domain, self::PLACEHOLDER_DOMAINS, true) || str_ends_with($domain, '.wixpress.com') || preg_match('/\.(png|jpe?g|gif|svg|webp|css|js)$/', $domain)) {
            return 'placeholder';
        }
        if (in_array($domain, self::DISPOSABLE, true)) {
            return 'disposable';
        }
        $bare = str_replace(['.', '_'], '', $local);
        if (in_array($local, self::SKIP_LOCAL, true) || in_array($bare, self::SKIP_LOCAL, true) || preg_match('/^[0-9a-f]{16,}$/', $local)) {
            return 'not a business inbox';
        }

        if (preg_match('/\d{7,}/', $local)) {
            return 'invalid syntax';
        }

        return $this->hasMx($domain) ? null : 'no MX records';
    }

    /** Role words a published business inbox uses; a mailbox without one is likely a person's. */
    private const ROLE_WORDS = ['sales', 'info', 'letting', 'rent', 'leas', 'propert', 'manage', 'estate', 'home', 'realt', 'book', 'reserv', 'stay', 'office',
        'admin', 'account', 'care', 'support', 'hello', 'enquir', 'inquir', 'contact', 'service', 'team', 'desk', 'front', 'guest', 'hotel', 'suite', 'apartment'];

    /**
     * A business inbox, not a private individual's: a role word in the mailbox, or (for gmail and the like) the business's own name.
     * Named staff addresses (j.mwangi@, rose@) are left out.
     */
    public static function isBusinessInbox(string $email, ?string $siteDomain = null): bool
    {
        $local = str_replace(['.', '-', '_'], '', Str::before(Str::lower($email), '@'));
        foreach (self::ROLE_WORDS as $w) {
            if (str_contains($local, $w)) {
                return true;
            }
        }
        $brand = $siteDomain ? preg_replace('/[^a-z0-9]/', '', Str::before($siteDomain, '.')) : '';

        return strlen($brand) >= 4 && (str_contains($local, $brand) || str_contains($brand, $local) && strlen($local) >= 4);
    }

    public function hasMx(string $domain): bool
    {
        return $this->mx[$domain] ??= (bool) @checkdnsrr($domain.'.', 'MX');
    }

    /**
     * The email's domain belongs to the same business as the website: same domain, a subdomain, or the same brand
     * on another suffix or host (methodistresort.com / methodistresort.co.ke, movenpick.accor.com / movenpick.com).
     */
    public static function sameBusiness(string $emailDomain, ?string $siteDomain): bool
    {
        $e = Str::lower($emailDomain);
        $s = preg_replace('/^www\./', '', Str::lower((string) $siteDomain));
        if ($s === '') {
            return false;
        }
        if ($e === $s || str_ends_with($e, '.'.$s) || str_ends_with($s, '.'.$e)) {
            return true;
        }
        $brand = fn ($d) => array_filter(explode('.', $d), fn ($l) => strlen($l) >= 5 && ! in_array($l, ['africa', 'kenya', 'hotel', 'hotels', 'online', 'group'], true));
        $eb = $brand($e);

        return (bool) array_intersect($eb ? [reset($eb)] : [], $brand($s));
    }

    public static function isFreeMail(string $email): bool
    {
        return in_array(Str::after(Str::lower($email), '@'), self::FREE_MAIL, true);
    }

    /** Lower is better: same-domain role addresses first, then other same-domain ones, then free-mail. */
    public static function rank(string $email, ?string $siteDomain = null): int
    {
        [$local, $domain] = explode('@', Str::lower($email), 2);
        $role = array_search(str_replace(['.', '-', '_'], '', $local), self::ROLES, true);
        $own = $siteDomain && self::sameBusiness($domain, $siteDomain);

        return ($own ? 0 : (self::isFreeMail($email) ? 200 : 100)) + ($role === false ? 50 : $role);
    }

    /**
     * Read the company's website and return verified addresses, best first.
     *
     * @return array<int, array{email: string, source: string}>
     */
    public function fromWebsite(string $website): array
    {
        $website = Normalizer::website($website);
        if (! $website) {
            return [];
        }
        $host = Str::lower((string) parse_url($website, PHP_URL_HOST));
        $siteDomain = preg_replace('/^www\./', '', $host);
        $root = rtrim($website, '/');
        $base = parse_url($website, PHP_URL_SCHEME).'://'.$host;

        $found = []; // email => source url
        $queue = [$root.'/'];
        $seen = [];
        while ($queue && count($seen) < 6) {
            $url = array_shift($queue);
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $html = $this->fetch($url);
            if ($html === null) {
                continue;
            }
            foreach (self::extract($html) as $email) {
                $found[$email] ??= $url;
            }
            if (count($seen) === 1) { // from the home page: follow its contact/about links on the same site, else try the usual paths
                $links = self::contactLinks($html, $base, $host);
                $queue = array_merge($queue, $links ?: array_map(fn ($p) => $base.'/'.$p.'/', self::PAGES));
            }
        }

        $out = [];
        foreach ($found as $email => $source) {
            $domain = Str::after($email, '@');
            $own = self::sameBusiness($domain, $siteDomain);
            if (! $own && ! self::isFreeMail($email)) {
                continue; // another firm's address (web designer, partner, portal): not this business
            }
            if ($this->problem($email) === null && self::isBusinessInbox($email, $siteDomain)) {
                $out[] = ['email' => $email, 'source' => $source, 'rank' => self::rank($email, $siteDomain)];
            }
        }
        usort($out, fn ($a, $b) => $a['rank'] <=> $b['rank']);

        return array_map(fn ($r) => ['email' => $r['email'], 'source' => $r['source']], array_slice($out, 0, self::MAX));
    }

    /** Emails printed on a page or in mailto: links (including &#64; / [at] obfuscation and Cloudflare-protected ones). */
    public static function extract(string $html): array
    {
        // Tags become spaces so text in neighbouring elements ("Kenya" + "+254…" + "info@…") is never glued into one address.
        $text = html_entity_decode(preg_replace('/<[^>]*>/', ' ', preg_replace('/href=["\']mailto:([^"\'?]+)/i', '> $1 <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s*(\[at\]|\(at\)|\{at\})\s*/i', '@', $text);
        $text = preg_replace('/\s*(\[dot\]|\(dot\)|\{dot\})\s*/i', '.', $text);
        $text = str_ireplace(['%40', '%20'], ['@', ' '], $text);
        preg_match_all('/data-cfemail="([0-9a-f]+)"/i', $html, $cf);
        foreach ($cf[1] as $hex) {
            $text .= ' '.self::cloudflare($hex);
        }
        preg_match_all('/(?<![A-Za-z0-9._%+\'-])[A-Za-z0-9][A-Za-z0-9._%+\'-]*@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/', $text, $m);

        return array_values(array_unique(array_map(fn ($e) => Str::lower(trim($e, ".'-")), $m[0])));
    }

    private static function cloudflare(string $hex): string
    {
        $key = hexdec(substr($hex, 0, 2));
        $out = '';
        for ($i = 2; $i < strlen($hex); $i += 2) {
            $out .= chr(hexdec(substr($hex, $i, 2)) ^ $key);
        }

        return $out;
    }

    /** Same-site links whose URL or text says contact / about / reach us. */
    private static function contactLinks(string $html, string $base, string $host): array
    {
        preg_match_all('/<a\s[^>]*href=["\']([^"\'#]+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER);
        $links = [];
        foreach ($m as [$all, $href, $label]) {
            if (! preg_match('/contact|about|reach|get-in-touch|enquir|office/i', $href.' '.strip_tags($label))) {
                continue;
            }
            if (str_starts_with($href, '//')) {
                $href = 'https:'.$href;
            } elseif (str_starts_with($href, '/')) {
                $href = $base.$href;
            } elseif (! preg_match('#^https?://#i', $href)) {
                continue;
            }
            $h = Str::lower((string) parse_url($href, PHP_URL_HOST));
            if (preg_replace('/^www\./', '', $h) === preg_replace('/^www\./', '', $host)) {
                $links[$href] = true;
            }
        }

        return array_slice(array_keys($links), 0, 4);
    }

    private function fetch(string $url): ?string
    {
        try {
            $r = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; scharge-crm/1.0; +https://scharge.sharet.africa)', 'Accept' => 'text/html'])
                ->timeout(12)->connectTimeout(6)->withOptions(['allow_redirects' => ['max' => 4]])->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $r->successful() || ! str_contains(Str::lower((string) $r->header('Content-Type')), 'html')) {
            return null;
        }

        return mb_substr($r->body(), 0, 1_500_000);
    }

    /**
     * Save verified addresses on the company: the best in companies.email (unless it already has a working one),
     * the rest in crm_profile.emails. Returns how many new addresses were added.
     *
     * @param  array<int, array{email: string, source: string}>  $emails
     */
    public static function store(Company $company, array $emails, bool $ownSite): int
    {
        $profile = $company->crm_profile ?? [];
        $primary = Str::lower(trim((string) $company->email));
        $primaryOk = $primary !== '' && filter_var($primary, FILTER_VALIDATE_EMAIL) && empty($profile['email_bounced']);
        $extra = collect($profile['emails'] ?? [])->filter(fn ($e) => ! empty($e['email']))->keyBy(fn ($e) => Str::lower($e['email']));
        $bounced = collect($profile['bounced_emails'] ?? [])->map(fn ($e) => Str::lower($e))->all();

        $added = 0;
        foreach ($emails as $e) {
            $email = Str::lower($e['email']);
            if ($email === $primary || $extra->has($email) || in_array($email, $bounced, true)) {
                continue;
            }
            $row = ['email' => $email, 'source' => $e['source'], 'found_at' => now()->toDateString(), 'verified' => 'syntax+mx'];
            if (! $primaryOk) {
                if ($primary !== '' && ! in_array($primary, $bounced, true) && ! empty($profile['email_bounced'])) {
                    $bounced[] = $primary;
                }
                $company->email = $email;
                $primary = $email;
                $primaryOk = true;
                $profile['email_source'] = $e['source'];
                $profile['email_found_at'] = now()->toDateString();
                unset($profile['email_bounced']);
            } elseif ($extra->count() < self::MAX - 1) {
                $extra->put($email, $row);
            } else {
                continue;
            }
            $added++;
        }
        if (! $added) {
            return 0;
        }
        $profile['emails'] = $extra->values()->all();
        if ($bounced) {
            $profile['bounced_emails'] = array_values(array_unique($bounced));
        }
        if ($ownSite) {
            $profile['needs_verification'] = false;
        }
        $profile['emails_checked_at'] = now()->toDateString();
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();

        return $added;
    }

    /** Extra addresses a campaign may copy: verified, not the primary, not bounced. */
    public static function ccFor(Company $company): array
    {
        $primary = Str::lower(trim((string) $company->email));
        $bounced = array_map('strtolower', $company->profile('bounced_emails', []));

        return collect($company->profile('emails', []))->pluck('email')->filter()->map(fn ($e) => Str::lower($e))
            ->reject(fn ($e) => $e === $primary || in_array($e, $bounced, true) || ! filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()->take(self::MAX - 1)->values()->all();
    }
}
