{{-- Settings › White label & data (BrandController). docs/WHITE_LABEL.md --}}
@extends('layouts.app')
@section('title', 'White label & data')

@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
  $q = $isStaff ? ['company' => $company->id] : [];
  $files = ['logo' => ['Logo (light backgrounds)', $editBrand->logoUrl()], 'logo_dark' => ['Logo for dark backgrounds', $editBrand->logo_dark_path ? $editBrand->logoDarkUrl() : null], 'icon' => ['Square icon (browser tab)', $editBrand->iconUrl()]];
@endphp

@section('content')
<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">White label & data</h1>
    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ $company->name }}: your name, logo and colour on the dashboard, sign-in, tenant bills, PDFs and emails, and a copy of all your data whenever you want it.</p>
  </div>

  @if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ $errors->first() }}</div>
  @endif

  <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <form method="POST" action="{{ route('brand.update', $q) }}" enctype="multipart/form-data" class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 xl:col-span-2 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
      @csrf
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">Your brand</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label class="{{ $label }}">Name shown everywhere<input name="display_name" required maxlength="120" value="{{ old('display_name', $editBrand->display_name) }}" class="{{ $field }} mt-1.5"></label>
        <label class="{{ $label }}">Colour
          <span class="mt-1.5 flex gap-2"><input type="color" value="{{ old('primary_color', $editBrand->primary_color ?: '#465FFF') }}" oninput="this.nextElementSibling.value=this.value" class="h-11 w-14 rounded-lg border border-gray-300 dark:border-gray-700" aria-label="Pick colour">
          <input name="primary_color" required pattern="#[0-9a-fA-F]{6}" value="{{ old('primary_color', $editBrand->primary_color ?: '#465FFF') }}" oninput="this.previousElementSibling.value=this.value" class="{{ $field }}"></span></label>
        <label class="{{ $label }} sm:col-span-2">Line on the sign-in page<input name="tagline" maxlength="160" value="{{ old('tagline', $editBrand->tagline) }}" placeholder="e.g. Your home, managed well." class="{{ $field }} mt-1.5"></label>
        <label class="{{ $label }}">Support phone (on bills)<input name="support_phone" maxlength="40" value="{{ old('support_phone', $editBrand->support_phone) }}" class="{{ $field }} mt-1.5"></label>
        <label class="{{ $label }}">Support email (on bills)<input type="email" name="support_email" maxlength="120" value="{{ old('support_email', $editBrand->support_email) }}" class="{{ $field }} mt-1.5"></label>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($files as $key => [$text, $url])
          <div>
            <span class="{{ $label }}">{{ $text }}</span>
            <div @class(['mt-1.5 flex h-20 items-center justify-center rounded-lg border border-dashed border-gray-300 p-2 dark:border-gray-700', 'bg-gray-900' => $key === 'logo_dark'])>
              @if ($url)<img src="{{ $url }}" alt="" class="max-h-16 max-w-full object-contain">@else<span class="text-xs text-gray-400">None yet</span>@endif
            </div>
            <input type="file" name="{{ $key }}" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-xs text-gray-600 dark:text-gray-400">
            @if ($url)<label class="mt-1 flex items-center gap-1.5 text-xs text-gray-500"><input type="checkbox" name="remove[]" value="{{ $key }}"> Remove</label>@endif
          </div>
        @endforeach
      </div>
      <p class="text-xs text-gray-500">PNG, JPEG or WebP, up to 2 MB. A PNG logo also goes on PDF bills and receipts.</p>

      <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Your own web address</h3>
        @if ($isStaff)
          <label class="{{ $label }} mt-3">Address (set up by scharge)<input name="domain" maxlength="191" value="{{ old('domain', $editBrand->domain) }}" placeholder="portal.danaff.co.ke" class="{{ $field }} mt-1.5"></label>
          <label class="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="hidden" name="show_powered_by" value="0"><input type="checkbox" name="show_powered_by" value="1" @checked(old('show_powered_by', $editBrand->exists ? $editBrand->show_powered_by : true))> Show "Powered by scharge"</label>
          <p class="mt-2 text-xs text-gray-500">Before saving an address: the company points it (CNAME) at scharge.sharet.africa, and the address is added to the scharge site on the server with a certificate (docs/WHITE_LABEL.md).</p>
        @else
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            @if ($editBrand->domain)Your team and tenants use <strong>https://{{ $editBrand->domain }}</strong>.@else Want <strong>portal.yourcompany.co.ke</strong> instead of scharge's address? Ask scharge to set it up.@endif
          </p>
        @endif
      </div>

      <div class="flex flex-wrap gap-3"><button class="{{ $primary }}">Save</button></div>
    </form>

    <div class="space-y-6">
      <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Your data</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Everything, from the first day: one spreadsheet per kind of record, in a zip.</p>
        <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
          @foreach (['units' => 'Units', 'tenants' => 'Tenants', 'tenancies' => 'Tenancies', 'invoices' => 'Bills', 'payments' => 'Payments', 'water_readings' => 'Readings'] as $k => $l)
            <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5"><dt class="text-xs text-gray-500">{{ $l }}</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ number_format($counts[$k] ?? 0) }}</dd></div>
          @endforeach
        </dl>
        <a href="{{ route('brand.export', $q) }}" class="{{ $outline }} mt-4 w-full">Download all data</a>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Nightly copy</h2>
        @if ($backup?->enabled)
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Every night at 02:30 to your {{ $backup->get('method') === 's3' ? 'bucket' : 'SFTP server' }}.</p>
          @if ($backup->last_test_message)<p @class(['mt-2 text-xs', 'text-emerald-700 dark:text-emerald-300' => $backup->last_test_ok, 'text-red-600 dark:text-red-400' => ! $backup->last_test_ok])>{{ $backup->last_test_message }}</p>@endif
        @else
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Not set up. Keep your own copy of everything, every night, on storage you control.</p>
        @endif
        <a href="{{ route('integrations.index') }}" class="{{ $outline }} mt-4 w-full">{{ $backup ? 'Change' : 'Set up' }} in Integrations</a>
      </div>
    </div>
  </div>
</div>
@endsection
