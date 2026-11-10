<?php

namespace App\Console\Commands;

use App\Services\Data\CompanyExport;
use Illuminate\Console\Command;

/** All of one company's data as a zip of CSVs (the same file as Settings › White label & data › Download). */
class ExportCompany extends Command
{
    protected $signature = 'company:export {company : Company id} {--to= : Folder to move the zip to}';

    protected $description = 'Export everything a company owns (whole history) to a zip of CSV files';

    public function handle(CompanyExport $export): int
    {
        $file = $export->zip((int) $this->argument('company'));
        if ($to = $this->option('to')) {
            @mkdir($to, 0700, true);
            rename($file, $dest = rtrim($to, '/').'/'.basename($file));
            $file = $dest;
        }
        $this->info($file.' ('.round(filesize($file) / 1024).' KB)');

        return self::SUCCESS;
    }
}
