@extends('home.auth')
@section('robots', 'noindex,nofollow')

@section('title', 'Choose a new password | scharge')
@section('description', 'Set a new password for your scharge account.')

@section('auth_form')
<form class="auth-view" method="POST" action="{{ route('password.store') }}">
@csrf
<input type="hidden" name="token" value="{{ $request->route('token') }}">
<h1>Choose a new password.</h1>
<p class="lead">Use at least 8 characters. You will log in with it from now on.</p>

<div class="field">
<label for="email">Email</label>
<input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
@error('email')<p class="auth-error" id="email-error">{{ $message }}</p>@enderror
</div>

<div class="field">
<label for="password">New password</label>
<div class="input-wrap"><input id="password" type="password" name="password" required autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror><button type="button" class="peek" aria-label="Show password">Show</button></div>
@error('password')<p class="auth-error" id="password-error">{{ $message }}</p>@enderror
</div>

<div class="field">
<label for="password_confirmation">Type it again</label>
<input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
@error('password_confirmation')<p class="auth-error">{{ $message }}</p>@enderror
</div>

<button class="btn btn--primary" type="submit">Save new password</button>
<p class="auth-foot"><a class="link-btn" href="{{ route('login') }}">Back to log in</a></p>
</form>
@endsection
