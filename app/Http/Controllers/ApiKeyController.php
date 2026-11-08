<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Settings › API keys (same flow as Sharet Africa). Company admins see their company's keys;
 * sysadmins see every key. The full key is returned once, on create and on regenerate.
 */
class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('api_keys'), 503, 'Apply database/schema/2026_11_08_sharet_payments.sql first.');
        if ($request->wantsJson()) {
            return response()->json(['keys' => $this->visible($request)->with('user:id,name', 'company:id,name')->latest()->get()->map(fn ($k) => $this->present($k))->values()]);
        }

        return view('api-keys.index', ['isAdmin' => $request->user()->hasRole('sysadmin'), 'company' => $request->user()->company ?? null]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->company_id, 422, 'Keys belong to a company. Sign in as a company admin to create one.');
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'environment' => 'required|in:test,live',
            'scopes' => 'nullable|array',
            'scopes.*' => 'in:'.implode(',', ApiKey::SCOPES),
        ]);
        $data['scopes'] = array_values(array_unique(array_merge(['read'], $data['scopes'] ?? [])));
        $data['allowed_ips'] = $this->ips($request);

        $key = new ApiKey($data + ['user_id' => $request->user()->id, 'company_id' => $request->user()->company_id, 'enabled' => true]);
        $plain = $key->issue();
        $key->save();

        return response()->json(['message' => 'Key created. Copy it now: it will not be shown again.', 'plain' => $plain, 'key' => $this->present($key->load('user:id,name', 'company:id,name'))], 201);
    }

    public function settings(Request $request, int $id)
    {
        $key = $this->find($request, $id);
        $data = $request->validate(['scopes' => 'nullable|array', 'scopes.*' => 'in:'.implode(',', ApiKey::SCOPES)]);
        $key->update(['scopes' => array_values(array_unique(array_merge(['read'], $data['scopes'] ?? []))), 'allowed_ips' => $this->ips($request)]);

        return response()->json(['message' => 'Saved.', 'key' => $this->present($key->fresh(['user:id,name', 'company:id,name']))]);
    }

    public function toggle(Request $request, int $id)
    {
        $key = $this->find($request, $id);
        $key->update(['enabled' => ! $key->enabled]);

        return response()->json(['message' => $key->enabled ? 'Key enabled.' : 'Key disabled. Requests with it now get 401.', 'key' => $this->present($key)]);
    }

    public function rotate(Request $request, int $id)
    {
        $key = $this->find($request, $id);
        $plain = $key->issue();
        $key->last_used_at = null;
        $key->save();

        return response()->json(['message' => 'New key issued. The old one stopped working. Copy the new one now.', 'plain' => $plain, 'key' => $this->present($key)]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->find($request, $id)->delete();

        return response()->json(['message' => 'Key deleted.']);
    }

    private function ips(Request $request): array
    {
        $ips = ApiKey::parseIps($request->input('allowed_ips'));
        if ($ips === null) {
            throw ValidationException::withMessages(['allowed_ips' => 'Use IP addresses or ranges like 41.90.12.7 or 196.201.0.0/16, separated by commas.']);
        }

        return $ips;
    }

    private function visible(Request $request)
    {
        $user = $request->user();

        return ApiKey::query()->when(! $user->hasRole('sysadmin'), fn ($q) => $q->where('company_id', $user->company_id ?? 0));
    }

    private function find(Request $request, int $id): ApiKey
    {
        return $this->visible($request)->findOrFail($id);
    }

    private function present(ApiKey $k): array
    {
        return [
            'id' => $k->id,
            'name' => $k->name,
            'environment' => $k->environment,
            'masked' => $k->masked(),
            'scopes' => $k->scopes ?? ['read'],
            'allowed_ips' => $k->allowed_ips ?? [],
            'enabled' => $k->enabled,
            'owner' => $k->company?->name ?? $k->user?->name,
            'created_at' => $k->created_at?->timezone('Africa/Nairobi')->format('j M Y'),
            'last_used_at' => $k->last_used_at?->diffForHumans() ?? 'Never',
        ];
    }
}
