{{-- Settings › Website › edit a page (WebsiteController). Changes are a draft until scharge approves. --}}
@extends('layouts.app')
@section('title', 'Edit page')

@section('content')
@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $field = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
  $hero = $page->edit('hero_image');
@endphp
<div class="space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <a href="{{ route('website.index', $q) }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">‹ Website</a>
      <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $page->edit('title') }}</h1>
      <p class="mt-1 text-sm text-gray-500">/{{ $page->slug }} · {{ \App\Models\CompanyPage::STATUSES[$page->status] ?? $page->status }}</p>
    </div>
    <a href="{{ route('website.preview', [$page] + $q) }}" target="_blank" rel="noopener" class="{{ $outline }}">Preview</a>
  </div>
  @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ $errors->first() }}</div>@endif
  @if ($page->review_note)<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"><strong>scharge asks:</strong> {{ $page->review_note }}</div>@endif

  <form method="POST" action="{{ route('website.update', [$page] + $q) }}" enctype="multipart/form-data" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
    @csrf
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <label class="{{ $label }}">Menu title<input name="title" required maxlength="160" value="{{ old('title', $page->edit('title')) }}" class="{{ $field }} mt-1.5"></label>
      <label class="{{ $label }}">Big heading<input name="hero_title" maxlength="190" value="{{ old('hero_title', $page->edit('hero_title')) }}" class="{{ $field }} mt-1.5"></label>
      <label class="{{ $label }} sm:col-span-2">Line under the heading<input name="hero_text" maxlength="500" value="{{ old('hero_text', $page->edit('hero_text')) }}" class="{{ $field }} mt-1.5"></label>
    </div>
    <div>
      <span class="{{ $label }}">Banner picture</span>
      @if ($hero)<img src="{{ $page->heroUrl($hero) }}" alt="" class="mt-1.5 max-h-40 rounded-lg border border-gray-200 dark:border-gray-800"><label class="mt-1 flex items-center gap-2 text-xs text-gray-500"><input type="checkbox" name="remove_hero" value="1"> Remove</label>@endif
      <input type="file" name="hero" accept="image/jpeg,image/png,image/webp" class="mt-1.5 block w-full text-sm text-gray-600 dark:text-gray-400">
    </div>
    @if ($page->template !== 'rentals')
      <label class="{{ $label }}">Text
        <textarea name="body" rows="14" maxlength="20000" class="{{ $field }} mt-1.5 font-mono">{{ old('body', $page->edit('body')) }}</textarea>
        <span class="mt-1 block text-xs font-normal text-gray-500">## Heading · **bold** · - list item · [link](https://…) · a blank line starts a new paragraph.</span></label>
    @else
      <p class="text-sm text-gray-500">This page lists your vacant units automatically, with a "Book a viewing" form on each. Requests arrive in Enquiries.</p>
    @endif
    @if ($page->template === 'contact')<p class="text-sm text-gray-500">A contact form is added under the text. Messages arrive in Enquiries, and your admins get an SMS.</p>@endif
    <div class="flex flex-wrap items-center gap-4">
      <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="show_in_menu" value="1" @checked(old('show_in_menu', $page->show_in_menu))> Show in the menu</label>
      <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">Order <input type="number" name="sort" min="0" max="99" value="{{ old('sort', $page->sort) }}" class="{{ $field }} w-20"></label>
    </div>
    <div class="flex flex-wrap gap-2">
      <button name="action" value="save" class="{{ $outline }}">Save draft</button>
      <button name="action" value="submit" class="{{ $primary }}">Send to scharge for approval</button>
      @if ($isStaff)<button name="action" value="publish" class="{{ $outline }}">Publish now (scharge)</button>@endif
    </div>
  </form>
  @if ($page->template === 'custom')
    <form method="POST" action="{{ route('website.destroy', [$page] + $q) }}" onsubmit="return confirm('Delete this page?')">@csrf<button class="text-sm text-red-600 hover:underline dark:text-red-400">Delete page</button></form>
  @endif
</div>
@endsection
