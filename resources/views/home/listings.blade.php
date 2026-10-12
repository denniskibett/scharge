@extends('home.layout')

@section('title', 'Open listings | Verified homes, short stays and plots | scharge')
@section('description', 'Vacant homes, short stays and plots from owners on scharge. Every cost shown, no viewing fees.')

@section('header_cta')
<a class="btn btn--primary" href="#owners">List my vacancy free</a>
@endsection

@section('subnav')
<nav class="subnav" aria-label="On this page"><div class="wrap"><a href="#browse">Browse</a><a href="#how">How renting works</a><a href="#owners">For owners</a></div></nav>
@endsection

@section('content')
<section class="hero" data-slider aria-roledescription="carousel" aria-label="Highlights">
<div class="hero-slide is-active" role="group" aria-roledescription="slide" aria-label="1 of 4">
<div class="hero-bg" style="background-image:url('{{ asset('assets/images/slides/listings-1.jpg') }}')"></div><div class="hero-shade"></div><div class="hero-stripe s-teal"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-teal">Vacant now · Ruaka · Inspected</span>
<h1 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">2 BR apartment, Mwangaza Court</h1>
<p>KES 38,000 a month. Water KES 150/m³, service charge KES 1,600, deposit one month. Borehole, backup power, lift.</p>
<div class="actions"><a class="btn btn--primary" href="#browse">Book a viewing</a><a class="btn btn--glass" href="#browse">Browse all vacancies</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="2 of 4">
<div class="hero-bg" data-bg="assets/images/slides/listings-2.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-jac"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-jac">Short stay · Diani</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">Diani Garden Cottage</h2>
<p>KES 12,000 a night. Two bedrooms, pool, five minutes to the beach. Free from 19 Oct.</p>
<div class="actions"><a class="btn btn--primary" href="#browse">Check dates</a><a class="btn btn--glass" href="#browse">Browse all vacancies</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="3 of 4">
<div class="hero-bg" data-bg="assets/images/slides/listings-3.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-gold"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-gold">Plots · Kitengela</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">50 × 100 plots, 12 of 40 left</h2>
<p>KES 950,000, paid over 12 months. Ready title, water on site, fenced.</p>
<div class="actions"><a class="btn btn--primary" href="#browse">Book a site visit</a><a class="btn btn--glass" href="#browse">Browse all vacancies</a></div>
</div>
</div><div class="hero-slide" role="group" aria-roledescription="slide" aria-label="4 of 4">
<div class="hero-bg" data-bg="assets/images/slides/listings-4.jpg"></div><div class="hero-shade"></div><div class="hero-stripe s-red"></div>
<div class="wrap hero-content">
<span class="hero-kicker k-red">Vacant now · Syokimau · Inspected</span>
<h2 class="hero-title" style="font-size:clamp(2.1rem,5.2vw,4.2rem)">3 BR maisonette with garden</h2>
<p>KES 62,000 a month. DSQ, two parking bays, metered water, service charge KES 3,000.</p>
<div class="actions"><a class="btn btn--primary" href="#browse">Book a viewing</a><a class="btn btn--glass" href="#browse">Browse all vacancies</a></div>
</div>
</div>
<div class="hero-controls"><button class="hero-arrow hero-prev" aria-label="Previous slide">‹</button><button class="hero-dot" aria-label="Show slide 1" aria-current="true"></button><button class="hero-dot" aria-label="Show slide 2" aria-current="false"></button><button class="hero-dot" aria-label="Show slide 3" aria-current="false"></button><button class="hero-dot" aria-label="Show slide 4" aria-current="false"></button><button class="hero-arrow hero-next" aria-label="Next slide">›</button></div>
</section>
<section class="section" id="browse"><div class="wrap stack-l">
<div class="filters"><div role="tablist" aria-label="Listing type" class="tabs"><button role="tab" class="tab" data-kind="0" aria-selected="false">Homes to rent (6)</button><button role="tab" class="tab" data-kind="1" aria-selected="false">Short stays (3)</button><button role="tab" class="tab" data-kind="2" aria-selected="false">Plots for sale (2)</button></div>
<div class="filters-row"><div class="chip-row" style="align-items:center"><span class="small muted" style="font-weight:600">Area</span><span class="chip-row" id="area-chips"></span></div>
<label class="price" for="lmax"><span class="range-label"><span id="lmax-label">Max rent</span><b id="lmax-out" style="color:var(--ink)"></b></span><input id="lmax" type="range"></label>
<label class="check" style="gap:10px"><input id="lver" type="checkbox"> Inspected only</label></div></div>
<div class="row" style="justify-content:space-between;align-items:center"><span class="muted" id="result-line" aria-live="polite"></span><form class="row" style="gap:8px;align-items:center" onsubmit="event.preventDefault();this.querySelector('button').textContent='Alert set'"><label class="small" for="alert-ph">SMS me new matches</label><input id="alert-ph" type="tel" placeholder="07XX XXX XXX" style="min-height:40px;padding:0 10px;border-radius:10px;border:1px solid var(--line);font:inherit;width:150px"><button class="btn btn--ink" style="min-height:40px">Alert me</button></form></div>
<div class="listing-grid" id="listing-grid"></div>
<p id="none" class="card card--blush" hidden>Nothing matches yet. Widen the area or price, or leave your number for an SMS alert.</p>
<div class="modal-backdrop" id="detail-backdrop" hidden></div>
<div class="detail" id="detail" role="dialog" aria-modal="true" aria-labelledby="d-title" hidden><div class="stack"><div class="row" style="justify-content:space-between;align-items:flex-start;gap:10px"><div><h3 id="d-title"></h3><p class="muted" id="d-sub"></p></div><button type="button" class="t-btn" id="d-close" aria-label="Close">×</button></div><div id="d-lines"></div><div class="chip-row" id="d-tags"></div></div>
<div class="card card--blush"><form class="form" id="d-form" data-demo novalidate><div class="fields form"><h3 id="d-form-title"></h3><label for="v-name">Your name</label><input id="v-name" required autocomplete="name"><label for="v-ph">Phone (WhatsApp)</label><input id="v-ph" type="tel" required autocomplete="tel"><label for="d-when" id="d-when-label"></label><input id="d-when"><button class="btn btn--primary" type="submit" id="d-submit"></button><p class="small muted">Free. The owner or caretaker replies on WhatsApp.</p></div><div class="thanks stack" tabindex="-1"><h3>Request sent.</h3><p class="muted" id="d-thanks-msg"></p><button type="button" class="btn btn--ghost" data-reset>Send another request</button></div></form></div></div>
</div></section>
<section class="section section--dark" id="how"><div class="wrap stack-l"><h2>Renting through scharge</h2><div class="grid"><div class="card card--dark"><span class="step-n" style="color:var(--rose)">01</span><h3>See the real cost</h3><p>Rent, water, service charge and deposit on every listing.</p></div><div class="card card--dark"><span class="step-n" style="color:var(--rose)">02</span><h3>Book a viewing free</h3><p>The caretaker confirms a time on WhatsApp.</p></div><div class="card card--dark"><span class="step-n" style="color:var(--rose)">03</span><h3>Apply in the app</h3><p>Upload your ID and payslip once; reuse for any listing.</p></div><div class="card card--dark"><span class="step-n" style="color:var(--rose)">04</span><h3>Pay safely, move in</h3><p>Deposit on M-Pesa to the property account, digital lease, keys.</p></div></div><p class="muted" style="color:var(--on-dark)">Never pay a deposit to a personal number. On scharge, deposits go to the property's account and you get a receipt.</p></div></section>
<section class="section" id="owners"><div class="wrap"><div class="section--red row" style="border-radius:28px;padding:clamp(24px,4vw,48px);align-items:center;justify-content:space-between"><div class="stack" style="max-width:40rem"><h2>Got an empty unit, listing or plot?</h2><p>List it here free with any scharge plan. Viewings, applications, screening and the first deposit all land in your dashboard, and you can show the same listings on your own website.</p></div><div class="actions"><a class="btn btn--light" href="{{ route('home') }}#pricing">List my vacancy</a><a class="btn btn--glass" href="{{ route('home.api') }}">Embed on my website</a></div></div></div></section>
@endsection

@push('page_data')
@verbatim
<script type="application/json" id="listings-data">{"items": [{"k": 0, "title": "2 BR apartment, Block B", "area": "Ruaka", "price": 38000, "avail": "Available now", "costs": "Water KES 150/m³ · service KES 1,600 · deposit 1 month", "verified": true, "owner": "Mwangaza Court", "tags": ["Borehole", "Backup power", "Parking", "Lift"], "id": 0, "photo": "assets/images/listings/listing-1.jpg"}, {"k": 0, "title": "Studio", "area": "Ruaka", "price": 16500, "avail": "From 1 Nov", "costs": "Water metered · service KES 900", "verified": true, "owner": "Kijani Apartments", "tags": ["Guard", "Water tank"], "id": 1, "photo": "assets/images/listings/listing-2.jpg"}, {"k": 0, "title": "3 BR maisonette", "area": "Syokimau", "price": 62000, "avail": "Available now", "costs": "Water metered · service KES 3,000", "verified": true, "owner": "Private landlord", "tags": ["Garden", "DSQ", "Parking ×2"], "id": 2, "photo": "assets/images/listings/listing-3.jpg"}, {"k": 0, "title": "1 BR apartment", "area": "Kilimani", "price": 55000, "avail": "From 15 Nov", "costs": "Water included · service KES 4,500", "verified": false, "owner": "Lenana Place", "tags": ["Gym", "Lift", "Backup power"], "id": 3, "photo": "assets/images/listings/listing-4.jpg"}, {"k": 0, "title": "Bedsitter", "area": "Kitengela", "price": 8500, "avail": "Available now", "costs": "Water tokens · no service charge", "verified": true, "owner": "Baraka Flats", "tags": ["Tiled", "Shared parking"], "id": 4, "photo": "assets/images/listings/listing-5.jpg"}, {"k": 0, "title": "2 BR apartment", "area": "Kasarani", "price": 26000, "avail": "Available now", "costs": "Water metered · service KES 1,200", "verified": true, "owner": "Tulivu Gardens", "tags": ["Balcony", "Guard"], "id": 5, "photo": "assets/images/listings/listing-6.jpg"}, {"k": 1, "title": "Kilimani Loft", "area": "Kilimani", "price": 7400, "avail": "Free 17–18 Oct", "costs": "Cleaning KES 1,500 · deposit held", "verified": true, "owner": "Hosted on scharge", "tags": ["Smart lock", "Wi-Fi", "Workspace"], "id": 6, "photo": "assets/images/listings/listing-7.jpg"}, {"k": 1, "title": "Diani Garden Cottage", "area": "Diani", "price": 12000, "avail": "Free from 19 Oct", "costs": "Cleaning KES 2,500", "verified": true, "owner": "Hosted on scharge", "tags": ["Pool", "2 BR", "Beach 5 min"], "id": 7, "photo": "assets/images/listings/listing-8.jpg"}, {"k": 1, "title": "Westlands Studio", "area": "Westlands", "price": 5200, "avail": "Free 13–20 Oct", "costs": "Cleaning KES 1,000", "verified": false, "owner": "Hosted on scharge", "tags": ["Wi-Fi", "Lift"], "id": 8, "photo": "assets/images/listings/listing-9.jpg"}, {"k": 2, "title": "50 × 100 plot", "area": "Kitengela", "price": 950000, "avail": "12 of 40 left", "costs": "Pay over 12 months · title on completion", "verified": true, "owner": "Landowner via scharge", "tags": ["Ready title", "Water on site", "Fenced"], "id": 9, "photo": "assets/images/listings/listing-10.jpg"}, {"k": 2, "title": "1/8 acre plot", "area": "Juja", "price": 1450000, "avail": "6 of 18 left", "costs": "30% deposit, then 9 months", "verified": true, "owner": "Landowner via scharge", "tags": ["Road access", "Power nearby"], "id": 10, "photo": "assets/images/listings/listing-11.jpg"}], "kinds": [{"name": "Homes to rent", "per": "/ month", "priceLabel": "Max rent", "min": 5000, "max": 150000, "step": 5000, "def": 150000, "btn": "Book a viewing", "formTitle": "Book a viewing", "whenLabel": "Preferred day", "whenHint": "e.g. Saturday morning", "formBtn": "Request viewing", "sentMsg": "The caretaker will WhatsApp you a time today."}, {"name": "Short stays", "per": "/ night", "priceLabel": "Max per night", "min": 2000, "max": 30000, "step": 500, "def": 30000, "btn": "Check dates", "formTitle": "Request these dates", "whenLabel": "Check-in and check-out", "whenHint": "e.g. 17–18 Oct", "formBtn": "Request booking", "sentMsg": "The host confirms on WhatsApp, then you pay a deposit on M-Pesa."}, {"name": "Plots for sale", "per": "total", "priceLabel": "Max price", "min": 300000, "max": 5000000, "step": 50000, "def": 5000000, "btn": "Book a site visit", "formTitle": "Book a site visit", "whenLabel": "Preferred day", "whenHint": "Site visits on Saturdays", "formBtn": "Request site visit", "sentMsg": "The landowner’s team will call to arrange transport to the site."}]}</script>
@endverbatim
@endpush
