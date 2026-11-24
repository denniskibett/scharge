{{-- One listing's own page (HomeController@listing). Data: App\Services\ListingCatalog item. --}}
@extends('home.layout')
@php
    $stay = $item['k'] === \App\Services\ListingCatalog::KIND_STAY;
    $per = $stay ? 'a night' : 'a month';
    $kind = $stay ? 'Short stay' : 'House to rent';
    $action = $stay ? 'Request these dates' : 'Book a viewing';
    $price = 'KES '.number_format($item['price']);
@endphp

@section('title', $item['title'].', '.$item['area'].' · '.$price.($stay ? '/night' : '/month').' | scharge')
@section('description', $kind.' in '.$item['area'].': '.$item['title'].', '.$price.' '.$per.'. '.$item['costs'].'. '.$item['avail'].'. '.($stay ? 'Request your dates free; the host confirms on WhatsApp.' : 'Book a viewing free, no agent fees.'))
@section('canonical', $item['url'])
@if (! empty($item['photos'][0]))
@section('og_image', $item['photos'][0])
@endif

@section('header_cta')
<a class="btn btn--primary" href="#request">{{ $action }}</a>
@endsection

@section('content')
<section class="section"><div class="wrap stack-l">
  <nav aria-label="Breadcrumb" class="small muted"><a href="{{ route('home') }}">Home</a> › <a href="{{ route('home.listings') }}">Open listings</a> › <a href="{{ route('home.listings', $stay ? ['type' => 'stays'] : []) }}#browse">{{ $stay ? 'Short stays' : 'Homes to rent' }}</a> › <span aria-current="page">{{ $item['title'] }}</span></nav>
  <div class="row" style="justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap">
    <div class="stack" style="max-width:44rem">
      <div class="chip-row" style="align-self:flex-start">@if ($stay)<span class="pill" style="background:var(--jac);color:#FFF">Short stay · BnB · priced per night</span>@else<span class="pill" style="background:var(--teal);color:#FFF">House to rent · priced per month</span>@endif<span class="pill" style="background:var(--blush)">{{ $item['avail'] }}</span></div>
      <h1 style="font-size:clamp(1.8rem,4vw,3rem)">{{ $item['title'] }}</h1>
      <p class="muted">@if ($stay)Furnished short stay at {{ $item['owner'] }}, {{ $item['area'] }}@if (! empty($item['host'])), hosted by <b>{{ $item['host'] }}</b>@endif. Book by the night; this is not a monthly rental.@else{{ $kind }} in {{ $item['area'] }}, listed by {{ $item['owner'] }}.@endif</p>
    </div>
    <p class="listing-price" style="font-size:1.6rem">{{ $price }} <span class="small muted" style="font-family:var(--font-body)">{{ $per }}</span></p>
  </div>

  @if (! empty($item['photos']))
    <div class="gallery" data-gallery>
      <div class="gallery-track">
        @foreach (array_slice($item['photos'], 0, 12) as $i => $photo)
          <a class="gallery-item" href="{{ $photo }}" target="_blank" rel="noopener"><img src="{{ $photo }}" alt="{{ $item['title'] }}, photo {{ $i + 1 }}" loading="{{ $i < 2 ? 'eager' : 'lazy' }}" width="640" height="420"></a>
        @endforeach
      </div>
      @if (count($item['photos']) > 1)
        <button type="button" class="gallery-nav gallery-prev" data-gallery-prev aria-label="Previous photo">‹</button>
        <button type="button" class="gallery-nav gallery-next" data-gallery-next aria-label="Next photo">›</button>
        <span class="gallery-count"></span>
      @endif
    </div>
  @else
    <img src="{{ $item['photo'] }}" alt="" width="640" height="420" style="width:100%;max-width:640px;border-radius:16px">
  @endif

  @if ($stay)
    {{-- Free and taken nights for the next three months (drawn by main.js stayCalendar from ListingCatalog's "booked"). --}}
    <div class="stack">
      <h2 style="font-size:1.3rem">Free dates</h2>
      <p class="muted">{{ $item['avail'] }}. Green nights are free. Tap your check-in, then your check-out, and the dates go into the request below.</p>
      <div id="stay-cal" data-today="{{ now('Africa/Nairobi')->toDateString() }}" data-booked='@json($item['booked'] ?? [])'><noscript>Ask the host for free dates.</noscript></div>
    </div>
  @endif

  <div class="detail-page" style="display:flex;flex-wrap:wrap;gap:24px">
    <div class="stack" style="flex:1 1 300px">
      <h2 style="font-size:1.3rem">Every cost, up front</h2>
      <div>
        <div class="kv"><span class="muted">{{ $stay ? 'Per night' : 'Rent' }}</span><b>{{ $price }}</b></div>
        @if (! empty($item['host']))<div class="kv"><span class="muted">Hosted by</span><b>{{ $item['host'] }}</b></div>@endif
        <div class="kv"><span class="muted">Other costs</span><b>{{ $item['costs'] }}</b></div>
        <div class="kv"><span class="muted">Availability</span><b>{{ $item['avail'] }}</b></div>
        <div class="kv"><span class="muted">Inspected by scharge</span><b>{{ $item['verified'] ? 'Yes' : 'Listed by owner' }}</b></div>
      </div>
      @if ($item['tags'])
        <div class="chip-row">@foreach ($item['tags'] as $t)<span class="pill" style="background:var(--blush)">{{ $t }}</span>@endforeach</div>
      @endif
      <p class="small muted">Never pay a deposit to a personal number. On scharge, deposits go to the property's account and you get a receipt.</p>
      <p><a class="btn btn--ghost" href="{{ route('home.listings', $stay ? ['type' => 'stays'] : []) }}#browse">{{ $stay ? 'See all short stays' : 'See all homes to rent' }}</a></p>
    </div>

    <div class="card card--blush" id="request" style="flex:1 1 300px">
      <form class="form" method="POST" action="{{ route('site.listings.request') }}" data-remote novalidate>@csrf
        <div class="fields form">
          <h2 style="font-size:1.3rem">{{ $action }}</h2>
          <label for="v-name">Your name</label><input id="v-name" name="name" required maxlength="120" autocomplete="name">
          <label for="v-ph">Phone (WhatsApp)</label><input id="v-ph" name="phone" type="tel" required maxlength="40" autocomplete="tel">
          <label for="v-when">{{ $stay ? 'Check-in and check-out' : 'Preferred day' }}</label><input id="v-when" name="when" maxlength="120" placeholder="{{ $stay ? 'e.g. 17–18 Oct' : 'e.g. Saturday morning' }}">
          <input type="hidden" name="listing_title" value="{{ $item['title'] }}"><input type="hidden" name="listing_area" value="{{ $item['area'] }}">
          <input type="hidden" name="listing_owner" value="{{ $item['owner'] }}"><input type="hidden" name="listing_kind" value="{{ $stay ? 'Short stays' : 'Homes to rent' }}">
          <input type="hidden" name="listing_action" value="{{ $action }}"><input type="hidden" name="listing_slug" value="{{ $item['slug'] }}">
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="btn btn--primary" type="submit">{{ $stay ? 'Request booking' : 'Request viewing' }}</button>
          <p class="small muted">Free. {{ $stay ? 'The host' : 'The owner or caretaker' }} replies on WhatsApp.</p>
        </div>
        <div class="thanks stack" tabindex="-1"><h3>Request sent.</h3><p class="muted">{{ $stay ? 'The host confirms on WhatsApp, then you pay a deposit on M-Pesa.' : 'The caretaker will WhatsApp you a time today.' }}</p></div>
      </form>
    </div>
  </div>
</div></section>
@endsection

@push('jsonld')
{!! \App\Support\Seo::script(['@context' => 'https://schema.org'] + \App\Support\Seo::listing($item)) !!}
{!! \App\Support\Seo::script(\App\Support\Seo::breadcrumbs([['Home', route('home')], ['Open listings', route('home.listings')], [$item['title'], $item['url']]])) !!}
@endpush

@push('scripts')
<script>window.addEventListener('load', function () { if (window.listingViewed) window.listingViewed(@js($item['title'])); });</script>
@endpush
