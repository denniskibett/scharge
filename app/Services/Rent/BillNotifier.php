<?php

namespace App\Services\Rent;

use App\Models\Invoice;
use App\Models\RentMessage;
use App\Modules\SMS\Services\KenyaSMS;
use App\Services\Integrations;
use App\Services\Sharet\SharetCollections;
use App\Services\WhatsApp;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sends rent bills and arrears reminders to tenants (SMS, or WhatsApp when the company chose it and has its own
 * WhatsApp number set up). Each message is claimed in `rent_messages` first (unique per invoice, kind and stage),
 * so a second run, or two runs at once, never send it twice.
 */
class BillNotifier
{
    public function billReady(Invoice $invoice): ?RentMessage
    {
        $tenancy = $invoice->tenancy;
        $companyId = (int) $invoice->owningCompanyId();
        $settings = $companyId ? RentSettings::for($companyId) : null;
        if (! $tenancy || ! $settings || ! $settings->sendBill) {
            return null;
        }

        $month = CarbonImmutable::parse(substr((string) $invoice->getRawOriginal('billing_month'), 0, 10), RentSettings::TZ)->startOfMonth();
        $balance = $this->balance($invoice);
        $text = sprintf('Hi %s, your %s bill for %s is KES %s, due %s. Pay here: %s',
            $this->firstName($tenancy), $month->format('F Y'), $this->place($tenancy), number_format($balance),
            $settings->dueDate($month)->format('j M'), $this->payLink($invoice));

        return $this->send($settings, $invoice, 'bill', 0, $balance, $text);
    }

    /**
     * One reminder for a tenancy's overdue invoices: the total owed, with a pay link for the oldest one.
     *
     * @param  Collection<int, Invoice>  $invoices  overdue invoices of one tenancy, oldest first
     */
    public function remind(RentSettings $settings, Collection $invoices, int $stage, int $daysLate): ?RentMessage
    {
        $oldest = $invoices->first();
        $tenancy = $oldest?->tenancy;
        if (! $tenancy) {
            return null;
        }

        $owed = $invoices->sum(fn ($i) => $this->balance($i));
        $dueOn = app(ArrearsReminders::class)->dueDate($settings, $oldest);
        $late = $daysLate <= 0 ? 'was due today' : "is {$daysLate} ".($daysLate === 1 ? 'day' : 'days').' late';
        $text = sprintf('Hi %s, KES %s for %s %s (due %s). Please pay here: %s . Already paid? Please ignore this.',
            $this->firstName($tenancy), number_format($owed), $this->place($tenancy), $late, $dueOn->format('j M'), $this->payLink($oldest));

        $message = $this->send($settings, $oldest, 'reminder', $stage, $owed, $text);
        // The other invoices in this message count as reminded at this stage too.
        foreach ($invoices->slice(1) as $invoice) {
            $this->claim($settings, $invoice, 'reminder', $stage, $this->balance($invoice), 'Included in the reminder for invoice '.$oldest->id.'.', 'grouped');
        }

        return $message;
    }

    /**
     * A fresh link to a bill (e.g. after old links stopped working). At most once a day per bill. Works whether or not
     * rent automation is on; uses the company's SMS sender.
     */
    public function resendLink(Invoice $invoice): ?RentMessage
    {
        $tenancy = $invoice->tenancy;
        $companyId = (int) $invoice->owningCompanyId();
        if (! $tenancy || ! $companyId) {
            return null;
        }
        $settings = RentSettings::for($companyId) ?? new RentSettings($companyId, 1, 5, false, [], 'sms');
        $today = RentSettings::today();
        $text = sprintf('Hi %s, here is your bill for %s: KES %s to pay. View and pay here: %s',
            $this->firstName($tenancy), $this->place($tenancy), number_format($this->balance($invoice)), $this->payLink($invoice));

        return $this->send($settings, $invoice, 'link', ($today->year % 100) * 1000 + $today->dayOfYear, $this->balance($invoice), $text);
    }

    /** Send a failed bill or reminder again (same text, fresh attempt). */
    public function retry(RentMessage $message): RentMessage
    {
        abort_unless(in_array($message->status, ['failed', 'no_phone'], true), 422, 'Only failed messages can be sent again.');
        $invoice = Invoice::with('tenancy.tenant.user')->findOrFail($message->invoice_id);
        $settings = RentSettings::for((int) $message->company_id) ?? new RentSettings((int) $message->company_id, 1, 5, true, [], 'sms');
        $message->update(['status' => 'sending', 'error' => null]);
        $this->deliver($settings, $invoice, $message, (string) $message->message);

        return $message->fresh();
    }

    private function send(RentSettings $settings, Invoice $invoice, string $kind, int $stage, float $balance, string $text): ?RentMessage
    {
        $row = $this->claim($settings, $invoice, $kind, $stage, $balance, $text, 'sending');
        if (! $row) {
            return null; // already sent
        }

        return $this->deliver($settings, $invoice, $row, $text);
    }

    private function deliver(RentSettings $settings, Invoice $invoice, RentMessage $row, string $text): RentMessage
    {
        // The scharge app first: a push is free; SMS only for people without the app (docs/APP_DATA.md).
        $userId = $invoice->tenancy?->tenant?->user?->id;
        if ($userId && \App\Services\App\PushService::reachable($userId)) {
            $title = ['bill' => 'Your bill is ready', 'reminder' => 'Rent reminder', 'link' => 'Your bill'][$row->kind] ?? 'scharge';
            $sent = app(\App\Services\App\PushService::class)->toUser($userId, $title, $text, ['type' => $row->kind, 'invoice_id' => $invoice->id]);
            if ($sent > 0) {
                $row->update(['channel' => 'push', 'status' => 'sent', 'error' => null]);

                return $row;
            }
        }

        $phone = $invoice->tenancy?->tenant?->user?->phone;
        if (blank($phone)) {
            $row->update(['status' => 'no_phone', 'error' => 'The tenant has no phone number.']);

            return $row;
        }

        try {
            $wa = $settings->channel === 'whatsapp' ? WhatsApp::forCompany($settings->companyId) : null;
            if ($wa && $wa->canSend()) {
                $res = $wa->send($phone, $text);
                $channel = 'whatsapp';
            } else {
                $res = app(KenyaSMS::class)->forCompany($settings->companyId)->sendOne($phone, $text);
                $channel = ($res['channel'] ?? null) === 'whatsapp' ? 'whatsapp' : 'sms';
            }
            $ok = (bool) ($res['success'] ?? false);
            $row->update(['phone' => $phone, 'channel' => $channel, 'status' => $ok ? 'sent' : 'failed',
                'error' => $ok ? null : mb_substr((string) ($res['message'] ?? $res['error'] ?? 'Not sent.'), 0, 250)]);
        } catch (\Throwable $e) {
            Log::warning('Rent message failed', ['invoice_id' => $invoice->id, 'kind' => $row->kind, 'error' => $e->getMessage()]);
            $row->update(['phone' => $phone, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
        }

        return $row;
    }

    /** Reserve the message; null when it was already sent (or is being sent by another run). */
    private function claim(RentSettings $settings, Invoice $invoice, string $kind, int $stage, float $balance, string $text, string $status): ?RentMessage
    {
        try {
            return RentMessage::create(['company_id' => $settings->companyId, 'tenancy_id' => $invoice->tenancy_id, 'invoice_id' => $invoice->id,
                'kind' => $kind, 'stage' => $stage, 'status' => $status, 'balance' => $balance, 'message' => $text]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    public function balance(Invoice $invoice): float
    {
        return round(max(0, (float) $invoice->total_amount - (float) $invoice->total_paid), 2);
    }

    /** Sharet pay link when the company has no Daraja app of its own and Sharet is set up, else the public invoice page. */
    public function payLink(Invoice $invoice): string
    {
        try {
            if (! Integrations::daraja($invoice->owningCompanyId()) && SharetCollections::available()) {
                return app(SharetCollections::class)->payLink($invoice, null, $invoice->tenancy?->tenant?->user?->phone)->pay_url;
            }
        } catch (\Throwable $e) {
            Log::info('Rent message: Sharet pay link not made, using the invoice page', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
        }

        return \App\Support\InvoiceLink::url($invoice);
    }

    private function firstName($tenancy): string
    {
        return strtok(trim((string) ($tenancy->tenant?->user?->name ?? '')), ' ') ?: 'there';
    }

    private function place($tenancy): string
    {
        $unit = $tenancy->unit;

        return trim(($unit?->unit_number ? $unit->unit_number : 'your unit').($unit?->property?->name ? ', '.$unit->property->name : ''));
    }
}
