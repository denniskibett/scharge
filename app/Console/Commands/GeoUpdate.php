<?php

namespace App\Console\Commands;

use App\Services\Analytics\GeoIp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader;

/**
 * Downloads this month's DB-IP "IP to City Lite" database (free, CC BY 4.0) used to place sign-ins on the map.
 * Scheduled monthly; safe to run any time (keeps the old file if the download fails or is not a valid database).
 */
class GeoUpdate extends Command
{
    protected $signature = 'geo:update';

    protected $description = 'Download the free DB-IP city database for sign-in locations';

    public function handle(): int
    {
        @mkdir(dirname(GeoIp::path()), 0755, true);
        $tmp = GeoIp::path().'.download';
        foreach ([now(), now()->subMonthNoOverflow()] as $month) { // a new month's file appears a day or two late
            $url = 'https://download.db-ip.com/free/dbip-city-lite-'.$month->format('Y-m').'.mmdb.gz';
            $res = Http::timeout(300)->withOptions(['sink' => $tmp.'.gz'])->get($url);
            if (! $res->successful()) {
                continue;
            }
            $in = gzopen($tmp.'.gz', 'rb');
            $out = fopen($tmp, 'wb');
            while (! gzeof($in)) {
                fwrite($out, gzread($in, 1 << 20));
            }
            gzclose($in);
            fclose($out);
            @unlink($tmp.'.gz');
            try {
                (new Reader($tmp))->close(); // a real database, not an error page
            } catch (\Throwable $e) {
                @unlink($tmp);
                $this->error('Downloaded file is not a valid database: '.$e->getMessage());

                return self::FAILURE;
            }
            rename($tmp, GeoIp::path());
            $this->info('Updated: '.$month->format('F Y').' ('.round(filesize(GeoIp::path()) / 1048576).' MB).');

            return self::SUCCESS;
        }
        @unlink($tmp.'.gz');
        $this->warn('No DB-IP download available; the current file (if any) is kept.');

        return self::SUCCESS;
    }
}
