@extends('home.layout')

@section('title', 'scharge for Airbnb hosts | One calendar, no double bookings')
@section('description', 'Calendar sync, smart-lock codes, cleaner checklists and payouts for Airbnb and short-stay hosts across Africa, starting in Kenya.')

@section('header_cta')
<a class="btn btn--primary" href="#calc">Add my listing</a>
@endsection

@section('subnav')
<nav class="subnav" aria-label="On this page"><div class="wrap"><a href="#sync">Calendar sync</a><a href="#calc">Pricing</a><a href="#features">Features</a><a href="#how">How a stay runs</a><a href="#faq">FAQ</a></div></nav>
@endsection

@section('content')
<section class="hero" data-slider aria-roledescription="carousel" aria-label="Highlights">
<div class="hero-slide is-active" role="group" aria-roledescription="slide" aria-label="1 of 4">
<div class="hero-bg" style="background-image:url('{{ asset('assets/images/slides/airbnb-1.jpg') }}')"></div><div class="hero-shade"></div><div class="hero-stripe s-jac"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-jac">For Airbnb owners</span>
<h1 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Host more. Answer the phone less.</h1>
<p>Lock codes, Wi-Fi and gate passes go to guests automatically. Cleaners tick off a photo checklist. You see what each listing really earns.</p>
<div class="actions"><a class="btn btn--primary" href="#calc">Work out my cost</a><a class="btn btn--glass" href="#calc">Work out my cost</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="2 of 4">
<div class="hero-bg" data-bg="assets/images/slides/airbnb-2.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-teal"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-teal">Calendar sync</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">One calendar. No double bookings.</h2>
<p>Airbnb, Booking.com and direct guests in one place. A night booked anywhere closes everywhere else.</p>
<div class="actions"><a class="btn btn--primary" href="#sync">See the sync</a><a class="btn btn--glass" href="#calc">Work out my cost</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="3 of 4">
<div class="hero-bg" data-bg="assets/images/slides/airbnb-3.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-gold"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-gold">Direct bookings</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Your website. Your guests. No commission.</h2>
<p>Embed a booking calendar on your own site and take an M-Pesa deposit. Repeat guests book you directly.</p>
<div class="actions"><a class="btn btn--primary" href="{{ route('home.api') }}#code">See the booking widget</a><a class="btn btn--glass" href="#calc">Work out my cost</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="4 of 4">
<div class="hero-bg" data-bg="assets/images/slides/airbnb-4.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-red"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-red">Turnovers</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Every clean checked with photos.</h2>
<p>Your cleaner gets the checklist, you get the photos, and damage claims come with proof.</p>
<div class="actions"><a class="btn btn--primary" href="#how">How a stay runs</a><a class="btn btn--glass" href="#calc">Work out my cost</a></div>
</div>
</div>
<div class="hero-controls"><button class="hero-arrow hero-prev" aria-label="Previous slide">‹</button><button class="hero-dot" aria-label="Show slide 1" aria-current="true"></button><button class="hero-dot" aria-label="Show slide 2" aria-current="false"></button><button class="hero-dot" aria-label="Show slide 3" aria-current="false"></button><button class="hero-dot" aria-label="Show slide 4" aria-current="false"></button><button class="hero-arrow hero-next" aria-label="Next slide">›</button></div>
</section>
<section class="section" id="calc"><div class="wrap stack-l">
<div class="stack"><h2>A flat fee per listing. Never a cut of your bookings.</h2><p class="lead">Your price depends on the area. Your bookings stay yours.</p></div>
<div class="row" style="align-items:flex-start" id="host-calc">
<div class="calc" style="flex:1 1 420px;border:1px solid var(--line)"><b>Area</b><div class="band-grid"><button type="button" class="band" data-hband="0" aria-pressed="false"><b>Prime · KES 1,500</b><small>e.g. Westlands, Kilimani, Karen</small></button><button type="button" class="band" data-hband="1" aria-pressed="false"><b>Upmarket · KES 1,000</b><small>e.g. Kileleshwa, Lavington, Nyali</small></button><button type="button" class="band" data-hband="2" aria-pressed="false"><b>Mid-market · KES 700</b><small>e.g. Ruaka, Kasarani, Thika Rd</small></button><button type="button" class="band" data-hband="3" aria-pressed="false"><b>Affordable · KES 400</b><small>e.g. Kitengela, Ruiru, Rongai</small></button></div>
<label class="range-label" for="h-listings"><span>Listings</span><b id="h-listings-out">2</b></label><input id="h-listings" type="range" min="1" max="30" value="2">
<label class="range-label" for="h-nightly"><span>Average nightly rate</span><b id="h-nightly-out">KES 7,500</b></label><input id="h-nightly" type="range" min="2000" max="30000" step="500" value="7500">
<label class="range-label" for="h-occ"><span>Nights booked</span><b id="h-occ-out">65%</b></label><input id="h-occ" type="range" min="20" max="95" step="5" value="65"></div>
<div class="stack" style="flex:1 1 380px"><div class="result" aria-live="polite"><span class="small muted" id="h-summary"></span><span class="amount" id="h-cost"></span><span class="small muted">per month</span><span class="small muted" id="h-share"></span>
<div class="kv" style="border-color:#3a2a2d"><span class="muted">A co-host at 20% would cost</span><b id="h-cohost"></b></div><div class="kv" style="border:0"><span class="muted">Pay yearly (12 months for 10)</span><b id="h-yearly"></b></div>
<a class="btn btn--primary" href="{{ route('home.listings') }}#owners" style="align-self:flex-start">Add my first listing</a></div>
<div class="card"><p><b style="color:var(--ink)">30+ listings or a serviced-apartment block?</b> Talk to us for a tailored quote. <a href="{{ route('home.developers') }}#talk">Talk to sales</a></p></div></div>
</div></div></section>
<section class="section section--blush" id="sync"><div class="wrap stack-l">
<div class="stack"><span class="label">Calendar sync and listings</span><h2>Every listing, one calendar. No double bookings.</h2><p class="lead">Connect Airbnb, Booking.com and your direct bookings. When a night is booked on one, it closes on the others.</p></div>
<div class="sync" id="sync-demo"><div class="row" style="justify-content:space-between;align-items:center"><b>Kilimani Loft, 12 to 25 Oct</b><div class="toggle-group" role="group" aria-label="Sync"><button type="button" data-sync="on" aria-pressed="true">With scharge sync</button><button type="button" data-sync="off" aria-pressed="false">Without sync</button></div></div>
<div class="table-wrap" style="border:0"><div class="sync-grid"></div></div><div class="sync-note" aria-live="polite"></div></div>
<div class="row"><div class="calc" style="flex:1 1 420px"><b>Manage all your listings in one place</b><div class="kv" style="flex-wrap:wrap;align-items:center"><span style="display:flex;flex-direction:column;flex:1 1 180px"><b>Kilimani Loft</b><span class="small muted">1 BR, 2 guests, smart lock</span></span><b>KES 7,400 <span class="small muted">/ night</span></b><span class="chip-row"><span class=pill style=background:var(--blush);color:var(--red-deep)>Airbnb</span><span class=pill style=background:var(--blush);color:var(--red-deep)>Booking</span><span class=pill style=background:var(--blush);color:var(--red-deep)>Direct</span></span><span class="small" style="color:var(--red);font-weight:600">81% booked</span></div><div class="kv" style="flex-wrap:wrap;align-items:center"><span style="display:flex;flex-direction:column;flex:1 1 180px"><b>Westlands Studio</b><span class="small muted">Studio, 2 guests</span></span><b>KES 5,200 <span class="small muted">/ night</span></b><span class="chip-row"><span class=pill style=background:var(--blush);color:var(--red-deep)>Airbnb</span><span class=pill style=background:var(--blush);color:var(--red-deep)>Direct</span></span><span class="small" style="color:var(--red);font-weight:600">74% booked</span></div><div class="kv" style="flex-wrap:wrap;align-items:center"><span style="display:flex;flex-direction:column;flex:1 1 180px"><b>Diani Garden Cottage</b><span class="small muted">2 BR, 4 guests, pool</span></span><b>KES 12,000 <span class="small muted">/ night</span></b><span class="chip-row"><span class=pill style=background:var(--blush);color:var(--red-deep)>Airbnb</span><span class=pill style=background:var(--blush);color:var(--red-deep)>Booking</span></span><span class="small" style="color:var(--red);font-weight:600">62% booked</span></div><p class="small muted">Change prices, minimum nights and blocked dates once; scharge updates every connected channel.</p></div>
<div class="calc section--dark" style="flex:1 1 420px"><b>How every stay runs</b><div style="display:flex;gap:12px"><span class="t-logo" style="width:30px;height:30px;border-radius:50%;background:var(--red);color:#fff;font-size:12px">1</span><span><b>Guest books</b><br><span class="small" style="color:var(--on-dark)">On Airbnb, Booking.com or your direct link. Other channels close.</span></span></div><div style="display:flex;gap:12px"><span class="t-logo" style="width:30px;height:30px;border-radius:50%;background:var(--red);color:#fff;font-size:12px">2</span><span><b>ID and deposit</b><br><span class="small" style="color:var(--on-dark)">Guest ID checked; damage deposit held on direct bookings.</span></span></div><div style="display:flex;gap:12px"><span class="t-logo" style="width:30px;height:30px;border-radius:50%;background:var(--red);color:#fff;font-size:12px">3</span><span><b>Codes sent</b><br><span class="small" style="color:var(--on-dark)">Smart-lock code, gate pass for the guard, Wi-Fi and directions.</span></span></div><div style="display:flex;gap:12px"><span class="t-logo" style="width:30px;height:30px;border-radius:50%;background:var(--red);color:#fff;font-size:12px">4</span><span><b>Stay</b><br><span class="small" style="color:var(--on-dark)">Guest messages and issues land in one inbox.</span></span></div><div style="display:flex;gap:12px"><span class="t-logo" style="width:30px;height:30px;border-radius:50%;background:var(--red);color:#fff;font-size:12px">5</span><span><b>Turnover</b><br><span class="small" style="color:var(--on-dark)">Cleaner checklist with photos; damage claims if needed.</span></span></div><div style="display:flex;gap:12px"><span class="t-logo" style="width:30px;height:30px;border-radius:50%;background:var(--red);color:#fff;font-size:12px">6</span><span><b>Payout report</b><br><span class="small" style="color:var(--on-dark)">Earnings per listing and channel, net of cleaning and fees.</span></span></div><a class="btn btn--primary" href="#calc" style="align-self:flex-start">Work out my cost</a></div></div>
</div></section>
<section class="section"><div class="wrap"><div class="section--dark row" style="border-radius:28px;padding:clamp(24px,4vw,44px);align-items:center">
<div class="stack" style="flex:1 1 360px"><span class="label">Direct bookings</span><h2>Take bookings on your own website, with no platform commission.</h2><p class="lead">Embed a booking calendar that shows your real availability across Airbnb and Booking.com, takes an M-Pesa deposit and closes the dates everywhere.</p></div>
<div class="actions" style="flex:1 1 280px"><a class="btn btn--primary" href="{{ route('home.api') }}#code">See the booking widget</a><a class="btn btn--glass" href="{{ route('home.listings') }}">Your listings on open listings</a></div></div></div></section>
<section class="section section--dark" id="features"><div class="wrap stack-l"><h2>Everything between the booking and the payout.</h2><div class="grid"><div class="card card--dark"><h3>Smart-lock codes</h3><p>A unique code for each stay, valid only from check-in to checkout.</p></div><div class="card card--dark"><h3>Gate pass for the guard</h3><p>Your building’s guard gets the guest’s name and car plate before they arrive.</p></div><div class="card card--dark"><h3>Auto check-in guide</h3><p>Directions, Wi-Fi and house rules sent on WhatsApp the morning of arrival.</p></div><div class="card card--dark"><h3>Guest ID check</h3><p>Passport or ID collected before the code is released.</p></div><div class="card card--dark"><h3>Cleaner checklists</h3><p>Photo proof of every turnover, restock list and damage reports.</p></div><div class="card card--dark"><h3>Damage deposits</h3><p>Hold a deposit and claim against it with the cleaner’s photos.</p></div><div class="card card--dark"><h3>Direct bookings on M-Pesa</h3><p>Repeat guests book with you directly, no platform commission.</p></div><div class="card card--dark"><h3>Earnings per listing</h3><p>Bookings minus cleaning, restocking and fees, exportable for your accountant.</p></div><div class="card card--dark"><h3>Airbnb &amp; Booking.com calendar sync</h3><p>One calendar, no double bookings.</p></div></div></div></section>
<section class="section" id="how"><div class="wrap stack-l"><h2>How a stay runs on scharge.</h2><div class="steps"><div class="step"><span class="label">Booked</span><h3>Guest books</h3><p class="muted">On Airbnb, Booking.com or directly on M-Pesa.</p></div><div class="step"><span class="label">Day before</span><h3>ID &amp; deposit</h3><p class="muted">Guest uploads ID; deposit held.</p></div><div class="step"><span class="label">Morning of</span><h3>Codes sent</h3><p class="muted">Lock code, Wi-Fi, directions; guard gets the gate pass.</p></div><div class="step"><span class="label">During</span><h3>Stay</h3><p class="muted">Guest messages and issues in one thread.</p></div><div class="step"><span class="label">Checkout</span><h3>Turnover</h3><p class="muted">Cleaner runs the photo checklist; code expires.</p></div><div class="step"><span class="label">Month end</span><h3>Payout report</h3><p class="muted">Net earnings per listing and channel.</p></div></div></div></section>
<section class="section section--blush" id="faq"><div class="wrap stack-l" style="max-width:860px"><h2>Host questions</h2><div class="faq stack"><details><summary>Do you take a percentage of my bookings?</summary><p>No. You pay a flat monthly fee per listing based on the area. Your bookings are yours.</p></details><details><summary>Do you replace a co-host?</summary><p>We replace the systems: codes, messages, checklists and reports. Someone still needs to clean and handle emergencies, usually your caretaker or cleaner, who use scharge free.</p></details><details><summary>Which smart locks work?</summary><p>Wi-Fi smart locks that run on TTLock, igloohome or Yale apps, the brands most sold in Kenya. No smart lock yet? Use the key-handover checklist and we’ll suggest one.</p></details><details><summary>Will it connect to my Airbnb account?</summary><p>Calendar sync with Airbnb and Booking.com is coming soon. Join the waitlist and we’ll set it up for you first.</p></details><details><summary>I also have long-term tenants. One account?</summary><p>Yes. Short stays and long lets on one dashboard, each priced by its own type and area.</p></details><details><summary>Is guest data safe?</summary><p>Yes. We follow Kenya’s Data Protection Act, 2019, and the data law of each country we launch in. Guest IDs are encrypted, shown only to you and your team, and deleted 90 days after checkout unless there is an open damage claim.</p></details></div></div></section>
<section class="section"><div class="wrap stack-l">
<h2>Your money, your data, our promise.</h2>
<div class="grid">
<div class="card"><h3>Where your money sits</h3><p>Tenants and guests pay straight into your own M-Pesa Paybill, Till or bank account. scharge matches and receipts every payment but never holds your money, so payouts are as fast as your bank.</p></div>
<div class="card"><h3>How we protect data</h3><p>We handle tenant and guest data under Kenya’s Data Protection Act, 2019, and the data protection law of every country we launch in. IDs are encrypted, visible only to people you choose, and deleted when you ask.</p></div>
<div class="card section--red" style="border:0"><h3>Free move-in</h3><p style="color:#fff">We import your units, listings and history for free, and your first collection unlocks 2 extra months.</p></div>
</div></div></section>
<section class="section section--red"><div class="wrap row" style="align-items:center;justify-content:space-between"><div class="stack"><h2>Your next guest checks in on scharge.</h2><p>Set up your first listing in 15 minutes. Free for your cleaner and caretaker.</p></div><a class="btn btn--light" href="#calc">Add my listing</a></div></section>
@endsection

@push('page_data')
@verbatim
<script type="application/json" id="host-data">{"bands": [{"name": "Prime", "eg": "e.g. Westlands, Kilimani, Karen", "rate": 1500}, {"name": "Upmarket", "eg": "e.g. Kileleshwa, Lavington, Nyali", "rate": 1000}, {"name": "Mid-market", "eg": "e.g. Ruaka, Kasarani, Thika Rd", "rate": 700}, {"name": "Affordable", "eg": "e.g. Kitengela, Ruiru, Rongai", "rate": 400}]}</script>
@endverbatim
@endpush
