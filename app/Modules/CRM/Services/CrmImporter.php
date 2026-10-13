<?php

namespace App\Modules\CRM\Services;

use App\Models\User;
use App\Modules\CRM\Models\Country;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports prospect sheets (CSV) into crm_companies. Columns are matched by header name, so both
 * sales sheets work, and re-running an import merges into existing companies instead of duplicating.
 */
class CrmImporter
{
    /** CSV header (lowercased) => crm_companies field. */
    private const COLUMNS = [
        'company' => 'name',
        'priority' => 'tier',
        'business type' => 'business_type',
        'known project / portfolio' => 'portfolio',
        'key project / portfolio' => 'portfolio',
        'scale / fit signal' => 'scale_signal',
        'scale signal' => 'scale_signal',
        'scharge opportunity' => 'opportunity',
        'scharge sales angle' => 'opportunity',
        'first target role' => 'target_role',
        'public phone' => 'phone',
        'public email' => 'email',
        'decision maker' => 'decision_maker',
        'linkedin' => 'linkedin',
        'current property system' => 'current_system',
        'estimated units / gla' => 'estimated_units',
        'website' => 'website',
        'route' => 'route',
        'lead status' => 'stage',
        'next action' => 'next_action',
        'last contact' => 'last_contacted_at',
        'owner' => 'owner',
        'notes' => 'notes',
        'source' => 'source',
    ];

    /** Sheet "Lead Status" values => CRM stage. */
    private const STAGES = [
        'not contacted' => 'new', 'new' => 'new', 'contacted' => 'contacted', 'in contact' => 'contacted',
        'qualified' => 'qualified', 'demo' => 'demo_booked', 'demo booked' => 'demo_booked',
        'proposal' => 'proposal', 'proposal sent' => 'proposal', 'negotiation' => 'negotiation',
        'won' => 'won', 'closed won' => 'won', 'lost' => 'lost', 'closed lost' => 'lost', 'nurture' => 'nurture',
    ];

    public function __construct(private ContactService $contacts)
    {
    }

    /** @return array{rows: int, created: int, merged: int, skipped: int, flagged: int} */
    public function import(string $path, ?int $defaultOwnerId = null): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}");
        }
        $label = 'Import: '.basename($path);
        $kenya = Country::where('iso2', 'KE')->first();
        $stats = ['rows' => 0, 'created' => 0, 'merged' => 0, 'skipped' => 0, 'flagged' => 0];

        $handle = fopen($path, 'r');
        $headers = array_map(fn ($h) => Str::lower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle, null, ',', '"', '') ?: []);

        while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($cells === [null] || ! array_filter($cells, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }
            $stats['rows']++;
            $row = [];
            foreach ($headers as $i => $header) {
                if (isset(self::COLUMNS[$header]) && ($cells[$i] ?? '') !== '') {
                    $row[self::COLUMNS[$header]] ??= trim($cells[$i]);
                }
            }
            if (Normalizer::blank($row['name'] ?? null)) {
                $stats['skipped']++;
                continue;
            }

            $data = $this->mapRow($row, $label, $defaultOwnerId);
            $data['country_id'] = $kenya?->id;
            $stats['flagged'] += (int) $data['needs_verification'];

            $existing = $this->contacts->findCompany(
                Normalizer::domain($data['website'] ?? null, $data['email'] ?? null),
                Normalizer::companyKey(Normalizer::companyName($data['name']))
            );
            $firstTimeFromFile = ! $existing || ! str_contains((string) $existing->source, $label);

            [$company, $created] = $this->contacts->upsertCompany($data, $label);
            $stats[$created ? 'created' : 'merged']++;

            if ($firstTimeFromFile) {
                $this->contacts->log($company, 'import', ($created ? 'Imported from ' : 'Details merged from ').basename($path), null, ['row' => $stats['rows'] + 1]);
            }
        }
        fclose($handle);

        return $stats;
    }

    /** @param  array<string, string>  $row */
    private function mapRow(array $row, string $label, ?int $defaultOwnerId): array
    {
        $verify = fn (?string $v) => $v !== null && (Str::lower(trim($v)) === 'verify' || str_contains($v, 'email protected'));
        $phones = Normalizer::phones($row['phone'] ?? null);

        $data = [
            'name' => $row['name'],
            'tier' => preg_match('/(\d)/', $row['tier'] ?? '', $m) ? (int) $m[1] : 3,
            'business_type' => $row['business_type'] ?? null,
            'portfolio' => $row['portfolio'] ?? null,
            'scale_signal' => $row['scale_signal'] ?? null,
            'opportunity' => $row['opportunity'] ?? null,
            'target_role' => $row['target_role'] ?? null,
            'route' => $row['route'] ?? null,
            'phone' => $phones[0] ?? null,
            'phone_alt' => $phones[1] ?? null,
            'email' => Normalizer::email($row['email'] ?? null),
            'website' => Normalizer::website($row['website'] ?? null),
            'decision_maker' => Normalizer::personName($row['decision_maker'] ?? null),
            'linkedin' => Normalizer::website($row['linkedin'] ?? null),
            'current_system' => $row['current_system'] ?? null,
            'estimated_units' => isset($row['estimated_units']) && preg_match('/[\d,]+/', $row['estimated_units'], $m) ? (int) str_replace(',', '', $m[0]) : null,
            'stage' => self::STAGES[Str::lower($row['stage'] ?? '')] ?? 'new',
            'next_action' => $row['next_action'] ?? null,
            'last_contacted_at' => $this->date($row['last_contacted_at'] ?? null),
            'owner_id' => $this->owner($row['owner'] ?? null) ?? $defaultOwnerId,
            'notes' => $row['notes'] ?? null,
            'needs_verification' => $verify($row['phone'] ?? null) || $verify($row['email'] ?? null) || $verify($row['website'] ?? null),
        ];
        if (! empty($row['source'])) {
            $data['notes'] = trim(($data['notes'] ? $data['notes']."\n" : '').'Source: '.$row['source']);
        }

        return $data;
    }

    private function date(?string $value): ?Carbon
    {
        if (Normalizer::blank($value)) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** "Owner" column: a staff member's email or full name. */
    private function owner(?string $value): ?int
    {
        if (Normalizer::blank($value)) {
            return null;
        }
        $value = trim($value);

        return User::whereRaw('LOWER(email) = ?', [Str::lower($value)])->orWhere('name', $value)->value('id');
    }
}
