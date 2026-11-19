<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use App\Models\CompanyIntegration;
use App\Services\Integrations;
use Illuminate\Support\Facades\DB;

/**
 * The Claude assistant (docs/ASSISTANT.md): turns facts from scharge into a draft message (email, SMS or WhatsApp) that
 * waits in ai_drafts for a person to send. Claude only words the message: every amount, date, link and name comes from
 * the facts passed in, and it is told never to invent any.
 */
class Claude
{
    public const MODEL = 'claude-opus-5-5';

    /** Tests only: fn (string $task, string $channel, array $facts) => ['subject' => …, 'body' => …, 'note' => …]. */
    public static ?\Closure $fake = null;

    public static function apiKey(): ?string
    {
        if (! Integrations::installed()) {
            return config('services.anthropic.key') ?: null;
        }
        $own = Integrations::for(null, 'claude');
        $kyc = CompanyIntegration::where('provider', 'document_reader')->whereNull('company_id')->where('enabled', true)->latest('id')->first();

        return $own?->get('api_key') ?: ($kyc?->get('api_key') ?: (config('services.anthropic.key') ?: null));
    }

    /** On for a company (its Assistant card), or for scharge's own CRM drafts (company null). */
    public static function enabledFor(?int $companyId): bool
    {
        if (! self::installed() || ! self::apiKey()) {
            return false;
        }
        if ($companyId === null) {
            return Integrations::for(null, 'claude')?->get('crm', 'on') !== 'off';
        }

        return (bool) Integrations::for($companyId, 'assistant');
    }

    public static function installed(): bool
    {
        return \App\Services\Connectors\Hub::installed('ai_drafts');
    }

    /**
     * Write a draft and store it (once per $ref). Returns the ai_drafts id, or null when it was already drafted,
     * the assistant is off, or the daily cap is reached.
     *
     * @param  array{kind: string, ref: string, channel: string, to_name?: ?string, to_address?: ?string, task: string, facts: array, context?: array, subject?: ?string}  $d
     */
    public static function draft(?int $companyId, array $d): ?int
    {
        if (! self::enabledFor($companyId)) {
            return null;
        }
        $ref = ($companyId ? 'c'.$companyId : 'scharge').':'.$d['ref'];
        if (DB::table('ai_drafts')->where('source_ref', $ref)->exists() || self::overCap($companyId)) {
            return null;
        }
        [$out, $usage, $error] = self::write($companyId, $d['task'], $d['channel'], $d['facts']);

        return DB::table('ai_drafts')->insertOrIgnore([
            'company_id' => $companyId, 'kind' => $d['kind'], 'status' => $error ? 'failed' : 'draft', 'channel' => $d['channel'], 'source_ref' => $ref,
            'to_name' => mb_substr((string) ($d['to_name'] ?? ''), 0, 190) ?: null, 'to_address' => mb_substr((string) ($d['to_address'] ?? ''), 0, 190) ?: null,
            'subject' => match ($d['channel']) {
                'email' => mb_substr((string) (($out['subject'] ?? '') ?: ($d['subject'] ?? '')), 0, 255),
                'action' => mb_substr((string) ($d['subject'] ?? ''), 0, 255),
                default => null,
            },
            'body' => $out['body'] ?? null, 'note' => mb_substr((string) ($out['note'] ?? ''), 0, 500) ?: null,
            'context' => json_encode(($d['context'] ?? []) + ['facts' => $d['facts'], 'task' => $d['task']]),
            'model' => self::MODEL, 'input_tokens' => $usage[0] ?? null, 'output_tokens' => $usage[1] ?? null, 'error' => $error,
            'created_at' => now(), 'updated_at' => now(),
        ]) ? (int) DB::table('ai_drafts')->where('source_ref', $ref)->value('id') : null;
    }

    /** Write again, following the reviewer's instruction. */
    public static function redraft(object $draft, string $instruction): array
    {
        $ctx = json_decode((string) $draft->context, true) ?: [];
        $facts = ($ctx['facts'] ?? []) + ['previous_draft' => ['subject' => $draft->subject, 'body' => $draft->body]];
        [$out, $usage, $error] = self::write($draft->company_id ? (int) $draft->company_id : null,
            ($ctx['task'] ?? 'Write the message.')."\nThe reviewer asked for this change to the previous draft: ".mb_substr($instruction, 0, 1000), $draft->channel, $facts);

        return [$out, $error];
    }

    /** One call to Claude. @return array{0: array{subject?: string, body?: string, note?: string}, 1: array{0: int, 1: int}, 2: ?string} */
    public static function write(?int $companyId, string $task, string $channel, array $facts): array
    {
        if (self::$fake) {
            return [(self::$fake)($task, $channel, $facts), [10, 20], null];
        }
        $key = self::apiKey();
        if (! $key) {
            return [[], [], 'The Claude assistant has no Anthropic key yet.'];
        }
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['subject', 'body', 'note'], 'properties' => [
            'subject' => ['type' => 'string', 'description' => 'Email subject; empty for SMS and WhatsApp'],
            'body' => ['type' => 'string', 'description' => 'The message, ready to send'],
            'note' => ['type' => 'string', 'description' => 'One line for the person reviewing: anything to check before sending, or empty'],
        ]];
        try {
            $message = (new Client(apiKey: $key))->beta->messages->create(
                maxTokens: 4000,
                model: self::MODEL,
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                system: self::system($companyId, $channel),
                messages: [['role' => 'user', 'content' => "Task: {$task}\n\nFacts (JSON, the only source of truth):\n"
                    .json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            );
            if ($message->stopReason === 'refusal') {
                return [[], [], 'Claude declined to write this one. Write it by hand.'];
            }
            foreach ($message->content as $block) {
                if ($block->type === 'text' && is_array($json = json_decode($block->text, true))) {
                    $body = trim((string) ($json['body'] ?? ''));
                    if (in_array($channel, ['sms', 'whatsapp'], true)) {
                        $body = mb_substr(preg_replace('/\s+/', ' ', $body), 0, 480);
                    }

                    return [['subject' => trim((string) $json['subject']), 'body' => $body, 'note' => trim((string) $json['note'])],
                        [(int) ($message->usage->inputTokens ?? 0), (int) ($message->usage->outputTokens ?? 0)], $body === '' ? 'Empty draft.' : null];
                }
            }

            return [[], [], 'Claude returned no draft.'];
        } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
            return [[], [], 'Claude could not be reached ('.($e->type?->value ?? 'API error').').'];
        } catch (\Throwable $e) {
            return [[], [], mb_substr('Claude could not be reached: '.$e->getMessage(), 0, 300)];
        }
    }

    public static function test(CompanyIntegration $row): array
    {
        if ($row->provider === 'assistant' && ! self::apiKey()) {
            return [false, 'scharge has not added its Anthropic key yet. Ask scharge support.'];
        }
        if (! self::installed()) {
            return [false, 'Not installed yet: scharge support must apply database/schema/2026_11_20_ai_drafts.sql.'];
        }
        [$out, , $error] = self::write($row->company_id ? (int) $row->company_id : null, 'Write a one-sentence SMS telling a tenant their water will be off on Saturday from 9am to noon for repairs.', 'sms',
            ['tenant_first_name' => 'Jane', 'unit' => 'A1', 'date' => 'Saturday']);

        return $error ? [false, $error] : [true, 'Claude is ready. Sample: "'.mb_substr($out['body'], 0, 160).'"'];
    }

    private static function system(?int $companyId, string $channel): string
    {
        if ($companyId) {
            $row = Integrations::for($companyId, 'assistant');
            $brand = \App\Support\Brand::forCompany($companyId)?->display_name ?: \App\Models\Company::find($companyId)?->name;
            $who = "You write on behalf of {$brand}, a Kenyan landlord / property management business using scharge. You write to their tenants and guests.";
            $tone = ['warm' => 'warm and friendly', 'formal' => 'formal and polite', 'brief' => 'short and to the point'][$row?->get('tone', 'warm')] ?? 'warm and friendly';
            $sign = $row?->get('sign_off') ?: $brand;
            $house = (string) $row?->get('instructions');
        } else {
            $row = Integrations::for(null, 'claude');
            $who = 'You write for scharge (scharge.sharet.africa), Kenyan property management software for landlords, agencies and short-stay hosts. '
                .'You write to property companies: prospects in the sales pipeline and customers about their subscription. Payments are marketed as Sharet Pay.';
            $tone = 'warm, confident and concise, like a helpful account manager';
            $sign = 'the scharge team';
            $house = (string) $row?->get('instructions');
        }
        $format = match ($channel) {
            'email' => 'Write an email: a clear subject, then a short body in plain text (no markdown), with short paragraphs. End with the sign-off.',
            'action' => 'Write a short plain explanation for the business\'s own staff (not a message to anyone outside). Subject empty.',
            default => 'Write one SMS/WhatsApp message under 300 characters, plain text, no subject, no emojis, starting with the person\'s first name.',
        };

        return "{$who}\nTone: {$tone}. Sign off as: {$sign}.\n{$format}\n"
            ."Rules: use only the facts given. Never invent amounts, dates, account numbers, links, promises, discounts or policies. "
            ."Copy amounts and links exactly. If a fact needed to answer is missing, write the best safe reply and say what is missing in the note. "
            ."Never threaten eviction, legal action or penalties unless the facts say so. Never mention that you are an AI or that this was drafted automatically. "
            ."Write in the language of the person's message when there is one (English or Swahili), else English."
            .($house !== '' ? "\nHouse rules and facts from the business:\n".mb_substr($house, 0, 3000) : '');
    }

    private static function overCap(?int $companyId): bool
    {
        $cap = (int) (Integrations::for(null, 'claude')?->get('daily_limit') ?: 60);

        return DB::table('ai_drafts')->where('company_id', $companyId)->where('created_at', '>=', now()->startOfDay())->count() >= $cap;
    }
}
