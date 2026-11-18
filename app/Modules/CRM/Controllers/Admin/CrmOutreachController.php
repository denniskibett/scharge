<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\CRM\Support\Playbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The CRM "Playbook" modal: how to approach a prospect, what to sell, a cold email signed by the account manager
 * (saved per company, editable, sent from scharge with Reply-To the manager), and a follow-up booked for them.
 */
class CrmOutreachController extends Controller
{
    public function __construct(private ContactService $contacts)
    {
    }

    /** Modal body (HTML fragment), loaded on demand from the pipeline list or the company page. */
    public function show(Request $request, Company $crmCompany): View
    {
        CrmAccess::authorizeCompany($crmCompany, $request->user());
        $company = $crmCompany->load('owner:id,name,email,phone');
        $manager = $company->owner ?? $request->user();

        return view('crm.playbook', [
            'company' => $company,
            'book' => Playbook::for($company),
            'draft' => Playbook::draft($company, $manager),
            'manager' => $manager,
            'hasSavedDraft' => (bool) $company->profile('email_body'),
            'doNotEmail' => (bool) $company->profile('do_not_email'),
            'pains' => Playbook::pains($company),
            'chosenPains' => array_filter(explode(',', (string) $company->profile('pains'))),
            'signals' => Playbook::signals($company),
            'demoUrl' => Playbook::demoUrl($company),
        ]);
    }

    /** The email exactly as it will arrive (scharge "Ink" design), using the saved draft or the playbook template. */
    public function preview(Request $request, Company $crmCompany): string
    {
        CrmAccess::authorizeCompany($crmCompany, $request->user());
        $manager = $crmCompany->owner ?? $request->user();
        $draft = Playbook::draft($crmCompany, $manager);

        return (new CrmOutreachEmail($draft['subject'], $draft['body'], $manager, $crmCompany))->render();
    }

    /** Research step: chosen pain points + what the account manager found -> a researched email draft. */
    public function compose(Request $request, Company $crmCompany): RedirectResponse
    {
        CrmAccess::authorizeCompany($crmCompany, $request->user());
        $input = $request->validate([
            'pains' => ['nullable', 'array'],
            'pains.*' => [\Illuminate\Validation\Rule::in(array_keys(Playbook::pains($crmCompany)))],
            'research' => ['nullable', 'string', 'max:600'],
        ]);
        $manager = $crmCompany->owner ?? $request->user();
        $draft = Playbook::compose($crmCompany, $manager, $input['pains'] ?? [], $input['research'] ?? null);
        $this->setProfile($crmCompany, [
            'research' => $input['research'] ?? null,
            'pains' => implode(',', $input['pains'] ?? []),
            'email_subject' => $draft['subject'],
            'email_body' => $draft['body'],
        ]);

        return back()->with('success', 'Email rewritten around their problems. Check it, then send.')
            ->with('open_playbook', route('crm.companies.playbook', $crmCompany));
    }

    /** Save (or reset) this company's email draft. */
    public function saveDraft(Request $request, Company $crmCompany): RedirectResponse
    {
        CrmAccess::authorizeCompany($crmCompany, $request->user());
        if ($request->boolean('reset')) {
            $this->setProfile($crmCompany, ['email_subject' => null, 'email_body' => null]);

            return back()->with('success', 'Email reset to the playbook template.')->with('open_playbook', route('crm.companies.playbook', $crmCompany));
        }
        $input = $this->validateBody($request, ['subject' => ['required', 'string', 'max:200']]);
        $this->setProfile($crmCompany, ['email_subject' => $input['subject'], 'email_body' => $input['body']]);

        return back()->with('success', 'Email draft saved for '.$crmCompany->name.'.')->with('open_playbook', route('crm.companies.playbook', $crmCompany));
    }

    /** Send the cold email, log it, and book the account manager's follow-up. */
    public function send(Request $request, Company $crmCompany): RedirectResponse
    {
        $company = $crmCompany;
        $user = $request->user();
        CrmAccess::authorizeCompany($company, $user);
        $input = $this->validateBody($request, [
            'to' => ['required', 'email:rfc', 'max:190'],
            'subject' => ['required', 'string', 'max:200'],
            'follow_up_days' => ['required', 'integer', 'between:1,30'],
        ]);
        if ($company->profile('do_not_email')) {
            return back()->withErrors(['to' => $company->name.' asked not to be emailed. Untick "Do not email" under Verify details only if that has changed.']);
        }

        // Whoever sends the first email takes an unowned prospect: the follow-up is theirs.
        if (! $company->owner_id) {
            $company->update(['owner_id' => $user->id]);
            $this->contacts->log($company, 'note', 'Owner: nobody → '.$user->name, 'Took the account when sending the first email.', [], null, $user->id);
        }
        $manager = $company->fresh('owner')->owner ?? $user;

        try {
            Mail::to($input['to'])->send(new CrmOutreachEmail($input['subject'], $input['body'], $manager, $company));
        } catch (\Throwable $e) {
            Log::warning('CRM outreach email failed', ['company_id' => $company->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['to' => 'The email could not be sent: '.$e->getMessage()]);
        }

        $company->followUps()->create([
            'assigned_to' => $manager->id, 'created_by' => $user->id, 'type' => 'email', 'status' => 'done',
            'title' => 'Cold email: '.$input['subject'], 'notes' => EmailBody::toText($input['body']), 'done_at' => now(),
            'details' => ['to' => $input['to'], 'sent_by' => $user->id, 'playbook' => Playbook::segmentOf($company)],
        ]);
        $due = now()->addWeekdays((int) $input['follow_up_days'])->setTime(10, 0);
        $company->followUps()->create([
            'assigned_to' => $manager->id, 'created_by' => $user->id, 'type' => 'call', 'status' => 'open',
            'title' => 'Follow up on the cold email to '.$company->name, 'due_at' => $due,
            'notes' => 'Emailed '.$input['to'].' on '.now()->format('j M').'. Call to book a demo; if no answer, send a short follow-up email.',
        ]);
        if ($company->crm_stage === 'new') {
            $this->contacts->setStage($company, 'contacted', $user->id);
        }

        $profile = ['email_subject' => $input['subject'], 'email_body' => $input['body'], 'last_emailed_at' => now()->toDateTimeString()];
        if (! $company->email) {
            $company->update(['email' => $input['to']]);
        }
        $this->setProfile($company, $profile);

        return back()->with('success', 'Email sent to '.$input['to'].'. '.$manager->name.' has a follow-up on '.$due->format('D j M').'.');
    }

    /** Validate the rest, plus the rich-text body: cleaned to the allowed HTML, and not empty once cleaned. */
    private function validateBody(Request $request, array $rules): array
    {
        $input = $request->validate($rules + ['body' => ['required', 'string', 'max:30000']]);
        $input['body'] = EmailBody::toHtml($input['body']);
        if (trim(EmailBody::toText($input['body'])) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['body' => 'Write the email before saving or sending it.']);
        }

        return $input;
    }

    private function setProfile(Company $company, array $values): void
    {
        $profile = array_merge($company->crm_profile ?? [], $values);
        $company->update(['crm_profile' => array_filter($profile, fn ($v) => $v !== null && $v !== '')]);
    }
}
