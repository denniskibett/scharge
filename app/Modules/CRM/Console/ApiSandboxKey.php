<?php

namespace App\Modules\CRM\Console;

use App\Models\ApiKey;
use App\Models\Company;
use App\Modules\CRM\Mail\ApiSandboxKeyEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ContactService;
use App\Modules\Users\Models\User;
use App\Support\ApiSandbox;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Answers a "Sandbox API keys" request (/crm/requests): issues a test key on the API sandbox (sample data, see
 * App\Support\ApiSandbox), and emails it with the partner pack PDF (white label, prices, revenue share).
 *
 *   crm:api-sandbox-key 96 --preview=/tmp/out      write the email (HTML) and PDF with a dummy key; nothing issued or sent
 *   crm:api-sandbox-key 96 --send [--manager=664]  issue the key, send, log it on the company and close the request
 *
 * The plain key exists only in the email: it is never printed, logged or stored (only its hash, like every key).
 */
class ApiSandboxKey extends Command
{
    protected $signature = 'crm:api-sandbox-key {company : CRM company id} {--email= : Send to this address (default: the request\'s email, else the company email)}
        {--manager= : User id who signs (default: the company owner, else "The scharge team")} {--preview= : Folder for a preview} {--send : Issue and send}';

    protected $description = 'Issue a sandbox API key and email it with the partner pack (white label, prices, revenue share)';

    public function handle(ContactService $contacts): int
    {
        $company = Company::withoutGlobalScope('customers')->find($this->argument('company'));
        if (! $company) {
            $this->error('No such company.');

            return self::FAILURE;
        }
        $request = $company->followUps()->where('type', 'api_keys')->latest('id')->first();
        $to = $this->option('email') ?: ($request && preg_match('/[^\s]+@[^\s]+/', (string) $request->title, $m) ? $m[0] : $company->email);
        $managerId = $this->option('manager') ?: $company->owner_id;
        $manager = $managerId ? User::find($managerId) : null;
        $first = Str::before((string) ($request?->contact?->name ?? ''), ' ') ?: null;
        $ref = 'AK-'.strtoupper(Str::random(6));

        if ($dir = $this->option('preview')) {
            @mkdir($dir, 0775, true);
            $key = 'sk_test_'.str_repeat('x', 36).'PREV';
            $pdf = $dir.'/partner-pack-'.$ref.'.pdf';
            file_put_contents($pdf, $this->pdf($company, $ref, 'sk_test_••••••••••PREV'));
            file_put_contents($dir.'/email-'.$ref.'.html', (new ApiSandboxKeyEmail($company, $key, $pdf, $ref, $manager, $first))->render());
            $this->info("Preview in {$dir}: to {$to}, signed ".($manager?->name ?? 'The scharge team').", ref {$ref}. Nothing issued or sent.");

            return self::SUCCESS;
        }
        if (! $this->option('send')) {
            $this->line("Would send to {$to} (signed ".($manager?->name ?? 'The scharge team')."). Use --preview=<dir> or --send.");

            return self::SUCCESS;
        }
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('No valid address to send to. Pass --email=.');

            return self::FAILURE;
        }

        $sandbox = ApiSandbox::companyOrCreate();
        $apiKey = new ApiKey(['company_id' => $sandbox->id, 'user_id' => $request?->user_id, 'name' => Str::limit($company->name, 60).' sandbox',
            'environment' => 'test', 'scopes' => ['read', 'collect'], 'enabled' => true]);
        $plain = $apiKey->issue();
        $apiKey->save();

        $path = storage_path('app/crm/partner-packs/'.$company->id.'/'.$ref.'.pdf');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $this->pdf($company, $ref, $apiKey->masked()));

        try {
            $sent = Mail::to($to)->send(new ApiSandboxKeyEmail($company, $plain, $path, $ref, $manager, $first));
        } catch (\Throwable $e) {
            $apiKey->delete(); // nobody has it: don't leave a key that was never delivered
            $this->error('Sending failed, key removed: '.$e->getMessage());

            return self::FAILURE;
        }
        unset($plain);

        $contacts->log($company, 'email', 'Sent sandbox API key and partner pack ('.$ref.')',
            'Sandbox key '.$apiKey->masked().' (sample data, read + pay links). Partner pack PDF: white label, KES 50,000 setup (3 ways to pay), monthly rates, revenue share 20/25/30%.',
            array_filter(['direction' => 'out', 'to' => $to, 'from' => \App\Modules\CRM\Support\Playbook::SALES_FROM, 'ref' => $ref, 'api_key_id' => $apiKey->id,
                'pdf' => 'crm/partner-packs/'.$company->id.'/'.$ref.'.pdf', 'message_id' => $sent?->getMessageId()]), $request?->contact, $manager?->id);
        CrmFollowUp::where('company_id', $company->id)->where('type', 'api_keys')->whereIn('status', ['open', 'scheduled'])
            ->update(['status' => 'done', 'done_at' => now(), 'outcome' => 'Sandbox key and partner pack sent ('.$ref.')']);
        if (! $company->owner_id && $manager) {
            $company->forceFill(['owner_id' => $manager->id])->saveQuietly();
        }
        if (in_array($company->crm_stage, [null, 'new'], true)) {
            $contacts->setStage($company, 'contacted', $manager?->id);
        }
        $this->info("Sent to {$to} ({$ref}), key {$apiKey->masked()} on the sandbox (#{$sandbox->id}). Request closed.");

        return self::SUCCESS;
    }

    private function pdf(Company $company, string $ref, string $masked): string
    {
        return Pdf::loadView('crm.pdf.partner-pack', ['company' => $company, 'ref' => $ref, 'masked' => $masked])->setPaper('a4')->output();
    }
}
