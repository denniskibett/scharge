{{-- "Re-verify email" button: <x-reverify-email :user="$user" />. POST users.reverify-email, then refreshes the page. --}}
@props(['user', 'label' => 'Re-verify email'])
@if ($user && \Illuminate\Support\Facades\Schema::hasColumn('users', 'email_confirmed_at'))
<button type="button" {{ $attributes->merge(['class' => 'text-sm font-medium text-brand-500 hover:text-brand-600']) }}
        x-data="{ busy: false }" :disabled="busy"
        @click="if (!confirm(@js('Ask '.$user->name.' to confirm '.$user->email.' with a code at their next sign-in?'))) return;
                busy = true;
                fetch(@js(route('users.reverify-email', $user->id)), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } })
                  .then(r => r.json().then(d => ({ ok: r.ok, d })))
                  .then(({ ok, d }) => { window.dispatchEvent(new CustomEvent('toast', { detail: { type: ok ? 'success' : 'error', message: d.message } })); if (ok) (window.schargeRefresh || (() => location.reload()))(); })
                  .finally(() => busy = false)">
  {{ $label }}
</button>
@endif
