<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Notifications\TeamInvitation;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Team & access (pattern from Brevemaster): a company admin invites their employees, gives each a
 * role and can switch a login off. Invitees get an email (and an SMS when SMS is set up) with a link
 * to set their own password. Sysadmins pick a company (?company=<id>) or the scharge team (?company=staff).
 */
class TeamController extends Controller
{
    public function index(Request $request)
    {
        [$companyId, $staffTeam] = $this->scope($request);
        $members = User::with('roles:id,name')
            ->when($staffTeam, fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['sysadmin', 'account_manager'])),
                fn ($q) => $q->where('company_id', $companyId)->whereDoesntHave('roles', fn ($r) => $r->where('name', 'tenant')))
            ->when($request->query('role'), fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->orderBy('name')->get();

        // Invited = their set-your-password link is still unused.
        $invited = \Illuminate\Support\Facades\DB::table('password_reset_tokens')->whereIn('email', $members->pluck('email'))->pluck('email')->flip();

        return view('team.index', [
            'members' => $members,
            'invited' => $invited,
            'roles' => $this->assignableRoles($request, $staffTeam),
            'matrix' => $this->matrix(),
            'scopeName' => $staffTeam ? 'the scharge team' : (Company::find($companyId)?->name ?? 'your company'),
            'companies' => $request->user()->hasRole('sysadmin') ? Company::where(fn ($q) => $q->whereNull('lifecycle')->orWhere('lifecycle', '!=', 'prospect'))->orderBy('name')->get(['id', 'name']) : collect(),
            'scopeParam' => $staffTeam ? 'staff' : $companyId,
            'filterRole' => $request->query('role'),
        ]);
    }

    public function store(Request $request)
    {
        [$companyId, $staffTeam] = $this->scope($request);
        $roles = $this->assignableRoles($request, $staffTeam);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email'), new \App\Rules\RealEmail],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(array_keys($roles))],
        ], ['email.unique' => 'This email already has a scharge login. Use another email, or ask scharge support to move it.']);

        $user = User::forceCreate([
            'name' => $data['name'], 'email' => Str::lower($data['email']), 'phone' => $data['phone'] ?? null,
            'company_id' => $staffTeam ? null : $companyId, 'password' => bcrypt(Str::password(32)),
            'status' => 0, 'email_verified_at' => now(),   // they prove the address by using the link
        ]);
        $user->syncRoles([$data['role']]);

        $url = route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]);
        $sent = 'email';
        try {
            $user->notify(new TeamInvitation($staffTeam ? 'scharge' : (Company::find($companyId)?->name ?? 'scharge'), $request->user()->name, $roles[$data['role']], $url));
        } catch (\Throwable $e) {
            report($e);
            $sent = null;
        }
        if (! empty($data['phone'])) {
            try {
                app(\App\Services\SmsService::class)->send($data['phone'], "{$request->user()->name} added you to scharge. Set your password: {$url}");
                $sent = $sent ? 'email and SMS' : 'SMS';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'message' => $sent ? "Invitation sent to {$user->name} by {$sent}." : "{$user->name} was added, but the invitation could not be sent. Copy the link below and share it.",
            'link' => $sent ? null : $url,
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        [$companyId, $staffTeam] = $this->scope($request);
        $this->ensureInScope($user, $companyId, $staffTeam);
        $roles = $this->assignableRoles($request, $staffTeam);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(array_keys($roles))],
            'active' => ['required', 'boolean'],
        ]);

        $wasAdmin = $user->hasRole('admin');
        if (! $staffTeam && $wasAdmin && ($data['role'] !== 'admin' || ! $data['active']) && $this->activeAdmins($companyId) <= 1) {
            return response()->json(['message' => 'A company needs at least one active admin. Make someone else an admin first.'], 422);
        }
        if ($user->is($request->user()) && ! $data['active']) {
            return response()->json(['message' => 'You cannot switch off your own login.'], 422);
        }

        $user->forceFill(['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'status' => $data['active'] ? 0 : 1])->save();
        $user->syncRoles([$data['role']]);

        return response()->json(['message' => "{$user->name} updated."]);
    }

    /** Switch the login off (the record stays: it is linked to readings, payments, notes). */
    public function destroy(Request $request, User $user)
    {
        [$companyId, $staffTeam] = $this->scope($request);
        $this->ensureInScope($user, $companyId, $staffTeam);
        abort_if($user->is($request->user()), 422, 'You cannot remove yourself.');
        if (! $staffTeam && $user->hasRole('admin') && $this->activeAdmins($companyId) <= 1) {
            return response()->json(['message' => 'You cannot remove the last admin.'], 422);
        }
        $user->forceFill(['status' => 1])->save();

        return response()->json(['message' => "{$user->name} can no longer sign in."]);
    }

    /** @return array{0: ?int, 1: bool} company id, and whether this is the scharge staff team */
    private function scope(Request $request): array
    {
        $user = $request->user();
        if ($user->hasRole('sysadmin')) {
            $c = $request->input('company');
            if ($c === 'staff' || blank($c) && ! $user->company_id) {
                return [null, true];
            }

            return [(int) ($c ?: $user->company_id), false];
        }
        abort_unless($user->company_id, 403, 'Your account is not linked to a company.');

        return [(int) $user->company_id, false];
    }

    private function assignableRoles(Request $request, bool $staffTeam): array
    {
        if ($staffTeam) {
            return ['account_manager' => 'Account manager: owns a book of companies, their leads and revenue', 'sysadmin' => 'System admin: everything'];
        }

        // Custom roles made on the Roles page can be given too.
        $custom = \Spatie\Permission\Models\Role::whereNotIn('name', Access::BUILT_IN)->pluck('name')
            ->mapWithKeys(fn ($n) => [$n => ucfirst(str_replace('_', ' ', $n)).' (custom)'])->all();

        return Access::COMPANY_ROLES + $custom;
    }

    private function ensureInScope(User $user, ?int $companyId, bool $staffTeam): void
    {
        $ok = $staffTeam ? $user->hasAnyRole(['sysadmin', 'account_manager']) : (int) $user->company_id === (int) $companyId;
        abort_unless($ok, 404);
    }

    private function activeAdmins(?int $companyId): int
    {
        return User::where('company_id', $companyId)->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 1))
            ->whereHas('roles', fn ($r) => $r->where('name', 'admin'))->count();
    }

    /** Area => role => full | edit | view | null, for the access table (from the live permissions). */
    private function matrix(): array
    {
        $roles = \Spatie\Permission\Models\Role::with('permissions:id,name')->whereIn('name', array_keys(Access::COMPANY_ROLES))->get()->keyBy('name');

        return collect(Access::AREAS)->mapWithKeys(fn ($label, $area) => [$label => collect(Access::COMPANY_ROLES)->mapWithKeys(function ($x, $role) use ($roles, $area) {
            $has = fn ($a) => $roles->get($role)?->permissions->contains('name', "{$area}.{$a}");

            return [$role => $has('delete') ? 'full' : ($has('manage') ? 'edit' : ($has('view') ? 'view' : null))];
        })->all()])->all();
    }
}
