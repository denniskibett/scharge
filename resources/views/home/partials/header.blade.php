@php
    $pages = [
        'home.developers' => 'Developers &amp; landowners',
        'home.airbnb' => 'Airbnb hosts',
        'home.listings' => 'Open listings',
        'home.api' => 'API &amp; white label',
    ];
@endphp
<header class="site-header">
<div class="wrap">
<a class="brand" href="{{ route('home') }}" aria-label="scharge home"><img class="logo-dark" src="{{ asset('assets/images/brand/scharge-logo.svg') }}" alt="scharge" width="124" height="30"><img class="logo-light" src="{{ asset('assets/images/brand/scharge-logo-white.svg') }}" alt="" width="124" height="30"></a>
<button class="nav-toggle" aria-controls="site-nav" aria-expanded="false" aria-label="Menu"><span></span></button>
<nav class="nav" id="site-nav" aria-label="Main">@foreach ($pages as $name => $label)<a href="{{ route($name) }}"@if (request()->routeIs($name)) aria-current="page"@endif>{!! $label !!}</a>@endforeach @hasSection('header_cta')@yield('header_cta')@else<a class="btn btn--primary" href="{{ route('home') }}#pricing">Get started</a>@endif<span class="nav-auth">@auth<a class="btn btn--outline" href="{{ route('dashboard') }}">Dashboard</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="nav-login">Log out</button></form>@else<a class="nav-login" href="{{ route('login') }}">Log in</a><a class="btn btn--outline" href="{{ route('register') }}">Register</a>@endauth</span></nav>
</div>
@yield('subnav')
</header>
