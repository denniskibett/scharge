<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyIntegration;
use Illuminate\Database\Seeder;

/**
 * The KenyaSMS account in .env (KENYASMS_KEY / KENYASMS_SENDER_ID) belongs to Danaff Kenya. This saves it as
 * Danaff's own "SMS sender ID" integration (company_integrations, provider kenyasms_sender, credentials
 * encrypted with APP_KEY), so Danaff's bills and reminders go out from its own account and sender ID,
 * exactly as if it had been entered under Integrations. Safe to run again: it updates the same row.
 *
 *   php artisan db:seed --class=DanaffKenyaSmsSeeder --force
 */
class DanaffKenyaSmsSeeder extends Seeder
{
    public function run(): void
    {
        $key = (string) (config('sms.kenyasms.api_key') ?: config('sms.api_key'));
        $sender = (string) (config('sms.kenyasms.sender_id') ?: config('sms.sender_id'));
        if ($key === '' || $sender === '') {
            $this->command?->warn('KENYASMS_KEY or KENYASMS_SENDER_ID is empty in .env: nothing saved.');

            return;
        }

        $company = Company::where('name', 'like', 'Danaff Kenya%')->orderBy('id')->first();
        if (! $company) {
            $this->command?->warn('No company named "Danaff Kenya…" found: nothing saved.');

            return;
        }

        $row = CompanyIntegration::firstOrNew(['company_id' => $company->id, 'provider' => 'kenyasms_sender']);
        $row->credentials = array_merge($row->credentials ?? [], ['sender_id' => $sender, 'api_key' => $key]);
        $row->enabled = true;
        $row->save();

        $masked = substr($key, 0, 4).str_repeat('•', 6).substr($key, -2);
        $this->command?->info("Saved KenyaSMS for {$company->name} (#{$company->id}): sender {$sender}, key {$masked}, switched on.");
    }
}
