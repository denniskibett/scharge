<?php

namespace App\Services\Kyc;

use Anthropic\Client;
use App\Models\KycDocument;
use App\Services\Integrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Reads an uploaded KYC document (photo or PDF of a Kenyan ID, passport, KRA PIN certificate, business registration,
 * CR12 or title deed) with Claude (vision) and returns its fields as structured JSON, so owners do not type them and
 * KycChecks can compare documents. Platform setting: Integrations › Document reading (Anthropic API key), off until set.
 * Documents go to Anthropic's API only while it is on (docs/ONBOARDING.md, data protection note).
 */
class DocumentReader
{
    public const MODEL = 'claude-opus-5-5';

    /** Fields to read per kind of document (all strings, "" when not on the document). */
    public const FIELDS = [
        'national_id' => ['full_name', 'id_number', 'date_of_birth', 'sex', 'serial_number', 'date_of_issue'],
        'passport' => ['full_name', 'passport_number', 'nationality', 'date_of_birth', 'date_of_expiry'],
        'kra_pin' => ['taxpayer_name', 'kra_pin', 'email', 'date_of_issue'],
        'business_registration' => ['business_name', 'registration_number', 'date_of_registration', 'business_type'],
        'cr12' => ['company_name', 'registration_number', 'directors', 'date_of_issue'],
        'title_deed' => ['owner_name', 'title_number', 'land_reference', 'location', 'area_size', 'date_of_issue'],
        'other' => ['document_type', 'name', 'reference_number', 'date'],
    ];

    public static function enabled(): bool
    {
        return (bool) self::apiKey();
    }

    private static function apiKey(): ?string
    {
        $row = Integrations::installed() ? \App\Models\CompanyIntegration::withoutGlobalScope('company')->where('provider', 'document_reader')->whereNull('company_id')->where('enabled', true)->latest('id')->first() : null;

        return $row?->get('api_key') ?: (config('services.anthropic.key') ?: null);
    }

    /** Read one document and store the result on it. */
    public function read(KycDocument $doc): KycDocument
    {
        $key = self::apiKey();
        if (! $key) {
            $doc->update(['read_status' => 'off', 'read_error' => 'Document reading is not switched on: fill the details by hand.']);

            return $doc;
        }

        try {
            $fields = self::FIELDS[$doc->kind] ?? self::FIELDS['other'];
            $data = base64_encode(Storage::disk('local')->get($doc->path));
            $file = $doc->mime === 'application/pdf'
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data]]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $doc->mime, 'data' => $data]];
            $schema = ['type' => 'object', 'additionalProperties' => false,
                'required' => array_merge($fields, ['legible', 'looks_like', 'notes']),
                'properties' => array_fill_keys($fields, ['type' => 'string']) + [
                    'legible' => ['type' => 'boolean'],
                    'looks_like' => ['type' => 'string', 'description' => 'What kind of document this actually is'],
                    'notes' => ['type' => 'string', 'description' => 'Anything unclear, cut off, edited-looking or inconsistent'],
                ]];

            $message = (new Client(apiKey: $key))->beta->messages->create(
                maxTokens: 4000,
                model: self::MODEL,
                fallbacks: 'default', // a declined request is retried on the server's fallback model
                betas: ['server-side-fallback-2026-07-01'],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                system: 'You read Kenyan identity, tax, company and land documents for a landlord verification (KYC) check. '
                    .'Copy each field exactly as printed; do not guess or complete partly hidden values: leave a field "" when it is not on the document or not readable. '
                    .'Dates as YYYY-MM-DD. Names in the order printed.',
                messages: [['role' => 'user', 'content' => [$file, ['type' => 'text',
                    'text' => 'This was uploaded as: '.(KycDocument::KINDS[$doc->kind] ?? $doc->kind).'. Read it and return the fields.']]]],
            );

            if ($message->stopReason === 'refusal') {
                throw new \RuntimeException('The document could not be read automatically.');
            }
            $json = null;
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $json = json_decode($block->text, true);
                    break;
                }
            }
            if (! is_array($json)) {
                throw new \RuntimeException('No readable result.');
            }
            $doc->update(['read_status' => 'read', 'extracted' => array_map(fn ($v) => is_string($v) ? trim($v) : $v, $json), 'read_error' => null]);
        } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
            Log::warning('KYC document reading failed', ['document_id' => $doc->id, 'type' => $e->type?->value]);
            $doc->update(['read_status' => 'failed', 'read_error' => 'Reading failed ('.($e->type?->value ?? 'API error').'). Fill the details by hand or try again.']);
        } catch (\Throwable $e) {
            Log::warning('KYC document reading failed', ['document_id' => $doc->id, 'error' => $e->getMessage()]);
            $doc->update(['read_status' => 'failed', 'read_error' => mb_substr('Reading failed: '.$e->getMessage(), 0, 250)]);
        }

        return $doc->fresh();
    }
}
