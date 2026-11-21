@extends('home.auth')
@section('robots', 'noindex,nofollow')

@section('title', 'Confirm your password | scharge')
@section('description', 'Confirm your password to continue.')

@section('auth_form')
<form class="auth-view" method="POST" action="{{ route('password.confirm') }}">
@csrf
<h1>Confirm it's you.</h1>
<p class="lead">This part of scharge needs your password again before you continue.</p>

<div class="field">
<label for="password">Password</label>
<div class="input-wrap"><input id="password" type="password" name="password" required autofocus autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror><button type="button" class="peek" aria-label="Show password">Show</button></div>
@error('password')<p class="auth-error" id="password-error">{{ $message }}</p>@enderror
</div>

<button class="btn btn--primary" type="submit">Continue</button>
</form>
@endsection
