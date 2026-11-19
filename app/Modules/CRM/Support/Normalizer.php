<?php

namespace App\Modules\CRM\Support;

use Illuminate\Support\Str;

/**
 * Cleans contact data from the website forms and CRM imports so duplicates can be found:
 * people's names and company names in name case, lowercase emails, phones in E.164 (+2547...).
 */
class Normalizer
{
    /** Values the sales sheets use for "unknown"; treated as empty. */
    private const PLACEHOLDERS = ['verify', 'n/a', 'na', 'none', 'null', '-', 'tbc', 'tbd', '[email protected]', '[email protected]'];

    /** Words kept lowercase inside a name ("Bin", "van" etc. stay as the person wrote them). */
    private const SMALL_WORDS = ['and', 'of', 'the', 'by', 'for', 'at', 'in', 'on', 'de', 'la', 'du', 'van', 'von', 'bin', 'binti', 'wa'];

    /** Legal and filler suffixes ignored when matching company names. */
    private const COMPANY_NOISE = ['limited', 'ltd', 'plc', 'llc', 'inc', 'co', 'company', 'kenya', 'kenyaltd', 'the'];

    /** Free email providers: their domain says nothing about the company. */
    private const FREE_MAIL = ['gmail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'aol.com', 'proton.me', 'protonmail.com', 'ymail.com', 'msn.com'];

    public static function blank(?string $value): bool
    {
        $value = trim((string) $value);

        return $value === '' || in_array(Str::lower($value), self::PLACEHOLDERS, true);
    }

    /**
     * Safe plain text for anything we save from outside (forms, emails, websites, imports): no HTML tags,
     * no control or zero-width characters, entities decoded, whitespace collapsed, valid UTF-8, length capped.
     * $multiline keeps line breaks (message bodies, notes); otherwise everything is one line.
     */
    public static function text(?string $value, int $max = 255, bool $multiline = false): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = mb_scrub((string) $value, 'UTF-8');
        $value = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $value); // zero-width
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', ' ', $value);
        if ($multiline) {
            $value = preg_replace("/[ \t\x{00A0}]+/u", ' ', $value);
            $value = preg_replace("/ *\n */", "\n", $value);
            $value = preg_replace("/\n{3,}/", "\n\n", $value);
        } else {
            $value = preg_replace('/\s+/u', ' ', $value);
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * A phone we can dial: Kenyan numbers must be +254 plus 9 digits (mobile 7xx/1xx or a landline area code);
     * other countries 8-15 digits. Returns the E.164 form or null.
     */
    public static function dialable(?string $value, string $dialCode = '254'): ?string
    {
        $phone = self::phone($value, $dialCode);
        if (! $phone || preg_match('/(\d)\1{6,}/', substr($phone, 4))) {
            return null; // placeholders like 0700000000
        }
        if (str_starts_with($phone, '+254')) {
            return preg_match('/^\+254[1-9]\d{8}$/', $phone) ? $phone : null;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) ? $phone : null;
    }

    /** A name on a person record that may really be a business ("ABC Properties Ltd"): company case for those, person case otherwise. */
    public static function anyName(?string $value): ?string
    {
        $value = self::text($value, 255);
        if ($value === null) {
            return null;
        }

        return preg_match('/\b(ltd|limited|plc|llc|inc|co|company|properties|property|apartments?|agenc(y|ies)|investments?|enterprises?|group|estates?|homes|realty|realtors|management|holdings|developers|associates|services|hotel|villas?|court|towers?|plaza|sacco|church|school|bank|trust)\b\.?/i', $value)
            ? self::companyName($value)
            : self::personNameKeepingAcronyms($value);
    }

    /** Person case, but short words typed in capitals (BNB, PC, KCB) stay as typed unless the whole name is in capitals. */
    private static function personNameKeepingAcronyms(string $value): ?string
    {
        $cased = self::personName($value);
        $letters = preg_replace('/[^\p{L}]/u', '', $value);
        if ($cased === null || $letters === '') {
            return $cased;
        }
        // Whole name in capitals ("MERCY MUMBI KCB"): only vowel-less short words are acronyms; otherwise any 2-4 letter capitals word is.
        $shouting = mb_strtoupper($letters) === $letters;
        $orig = explode(' ', preg_replace('/\s+/u', ' ', trim($value)));
        $out = explode(' ', $cased);
        foreach ($orig as $i => $w) {
            $l = preg_replace('/[^\p{L}]/u', '', $w);
            $isCaps = mb_strlen($l) >= 2 && mb_strtoupper($l) === $l;
            $acronym = $shouting ? $isCaps && mb_strlen($l) <= 4 && ! preg_match('/[AEIOUY]/u', $l) : $isCaps && mb_strlen($l) <= 4;
            if (isset($out[$i]) && $acronym) {
                $out[$i] = $w;
            }
        }

        return implode(' ', $out);
    }

    /** "  jOHN   o'brien-mwangi " -> "John O'Brien-Mwangi". */
    public static function personName(?string $value): ?string
    {
        if (self::blank($value)) {
            return null;
        }
        $value = preg_replace('/\s+/u', ' ', trim($value));
        $words = explode(' ', $value);

        foreach ($words as $i => $word) {
            $lower = mb_strtolower($word);
            if ($i > 0 && in_array($lower, self::SMALL_WORDS, true)) {
                $words[$i] = $lower;
                continue;
            }
            $words[$i] = self::capitaliseWord($lower);
        }

        return implode(' ', $words);
    }

    /**
     * Company names keep deliberate capitals: acronyms (GY, HF, KLML, REIT) and brand casing
     * (HassConsult, EasternVille). Words typed all lower or ALL UPPER (6+ letters) get name case.
     */
    public static function companyName(?string $value): ?string
    {
        if (self::blank($value)) {
            return null;
        }
        $value = preg_replace('/\s+/u', ' ', trim($value));
        $words = explode(' ', $value);
        // Whole name typed in capitals ("KINGS DEVELOPERS LTD"): only short or vowel-less words are acronyms.
        $allLetters = preg_replace('/[^\p{L}]/u', '', $value);
        $shouting = $allLetters !== '' && mb_strtoupper($allLetters) === $allLetters;

        foreach ($words as $i => $word) {
            $letters = preg_replace('/[^\p{L}]/u', '', $word);
            $isUpper = $letters !== '' && mb_strtoupper($letters) === $letters;
            $isMixed = $letters !== '' && ! $isUpper && preg_match('/\p{Lu}/u', mb_substr($letters, 1));
            $isAcronym = $isUpper && ($shouting
                ? mb_strlen($letters) <= 3 || ! preg_match('/[AEIOUY]/u', $letters)
                : mb_strlen($letters) <= 5);
            $lower = mb_strtolower($word);

            if ($i > 0 && in_array($lower, self::SMALL_WORDS, true)) {
                $words[$i] = $lower;
            } elseif (in_array($lower, ['ltd', 'ltd.'], true)) {
                $words[$i] = 'Ltd';
            } elseif ($isAcronym || $isMixed) {
                $words[$i] = $word;
            } else {
                $words[$i] = self::capitaliseWord($lower);
            }
        }

        return implode(' ', $words);
    }

    /** Matching key: "Kings Developers Limited" and "KINGS DEVELOPERS" both give "kingsdevelopers". */
    public static function companyKey(?string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii((string) $name)), -1, PREG_SPLIT_NO_EMPTY);
        $kept = array_values(array_diff($words, self::COMPANY_NOISE));
        // Ignore plurals: "Primofort Investments" = "Primofort Investment", "Properties" = "Property".
        $singular = fn ($w) => strlen($w) > 4 ? preg_replace(['/ies$/', '/(?<!s)s$/'], ['y', ''], $w) : $w;
        [$words, $kept] = [array_map($singular, $words), array_map($singular, $kept)];

        return implode('', $kept ?: $words);
    }

    public static function email(?string $value): ?string
    {
        if (self::blank($value)) {
            return null;
        }
        $value = Str::lower(trim($value, " \t\n\r\0\x0B<>;,"));
        if (str_starts_with($value, 'mailto:')) {
            $value = substr($value, 7);
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    /**
     * Phone to E.164 using the country's dialling code for local formats.
     * "0712 345 678", "712345678", "254712345678", "+254 712-345-678" -> "+254712345678".
     */
    public static function phone(?string $value, string $dialCode = '254'): ?string
    {
        if (self::blank($value)) {
            return null;
        }
        $raw = trim($value);
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            // already international
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, $dialCode) && strlen($digits) > 10) {
            // "254712345678"
        } elseif (str_starts_with($digits, '0')) {
            $digits = $dialCode.substr($digits, 1);
        } else {
            $digits = $dialCode.$digits;
        }

        // E.164 numbers are 8-15 digits long including the country code.
        return (strlen($digits) >= 8 && strlen($digits) <= 15) ? '+'.$digits : null;
    }

    /** A cell can hold several numbers: "+254 203 513 306 / +254 733 209 709". */
    public static function phones(?string $value, string $dialCode = '254'): array
    {
        if (self::blank($value)) {
            return [];
        }
        $parts = preg_split('#\s*(?:/|,|;|\bor\b)\s*#i', $value);

        return array_values(array_unique(array_filter(array_map(fn ($p) => self::phone($p, $dialCode), $parts))));
    }

    /**
     * True for numbers typed without a country code ("0772 123 456", "772123456"). Their country is
     * a guess, so matching should compare only the last 9 digits.
     */
    public static function isLocalPhone(?string $value): bool
    {
        $raw = trim((string) $value);
        $digits = preg_replace('/\D+/', '', $raw);

        return $digits !== '' && ! str_starts_with($raw, '+') && ! str_starts_with($digits, '00') && strlen($digits) <= 10;
    }

    /** Last 9 digits: enough to find the same number stored as 07..., 254..., or +254.... */
    public static function phoneTail(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 9 ? substr($digits, -9) : null;
    }

    public static function website(?string $value): ?string
    {
        if (self::blank($value)) {
            return null;
        }
        $value = trim($value);
        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? rtrim($value, '/').'/' : null;
    }

    /** "https://www.Kings-Developers.com/about" -> "kings-developers.com". */
    public static function domain(?string $website = null, ?string $email = null): ?string
    {
        if ($website = self::website($website)) {
            $host = Str::lower((string) parse_url($website, PHP_URL_HOST));

            return preg_replace('/^www\./', '', $host) ?: null;
        }
        if ($email = self::email($email)) {
            $host = Str::after($email, '@');

            return in_array($host, self::FREE_MAIL, true) ? null : $host;
        }

        return null;
    }

    private static function capitaliseWord(string $lower): string
    {
        // Capitalise after hyphens and apostrophes too: mwangi-otieno, o'brien, d'souza; but not a possessive or
        // contraction ending (andrew's, ma'am, n't), so only after a one-letter prefix like O' or D'.
        $word = preg_replace_callback("/(^|-)(\p{L})/u", fn ($m) => $m[1].mb_strtoupper($m[2]), $lower);
        $word = preg_replace_callback("/(^|-)(\p{L})(['’])(\p{L})(?=\p{L})/u", fn ($m) => $m[1].$m[2].$m[3].mb_strtoupper($m[4]), $word);

        // Mc prefix: mcdonald -> McDonald.
        return preg_replace_callback('/^(Mc)(\p{L})/u', fn ($m) => $m[1].mb_strtoupper($m[2]), $word);
    }
}
