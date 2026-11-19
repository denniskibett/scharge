{{-- Settings › Website (WebsiteController). docs/COMPANY_WEBSITE.md --}}
@extends('layouts.app')
@section('title', 'Website')

@section('content')
@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $tone = ['draft' => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300', 'pending' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    'published' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'changes_pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'];
@endphp
<div class="space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      @section('page_title')
Website
@endsection
      <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ $company->name }}'s website, hosted by scharge @if ($brand?->domain)at <a class="font-medium text-brand-600 hover:underline dark:text-brand-400" href="{{ $brand->baseUrl() }}" target="_blank" rel="noopener">{{ $brand->domain }}</a>@else on your own address once it is set up @endif. Your changes go live after scharge approves them. Your tenants' portal is on the same address.</p>
    </div>
  </div>
  @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ $errors->first() }}</div>@endif
  @unless ($brand)<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">Set up your name, logo and colour first on <a class="font-medium underline" href="{{ route('brand.edit', $q) }}">White label & data</a>: the website uses them.</div>@endunless

  @if ($pages->isEmpty())
    <form method="POST" action="{{ route('website.start', $q) }}" class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
      @csrf
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">Start your website</h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Begin with scharge's template: Home, About us, Homes to rent (your vacant units, kept up to date automatically) and Contact. Edit the words and pictures, add your own pages.</p>
      <button class="{{ $primary }} mt-4">Start from the template</button>
    </form>
  @else
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400"><th class="p-3">Page</th><th class="p-3">Address</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          @foreach ($pages as $p)
            <tr>
              <td class="p-3 text-gray-800 dark:text-white/90">{{ $p->edit('title') ?? $p->title }}<span class="block text-xs text-gray-500">{{ \App\Models\CompanyPage::TEMPLATES[$p->template] ?? $p->template }}{{ $p->show_in_menu ? '' : ' · hidden from menu' }}</span>
                @if ($p->review_note)<span class="mt-1 block text-xs text-amber-700 dark:text-amber-300">scharge: {{ $p->review_note }}</span>@endif</td>
              <td class="p-3 font-mono text-xs text-gray-600 dark:text-gray-400">/{{ $p->slug }}</td>
              <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tone[$p->status] ?? '' }}">{{ \App\Models\CompanyPage::STATUSES[$p->status] ?? $p->status }}</span>
                @if ($p->draft && ! $p->submitted_at && $p->isLive())<span class="mt-1 block text-xs text-gray-500">Changes not sent yet</span>@endif</td>
              <td class="p-3 text-right"><a href="{{ route('website.edit', [$p] + $q) }}" class="{{ $outline }}">Edit</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <form method="POST" action="{{ route('website.store', $q) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
      @csrf
      <label class="text-sm text-gray-700 dark:text-gray-300">New page title<input name="title" required maxlength="160" class="{{ $field }} mt-1 w-64 max-w-full" placeholder="Our buildings"></label>
      <label class="text-sm text-gray-700 dark:text-gray-300">Address<input name="slug" required maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="{{ $field }} mt-1 w-48 max-w-full" placeholder="our-buildings"></label>
      <button class="{{ $outline }}">Add page</button>
    </form>
  @endif
</div>
@endsection
