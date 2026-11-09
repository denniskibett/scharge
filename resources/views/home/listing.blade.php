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
@section('description', $kind.' in '.$item['area'].': '.$item['title'].', '.$price.' '.$per.'. '.$item['costs'].'. '.$item['avail'].'. Book a viewing free, no agent fees.')
@section('canonical', $item['url'])
@if (! empty($item['photos'][0]))
@section('og_image', $item['photos'][0])
@endif

@section('header_cta')
<a class="btn btn--primary" href="#request">{{ $action }}</a>
@endsection

@section('content')
<section class="section"><div class="wrap stack-l">
  <nav aria-label="Breadcrumb" class="small muted"><a href="{{ route('home') }}">Home</a> › <a href="{{ route('home.listings') }}">Open listings</a> › <span aria-current="page">{{ $item['title'] }}</span></nav>
  <div class="row" style="justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap">
    <div class="stack" style="max-width:44rem">
      <span class="pill" style="background:var(--blush);align-self:flex-start">{{ $item['avail'] }}</span>
      <h1 style="font-size:clamp(1.8rem,4vw,3rem)">{{ $item['title'] }}</h1>
      <p class="muted">{{ $kind }} in {{ $item['area'] }}, listed by {{ $item['owner'] }}.</p>
    </div>
    <p class="listing-price" style="font-size:1.6rem">{{ $price }} <span class="small muted" style="font-family:var(--font-body)">{{ $per }}</span></p>
  </div>

  @if (! empty($item['photos']))
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,220px),1fr));gap:10px">
      @foreach (array_slice($item['photos'], 0, 12) as $i => $photo)
        <a href="{{ $photo }}" target="_blank" rel="noopener"><img src="{{ $photo }}" alt="{{ $item['title'] }}, photo {{ $i + 1 }}" loading="{{ $i < 3 ? 'eager' : 'lazy' }}" width="440" height="300" style="width:100%;height:200px;object-fit:cover;border-radius:16px"></a>
      @endforeach
    </div>
  @else
    <img src="{{ $item['photo'] }}" alt="" width="640" height="420" style="width:100%;max-width:640px;border-radius:16px">
  @endif

  <div class="detail-page" style="display:flex;flex-wrap:wrap;gap:24px">
    <div class="stack" style="flex:1 1 300px">
      <h2 style="font-size:1.3rem">Every cost, up front</h2>
      <div>
        <div class="kv"><span class="muted">{{ $stay ? 'Per night' : 'Rent' }}</span><b>{{ $price }}</b></div>
        <div class="kv"><span class="muted">Other costs</span><b>{{ $item['costs'] }}</b></div>
        <div class="kv"><span class="muted">Availability</span><b>{{ $item['avail'] }}</b></div>
        <div class="kv"><span class="muted">Inspected by scharge</span><b>{{ $item['verified'] ? 'Yes' : 'Listed by owner' }}</b></div>
      </div>
      @if ($item['tags'])
        <div class="chip-row">@foreach ($item['tags'] as $t)<span class="pill" style="background:var(--blush)">{{ $t }}</span>@endforeach</div>
      @endif
      <p class="small muted">Never pay a deposit to a personal number. On scharge, deposits go to the property's account and you get a receipt.</p>
      <p><a class="btn btn--ghost" href="{{ route('home.listings') }}#browse">See all vacancies</a></p>
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
          <input type="hidden" name="listing_action" value="{{ $action }}">
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="btn btn--primary" type="submit">{{ $stay ? 'Request booking' : 'Request viewing' }}</button>
          <p class="small muted">Free. The owner or caretaker replies on WhatsApp.</p>
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
