{{-- One answer-style guide (HomeController@guide). Content: App\Support\Guides. --}}
@extends('home.layout')

@section('title', $guide['title'])
@section('description', $guide['description'])
@section('canonical', \App\Support\Guides::url($guide['slug']))

@section('header_cta')
<a class="btn btn--primary" href="{{ \App\Support\Guides::ctaUrl($guide) }}">{{ $guide['cta'][0] }}</a>
@endsection

@push('styles')
<style>
.guide{max-width:780px}
.guide h2{font-size:clamp(1.25rem,2.4vw,1.6rem);margin-top:8px}
.guide p,.guide li{line-height:1.65}
.guide ul,.guide ol{padding-left:22px;display:flex;flex-direction:column;gap:8px}
.guide table{width:100%;border-collapse:collapse;font-size:.95rem;display:block;overflow-x:auto}
.guide th,.guide td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:top}
.guide th{font-weight:600;background:var(--blush)}
.guide .answer{border-left:4px solid var(--red);background:var(--blush);padding:18px 20px;border-radius:0 18px 18px 0;font-size:1.06rem}
</style>
@endpush

@section('content')
<article class="section"><div class="wrap stack-l guide">
  <nav aria-label="Breadcrumb" class="small muted"><a href="{{ route('home') }}">Home</a> › <a href="{{ route('home.guides') }}">Guides</a></nav>
  <header class="stack">
    <h1 style="font-size:clamp(1.8rem,4.2vw,2.8rem)">{{ $guide['h1'] }}</h1>
    <p class="small muted">By the scharge team, Nairobi · Updated <time datetime="{{ \App\Support\Guides::UPDATED }}">{{ \Illuminate\Support\Carbon::parse(\App\Support\Guides::UPDATED)->format('j F Y') }}</time></p>
  </header>

  <p class="answer"><b>Short answer:</b> {{ $guide['answer'] }}</p>

  @foreach ($guide['sections'] as [$heading, $html])
    <section class="stack"><h2>{{ $heading }}</h2>{!! $html !!}</section>
  @endforeach

  <section class="stack" aria-labelledby="faq-h">
    <h2 id="faq-h">Questions people ask</h2>
    <div class="faq stack">@foreach ($guide['faqs'] as [$q, $a])<details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>@endforeach</div>
  </section>

  <div class="section--red row" style="border-radius:28px;padding:clamp(22px,4vw,40px);align-items:center;justify-content:space-between;gap:16px">
    <div class="stack" style="max-width:32rem"><h2 style="margin:0">Try it on your property</h2><p style="margin:0">Free set-up and free import of your tenant list. Priced per unit by area, from KES 40 a month.</p></div>
    <div class="actions"><a class="btn btn--light" href="{{ \App\Support\Guides::ctaUrl($guide) }}">{{ $guide['cta'][0] }}</a><a class="btn btn--glass" href="{{ route('home.developers') }}#talk">Book a demo</a></div>
  </div>

  <nav class="stack" aria-label="More guides">
    <h2 style="font-size:1.2rem">More guides</h2>
    <ul>@foreach ($others as $slug => $g)<li><a href="{{ \App\Support\Guides::url($slug) }}">{{ $g['h1'] }}</a></li>@endforeach</ul>
  </nav>
</div></article>
@endsection

@push('jsonld')
@foreach (\App\Support\Guides::jsonLd($guide) as $ld)
{!! \App\Support\Seo::script($ld) !!}
@endforeach
@endpush
