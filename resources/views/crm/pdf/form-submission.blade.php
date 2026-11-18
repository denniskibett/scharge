{{-- PDF of any website form (FormSubmissionService). dompdf: simple tables, inline CSS, core fonts. --}}
@php($c = $f->company)
@php($p = $f->contact)
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1A1214; margin: 0; }
  .head { background: #1A1214; color: #fff; padding: 18px 24px; }
  .head .ref { float: right; background: #DC2626; color: #fff; padding: 4px 10px; border-radius: 10px; font-weight: bold; }
  h1 { font-size: 17px; margin: 8px 0 2px; } .sub { color: #CDBFC2; }
  .body { padding: 18px 24px; } h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #5C4A4E; margin: 16px 0 6px; }
  table { width: 100%; border-collapse: collapse; } td { padding: 6px 4px; border-bottom: 1px solid #EDE6E7; text-align: left; vertical-align: top; }
  .k { width: 32%; color: #5C4A4E; }
  .foot { color: #8a7a7d; font-size: 9px; padding: 0 24px; }
</style></head><body>
<div class="head">
  <span class="ref">{{ $f->detail('ref') }}</span>
  <div style="font-weight:bold">scharge · {{ $f->detail('form.name') }}</div>
  <h1>{{ $c?->name ?? $p?->name ?? 'Website visitor' }}</h1>
  <div class="sub">Submitted {{ \Illuminate\Support\Carbon::parse($f->detail('form.submitted_at', $f->created_at))->timezone('Africa/Nairobi')->format('D j M Y, H:i') }} · {{ $f->assignee ? 'Owner: '.$f->assignee->name : 'Not assigned' }}</div>
</div>
<div class="body">
  <h2>Who</h2>
  <table>
    <tr><td class="k">Name</td><td>{{ $p?->name ?: '-' }}</td></tr>
    <tr><td class="k">Phone</td><td>{{ $p?->phone ?: '-' }}</td></tr>
    <tr><td class="k">Email</td><td>{{ $p?->email ?: '-' }}</td></tr>
    @if ($c)<tr><td class="k">Company</td><td>{{ $c->name }}</td></tr>@endif
  </table>

  <h2>What they sent</h2>
  <table>
    <tr><td class="k">Request</td><td>{{ $f->title }}</td></tr>
    @foreach ($f->detail('form.rows', []) as $label => $value)
      <tr><td class="k">{{ $label }}</td><td>{{ is_array($value) ? implode(', ', $value) : $value }}</td></tr>
    @endforeach
  </table>

  @if ($f->detail('form.message'))<h2>Their message</h2><p>{{ $f->detail('form.message') }}</p>@endif
</div>
<p class="foot">Sent through the {{ strtolower($f->detail('form.name')) }} form. Business use only (Kenya Data Protection Act).</p>
</body></html>
