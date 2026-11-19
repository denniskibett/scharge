<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Services\Integrations;
use App\Services\Sharet\SharetClient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Settings › Integrations (pattern from Sharet Africa's Integrations page).
 * Sysadmins manage scharge's platform providers (?company=platform) or any company (?company=<id>);
 * company admins only their own company. Secret fields are write-only.
 */
class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Integrations::installed(), 503, 'Apply database/schema/2026_11_08_sharet_payments.sql first.');
        $companyId = $this->companyId($request);
        $rows = CompanyIntegration::where('provider', '!=', '')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId), fn ($q) => $q->whereNull('company_id'))
            ->get()->keyBy('provider');

        $providers = $this->definitions($companyId)->map(fn ($p, $key) => $this->state($key, $rows->get($key)));
        if ($request->wantsJson()) {
            return response()->json(['providers' => $providers->values()]);
        }

        $isAdmin = $request->user()->hasRole('sysadmin');

        return view('integrations.index', [
            'scope' => $companyId ? Company::find($companyId) : null,
            'companies' => $isAdmin ? Company::where(fn ($q) => $q->whereNull('lifecycle')->orWhere('lifecycle', '!=', 'prospect'))->orderBy('name')->get(['id', 'name']) : collect(),
            'isAdmin' => $isAdmin,
            'flash' => session('integration_flash'),
        ]);
    }

    public function update(Request $request, string $provider)
    {
        $companyId = $this->companyId($request);
        $definition = $this->definition($provider, $companyId);
        $row = $this->rowOrNew($companyId, $provider);
        $saved = $row->credentials ?? [];
        $input = (array) $request->input('values', []);

        $values = [];
        $errors = [];
        $isAdmin = $request->user()->hasRole('sysadmin');
        foreach ($definition['fields'] as $key => $field) {
            if (! empty($field['admin_only']) && ! $isAdmin) {
                $values[$key] = $saved[$key] ?? null;   // scharge staff set these

                continue;
            }
            $value = is_string($input[$key] ?? null) ? trim($input[$key]) : ($input[$key] ?? null);
            if (! empty($field['secret']) && blank($value)) {
                $value = $saved[$key] ?? null;   // keep the stored secret
            }
            if (! empty($field['required']) && blank($value)) {
                $errors["values.$key"] = "{$field['label']} is required.";
            }
            if (($field['type'] ?? '') === 'select' && filled($value) && ! array_key_exists($value, $field['options'])) {
                $errors["values.$key"] = "Choose a valid {$field['label']}.";
            }
            if (($field['type'] ?? '') === 'url' && filled($value) && ! filter_var($value, FILTER_VALIDATE_URL)) {
                $errors["values.$key"] = "{$field['label']} must be a full web address.";
            }
            $values[$key] = $value === '' ? null : $value;
        }
        if ($provider === 'sharet' && filled($values['api_key'] ?? null) && ! preg_match('/^sk_(test|live)_/', $values['api_key'])) {
            $errors['values.api_key'] = 'A Sharet key starts with sk_test_ or sk_live_.';
        }
        [$values, $internal, $reveal] = $this->prepare($provider, $companyId, $values, $saved, $errors, $isAdmin);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Keep internal keys (connection_id, connection_status, tokens) that are not form fields.
        $row->credentials = array_merge(array_diff_key($saved, $definition['fields']), $internal, $values);
        $row->updated_by = $request->user()->id;
        if (! $row->exists) {
            $row->enabled = true;
        }
        $row->save();
        Integrations::forget($companyId, $provider);

        return response()->json(['message' => "{$definition['name']} saved.", 'provider' => $this->state($provider, $row), 'reveal' => $reveal]);
    }

    /**
     * Per-provider checks and generated values on Save.
     *
     * @return array{0: array, 1: array, 2: ?array} form values, internal values, a one-time value to show
     */
    protected function prepare(string $provider, ?int $companyId, array $values, array $saved, array &$errors, bool $isAdmin): array
    {
        $internal = [];
        $reveal = null;
        switch ($provider) {
            case 'webhooks':
                if (filled($values['url'] ?? null) && ! \App\Services\Connectors\Webhooks::safeUrl($values['url'])) {
                    $errors['values.url'] = 'Use a public https:// address.';
                }
                break;
            case 'company_email':
                foreach (['from_address' => 'Send from', 'reply_to' => 'Replies go to'] as $k => $label) {
                    if (filled($values[$k] ?? null) && ! filter_var($values[$k], FILTER_VALIDATE_EMAIL)) {
                        $errors["values.$k"] = "$label must be an email address.";
                    }
                }
                foreach (['smtp' => ['host' => 'SMTP server'], 'mailgun' => ['mailgun_domain' => 'Mailgun domain', 'mailgun_key' => 'Mailgun API key']][$values['provider'] ?? ''] ?? [] as $k => $label) {
                    if (blank($values[$k] ?? null)) {
                        $errors["values.$k"] = "$label is required.";
                    }
                }
                break;
            case 'etims':
                $values['tin'] = strtoupper((string) ($values['tin'] ?? ''));
                if (! preg_match('/^[AP]\d{9}[A-Z]$/', $values['tin'])) {
                    $errors['values.tin'] = 'A KRA PIN looks like P051234567X.';
                }
                if (($saved['tin'] ?? null) !== $values['tin'] || ($saved['device_serial'] ?? null) !== ($values['device_serial'] ?? null) || ($saved['environment'] ?? null) !== ($values['environment'] ?? null)) {
                    $internal = ['cmc_key' => null, 'item_code' => null];   // a new device: Test registers it again
                }
                break;
            case 'data_feeds':
                if (blank($saved['feed_token'] ?? null) || ($values['reset'] ?? 'no') === 'yes') {
                    $internal['feed_token'] = \Illuminate\Support\Str::random(40);
                }
                $values['reset'] = 'no';
                break;
            case 'smart_meters':
                if (blank($values['gateway_key'] ?? null) && $companyId) {
                    $values['gateway_key'] = 'mgw_'.$companyId.'_'.\Illuminate\Support\Str::random(32);
                    $reveal = ['label' => 'Gateway key (copy it now: it is not shown again)', 'value' => $values['gateway_key']];
                }
                break;
            case 'kenyasms_sender':
            case 'whatsapp_business':
                $managed = ($values['setup'] ?? $values['mode'] ?? null) === 'managed';
                if ($managed && ! $isAdmin && blank($values['approval'] ?? null)) {
                    $values['approval'] = 'requested';
                    $this->tellStaff($provider, $companyId, $values);
                }
                if ($provider === 'kenyasms_sender' && filled($values['sender_id'] ?? null) && ! preg_match('/^[A-Za-z0-9 ._-]{1,11}$/', $values['sender_id'])) {
                    $errors['values.sender_id'] = 'Up to 11 letters or numbers.';
                }
                break;
        }

        return [$values, $internal, $reveal];
    }

    /** A company asked scharge to register its SMS sender ID or WhatsApp number: email scharge's admins. */
    protected function tellStaff(string $provider, ?int $companyId, array $values): void
    {
        $company = Company::find($companyId)?->name ?? "company {$companyId}";
        $what = $provider === 'kenyasms_sender' ? "SMS sender ID \"{$values['sender_id']}\"" : 'WhatsApp Business number '.($values['number_to_use'] ?? '').' as "'.($values['display_name'] ?? '').'"';
        $link = route('integrations.index', ['company' => $companyId]);
        dispatch(function () use ($company, $what, $link) {
            $to = \App\Models\User::role('sysadmin')->pluck('email')->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
            if ($to && \App\Services\EmailOtp::mailWorks()) {
                rescue(fn () => \Illuminate\Support\Facades\Mail::raw("{$company} asked scharge to set up their {$what}.\n\nRegister it, then set the status on their card: {$link}",
                    fn ($m) => $m->to($to)->subject("Set-up request: {$company}")));
            }
        })->afterResponse();
    }

    /** Start "Connect" for an accounting system: off to its sign-in page. */
    public function oauthStart(Request $request, string $provider)
    {
        $companyId = $this->companyId($request);
        abort_unless($companyId && isset(\App\Services\Connectors\Accounting::PROVIDERS[$provider]), 404);
        if (! \App\Services\Connectors\Accounting::app($provider)) {
            return redirect()->route('integrations.index', ['company' => $companyId])
                ->with('integration_flash', 'scharge has not set up its '.\App\Services\Connectors\Accounting::label($provider).' app yet. Ask scharge support.');
        }
        $state = encrypt(['c' => $companyId, 'p' => $provider, 'u' => $request->user()->id, 't' => time()]);

        return redirect()->away(\App\Services\Connectors\Accounting::authorizeUrl($provider, $state));
    }

    public function oauthCallback(Request $request, string $provider)
    {
        try {
            $state = decrypt((string) $request->query('state'));
        } catch (\Throwable) {
            abort(400, 'This link has expired. Click Connect again.');
        }
        abort_unless(($state['p'] ?? null) === $provider && ($state['u'] ?? null) === $request->user()->id && time() - ($state['t'] ?? 0) < 900, 400, 'This link has expired. Click Connect again.');
        $companyId = (int) $state['c'];
        if (! $request->filled('code')) {
            return redirect()->route('integrations.index', ['company' => $companyId])->with('integration_flash', 'Not connected: '.($request->query('error_description') ?? $request->query('error') ?? 'cancelled').'.');
        }
        $row = $this->rowOrNew($companyId, $provider);
        $row->updated_by = $request->user()->id;
        [$ok, $message] = \App\Services\Connectors\Accounting::connect($provider, $row, (string) $request->query('code'), $request->query());
        Integrations::forget($companyId, $provider);
        if ($row->exists) {
            $row->forceFill(['last_tested_at' => now(), 'last_test_ok' => $ok, 'last_test_message' => mb_substr($message, 0, 250)])->save();
        }

        return redirect()->route('integrations.index', ['company' => $companyId])->with('integration_flash', $message);
    }

    public function toggle(Request $request, string $provider)
    {
        $row = $this->row($request, $provider);
        $row->update(['enabled' => ! $row->enabled, 'updated_by' => $request->user()->id]);
        Integrations::forget($row->company_id, $provider);

        return response()->json(['message' => $row->enabled ? 'Turned on.' : 'Turned off.', 'provider' => $this->state($provider, $row)]);
    }

    public function test(Request $request, string $provider)
    {
        $row = $this->row($request, $provider);
        [$ok, $message] = Integrations::test($row);

        return response()->json(['ok' => $ok, 'message' => $message, 'provider' => $this->state($provider, $row->fresh())], $ok ? 200 : 422);
    }

    public function destroy(Request $request, string $provider)
    {
        $row = $this->row($request, $provider);
        $row->delete();
        Integrations::forget($row->company_id, $provider);

        return response()->json(['message' => 'Removed. Saved credentials were deleted.', 'provider' => $this->state($provider, null)]);
    }

    /** Start connecting a landlord's own Sharet account: returns the Sharet approval page URL. */
    public function connect(Request $request)
    {
        $companyId = $this->companyId($request);
        abort_unless($companyId, 422, 'Choose a company first.');
        $platform = Integrations::sharet();
        if (! $platform) {
            return response()->json(['message' => 'scharge has not connected its Sharet account yet. Ask scharge support.'], 422);
        }

        $res = SharetClient::fromIntegration($platform)->post('/connect/links', [
            'scopes' => ['collect'],
            'reference' => "scharge-company-{$companyId}",
            'return_url' => route('integrations.connected', ['company' => $companyId]),
        ], "scharge-connect-{$companyId}-".now()->format('YmdHi'));

        if (! $res->successful() || ! $res->json('url')) {
            return response()->json(['message' => 'Sharet could not start the connection: '.($res->json('detail') ?? $res->status())], 422);
        }

        $row = $this->rowOrNew($companyId, 'sharet_payouts');
        $row->mergeCredentials(['connection_id' => $res->json('id'), 'connection_status' => 'pending', 'mode' => $row->get('mode', 'rail')]);
        $row->enabled = true;
        $row->updated_by = $request->user()->id;
        $row->save();

        return response()->json(['url' => $res->json('url')]);
    }

    /** Sharet sends the landlord back here after they approve (or decline) the connection. */
    public function connected(Request $request)
    {
        $companyId = $this->companyId($request);
        $row = $companyId ? CompanyIntegration::where('company_id', $companyId)->where('provider', 'sharet_payouts')->first() : null;
        $platform = Integrations::sharet();
        $message = 'The Sharet connection was not completed.';

        if ($row && $platform && $request->query('status') === 'active') {
            $connectionId = $request->query('connection', $row->get('connection_id'));
            $list = SharetClient::fromIntegration($platform)->get('/connect/accounts')->json();
            $match = collect($list['data'] ?? $list ?? [])->first(fn ($c) => is_array($c) && ($c['id'] ?? null) === $connectionId);
            $sharetId = $match['account']['sharet_id'] ?? $match['sharet_id'] ?? null;

            if ($sharetId) {
                $row->mergeCredentials(['sharet_account' => $sharetId, 'connection_id' => $connectionId, 'connection_status' => 'active', 'mode' => 'connected']);
                $row->save();
                $message = "Connected. Rent now goes straight into Sharet account {$sharetId}.";
            }
        }

        return redirect()->route('integrations.index', ['company' => $companyId])->with('integration_flash', $message);
    }

    /** What the page needs about one provider; secrets only as "saved". */
    protected function state(string $key, ?CompanyIntegration $row): array
    {
        $d = self::withOptions(config("integrations.$key"));

        return [
            'key' => $key,
            'name' => $d['name'],
            'category' => $d['category'],
            'description' => $d['description'],
            'color' => $d['color'],
            'logo' => isset($d['logo']) && is_file(public_path('images/integrations/'.$d['logo'])) ? '/images/integrations/'.$d['logo'] : null,
            'scope' => $d['scope'],
            'fields' => collect($d['fields'])->reject(fn ($f) => ! empty($f['admin_only']) && ! auth()->user()?->hasRole('sysadmin'))
                ->map(fn ($f, $k) => $f + ['key' => $k, 'type' => $f['type'] ?? 'text'])->values(),
            'oauth' => ! empty($d['oauth']),
            'link' => isset($d['link']) ? ['label' => $d['link']['label'], 'url' => route($d['link']['route'], array_filter(['company' => $row?->company_id ?? request('company')]))] : null,
            'extras' => $row ? Integrations::extras($row) : [],
            'connected' => (bool) $row?->exists,
            'enabled' => (bool) $row?->enabled,
            'values' => $row?->publicValues() ?? [],
            'saved_secrets' => $row?->savedSecrets() ?? [],
            'connection_status' => $row?->get('connection_status'),
            'last_tested_at' => $row?->last_tested_at?->diffForHumans(),
            'last_test_ok' => $row?->last_test_ok,
            'last_test_message' => $row?->last_test_message,
            'updated_at' => $row?->updated_at?->timezone('Africa/Nairobi')->format('j M Y, H:i'),
        ];
    }

    /** Providers for this scope: platform ones for scharge itself, company ones for a company. */
    protected function definitions(?int $companyId)
    {
        return collect(config('integrations'))->filter(fn ($p) => $p['scope'] === ($companyId ? 'company' : 'platform'))->map(fn ($p) => self::withOptions($p));
    }

    /** Fields with 'options_from' => 'banks' get the Kenyan bank list (resources/data/banks-kenya.txt). */
    protected static function withOptions(array $d): array
    {
        foreach ($d['fields'] ?? [] as $k => $f) {
            if (($f['options_from'] ?? null) === 'banks') {
                $d['fields'][$k]['options'] = collect(\App\Support\DataList::get('banks-kenya'))
                    ->mapWithKeys(fn ($b) => [(\App\Support\Partners::forBank($b['name']) ?? \Illuminate\Support\Str::slug($b['name'])) => $b['name']])
                    ->all();
            }
        }

        return $d;
    }

    protected function definition(string $provider, ?int $companyId): array
    {
        return $this->definitions($companyId)->get($provider) ?? abort(404);
    }

    protected function rowOrNew(?int $companyId, string $provider): CompanyIntegration
    {
        return CompanyIntegration::where('provider', $provider)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId), fn ($q) => $q->whereNull('company_id'))
            ->first() ?? new CompanyIntegration(['company_id' => $companyId, 'provider' => $provider]);
    }

    protected function row(Request $request, string $provider): CompanyIntegration
    {
        $companyId = $this->companyId($request);
        $this->definition($provider, $companyId);
        $row = $this->rowOrNew($companyId, $provider);
        abort_unless($row->exists, 404);

        return $row;
    }

    protected function companyId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->hasRole('sysadmin')) {
            $company = $request->input('company');

            return blank($company) || $company === 'platform' ? null : Company::findOrFail((int) $company)->id;
        }

        abort_unless($user->company_id, 403, 'Your account is not linked to a company yet.');

        return (int) $user->company_id;
    }
}
