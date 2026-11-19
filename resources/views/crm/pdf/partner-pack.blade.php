{{-- Partner pack (API sandbox, white label, prices, revenue share) sent with a sandbox key: crm:api-sandbox-key.
     dompdf: simple tables, inline CSS, DejaVu Sans. Monthly rates mirror the public price list (#pricing-data in welcome.blade.php). --}}
@php
  $rates = [
    ['Apartments', 'unit', 500, 300, 150, 40],
    ['Airbnb & serviced', 'listing', 1500, 1000, 700, 400],
    ['Hotels', 'room', 800, 600, 400, 250],
    ['Offices', 'suite', 2000, 1500, 1000, 600],
    ['Warehouses', 'bay', 2000, 1600, 1200, 800],
  ];
  $tiers = [['Up to 500 units', '20%'], ['501 to 2,000 units', '25%'], ['Over 2,000 units', '30%']];
  $base = url('/api/v1');
@endphp
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1A1214; margin: 0; line-height: 1.5; }
  .head { background: #1A1214; color: #fff; padding: 22px 30px 20px; }
  .head .ref { float: right; background: #DC2626; color: #fff; padding: 4px 10px; border-radius: 10px; font-weight: bold; font-size: 10px; }
  h1 { font-size: 19px; margin: 10px 0 2px; } .sub { color: #CDBFC2; }
  .body { padding: 6px 30px 10px; }
  h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #DC2626; margin: 18px 0 6px; }
  p { margin: 0 0 7px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  td, th { padding: 5px 5px; border-bottom: 1px solid #EDE6E7; text-align: left; vertical-align: top; }
  th { font-size: 9.5px; color: #5C4A4E; font-weight: bold; background: #F7F3F3; }
  .k { width: 30%; color: #5C4A4E; }
  .num { text-align: right; }
  .box { background: #F7F3F3; border-left: 3px solid #DC2626; padding: 8px 10px; margin: 6px 0 8px; }
  .mono { font-family: DejaVu Sans Mono, monospace; font-size: 9.5px; }
  .opt { font-weight: bold; color: #1A1214; }
  .foot { color: #8a7a7d; font-size: 8.5px; padding: 6px 30px 18px; }
  .page { page-break-before: always; }
</style></head><body>
<div class="head">
  <span class="ref">{{ $ref }}</span>
  <div style="font-weight:bold">scharge · Partner pack</div>
  <h1>{{ $company->name }}</h1>
  <div class="sub">API sandbox, white label, prices and revenue share · {{ now('Africa/Nairobi')->format('j F Y') }}</div>
</div>
<div class="body">
  <h2>1. Your API sandbox</h2>
  <p>Your sandbox key reads a sample property company: 3 properties (Kilimani and Ruaka apartments, Westlands serviced suites), 34 units, tenants, three months of bills with rent, water and service charge, M-Pesa payments and meter readings. It is the same API and the same fields as a live account, so code you write against the sandbox works unchanged with a live key. Nothing you do in the sandbox bills anyone or sends any SMS.</p>
  <table>
    <tr><td class="k">Base URL</td><td class="mono">{{ $base }}</td></tr>
    <tr><td class="k">Key</td><td class="mono">{{ $masked }} <span style="font-family:DejaVu Sans,sans-serif;color:#5C4A4E">(full key in the email; send it as "Authorization: Bearer &lt;key&gt;")</span></td></tr>
    <tr><td class="k">Who am I</td><td class="mono">GET /me</td></tr>
    <tr><td class="k">Records, page by page</td><td class="mono">GET /properties · /units · /people · /tenants · /tenancies · /invoices · /invoice_items · /payments · /water_readings<br><span style="font-family:DejaVu Sans,sans-serif;color:#5C4A4E">?limit=1..500 &amp; after={last id} &amp; updated_since={date}</span></td></tr>
    <tr><td class="k">One bill</td><td class="mono">GET /invoices/{id}</td></tr>
    <tr><td class="k">Pay link for a bill</td><td class="mono">POST /invoices/{id}/pay-link <span style="font-family:DejaVu Sans,sans-serif;color:#5C4A4E">(sandbox: a test link, no money moves)</span></td></tr>
    <tr><td class="k">Limits</td><td>120 requests a minute per key. Read-only plus pay links.</td></tr>
  </table>
  <p>Typical use for your accounting or CRM: pull <span class="mono">invoices</span> and <span class="mono">payments</span> every hour with <span class="mono">updated_since</span>, match them to your ledger by tenancy and unit, and raise pay links for balances. Developer guide: {{ url('/developers') }}</p>

  <h2>2. White label: scharge under your name</h2>
  <p>Your clients and their tenants see your company, not ours. It runs on scharge's servers, so you have nothing to host or maintain.</p>
  <table>
    <tr><td class="k">Your brand</td><td>Your name, logo (light and dark), colours and tagline across the dashboard, sign-in page, bills, receipts and PDF statements.</td></tr>
    <tr><td class="k">Your address</td><td>Your own domain (for example portal.yourcompany.co.ke) with SSL. On it there is no scharge sign-up and no scharge branding.</td></tr>
    <tr><td class="k">Your sender</td><td>Bill and receipt emails go out under your name; SMS under your sender ID and WhatsApp on your number once approved.</td></tr>
    <tr><td class="k">"Powered by"</td><td>A small "powered by scharge" line can be shown or removed.</td></tr>
    <tr><td class="k">Website</td><td>A ready website for each client: home, about, homes to rent (vacant units with a viewing form) and contact, in your design.</td></tr>
    <tr><td class="k">Data</td><td>Each client's data is theirs: full export any time, a nightly off-site copy, the read API, and a data-processing agreement.</td></tr>
  </table>
  <div class="box">
    <span class="opt">How it goes:</span> week 1 brand kit and domain · week 2 your clients' properties and units loaded (CSV or our team) · week 3 bills, M-Pesa and SMS tested on one building · week 4 live. About 4 weeks in all.
  </div>
</div>

<div class="page"></div>
<div class="body" style="padding-top:22px">
  <h2>3. White-label setup fee: KES 50,000, three ways to pay</h2>
  <table>
    <tr><th style="width:30%">Option</th><th>What you pay</th></tr>
    <tr><td class="opt">A. Pay upfront</td><td>KES 50,000 when we start the setup.</td></tr>
    <tr><td class="opt">B. Half now, half from fees</td><td>KES 25,000 when we start. The other KES 25,000 is taken from your revenue share as your clients pay their monthly fees, so you never send it.</td></tr>
    <tr><td class="opt">C. Nothing upfront</td><td>Bring 100 units onto scharge in your first 3 months and you pay nothing to start: the KES 50,000 is recovered from your revenue share as those units' fees are paid. If fewer than 100 units are live after 3 months, the fee moves to option B.</td></tr>
  </table>

  <h2>4. What your clients pay scharge each month</h2>
  <p>Per unit per month, in KES, by area. Vacant units are half price. At least KES 1,500 per property a month.</p>
  <table>
    <tr><th>Property type</th><th>Per</th><th class="num">Prime</th><th class="num">Upmarket</th><th class="num">Mid-market</th><th class="num">Affordable</th></tr>
    @foreach ($rates as [$type, $per, $a, $b, $c, $d])
      <tr><td>{{ $type }}</td><td>{{ $per }}</td><td class="num">{{ number_format($a) }}</td><td class="num">{{ number_format($b) }}</td><td class="num">{{ number_format($c) }}</td><td class="num">{{ number_format($d) }}</td></tr>
    @endforeach
  </table>
  <p style="color:#5C4A4E">Example: a 20-unit affordable block is 20 × 40 = KES 800, so the KES 1,500 minimum applies. A 40-unit mid-market block is KES 6,000 a month.</p>

  <h2>5. Your revenue share</h2>
  <p>For every client you bring, you earn a share of the monthly fee they pay scharge, every month they stay. The rate is set by the total units across all your clients, and the whole share moves up when you reach the next tier.</p>
  <table>
    <tr><th style="width:45%">Your clients' units on scharge</th><th>Your share of their monthly fee</th></tr>
    @foreach ($tiers as [$band, $rate])
      <tr><td>{{ $band }}</td><td style="font-weight:bold">{{ $rate }}</td></tr>
    @endforeach
  </table>
  <div class="box">
    <span class="opt">Example:</span> your clients have 800 mid-market apartments, so they pay 800 × KES 150 = KES 120,000 a month. You are in the 25% tier and earn <strong>KES 30,000 every month</strong>, about KES 360,000 a year, for as long as they stay.
  </div>
  <p>Your share is worked out each month on the fees your clients actually paid, with a statement showing each client and unit. It is paid by M-Pesa or bank transfer once their fees are in. Under options B and C, the setup balance comes out of it first.</p>

  <h2>6. Next steps</h2>
  <p>1. Try the sandbox with the key in the email. 2. Tell us which accounting or CRM you are connecting, and we will help with the mapping. 3. Book a call to agree your partner terms and the brand kit. A short partner agreement sets out the above before you start.</p>
</div>
<p class="foot">scharge · Sharet Africa · Nairobi, Kenya · {{ url('/') }} · Reply to the email or write to sales@sharet.africa. Prices in KES, exclusive of VAT where it applies. Business use only (Kenya Data Protection Act).</p>
</body></html>
