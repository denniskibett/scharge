<?php

namespace App\Console\Commands;

use App\Models\CompanyIntegration;
use App\Services\Data\CompanyBackup;
use Illuminate\Console\Command;

/** Nightly copy of each company's data to its own storage (Integrations › Nightly data copy). Scheduled 02:30. */
class CompanyDataCommands extends Command
{
    protected $signature = 'company:backup-offsite {--company= : One company id}';

    protected $description = 'Copy each company\'s data to the storage it set up (SFTP or S3)';

    public function handle(CompanyBackup $backup): int
    {
        $rows = CompanyIntegration::withoutGlobalScope('company')->where('provider', 'data_backup')->where('enabled', true)->whereNotNull('company_id')
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))->get();
        foreach ($rows as $row) {
            [$ok, $message] = $backup->run($row);
            $this->line(($ok ? 'OK  ' : 'NO  ').'company '.$row->company_id.': '.$message);
        }
        if ($rows->isEmpty()) {
            $this->line('No company has a nightly data copy set up.');
        }

        return self::SUCCESS;
    }
}
