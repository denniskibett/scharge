<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Support\EmailFinder;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Up to 3 published work email addresses per prospect, so campaigns reach more of them.
 *
 *   crm:find-emails                          read the websites of prospects with fewer than 3 addresses (strongholds first)
 *   crm:find-emails --from=found.jsonl       first import researched websites/addresses ({"id", "website", "emails": [{email, source}]})
 *   crm:find-emails --dry-run --limit=20     show what would be saved
 *
 * Every address is checked (syntax, MX records, not disposable or a placeholder); no probe email is sent and nothing is guessed.
 * The best goes in companies.email, the 2nd and 3rd in crm_profile.emails (campaigns CC them). Opted-out and bounced companies are skipped.
 */
class FindEmails extends Command
{
    /** Nairobi strongholds, in priority order. */
    public const STRONGHOLDS = ['Kileleshwa', 'Kilimani', 'Lavington', "Lang'ata", 'Langata', 'Upper Hill', 'Upperhill', 'Westlands', 'Ruaka', 'Ruiru'];

    /** Segments in priority order: residential managers/agents/developers, then short-stay, commercial, utilities. */
    public const SEGMENTS = ['Property Manager / Agency', 'Developer', 'Residential Property (named building/estate)', 'Affordable Housing',
        'Short-stay / Airbnb Manager', 'Short-stay / Airbnb', 'Hotel / Serviced Accommodation', 'Commercial Property / Mall', 'Industrial / Warehouse Park',
        'Industrial / Factory (utility & metering)', 'Water Company', 'Private Water Supplier', 'Electricity / KPLC & Metering'];

    protected $signature = 'crm:find-emails
        {--from= : JSON Lines of researched websites/addresses to import first}
        {--limit=200 : Most websites to read in this run}
        {--ids= : Only these company ids (comma separated)}
        {--recheck : Also read sites checked before}
        {--phones : Also read sites of prospects that have no phone yet (phones are always collected from every site read)}
        {--dry-run : Show, do not save}';

    protected $description = 'Find and verify up to 3 published work emails per prospect (own website, researched listings)';

    public function handle(EmailFinder $finder): int
    {
        $dry = (bool) $this->option('dry-run');
        $stats = ['companies' => 0, 'added' => 0, 'rejected' => 0, 'websites' => 0];

        if ($path = $this->option('from')) {
            if (! is_readable($path)) {
                $this->error('Cannot read '.$path);

                return self::FAILURE;
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $row = json_decode($line, true);
                $company = is_array($row) ? Company::withoutGlobalScope('customers')->where('lifecycle', 'prospect')->find($row['id'] ?? 0) : null;
                if (! $company || $this->skip($company)) {
                    continue;
                }
                $website = Normalizer::website($row['website'] ?? null);
                if ($website && ! $company->profile('website')) {
                    $stats['websites']++;
                    if (! $dry) {
                        $company->forceFill(['crm_profile' => array_merge($company->crm_profile ?? [], ['website' => $website, 'website_source' => 'research '.now()->toDateString()])])->saveQuietly();
                        $company->domain ??= Normalizer::domain($website);
                        $company->domain && $company->saveQuietly();
                    }
                }
                $site = Normalizer::domain($website ?: $company->profile('website'));
                $ok = [];
                foreach ($row['emails'] ?? [] as $e) {
                    $email = Normalizer::email($e['email'] ?? null);
                    $problem = $email ? $finder->problem($email) : 'invalid syntax';
                    // Another firm's address (web designer, portal, partner) is not this business's.
                    $foreign = $email && ! EmailFinder::isFreeMail($email) && $site && ! $this->sameDomain($email, $site);
                    if (! $problem && $email && ! EmailFinder::isBusinessInbox($email, $site ?: Str::after($email, '@'))) {
                        $problem = 'looks like a person\'s mailbox';
                    }
                    if ($problem || $foreign) {
                        $stats['rejected']++;
                        $this->line('  skip '.($e['email'] ?? '?').' (#'.$company->id.'): '.($problem ?: 'not the business domain'));

                        continue;
                    }
                    $ok[] = ['email' => $email, 'source' => (string) ($e['source'] ?? 'research'), 'rank' => EmailFinder::rank($email, $site)];
                }
                usort($ok, fn ($a, $b) => $a['rank'] <=> $b['rank']);
                $ownSite = $site && collect($ok)->contains(fn ($e) => $this->onOwnSite($e['source'], $site));
                $this->save($company, $ok, $ownSite, $dry, $stats);
            }
        }

        $limit = (int) $this->option('limit');
        $read = 0;
        foreach ($this->candidates() as $company) {
            if ($read >= $limit) {
                break;
            }
            $read++;
            $website = $company->profile('website');
            $found = $finder->fromWebsite($website);
            if (! $dry) {
                $company->forceFill(['crm_profile' => array_merge($company->fresh()->crm_profile ?? [], ['emails_checked_at' => now()->toDateString(), 'phones_checked_at' => now()->toDateString()])])->saveQuietly();
            }
            $this->save($company->fresh(), $found, true, $dry, $stats);
            $this->savePhones($company->fresh(), $finder->phones, $website, $dry, $stats);
        }

        $this->info(sprintf('%s%d website(s) read, %d website(s) added from research, %d companies got %d new address(es), %d address(es) rejected, %d phone number(s) added.',
            $dry ? '[dry run] ' : '', $read, $stats['websites'], $stats['companies'], $stats['added'], $stats['rejected'], $stats['phones'] ?? 0));

        return self::SUCCESS;
    }

    /** Fill a missing main phone, then the alternate number; never overwrite one we already have. */
    private function savePhones(Company $company, array $phones, ?string $website, bool $dry, array &$stats): void
    {
        $have = array_filter([Normalizer::dialable($company->phone), Normalizer::dialable($company->profile('phone_alt'))]);
        $new = array_values(array_diff($phones, $have));
        if (! $new) {
            return;
        }
        $this->line('#'.$company->id.' '.$company->name.': phone '.implode(', ', $new));
        if ($dry) {
            $stats['phones'] = ($stats['phones'] ?? 0) + count($new);

            return;
        }
        $profile = $company->crm_profile ?? [];
        $added = 0;
        if (! Normalizer::dialable($company->phone)) {
            $company->phone = array_shift($new);
            $added++;
        }
        if ($new && ! $company->profile('phone_alt')) {
            $profile['phone_alt'] = array_shift($new);
            $added++;
        }
        if ($added) {
            $profile['phone_source'] = $website;
            $company->forceFill(['crm_profile' => $profile])->saveQuietly();
            $stats['phones'] = ($stats['phones'] ?? 0) + $added;
        }
    }

    private function save(Company $company, array $emails, bool $ownSite, bool $dry, array &$stats): void
    {
        if (! $emails) {
            return;
        }
        $list = implode(', ', array_column($emails, 'email'));
        if ($dry) {
            $this->line('#'.$company->id.' '.$company->name.': '.$list);
            $stats['companies']++;
            $stats['added'] += count($emails);

            return;
        }
        $added = EmailFinder::store($company, array_map(fn ($e) => ['email' => $e['email'], 'source' => $e['source']], $emails), $ownSite);
        if ($added) {
            $stats['companies']++;
            $stats['added'] += $added;
            $this->line('#'.$company->id.' '.$company->name.': +'.$added.' ('.$list.')');
        }
    }

    /** Prospects with a website and fewer than 3 addresses, strongholds first, then by segment. */
    private function candidates(): \Generator
    {
        $q = Company::withoutGlobalScope('customers')->where('lifecycle', 'prospect')->whereNotNull('crm_profile->website');
        if ($ids = $this->option('ids')) {
            $q->whereIn('id', array_map('intval', explode(',', $ids)));
        } elseif ($this->option('phones')) {
            // not read yet for emails, or read before phones were collected and still without a phone
            $q->where(fn ($w) => $w->whereNull('crm_profile->emails_checked_at')
                ->orWhere(fn ($x) => $x->whereNull('crm_profile->phones_checked_at')->where(fn ($p) => $p->whereNull('phone')->orWhere('phone', ''))));
        } elseif (! $this->option('recheck')) {
            $q->whereNull('crm_profile->emails_checked_at');
        }
        $needsPhone = (bool) $this->option('phones');
        $rows = $q->get()->reject(fn ($c) => $this->skip($c) || ($this->count($c) >= EmailFinder::MAX && ! ($needsPhone && ! $c->phone)))
            ->sortBy(fn ($c) => [$this->areaRank($c), $this->segmentRank($c), $c->id]);
        foreach ($rows as $c) {
            yield $c;
        }
    }

    private function skip(Company $c): bool
    {
        return (bool) $c->profile('do_not_email') || (bool) $c->profile('email_bounced');
    }

    private function count(Company $c): int
    {
        return (filter_var($c->email, FILTER_VALIDATE_EMAIL) ? 1 : 0) + count($c->profile('emails', []));
    }

    public static function areaRank(Company $c): int
    {
        $hay = $c->profile('area').' '.$c->address.' '.$c->profile('city');
        foreach (self::STRONGHOLDS as $i => $a) {
            if (stripos($hay, $a) !== false) {
                return 0;
            }
        }

        return stripos($hay, 'Nairobi') !== false ? 1 : 2;
    }

    public static function segmentRank(Company $c): int
    {
        $i = array_search($c->profile('segment'), self::SEGMENTS, true);

        return $i === false ? (in_array($c->kind, ['manager', 'developer'], true) ? 0 : 50) : $i;
    }

    private function sameDomain(string $email, string $site): bool
    {
        return EmailFinder::sameBusiness(Str::after($email, '@'), $site);
    }

    /** True when the source page is on the business's own site ($site null: the source is on some site we know nothing about). */
    private function onOwnSite(string $source, ?string $site): bool
    {
        if (! $site) {
            return false;
        }
        $host = preg_replace('/^www\./', '', Str::lower((string) parse_url($source, PHP_URL_HOST)));

        return $host !== '' && ($host === $site || str_ends_with($host, '.'.$site));
    }
}
