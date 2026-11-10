{{-- The scharge app (Android test build). docs/MOBILE_APP.md. Shown on the API & white label and Developers pages. --}}
@php($apk = file_exists(public_path('downloads/scharge-app.apk')) ? asset('downloads/scharge-app.apk') : null)
<section class="section" id="app"><div class="wrap"><div class="section--blush row" style="border-radius:28px;padding:clamp(24px,4vw,44px);align-items:center">
<div class="stack" style="flex:2 1 360px">
<span class="badge badge--soon">In development</span>
<h2>The scharge app, for every role</h2>
<p class="lead">One Android app for tenants, owners, managers, meter readers, maintenance, security and BnB hosts. Each person signs in with their phone number and an SMS code, then opens it with their fingerprint or phone lock, and sees only what their role allows.</p>
<ul class="stack" style="gap:6px;padding-left:1.2em;margin:0">
<li>Tenants: bills and balance, pay with M-Pesa, receipts, repairs, gate passes for visitors.</li>
<li>Owners and staff: rent collected this month, unpaid bills, payments, tenants and units.</li>
<li>Field staff: meter readings with a photo, work orders, the gate.</li>
<li>White label: the app wears your name, logo and colour after sign-in.</li>
</ul>
<p class="small muted">A test build: install it on Android to try it with your scharge account. iPhone and the Play Store come later.</p>
</div>
<div class="stack" style="flex:1 1 240px;align-items:flex-start">
@if ($apk)
<a class="btn btn--primary" href="{{ $apk }}" download>Download the Android app (APK)</a>
<span class="small muted">Android 7 or newer. Allow "install unknown apps" for your browser when asked.</span>
@else
<span class="btn btn--glass" aria-disabled="true">Android test build: coming soon</span>
@endif
<a class="small" href="{{ route('home.api') }}#keys">White label it for your company</a>
</div>
</div></div></section>
