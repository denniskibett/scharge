<?php

namespace App\Modules\CRM\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Support\Countries;
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
            'country' => ['required', 'string', 'size:2', Rule::in(array_keys(Countries::waitlist()))],
            'company' => ['nullable', 'string', 'max:190'],
        ], [
            'country.in' => 'Choose a country from the list.',
        ], [
            'contact' => 'phone or email',
        ]);

        $code = strtoupper($input['country']);
        [$email, $phone] = $this->splitContact($input['contact'], $code);

        try {
            $result = $this->leads->joinWaitlist([
                'name' => $input['name'],
                'email' => $email,
                'phone' => $phone,
                'company' => $input['company'] ?? null,
                'country_code' => $code,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            return $this->failed($request, $e, 'waitlist');
        }

        $where = Countries::name($code);

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
            'country' => ['required', 'string', 'size:2', Rule::in(array_keys(Countries::africa()))],
            'project_type' => ['nullable', 'string', Rule::in(self::PROJECT_TYPES)],
            'units' => ['required', 'integer', 'min:1', 'max:1000000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], ['country.in' => 'Choose a country from the list.'], [
            'units' => 'units or plots',
            'project_type' => 'project',
        ]);

        $code = strtoupper($input['country']);
        if (! Normalizer::phone($input['phone'], Countries::dial($code))) {
            throw ValidationException::withMessages(['phone' => 'Enter a phone number we can call, e.g. 0712 345 678.']);
        }

        try {
            $result = $this->leads->bookDemo([
                'name' => $input['name'],
                'company' => $input['company'],
                'phone' => $input['phone'],
                'email' => $input['email'] ?? null,
                'country_code' => $code,
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

        if ($result['waitlisted']) {
            $where = Countries::name($code);

            return $this->alert($request, "Thanks, we have your request for {$where}.", "scharge isn't live in {$where} yet. You're on our list there with your project details, and we'll set up your demo as soon as we launch.");
        }

        return $result['already']
            ? $this->alert($request, 'We already have your request.', "We've added the new details. Our projects team will call to confirm a time within one working day.")
            : $this->alert($request, 'Thank you, your meeting is booked.', "We'll call {$this->firstName(Normalizer::personName($input['name']))} to confirm a time within one working day.");
    }

    public const API_USES = [
        'Vacancies on my website',
        'Booking calendar for my short stays',
        'Branded tenant or buyer app',
        'Connect to my accounting or CRM',
    ];

    /** "Get sandbox keys" on the API page. */
    public function apiKeys(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->isBot($request)) {
            return $this->alert($request, 'Check your inbox.', 'Your sandbox keys and a link to the docs are on their way.');
        }

        $input = $request->validate([
            'company' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'use' => ['nullable', 'string', Rule::in(self::API_USES)],
        ], [], ['company' => 'company or property name', 'email' => 'work email']);

        try {
            $result = $this->leads->requestApiKeys($input + ['source_page' => $request->headers->get('referer')]);
        } catch (\Throwable $e) {
            return $this->failed($request, $e, 'API keys');
        }

        return $result['already']
            ? $this->alert($request, 'We already have your request.', 'Our team will email your sandbox keys within one working day.')
            : $this->alert($request, 'Request received.', 'Our team will email your sandbox keys and a link to the docs within one working day.');
    }

    /** Viewing / booking / site-visit request from the Open listings detail panel. */
    public function listingRequest(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->isBot($request)) {
            return $this->alert($request, 'Request sent.', 'The owner or caretaker will reply on WhatsApp.');
        }

        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'when' => ['nullable', 'string', 'max:120'],
            'listing_title' => ['required', 'string', 'max:190'],
            'listing_area' => ['nullable', 'string', 'max:120'],
            'listing_owner' => ['nullable', 'string', 'max:190'],
            'listing_kind' => ['nullable', 'string', 'max:60'],
            'listing_action' => ['nullable', 'string', 'max:60'],
        ], ['listing_title.required' => 'Choose a listing first.'], ['phone' => 'phone (WhatsApp)']);
        $this->assertPhone($input['phone'], 'phone');

        try {
            $result = $this->leads->requestListing([
                'name' => $input['name'],
                'phone' => $input['phone'],
                'when' => $input['when'] ?? null,
                'listing' => [
                    'title' => $input['listing_title'],
                    'area' => $input['listing_area'] ?? null,
                    'owner' => $input['listing_owner'] ?? null,
                    'kind' => $input['listing_kind'] ?? null,
                    'action' => $input['listing_action'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->failed($request, $e, 'listing request');
        }

        return $result['already']
            ? $this->alert($request, 'We already have your request.', "We've updated it. The owner or caretaker will reply on WhatsApp.")
            : $this->alert($request, 'Request sent.', 'The owner or caretaker will reply on WhatsApp, usually the same day.');
    }

    /** "SMS me new matches" on Open listings. */
    public function listingAlert(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->isBot($request)) {
            return $this->alert($request, 'Alert set.', "We'll SMS you when a new match is listed.");
        }

        $input = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'search_kind' => ['nullable', 'string', 'max:60'],
            'search_area' => ['nullable', 'string', 'max:120'],
            'search_max' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->assertPhone($input['phone'], 'phone');

        try {
            $this->leads->listingAlert([
                'phone' => $input['phone'],
                'search' => ['kind' => $input['search_kind'] ?? null, 'area' => $input['search_area'] ?? null, 'max' => $input['search_max'] ?? null],
            ]);
        } catch (\Throwable $e) {
            return $this->failed($request, $e, 'listing alert');
        }

        return $this->alert($request, 'Alert set.', "We'll SMS you when a new match is listed. Reply STOP to any message to cancel.");
    }

    private function assertPhone(string $phone, string $field): void
    {
        if (! Normalizer::phone($phone)) {
            throw ValidationException::withMessages([$field => 'Enter a phone number we can reach, e.g. 0712 345 678.']);
        }
    }

    /** @return array{0: ?string, 1: ?string} [email, phone] */
    private function splitContact(string $contact, string $code): array
    {
        if (str_contains($contact, '@')) {
            if ($email = Normalizer::email($contact)) {
                return [$email, null];
            }
            throw ValidationException::withMessages(['contact' => 'That email address does not look right.']);
        }
        if (Normalizer::phone($contact, Countries::dial($code))) {
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
