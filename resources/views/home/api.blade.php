@extends('home.layout')

@section('title', 'scharge API and white label')
@section('description', 'Put vacancies and booking calendars on your own website, or launch a tenant and buyer app in your brand.')

@section('header_cta')
<a class="btn btn--primary" href="#keys">Get sandbox keys</a>
@endsection

@section('subnav')
<nav class="subnav" aria-label="On this page"><div class="wrap"><a href="#why">Why white label</a><a href="#uses">What you can build</a><a href="#code">Quickstart</a><a href="#ref">Reference</a></div></nav>
@endsection

@section('content')
<section class="hero" data-slider aria-roledescription="carousel" aria-label="Highlights">
<div class="hero-slide is-active" role="group" aria-roledescription="slide" aria-label="1 of 4">
<div class="hero-bg" style="background-image:url('{{ asset('assets/images/slides/api-1.jpg') }}')"></div><div class="hero-shade"></div><div class="hero-stripe s-jac"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-jac">White label</span>
<h1 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Your brand on the outside. scharge underneath.</h1>
<p>Your name, logo and domain on every app screen, receipt and SMS. We run the payments, sync and security.</p>
<div class="actions"><a class="btn btn--primary" href="#keys">Talk to us about white label</a><a class="btn btn--glass" href="#why">Why white label</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="2 of 4">
<div class="hero-bg" data-bg="assets/images/slides/api-2.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-red"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-red">For landlords</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Vacancies on your own website, live.</h2>
<p>Units appear the moment they are vacant and disappear when let. Viewings land in your dashboard.</p>
<div class="actions"><a class="btn btn--primary" href="#code">See the listings widget</a><a class="btn btn--glass" href="#why">Why white label</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="3 of 4">
<div class="hero-bg" data-bg="assets/images/slides/api-3.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-gold"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-gold">For Airbnb owners</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Direct bookings without platform commission.</h2>
<p>A booking calendar on your site that respects your Airbnb and Booking.com dates, with an M-Pesa deposit.</p>
<div class="actions"><a class="btn btn--primary" href="#code">See the booking calendar</a><a class="btn btn--glass" href="#why">Why white label</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="4 of 4">
<div class="hero-bg" data-bg="assets/images/slides/api-4.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-teal"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-teal">For developers</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">A buyer and resident app in your project’s name.</h2>
<p>Instalments, snag lists and service charge in an app your buyers download with your logo on it.</p>
<div class="actions"><a class="btn btn--primary" href="#keys">Plan my app</a><a class="btn btn--glass" href="#why">Why white label</a></div>
</div>
</div>
<div class="hero-controls"><button class="hero-arrow hero-prev" aria-label="Previous slide">‹</button><button class="hero-dot" aria-label="Show slide 1" aria-current="true"></button><button class="hero-dot" aria-label="Show slide 2" aria-current="false"></button><button class="hero-dot" aria-label="Show slide 3" aria-current="false"></button><button class="hero-dot" aria-label="Show slide 4" aria-current="false"></button><button class="hero-arrow hero-next" aria-label="Next slide">›</button></div>
</section>
<section class="section" id="why"><div class="wrap stack-l">
<div class="stack" style="max-width:48rem"><span class="label" style="color:var(--jac)">Why white label</span><h2>People trust the name on the door. Make it yours.</h2><p class="lead">Tenants, buyers and guests deal with you, not with a software company. White label keeps every touchpoint in your brand while scharge does the heavy lifting.</p></div>
<div class="grid"><div class="card" style="background:#7B5EA7;color:#FFFFFF;border:0"><span style="font-family:var(--font-display);font-size:1.6rem;letter-spacing:-.02em">Your name</span><h3>Trust where it counts</h3><p style="color:inherit;opacity:.9">Tenants and buyers see your brand on the app, receipts and SMS, so payments feel safe and familiar.</p></div><div class="card" style="background:#F2B33D;color:#1A1214;border:0"><span style="font-family:var(--font-display);font-size:1.6rem;letter-spacing:-.02em">0%</span><h3>No platform commission</h3><p style="color:inherit;opacity:.9">Direct bookings and rent go through your brand, not a marketplace taking 3–15% of each booking.</p></div><div class="card" style="background:#0F766E;color:#FFFFFF;border:0"><span style="font-family:var(--font-display);font-size:1.6rem;letter-spacing:-.02em">Yours</span><h3>You own the relationship</h3><p style="color:inherit;opacity:.9">Guest and tenant contacts, history and reviews stay with you, ready for the next booking or the next block.</p></div><div class="card" style="background:#F5EBDD;color:#1A1214;border:0"><span style="font-family:var(--font-display);font-size:1.6rem;letter-spacing:-.02em">4 weeks</span><h3>Launch without a dev team</h3><p style="color:inherit;opacity:.9">M-Pesa, bank, calendar sync, statements and security are already built. You bring the brand.</p></div><div class="card" style="background:#FEF2F2;color:#1A1214;border:0"><span style="font-family:var(--font-display);font-size:1.6rem;letter-spacing:-.02em">Live</span><h3>Your website always current</h3><p style="color:inherit;opacity:.9">Vacancies, prices and availability update themselves, so nobody calls about a unit that is let.</p></div><div class="card" style="background:#1A1214;color:#FFFFFF;border:0"><span style="font-family:var(--font-display);font-size:1.6rem;letter-spacing:-.02em">+</span><h3>A product you can sell</h3><p style="color:inherit;opacity:.9">Developers can bundle the resident app into the sale and keep a service relationship after handover.</p></div></div>
<div class="row" style="align-items:center">
<div style="flex:1 1 300px;display:flex;justify-content:center"><div class="phone" style="background:var(--sand)"><div class="row" style="gap:10px;align-items:center"><span class="t-logo" style="background:var(--teal);color:#fff;width:34px;height:34px">DL</span><b>Danaff Living</b></div>
<div style="border-radius:20px;background:var(--teal);color:#fff;padding:16px;display:flex;flex-direction:column;gap:6px"><span class="small" style="opacity:.85">Unit C-1204, instalment 7 of 18</span><span style="font-family:var(--font-display);font-size:1.4rem">KES 185,000</span><span class="small" style="opacity:.85">Due 5 Nov. 61% of unit paid.</span><span class="btn" style="background:var(--gold);color:var(--ink);min-height:38px">Pay with M-Pesa</span></div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;text-align:center" class="small"><span class="card" style="padding:10px 4px">Snag list</span><span class="card" style="padding:10px 4px">Statement</span><span class="card" style="padding:10px 4px">Handover</span></div><span class="small muted" style="text-align:center">Sample: a developer's app on white label</span></div></div>
<div class="table-wrap" style="flex:2 1 520px"><table><thead><tr><th></th><th>scharge-branded</th><th style="background:var(--jac);color:#fff">White label</th><th>Build it yourself</th></tr></thead><tbody><tr><th scope="row">Your logo, colours, app name</th><td>No</td><td style="background:#F7F3FB;font-weight:600">Yes</td><td>Yes</td></tr><tr><th scope="row">Your domain and email sender</th><td>No</td><td style="background:#F7F3FB;font-weight:600">Yes</td><td>Yes</td></tr><tr><th scope="row">Mobile money &amp; bank collections</th><td>Included</td><td style="background:#F7F3FB;font-weight:600">Included</td><td>Build and certify yourself</td></tr><tr><th scope="row">Airbnb &amp; Booking.com sync</th><td>Included</td><td style="background:#F7F3FB;font-weight:600">Included</td><td>Build and maintain yourself</td></tr><tr><th scope="row">Time to launch</th><td>Same day</td><td style="background:#F7F3FB;font-weight:600">About 4 weeks</td><td>6–12 months</td></tr><tr><th scope="row">Upfront cost</th><td>None</td><td style="background:#F7F3FB;font-weight:600">One-off setup fee</td><td>Developers, design, hosting</td></tr><tr><th scope="row">Updates &amp; security</th><td>scharge</td><td style="background:#F7F3FB;font-weight:600">scharge</td><td>Your team, forever</td></tr></tbody></table></div>
</div>
<div class="steps"><div class="step" style="border-color:#7B5EA7"><span class="label">Week 1</span><h3>Brand kit &amp; domain</h3><p class="muted">Send your logo, colours and domain. We set up your app name and sender ID.</p></div><div class="step" style="border-color:#0F766E"><span class="label">Week 2</span><h3>Properties &amp; payments</h3><p class="muted">Import units or plots, connect M-Pesa and bank, set prices.</p></div><div class="step" style="border-color:#F2B33D"><span class="label">Week 3</span><h3>Test in the sandbox</h3><p class="muted">Your team tries every journey with test payments.</p></div><div class="step" style="border-color:#DC2626"><span class="label">Week 4</span><h3>Launch</h3><p class="muted">Live on your website and in the app stores under your name.</p></div></div>
</div></section>
<section class="section section--blush" id="uses"><div class="wrap stack-l"><h2>What owners build on scharge</h2><div class="grid"><div class="card card--blush"><span class="label">Landlords &amp; owners</span><h3>Vacancies on your own website</h3><p>Your listings, in your design, with live availability and a &quot;Book viewing&quot; button.</p><b class="small">Copy-paste widget · same day</b></div><div class="card card--blush"><span class="label">Airbnb owners</span><h3>Direct booking calendar</h3><p>Take bookings without platform commission while staying in sync with Airbnb and Booking.com.</p><b class="small">Copy-paste widget · same day</b></div><div class="card card--blush"><span class="label">Developers</span><h3>A buyer and resident app in your name</h3><p>Instalments, snag lists and service charge, branded for your project.</p><b class="small">White label · 2–4 weeks</b></div><div class="card card--blush"><span class="label">Landowners</span><h3>Plot sales on your site</h3><p>Show available plots, take site-visit requests and instalment deposits.</p><b class="small">Widget or API · 1 week</b></div></div></div></section>
<section class="section" id="code" data-tabs><div class="wrap stack-l"><h2>Quickstart</h2><div role="tablist" aria-label="Code examples" class="tabs"><button role="tab" class="tab" id="ct-0" aria-controls="cp-0">Listings widget</button><button role="tab" class="tab" id="ct-1" aria-controls="cp-1">Booking calendar</button><button role="tab" class="tab" id="ct-2" aria-controls="cp-2">REST API</button><button role="tab" class="tab" id="ct-3" aria-controls="cp-3">Webhooks</button></div><div role="tabpanel" id="cp-0" aria-labelledby="ct-0" class="row" hidden><div class="stack" style="flex:1 1 300px"><h3>Vacancies on your website</h3><p class="muted">No coding beyond copy and paste. Listings update the moment a unit becomes vacant or is let.</p><p><b>1.</b> Get a publishable key.</p><p><b>2.</b> Paste the snippet where listings should show.</p><p><b>3.</b> Viewing requests arrive in your dashboard.</p></div><pre class="code" style="flex:2 1 480px;min-width:0"><code>&lt;!-- On your page, e.g. /vacancies --&gt;
&lt;div id=&quot;scharge-listings&quot;&gt;&lt;/div&gt;
&lt;script src=&quot;https://embed.scharge.com/v1/listings.js&quot;
  data-key=&quot;pk_live_••••&quot;
  data-properties=&quot;mwangaza,kijani&quot;
  data-theme=&quot;#1A1214&quot;
  data-font=&quot;inherit&quot;
  data-branding=&quot;hidden&quot;&gt;&lt;/script&gt;</code></pre></div><div role="tabpanel" id="cp-1" aria-labelledby="ct-1" class="row" hidden><div class="stack" style="flex:1 1 300px"><h3>Direct bookings for short stays</h3><p class="muted">Guests see live availability that respects your Airbnb and Booking.com calendars, and pay a deposit on M-Pesa.</p><p><b>1.</b> Connect your channels in scharge.</p><p><b>2.</b> Embed the calendar for each listing.</p><p><b>3.</b> Bookings close the dates everywhere.</p></div><pre class="code" style="flex:2 1 480px;min-width:0"><code>&lt;div id=&quot;scharge-booking&quot;&gt;&lt;/div&gt;
&lt;script src=&quot;https://embed.scharge.com/v1/booking.js&quot;
  data-key=&quot;pk_live_••••&quot;
  data-listing=&quot;air_kilimani_loft&quot;
  data-deposit=&quot;mpesa&quot;
  data-min-nights=&quot;2&quot;&gt;&lt;/script&gt;</code></pre></div><div role="tabpanel" id="cp-2" aria-labelledby="ct-2" class="row" hidden><div class="stack" style="flex:1 1 300px"><h3>Build your own pages</h3><p class="muted">Fetch vacancies, create viewing requests and invoices from your own website or app, server-side.</p><p><b>1.</b> Use your secret key on your server.</p><p><b>2.</b> Call the endpoints you need.</p><p><b>3.</b> Listen for webhooks to update your site.</p></div><pre class="code" style="flex:2 1 480px;min-width:0"><code>GET /v1/listings?status=vacant&amp;property=mwangaza
Authorization: Bearer sk_live_••••

200 OK
{ &quot;data&quot;: [ { &quot;id&quot;: &quot;lst_B07&quot;, &quot;title&quot;: &quot;2 BR, Block B&quot;,
  &quot;rent&quot;: 38000, &quot;water_rate&quot;: 150, &quot;service_charge&quot;: 1600,
  &quot;available_from&quot;: &quot;2026-10-12&quot; } ] }

POST /v1/viewings
{ &quot;listing_id&quot;: &quot;lst_B07&quot;, &quot;name&quot;: &quot;Faith C.&quot;,
  &quot;phone&quot;: &quot;+2547••••••••&quot;, &quot;preferred&quot;: &quot;2026-10-17&quot; }</code></pre></div><div role="tabpanel" id="cp-3" aria-labelledby="ct-3" class="row" hidden><div class="stack" style="flex:1 1 300px"><h3>Keep your systems in step</h3><p class="muted">scharge tells your server when money moves or a tenant applies, signed so you know it came from us.</p><p><b>1.</b> Add your endpoint URL.</p><p><b>2.</b> Verify the signature header.</p><p><b>3.</b> Update your CRM or accounts.</p></div><pre class="code" style="flex:2 1 480px;min-width:0"><code>POST https://yourcompany.co.ke/hooks/scharge
scharge-signature: t=1791823445,v1=••••

{ &quot;type&quot;: &quot;payment.succeeded&quot;,
  &quot;data&quot;: { &quot;invoice&quot;: &quot;inv_8K2Q71&quot;, &quot;unit&quot;: &quot;B04&quot;,
    &quot;amount&quot;: 38445, &quot;channel&quot;: &quot;mpesa&quot;,
    &quot;receipt&quot;: &quot;TJ74KD2X9P&quot; } }</code></pre></div></div></section>
<section class="section section--blush" id="ref"><div class="wrap stack-l"><h2>Reference</h2>
<div class="table-wrap"><table><thead><tr><th>Method</th><th>Endpoint</th><th>What it does</th></tr></thead><tbody><tr><td><span class="pill" style="background:#EAF1FD;color:#1E4FB8">GET</span></td><td><code>/v1/properties</code></td><td class="muted">Your properties and units</td></tr><tr><td><span class="pill" style="background:#EAF1FD;color:#1E4FB8">GET</span></td><td><code>/v1/listings</code></td><td class="muted">Vacant units, short stays and plots</td></tr><tr><td><span class="pill" style="background:#F3FFD0;color:#3B5200">POST</span></td><td><code>/v1/viewings</code></td><td class="muted">Request a viewing or site visit</td></tr><tr><td><span class="pill" style="background:#F3FFD0;color:#3B5200">POST</span></td><td><code>/v1/applications</code></td><td class="muted">Submit a tenant application</td></tr><tr><td><span class="pill" style="background:#EAF1FD;color:#1E4FB8">GET</span></td><td><code>/v1/availability</code></td><td class="muted">Short-stay availability across channels</td></tr><tr><td><span class="pill" style="background:#F3FFD0;color:#3B5200">POST</span></td><td><code>/v1/bookings</code></td><td class="muted">Create a direct booking with M-Pesa deposit</td></tr><tr><td><span class="pill" style="background:#F3FFD0;color:#3B5200">POST</span></td><td><code>/v1/invoices</code></td><td class="muted">Bill rent, water, service charge or instalments</td></tr><tr><td><span class="pill" style="background:#EAF1FD;color:#1E4FB8">GET</span></td><td><code>/v1/payments</code></td><td class="muted">Payments received, with M-Pesa references</td></tr><tr><td><span class="pill" style="background:#EAF1FD;color:#1E4FB8">GET</span></td><td><code>/v1/statements</code></td><td class="muted">Owner and tenant statements</td></tr><tr><td><span class="pill" style="background:#FFF4DB;color:#7A4F00">PATCH</span></td><td><code>/v1/units/{id}</code></td><td class="muted">Update rent, status or listing details</td></tr></tbody></table></div>
<div class="grid"><div class="card"><h3>Webhook events</h3><p><code>payment.succeeded, payment.failed, invoice.overdue, viewing.requested, application.submitted, booking.created, booking.cancelled</code></p></div>
<div class="card"><h3>Security</h3><p>Publishable keys for widgets, secret keys server-side only, signed webhooks, per-property permissions. Rate limit: 120 requests a minute per key, raised on request.</p></div>
<div class="card"><h3>Environments</h3><p>Sandbox with test M-Pesa payments and sample properties. Go live after a short review of your use case.</p></div></div>
<p class="small muted">Endpoint names, the embed host and event names are illustrative until the published API docs replace them.</p></div></section>
<section class="section section--dark"><div class="wrap grid"><div class="card" style="background:#26191C;color:#fff;border:0"><h3>Sandbox</h3><span style="font-family:var(--font-display);font-size:1.4rem">Free</span><p style="color:#D9C9CC">Test keys, sample properties, test M-Pesa.</p></div><div class="card" style="background:#26191C;color:#fff;border:0"><h3>Widgets &amp; API</h3><span style="font-family:var(--font-display);font-size:1.4rem">Included</span><p style="color:#D9C9CC">With any scharge plan: listings widget, booking calendar, REST API and webhooks.</p></div><div class="card" style="background:#DC2626;color:#fff;border:0"><h3>White label</h3><span style="font-family:var(--font-display);font-size:1.4rem">Quoted per portfolio</span><p style="color:#FFFFFF">Your app name, logo, colours and domain, plus location pricing per unit. Hide scharge branding.</p></div></div></section>
<section class="section" id="keys"><div class="wrap"><div class="section--blush row" style="border-radius:28px;padding:clamp(24px,4vw,44px)">
<div class="stack" style="flex:1 1 320px"><h2>Get sandbox keys</h2><p class="lead">Free, instant, with test properties and test M-Pesa. Your web developer can have listings on your site the same day.</p></div>
<div style="flex:1 1 380px;background:#fff;border-radius:22px;padding:22px"><form class="form" data-demo novalidate><div class="fields form"><label for="k-co">Company or property name</label><input id="k-co" name="k-co" type="text" autocomplete="organization" required><label for="k-em">Work email</label><input id="k-em" name="k-em" type="email" autocomplete="email" required><label for="k-use">What will you build?</label><select id="k-use" name="k-use"><option>Vacancies on my website</option><option>Booking calendar for my short stays</option><option>Branded tenant or buyer app</option><option>Connect to my accounting or CRM</option></select><button class="btn btn--primary" type="submit">Send me keys</button></div>
<div class="thanks stack" tabindex="-1"><h3>Check your inbox.</h3><p class="muted">Your sandbox keys and a link to the docs are on their way.</p><button type="button" class="btn btn--ghost" data-reset>Send another request</button></div></form></div>
</div></div></section>
@endsection
