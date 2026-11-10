<?php

namespace App\Services\Data;

use App\Models\CompanyIntegration;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * The nightly copy of a company's data to storage the company controls (Integrations › Nightly data copy):
 * an SFTP server, or any S3-compatible bucket (Amazon S3, Google Cloud Storage with HMAC keys, Wasabi, Backblaze B2,
 * DigitalOcean Spaces…). The file is CompanyExport's zip; copies older than "keep days" are deleted there.
 * The company holds its own copy; scharge keeps the master database and its own backups (scripts/deploy.sh, cron).
 */
class CompanyBackup
{
    public function __construct(private CompanyExport $export)
    {
    }

    /** @return array{0: bool, 1: string} */
    public function run(CompanyIntegration $row): array
    {
        $file = null;
        try {
            $disk = $this->disk($row);
            $file = $this->export->zip((int) $row->company_id);
            $name = basename($file);
            $stream = fopen($file, 'r');
            $disk->writeStream($name, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $size = round(filesize($file) / 1048576, 2);
            $pruned = $this->prune($disk, (int) $row->company_id, max(1, (int) ($row->get('keep_days') ?: 30)));
            $result = [true, sprintf('Copied %s (%s MB) at %s.%s', $name, $size, now('Africa/Nairobi')->format('j M H:i'), $pruned ? " Removed {$pruned} older ".($pruned === 1 ? 'copy' : 'copies').'.' : '')];
        } catch (\Throwable $e) {
            $result = [false, 'Nightly copy failed: '.mb_substr($e->getMessage(), 0, 200)];
        } finally {
            if ($file && is_file($file)) {
                unlink($file);
            }
        }
        $row->forceFill(['last_tested_at' => now(), 'last_test_ok' => $result[0], 'last_test_message' => mb_substr($result[1], 0, 250)])->save();

        return $result;
    }

    /** Integrations › Test: write and delete a small file, nothing else. @return array{0: bool, 1: string} */
    public static function test(CompanyIntegration $row): array
    {
        try {
            $disk = (new self(app(CompanyExport::class)))->disk($row);
            $probe = 'scharge-test-'.now()->format('YmdHis').'.txt';
            $disk->put($probe, 'scharge can write here. You can delete this file.');
            $disk->delete($probe);

            return [true, 'scharge can write to your storage. The first copy goes there tonight at 02:30.'];
        } catch (\Throwable $e) {
            return [false, 'Could not write to your storage: '.mb_substr($e->getMessage(), 0, 200)];
        }
    }

    protected function disk(CompanyIntegration $row): Filesystem
    {
        $root = trim((string) $row->get('path', 'scharge-backups'), '/');

        return match ($row->get('method', 'sftp')) {
            's3' => Storage::build(['driver' => 's3', 'key' => $row->get('s3_key'), 'secret' => $row->get('s3_secret'),
                'region' => $row->get('s3_region') ?: 'us-east-1', 'bucket' => $row->get('s3_bucket'), 'endpoint' => $row->get('s3_endpoint') ?: null,
                'use_path_style_endpoint' => filled($row->get('s3_endpoint')), 'root' => $root, 'throw' => true, 'visibility' => 'private']),
            default => Storage::build(['driver' => 'sftp', 'host' => $row->get('host'), 'port' => (int) ($row->get('port') ?: 22),
                'username' => $row->get('username'), 'password' => $row->get('password'), 'root' => '/'.$root, 'timeout' => 30, 'throw' => true]),
        };
    }

    private function prune(Filesystem $disk, int $companyId, int $keepDays): int
    {
        $cutoff = now()->subDays($keepDays)->format('Ymd');
        $removed = 0;
        foreach ($disk->files() as $f) {
            if (preg_match('/-'.$companyId.'-(\d{8})-\d{6}\.zip$/', $f, $m) && $m[1] < $cutoff) {
                $disk->delete($f);
                $removed++;
            }
        }

        return $removed;
    }
}
