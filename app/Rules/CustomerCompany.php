<?php

namespace App\Rules;

use App\Models\Company;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The company exists and is a customer (not a CRM prospect). Use instead of
 * "exists:companies,id", which reads the table directly and would accept prospects.
 */
class CustomerCompany implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Company::whereKey($value)->exists()) {
            $fail('The selected company is invalid.');
        }
    }
}
