@extends('home.auth')
@section('robots', 'noindex,nofollow')

@section('title', 'Confirm your email | scharge')
@section('description', 'Confirm your email address to start using scharge.')

@section('auth_form')
<div class="auth-view">
<h1>Check your email.</h1>
<p class="lead">We sent a link to <b>{{ auth()->user()?->email }}</b>. Click it to confirm your address, then come back here.</p>

<form method="POST" action="{{ route('verification.send') }}">
@csrf
<button class="btn btn--primary" type="submit">Send the link again</button>
</form>

<form method="POST" action="{{ route('logout') }}">
@csrf
<p class="auth-foot">Wrong account? <button type="submit" class="link-btn">Log out</button></p>
</form>
</div>
@endsection
