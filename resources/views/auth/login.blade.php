@extends('home.auth')

@section('title', 'Log in | scharge')
@section('description', 'Log in to scharge to see who has paid, what is due and what needs fixing.')

@section('auth_form')
<form class="auth-view" method="POST" action="{{ route('login') }}">
@csrf
<h1>Welcome back.</h1>
<p class="lead">Log in to see who has paid, what is due and what needs fixing.</p>

<div class="field">
<label for="email">Email</label>
<input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.co.ke" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
@error('email')<p class="auth-error" id="email-error">{{ $message }}</p>@enderror
</div>

<div class="field">
<label for="password">Password</label>
<div class="input-wrap"><input id="password" type="password" name="password" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror><button type="button" class="peek" aria-label="Show password">Show</button></div>
@error('password')<p class="auth-error" id="password-error">{{ $message }}</p>@enderror
</div>

<div class="auth-line">
<label for="remember_me"><input id="remember_me" type="checkbox" name="remember" @checked(old('remember'))> Keep me logged in</label>
@if (Route::has('password.request'))
<a class="link-btn" href="{{ route('password.request') }}">Forgot password?</a>
@endif
</div>

<button class="btn btn--primary" type="submit">Log in</button>

<p class="auth-foot">New to scharge? <a class="link-btn" href="{{ route('register') }}">Create an account</a></p>
</form>
@endsection
