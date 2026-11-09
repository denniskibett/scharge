@extends('home.auth')
@section('robots', 'noindex,nofollow')
@section('title', 'Confirm your email | scharge')
@section('description', 'Enter the code we emailed you to confirm your email.')

@section('auth_form')
<form class="auth-view" method="POST" action="{{ route('email-code.verify') }}">
@csrf
<h1>Confirm your email.</h1>
<p class="lead">We sent a {{ $digits }}-digit code to <strong>{{ $email }}</strong>. Enter it to continue. Check spam or promotions if it is not in your inbox.</p>

@if (session('status'))<p class="auth-note" role="status">{{ session('status') }}</p>@endif
@if (session('error'))<p class="auth-error" role="alert">{{ session('error') }}</p>@endif

<div class="field">
<label for="code">Code</label>
<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="{{ $digits + 2 }}" required autofocus
       placeholder="{{ str_repeat('•', $digits) }}" style="letter-spacing:.4em;font-size:1.25rem;text-align:center" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
@error('code')<p class="auth-error" id="code-error">{{ $message }}</p>@enderror
</div>

<button class="btn btn--primary" type="submit">Confirm email</button>
</form>

<form class="auth-view" method="POST" action="{{ route('email-code.resend') }}" style="margin-top:12px">
@csrf
<p class="auth-foot">No email? <button class="link-btn" type="submit" @disabled($wait > 0)>{{ $wait > 0 ? "Send another in {$wait}s" : 'Send another code' }}</button></p>
</form>

<details class="auth-view" style="margin-top:8px" @if ($errors->has('email')) open @endif>
<summary class="link-btn" style="cursor:pointer">Wrong email address?</summary>
<form method="POST" action="{{ route('email-code.change') }}" style="margin-top:12px">
@csrf
<div class="field">
<label for="email">Your correct email</label>
<input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@company.co.ke" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
@error('email')<p class="auth-error" id="email-error">{{ $message }}</p>@enderror
</div>
<button class="btn" type="submit">Use this email and send the code</button>
</form>
</details>

<form method="POST" action="{{ route('logout') }}" style="margin-top:16px">
@csrf
<p class="auth-foot"><button class="link-btn" type="submit">Sign out</button></p>
</form>
@endsection
