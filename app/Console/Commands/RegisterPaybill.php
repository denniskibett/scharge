<?php

namespace App\Console\Commands;

use App\Models\CompanyIntegration;
use App\Services\Rent\PaybillRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/** Register scharge's paybill-payment address with Safaricom for companies with their own Daraja paybill. */
class RegisterPaybill extends Command
{
    protected $signature = 'mpesa:register-paybill {--company= : One company id}';

    protected $description = 'Send payments made straight to company paybills to scharge (Safaricom C2B)';

    public function handle(): int
    {
        $this->line('Address: '.PaybillRegistration::urls()['confirmation']);
        $rows = CompanyIntegration::where('provider', 'mpesa_daraja')->where('enabled', true)->whereNotNull('company_id')
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))->get();

        foreach ($rows as $row) {
            $base = $row->get('environment') === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
            $auth = Http::withBasicAuth((string) $row->get('consumer_key'), (string) $row->get('consumer_secret'))->timeout(20)
                ->get($base.'/oauth/v1/generate?grant_type=client_credentials');
            [$ok, $message] = $auth->json('access_token') ? PaybillRegistration::register($row, (string) $auth->json('access_token'))
                : [false, 'Safaricom rejected the consumer key/secret.'];
            $this->line(sprintf('%s company %d, paybill %s: %s', $ok ? 'OK ' : 'NO ', $row->company_id, $row->get('shortcode'), $message));
        }
        if ($rows->isEmpty()) {
            $this->warn('No company has its own M-Pesa Daraja paybill switched on.');
        }

        return self::SUCCESS;
    }
}
