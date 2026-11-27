@extends('home.layout')
@section('robots', 'noindex,nofollow')
@section('title', 'Create your scharge account | '.$company->name)
@section('description', 'Create your scharge account from your demo request.')

@php
    $name = preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name);
    $email = $contact?->email ?: $company->email;
@endphp
@section('content')
<section class="section section--blush">
<div class="wrap" style="max-width:720px">
<div class="stack" style="gap:16px;background:#fff;border-radius:18px;padding:28px">
@if ($done)
    <span class="label">Your scharge account</span>
    <h1 style="font-size:1.6rem">{{ $name }}, your account is ready.</h1>
    <p class="lead">We sent the details to {{ $user?->email ?? $email }}. Set your password and sign in to add your units.</p>
    <form method="POST" action="{{ $action }}">@csrf<button class="btn btn--primary">Set my password</button></form>
@else
    <span class="label">Create your scharge account</span>
    <h1 style="font-size:1.6rem">{{ $name }}, start using scharge today</h1>
    <p class="lead">We set your account up from what you sent us. You add your units; we do everything else.</p>
    @if (session('error'))<div role="alert" style="background:#FEF2F2;color:#991B1B;border-radius:12px;padding:12px">{{ session('error') }}</div>@endif

    @if ($properties)
    <div>
        <b>Your properties</b>
        <ul style="margin:6px 0 0 18px">@foreach ($properties as $p)<li>{{ $p['name'] ?? 'Property' }}{{ ! empty($p['location']) ? ', '.$p['location'] : '' }}{{ ! empty($p['units']) ? ' · '.number_format((int) $p['units']).' units' : '' }}</li>@endforeach</ul>
    </div>
    @endif

    <div>
        <b>What happens when you press the button</b>
        <ol style="margin:6px 0 0 18px">
            <li>Your account is created and your properties are added.</li>
            <li>You choose your password{{ $email ? ' (your login is '.$email.')' : '' }}.</li>
            <li>You add your units with their number, type and rent, or send us your list in Excel.</li>
            <li>{{ $manager?->name ?? 'Your account manager' }} imports your tenants, connects your M-Pesa Paybill or Till and switches on automatic invoices and reminders.</li>
        </ol>
    </div>
    <p class="muted">Nothing is billed while we set you up, and nothing until you decide to go ahead. You will also get an onboarding to-do PDF by email and the link by SMS.</p>

    <form method="POST" action="{{ $action }}" onsubmit="this.querySelector('button').disabled=true">@csrf
        <button class="btn btn--primary">Create my scharge account</button>
    </form>
    @if ($manager)<p class="muted small">Questions first? Call or WhatsApp {{ $manager->name }}{{ $manager->phone ? ' on '.$manager->phone : '' }}.</p>@endif
@endif
</div>
</div>
</section>
@endsection
