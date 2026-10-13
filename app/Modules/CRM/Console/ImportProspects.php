<?php

namespace App\Modules\CRM\Console;

use App\Models\User;
use App\Modules\CRM\Services\CrmImporter;
use Illuminate\Console\Command;

class ImportProspects extends Command
{
    protected $signature = 'crm:import
        {files?* : CSV files to import (default: every CSV in database/data/crm)}
        {--owner= : Email of the staff member who owns rows without an Owner}';

    protected $description = 'Import prospect sheets into the CRM, merging duplicates';

    public function handle(CrmImporter $importer): int
    {
        $files = $this->argument('files') ?: glob(database_path('data/crm/*.csv'));
        if (! $files) {
            $this->error('No CSV files found.');

            return self::FAILURE;
        }

        $ownerId = null;
        if ($email = $this->option('owner')) {
            $ownerId = User::where('email', $email)->value('id');
            if (! $ownerId) {
                $this->error("No user with email {$email}.");

                return self::FAILURE;
            }
        }

        $rows = [];
        foreach ($files as $file) {
            $stats = $importer->import($file, $ownerId);
            $rows[] = [basename($file), $stats['rows'], $stats['created'], $stats['merged'], $stats['skipped'], $stats['flagged']];
        }
        $this->table(['File', 'Rows', 'New companies', 'Merged into existing', 'Skipped', 'Need verifying'], $rows);

        return self::SUCCESS;
    }
}
