@extends('home.auth')

@section('title', 'List your BnB | scharge')
@section('description', 'Register your short-stay unit on scharge. Your building is added once; your unit stays yours even when the building management joins.')

@section('auth_form')
<form class="auth-view" method="POST" action="{{ route('host.store') }}" x-data="hostSignup('{{ route('host.properties.search') }}')">
@csrf
<h1>List your BnB.</h1>
<p class="lead">One unit or many. If your building is already on scharge we add your unit to it, so nothing is duplicated and your unit stays yours.</p>

<fieldset class="field"><legend>Your building</legend>
<label for="property_name">Building name</label>
<input id="property_name" name="property_name" value="{{ old('property_name') }}" maxlength="160" placeholder="e.g. Kings Serenity Residence" x-model="name" @input.debounce.500ms="search()">
<label for="location">Area</label>
<input id="location" name="location" value="{{ old('location') }}" maxlength="160" placeholder="e.g. Kitengela" x-model="location" @input.debounce.500ms="search()">
<input type="hidden" name="property_id" :value="picked ? picked.id : ''">
<template x-if="matches.length && !picked">
  <div class="auth-note" style="margin-top:8px"><p>Is it one of these? Pick it so your unit joins the same building:</p>
    <template x-for="m in matches" :key="m.id"><button type="button" class="link-btn" style="display:block;margin:4px 0" @click="picked = m" x-text="m.name + ' · ' + (m.location || '')"></button></template>
  </div>
</template>
<p x-show="picked" class="auth-note">Adding your unit to <b x-text="picked && picked.name"></b>. <button type="button" class="link-btn" @click="picked = null">Not this one</button></p>
@error('property_name')<p class="auth-error">{{ $message }}</p>@enderror
</fieldset>

<div class="field"><label for="unit_number">Your unit number</label>
<input id="unit_number" name="unit_number" value="{{ old('unit_number') }}" required maxlength="30" placeholder="e.g. B4">
@error('unit_number')<p class="auth-error">{{ $message }}</p>@enderror</div>

<div class="field"><label for="unit_type">Type</label>
<select id="unit_type" name="unit_type" required>@foreach (['Studio','One Bedroom','Two Bedroom','Three Bedroom','Four Bedroom','Villa','Cottage'] as $t)<option @selected(old('unit_type') === $t)>{{ $t }}</option>@endforeach</select></div>

<div class="field"><label for="nightly_rate">Price per night (KES)</label>
<input id="nightly_rate" name="nightly_rate" type="number" min="500" step="50" value="{{ old('nightly_rate') }}" required>
@error('nightly_rate')<p class="auth-error">{{ $message }}</p>@enderror</div>

<fieldset class="field"><legend>You</legend>
<label for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
@error('name')<p class="auth-error">{{ $message }}</p>@enderror
<label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
@error('email')<p class="auth-error">{{ $message }}</p>@enderror
<label for="phone">Phone (WhatsApp)</label><input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required maxlength="30" autocomplete="tel">
<label for="password">Password</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
<label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
@error('password')<p class="auth-error">{{ $message }}</p>@enderror
</fieldset>

<label class="auth-check" for="terms"><input id="terms" type="checkbox" name="terms" required> I agree to the scharge terms and privacy policy.</label>
<button class="btn btn--primary" type="submit">List my BnB</button>
<p class="auth-foot">Already on scharge? <a class="link-btn" href="{{ route('login') }}">Log in</a></p>
</form>

<script>
  function hostSignup(url) {
    return { name: @json(old('property_name', '')), location: @json(old('location', '')), matches: [], picked: null,
      async search() {
        this.picked = null;
        if (this.name.trim().length < 3) { this.matches = []; return; }
        const r = await fetch(`${url}?name=${encodeURIComponent(this.name)}&location=${encodeURIComponent(this.location)}`, { headers: { Accept: 'application/json' } });
        this.matches = r.ok ? (await r.json()).matches : [];
      } };
  }
</script>
@endsection
