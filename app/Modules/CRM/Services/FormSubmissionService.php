<?php

namespace App\Modules\CRM\Services;

use App\Models\User;
use App\Modules\CRM\Mail\FormSubmissionNotice;
use App\Modules\CRM\Models\CrmFollowUp;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The shared tail of every public form, after the submission has landed on its CRM row (the /demo/{ref} pattern):
 * a reference number, an owner (the company's account manager or the default one), a PDF of what was sent on the
 * private disk (linked from the thread, downloaded via crm.follow-ups.pdf) and an "Ink" email with the PDF attached.
 *
 * The form's answers travel in details.form: ['name' => 'Waitlist', 'rows' => [label => value|list], 'message' => ?string].
 */
class FormSubmissionService
{
    /** Form key => [reference prefix, label]. */
    public const FORMS = [
        'waitlist' => ['WL', 'Waitlist'],
        'demo' => ['BD', 'Book a demo'],
        'api_keys' => ['AK', 'Sandbox API keys'],
        'listing_request' => ['LR', 'Listing request'],
        'listing_alert' => ['LA', 'Listing alert'],
        'host_register' => ['HR', 'BnB host sign-up'],
        'site_contact' => ['WC', 'Website contact'],
        'feedback' => ['FB', 'Feedback'],
    ];

    /**
     * @param  array<string, mixed>  $rows  label => value (string, number or list of strings); blanks are dropped
     * @param  list<string>|null  $notify  email addresses; null = the follow-up's assignee
     * @param  bool  $assign  give an unowned row to the account manager (false for a customer's own enquiries)
     * @param  array{url: string, label: string}|null  $cta  the email's button; null = the CRM
     */
    public function record(CrmFollowUp $followUp, string $form, array $rows, ?string $message = null, ?array $notify = null, bool $assign = true, ?array $cta = null, ?string $fromName = null): CrmFollowUp
    {
        [$prefix, $label] = self::FORMS[$form];
        $ref = $followUp->detail('ref') ?: self::newRef($prefix);
        $rows = array_filter($rows, fn ($v) => is_array($v) ? array_filter($v) !== [] : ! blank($v));

        if ($assign && ! $followUp->assigned_to) {
            $followUp->assigned_to = $followUp->company?->owner_id ?? self::defaultManager()?->id;
        }
        $followUp->details = array_merge($followUp->details ?? [], [
            'ref' => $ref,
            'form' => array_filter(['key' => $form, 'name' => $label, 'rows' => $rows, 'message' => $message, 'submitted_at' => now()->toDateTimeString(), 'cta' => $cta, 'from_name' => $fromName], fn ($v) => $v !== null),
        ]);
        $followUp->save();
        $followUp->load('company', 'contact', 'assignee');

        try {
            $path = 'crm/submissions/'.($followUp->company_id ?: 'none').'/'.$ref.'-'.$followUp->id.'.pdf';
            Storage::disk('local')->put($path, Pdf::loadView('crm.pdf.form-submission', ['f' => $followUp])->setPaper('a4')->output());
            $followUp->update(['details' => array_merge($followUp->details, ['pdf' => $path])]);
        } catch (\Throwable $e) {
            Log::warning('Form submission PDF failed', ['follow_up' => $followUp->id, 'form' => $form, 'error' => $e->getMessage()]);
        }

        $to = array_values(array_unique(array_filter($notify ?? [$followUp->assignee?->email], fn ($e) => $e && filter_var($e, FILTER_VALIDATE_EMAIL))));
        if ($to) {
            // Never inside the caller's transaction: a rollback must not leave an email about a row that isn't there.
            DB::afterCommit(function () use ($followUp, $to, $form) {
                try {
                    Mail::to($to)->send(new FormSubmissionNotice($followUp->fresh(['company', 'contact', 'assignee'])));
                } catch (\Throwable $e) {
                    Log::warning('Form submission notice failed', ['follow_up' => $followUp->id, 'form' => $form, 'error' => $e->getMessage()]);
                }
            });
        }

        return $followUp;
    }

    public static function newRef(string $prefix): string
    {
        return $prefix.'-'.strtoupper(Str::random(6));
    }

    /** Who gets inbound requests nobody owns: CRM_DEFAULT_ACCOUNT_MANAGER (an email), else the first account manager, else the first sysadmin. */
    public static function defaultManager(): ?User
    {
        $email = config('services.crm.default_account_manager');
        if ($email && ($user = User::where('email', $email)->first())) {
            return $user;
        }

        return User::role('account_manager')->orderBy('id')->first() ?? User::role('sysadmin')->orderBy('id')->first();
    }
}
