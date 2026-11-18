{{-- PDF of a demo-form submission (DemoRequestService::pdf). dompdf: simple tables, inline CSS, core fonts. --}}
@php
    use App\Modules\CRM\Services\DemoRequestService as Demo;
    $c = $f->company; $p = $f->contact; $props = $f->detail('properties', []);
@endphp
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1A1214; margin: 0; }
  .head { background: #1A1214; color: #fff; padding: 18px 24px; }
  .head .ref { float: right; background: #DC2626; color: #fff; padding: 4px 10px; border-radius: 10px; font-weight: bold; }
  h1 { font-size: 17px; margin: 8px 0 2px; } .sub { color: #CDBFC2; }
  .body { padding: 18px 24px; } h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #5C4A4E; margin: 16px 0 6px; }
  table { width: 100%; border-collapse: collapse; } td, th { padding: 6px 4px; border-bottom: 1px solid #EDE6E7; text-align: left; vertical-align: top; }
  th { color: #5C4A4E; font-weight: bold; } .num { text-align: right; } .k { width: 32%; color: #5C4A4E; }
  .foot { color: #8a7a7d; font-size: 9px; padding: 0 24px; }
</style></head><body>
<div class="head">
  <span class="ref">{{ $f->detail('ref') }}</span>
  <div style="font-weight:bold">scharge · Demo request</div>
  <h1>{{ $c?->name }}</h1>
  <div class="sub">Submitted {{ $f->created_at?->timezone('Africa/Nairobi')->format('D j M Y, H:i') }} · Account manager: {{ $f->assignee?->name ?? 'not assigned' }}</div>
</div>
<div class="body">
  <h2>Who</h2>
  <table>
    <tr><td class="k">Name</td><td>{{ $p?->name }}{{ $c && $f->detail('role') ? ' · '.(Demo::ROLES[$f->detail('role')] ?? '') : '' }}</td></tr>
    <tr><td class="k">Phone</td><td>{{ $p?->phone }}</td></tr>
    <tr><td class="k">Email</td><td>{{ $p?->email ?: '-' }}</td></tr>
  </table>

  <h2>Contact them</h2>
  <table>
    <tr><td class="k">When</td><td>{{ \Illuminate\Support\Carbon::parse($f->detail('contact.date'))->format('l j F Y') }}, {{ $f->detail('contact.window') }} (Nairobi)</td></tr>
    <tr><td class="k">How</td><td>{{ $f->detail('contact.channel') }}</td></tr>
    <tr><td class="k">Bills and collects today</td><td>{{ $f->detail('billing_now') ?: '-' }}</td></tr>
  </table>

  <h2>Properties ({{ count($props) }} · {{ number_format((int) $f->detail('units')) }} units)</h2>
  <table>
    <tr><th>Property</th><th>Type</th><th>Location</th><th class="num">Units</th></tr>
    @foreach ($props as $pr)
      <tr><td>{{ $pr['name'] ?? '' ?: '-' }}</td><td>{{ $pr['type'] ?? '' ?: '-' }}</td><td>{{ $pr['location'] ?? '' ?: '-' }}</td><td class="num">{{ isset($pr['units']) && $pr['units'] !== '' ? number_format((int) $pr['units']) : '-' }}</td></tr>
    @endforeach
    <tr><td colspan="3"><strong>Total</strong></td><td class="num"><strong>{{ number_format((int) $f->detail('units')) }}</strong></td></tr>
  </table>

  <h2>Wants to see</h2>
  <table>
    @forelse ($f->detail('interests', []) as $i)<tr><td>✓ {{ Demo::INTERESTS[$i] ?? $i }}</td></tr>@empty<tr><td>-</td></tr>@endforelse
  </table>

  @if ($f->notes)<h2>Their note</h2><p>{{ $f->notes }}</p>@endif
</div>
<p class="foot">Consent to be contacted about this demo given {{ $f->detail('consent_at') }}. Business use only (Kenya Data Protection Act).</p>
</body></html>
