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
        foreach ($definition['fields'] as $key => $field) {
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
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Keep internal keys (connection_id, connection_status) that are not form fields.
        $row->credentials = array_merge(array_diff_key($saved, $definition['fields']), $values);
        $row->updated_by = $request->user()->id;
        if (! $row->exists) {
            $row->enabled = true;
        }
        $row->save();

        return response()->json(['message' => "{$definition['name']} saved.", 'provider' => $this->state($provider, $row)]);
    }

    public function toggle(Request $request, string $provider)
    {
        $row = $this->row($request, $provider);
        $row->update(['enabled' => ! $row->enabled, 'updated_by' => $request->user()->id]);

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
        $this->row($request, $provider)->delete();

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
        $d = config("integrations.$key");

        return [
            'key' => $key,
            'name' => $d['name'],
            'category' => $d['category'],
            'description' => $d['description'],
            'color' => $d['color'],
            'scope' => $d['scope'],
            'fields' => collect($d['fields'])->map(fn ($f, $k) => $f + ['key' => $k, 'type' => $f['type'] ?? 'text'])->values(),
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
        return collect(config('integrations'))->filter(fn ($p) => $p['scope'] === ($companyId ? 'company' : 'platform'));
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
