@extends('home.auth')
@section('robots', 'noindex,follow')

@section('title', 'Create your account | scharge')
@section('description', 'Create a scharge account and set up your first property in about 15 minutes.')

@section('auth_form')
<form class="auth-view" method="POST" action="{{ route('register') }}">
@csrf
<h1>Create your account.</h1>
<p class="lead">Set up your first property in about 15 minutes.</p>

<div class="field">
<label for="name">Full name</label>
<input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
@error('name')<p class="auth-error" id="name-error">{{ $message }}</p>@enderror
</div>

<div class="field">
<label for="email">Email</label>
<input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@company.co.ke" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
@error('email')<p class="auth-error" id="email-error">{{ $message }}</p>@enderror
</div>

<div class="field">
<label for="business_name">Business name</label>
<input id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" required maxlength="120" autocomplete="organization" placeholder="e.g. Grey Oak Properties" @error('business_name') aria-invalid="true" aria-describedby="business_name-error" @enderror>
@error('business_name')<p class="auth-error" id="business_name-error">{{ $message }}</p>@enderror
</div>

<div class="field-row">
<div class="field">
<label for="kind">What do you do?</label>
<select id="kind" name="kind" required>
@foreach (\App\Http\Controllers\Auth\AccountActivationController::KINDS as $k => $label)<option value="{{ $k }}" @selected(old('kind', 'manager') === $k)>{{ $label }}</option>@endforeach
</select>
@error('kind')<p class="auth-error">{{ $message }}</p>@enderror
</div>
<div class="field">
<label for="phone">Business phone (M-PESA)</label>
<input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="0712 345 678" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
@error('phone')<p class="auth-error" id="phone-error">{{ $message }}</p>@enderror
</div>
</div>

<div class="field">
<label for="password">Password</label>
<div class="input-wrap"><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" data-strength-for @error('password') aria-invalid="true" aria-describedby="password-error" @enderror><button type="button" class="peek" aria-label="Show password">Show</button></div>
<div class="strength" aria-hidden="true"><i></i></div><span class="strength-label" aria-live="polite">At least 8 characters</span>
@error('password')<p class="auth-error" id="password-error">{{ $message }}</p>@enderror
</div>

<div class="field">
<label for="password_confirmation">Confirm password</label>
<div class="input-wrap"><input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"><button type="button" class="peek" aria-label="Show password">Show</button></div>
</div>

<label class="auth-check" for="terms"><input id="terms" type="checkbox" name="terms" required @checked(old('terms'))> I agree to the scharge terms and privacy policy.</label>

<button class="btn btn--primary" type="submit">Create account</button>

<p class="auth-foot">Already have an account? <a class="link-btn" href="{{ route('login') }}">Log in</a></p>
</form>
@endsection
