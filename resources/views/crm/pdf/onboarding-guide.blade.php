{{-- Onboarding to-do PDF sent after a demo (DemoOnboarding::pdf). dompdf: simple tables, inline CSS, core fonts, images as data URIs. --}}
@php
    $name = preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name);
    $first = $user ? (explode(' ', trim((string) $user->name))[0] ?: $user->name) : null;
@endphp
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1A1214; margin: 0; }
  .head { background: #1A1214; color: #fff; padding: 22px 28px; }
  .head .tag { float: right; background: #DC2626; color: #fff; padding: 4px 10px; border-radius: 10px; font-weight: bold; font-size: 10px; }
  h1 { font-size: 19px; margin: 8px 0 3px; } .sub { color: #CDBFC2; }
  .body { padding: 16px 28px; }
  h2 { font-size: 14px; margin: 12px 0 5px; } h2 .n { display: inline-block; background: #DC2626; color: #fff; border-radius: 9px; padding: 1px 7px; margin-right: 6px; font-size: 11px; }
  .box { border: 1px solid #EDE6E7; border-radius: 8px; padding: 10px 12px; margin: 6px 0; }
  table { width: 100%; border-collapse: collapse; } td, th { padding: 6px 4px; border-bottom: 1px solid #EDE6E7; text-align: left; vertical-align: top; }
  th { color: #5C4A4E; } .num { text-align: right; } .chk { width: 18px; font-size: 13px; }
  .shotwrap { text-align: center; margin: 6px 0 2px; } .shot { width: 68%; border: 1px solid #EDE6E7; border-radius: 6px; } .shot.sm { width: 48%; }
  .cap { color: #8a7a7d; font-size: 9px; margin-bottom: 4px; text-align: center; }
  .us { color: #0F766E; font-weight: bold; } .you { color: #DC2626; font-weight: bold; }
  .page { page-break-before: always; }
  .foot { color: #8a7a7d; font-size: 9px; padding: 6px 28px 18px; }
  ul { margin: 4px 0 4px 16px; padding: 0; } li { margin: 2px 0; }
</style></head><body>

<div class="head">
  <span class="tag">YOUR ONBOARDING TO-DO</span>
  <div style="font-weight:bold">scharge</div>
  <h1>Welcome, {{ $name }}</h1>
  <div class="sub">We set your account up from the demo. You add your units; we do everything else.@if ($manager) Your account manager: {{ $manager->name }}{{ $manager->phone ? ', '.$manager->phone : '' }}.@endif</div>
</div>

<div class="body">
  <h2>Your checklist</h2>
  <table>
    <tr><th class="chk"></th><th>Step</th><th>Who</th></tr>
    <tr><td class="chk">&#9744;</td><td>Set your password and sign in{{ $user ? ' ('.$user->email.')' : '' }}</td><td class="you">You, 2 minutes</td></tr>
    <tr><td class="chk">&#9744;</td><td>Add your units{{ $expected ? ' (about '.number_format($expected).')' : '' }} with their number, type and rent, or send us your list in Excel</td><td class="you">You</td></tr>
    <tr><td class="chk">&#9744;</td><td>Import your tenants and their balances</td><td class="us">scharge</td></tr>
    <tr><td class="chk">&#9744;</td><td>Connect your M-Pesa Paybill or Till and switch on automatic invoices and reminders</td><td class="us">scharge</td></tr>
    <tr><td class="chk">&#9744;</td><td>Verify your company details (registered name, KRA PIN, documents)</td><td class="us">scharge, with you</td></tr>
    <tr><td class="chk">&#9744;</td><td>Open full access to every page for your team</td><td class="us">scharge</td></tr>
  </table>
  <p style="margin-top:6px">Nothing is billed while we set you up. Your dashboard shows the same checklist and ticks each step itself as it is done, and we track it on our side too.</p>

  @if ($properties)
  <h2>Already set up for you</h2>
  <table>
    <tr><th>Property</th><th class="num">Units to add</th></tr>
    @foreach ($properties as $p)
      <tr><td>{{ $p['name'] }}{{ ($p['status'] ?? '') === 'claim' ? ' (we are confirming this building with you)' : '' }}</td><td class="num">{{ $p['units'] ? number_format($p['units']) : '-' }}</td></tr>
    @endforeach
  </table>
  @endif

  <h2><span class="n">1</span>Set your password and sign in</h2>
  <div class="box">
    Open the email titled <b>"Your scharge account is ready"</b> and press <b>open your scharge account</b>. Choose a password and you are in.
    The link works for 14 days. After that, go to <b>{{ $loginUrl }}</b>, press <b>Forgot your password?</b> and enter {{ $user?->email ?? 'your email' }}.
    You can change your name, phone and password any time on <b>Profile</b>.
  </div>
  @if ($shots['profile'])<div class="shotwrap"><img class="shot sm" src="{{ $shots['profile'] }}"></div><div class="cap">Profile: your details and password.</div>@endif
</div>

<div class="body page">
  <h2><span class="n">2</span>Your checklist on the dashboard</h2>
  <div class="box">When you sign in you land on your dashboard. Your checklist shows what is done and what is next, and ticks itself from your data. Each step has a button that opens the right page.</div>
  @if ($shots['guide'])<div class="shotwrap"><img class="shot" src="{{ $shots['guide'] }}"></div><div class="cap">Your checklist (Guide): it ticks itself as each step is done.</div>@endif

  <h2><span class="n">3</span>Add your units <span class="you">(your part)</span></h2>
  <div class="box">
    <ul>
      <li>Open <b>Units</b> in the menu and press <b>Add Unit</b>.</li>
      <li>Pick the property, then type the unit number (for example A1 or 12), the type (bedsitter, 1 bedroom...) and the monthly rent. Add the deposit and any water or service charge if you know them.</li>
      <li>The unit number is what tenants will type as the M-Pesa account number, so use the numbers on the doors.</li>
      <li>Many units? Send your list in Excel to your account manager and we load it for you in minutes.</li>
    </ul>
  </div>
  @if ($shots['units'])<div class="shotwrap"><img class="shot" src="{{ $shots['units'] }}"></div><div class="cap">Units: press Add Unit for each house; the list fills as you go.</div>@endif
</div>

<div class="body page">
  <h2><span class="n">4</span>What we do for you</h2>
  <div class="box">
    <ul>
      <li><b>Tenants and balances:</b> send us your tenant list (Excel, or even a photo of your book) and we import names, phones, units, rent and balances.</li>
      <li><b>Payments:</b> we connect your own M-Pesa Paybill or Till. Tenants pay you directly and every payment is matched to the right unit automatically. Owners and tenants abroad can pay and be paid in USD and other currencies through Sharet Pay.</li>
      <li><b>Automatic invoices and reminders:</b> rent, water and service charge go out by SMS on your invoice day, and late payers are reminded.</li>
      <li><b>Company details and KYC:</b> we verify your registered name and KRA PIN with you.</li>
      <li><b>AI assistant:</b> ask for any report in plain words, save the prompts you use often, or ask anything about your business.</li>
    </ul>
  </div>
  @if ($shots['import'])<div class="shotwrap"><img class="shot" src="{{ $shots['import'] }}"></div><div class="cap">Import: how we load your units and tenants from your file, checked before anything is saved.</div>@endif

  <h2><span class="n">5</span>When setup is done</h2>
  <div class="box">We open every page for your team: tenants, invoices, payments, reports, water, repairs, team logins and your own branding. Until then you can add and edit your units, and we handle the rest.</div>

  @if ($manager)
  <h2>Your account manager</h2>
  <div class="box"><b>{{ $manager->name }}</b><br>{{ $managerEmail }}{{ $manager->phone ? ' · '.$manager->phone : '' }}<br>Call or WhatsApp any time during setup.</div>
  @endif
</div>
<div class="foot">scharge · Property software for Africa, starting in Kenya · {{ now()->timezone('Africa/Nairobi')->format('j F Y') }}</div>
</body></html>
