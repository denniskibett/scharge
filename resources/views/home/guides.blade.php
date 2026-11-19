{{-- Guides index (HomeController@guides). Content: App\Support\Guides. --}}
@extends('home.layout')

@section('title', 'Landlord & property management guides for Kenya | scharge')
@section('description', 'Straight answers for Kenyan landlords, agents and Airbnb hosts: rent collection on M-Pesa, water billing, service charge, short-stay management and software prices.')

@section('header_cta')
<a class="btn btn--primary" href="{{ route('home') }}#pricing">Price my property</a>
@endsection

@section('content')
<section class="section"><div class="wrap stack-l" style="max-width:900px">
  <header class="stack">
    <h1 style="font-size:clamp(1.9rem,4.4vw,3rem)">Guides for landlords, agents and hosts in Kenya</h1>
    <p class="muted">Short, practical answers from the team that builds scharge in Nairobi.</p>
  </header>
  <div class="grid">
    @foreach ($guides as $slug => $g)
      <article class="card stack">
        <h2 style="font-size:1.15rem"><a href="{{ \App\Support\Guides::url($slug) }}" style="color:inherit">{{ $g['h1'] }}</a></h2>
        <p class="small muted">{{ $g['description'] }}</p>
        <p><a href="{{ \App\Support\Guides::url($slug) }}">Read the answer →</a></p>
      </article>
    @endforeach
  </div>
</div></section>
@endsection

@push('jsonld')
{!! \App\Support\Seo::script([
    '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Guides for landlords, agents and hosts in Kenya', 'url' => route('home.guides'),
    'hasPart' => array_values(array_map(fn ($slug) => ['@type' => 'Article', 'headline' => $guides[$slug]['h1'], 'url' => \App\Support\Guides::url($slug)], array_keys($guides))),
]) !!}
@endpush
