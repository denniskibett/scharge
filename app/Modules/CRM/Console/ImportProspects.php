<?php

namespace App\Modules\CRM\Console;

use App\Models\User;
use App\Modules\CRM\Services\CrmImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportProspects extends Command
{
    protected $signature = 'crm:import
        {files?* : CSV files to import (default: every CSV in database/data/crm)}
        {--owner= : Email of the staff member who owns rows without an Owner}';

    protected $description = 'Import prospect sheets into the CRM, merging duplicates';

    public function handle(CrmImporter $importer): int
    {
        $missing = array_filter([
            'companies.lifecycle / name_key / crm_profile' => ! Schema::hasColumns('companies', ['lifecycle', 'name_key', 'crm_profile']),
            'users.country_code' => ! Schema::hasColumn('users', 'country_code'),
            'crm_follow_ups table' => ! Schema::hasTable('crm_follow_ups'),
            'waitlist_entries table' => ! Schema::hasTable('waitlist_entries'),
        ]);
        if ($missing) {
            $this->error('The CRM tables are not set up in database "'.DB::connection()->getDatabaseName().'" yet. Missing: '.implode(', ', array_keys($missing)).'.');
            $this->line('Apply database/schema/2026_10_13_crm_waitlist.sql to that database first, then run this again.');

            return self::FAILURE;
        }

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
