<?php

namespace App\Modules\CRM\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Country;
use App\Modules\CRM\Support\Normalizer;
use App\Modules\CRM\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The website's waitlist and "Book a demo" forms. */
class SiteFormController extends Controller
{
    public const PROJECT_TYPES = [
        'Apartments, off-plan',
        'Apartments, completed',
        'Plots on instalments',
        'Joint venture with a developer',
        'Commercial or mixed use',
        'Property management portfolio',
    ];

    public function __construct(private LeadService $leads)
    {
    }

    public function waitlist(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->isBot($request)) {
            return $this->alert($request, 'You are on the list.', 'We will message you as soon as scharge opens in your country.');
        }

        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['required', 'string', 'max:190'],
            'country' => ['required', 'string', 'max:2'],
            'country_other' => ['nullable', 'required_if:country,ZZ', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:190'],
        ], [
            'country_other.required_if' => 'Tell us which country.',
        ], [
            'contact' => 'phone or email',
        ]);

        $country = $this->country($input['country']);
        [$email, $phone] = $this->splitContact($input['contact'], $country);

        try {
            $result = $this->leads->joinWaitlist([
                'name' => $input['name'],
                'email' => $email,
                'phone' => $phone,
                'company' => $input['company'] ?? null,
                'country' => $country,
                'country_name' => $country ? null : trim($input['country_other'] ?? ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            return $this->failed($request, $e, 'waitlist');
        }

        $where = $result['entry']->country_label;

        return $result['already']
            ? $this->alert($request, "You're already on the {$where} list.", "We've updated your details and will message you as soon as scharge opens in {$where}.")
            // Greet with the name they typed, never the stored one: an email must not reveal who owns it.
            : $this->alert($request, 'You are on the list.', "Thanks {$this->firstName(Normalizer::personName($input['name']))}. We will message you as soon as scharge opens in {$where}.");
    }

    public function demo(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->isBot($request)) {
            return $this->alert($request, 'Thank you.', "We'll call to confirm a time within one working day.");
        }

        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['required', 'string', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'country' => ['required', 'string', 'max:2'],
            'project_type' => ['nullable', 'string', Rule::in(self::PROJECT_TYPES)],
            'units' => ['required', 'integer', 'min:1', 'max:1000000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'units' => 'units or plots',
            'project_type' => 'project',
        ]);

        $country = $this->country($input['country']);
        if (! Normalizer::phone($input['phone'], $country?->dial_code ?? '254')) {
            throw ValidationException::withMessages(['phone' => 'Enter a phone number we can call, e.g. 0712 345 678.']);
        }

        try {
            $result = $this->leads->bookDemo([
                'name' => $input['name'],
                'company' => $input['company'],
                'phone' => $input['phone'],
                'email' => $input['email'] ?? null,
                'country' => $country,
                'project_type' => $input['project_type'] ?? null,
                'units' => (int) $input['units'],
                'message' => $input['message'] ?? null,
                'source_page' => $request->headers->get('referer'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            return $this->failed($request, $e, 'demo');
        }

        return $result['already']
            ? $this->alert($request, 'We already have your request.', "We've added the new details. Our projects team will call to confirm a time within one working day.")
            : $this->alert($request, 'Thank you, your meeting is booked.', "We'll call {$this->firstName(Normalizer::personName($input['name']))} to confirm a time within one working day.");
    }

    /** "ZZ" = "Another country". */
    private function country(string $iso2): ?Country
    {
        if (strtoupper($iso2) === 'ZZ') {
            return null;
        }
        $country = Country::where('iso2', strtoupper($iso2))->where('is_active', true)->first();
        if (! $country) {
            throw ValidationException::withMessages(['country' => 'Choose a country from the list.']);
        }

        return $country;
    }

    /** @return array{0: ?string, 1: ?string} [email, phone] */
    private function splitContact(string $contact, ?Country $country): array
    {
        if (str_contains($contact, '@')) {
            if ($email = Normalizer::email($contact)) {
                return [$email, null];
            }
            throw ValidationException::withMessages(['contact' => 'That email address does not look right.']);
        }
        if (Normalizer::phone($contact, $country?->dial_code ?? '254')) {
            // Pass it on as typed: ContactService needs to know whether it had a country code.
            return [null, $contact];
        }
        throw ValidationException::withMessages(['contact' => 'Enter a phone number (e.g. 0712 345 678) or an email address.']);
    }

    /** Hidden "website" field: people never see it, bots fill it in. */
    private function isBot(Request $request): bool
    {
        return filled($request->input('website'));
    }

    private function firstName(?string $name): string
    {
        return strtok((string) $name, ' ') ?: 'you';
    }

    private function alert(Request $request, string $title, string $message, string $type = 'success', int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['type' => $type, 'title' => $title, 'message' => $message], $status);
        }

        return back()->with('site_alert', compact('type', 'title', 'message'));
    }

    private function failed(Request $request, \Throwable $e, string $form): JsonResponse|RedirectResponse
    {
        Log::error("Website {$form} form failed", ['error' => $e->getMessage()]);

        return $this->alert($request, 'Something went wrong.', 'We could not save your details. Please try again, or email hello@scharge.com.', 'error', 500);
    }
}
