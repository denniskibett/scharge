<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ $property->name }} service charge {{ $year }}</title>
@php $kes = fn ($n) => 'KES '.number_format((float) $n, 0); @endphp
<style>
  :root { --ink: #1d2430; --muted: #667085; --line: #e4e7ec; --brand: #e5322d; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 14px/1.5 -apple-system, system-ui, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: #f6f7f9; }
  main { max-width: 820px; margin: 24px auto; background: #fff; padding: 36px 40px; border-radius: 12px; }
  header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid var(--brand); padding-bottom: 16px; }
  h1 { margin: 0; font-size: 22px; } h2 { font-size: 15px; margin: 28px 0 8px; }
  .muted { color: var(--muted); }
  .tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 20px; }
  .tile { border: 1px solid var(--line); border-radius: 10px; padding: 12px; } .tile b { display: block; font-size: 17px; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; } th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--line); }
  th { font-size: 12px; color: var(--muted); font-weight: 600; } td.n, th.n { text-align: right; white-space: nowrap; }
  tfoot td { font-weight: 700; border-bottom: 0; } .over { color: #b42318; }
  .print { position: fixed; right: 20px; top: 20px; background: var(--brand); color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; font: inherit; cursor: pointer; }
  @media (max-width: 640px) { main { padding: 20px 16px; margin: 0; border-radius: 0; } .tiles { grid-template-columns: repeat(2, 1fr); } header { flex-direction: column; } }
  @media print { body { background: #fff; } main { margin: 0; padding: 0; } .print { display: none; } }
</style>
</head>
<body>
<button class="print" onclick="window.print()">Print or save as PDF</button>
<main>
  <header>
    <div>
      <h1>{{ $property->name }}</h1>
      <div class="muted">Service charge statement · {{ $year }}{{ $property->location ? ' · '.$property->location : '' }}</div>
    </div>
    <div class="muted" style="text-align:right">{{ $company?->name }}<br>Prepared {{ now()->format('j M Y') }}</div>
  </header>

  <div class="tiles">
    <div class="tile"><span class="muted">Yearly budget</span><b>{{ $kes($figures['planned']) }}</b></div>
    <div class="tile"><span class="muted">Spent so far</span><b>{{ $kes($figures['spent']) }}</b></div>
    <div class="tile"><span class="muted">Sinking fund</span><b>{{ $kes($figures['sinking']) }}</b></div>
    <div class="tile"><span class="muted">Collected</span><b>{{ $kes($figures['collected']) }}</b><span class="muted">of {{ $kes($figures['billed']) }} billed</span></div>
  </div>

  <h2>Budget against spending</h2>
  <table>
    <thead><tr><th>Line</th><th class="n">Budget</th><th class="n">Spent</th><th class="n">Left</th></tr></thead>
    <tbody>
      @forelse ($figures['lines'] as $l)
        <tr><td>{{ $l['name'] }}</td><td class="n">{{ $kes($l['budget']) }}</td><td class="n">{{ $kes($l['spent']) }}</td><td class="n {{ $l['left'] < 0 ? 'over' : '' }}">{{ $kes($l['left']) }}</td></tr>
      @empty
        <tr><td colspan="4" class="muted">No budget lines yet.</td></tr>
      @endforelse
      @if ($figures['other_spent'] > 0)
        <tr><td class="muted">Other spending</td><td class="n">—</td><td class="n">{{ $kes($figures['other_spent']) }}</td><td class="n">—</td></tr>
      @endif
    </tbody>
    <tfoot><tr><td>Total</td><td class="n">{{ $kes($figures['planned']) }}</td><td class="n">{{ $kes($figures['spent']) }}</td><td class="n {{ $figures['planned'] - $figures['spent'] < 0 ? 'over' : '' }}">{{ $kes($figures['planned'] - $figures['spent']) }}</td></tr></tfoot>
  </table>

  <h2>What each unit pays</h2>
  <p>Running costs of {{ $kes($figures['planned']) }} plus a {{ rtrim(rtrim(number_format($budget->sinking_fund_percent, 2), '0'), '.') }}% sinking fund ({{ $kes($figures['sinking']) }}) come to {{ $kes($figures['total_needed']) }} for the year.
    @if ($figures['per_unit_month'] !== null) Shared across {{ $figures['units'] }} units, that is <b>{{ $kes($figures['per_unit_month']) }} per unit a month</b>.@endif</p>
  @if ($budget->notes)<h2>Note</h2><p>{{ $budget->notes }}</p>@endif
  <p class="muted" style="margin-top:32px;font-size:12px">Figures from scharge: spending from recorded expenses for this building, collections from service charge on invoices for {{ $year }}.</p>
</main>
</body>
</html>
