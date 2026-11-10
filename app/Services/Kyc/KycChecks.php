<?php

namespace App\Services\Kyc;

use App\Models\CompanyKyc;
use App\Models\KycDocument;
use Illuminate\Support\Collection;

/**
 * Automatic checks on a company's KYC, so scharge's reviewer only looks at what needs a human
 * (docs/ONBOARDING.md): formats (KRA PIN, ID number), the same person on the ID, the KRA certificate and the title
 * deeds, the business name on its certificate, expired passports, unreadable or mislabelled documents.
 * Returns [{level: ok|warn|fail, message}].
 */
class KycChecks
{
    /** @return array<int, array{level: string, message: string}> */
    public function run(CompanyKyc $kyc, Collection $docs): array
    {
        $out = [];
        $add = function (string $level, string $message) use (&$out) {
            $out[] = ['level' => $level, 'message' => $message];
        };
        $byKind = $docs->groupBy('kind');
        $read = fn (string $kind) => $byKind->get($kind, collect())->first(fn ($d) => $d->read_status === 'read')?->extracted ?? [];

        if (! $byKind->has('national_id') && ! $byKind->has('passport')) {
            $add('fail', 'No ID or passport for the owner or a director.');
        }
        if (! $byKind->has('kra_pin')) {
            $add('fail', 'No KRA PIN certificate.');
        }

        $pin = strtoupper(trim((string) $kyc->kra_pin));
        if ($pin !== '' && ! preg_match('/^[AP]\d{9}[A-Z]$/', $pin)) {
            $add('fail', "KRA PIN {$pin} does not look like a KRA PIN (e.g. A012345678Z).");
        } elseif ($pin !== '' && ($docPin = strtoupper(preg_replace('/\s/', '', (string) ($read('kra_pin')['kra_pin'] ?? '')))) !== '' && $docPin !== $pin) {
            $add('fail', "KRA PIN typed ({$pin}) differs from the certificate ({$docPin}).");
        }
        $idNo = preg_replace('/\D/', '', (string) $kyc->owner_id_number);
        if ($idNo !== '' && (strlen($idNo) < 6 || strlen($idNo) > 9)) {
            $add('warn', 'The ID number is an unusual length for a Kenyan ID.');
        } elseif ($idNo !== '' && ($docId = preg_replace('/\D/', '', (string) ($read('national_id')['id_number'] ?? ''))) !== '' && $docId !== $idNo) {
            $add('fail', 'ID number typed differs from the ID card.');
        }

        // The same person across documents.
        $owner = (string) $kyc->owner_name;
        foreach (['national_id' => 'full_name', 'passport' => 'full_name', 'kra_pin' => 'taxpayer_name'] as $kind => $field) {
            $name = (string) ($read($kind)[$field] ?? '');
            if ($owner !== '' && $name !== '' && ! self::sameName($owner, $name) && ! ($kind === 'kra_pin' && self::sameName((string) $kyc->business_name, $name))) {
                $add('warn', 'Name on the '.strtolower(KycDocument::KINDS[$kind]).' ("'.$name.'") does not match "'.$owner.'".');
            }
        }
        $biz = (string) ($read('business_registration')['business_name'] ?? $read('cr12')['company_name'] ?? '');
        if ($biz !== '' && $kyc->business_name && ! self::sameName($kyc->business_name, $biz)) {
            $add('warn', 'Business name on the certificate ("'.$biz.'") differs from "'.$kyc->business_name.'".');
        }
        foreach ($byKind->get('title_deed', collect()) as $deed) {
            $holder = (string) ($deed->extracted['owner_name'] ?? '');
            if ($deed->read_status === 'read' && $holder !== '' && ! self::sameName($holder, $owner) && ! self::sameName($holder, (string) $kyc->business_name)) {
                $add('warn', 'Title deed'.($deed->property ? ' for '.$deed->property->name : '').' is in the name of "'.$holder.'": ask for an authority to manage it.');
            }
        }
        $expiry = $read('passport')['date_of_expiry'] ?? null;
        if ($expiry && strtotime($expiry) && strtotime($expiry) < time()) {
            $add('fail', 'The passport has expired.');
        }
        foreach ($docs as $d) {
            if ($d->read_status === 'read' && ($d->extracted['legible'] ?? true) === false) {
                $add('warn', (KycDocument::KINDS[$d->kind] ?? $d->kind).' is hard to read: ask for a clearer photo.');
            }
            if ($d->read_status === 'read' && filled($d->extracted['notes'] ?? null)) {
                $add('warn', (KycDocument::KINDS[$d->kind] ?? $d->kind).': '.mb_substr($d->extracted['notes'], 0, 200));
            }
        }
        if (! $out) {
            $add('ok', 'All automatic checks passed.');
        }

        return $out;
    }

    /** Names match when the shorter one's words all appear in the longer (order and case ignored): "Jane W. Otieno" ~ "OTIENO JANE WANJIKU". */
    public static function sameName(string $a, string $b): bool
    {
        $words = fn ($s) => collect(preg_split('/[^a-z0-9]+/', strtolower($s)))->filter(fn ($w) => strlen($w) > 1)
            ->reject(fn ($w) => in_array($w, ['ltd', 'limited', 'co', 'company', 'the', 'and', 'plc'], true))->unique()->values();
        [$x, $y] = [$words($a), $words($b)];
        if ($x->isEmpty() || $y->isEmpty()) {
            return false;
        }
        [$short, $long] = $x->count() <= $y->count() ? [$x, $y] : [$y, $x];

        return $short->diff($long)->isEmpty();
    }
}
