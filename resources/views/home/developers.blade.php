@extends('home.layout')

@section('title', 'scharge for developers and landowners')
@section('description', 'Buyer instalments, snag lists, service charge and handover for developers and landowners across Africa, starting in Kenya.')

@section('header_cta')
<a class="btn btn--primary" href="#talk">Book a meeting</a>
@endsection

@section('subnav')
<nav class="subnav" aria-label="On this page"><div class="wrap"><a href="#dev">Developers</a><a href="#land">Landowners</a><a href="#how">How it works</a><a href="#talk">Book a meeting</a></div></nav>
@endsection

@section('content')
<section class="hero" data-slider aria-roledescription="carousel" aria-label="Highlights">
<div class="hero-slide is-active" role="group" aria-roledescription="slide" aria-label="1 of 4">
<div class="hero-bg" style="background-image:url('{{ asset('assets/images/slides/developers-1.jpg') }}')"></div><div class="hero-shade"></div><div class="hero-stripe s-teal"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-teal">For property developers</span>
<h1 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Sell faster. Hand over cleaner.</h1>
<p>Buyer instalments, snag lists and service charge visible from day one, so buyers trust the project and you keep income after handover.</p>
<div class="actions"><a class="btn btn--primary" href="#talk">Book a 30-minute meeting</a><a class="btn btn--glass" href="#dev">See what you get</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="2 of 4">
<div class="hero-bg" data-bg="assets/images/slides/developers-2.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-gold"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-gold">For landowners</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Every plot, buyer and shilling on one record.</h2>
<p>Sell plots on instalments, collect ground rent and see your joint-venture share as it comes in.</p>
<div class="actions"><a class="btn btn--primary" href="#land">See landowner tools</a><a class="btn btn--glass" href="#dev">See what you get</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="3 of 4">
<div class="hero-bg" data-bg="assets/images/slides/developers-3.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-jac"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-jac">Handover</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Buyers move into a building that already collects.</h2>
<p>Owners, service charge, caretakers and the management company set up before the keys change hands.</p>
<div class="actions"><a class="btn btn--primary" href="#how">How it works</a><a class="btn btn--glass" href="#dev">See what you get</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="4 of 4">
<div class="hero-bg" data-bg="assets/images/slides/developers-4.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-red"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-red">Banks &amp; investors</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Reports your bank will trust.</h2>
<p>Live sales, collections and arrears per block, exported monthly for lenders, investors and auditors.</p>
<div class="actions"><a class="btn btn--primary" href="#talk">Book a meeting</a><a class="btn btn--glass" href="#dev">See what you get</a></div>
</div>
</div>
<div class="hero-controls"><button class="hero-arrow hero-prev" aria-label="Previous slide">‹</button><button class="hero-dot" aria-label="Show slide 1" aria-current="true"></button><button class="hero-dot" aria-label="Show slide 2" aria-current="false"></button><button class="hero-dot" aria-label="Show slide 3" aria-current="false"></button><button class="hero-dot" aria-label="Show slide 4" aria-current="false"></button><button class="hero-arrow hero-next" aria-label="Next slide">›</button></div>
</section>
<section class="section" id="dev"><div class="wrap stack-l">
<div class="stack" style="max-width:48rem"><span class="label">Developers</span><h2>Sell faster. Hand over cleaner. Earn after handover.</h2><p class="lead">Buyers trust a project where payments, defects and service charge are visible from day one, and you keep a recurring revenue line once the last unit is sold.</p></div>
<div class="row" style="align-items:stretch"><div class="grid" style="flex:3 1 520px"><div class="card card--blush"><span class="badge badge--soon">Coming soon</span><h3>Off-plan instalments</h3><p>Buyer deposits and instalments on M-Pesa and bank, with statements per buyer.</p></div><div class="card card--blush"><h3>Investor &amp; bank reports</h3><p>Sales, collections and arrears per block, exportable monthly.</p></div><div class="card card--blush"><h3>Snag lists</h3><p>Buyers report defects with photos during the liability period.</p></div><div class="card card--blush"><h3>Service charge from day one</h3><p>Budgets, sinking fund and statements ready at handover.</p></div><div class="card card--blush"><h3>Handover in one step</h3><p>Transfer the building, owners and history to the management company.</p></div><div class="card card--blush"><span class="badge badge--soon">Coming soon</span><h3>Recurring revenue</h3><p>Earn a share as the building’s appointed manager, or a referral fee.</p></div></div>
<figure class="t-card t-teal" style="flex:2 1 320px"><span class="t-mark" aria-hidden="true">“</span><blockquote>Buyers always ask how the building will be run after handover. Now we show them on their phone, before they sign.</blockquote><figcaption><span class="t-logo">DK</span><span style="display:flex;flex-direction:column"><b>Danaff Kenya Co. Ltd</b><span class="small" style="opacity:.85">Property developer</span></span></figcaption></figure></div>
</div></section>
<section class="section section--blush" id="land"><div class="wrap row">
<div class="stack" style="flex:1 1 380px"><span class="label">Landowners</span><h2>Every plot, buyer and shilling on one record.</h2><p class="lead">Whether you sell plots on instalments, lease land or partner with a developer, you see what is owed, what is paid and what is yours.</p></div>
<div class="grid" style="flex:1 1 520px"><div class="card"><span class="badge badge--soon">Coming soon</span><h3>Plot sales on instalments</h3><p>Buyers pay on M-Pesa; balances and statements update automatically.</p></div><div class="card"><h3>Ground rent &amp; land leases</h3><p>Billed, chased and receipted automatically.</p></div><div class="card"><span class="badge badge--soon">Coming soon</span><h3>Joint-venture statements</h3><p>Your share of every unit sold or let, as it happens.</p></div><div class="card"><h3>Document vault</h3><p>Titles, sale agreements and receipts in one place.</p></div></div>
</div></section>
<section class="section" id="how"><div class="wrap stack-l"><h2>One project, four stages.</h2><div class="steps"><div class="step" style="border-color:#FCA5A5"><span class="step-n">01</span><h3>Sell</h3><p class="muted">Off-plan buyers and plot buyers pay in instalments.</p></div><div class="step" style="border-color:#F87171"><span class="step-n">02</span><h3>Build</h3><p class="muted">Investor and bank reports from live sales data.</p></div><div class="step" style="border-color:#DC2626"><span class="step-n">03</span><h3>Hand over</h3><p class="muted">Snag lists closed, owners and service charge set up.</p></div><div class="step" style="border-color:#991B1B"><span class="step-n">04</span><h3>Manage</h3><p class="muted">Rent, service charge and repairs, collecting from day one.</p></div></div>
<div class="card row" style="flex-direction:row;align-items:center;justify-content:space-between"><div class="stack" style="flex:1 1 320px"><span class="label">Handover day</span><h3>Keys, app login and first invoice, on the same day.</h3><p>Each buyer gets their keys, a resident app login, their first service charge invoice and the caretaker’s number before they leave the show flat. Snags reported that week go straight to your site team.</p></div><div class="stack" style="flex:0 1 260px;gap:8px"><a class="btn btn--primary" href="#talk">Book a walkthrough</a><span class="small muted">30 minutes with our projects team, on site or on video.</span></div></div>
</div></section>
<section class="section section--blush"><div class="wrap grid">
<div class="card"><h3>Enterprise pricing</h3><p>Per unit or per plot, agreed per project. Developers get 3 months free on each block handed over through scharge.</p></div>
<div class="card"><h3>Your brand, if you want it</h3><p>Buyer and resident apps in your project's name, powered by scharge. API access for your CRM and accounts. <a href="{{ route('home.api') }}">See white label</a></p></div>
<div class="card"><h3>Money handled properly</h3><p>Buyer instalments go straight to the project’s own bank or escrow account, with every payment receipted and reconciled. Reports your bank and auditors can rely on.</p></div>
</div></section>
<section class="section" id="talk"><div class="wrap"><div class="section--dark row" style="border-radius:28px;padding:clamp(24px,4vw,48px)">
<div class="stack" style="flex:1 1 340px"><h2>Book 30 minutes with our projects team.</h2><p class="lead">Bring your site plan or plot list. You leave with a setup plan and a quote within one working day.</p></div>
<div style="flex:1 1 380px;background:#fff;color:var(--ink);border-radius:22px;padding:24px"><form class="form" data-demo novalidate><div class="fields form"><label for="d-name">Name and company</label><input id="d-name" name="d-name" type="text" autocomplete="organization" required><label for="d-phone">Phone or WhatsApp</label><input id="d-phone" name="d-phone" type="tel" autocomplete="tel" required><label for="d-country">Country</label><select id="d-country" name="d-country"><option>Kenya</option><option>Uganda</option><option>Tanzania</option><option>Rwanda</option><option>Another African country</option></select><label for="d-kind">Project</label><select id="d-kind" name="d-kind"><option>Apartments, off-plan</option><option>Apartments, completed</option><option>Plots on instalments</option><option>Joint venture with a developer</option><option>Commercial or mixed use</option></select><label for="d-size">Units or plots</label><input id="d-size" name="d-size" type="number" min="1" required><button class="btn btn--primary" type="submit">Book my meeting</button></div>
<div class="thanks stack" tabindex="-1"><h3>Thank you.</h3><p class="muted">We&#x27;ll call to confirm a time within one working day.</p><button type="button" class="btn btn--ghost" data-reset>Send another request</button></div></form></div>
</div></div></section>
<section class="section"><div class="wrap stack-l">
<h2>Your money, your data, our promise.</h2>
<div class="grid">
<div class="card"><h3>Where your money sits</h3><p>Tenants and guests pay straight into your own M-Pesa Paybill, Till or bank account. scharge matches and receipts every payment but never holds your money, so payouts are as fast as your bank.</p></div>
<div class="card"><h3>How we protect data</h3><p>We handle tenant and guest data under Kenya’s Data Protection Act, 2019, and the data protection law of every country we launch in. IDs are encrypted, visible only to people you choose, and deleted when you ask.</p></div>
<div class="card section--red" style="border:0"><h3>Free move-in</h3><p style="color:#fff">We import your units, listings and history for free, and your first collection unlocks 2 extra months.</p></div>
</div></div></section>
@endsection
