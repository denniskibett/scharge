<?php

namespace App\Services\Kyc;

use App\Models\CompanyKyc;
use App\Models\KycDocument;
use Illuminate\Http\UploadedFile;

/** Uploading, reading and pre-filling a company's KYC (docs/ONBOARDING.md). */
class KycService
{
    public function __construct(private DocumentReader $reader, private KycChecks $checks)
    {
    }

    public function forCompany(int $companyId): CompanyKyc
    {
        return CompanyKyc::firstOrCreate(['company_id' => $companyId], ['status' => 'draft']);
    }

    public function upload(int $companyId, string $kind, UploadedFile $file, ?int $propertyId, ?int $userId): KycDocument
    {
        $doc = KycDocument::create(['company_id' => $companyId, 'property_id' => $propertyId, 'kind' => $kind,
            'path' => $file->store('kyc/'.$companyId, 'local'), 'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'mime' => $file->getMimeType() ?: 'application/octet-stream', 'size' => (int) $file->getSize(),
            'read_status' => DocumentReader::enabled() ? 'pending' : 'off', 'uploaded_by' => $userId]);

        return $doc;
    }

    /** Read a document, then fill empty KYC fields from it and re-run the checks. */
    public function readAndPrefill(KycDocument $doc): void
    {
        $doc = $this->reader->read($doc);
        $kyc = $this->forCompany($doc->company_id);
        if ($doc->read_status === 'read') {
            $e = $doc->extracted ?? [];
            $fill = match ($doc->kind) {
                'national_id' => ['owner_name' => $e['full_name'] ?? null, 'owner_id_number' => $e['id_number'] ?? null],
                'passport' => ['owner_name' => $e['full_name'] ?? null, 'owner_id_number' => $e['passport_number'] ?? null],
                'kra_pin' => ['kra_pin' => strtoupper(preg_replace('/\s/', '', (string) ($e['kra_pin'] ?? ''))) ?: null],
                'business_registration' => ['business_name' => $e['business_name'] ?? null, 'registration_number' => $e['registration_number'] ?? null],
                'cr12' => ['business_name' => $e['company_name'] ?? null, 'registration_number' => $e['registration_number'] ?? null],
                default => [],
            };
            foreach ($fill as $k => $v) {
                if (blank($kyc->{$k}) && filled($v)) {
                    $kyc->{$k} = $v;
                }
            }
            $kyc->save();
        }
        $this->recheck($kyc);
    }

    public function recheck(CompanyKyc $kyc): CompanyKyc
    {
        $kyc->update(['checks' => $this->checks->run($kyc, KycDocument::with('property')->where('company_id', $kyc->company_id)->get())]);

        return $kyc;
    }
}
