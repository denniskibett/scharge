<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An address a real person can receive mail at: not a test or throwaway domain, and the domain
 * accepts mail (MX or A record). Use with 'email': ['required', 'email', new RealEmail].
 */
class RealEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim((string) $value));
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '') {
            return; // the 'email' rule reports the format
        }
        $tld = substr(strrchr($domain, '.') ?: '.'.$domain, 1);

        if (in_array($domain, config('kyc.blocked_email_domains', []), true) || in_array($tld, config('kyc.blocked_email_tlds', []), true)
            || preg_match('/^(test|demo|fake|noemail|no-email|none|dummy)\d*@/', $email)) {
            $fail('Use a real email address: we send a code to it to confirm it is yours.');

            return;
        }

        if (! app()->runningUnitTests() && function_exists('checkdnsrr') && ! checkdnsrr($domain, 'MX') && ! checkdnsrr($domain, 'A')) {
            $fail("{$domain} cannot receive email. Check the spelling of the address.");
        }
    }
}
