{{-- Landing pages for Kenyan search intent (HomeController@landing). Content: App\Support\SeoLandings. --}}
@extends('home.layout')
@php
    $url = route('landing.'.$slug);
    [$ctaText, $ctaRoute, $ctaHash] = $landing['cta'];
@endphp

@section('title', $landing['title'])
@section('description', $landing['description'])
@section('canonical', $url)

@section('header_cta')
<a class="btn btn--primary" href="{{ route($ctaRoute) }}{{ $ctaHash }}">{{ $ctaText }}</a>
@endsection

@push('styles')
<style>.landing-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr))}
.landing-listing{display:flex;flex-direction:column;gap:6px;padding:0;overflow:hidden}.landing-listing img{width:100%;height:170px;object-fit:cover;display:block}
.landing-listing .stack{padding:14px 16px 16px;gap:4px}.landing-listing a{color:inherit}
.landing-related{display:flex;flex-wrap:wrap;gap:10px}</style>
@endpush

@section('content')
<section class="section"><div class="wrap stack-l">
  <nav aria-label="Breadcrumb" class="small muted"><a href="{{ route('home') }}">Home</a> › <span aria-current="page">{{ $landing['h1'] }}</span></nav>
  <div class="stack" style="max-width:52rem">
    <span class="label">{{ $landing['kicker'] }}</span>
    <h1 style="font-size:clamp(1.9rem,4.4vw,3.2rem)">{{ $landing['h1'] }}</h1>
    <p class="lead">{{ $landing['lead'] }}</p>
    <div class="actions"><a class="btn btn--primary" href="{{ route($ctaRoute) }}{{ $ctaHash }}">{{ $ctaText }}</a><a class="btn btn--ghost" href="{{ route('home.developers') }}#talk">Book a demo</a></div>
  </div>
</div></section>

@if (! empty($landing['listings']))
<section class="section section--blush" id="vacancies" aria-labelledby="vacancies-h"><div class="wrap stack-l">
  <h2 id="vacancies-h">Vacant now</h2>
  @php($rent = collect($listingItems)->where('k', \App\Services\ListingCatalog::KIND_RENT)->take(12))
  @if ($rent->isNotEmpty())
    <div class="landing-grid">
      @foreach ($rent as $h)
        <article class="card landing-listing">
          <img src="{{ $h['photo'] }}" alt="{{ $h['title'] }}, {{ $h['area'] }}" width="400" height="170" loading="lazy">
          <div class="stack">
            <h3 style="font-size:1.05rem;margin:0"><a href="{{ $h['url'] }}">{{ $h['title'] }}</a></h3>
            <span class="small muted">{{ $h['area'] }} · {{ $h['avail'] }}</span>
            <span class="listing-price">KES {{ number_format($h['price']) }} <span class="small muted" style="font-family:var(--font-body)">a month</span></span>
            <span class="small muted">{{ $h['costs'] }}</span>
          </div>
        </article>
      @endforeach
    </div>
  @else
    <p class="card">No vacancies are listed right now. <a href="{{ route('home.listings') }}#browse">Leave your number on the listings page</a> and we will text you when a matching house comes up.</p>
  @endif
  <p><a class="btn btn--ghost" href="{{ route('home.listings') }}#browse">See every vacancy and short stay</a></p>
</div></section>
@endif

@foreach ($landing['sections'] as $i => [$heading, $body])
<section class="section{{ $i % 2 === 1 ? ' section--blush' : '' }}"><div class="wrap stack-l">
  <h2>{{ $heading }}</h2>
  @if (is_array($body[0] ?? null))
    <div class="grid">@foreach ($body as [$title, $text])<div class="card"><h3>{{ $title }}</h3><p>{{ $text }}</p></div>@endforeach</div>
  @else
    <div class="stack" style="max-width:52rem">@foreach ($body as $para)<p>{!! $para !!}</p>@endforeach</div>
  @endif
</div></section>
@endforeach

<section class="section section--blush" id="faq"><div class="wrap stack-l" style="max-width:860px">
  <h2>Questions landlords ask</h2>
  <div class="faq stack">@foreach ($landing['faqs'] as [$q, $a])<details><summary>{{ $q }}</summary><p>{!! $a !!}</p></details>@endforeach</div>
</div></section>

<section class="section"><div class="wrap stack">
  <h2 style="font-size:1.3rem">Related</h2>
  <nav class="landing-related" aria-label="Related pages">
    @foreach ($landing['related'] as $other)<a class="btn btn--outline" href="{{ route('landing.'.$other) }}">{{ \App\Support\SeoLandings::LINK_TEXT[$other] }}</a>@endforeach
    <a class="btn btn--outline" href="{{ route('home') }}#pricing">Pricing</a>
  </nav>
</div></section>
@endsection

@push('jsonld')
{!! \App\Support\Seo::script(\App\Support\Seo::webPage($url, $landing['h1'], $landing['description'])) !!}
{!! \App\Support\Seo::script(\App\Support\Seo::faq($landing['faqs'])) !!}
{!! \App\Support\Seo::script(\App\Support\Seo::breadcrumbs([['Home', route('home')], [$landing['h1'], $url]])) !!}
@if (! empty($landing['listings']) && ! empty($listingItems))
{!! \App\Support\Seo::script(\App\Support\Seo::listingList(collect($listingItems)->where('k', \App\Services\ListingCatalog::KIND_RENT)->take(12)->values()->all())) !!}
@endif
@endpush
